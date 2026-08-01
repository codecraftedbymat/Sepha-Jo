<?php
session_start();
require_once dirname(__DIR__, 2) . '/api/database.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if (!empty($_GET['expired'])) {
    $error = "Votre session a expiré, merci de vous reconnecter.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = "Merci de renseigner votre identifiant et votre mot de passe.";
    } else {
        $database = new Database();
        $conn = $database->connect();

        $stmt = $conn->prepare('SELECT Id, Username, Password FROM Users WHERE Username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        // Message identique dans les deux cas : on ne révèle pas si le compte existe.
        if ($user === false || !password_verify($password, $user['Password'])) {
            $error = "Identifiant ou mot de passe incorrect.";
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']       = $user['Id'];
            $_SESSION['username']      = $user['Username'];
            $_SESSION['last_activity'] = time();

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — Admin Salon</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="login-body">

<div class="login-shell">
    <div class="login-card">
        <span class="login-mark">S</span>
        <h1>Administration</h1>
        <p class="login-sub">Connectez-vous pour gérer les réservations du salon.</p>

        <?php if ($error !== '') : ?>
            <div class="flash flash-err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <div class="fld">
                <label for="username">Identifiant</label>
                <input id="username" name="username" type="text" autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fld">
                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Connexion</button>
        </form>
    </div>
    <p class="login-legal">Accès réservé au personnel du salon.</p>
</div>

</body>
</html>
