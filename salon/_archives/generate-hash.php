<?php
/**
 * Utilitaire de développement : génère le hash à insérer dans users.password.
 *
 *   1. Ouvrez cette page dans le navigateur
 *   2. Saisissez le mot de passe admin voulu
 *   3. Copiez le hash affiché et collez-le dans votre INSERT SQL
 *   4. SUPPRIMEZ CE FICHIER avant toute mise en ligne
 */

$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['pwd'])) {
    $hash = password_hash($_POST['pwd'], PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générateur de hash</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 640px; margin: 60px auto; padding: 0 20px; color: #241C1A; }
        input, button { font-size: 15px; padding: 9px 12px; border-radius: 6px; border: 1px solid #ccc; }
        button { background: #6B2737; color: #fff; border: none; cursor: pointer; }
        .out { background: #F3EDE4; padding: 14px; border-radius: 8px; margin-top: 22px;
               font-family: ui-monospace, monospace; font-size: 13px; word-break: break-all; }
        .warn { color: #9A3F2C; font-size: 13.5px; margin-top: 24px; }
    </style>
</head>
<body>
    <h2>Générateur de hash de mot de passe</h2>
    <form method="post">
        <input type="text" name="pwd" placeholder="Mot de passe admin" size="30" autofocus>
        <button type="submit">Générer</button>
    </form>

    <?php if ($hash !== '') : ?>
        <div class="out"><?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?></div>
        <p>Requête à exécuter dans phpMyAdmin :</p>
        <div class="out">INSERT INTO users (username, password) VALUES ('admin', '<?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?>');</div>
    <?php endif; ?>

    <p class="warn">⚠ Supprimez ce fichier une fois le compte créé.</p>
</body>
</html>
