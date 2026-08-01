<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

/* --- Indicateurs ------------------------------------------------------ */
$rdvAujourdhui = $conn->query("
    SELECT COUNT(*) FROM Reservations
    WHERE DATE(StartDate) = CURDATE() AND Status = 'confirmed'
")->fetchColumn();

$rdvSemaine = $conn->query("
    SELECT COUNT(*) FROM Reservations
    WHERE YEARWEEK(StartDate, 1) = YEARWEEK(CURDATE(), 1) AND Status = 'confirmed'
")->fetchColumn();

$caMois = $conn->query("
    SELECT COALESCE(SUM(p.Prices), 0)
    FROM Reservations r
    JOIN Services p ON p.Id = r.ServiceId
    WHERE YEAR(r.StartDate) = YEAR(CURDATE())
      AND MONTH(r.StartDate) = MONTH(CURDATE())
      AND r.Status = 'confirmed'
")->fetchColumn();

$topPresta = $conn->query("
    SELECT p.Service, COUNT(*) AS n
    FROM Reservations r
    JOIN Services p ON p.Id = r.ServiceId
    WHERE r.Status = 'confirmed'
      AND r.StartDate >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY p.Id
    ORDER BY n DESC
    LIMIT 1
")->fetch();

/* --- Agenda du jour --------------------------------------------------- */
$today = $conn->query("
    SELECT r.*, p.Service AS prestation, p.Delay, p.Prices
    FROM Reservations r
    JOIN Services p ON p.Id = r.ServiceId
    WHERE DATE(r.StartDate) = CURDATE()
    ORDER BY r.StartDate ASC
")->fetchAll();

/* --- Prochains rendez-vous ------------------------------------------- */
$next = $conn->query("
    SELECT r.*, p.Service AS prestation, p.Delay
    FROM Reservations r
    JOIN Services p ON p.Id = r.ServiceId
    WHERE r.StartDate > NOW() AND r.Status = 'confirmed'
    ORDER BY r.StartDate ASC
    LIMIT 6
")->fetchAll();

/* --- Répartition sur 7 jours (mini-graphe) ---------------------------- */
$serie = $conn->query("
    SELECT DATE(StartDate) AS j, COUNT(*) AS n
    FROM Reservations
    WHERE Status = 'confirmed'
      AND StartDate >= CURDATE()
      AND StartDate < DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(StartDate)
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
        <span class="kpi-value kpi-text"><?= $topPresta ? e($topPresta['Service']) : '—' ?></span>
    </article>
</section>

<div class="cols">

    <section class="card">
        <div class="card-head">
            <h2>Agenda du jour</h2>
            <span>
                <a class="link" href="nouvelle-reservation.php">+ Nouveau rendez-vous</a>
                <a class="link" href="planning.php?v=jour" style="margin-left:14px;">Voir le planning</a>
            </span>
        </div>

        <?php if (!$today) : ?>
            <p class="empty">Aucun rendez-vous prévu aujourd'hui.</p>
        <?php else : ?>
            <ul class="timeline">
                <?php foreach ($today as $r) : ?>
                    <li class="tl-item<?= $r['Status'] === 'cancelled' ? ' is-cancelled' : '' ?>">
                        <span class="tl-time">
                            <?= e(fmt_heure($r['StartDate'])) ?>
                            <small><?= e(fmt_heure($r['EndDate'])) ?></small>
                        </span>
                        <span class="tl-body">
                            <strong><?= e($r['prestation']) ?></strong>
                            <span class="tl-meta"><?= e($r['ClientName']) ?> · <?= e($r['ClientTel']) ?></span>
                        </span>
                        <span class="badge badge-<?= $r['Status'] === 'cancelled' ? 'off' : 'on' ?>">
                            <?= $r['Status'] === 'cancelled' ? 'Annulée' : 'Confirmée' ?>
                        </span>
                        <a class="btn btn-mini" href="modifier-reservation.php?id=<?= (int) $r['Id'] ?>">Modifier</a>
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
                            <span class="avatar"><?= e(initiales($r['ClientName'])) ?></span>
                            <span class="mini-body">
                                <strong><?= e($r['ClientName']) ?></strong>
                                <span><?= e($r['prestation']) ?></span>
                            </span>
                            <span class="mini-when">
                                <?= e(date('d/m', strtotime($r['StartDate']))) ?><br>
                                <small><?= e(fmt_heure($r['StartDate'])) ?></small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

    </div>
</div>

<?php admin_footer(); ?>
