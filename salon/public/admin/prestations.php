<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    if ($action === 'ajouter') {
        $nom   = trim($_POST['nom'] ?? '');
        $duree = (int) ($_POST['duree'] ?? 0);
        $prix  = $_POST['prix'] === '' ? null : (float) $_POST['prix'];

        if ($nom === '' || $duree <= 0) {
            header('Location: prestations.php?err=' . urlencode('Nom et durée sont obligatoires.'));
            exit;
        }
        $conn->prepare('INSERT INTO Services (Service, Delay, Prices, Active) VALUES (:n, :d, :p, 1)')
             ->execute([':n' => $nom, ':d' => $duree, ':p' => $prix]);
        header('Location: prestations.php?ok=' . urlencode('Prestation ajoutée.'));
        exit;
    }

    if ($action === 'modifier' && $id) {
        $nom   = trim($_POST['nom'] ?? '');
        $duree = (int) ($_POST['duree'] ?? 0);
        $prix  = $_POST['prix'] === '' ? null : (float) $_POST['prix'];

        $conn->prepare('UPDATE Services SET Service = :n, Delay = :d, Prices = :p WHERE Id = :id')
             ->execute([':n' => $nom, ':d' => $duree, ':p' => $prix, ':id' => $id]);
        header('Location: prestations.php?ok=' . urlencode('Prestation mise à jour.'));
        exit;
    }

    if ($action === 'basculer' && $id) {
        $conn->prepare('UPDATE Services SET Active = 1 - Active WHERE Id = :id')->execute([':id' => $id]);
        header('Location: prestations.php');
        exit;
    }

    if ($action === 'supprimer' && $id) {
        // Une prestation déjà réservée ne peut pas être supprimée : on la désactive.
        $used = $conn->prepare('SELECT COUNT(*) FROM Reservations WHERE ServiceId = :id');
        $used->execute([':id' => $id]);

        if ($used->fetchColumn() > 0) {
            $conn->prepare('UPDATE Services SET Active = 0 WHERE Id = :id')->execute([':id' => $id]);
            header('Location: prestations.php?err=' . urlencode('Prestation déjà réservée : elle a été désactivée plutôt que supprimée.'));
        } else {
            $conn->prepare('DELETE FROM Services WHERE Id = :id')->execute([':id' => $id]);
            header('Location: prestations.php?ok=' . urlencode('Prestation supprimée.'));
        }
        exit;
    }
}

$prestations = $conn->query('
    SELECT p.*, (SELECT COUNT(*) FROM Reservations r WHERE r.ServiceId = p.Id) AS nb_rdv
    FROM Services p
    ORDER BY p.Active DESC, p.Service ASC
')->fetchAll();

admin_header('Prestations', 'prestations');
flash();
?>

<section class="card">
    <div class="card-head"><h2>Ajouter une prestation</h2></div>
    <form method="post" class="row-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="ajouter">
        <div class="fld grow">
            <label for="nom">Intitulé</label>
            <input id="nom" name="nom" type="text" placeholder="Ex. Soin du visage signature" required>
        </div>
        <div class="fld">
            <label for="duree">Durée (min)</label>
            <input id="duree" name="duree" type="number" min="5" step="5" value="30" required>
        </div>
        <div class="fld">
            <label for="prix">Tarif (€)</label>
            <input id="prix" name="prix" type="number" min="0" step="0.5" placeholder="—">
        </div>
        <button class="btn btn-primary">Ajouter</button>
    </form>
</section>

<section class="card card-flush">
    <div class="card-head"><h2>Catalogue</h2></div>

    <?php if (!$prestations) : ?>
        <p class="empty">Aucune prestation enregistrée.</p>
    <?php else : ?>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Intitulé</th>
                        <th>Durée</th>
                        <th>Tarif</th>
                        <th>Réservations</th>
                        <th>État</th>
                        <th class="ta-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($prestations as $p) : ?>
                    <tr<?= $p['Active'] ? '' : ' class="row-off"' ?>>
                        <form method="post" class="contents">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $p['Id'] ?>">
                        <td><input class="cell-input wide" type="text" name="nom" value="<?= e($p['Service']) ?>"></td>
                        <td><input class="cell-input" type="number" name="duree" min="5" step="5" value="<?= (int) $p['Delay'] ?>"></td>
                        <td><input class="cell-input" type="number" name="prix" min="0" step="0.5" value="<?= $p['Prices'] !== null ? e($p['Prices']) : '' ?>"></td>
                        <td class="mono"><?= (int) $p['nb_rdv'] ?></td>
                        <td>
                            <span class="badge badge-<?= $p['Active'] ? 'on' : 'off' ?>">
                                <?= $p['Active'] ? 'Active' : 'Masquée' ?>
                            </span>
                        </td>
                        <td class="ta-right">
                            <button class="btn btn-mini btn-primary" name="action" value="modifier">Enregistrer</button>
                            <button class="btn btn-mini" name="action" value="basculer"><?= $p['Active'] ? 'Masquer' : 'Activer' ?></button>
                            <button class="btn btn-mini btn-danger" name="action" value="supprimer"
                                    onclick="return confirm('Supprimer cette prestation ?');">Suppr.</button>
                        </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-foot">Les prestations masquées n'apparaissent plus sur le site de réservation.</p>
    <?php endif; ?>
</section>

<?php admin_footer(); ?>
