<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once dirname(__DIR__, 3) . '/includes/creneaux.php';

/* =======================================================================
   Vue et période affichées
   ======================================================================= */
$vue = $_GET['v'] ?? 'mois';
if (!in_array($vue, ['mois', 'semaine', 'jour'], true)) {
    $vue = 'mois';
}

// Date de référence : le jour sur lequel la vue est centrée.
$ref = $_GET['d'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref)) {
    $ref = date('Y-m-d');
}
$refTs = strtotime($ref);

$MOIS  = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$JOURS = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
$ABBR  = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

/* --- Bornes de la période, et libellés de navigation ------------------- */
switch ($vue) {
    case 'jour':
        $debut = $fin = $ref;
        $titre = $JOURS[(int) date('N', $refTs) - 1] . ' ' . date('j', $refTs)
               . ' ' . $MOIS[(int) date('n', $refTs) - 1] . ' ' . date('Y', $refTs);
        $prec  = date('Y-m-d', strtotime($ref . ' -1 day'));
        $suiv  = date('Y-m-d', strtotime($ref . ' +1 day'));
        break;

    case 'semaine':
        $debut = date('Y-m-d', strtotime('monday this week', $refTs));
        $fin   = date('Y-m-d', strtotime($debut . ' +6 days'));
        $titre = 'Semaine du ' . date('j', strtotime($debut))
               . ' ' . $MOIS[(int) date('n', strtotime($debut)) - 1]
               . ' ' . date('Y', strtotime($debut));
        $prec  = date('Y-m-d', strtotime($debut . ' -7 days'));
        $suiv  = date('Y-m-d', strtotime($debut . ' +7 days'));
        break;

    default: // mois
        $debut = date('Y-m-01', $refTs);
        $fin   = date('Y-m-t',  $refTs);
        $titre = ucfirst($MOIS[(int) date('n', $refTs) - 1]) . ' ' . date('Y', $refTs);
        $prec  = date('Y-m-d', strtotime($debut . ' -1 month'));
        $suiv  = date('Y-m-d', strtotime($debut . ' +1 month'));
        break;
}

/* =======================================================================
   Rendez-vous de la période
   ======================================================================= */
$stmt = $conn->prepare("
    SELECT r.*, p.Service AS prestation, p.Delay, p.Prices
    FROM Reservations r
    JOIN Services p ON p.Id = r.ServiceId
    WHERE DATE(r.StartDate) BETWEEN :a AND :b
    ORDER BY r.StartDate ASC
");
$stmt->execute([':a' => $debut, ':b' => $fin]);

$parJour = [];
$totalConfirmes = 0;
$recette = 0.0;

foreach ($stmt->fetchAll() as $r) {
    $parJour[date('Y-m-d', strtotime($r['StartDate']))][] = $r;
    if ($r['Status'] === 'confirmed') {
        $totalConfirmes++;
        $recette += (float) ($r['Prices'] ?? 0);
    }
}

/* --- Utilitaires d'affichage ------------------------------------------ */
function lien(string $vue, string $date): string
{
    return 'planning.php?v=' . urlencode($vue) . '&d=' . urlencode($date);
}

function heure_courte(string $sql): string
{
    return date('H\hi', strtotime($sql));
}

admin_header('Planning', 'planning');
flash();
?>

<div class="planning-bar">
    <nav class="tabs">
        <a href="<?= e(lien('mois', $ref)) ?>"    class="tab<?= $vue === 'mois'    ? ' is-active' : '' ?>">Mois</a>
        <a href="<?= e(lien('semaine', $ref)) ?>" class="tab<?= $vue === 'semaine' ? ' is-active' : '' ?>">Semaine</a>
        <a href="<?= e(lien('jour', $ref)) ?>"    class="tab<?= $vue === 'jour'    ? ' is-active' : '' ?>">Jour</a>
    </nav>

    <div class="period-nav">
        <a class="btn btn-ghost btn-nav" href="<?= e(lien($vue, $prec)) ?>" aria-label="Période précédente">‹</a>
        <span class="period-label"><?= e($titre) ?></span>
        <a class="btn btn-ghost btn-nav" href="<?= e(lien($vue, $suiv)) ?>" aria-label="Période suivante">›</a>
    </div>

    <div class="planning-actions">
        <?php if ($ref !== date('Y-m-d')) : ?>
            <a class="btn btn-ghost" href="<?= e(lien($vue, date('Y-m-d'))) ?>">Aujourd'hui</a>
        <?php endif; ?>
        <a class="btn btn-primary" href="nouvelle-reservation.php">+ Nouveau rendez-vous</a>
    </div>
</div>

<p class="planning-summary">
    <?= (int) $totalConfirmes ?> rendez-vous confirmé<?= $totalConfirmes > 1 ? 's' : '' ?>
    <?php if ($recette > 0) : ?>
        · <?= number_format($recette, 0, ',', ' ') ?> € attendus
    <?php endif; ?>
</p>


<?php /* ==================================================================
        VUE MOIS
        ================================================================== */ ?>
<?php if ($vue === 'mois') :

    // La grille commence au lundi précédant le 1er du mois.
    $premier    = strtotime($debut);
    $decalage   = (int) date('N', $premier) - 1;
    $caseDebut  = strtotime("-$decalage days", $premier);
    $nbSemaines = (int) ceil(($decalage + (int) date('t', $refTs)) / 7);
    $moisCourant = date('n', $refTs);
?>

    <section class="card card-flush">
        <div class="month">
            <?php foreach ($ABBR as $a) : ?>
                <div class="month-dow"><?= e($a) ?></div>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $nbSemaines * 7; $i++) :
                $ts    = strtotime("+$i days", $caseDebut);
                $jour  = date('Y-m-d', $ts);
                $rdvs  = $parJour[$jour] ?? [];
                $hors  = date('n', $ts) !== $moisCourant;
                $today = $jour === date('Y-m-d');
                $ferme = jour_ferme($conn, $jour);
            ?>
                <div class="month-cell<?= $hors ? ' is-out' : '' ?><?= $today ? ' is-today' : '' ?><?= $ferme ? ' is-closed' : '' ?>">
                    <a class="month-num" href="<?= e(lien('jour', $jour)) ?>"><?= date('j', $ts) ?></a>

                    <?php if ($rdvs) : ?>
                        <div class="month-events">
                            <?php foreach (array_slice($rdvs, 0, 3) as $r) : ?>
                                <a class="mini-event<?= $r['Status'] === 'cancelled' ? ' is-off' : '' ?>"
                                   href="modifier-reservation.php?id=<?= (int) $r['Id'] ?>"
                                   title="<?= e($r['prestation'] . ' — ' . $r['ClientName']) ?>">
                                    <span class="me-time"><?= e(heure_courte($r['StartDate'])) ?></span>
                                    <span class="me-name"><?= e($r['ClientName']) ?></span>
                                </a>
                            <?php endforeach; ?>

                            <?php if (count($rdvs) > 3) : ?>
                                <a class="mini-more" href="<?= e(lien('jour', $jour)) ?>">
                                    +<?= count($rdvs) - 3 ?> autre<?= count($rdvs) - 3 > 1 ? 's' : '' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>


<?php /* ==================================================================
        VUE SEMAINE
        ================================================================== */ ?>
<?php elseif ($vue === 'semaine') : ?>

    <div class="week">
        <?php for ($i = 0; $i < 7; $i++) :
            $jour  = date('Y-m-d', strtotime($debut . " +$i days"));
            $rdvs  = $parJour[$jour] ?? [];
            $today = $jour === date('Y-m-d');
            $ferme = jour_ferme($conn, $jour);
        ?>
            <section class="day<?= $today ? ' is-today' : '' ?><?= $ferme ? ' is-closed' : '' ?>">
                <header class="day-head">
                    <span class="day-name"><?= e($JOURS[$i]) ?></span>
                    <a class="day-num" href="<?= e(lien('jour', $jour)) ?>"><?= date('j', strtotime($jour)) ?></a>
                </header>
                <div class="day-body">
                    <?php if (!$rdvs) : ?>
                        <p class="day-empty"><?= $ferme ? 'Fermé' : '—' ?></p>
                    <?php else : foreach ($rdvs as $r) : ?>
                        <a class="chip<?= $r['Status'] === 'cancelled' ? ' is-off' : '' ?>"
                           href="modifier-reservation.php?id=<?= (int) $r['Id'] ?>">
                            <span class="chip-time"><?= e(heure_courte($r['StartDate'])) ?> – <?= e(heure_courte($r['EndDate'])) ?></span>
                            <span class="chip-name"><?= e($r['ClientName']) ?></span>
                            <span class="chip-presta"><?= e($r['prestation']) ?></span>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </section>
        <?php endfor; ?>
    </div>


<?php /* ==================================================================
        VUE JOUR — grille horaire proportionnelle
        ================================================================== */ ?>
<?php else :

    $rdvs = $parJour[$ref] ?? [];
    $dow  = (int) date('w', $refTs);
    $h    = horaires_semaine($conn)[$dow] ?? null;   // en minutes, ou null
    $motif = motif_fermeture($conn, $ref);

    // Amplitude affichée : horaires du jour, élargis si un rendez-vous
    // déborde (cas d'un créneau forcé hors ouverture).
    $ouv = $h ? intdiv($h[0], 60) : 9;
    $fer = $h ? (int) ceil($h[1] / 60) : 19;
    foreach ($rdvs as $r) {
        $ouv = min($ouv, (int) date('G', strtotime($r['StartDate'])));
        $fer = max($fer, (int) ceil((strtotime($r['EndDate']) - strtotime(date('Y-m-d', strtotime($r['EndDate'])))) / 3600));
    }
    $amplitude = max(1, $fer - $ouv);
    $hauteur   = 62; // pixels par heure
?>

    <section class="card">
        <?php if ($motif !== null) : ?>
            <p class="flash flash-err" style="margin-bottom:18px;">
                Fermeture exceptionnelle<?= $motif !== '' ? ' — ' . e($motif) : '' ?>.
            </p>
        <?php elseif (!$h) : ?>
            <p class="flash flash-err" style="margin-bottom:18px;">
                Le salon est fermé ce jour de la semaine.
                <a class="link" href="disponibilites.php">Modifier les horaires</a>
            </p>
        <?php endif; ?>

        <?php if (!$rdvs) : ?>
            <p class="empty">Aucun rendez-vous ce jour-là.</p>
        <?php endif; ?>

        <div class="daygrid" style="height: <?= $amplitude * $hauteur ?>px;">
            <div class="daygrid-hours">
                <?php for ($hh = $ouv; $hh <= $fer; $hh++) : ?>
                    <div class="dg-hour" style="top: <?= ($hh - $ouv) * $hauteur ?>px;">
                        <span><?= sprintf('%02dh', $hh) ?></span>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="daygrid-events">
                <?php
                // Bande de pause déjeuner, si configurée
                $pause = pause_dejeuner($conn);
                if ($pause !== null && $h) :
                    $pd = $pause[0] / 60; $pf = $pause[1] / 60;
                    if ($pf > $ouv && $pd < $fer) :
                ?>
                    <div class="dg-pause"
                         style="top: <?= (max($pd, $ouv) - $ouv) * $hauteur ?>px;
                                height: <?= (min($pf, $fer) - max($pd, $ouv)) * $hauteur ?>px;">
                        <span>Pause</span>
                    </div>
                <?php endif; endif; ?>

                <?php foreach ($rdvs as $r) :
                    $sMin = (int) date('G', strtotime($r['StartDate'])) * 60 + (int) date('i', strtotime($r['StartDate']));
                    $eMin = (int) date('G', strtotime($r['EndDate']))   * 60 + (int) date('i', strtotime($r['EndDate']));
                    $top  = (($sMin - $ouv * 60) / 60) * $hauteur;
                    $haut = max(24, (($eMin - $sMin) / 60) * $hauteur);
                ?>
                    <a class="dg-event<?= $r['Status'] === 'cancelled' ? ' is-off' : '' ?>"
                       href="modifier-reservation.php?id=<?= (int) $r['Id'] ?>"
                       style="top: <?= $top ?>px; height: <?= $haut ?>px;">
                        <span class="dge-time"><?= e(heure_courte($r['StartDate'])) ?> – <?= e(heure_courte($r['EndDate'])) ?></span>
                        <span class="dge-name"><?= e($r['ClientName']) ?></span>
                        <span class="dge-presta"><?= e($r['prestation']) ?></span>
                        <span class="dge-tel"><?= e($r['ClientTel']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

<?php endif; ?>

<?php admin_footer(); ?>
