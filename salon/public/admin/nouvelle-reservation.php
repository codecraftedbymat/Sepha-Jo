<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once SALON_BASE . '/includes/creneaux.php';
require_once SALON_BASE . '/includes/notifications.php';

// Un serveur SMTP injoignable ne doit pas bloquer l'administration.
@set_time_limit(30);

$prestations = $conn->query('
    SELECT Id, Service, Delay, Prices
    FROM Services
    WHERE Active = 1
    ORDER BY Service ASC
')->fetchAll();

if (!$prestations) {
    admin_header('Nouvelle réservation', 'nouvelle');
    echo '<div class="flash flash-err">Aucune prestation active. Créez-en une depuis le menu Prestations avant d\'enregistrer un rendez-vous.</div>';
    admin_footer();
    exit;
}

/* --- Sélection en cours ------------------------------------------------ */
$prestationId = (int) ($_POST['ServiceId'] ?? $prestations[0]['id']);
$date         = $_POST['date']  ?? date('Y-m-d');
$heure        = $_POST['heure'] ?? '';
$forcer       = !empty($_POST['forcer']);

$erreur = '';

/* --- Enregistrement ---------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer') {
    csrf_check();

    $nom      = trim($_POST['ClientName'] ?? '');
    $email    = trim($_POST['ClientEmail'] ?? '');
    $tel      = trim($_POST['ClientTel'] ?? '');
    $prevenir = !empty($_POST['prevenir']);

    $stmt = $conn->prepare('SELECT Id, Service, Delay, Prices FROM Services WHERE Id = :id');
    $stmt->execute([':id' => $prestationId]);
    $prestation = $stmt->fetch();

    if (!$prestation) {
        $erreur = "Prestation introuvable.";
    } elseif ($nom === '') {
        $erreur = "Le nom de la cliente est obligatoire.";
    } elseif ($tel === '' && $email === '') {
        $erreur = "Renseignez au moins un moyen de contact : téléphone ou e-mail.";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Cette adresse e-mail n'est pas valide.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $heure)) {
        $erreur = "Choisissez un créneau.";
    } else {
        $duree     = (int) $prestation['Delay'];
        $dateDebut = $date . ' ' . $heure . ':00';
        $dateFin   = date('Y-m-d H:i:s', strtotime($dateDebut) + $duree * 60);

        // Hors forçage, le créneau doit appartenir aux créneaux ouverts.
        $valide = true;
        if (!$forcer) {
            $libres = array_column(array_filter(
                creneaux_du_jour($conn, $date, $duree, 0, true),
                static fn($c) => $c['libre']
            ), 'debut');
            $valide = in_array($heure, $libres, true);
        }

        if (!$valide) {
            $erreur = "Ce créneau n'est pas disponible. Choisissez-en un autre, ou cochez « forcer ».";
        } else {
            $conflit = $conn->prepare("
                SELECT COUNT(*) FROM Reservations
                WHERE Status = 'confirmed' AND StartDate < :fin AND EndDate > :debut
            ");
            $conflit->execute([':debut' => $dateDebut, ':fin' => $dateFin]);

            if ($conflit->fetchColumn() > 0 && !$forcer) {
                $erreur = "Ce créneau chevauche un autre rendez-vous.";
            } else {
                // L'e-mail est facultatif ici : une cliente au téléphone n'en
                // donne pas toujours. On stocke une valeur neutre le cas échéant.
                $emailStocke = $email !== '' ? $email : 'non-communique@' . 'local';

                $conn->prepare('
                    INSERT INTO Reservations
                        (ServiceId, ClientName, ClientEmail, ClientTel, StartDate, EndDate, Status)
                    VALUES (:p, :n, :e, :t, :debut, :fin, \'confirmed\')
                ')->execute([
                    ':p'     => $prestationId,
                    ':n'     => $nom,
                    ':e'     => $emailStocke,
                    ':t'     => $tel !== '' ? $tel : '—',
                    ':debut' => $dateDebut,
                    ':fin'   => $dateFin,
                ]);

                $id = (int) $conn->lastInsertId();
                $message = 'Rendez-vous enregistré pour ' . $nom . '.';

                if ($prevenir && $email !== '') {
                    $envoye = notifier_client([
                        'Id'           => $id,
                        'prestation'   => $prestation['Service'],
                        'Delay'        => $duree,
                        'Prices'       => $prestation['Prices'],
                        'ClientName'   => $nom,
                        'ClientEmail'  => $email,
                        'ClientTel'    => $tel,
                        'StartDate'    => $dateDebut,
                        'EndDate'      => $dateFin,
                        'date_longue'  => fmt_date_longue($date),
                        'heure_debut'  => date('H\hi', strtotime($dateDebut)),
                        'heure_fin'    => date('H\hi', strtotime($dateFin)),
                    ]);

                    $message .= $envoye
                        ? ' Confirmation envoyée par e-mail.'
                        : " L'e-mail de confirmation n'a pas pu être envoyé.";
                }

                header('Location: reservations.php?ok=' . urlencode($message));
                exit;
            }
        }
    }
}

/* --- Créneaux du jour affiché ------------------------------------------ */
$prestationChoisie = null;
foreach ($prestations as $p) {
    if ((int) $p['Id'] === $prestationId) {
        $prestationChoisie = $p;
    }
}
$dureeAffichee = $prestationChoisie ? (int) $prestationChoisie['Delay'] : 30;

$creneaux = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    ? creneaux_du_jour($conn, $date, $dureeAffichee, 0, true)
    : [];

admin_header('Nouvelle réservation', 'nouvelle');
?>

<p style="margin:-6px 0 20px;">
    <span class="dim" style="display:inline;">
        Pour enregistrer un rendez-vous pris par téléphone, en boutique ou par message.
    </span>
</p>

<?php if ($erreur !== '') : ?>
    <div class="flash flash-err"><?= e($erreur) ?></div>
<?php endif; ?>

<form method="post" id="formNouvelle">
    <?= csrf_field() ?>

    <section class="card">
        <div class="card-head"><h2>Prestation et créneau</h2></div>

        <div class="row-form" style="margin-bottom:20px;">
            <div class="fld grow">
                <label for="ServiceId">Prestation</label>
                <select id="ServiceId" name="ServiceId" onchange="document.getElementById('formNouvelle').submit();">
                    <?php foreach ($prestations as $p) : ?>
                        <option value="<?= (int) $p['Id'] ?>" <?= (int) $p['Id'] === $prestationId ? 'selected' : '' ?>>
                            <?= e($p['Service']) ?> — <?= (int) $p['Delay'] ?> min<?= $p['Prices'] !== null ? ' — ' . e($p['Prices']) . ' €' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fld">
                <label for="date">Date</label>
                <input id="date" name="date" type="date" value="<?= e($date) ?>"
                       onchange="document.getElementById('formNouvelle').submit();">
            </div>
            <div class="fld">
                <label for="heure">Horaire retenu</label>
                <input id="heure" name="heure" type="time" step="300" value="<?= e($heure) ?>">
            </div>
        </div>

        <?php if (!$creneaux) : ?>
            <p class="empty">Le salon est fermé ce jour-là. Saisissez un horaire à la main et cochez « forcer » pour ouvrir exceptionnellement.</p>
        <?php else : ?>
            <p class="dim" style="margin-bottom:10px;">Cliquez un créneau libre pour le retenir.</p>
            <div class="slots-grid">
                <?php foreach ($creneaux as $c) : ?>
                    <button type="button"
                            class="slot<?= $c['libre'] ? '' : ' is-taken' ?><?= $c['debut'] === $heure ? ' is-selected' : '' ?>"
                            data-heure="<?= e($c['debut']) ?>"
                            <?= $c['libre'] ? '' : 'disabled' ?>><?= e($c['label']) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <label class="check-line" style="margin-top:18px;">
            <input type="checkbox" name="forcer" value="1" <?= $forcer ? 'checked' : '' ?>>
            Forcer cet horaire, même en dehors des créneaux proposés
        </label>
    </section>

    <section class="card">
        <div class="card-head"><h2>Cliente</h2></div>
        <div class="form-grid">
            <div class="fld">
                <label for="ClientName">Nom <span class="dim" style="display:inline;">(obligatoire)</span></label>
                <input id="ClientName" name="ClientName" type="text" value="<?= e($_POST['ClientName'] ?? '') ?>" autofocus>
            </div>
            <div class="fld">
                <label for="ClientTel">Téléphone</label>
                <input id="ClientTel" name="ClientTel" type="text" value="<?= e($_POST['ClientTel'] ?? '') ?>" placeholder="0477 59 67 69">
            </div>
            <div class="fld full">
                <label for="ClientEmail">E-mail <span class="dim" style="display:inline;">(facultatif)</span></label>
                <input id="ClientEmail" name="ClientEmail" type="email" value="<?= e($_POST['ClientEmail'] ?? '') ?>" placeholder="laissez vide si la cliente ne l'a pas donné">
            </div>
        </div>

        <label class="check-line" style="margin-top:18px;">
            <input type="checkbox" name="prevenir" value="1" checked>
            Envoyer la confirmation par e-mail, si une adresse est renseignée
        </label>

        <div class="cta-row" style="margin-top:22px;">
            <a class="btn" href="reservations.php">Annuler</a>
            <button class="btn btn-primary" name="action" value="creer">Enregistrer le rendez-vous</button>
        </div>
    </section>
</form>

<script>
    document.querySelectorAll('.slot[data-heure]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('heure').value = btn.dataset.heure;
            document.querySelectorAll('.slot').forEach(function (s) { s.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
        });
    });
</script>

<?php admin_footer(); ?>
