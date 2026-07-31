<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

/* Semaine affichée : décalage en semaines par rapport à la semaine courante */
$offset = (int) ($_GET['w'] ?? 0);
$lundi  = date('Y-m-d', strtotime('monday this week ' . ($offset >= 0 ? "+$offset" : $offset) . ' week'));
$dimanche = date('Y-m-d', strtotime($lundi . ' +6 days'));

$stmt = $conn->prepare("
    SELECT r.*, p.nom AS prestation
    FROM reservations r
    JOIN prestations p ON p.id = r.prestation_id
    WHERE r.statut = 'confirmee'
      AND DATE(r.date_debut) BETWEEN :a AND :b
    ORDER BY r.date_debut ASC
");
$stmt->execute([':a' => $lundi, ':b' => $dimanche]);

$parJour = [];
foreach ($stmt->fetchAll() as $r) {
    $parJour[date('Y-m-d', strtotime($r['date_debut']))][] = $r;
}

$noms = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];

admin_header('Planning', 'planning');
flash();
?>

<div class="toolbar">
    <div class="week-nav">
        <a class="btn btn-ghost" href="?w=<?= $offset - 1 ?>">←</a>
        <span class="week-label">
            Semaine du <?= date('j', strtotime($lundi)) ?>
            <?= e($mois[(int) date('n', strtotime($lundi)) - 1]) ?>
            <?= date('Y', strtotime($lundi)) ?>
        </span>
        <a class="btn btn-ghost" href="?w=<?= $offset + 1 ?>">→</a>
    </div>
    <?php if ($offset !== 0) : ?>
        <a class="btn btn-ghost" href="?w=0">Revenir à cette semaine</a>
    <?php endif; ?>
</div>

<div class="week">
    <?php for ($i = 0; $i < 7; $i++) :
        $jour = date('Y-m-d', strtotime($lundi . " +$i days"));
        $rdvs = $parJour[$jour] ?? [];
        $estAujourdhui = $jour === date('Y-m-d');
    ?>
        <section class="day<?= $estAujourdhui ? ' is-today' : '' ?>">
            <header class="day-head">
                <span class="day-name"><?= e($noms[$i]) ?></span>
                <span class="day-num"><?= date('j', strtotime($jour)) ?></span>
            </header>
            <div class="day-body">
                <?php if (!$rdvs) : ?>
                    <p class="day-empty">—</p>
                <?php else : foreach ($rdvs as $r) : ?>
                    <article class="chip">
                        <span class="chip-time"><?= e(fmt_heure($r['date_debut'])) ?></span>
                        <span class="chip-name"><?= e($r['client_nom']) ?></span>
                        <span class="chip-presta"><?= e($r['prestation']) ?></span>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </section>
    <?php endfor; ?>
</div>

<?php admin_footer(); ?>
