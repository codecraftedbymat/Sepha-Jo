<?php
/**
 * Logique métier des créneaux.
 *
 * Les horaires d'ouverture, la pause déjeuner et les fermetures
 * exceptionnelles sont stockés en base et modifiables depuis
 * l'administration (menu Disponibilités). Les constantes de
 * config.php ne servent plus que de valeurs de repli, le temps
 * qu'une installation soit initialisée.
 */

require_once __DIR__ . '/config.php';

function esc($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/* =======================================================================
   Lecture des réglages en base, avec mise en cache par requête HTTP
   ======================================================================= */

/**
 * Horaires d'ouverture, indexés par jour (0 = dimanche … 6 = samedi).
 * Valeur : [heureOuverture, heureFermeture] en minutes, ou null si fermé.
 */
function horaires_semaine(PDO $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        $lignes = $conn->query('SELECT DayOfWeek, OpenMinute, CloseMinute, IsOpen FROM OpeningHours')->fetchAll();
        foreach ($lignes as $l) {
            $cache[(int) $l['DayOfWeek']] = ((int) $l['IsOpen'] === 1)
                ? [(int) $l['OpenMinute'], (int) $l['CloseMinute']]
                : null;
        }
    } catch (Throwable $e) {
        $cache = [];
    }

    // Repli sur config.php si la table est absente ou vide.
    if (!$cache) {
        foreach (HORAIRES as $jour => $h) {
            $cache[$jour] = $h === null ? null : [$h[0] * 60, $h[1] * 60];
        }
    }

    return $cache;
}

/**
 * Réglages divers : pause déjeuner, granularité, délai minimum, horizon.
 */
function reglage(PDO $conn, string $cle, $defaut)
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            foreach ($conn->query('SELECT SettingKey, SettingValue FROM Settings')->fetchAll() as $l) {
                $cache[$l['SettingKey']] = $l['SettingValue'];
            }
        } catch (Throwable $e) {
            $cache = [];
        }
    }

    return array_key_exists($cle, $cache) && $cache[$cle] !== '' ? $cache[$cle] : $defaut;
}

/**
 * Pause déjeuner en minutes : [début, fin], ou null si aucune.
 */
function pause_dejeuner(PDO $conn): ?array
{
    $active = (int) reglage($conn, 'BreakEnabled', PAUSE_DEJEUNER !== null ? 1 : 0);
    if ($active !== 1) {
        return null;
    }

    $debut = (int) reglage($conn, 'BreakStart', PAUSE_DEJEUNER ? PAUSE_DEJEUNER[0] * 60 : 720);
    $fin   = (int) reglage($conn, 'BreakEnd',   PAUSE_DEJEUNER ? PAUSE_DEJEUNER[1] * 60 : 780);

    return $fin > $debut ? [$debut, $fin] : null;
}

/**
 * Fermetures exceptionnelles : liste de ['StartDate', 'EndDate', 'Reason'].
 */
function fermetures(PDO $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    try {
        $cache = $conn->query('
            SELECT Id, StartDate, EndDate, Reason
            FROM Closures
            ORDER BY StartDate ASC
        ')->fetchAll();
    } catch (Throwable $e) {
        // Repli sur les dates de config.php
        $cache = [];
        foreach (FERMETURES as $d) {
            $cache[] = ['Id' => 0, 'StartDate' => $d, 'EndDate' => $d, 'Reason' => ''];
        }
    }

    return $cache;
}

/**
 * Motif de fermeture d'une date, ou null si le salon est ouvert.
 * Renvoie une chaîne vide si fermé sans motif précisé.
 */
function motif_fermeture(PDO $conn, string $date): ?string
{
    foreach (fermetures($conn) as $f) {
        if ($date >= $f['StartDate'] && $date <= $f['EndDate']) {
            return (string) $f['Reason'];
        }
    }
    return null;
}

/* =======================================================================
   Disponibilité
   ======================================================================= */

function jour_ferme(PDO $conn, string $date): bool
{
    if (motif_fermeture($conn, $date) !== null) {
        return true;
    }

    $dow = (int) date('w', strtotime($date));
    return (horaires_semaine($conn)[$dow] ?? null) === null;
}

/**
 * Renvoie les créneaux d'une journée pour une prestation donnée.
 *
 * @param int  $ignorerId  Réservation à ne pas compter comme occupante.
 *                         Utilisé lors d'une modification : sans cela, le
 *                         rendez-vous entrerait en conflit avec lui-même.
 * @param bool $admin      Mode administration : lève le délai minimum et
 *                         autorise les horaires déjà passés, pour saisir un
 *                         rendez-vous pris par téléphone ou rattraper un oubli.
 *
 * @return array liste de ['debut' => 'HH:MM', 'label' => '09h00', 'libre' => bool]
 */
function creneaux_du_jour(PDO $conn, string $date, int $duree, int $ignorerId = 0, bool $admin = false): array
{
    if (jour_ferme($conn, $date)) {
        return [];
    }

    $dow = (int) date('w', strtotime($date));
    [$ouverture, $fermeture] = horaires_semaine($conn)[$dow];

    // Réservations déjà posées ce jour-là (on ignore les annulations)
    $sql = "SELECT StartDate, EndDate
            FROM Reservations
            WHERE Status = 'confirmed' AND DATE(StartDate) = :d";
    $params = [':d' => $date];

    if ($ignorerId > 0) {
        $sql .= ' AND Id <> :ignore';
        $params[':ignore'] = $ignorerId;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $occupes = [];
    foreach ($stmt->fetchAll() as $r) {
        $occupes[] = [
            'debut' => (int) date('H', strtotime($r['StartDate'])) * 60 + (int) date('i', strtotime($r['StartDate'])),
            'fin'   => (int) date('H', strtotime($r['EndDate']))   * 60 + (int) date('i', strtotime($r['EndDate'])),
        ];
    }

    $pas   = max(5, (int) reglage($conn, 'SlotStep', PAS_CRENEAU));
    $pause = pause_dejeuner($conn);

    // Seuil : on n'accepte pas un rendez-vous trop imminent
    $delai = (int) reglage($conn, 'MinDelayHours', DELAI_MIN_HEURES);
    $seuil = strtotime('+' . $delai . ' hours');

    $creneaux = [];
    for ($debut = $ouverture; $debut + $duree <= $fermeture; $debut += $pas) {
        $fin = $debut + $duree;

        $libre = true;

        // Chevauchement avec une réservation existante
        foreach ($occupes as $o) {
            if ($debut < $o['fin'] && $fin > $o['debut']) {
                $libre = false;
                break;
            }
        }

        // Chevauchement avec la pause déjeuner
        if ($libre && $pause !== null) {
            if ($debut < $pause[1] && $fin > $pause[0]) {
                $libre = false;
            }
        }

        // Créneau déjà passé ou trop proche (ignoré en mode administration)
        if ($libre && !$admin) {
            $ts = strtotime($date . ' ' . sprintf('%02d:%02d', intdiv($debut, 60), $debut % 60));
            if ($ts < $seuil) {
                $libre = false;
            }
        }

        $creneaux[] = [
            'debut' => sprintf('%02d:%02d', intdiv($debut, 60), $debut % 60),
            'label' => sprintf('%02dh%02d', intdiv($debut, 60), $debut % 60),
            'libre' => $libre,
        ];
    }

    return $creneaux;
}

/**
 * Liste des jours proposés au client, avec l'information « ouvert ou non ».
 */
function jours_proposes(PDO $conn): array
{
    $jours = [];
    $noms  = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    $mois  = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];

    $horizon = max(1, (int) reglage($conn, 'DaysAhead', JOURS_AHEAD));

    for ($i = 0; $i < $horizon; $i++) {
        $ts   = strtotime("+$i day");
        $date = date('Y-m-d', $ts);
        $jours[] = [
            'date'  => $date,
            'dow'   => $noms[(int) date('w', $ts)],
            'num'   => date('j', $ts),
            'mois'  => $mois[(int) date('n', $ts) - 1],
            'ferme' => jour_ferme($conn, $date),
        ];
    }
    return $jours;
}

function fmt_date_longue(string $date): string
{
    $ts    = strtotime($date);
    $jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
    $mois  = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    return $jours[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $mois[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function minutes_vers_hhmm(int $m): string
{
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

function hhmm_vers_minutes(string $hhmm): int
{
    [$h, $m] = array_map('intval', explode(':', $hhmm . ':0'));
    return $h * 60 + $m;
}
