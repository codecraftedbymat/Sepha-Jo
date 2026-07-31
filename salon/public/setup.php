<?php
/**
 * INSTALLATION — fichier temporaire.
 *
 * Crée les tables, le catalogue de prestations et le premier compte
 * administrateur, en une seule page. Conçu pour Railway, dont l'éditeur
 * de requêtes n'accepte qu'une instruction SQL à la fois.
 *
 * SÉCURITÉ : la page se désactive d'elle-même dès qu'un compte existe.
 * Supprimez-la malgré tout une fois l'installation terminée.
 */

require_once __DIR__ . '/../api/database.php';
require_once __DIR__ . '/../includes/config.php';

$conn = (new Database())->connect();

/* --- Définition des tables -------------------------------------------- */
$tables = [
    'users' => "
        CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(50)  NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'prestations' => "
        CREATE TABLE IF NOT EXISTS prestations (
            id    INT AUTO_INCREMENT PRIMARY KEY,
            nom   VARCHAR(120) NOT NULL,
            duree INT NOT NULL,
            prix  DECIMAL(6,2) DEFAULT NULL,
            actif TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    'reservations' => "
        CREATE TABLE IF NOT EXISTS reservations (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            prestation_id INT NOT NULL,
            client_nom    VARCHAR(120) NOT NULL,
            client_email  VARCHAR(180) NOT NULL,
            client_tel    VARCHAR(30)  NOT NULL,
            date_debut    DATETIME NOT NULL,
            date_fin      DATETIME NOT NULL,
            statut        ENUM('confirmee','annulee') NOT NULL DEFAULT 'confirmee',
            created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_resa_presta FOREIGN KEY (prestation_id) REFERENCES prestations(id),
            INDEX idx_debut (date_debut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

/* --- Catalogue Sepha-Jo by Lotte --------------------------------------
   Tarifs repris du site. Les durées sont des ESTIMATIONS : corrigez-les
   depuis l'administration, menu Prestations.
   --------------------------------------------------------------------- */
$catalogue = [
    ['Deluxe gelaatsverzorging',        75,  60.00],
    ['Dermaplanning',                   60,  70.00],
    ['Dermaplanning + Microneedling',  120, 140.00],
    ['Dermaplanning + Kruidenpeeling', 120, 140.00],
    ['Microneedling',                   75, 100.00],
    ['Kruidenpeeling',                  75, 100.00],
    ['Pedicure (aan huis)',             60,  35.00],
    ['Pedicure met gellak (aan huis)',  75,  55.00],
    ['Lashlift',                        60,  45.00],
    ['Lashlift + tint',                 75,  55.00],
    ['Verven wimpers',                  15,  10.00],
    ['Browlamination',                  45,  45.00],
    ['Browlamination + tint',           60,  55.00],
    ['Verven wenkbrauwen',              15,  10.00],
    ['Epilatie wenkbrauwen',            15,  10.00],
    ['Epilatie onderbenen',             30,  20.00],
    ['Epilatie boven- en onderbenen',   45,  40.00],
    ['Epilatie kin',                    15,  10.00],
    ['Epilatie bovenlip',               15,  10.00],
    ['Epilatie volledig gelaat',        30,  30.00],
    ['Epilatie oksels',                 20,  20.00],
    ['Sessie Oorkaarsen',               30,  10.00],
];

$journal  = [];
$erreur   = '';
$termine  = false;

/* --- La page est-elle encore utilisable ? ------------------------------ */
$verrouille = false;
try {
    $n = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ((int) $n > 0) {
        $verrouille = true;
    }
} catch (Throwable $e) {
    // La table n'existe pas encore : installation à faire.
}

/* --- Exécution --------------------------------------------------------- */
if (!$verrouille && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant = trim($_POST['username'] ?? '');
    $motdepasse  = $_POST['password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $identifiant)) {
        $erreur = "Identifiant invalide : 3 à 50 caractères, lettres, chiffres, point, tiret ou souligné.";
    } elseif (strlen($motdepasse) < 10) {
        $erreur = "Le mot de passe doit faire au moins 10 caractères.";
    } else {
        try {
            foreach ($tables as $nom => $sql) {
                $conn->exec($sql);
                $journal[] = "Table « $nom » prête.";
            }

            $dejaLa = (int) $conn->query("SELECT COUNT(*) FROM prestations")->fetchColumn();
            if ($dejaLa === 0) {
                $ins = $conn->prepare('INSERT INTO prestations (nom, duree, prix) VALUES (:n, :d, :p)');
                foreach ($catalogue as [$nom, $duree, $prix]) {
                    $ins->execute([':n' => $nom, ':d' => $duree, ':p' => $prix]);
                }
                $journal[] = count($catalogue) . " prestations ajoutées.";
            } else {
                $journal[] = "Catalogue déjà rempli ($dejaLa prestations) : aucun ajout.";
            }

            $conn->prepare('INSERT INTO users (username, password) VALUES (:u, :p)')
                 ->execute([
                     ':u' => $identifiant,
                     ':p' => password_hash($motdepasse, PASSWORD_DEFAULT),
                 ]);
            $journal[] = "Compte administrateur « $identifiant » créé.";

            $termine = true;

        } catch (Throwable $e) {
            $erreur = $e->getMessage();
        }
    }
}

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installation</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 620px;
               margin: 50px auto; padding: 0 22px; color: #3D2A20; background: #FBF6EF; }
        h1 { font-weight: 600; font-size: 26px; margin-bottom: 6px; }
        .sub { color: #9E9186; font-size: 14px; margin-bottom: 30px; line-height: 1.55; }
        .card { background: #fff; border: 1px solid #EBDFD2; border-radius: 12px;
                padding: 24px; }
        label { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase;
                letter-spacing: .05em; color: #7A5A47; margin: 0 0 6px; }
        input { width: 100%; font-size: 15px; padding: 11px 13px; border-radius: 9px;
                border: 1.5px solid #EBDFD2; background: #FBF6EF; color: #3D2A20;
                margin-bottom: 18px; box-sizing: border-box; }
        input:focus { outline: none; border-color: #C97B4A; background: #fff; }
        button { width: 100%; font-size: 15px; padding: 13px; border-radius: 100px;
                 border: none; background: #C97B4A; color: #fff; font-weight: 600;
                 cursor: pointer; }
        button:hover { background: #A85D31; }
        .ok  { background: #EDF2EA; border: 1px solid #CBD9C2; color: #4C6140;
               padding: 14px 16px; border-radius: 10px; font-size: 14px; }
        .ko  { background: #FBEAE6; border: 1px solid #E8C6BF; color: #A83A2B;
               padding: 14px 16px; border-radius: 10px; font-size: 13.5px;
               margin-bottom: 18px; word-break: break-word; }
        ul { margin: 12px 0 0; padding-left: 20px; font-size: 14px; line-height: 1.9; }
        a.btn { display: block; text-align: center; margin-top: 18px; padding: 13px;
                border-radius: 100px; background: #C97B4A; color: #fff;
                text-decoration: none; font-weight: 600; font-size: 15px; }
        .warn { background: #FBEAE6; border: 1px solid #E8C6BF; color: #A83A2B;
                padding: 13px 16px; border-radius: 10px; font-size: 13.5px;
                margin-top: 26px; line-height: 1.6; }
        code { background: #F2E9DE; padding: 1px 6px; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>

<h1>Installation</h1>
<p class="sub">
    Cette page crée les tables, ajoute le catalogue de prestations et votre
    premier compte administrateur.
</p>

<?php if ($verrouille) : ?>

    <div class="card">
        <div class="ok">
            <strong>Installation déjà effectuée.</strong><br>
            Un compte administrateur existe : cette page est désactivée.
        </div>
        <a class="btn" href="admin/login.php">Aller à l'administration</a>
    </div>

    <p class="warn">
        Supprimez maintenant le fichier <code>public/setup.php</code> de votre dépôt,
        puis faites un <code>git push</code>.
    </p>

<?php elseif ($termine) : ?>

    <div class="card">
        <div class="ok">
            <strong>Installation terminée.</strong>
            <ul>
                <?php foreach ($journal as $l) : ?>
                    <li><?= h($l) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <a class="btn" href="admin/login.php">Se connecter à l'administration</a>
    </div>

    <p class="warn">
        <strong>À faire tout de suite :</strong> supprimez les fichiers
        <code>public/setup.php</code>, <code>public/admin/generate-hash.php</code> et
        <code>public/test-mail.php</code> de votre dépôt, puis
        <code>git push</code>.<br><br>
        Pensez ensuite à vérifier les <strong>durées</strong> de chaque prestation dans
        le menu Prestations : ce sont des estimations, et elles déterminent
        l'espacement des créneaux proposés aux clientes.
    </p>

<?php else : ?>

    <div class="card">
        <?php if ($erreur !== '') : ?>
            <div class="ko"><?= h($erreur) ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="username">Identifiant administrateur</label>
            <input id="username" name="username" type="text" value="<?= h($_POST['username'] ?? '') ?>"
                   placeholder="lotte" autofocus required>

            <label for="password">Mot de passe</label>
            <input id="password" name="password" type="text"
                   placeholder="10 caractères minimum" required>

            <button type="submit">Lancer l'installation</button>
        </form>
    </div>

    <p class="warn">
        Le mot de passe est affiché en clair pour que vous puissiez le relire avant
        de valider. Ne montrez pas cet écran à quelqu'un d'autre.
    </p>

<?php endif; ?>

</body>
</html>
