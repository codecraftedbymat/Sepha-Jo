<?php
/**
 * Génération de l'événement d'agenda (.ics) et envoi des e-mails.
 */

require_once __DIR__ . '/config.php';

/**
 * Construit un événement iCalendar. Ouvert dans Google Agenda, Outlook ou
 * Apple Calendar, il crée le rendez-vous directement dans l'agenda.
 */
function generer_ics(array $r): string
{
    $fmt = static fn(string $dt): string => date('Ymd\THis', strtotime($dt));

    $description = sprintf(
        'Client : %s\\nTéléphone : %s\\nE-mail : %s\\nPrestation : %s (%d min)',
        $r['client_nom'], $r['client_tel'], $r['client_email'], $r['prestation'], $r['duree']
    );

    $lignes = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//' . SALON_NOM . '//Reservation//FR',
        'CALSCALE:GREGORIAN',
        'METHOD:REQUEST',
        'BEGIN:VEVENT',
        'UID:resa-' . $r['id'] . '@' . parse_url(SALON_URL, PHP_URL_HOST),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART;TZID=Europe/Paris:' . $fmt($r['date_debut']),
        'DTEND;TZID=Europe/Paris:'   . $fmt($r['date_fin']),
        'SUMMARY:' . $r['prestation'] . ' — ' . $r['client_nom'],
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
 */
function envoyer_mail(string $destinataire, string $sujet, string $corpsHtml, string $ics = ''): bool
{
    $limite = '=_' . md5(uniqid('', true));

    $entetes  = 'From: ' . SALON_NOM . ' <' . SALON_EMAIL . ">\r\n";
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
        'Bonjour ' . $r['client_nom'] . ', nous vous confirmons votre rendez-vous. Le fichier joint vous permet de l\'ajouter à votre agenda en un clic.',
        [
            'Prestation' => $r['prestation'],
            'Date'       => $r['date_longue'],
            'Horaire'    => $r['heure_debut'] . ' – ' . $r['heure_fin'],
            'Durée'      => $r['duree'] . ' min',
            'Tarif'      => $r['prix'] !== null ? $r['prix'] . ' €' : '—',
        ],
        'Un empêchement ? Prévenez-nous au moins 24 h à l\'avance par téléphone.'
    );

    return envoyer_mail($r['client_email'], 'Confirmation de votre rendez-vous — ' . SALON_NOM, $html, generer_ics($r));
}

function notifier_salon(array $r): bool
{
    $html = gabarit_mail(
        'Nouvelle réservation',
        'Une réservation vient d\'être enregistrée sur le site. Ouvrez la pièce jointe pour l\'ajouter à l\'agenda du salon.',
        [
            'Client'     => $r['client_nom'],
            'Téléphone'  => $r['client_tel'],
            'E-mail'     => $r['client_email'],
            'Prestation' => $r['prestation'],
            'Date'       => $r['date_longue'],
            'Horaire'    => $r['heure_debut'] . ' – ' . $r['heure_fin'],
        ]
    );

    return envoyer_mail(SALON_EMAIL, 'Nouvelle réservation : ' . $r['client_nom'] . ' — ' . $r['date_longue'], $html, generer_ics($r));
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
