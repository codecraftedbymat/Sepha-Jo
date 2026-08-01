<?php
/**
 * Utilitaire de développement : génère le hash à placer dans Users.Password.
 *
 *   1. Ouvrez cette page dans le navigateur
 *   2. Saisissez le mot de passe voulu
 *   3. Copiez la requête qui correspond à votre cas (création ou modification)
 *   4. Exécutez-la dans phpMyAdmin (local) ou dans Data > Query (Railway)
 *   5. SUPPRIMEZ CE FICHIER
 */

$hash     = '';
$identifiant = trim($_POST['user'] ?? 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pwd'])) {
    $hash = password_hash($_POST['pwd'], PASSWORD_DEFAULT);
}

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Générateur de hash</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 680px;
               margin: 50px auto; padding: 0 20px; color: #3D2A20; background: #FBF6EF; }
        h2 { font-weight: 600; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
        input { font-size: 15px; padding: 10px 12px; border-radius: 8px;
                border: 1.5px solid #EBDFD2; background: #fff; color: #3D2A20; }
        button { font-size: 15px; padding: 10px 20px; border-radius: 8px; border: none;
                 background: #C97B4A; color: #fff; cursor: pointer; font-weight: 600; }
        button:hover { background: #A85D31; }
        .lab { font-size: 13px; color: #7A5A47; margin: 22px 0 6px; font-weight: 600; }
        .out { background: #fff; border: 1px solid #EBDFD2; padding: 13px 15px;
               border-radius: 9px; font-family: ui-monospace, Consolas, monospace;
               font-size: 13px; word-break: break-all; line-height: 1.5; }
        .warn { color: #A83A2B; font-size: 13.5px; margin-top: 30px;
                background: #FBEAE6; border: 1px solid #E8C6BF;
                padding: 12px 15px; border-radius: 9px; }
        .hint { color: #9E9186; font-size: 13px; margin-top: 4px; }
    </style>
</head>
<body>
    <h2>Générateur de hash de mot de passe</h2>

    <form method="post">
        <div class="row">
            <input type="text" name="user" value="<?= h($identifiant) ?>" placeholder="Identifiant" size="16">
            <input type="text" name="pwd" placeholder="Mot de passe" size="26" autofocus>
            <button type="submit">Générer</button>
        </div>
    </form>
    <p class="hint">Le mot de passe n'est jamais enregistré : seule son empreinte est calculée.</p>

    <?php if ($hash !== '') : ?>

        <p class="lab">Hash généré</p>
        <div class="out"><?= h($hash) ?></div>

        <p class="lab">A. Pour CRÉER le compte (première fois)</p>
        <div class="out">INSERT INTO Users (Username, Password) VALUES ('<?= h($identifiant) ?>', '<?= h($hash) ?>');</div>

        <p class="lab">B. Pour MODIFIER le mot de passe d'un compte existant</p>
        <div class="out">UPDATE Users SET Password = '<?= h($hash) ?>' WHERE Username = '<?= h($identifiant) ?>';</div>

        <p class="hint">
            Si la requête A renvoie « Duplicate entry », c'est que le compte existe déjà :
            utilisez la requête B.
        </p>

    <?php endif; ?>

    <p class="warn">
        Supprimez ce fichier une fois l'opération terminée. Tant qu'il est en place,
        n'importe qui peut se fabriquer un accès à l'administration.
    </p>
</body>
</html>
