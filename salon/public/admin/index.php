<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

/* --- Indicateurs ------------------------------------------------------ */
$rdvAujourdhui = $conn->query("
    SELECT COUNT(*) FROM reservations
    WHERE DATE(date_debut) = CURDATE() AND statut = 'confirmee'
")->fetchColumn();

$rdvSemaine = $conn->query("
    SELECT COUNT(*) FROM reservations
    WHERE YEARWEEK(date_debut, 1) = YEARWEEK(CURDATE(), 1) AND statut = 'confirmee'
")->fetchColumn();

$caMois = $conn->query("
    SELECT COALESCE(SUM(p.prix), 0)
    FROM reservations r
    JOIN prestations p ON p.id = r.prestation_id
    WHERE YEAR(r.date_debut) = YEAR(CURDATE())
      AND MONTH(r.date_debut) = MONTH(CURDATE())
      AND r.statut = 'confirmee'
")->fetchColumn();

$topPresta = $conn->query("
    SELECT p.nom, COUNT(*) AS n
    FROM reservations r
    JOIN prestations p ON p.id = r.prestation_id
    WHERE r.statut = 'confirmee'
      AND r.date_debut >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY p.id
    ORDER BY n DESC
    LIMIT 1
")->fetch();

/* --- Agenda du jour --------------------------------------------------- */
$today = $conn->query("
    SELECT r.*, p.nom AS prestation, p.duree, p.prix
    FROM reservations r
    JOIN prestations p ON p.id = r.prestation_id
    WHERE DATE(r.date_debut) = CURDATE()
    ORDER BY r.date_debut ASC
")->fetchAll();

/* --- Prochains rendez-vous ------------------------------------------- */
$next = $conn->query("
    SELECT r.*, p.nom AS prestation, p.duree
    FROM reservations r
    JOIN prestations p ON p.id = r.prestation_id
    WHERE r.date_debut > NOW() AND r.statut = 'confirmee'
    ORDER BY r.date_debut ASC
    LIMIT 6
")->fetchAll();

/* --- Répartition sur 7 jours (mini-graphe) ---------------------------- */
$serie = $conn->query("
    SELECT DATE(date_debut) AS j, COUNT(*) AS n
    FROM reservations
    WHERE statut = 'confirmee'
      AND date_debut >= CURDATE()
      AND date_debut < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(date_debut)
")->fetchAll();

$parJour = [];
foreach ($serie as $row) {
    $parJour[$row['j']] = (int) $row['n'];
}
$maxJour = $parJour ? max($parJour) : 0;

admin_header('Tableau de bord', 'dashboard');
flash();
?>

<section class="kpis">
    <article class="kpi">
        <span class="kpi-label">Rendez-vous aujourd'hui</span>
        <span class="kpi-value"><?= (int) $rdvAujourdhui ?></span>
    </article>
    <article class="kpi">
        <span class="kpi-label">Cette semaine</span>
        <span class="kpi-value"><?= (int) $rdvSemaine ?></span>
    </article>
    <article class="kpi">
        <span class="kpi-label">Chiffre d'affaires du mois</span>
        <span class="kpi-value"><?= number_format((float) $caMois, 0, ',', ' ') ?> <small>€</small></span>
    </article>
    <article class="kpi">
        <span class="kpi-label">Prestation phare (30 j)</span>
        <span class="kpi-value kpi-text"><?= $topPresta ? e($topPresta['nom']) : '—' ?></span>
    </article>
</section>

<div class="cols">

    <section class="card">
        <div class="card-head">
            <h2>Agenda du jour</h2>
            <span>
                <a class="link" href="nouvelle-reservation.php">+ Nouveau rendez-vous</a>
                <a class="link" href="reservations.php" style="margin-left:14px;">Tout voir</a>
            </span>
        </div>

        <?php if (!$today) : ?>
            <p class="empty">Aucun rendez-vous prévu aujourd'hui.</p>
        <?php else : ?>
            <ul class="timeline">
                <?php foreach ($today as $r) : ?>
                    <li class="tl-item<?= $r['statut'] === 'annulee' ? ' is-cancelled' : '' ?>">
                        <span class="tl-time">
                            <?= e(fmt_heure($r['date_debut'])) ?>
                            <small><?= e(fmt_heure($r['date_fin'])) ?></small>
                        </span>
                        <span class="tl-body">
                            <strong><?= e($r['prestation']) ?></strong>
                            <span class="tl-meta"><?= e($r['client_nom']) ?> · <?= e($r['client_tel']) ?></span>
                        </span>
                        <span class="badge badge-<?= $r['statut'] === 'annulee' ? 'off' : 'on' ?>">
                            <?= $r['statut'] === 'annulee' ? 'Annulée' : 'Confirmée' ?>
                        </span>
                        <a class="btn btn-mini" href="modifier-reservation.php?id=<?= (int) $r['id'] ?>">Modifier</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div class="col-side">

        <section class="card">
            <div class="card-head"><h2>Charge des 7 prochains jours</h2></div>
            <div class="spark">
                <?php for ($i = 0; $i < 7; $i++) :
                    $d = date('Y-m-d', strtotime("+$i day"));
                    $n = $parJour[$d] ?? 0;
                    $h = $maxJour > 0 ? max(6, round(($n / $maxJour) * 100)) : 6;
                    $jours = ['D','L','M','M','J','V','S'];
                ?>
                    <div class="spark-col" title="<?= $n ?> rendez-vous">
                        <div class="spark-bar" style="height: <?= $h ?>%"></div>
                        <span class="spark-n"><?= $n ?></span>
                        <span class="spark-d"><?= $jours[(int) date('w', strtotime($d))] ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><h2>Prochains rendez-vous</h2></div>
            <?php if (!$next) : ?>
                <p class="empty">Rien à venir pour l'instant.</p>
            <?php else : ?>
                <ul class="mini-list">
                    <?php foreach ($next as $r) : ?>
                        <li>
                            <span class="avatar"><?= e(initiales($r['client_nom'])) ?></span>
                            <span class="mini-body">
                                <strong><?= e($r['client_nom']) ?></strong>
                                <span><?= e($r['prestation']) ?></span>
                            </span>
                            <span class="mini-when">
                                <?= e(date('d/m', strtotime($r['date_debut']))) ?><br>
                                <small><?= e(fmt_heure($r['date_debut'])) ?></small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>
</div>

<?php admin_footer(); ?>
