<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/creneaux.php';
require_once dirname(__DIR__, 3) . '/includes/notifications.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: reservations.php');
    exit;
}

/* --- Réservation courante --------------------------------------------- */
$stmt = $conn->prepare('
    SELECT r.*, p.nom AS prestation, p.duree, p.prix
    FROM reservations r
    JOIN prestations p ON p.id = r.prestation_id
    WHERE r.id = :id
');
$stmt->execute([':id' => $id]);
$resa = $stmt->fetch();

if (!$resa) {
    header('Location: reservations.php?err=' . urlencode('Réservation introuvable.'));
    exit;
}

$prestations = $conn->query('SELECT id, nom, duree, prix FROM prestations ORDER BY nom ASC')->fetchAll();

/* --- Sélection en cours ------------------------------------------------ */
$prestationId = (int) ($_POST['prestation_id'] ?? $resa['prestation_id']);
$date         = $_POST['date']  ?? date('Y-m-d', strtotime($resa['date_debut']));
$heure        = $_POST['heure'] ?? date('H:i', strtotime($resa['date_debut']));
$forcer       = !empty($_POST['forcer']);

$erreur = '';

/* --- Enregistrement ---------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enregistrer') {
    csrf_check();

    $nom      = trim($_POST['client_nom'] ?? '');
    $email    = trim($_POST['client_email'] ?? '');
    $tel      = trim($_POST['client_tel'] ?? '');
    $statut   = ($_POST['statut'] ?? 'confirmee') === 'annulee' ? 'annulee' : 'confirmee';
    $prevenir = !empty($_POST['prevenir']);

    $stmt = $conn->prepare('SELECT id, nom, duree, prix FROM prestations WHERE id = :id');
    $stmt->execute([':id' => $prestationId]);
    $prestation = $stmt->fetch();

    if (!$prestation) {
        $erreur = "Prestation introuvable.";
    } elseif ($nom === '' || $email === '' || $tel === '') {
        $erreur = "Nom, e-mail et téléphone sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Cette adresse e-mail n'est pas valide.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $heure)) {
        $erreur = "Date ou horaire invalide.";
    } else {
        $duree     = (int) $prestation['duree'];
        $dateDebut = $date . ' ' . $heure . ':00';
        $dateFin   = date('Y-m-d H:i:s', strtotime($dateDebut) + $duree * 60);

        // Sauf forçage explicite, le créneau doit faire partie des créneaux
        // ouverts : horaires du jour, pause déjeuner, chevauchements.
        $valide = true;
        if (!$forcer && $statut === 'confirmee') {
            $libres = array_column(array_filter(
                creneaux_du_jour($conn, $date, $duree, $id, true),
                static fn($c) => $c['libre']
            ), 'debut');
            $valide = in_array($heure, $libres, true);
        }

        if (!$valide) {
            $erreur = "Ce créneau n'est pas disponible. Choisissez-en un autre, ou cochez « forcer » si vous savez ce que vous faites.";
        } else {
            // Vérification de chevauchement, même en mode forcé : on avertit
            // plutôt que d'écraser silencieusement un autre rendez-vous.
            $conflit = $conn->prepare("
                SELECT COUNT(*) FROM reservations
                WHERE statut = 'confirmee' AND id <> :moi
                  AND date_debut < :fin AND date_fin > :debut
            ");
            $conflit->execute([':moi' => $id, ':debut' => $dateDebut, ':fin' => $dateFin]);

            if ($statut === 'confirmee' && $conflit->fetchColumn() > 0 && !$forcer) {
                $erreur = "Ce créneau chevauche un autre rendez-vous.";
            } else {
                $avant = [
                    'prestation'  => $resa['prestation'],
                    'date_longue' => fmt_date_longue(date('Y-m-d', strtotime($resa['date_debut']))),
                    'heure_debut' => date('H\hi', strtotime($resa['date_debut'])),
                    'heure_fin'   => date('H\hi', strtotime($resa['date_fin'])),
                ];

                $conn->prepare('
                    UPDATE reservations
                    SET prestation_id = :p, client_nom = :n, client_email = :e, client_tel = :t,
                        date_debut = :debut, date_fin = :fin, statut = :s
                    WHERE id = :id
                ')->execute([
                    ':p'     => $prestationId,
                    ':n'     => $nom,
                    ':e'     => $email,
                    ':t'     => $tel,
                    ':debut' => $dateDebut,
                    ':fin'   => $dateFin,
                    ':s'     => $statut,
                    ':id'    => $id,
                ]);

                $message = 'Réservation mise à jour.';

                if ($prevenir && $statut === 'confirmee') {
                    $envoye = notifier_modification([
                        'id'           => $id,
                        'prestation'   => $prestation['nom'],
                        'duree'        => $duree,
                        'prix'         => $prestation['prix'],
                        'client_nom'   => $nom,
                        'client_email' => $email,
                        'client_tel'   => $tel,
                        'date_debut'   => $dateDebut,
                        'date_fin'     => $dateFin,
                        'date_longue'  => fmt_date_longue($date),
                        'heure_debut'  => date('H\hi', strtotime($dateDebut)),
                        'heure_fin'    => date('H\hi', strtotime($dateFin)),
                    ], $avant);

                    $message .= $envoye
                        ? ' La cliente a été prévenue par e-mail.'
                        : " L'e-mail n'a pas pu être envoyé — prévenez la cliente autrement.";
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
    if ((int) $p['id'] === $prestationId) {
        $prestationChoisie = $p;
    }
}
$dureeAffichee = $prestationChoisie ? (int) $prestationChoisie['duree'] : (int) $resa['duree'];

$creneaux = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    ? creneaux_du_jour($conn, $date, $dureeAffichee, $id, true)
    : [];

admin_header('Modifier la réservation', 'reservations');
?>

<p style="margin:-6px 0 20px;">
    <a class="link" href="reservations.php">← Retour aux réservations</a>
</p>

<?php if ($erreur !== '') : ?>
    <div class="flash flash-err"><?= e($erreur) ?></div>
<?php endif; ?>

<form method="post" id="formModif">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <section class="card">
        <div class="card-head">
            <h2>Prestation et créneau</h2>
            <span class="dim" style="display:inline;">
                Actuellement : <?= e($resa['prestation']) ?>,
                <?= e(fmt_date_longue(date('Y-m-d', strtotime($resa['date_debut'])))) ?>
                à <?= e(date('H\hi', strtotime($resa['date_debut']))) ?>
            </span>
        </div>

        <div class="row-form" style="margin-bottom:20px;">
            <div class="fld grow">
                <label for="prestation_id">Prestation</label>
                <select id="prestation_id" name="prestation_id" onchange="document.getElementById('formModif').submit();">
                    <?php foreach ($prestations as $p) : ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === $prestationId ? 'selected' : '' ?>>
                            <?= e($p['nom']) ?> — <?= (int) $p['duree'] ?> min<?= $p['prix'] !== null ? ' — ' . e($p['prix']) . ' €' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fld">
                <label for="date">Date</label>
                <input id="date" name="date" type="date" value="<?= e($date) ?>"
                       onchange="document.getElementById('formModif').submit();">
            </div>
            <div class="fld">
                <label for="heure">Horaire retenu</label>
                <input id="heure" name="heure" type="time" step="300" value="<?= e($heure) ?>">
            </div>
        </div>

        <?php if (!$creneaux) : ?>
            <p class="empty">Le salon est fermé ce jour-là. Vous pouvez tout de même saisir un horaire à la main en cochant « forcer » ci-dessous.</p>
        <?php else : ?>
            <p class="dim" style="margin-bottom:10px;">
                Cliquez un créneau pour le retenir. Les créneaux barrés sont déjà pris par
                une autre cliente ; le rendez-vous en cours de modification n'est pas compté.
            </p>
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
                <label for="client_nom">Nom</label>
                <input id="client_nom" name="client_nom" type="text" value="<?= e($_POST['client_nom'] ?? $resa['client_nom']) ?>">
            </div>
            <div class="fld">
                <label for="client_tel">Téléphone</label>
                <input id="client_tel" name="client_tel" type="text" value="<?= e($_POST['client_tel'] ?? $resa['client_tel']) ?>">
            </div>
            <div class="fld full">
                <label for="client_email">E-mail</label>
                <input id="client_email" name="client_email" type="email" value="<?= e($_POST['client_email'] ?? $resa['client_email']) ?>">
            </div>
            <div class="fld">
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="confirmee" <?= ($_POST['statut'] ?? $resa['statut']) === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                    <option value="annulee"   <?= ($_POST['statut'] ?? $resa['statut']) === 'annulee'   ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
        </div>

        <label class="check-line" style="margin-top:18px;">
            <input type="checkbox" name="prevenir" value="1" checked>
            Prévenir la cliente par e-mail, avec le fichier agenda mis à jour
        </label>

        <div class="cta-row" style="margin-top:22px;">
            <a class="btn" href="reservations.php">Annuler</a>
            <button class="btn btn-primary" name="action" value="enregistrer">Enregistrer les modifications</button>
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
