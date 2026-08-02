<?php
/**
 * DIAGNOSTIC E-MAIL — fichier temporaire.
 * À placer dans public/ et à SUPPRIMER une fois le problème réglé.
 *
 * Ouvrir : http://localhost/salon/public/test-mail.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notifications.php';

$resultat = null;
$destinataire = trim($_POST['dest'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $destinataire !== '') {
    $html = '<p>Ceci est un message de test envoyé depuis le site de réservation.</p>'
          . '<p>Si vous le recevez, la configuration e-mail est correcte.</p>';
    $ok = envoyer_mail($destinataire, 'Test — ' . SALON_NOM, $html);
    $resultat = $ok;
}

function ligne(string $label, $valeur, ?bool $etat = null): void
{
    $couleur = $etat === null ? '#3D2A20' : ($etat ? '#4C6140' : '#A83A2B');
    $icone   = $etat === null ? '' : ($etat ? ' ✓' : ' ✗');
    echo '<tr><td>' . htmlspecialchars($label) . '</td>'
       . '<td style="color:' . $couleur . ';font-weight:600;">'
       . htmlspecialchars((string) $valeur) . $icone . '</td></tr>';
}

/* --- Collecte des informations ---------------------------------------- */
$phpVersion   = PHP_VERSION;
$phpOk        = version_compare($phpVersion, '7.4.0', '>=');
$mailExiste   = function_exists('mail');
$sendmailPath = ini_get('sendmail_path');
$smtpIni      = ini_get('SMTP');
$smtpPortIni  = ini_get('smtp_port');
$phpMailer    = class_exists(\PHPMailer\PHPMailer\PHPMailer::class);

$configLocal  = is_file(dirname(__DIR__) . '/includes/config.local.php');

// Quelle voie sera empruntée ?
if (BREVO_API_KEY !== '') {
    $voie = 'API HTTP de Brevo (port 443)';
} elseif (SMTP_HOST !== '' && $phpMailer) {
    $voie = 'PHPMailer en SMTP direct';
} elseif (SMTP_HOST === '' && getenv('MYSQLHOST')) {
    $voie = 'AUCUNE — envoi ignoré (en ligne sans SMTP configuré)';
} else {
    $voie = 'fonction mail() de PHP, via sendmail';
}

// Journal de sendmail
$logSendmail = 'C:\\xampp\\sendmail\\error.log';
$log = is_readable($logSendmail) ? trim((string) file_get_contents($logSendmail)) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnostic e-mail</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 760px;
               margin: 40px auto; padding: 0 20px; color: #3D2A20; background: #FBF6EF; }
        h2 { font-weight: 600; margin-top: 34px; }
        table { width: 100%; border-collapse: collapse; background: #fff;
                border: 1px solid #EBDFD2; border-radius: 10px; overflow: hidden; }
        td { padding: 10px 14px; border-bottom: 1px solid #F2E9DE; font-size: 14px; }
        tr:last-child td { border-bottom: none; }
        td:first-child { color: #7A5A47; width: 45%; }
        .box { background: #fff; border: 1px solid #EBDFD2; border-radius: 10px;
               padding: 14px 16px; font-family: ui-monospace, Consolas, monospace;
               font-size: 12.5px; white-space: pre-wrap; word-break: break-word;
               max-height: 260px; overflow: auto; }
        .ok  { background: #EDF2EA; border: 1px solid #CBD9C2; color: #4C6140;
               padding: 13px 16px; border-radius: 10px; }
        .ko  { background: #FBEAE6; border: 1px solid #E8C6BF; color: #A83A2B;
               padding: 13px 16px; border-radius: 10px; }
        input { font-size: 15px; padding: 10px 12px; border-radius: 8px;
                border: 1.5px solid #EBDFD2; background: #fff; min-width: 260px; }
        button { font-size: 15px; padding: 10px 20px; border-radius: 8px; border: none;
                 background: #C97B4A; color: #fff; cursor: pointer; font-weight: 600; }
        .warn { background: #FBEAE6; border: 1px solid #E8C6BF; color: #A83A2B;
                padding: 12px 15px; border-radius: 9px; font-size: 13.5px; margin-top: 34px; }
    </style>
</head>
<body>

<h1>Diagnostic e-mail</h1>

<h2>1. Environnement PHP</h2>
<table>
    <?php
    ligne('Version de PHP', $phpVersion, $phpOk);
    ligne('Fonction mail() disponible', $mailExiste ? 'oui' : 'non', $mailExiste);
    ligne('sendmail_path', $sendmailPath ?: '(vide)', (bool) $sendmailPath);
    ligne('SMTP (php.ini)', $smtpIni ?: '(vide)', null);
    ligne('smtp_port (php.ini)', $smtpPortIni ?: '(vide)', null);
    ligne('PHPMailer installé', $phpMailer ? 'oui' : 'non (composer install)', null);
    ?>
</table>

<h2>2. Configuration du site</h2>
<table>
    <?php
    ligne('config.local.php présent', $configLocal ? 'oui' : 'non', null);
    ligne('SALON_NOM', SALON_NOM, null);
    ligne('SALON_EMAIL (reçoit les notifications)', SALON_EMAIL,
          SALON_EMAIL !== 'contact@salon-eclat.fr');
    ligne('SMTP_FROM (adresse expéditrice)', SMTP_FROM,
          SMTP_FROM !== 'contact@salon-eclat.fr');
    ligne('BREVO_API_KEY', BREVO_API_KEY !== '' ? '(renseignée)' : '(vide)',
          BREVO_API_KEY !== '' ? true : null);
    ligne('SMTP_HOST', SMTP_HOST ?: '(vide)', null);
    ligne('SMTP_PORT', SMTP_PORT ?: '(vide)', null);
    ligne('SMTP_USER', SMTP_USER ?: '(vide)', null);
    ligne('SMTP_PASS', SMTP_PASS !== '' ? '(renseignée)' : '(vide)', null);
    ?>
</table>

<p style="font-size:14px;color:#7A5A47;">
    Voie d'envoi qui sera utilisée : <strong><?= htmlspecialchars($voie) ?></strong>
</p>

<?php if (SALON_EMAIL === 'contact@salon-eclat.fr' || SMTP_FROM === 'contact@salon-eclat.fr') : ?>
    <div class="ko" style="margin-top:14px;">
        <strong>Problème détecté.</strong> L'adresse <code>contact@salon-eclat.fr</code> est
        la valeur d'exemple : elle n'existe pas et n'est pas validée chez Brevo, donc
        tout envoi est rejeté. Renseignez votre propre adresse (voir plus bas).
    </div>
<?php endif; ?>

<h2>3. Envoyer un message de test</h2>
<form method="post">
    <input type="email" name="dest" placeholder="votre@email.fr"
           value="<?= htmlspecialchars($destinataire) ?>" required>
    <button type="submit">Envoyer</button>
</form>

<?php if ($resultat === true) : ?>
    <p class="ok" style="margin-top:16px;">
        La fonction d'envoi a répondu positivement. Vérifiez votre boîte de réception,
        <strong>et le dossier indésirables</strong>. Si rien n'arrive, consultez le
        journal ci-dessous et les statistiques de votre compte Brevo.
    </p>
<?php elseif ($resultat === false) : ?>
    <p class="ko" style="margin-top:16px;">
        L'envoi a échoué. Le détail se trouve dans le journal ci-dessous.
    </p>
<?php endif; ?>

<h2>4. Journal de sendmail</h2>
<?php if ($log === null) : ?>
    <p style="font-size:14px;color:#9E9186;">
        Fichier introuvable ou illisible : <code><?= htmlspecialchars($logSendmail) ?></code>.
        C'est normal si aucun envoi n'a encore été tenté par cette voie.
    </p>
<?php elseif ($log === '') : ?>
    <p style="font-size:14px;color:#9E9186;">Le journal est vide : aucune erreur enregistrée.</p>
<?php else : ?>
    <div class="box"><?= htmlspecialchars($log) ?></div>
<?php endif; ?>

<h2>5. Points à vérifier chez Brevo</h2>
<table>
    <tr><td>Expéditeur validé</td><td>Expéditeurs, domaine, IP → l'adresse doit être confirmée</td></tr>
    <tr><td>Clé SMTP active</td><td>SMTP et API → la clé ne doit pas être expirée ni supprimée</td></tr>
    <tr><td>Envois enregistrés</td><td>Transactionnel → Journaux : les tentatives y apparaissent</td></tr>
</table>

<p class="warn">
    Supprimez ce fichier une fois le diagnostic terminé : il affiche des éléments
    de configuration qui n'ont pas à être publics.
</p>

</body>
</html>
