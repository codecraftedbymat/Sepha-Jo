<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

/* --- Actions ---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'annuler' && $id) {
        $conn->prepare("UPDATE reservations SET statut = 'annulee' WHERE id = :id")->execute([':id' => $id]);
        header('Location: reservations.php?ok=' . urlencode('Réservation annulée.'));
        exit;
    }
    if ($action === 'retablir' && $id) {
        $conn->prepare("UPDATE reservations SET statut = 'confirmee' WHERE id = :id")->execute([':id' => $id]);
        header('Location: reservations.php?ok=' . urlencode('Réservation rétablie.'));
        exit;
    }
    if ($action === 'supprimer' && $id) {
        $conn->prepare('DELETE FROM reservations WHERE id = :id')->execute([':id' => $id]);
        header('Location: reservations.php?ok=' . urlencode('Réservation supprimée.'));
        exit;
    }
}

/* --- Filtres ---------------------------------------------------------- */
$filtre    = $_GET['f'] ?? 'avenir';
$recherche = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

switch ($filtre) {
    case 'avenir':
        $where[] = "r.date_debut >= CURDATE() AND r.statut = 'confirmee'";
        break;
    case 'passees':
        $where[] = "r.date_debut < CURDATE() AND r.statut = 'confirmee'";
        break;
    case 'annulees':
        $where[] = "r.statut = 'annulee'";
        break;
    // 'toutes' : aucun filtre
}

if ($recherche !== '') {
    $where[] = '(r.client_nom LIKE :q OR r.client_email LIKE :q OR r.client_tel LIKE :q)';
    $params[':q'] = '%' . $recherche . '%';
}

$sql = "SELECT r.*, p.nom AS prestation, p.duree, p.prix
        FROM reservations r
        JOIN prestations p ON p.id = r.prestation_id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= $filtre === 'passees' ? ' ORDER BY r.date_debut DESC' : ' ORDER BY r.date_debut ASC';

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$onglets = [
    'avenir'   => 'À venir',
    'passees'  => 'Passées',
    'annulees' => 'Annulées',
    'toutes'   => 'Toutes',
];

admin_header('Réservations', 'reservations');
flash();
?>

<div class="toolbar">
    <nav class="tabs">
        <?php foreach ($onglets as $key => $label) : ?>
            <a href="?f=<?= e($key) ?><?= $recherche !== '' ? '&q=' . urlencode($recherche) : '' ?>"
               class="tab<?= $filtre === $key ? ' is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="toolbar-right">
        <form class="search" method="get">
            <input type="hidden" name="f" value="<?= e($filtre) ?>">
            <input type="search" name="q" placeholder="Nom, e-mail ou téléphone…" value="<?= e($recherche) ?>">
            <button type="submit" class="btn btn-ghost">Rechercher</button>
        </form>
        <a class="btn btn-primary" href="nouvelle-reservation.php">+ Nouveau rendez-vous</a>
    </div>
</div>

<section class="card card-flush">
    <?php if (!$reservations) : ?>
        <p class="empty">Aucune réservation ne correspond à ce filtre.</p>
    <?php else : ?>
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Prestation</th>
                        <th>Date</th>
                        <th>Horaire</th>
                        <th>Contact</th>
                        <th>Statut</th>
                        <th class="ta-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reservations as $r) : ?>
                    <tr<?= $r['statut'] === 'annulee' ? ' class="row-off"' : '' ?>>
                        <td>
                            <span class="cell-user">
                                <span class="avatar"><?= e(initiales($r['client_nom'])) ?></span>
                                <?= e($r['client_nom']) ?>
                            </span>
                        </td>
                        <td>
                            <?= e($r['prestation']) ?>
                            <span class="dim"><?= (int) $r['duree'] ?> min</span>
                        </td>
                        <td><?= e(date('d/m/Y', strtotime($r['date_debut']))) ?></td>
                        <td class="mono"><?= e(fmt_heure($r['date_debut'])) ?> – <?= e(fmt_heure($r['date_fin'])) ?></td>
                        <td>
                            <a class="link" href="mailto:<?= e($r['client_email']) ?>"><?= e($r['client_email']) ?></a>
                            <span class="dim"><?= e($r['client_tel']) ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $r['statut'] === 'annulee' ? 'off' : 'on' ?>">
                                <?= $r['statut'] === 'annulee' ? 'Annulée' : 'Confirmée' ?>
                            </span>
                        </td>
                        <td class="ta-right">
                            <div class="inline-form">
                                <a class="btn btn-mini btn-primary" href="modifier-reservation.php?id=<?= (int) $r['id'] ?>">Modifier</a>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <?php if ($r['statut'] === 'confirmee') : ?>
                                        <button class="btn btn-mini" name="action" value="annuler">Annuler</button>
                                    <?php else : ?>
                                        <button class="btn btn-mini" name="action" value="retablir">Rétablir</button>
                                    <?php endif; ?>
                                    <button class="btn btn-mini btn-danger" name="action" value="supprimer"
                                            onclick="return confirm('Supprimer définitivement cette réservation ?');">Suppr.</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="table-foot"><?= count($reservations) ?> réservation<?= count($reservations) > 1 ? 's' : '' ?></p>
    <?php endif; ?>
</section>

<?php admin_footer(); ?>
