<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once SALON_BASE . '/includes/creneaux.php';

$JOURS = [
    1 => 'Lundi',
    2 => 'Mardi',
    3 => 'Mercredi',
    4 => 'Jeudi',
    5 => 'Vendredi',
    6 => 'Samedi',
    0 => 'Dimanche',
];

/* =======================================================================
   Enregistrement
   ======================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    /* --- Horaires hebdomadaires --------------------------------------- */
    if ($action === 'horaires') {
        $stmt = $conn->prepare('
            INSERT INTO OpeningHours (DayOfWeek, OpenMinute, CloseMinute, IsOpen)
            VALUES (:d, :o, :c, :a)
            ON DUPLICATE KEY UPDATE OpenMinute = :o2, CloseMinute = :c2, IsOpen = :a2
        ');

        $souci = '';
        foreach (array_keys($JOURS) as $d) {
            $ouvert = !empty($_POST['open'][$d]) ? 1 : 0;
            $o = hhmm_vers_minutes($_POST['from'][$d] ?? '09:00');
            $c = hhmm_vers_minutes($_POST['to'][$d]   ?? '19:00');

            if ($ouvert && $c <= $o) {
                $souci = 'Pour ' . $JOURS[$d] . ', l\'heure de fermeture doit suivre l\'heure d\'ouverture.';
                continue;
            }

            $stmt->execute([
                ':d' => $d, ':o' => $o, ':c' => $c, ':a' => $ouvert,
                ':o2' => $o, ':c2' => $c, ':a2' => $ouvert,
            ]);
        }

        if ($souci !== '') {
            header('Location: disponibilites.php?err=' . urlencode($souci));
        } else {
            header('Location: disponibilites.php?ok=' . urlencode('Horaires enregistrés.'));
        }
        exit;
    }

    /* --- Réglages généraux -------------------------------------------- */
    if ($action === 'reglages') {
        $valeurs = [
            'BreakEnabled'  => !empty($_POST['break_enabled']) ? 1 : 0,
            'BreakStart'    => hhmm_vers_minutes($_POST['break_start'] ?? '12:00'),
            'BreakEnd'      => hhmm_vers_minutes($_POST['break_end']   ?? '13:00'),
            'SlotStep'      => max(5,  (int) ($_POST['slot_step']  ?? 15)),
            'MinDelayHours' => max(0,  (int) ($_POST['min_delay']   ?? 2)),
            'DaysAhead'     => max(1,  (int) ($_POST['days_ahead']  ?? 30)),
        ];

        $stmt = $conn->prepare('
            INSERT INTO Settings (SettingKey, SettingValue) VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE SettingValue = :v2
        ');
        foreach ($valeurs as $k => $v) {
            $stmt->execute([':k' => $k, ':v' => (string) $v, ':v2' => (string) $v]);
        }

        header('Location: disponibilites.php?ok=' . urlencode('Réglages enregistrés.'));
        exit;
    }

    /* --- Ajout d'une fermeture ---------------------------------------- */
    if ($action === 'fermer') {
        $du     = $_POST['from_date'] ?? '';
        $au     = $_POST['to_date']   ?: $du;
        $motif  = trim($_POST['reason'] ?? '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $du) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $au)) {
            header('Location: disponibilites.php?err=' . urlencode('Indiquez au moins une date de début.'));
            exit;
        }
        if ($au < $du) {
            header('Location: disponibilites.php?err=' . urlencode('La date de fin précède la date de début.'));
            exit;
        }

        // Rendez-vous déjà pris sur la période : on prévient sans bloquer.
        $occ = $conn->prepare("
            SELECT COUNT(*) FROM Reservations
            WHERE Status = 'confirmed' AND DATE(StartDate) BETWEEN :a AND :b
        ");
        $occ->execute([':a' => $du, ':b' => $au]);
        $nb = (int) $occ->fetchColumn();

        $conn->prepare('INSERT INTO Closures (StartDate, EndDate, Reason) VALUES (:a, :b, :r)')
             ->execute([':a' => $du, ':b' => $au, ':r' => $motif]);

        $msg = 'Fermeture enregistrée.';
        if ($nb > 0) {
            $msg .= ' Attention : ' . $nb . ' rendez-vous ' . ($nb > 1 ? 'sont' : 'est')
                  . ' déjà pris sur cette période. Prévenez ' . ($nb > 1 ? 'les clientes' : 'la cliente')
                  . ' et déplacez-' . ($nb > 1 ? 'les' : 'le') . '.';
        }
        header('Location: disponibilites.php?ok=' . urlencode($msg));
        exit;
    }

    /* --- Suppression d'une fermeture ---------------------------------- */
    if ($action === 'rouvrir') {
        $conn->prepare('DELETE FROM Closures WHERE Id = :id')
             ->execute([':id' => (int) ($_POST['id'] ?? 0)]);
        header('Location: disponibilites.php?ok=' . urlencode('Période rouverte.'));
        exit;
    }
}

/* =======================================================================
   Lecture — sans passer par le cache, pour refléter l'enregistrement
   ======================================================================= */
$horaires = [];
foreach ($conn->query('SELECT DayOfWeek, OpenMinute, CloseMinute, IsOpen FROM OpeningHours')->fetchAll() as $l) {
    $horaires[(int) $l['DayOfWeek']] = $l;
}

$reglages = [];
foreach ($conn->query('SELECT SettingKey, SettingValue FROM Settings')->fetchAll() as $l) {
    $reglages[$l['SettingKey']] = $l['SettingValue'];
}
$reg = static fn(string $k, $d) => $reglages[$k] ?? $d;

$listeFermetures = $conn->query('
    SELECT Id, StartDate, EndDate, Reason
    FROM Closures
    ORDER BY StartDate DESC
')->fetchAll();

$aujourdhui = date('Y-m-d');

admin_header('Disponibilités', 'disponibilites');
flash();
?>

<section class="card">
    <div class="card-head">
        <h2>Horaires d'ouverture</h2>
        <span class="dim" style="display:inline;">Décochez un jour pour le fermer toute la semaine.</span>
    </div>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="horaires">

        <div class="hours-table">
            <?php foreach ($JOURS as $d => $nom) :
                $h      = $horaires[$d] ?? null;
                $ouvert = $h ? (int) $h['IsOpen'] === 1 : false;
                $de     = $h ? minutes_vers_hhmm((int) $h['OpenMinute'])  : '09:00';
                $a      = $h ? minutes_vers_hhmm((int) $h['CloseMinute']) : '19:00';
            ?>
                <div class="hours-row<?= $ouvert ? '' : ' is-closed' ?>">
                    <label class="check-line hours-day">
                        <input type="checkbox" name="open[<?= $d ?>]" value="1" <?= $ouvert ? 'checked' : '' ?>>
                        <span><?= e($nom) ?></span>
                    </label>
                    <div class="hours-range">
                        <input type="time" name="from[<?= $d ?>]" value="<?= e($de) ?>" step="900">
                        <span class="dim" style="display:inline;">à</span>
                        <input type="time" name="to[<?= $d ?>]" value="<?= e($a) ?>" step="900">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cta-row" style="margin-top:20px;">
            <button class="btn btn-primary">Enregistrer les horaires</button>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-head"><h2>Réglages de réservation</h2></div>

    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reglages">

        <label class="check-line" style="margin-bottom:16px;">
            <input type="checkbox" name="break_enabled" value="1" <?= (int) $reg('BreakEnabled', 1) === 1 ? 'checked' : '' ?>>
            Appliquer une pause quotidienne
        </label>

        <div class="row-form">
            <div class="fld">
                <label for="break_start">Pause de</label>
                <input id="break_start" name="break_start" type="time" step="900"
                       value="<?= e(minutes_vers_hhmm((int) $reg('BreakStart', 720))) ?>">
            </div>
            <div class="fld">
                <label for="break_end">à</label>
                <input id="break_end" name="break_end" type="time" step="900"
                       value="<?= e(minutes_vers_hhmm((int) $reg('BreakEnd', 780))) ?>">
            </div>
            <div class="fld">
                <label for="slot_step">Créneaux toutes les</label>
                <input id="slot_step" name="slot_step" type="number" min="5" step="5"
                       value="<?= (int) $reg('SlotStep', 15) ?>">
            </div>
            <div class="fld">
                <label for="min_delay">Délai minimum (h)</label>
                <input id="min_delay" name="min_delay" type="number" min="0" step="1"
                       value="<?= (int) $reg('MinDelayHours', 2) ?>">
            </div>
            <div class="fld">
                <label for="days_ahead">Réservable jusqu'à (jours)</label>
                <input id="days_ahead" name="days_ahead" type="number" min="1" step="1"
                       value="<?= (int) $reg('DaysAhead', 30) ?>">
            </div>
            <button class="btn btn-primary">Enregistrer</button>
        </div>

        <p class="dim" style="margin-top:14px;">
            Le délai minimum empêche une réservation trop imminente : à 2 h, une cliente
            ne peut pas réserver pour dans une heure. Il ne s'applique pas aux rendez-vous
            que vous saisissez vous-même.
        </p>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <h2>Fermetures exceptionnelles</h2>
        <span class="dim" style="display:inline;">Congés, jours fériés, absences.</span>
    </div>

    <form method="post" class="row-form" style="margin-bottom:22px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="fermer">
        <div class="fld">
            <label for="from_date">Du</label>
            <input id="from_date" name="from_date" type="date" value="<?= e($aujourdhui) ?>" required>
        </div>
        <div class="fld">
            <label for="to_date">Au <span class="dim" style="display:inline;">(facultatif)</span></label>
            <input id="to_date" name="to_date" type="date">
        </div>
        <div class="fld grow">
            <label for="reason">Motif</label>
            <input id="reason" name="reason" type="text" placeholder="Congés d'été, formation, jour férié…">
        </div>
        <button class="btn btn-primary">Fermer</button>
    </form>

    <?php if (!$listeFermetures) : ?>
        <p class="empty">Aucune fermeture enregistrée.</p>
    <?php else : ?>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Période</th>
                        <th>Motif</th>
                        <th>État</th>
                        <th class="ta-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($listeFermetures as $f) :
                    $passee = $f['EndDate'] < $aujourdhui;
                    $encours = $f['StartDate'] <= $aujourdhui && $f['EndDate'] >= $aujourdhui;
                ?>
                    <tr<?= $passee ? ' class="row-off"' : '' ?>>
                        <td class="mono">
                            <?= e(date('d/m/Y', strtotime($f['StartDate']))) ?>
                            <?php if ($f['EndDate'] !== $f['StartDate']) : ?>
                                → <?= e(date('d/m/Y', strtotime($f['EndDate']))) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $f['Reason'] !== '' ? e($f['Reason']) : '<span class="dim">—</span>' ?></td>
                        <td>
                            <?php if ($encours) : ?>
                                <span class="badge badge-off">En cours</span>
                            <?php elseif ($passee) : ?>
                                <span class="badge badge-off">Passée</span>
                            <?php else : ?>
                                <span class="badge badge-on">À venir</span>
                            <?php endif; ?>
                        </td>
                        <td class="ta-right">
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $f['Id'] ?>">
                                <button class="btn btn-mini btn-danger" name="action" value="rouvrir"
                                        onclick="return confirm('Rouvrir cette période ?');">Rouvrir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="table-foot">
        Les jours fermés apparaissent grisés dans le planning et deviennent
        impossibles à réserver depuis le site.
    </p>
</section>

<?php admin_footer(); ?>
