<?php
    session_start();
    include_once '../api/database.php';

    // Déjà connecté -> on va directement au dashboard
    if (isset($_SESSION['user_id'])) {
        header('Location: /admin/dashboard.php');
        exit;
    }

    $error = '';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Merci de renseigner votre identifiant et votre mot de passe.";
        } else {
            $database = new Database();
            $conn = $database->connect();

            $query = $conn->prepare('SELECT id, username, password FROM users WHERE username = :username');
            $query->execute([':username' => $username]);
            $user = $query->fetch(PDO::FETCH_ASSOC);

            // Message identique dans les deux cas : on n'indique jamais si le
            // compte existe ou non (évite l'énumération des identifiants).
            if ($user === false || !password_verify($password, $user['password'])) {
                $error = "Identifiant ou mot de passe incorrect.";
            } else {
                // Nouvel ID de session après authentification (anti fixation de session)
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['logged_at']  = time();

                header('Location: /admin/dashboard.php');
                exit;
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <title>Admin — Salon Éclat</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="dist/img/logo-salon.png">
    <link rel="stylesheet" type="text/css" href="fonts/font-awesome-4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="fonts/iconic/css/material-design-iconic-font.min.css">
    <link rel="stylesheet" type="text/css" href="css/util.css">
    <link rel="stylesheet" type="text/css" href="css/main.css">
    <style>
        .login-error {
            background: #FBEAE6;
            border: 1px solid #E8B8AE;
            color: #9A3F2C;
            font-size: 13.5px;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 18px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="limiter">
        <div class="container-login100" style="background-image: url('images/bg-02.jpg');">
            <div class="wrap-login100">
                <form class="login100-form validate-form" method="post">
                    <span class="login100-form-logo">
                        <img src="images/login.jpg" style="width: 130px; height: 130px;" alt=""/>
                    </span>
                    <span class="login100-form-title p-b-34 p-t-27">
                        Connexion
                    </span>

                    <?php if (!empty($error)) : ?>
                        <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <div class="wrap-input100 validate-input">
                        <input class="input100" type="text" placeholder="Identifiant" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <span class="focus-input100" data-placeholder="&#xf207;"></span>
                    </div>
                    <div class="wrap-input100 validate-input">
                        <input class="input100" type="password" placeholder="Mot de passe" name="password">
                        <span class="focus-input100" data-placeholder="&#xf191;"></span>
                    </div>
                    <div class="contact100-form-checkbox">
                        <input class="input-checkbox100" id="ckb1" type="checkbox" name="remember_checbox">
                        <label class="label-checkbox100" for="ckb1">
                            Se souvenir de moi
                        </label>
                    </div>
                    <div class="container-login100-form-btn">
                        <button class="login100-form-btn" type="submit">
                            Connexion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
