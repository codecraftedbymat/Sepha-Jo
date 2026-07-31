<?php
/**
 * Logique métier des créneaux : c'est ici que se calcule la disponibilité,
 * en fonction de la durée de la prestation et des réservations déjà prises.
 */

require_once __DIR__ . '/config.php';

function esc($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function jour_ferme(string $date): bool
{
    if (in_array($date, FERMETURES, true)) {
        return true;
    }
    $dow = (int) date('w', strtotime($date));
    return HORAIRES[$dow] === null;
}

/**
 * Renvoie les créneaux d'une journée pour une prestation donnée.
 *
 * @return array liste de ['debut' => 'HH:MM', 'label' => '09h00', 'libre' => bool]
 */
function creneaux_du_jour(PDO $conn, string $date, int $duree): array
{
    if (jour_ferme($date)) {
        return [];
    }

    $dow    = (int) date('w', strtotime($date));
    [$ho, $hf] = HORAIRES[$dow];
    $ouverture  = $ho * 60;
    $fermeture  = $hf * 60;

    // Réservations déjà posées ce jour-là (on ignore les annulations)
    $stmt = $conn->prepare("
        SELECT date_debut, date_fin
        FROM reservations
        WHERE statut = 'confirmee' AND DATE(date_debut) = :d
    ");
    $stmt->execute([':d' => $date]);

    $occupes = [];
    foreach ($stmt->fetchAll() as $r) {
        $occupes[] = [
            'debut' => (int) date('H', strtotime($r['date_debut'])) * 60 + (int) date('i', strtotime($r['date_debut'])),
            'fin'   => (int) date('H', strtotime($r['date_fin']))   * 60 + (int) date('i', strtotime($r['date_fin'])),
        ];
    }

    // Seuil : on n'accepte pas un rendez-vous trop imminent
    $seuil = strtotime('+' . DELAI_MIN_HEURES . ' hours');

    $creneaux = [];
    for ($debut = $ouverture; $debut + $duree <= $fermeture; $debut += PAS_CRENEAU) {
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
        if ($libre && PAUSE_DEJEUNER !== null) {
            $pd = PAUSE_DEJEUNER[0] * 60;
            $pf = PAUSE_DEJEUNER[1] * 60;
            if ($debut < $pf && $fin > $pd) {
                $libre = false;
            }
        }

        // Créneau déjà passé ou trop proche
        if ($libre) {
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
 * Liste des jours proposés au client, avec l'information "ouvert ou non".
 */
function jours_proposes(): array
{
    $jours = [];
    $noms  = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    $mois  = ['janv.','févr.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];

    for ($i = 0; $i < JOURS_AHEAD; $i++) {
        $ts   = strtotime("+$i day");
        $date = date('Y-m-d', $ts);
        $jours[] = [
            'date'  => $date,
            'dow'   => $noms[(int) date('w', $ts)],
            'num'   => date('j', $ts),
            'mois'  => $mois[(int) date('n', $ts) - 1],
            'ferme' => jour_ferme($date),
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
