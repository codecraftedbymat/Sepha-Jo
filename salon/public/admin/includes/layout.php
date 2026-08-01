<?php
/**
 * Gabarit commun à toutes les pages de l'admin.
 *   admin_header('Titre de la page', 'clé-du-menu-actif');
 *   ... contenu ...
 *   admin_footer();
 */

function admin_header(string $title, string $active = ''): void
{
    $nav = [
        'dashboard'    => ['index.php',        'Tableau de bord'],
        'reservations' => ['reservations.php', 'Réservations'],
        'nouvelle'     => ['nouvelle-reservation.php', 'Nouveau rendez-vous'],
        'prestations'  => ['prestations.php',  'Prestations'],
        'planning'     => ['planning.php',     'Planning'],
        'utilisateurs' => ['utilisateurs.php', 'Utilisateurs'],
    ];
    $user = $_SESSION['username'] ?? 'admin';
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> — Admin Salon</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>

<input type="checkbox" id="navToggle" class="nav-toggle-input">

<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark">S</span>
        <div class="brand-text">
            <strong>Salon</strong>
            <span>Administration</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($nav as $key => [$href, $label]) : ?>
            <a href="<?= e($href) ?>" class="nav-item<?= $active === $key ? ' is-active' : '' ?>">
                <span class="nav-bullet"></span><?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <div class="who">
            <span class="who-avatar"><?= e(initiales($user)) ?></span>
            <span class="who-name"><?= e($user) ?></span>
        </div>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>
</aside>

<label for="navToggle" class="nav-scrim"></label>

<main class="content">
    <header class="topbar">
        <label for="navToggle" class="burger" aria-label="Menu">
            <span></span><span></span><span></span>
        </label>
        <h1><?= e($title) ?></h1>
        <span class="topbar-date"><?= e(fmt_date(date('Y-m-d H:i:s'))) ?></span>
    </header>

    <div class="page">
    <?php
}

function admin_footer(): void
{
    ?>
    </div>
</main>

</body>
</html>
    <?php
}

/**
 * Bandeau de notification (succès / erreur), passé via ?ok= ou ?err=
 */
function flash(): void
{
    if (!empty($_GET['ok'])) {
        echo '<div class="flash flash-ok">' . e($_GET['ok']) . '</div>';
    }
    if (!empty($_GET['err'])) {
        echo '<div class="flash flash-err">' . e($_GET['err']) . '</div>';
    }
}
