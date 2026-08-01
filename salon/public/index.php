<?php
require_once __DIR__ . '/../api/database.php';
require_once __DIR__ . '/../includes/creneaux.php';

$conn = (new Database())->connect();

$prestations = $conn->query('
    SELECT Id, Service, Delay, Prices
    FROM Services
    WHERE Active = 1
    ORDER BY Service ASC
')->fetchAll();

$jours = jours_proposes($conn);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Réservation — <?= esc(SALON_NOM) ?></title>
    <link rel="stylesheet" href="assets/booking.css">
</head>
<body>

<div class="wrap">

    <header class="head">
        <p class="eyebrow">Réservation en ligne</p>
        <h1><?= esc(SALON_NOM) ?></h1>
        <p class="tagline">
            Choisissez votre prestation, un créneau disponible, puis laissez-nous vos coordonnées.
            Votre rendez-vous est confirmé immédiatement.
        </p>
    </header>

    <!-- ÉTAPE 1 -->
    <section class="step" id="step1">
        <div class="step-head"><span class="step-num">1</span><h2>La prestation</h2></div>

        <?php if (!$prestations) : ?>
            <p class="empty">Aucune prestation n'est disponible pour le moment.</p>
        <?php else : ?>
            <div class="services">
                <?php foreach ($prestations as $p) : ?>
                    <button type="button" class="svc"
                            data-id="<?= (int) $p['Id'] ?>"
                            data-nom="<?= esc($p['Service']) ?>"
                            data-duree="<?= (int) $p['Delay'] ?>"
                            data-prix="<?= $p['Prices'] !== null ? esc($p['Prices']) : '' ?>">
                        <span class="svc-nom"><?= esc($p['Service']) ?></span>
                        <span class="svc-meta">
                            <span class="pill"><?= (int) $p['Delay'] ?> min</span>
                            <?php if ($p['Prices'] !== null) : ?>
                                <span class="prix"><?= number_format((float) $p['Prices'], 0, ',', ' ') ?> €</span>
                            <?php endif; ?>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ÉTAPE 2 -->
    <section class="step is-locked" id="step2">
        <div class="step-head"><span class="step-num">2</span><h2>Le jour et l'heure</h2></div>
        <p class="step-sub" id="step2sub">Sélectionnez d'abord une prestation.</p>

        <div class="days" id="days">
            <?php foreach ($jours as $j) : ?>
                <button type="button" class="day<?= $j['ferme'] ? ' is-closed' : '' ?>"
                        data-date="<?= esc($j['date']) ?>" <?= $j['ferme'] ? 'disabled' : '' ?>>
                    <span class="d-dow"><?= esc($j['dow']) ?></span>
                    <span class="d-num"><?= esc($j['num']) ?></span>
                    <span class="d-mois"><?= esc($j['mois']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="slots" id="slots"></div>
    </section>

    <!-- ÉTAPE 3 -->
    <section class="step is-locked" id="step3">
        <div class="step-head"><span class="step-num">3</span><h2>Vos coordonnées</h2></div>

        <div class="recap" id="recap"></div>
        <div class="alert" id="alert"></div>

        <form id="form" novalidate>
            <div class="grid">
                <div class="fld">
                    <label for="nom">Nom et prénom</label>
                    <input id="nom" name="nom" type="text" autocomplete="name" placeholder="Claire Dupont">
                </div>
                <div class="fld">
                    <label for="tel">Téléphone</label>
                    <input id="tel" name="tel" type="tel" autocomplete="tel" placeholder="06 12 34 56 78">
                </div>
                <div class="fld full">
                    <label for="email">E-mail</label>
                    <input id="email" name="email" type="email" autocomplete="email" placeholder="vous@exemple.fr">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="submitBtn">Confirmer ma réservation</button>
        </form>
    </section>

    <!-- CONFIRMATION -->
    <section class="step confirm" id="confirm" hidden>
        <div class="stamp">Rendez-vous<br>confirmé</div>
        <h2 class="confirm-title">C'est noté, à très vite</h2>
        <p class="confirm-sub" id="confirmSub"></p>
        <div class="ticket" id="ticket"></div>
        <div class="confirm-actions">
            <a class="btn btn-ghost" id="icsLink" href="#">Ajouter à mon agenda</a>
            <button type="button" class="btn btn-primary" onclick="location.reload()">Nouvelle réservation</button>
        </div>
    </section>

    <footer class="foot">
        <?= esc(SALON_ADRESSE) ?> · <?= esc(SALON_TEL) ?>
    </footer>

</div>

<script src="assets/booking.js"></script>
</body>
</html>
