<?php
/**
 * Génération de l'événement d'agenda (.ics) et envoi des e-mails.
 */

require_once __DIR__ . '/config.php';

/* --- Chargement de PHPMailer -----------------------------------------
   Deux installations possibles, dans cet ordre :
     1. via Composer      -> vendor/autoload.php
     2. dépôt manuel      -> lib/PHPMailer/src/*.php
   Si aucune n'est présente, le code retombe sur la fonction mail().
   --------------------------------------------------------------------- */
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
$manuel   = dirname(__DIR__) . '/lib/PHPMailer/src/PHPMailer.php';

if (is_file($autoload)) {
    require_once $autoload;
} elseif (is_file($manuel)) {
    require_once dirname(__DIR__) . '/lib/PHPMailer/src/Exception.php';
    require_once dirname(__DIR__) . '/lib/PHPMailer/src/PHPMailer.php';
    require_once dirname(__DIR__) . '/lib/PHPMailer/src/SMTP.php';
}

/**
 * Construit un événement iCalendar. Ouvert dans Google Agenda, Outlook ou
 * Apple Calendar, il crée le rendez-vous directement dans l'agenda.
 */
function generer_ics(array $r, int $sequence = 0): string
{
    $fmt = static fn(string $dt): string => date('Ymd\THis', strtotime($dt));

    $description = sprintf(
        'Client : %s\\nTéléphone : %s\\nE-mail : %s\\nPrestation : %s (%d min)',
        $r['ClientName'], $r['ClientTel'], $r['ClientEmail'], $r['prestation'], $r['Delay']
    );

    $lignes = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//' . SALON_NOM . '//Reservation//FR',
        'CALSCALE:GREGORIAN',
        'METHOD:REQUEST',
        'BEGIN:VEVENT',
        'UID:resa-' . $r['Id'] . '@' . parse_url(SALON_URL, PHP_URL_HOST),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'SEQUENCE:' . $sequence,
        'DTSTART;TZID=Europe/Paris:' . $fmt($r['StartDate']),
        'DTEND;TZID=Europe/Paris:'   . $fmt($r['EndDate']),
        'SUMMARY:' . $r['prestation'] . ' — ' . $r['ClientName'],
        'DESCRIPTION:' . $description,
        'LOCATION:' . SALON_ADRESSE,
        'STATUS:CONFIRMED',
        'BEGIN:VALARM',
        'TRIGGER:-PT1H',
        'ACTION:DISPLAY',
        'DESCRIPTION:Rendez-vous dans 1 heure',
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    return implode("\r\n", $lignes);
}

/**
 * Envoie un e-mail HTML avec le .ics en pièce jointe.
 *
 * Utilise PHPMailer en SMTP si SMTP_HOST est renseigné (seule méthode qui
 * fonctionne sur Railway et en local), sinon retombe sur mail(). Si aucun
 * SMTP n'est configuré, l'envoi est ignoré : la réservation reste valide.
 */
function envoyer_mail(string $destinataire, string $sujet, string $corpsHtml, string $ics = ''): bool
{
    if (SMTP_HOST !== '' && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        return envoyer_via_smtp($destinataire, $sujet, $corpsHtml, $ics);
    }

    // Aucun SMTP configuré : inutile d'essayer mail(), qui échoue en ligne.
    if (SMTP_HOST === '' && getenv('MYSQLHOST')) {
        error_log('E-mail non envoye (aucun SMTP configure) : ' . $destinataire);
        return false;
    }

    return envoyer_via_mail_natif($destinataire, $sujet, $corpsHtml, $ics);
}

function envoyer_via_smtp(string $destinataire, string $sujet, string $corpsHtml, string $ics): bool
{
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = SMTP_USER !== '';
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_PORT === 465
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SALON_NOM);
        $mail->addAddress($destinataire);
        $mail->addReplyTo(SALON_EMAIL, SALON_NOM);

        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $corpsHtml;

        if ($ics !== '') {
            $mail->addStringAttachment($ics, 'rendez-vous.ics', 'base64', 'text/calendar');
        }

        return $mail->send();

    } catch (Throwable $e) {
        error_log('Envoi SMTP echoue : ' . $e->getMessage());
        return false;
    }
}

function envoyer_via_mail_natif(string $destinataire, string $sujet, string $corpsHtml, string $ics): bool
{
    $limite = '=_' . md5(uniqid('', true));

    $entetes  = 'From: ' . SALON_NOM . ' <' . SMTP_FROM . ">\r\n";
    $entetes .= 'Reply-To: ' . SALON_EMAIL . "\r\n";
    $entetes .= "MIME-Version: 1.0\r\n";
    $entetes .= 'Content-Type: multipart/mixed; boundary="' . $limite . '"' . "\r\n";

    $corps  = '--' . $limite . "\r\n";
    $corps .= "Content-Type: text/html; charset=UTF-8\r\n";
    $corps .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $corps .= $corpsHtml . "\r\n\r\n";

    if ($ics !== '') {
        $corps .= '--' . $limite . "\r\n";
        $corps .= "Content-Type: text/calendar; charset=UTF-8; name=\"rendez-vous.ics\"\r\n";
        $corps .= "Content-Transfer-Encoding: base64\r\n";
        $corps .= "Content-Disposition: attachment; filename=\"rendez-vous.ics\"\r\n\r\n";
        $corps .= chunk_split(base64_encode($ics)) . "\r\n";
    }

    $corps .= '--' . $limite . "--";

    return @mail($destinataire, '=?UTF-8?B?' . base64_encode($sujet) . '?=', $corps, $entetes);
}

/* --- Gabarit HTML commun aux deux e-mails ----------------------------- */
function gabarit_mail(string $titre, string $intro, array $lignes, string $pied = ''): string
{
    $rows = '';
    foreach ($lignes as $label => $valeur) {
        $rows .= '<tr>
            <td style="padding:8px 0;color:#9E9186;font-size:13px;">' . esc_mail($label) . '</td>
            <td style="padding:8px 0;text-align:right;font-weight:600;font-size:13px;color:#3D2A20;">' . esc_mail($valeur) . '</td>
        </tr>';
    }

    return '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#FBF6EF;font-family:Helvetica,Arial,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #EBDFD2;border-radius:14px;">
      <tr><td style="padding:30px 32px 8px;">
        <div style="font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#A85D31;font-weight:bold;">' . esc_mail(SALON_NOM) . '</div>
        <h1 style="margin:10px 0 6px;font-size:22px;color:#3D2A20;font-weight:normal;">' . esc_mail($titre) . '</h1>
        <p style="margin:0 0 20px;color:#7A5A47;font-size:14px;line-height:1.55;">' . esc_mail($intro) . '</p>
        <table width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #EBDFD2;">' . $rows . '</table>
      </td></tr>
      <tr><td style="padding:18px 32px 28px;color:#9E9186;font-size:12px;line-height:1.6;border-top:1px solid #EBDFD2;">'
        . ($pied !== '' ? esc_mail($pied) . '<br><br>' : '')
        . esc_mail(SALON_ADRESSE) . '<br>' . esc_mail(SALON_TEL) .
      '</td></tr>
    </table></body></html>';
}

function esc_mail($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/* --- Les deux envois -------------------------------------------------- */

function notifier_client(array $r): bool
{
    $html = gabarit_mail(
        'Votre rendez-vous est confirmé',
        'Bonjour ' . $r['ClientName'] . ', nous vous confirmons votre rendez-vous. Le fichier joint vous permet de l\'ajouter à votre agenda en un clic.',
        [
            'Prestation' => $r['prestation'],
            'Date'       => $r['date_longue'],
            'Horaire'    => $r['heure_debut'] . ' – ' . $r['heure_fin'],
            'Durée'      => $r['Delay'] . ' min',
            'Tarif'      => $r['Prices'] !== null ? $r['Prices'] . ' €' : '—',
        ],
        'Un empêchement ? Prévenez-nous au moins 24 h à l\'avance par téléphone.'
    );

    return envoyer_mail($r['ClientEmail'], 'Confirmation de votre rendez-vous — ' . SALON_NOM, $html, generer_ics($r));
}

function notifier_salon(array $r): bool
{
    $html = gabarit_mail(
        'Nouvelle réservation',
        'Une réservation vient d\'être enregistrée sur le site. Ouvrez la pièce jointe pour l\'ajouter à l\'agenda du salon.',
        [
            'Client'     => $r['ClientName'],
            'Téléphone'  => $r['ClientTel'],
            'E-mail'     => $r['ClientEmail'],
            'Prestation' => $r['prestation'],
            'Date'       => $r['date_longue'],
            'Horaire'    => $r['heure_debut'] . ' – ' . $r['heure_fin'],
        ]
    );

    return envoyer_mail(SALON_EMAIL, 'Nouvelle réservation : ' . $r['ClientName'] . ' — ' . $r['date_longue'], $html, generer_ics($r));
}

function notifier_modification(array $r, array $avant): bool
{
    $changements = [];
    if ($avant['prestation'] !== $r['prestation']) {
        $changements['Prestation'] = $avant['prestation'] . '  ->  ' . $r['prestation'];
    }
    if ($avant['date_longue'] !== $r['date_longue']) {
        $changements['Date'] = $avant['date_longue'] . '  ->  ' . $r['date_longue'];
    }
    if ($avant['heure_debut'] !== $r['heure_debut'] || $avant['heure_fin'] !== $r['heure_fin']) {
        $changements['Horaire'] = $avant['heure_debut'] . ' – ' . $avant['heure_fin']
                                . '  ->  ' . $r['heure_debut'] . ' – ' . $r['heure_fin'];
    }

    $lignes = [
        'Prestation' => $r['prestation'],
        'Date'       => $r['date_longue'],
        'Horaire'    => $r['heure_debut'] . ' – ' . $r['heure_fin'],
        'Durée'      => $r['Delay'] . ' min',
    ];
    if ($r['Prices'] !== null) {
        $lignes['Tarif'] = $r['Prices'] . ' €';
    }
    foreach ($changements as $quoi => $detail) {
        $lignes['Modifié — ' . $quoi] = $detail;
    }

    $html = gabarit_mail(
        'Votre rendez-vous a été modifié',
        'Bonjour ' . $r['ClientName'] . ', votre rendez-vous a été déplacé. Voici les nouvelles informations. Le fichier joint met à jour votre agenda.',
        $lignes,
        'Ce créneau ne vous convient pas ? Contactez-nous, nous trouverons une autre solution.'
    );

    // Séquence 1 : indique aux agendas qu'il s'agit d'une mise à jour du
    // même événement, et non d'un nouveau rendez-vous.
    return envoyer_mail(
        $r['ClientEmail'],
        'Modification de votre rendez-vous — ' . SALON_NOM,
        $html,
        generer_ics($r, 1)
    );
}

/**
 * Appel facultatif d'un webhook (Zapier, Make, Google Apps Script…) pour
 * créer l'événement dans un agenda en ligne sans intervention manuelle.
 */
function notifier_webhook(array $r): void
{
    if (WEBHOOK_AGENDA === '') {
        return;
    }

    $ch = curl_init(WEBHOOK_AGENDA);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($r, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
