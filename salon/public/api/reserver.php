<?php
/**
 * POST reserver.php
 * Corps JSON : { prestation, date, heure, nom, email, tel }
 *
 * Vérifie la disponibilité, enregistre en base dans une transaction
 * verrouillée (deux clients simultanés ne peuvent pas obtenir le même
 * créneau), puis envoie les notifications.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../api/database.php';
require_once __DIR__ . '/../../includes/creneaux.php';
require_once __DIR__ . '/../../includes/notifications.php';

function repondre(int $code, array $payload)
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    repondre(405, ['ok' => false, 'message' => 'Méthode non autorisée.']);
}

$in = json_decode(file_get_contents('php://input'), true) ?? [];

$prestationId = (int) ($in['prestation'] ?? 0);
$date         = trim($in['date']  ?? '');
$heure        = trim($in['heure'] ?? '');
$nom          = trim($in['nom']   ?? '');
$email        = trim($in['email'] ?? '');
$tel          = trim($in['tel']   ?? '');

/* --- Validation ------------------------------------------------------- */
if ($prestationId <= 0
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    || !preg_match('/^\d{2}:\d{2}$/', $heure)) {
    repondre(400, ['ok' => false, 'message' => 'Créneau invalide.']);
}
if ($nom === '' || $email === '' || $tel === '') {
    repondre(400, ['ok' => false, 'message' => 'Merci de renseigner votre nom, votre e-mail et votre téléphone.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    repondre(400, ['ok' => false, 'message' => 'Cette adresse e-mail ne semble pas valide.']);
}
if (!preg_match('/^[0-9 +().-]{8,20}$/', $tel)) {
    repondre(400, ['ok' => false, 'message' => 'Ce numéro de téléphone ne semble pas valide.']);
}

$conn = (new Database())->connect();

$stmt = $conn->prepare('SELECT Id, Service, Delay, Prices FROM Services WHERE Id = :id AND Active = 1');
$stmt->execute([':id' => $prestationId]);
$prestation = $stmt->fetch();

if (!$prestation) {
    repondre(404, ['ok' => false, 'message' => 'Prestation indisponible.']);
}

$duree     = (int) $prestation['Delay'];
$dateDebut = $date . ' ' . $heure . ':00';
$dateFin   = date('Y-m-d H:i:s', strtotime($dateDebut) + $duree * 60);

/* Le créneau demandé doit faire partie des créneaux réellement ouverts :
   cela couvre le jour de fermeture, la pause déjeuner et le délai minimum. */
$valides = array_column(array_filter(
    creneaux_du_jour($conn, $date, $duree),
    static fn($c) => $c['libre']
), 'debut');

if (!in_array($heure, $valides, true)) {
    repondre(409, ['ok' => false, 'message' => 'Ce créneau n\'est plus disponible. Merci d\'en choisir un autre.']);
}

/* --- Enregistrement sous transaction verrouillée ---------------------- */
try {
    $conn->beginTransaction();

    // Verrouille les lignes du jour : un second client en attente ne pourra
    // lire qu'après validation de la présente transaction.
    $lock = $conn->prepare("
        SELECT Id FROM Reservations
        WHERE Status = 'confirmed'
          AND DATE(StartDate) = :d
          AND StartDate < :fin
          AND EndDate   > :debut
        FOR UPDATE
    ");
    $lock->execute([':d' => $date, ':debut' => $dateDebut, ':fin' => $dateFin]);

    if ($lock->rowCount() > 0) {
        $conn->rollBack();
        repondre(409, ['ok' => false, 'message' => 'Ce créneau vient d\'être réservé. Merci d\'en choisir un autre.']);
    }

    $ins = $conn->prepare('
        INSERT INTO Reservations (ServiceId, ClientName, ClientEmail, ClientTel, StartDate, EndDate, Status)
        VALUES (:p, :n, :e, :t, :debut, :fin, \'confirmed\')
    ');
    $ins->execute([
        ':p'     => $prestationId,
        ':n'     => $nom,
        ':e'     => $email,
        ':t'     => $tel,
        ':debut' => $dateDebut,
        ':fin'   => $dateFin,
    ]);

    $id = (int) $conn->lastInsertId();
    $conn->commit();

} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Réservation échouée : ' . $e->getMessage());
    repondre(500, ['ok' => false, 'message' => 'L\'enregistrement a échoué. Merci de réessayer.']);
}

/* --- Notifications ---------------------------------------------------- */
$resa = [
    'Id'           => $id,
    'prestation'   => $prestation['Service'],
    'Delay'        => $duree,
    'Prices'       => $prestation['Prices'],
    'ClientName'   => $nom,
    'ClientEmail'  => $email,
    'ClientTel'    => $tel,
    'StartDate'    => $dateDebut,
    'EndDate'      => $dateFin,
    'date_longue'  => fmt_date_longue($date),
    'heure_debut'  => date('H\hi', strtotime($dateDebut)),
    'heure_fin'    => date('H\hi', strtotime($dateFin)),
];

// La réservation est déjà enregistrée : un échec d'envoi ne doit pas
// faire échouer la réponse au client.
$mailClient = notifier_client($resa);
$mailSalon  = notifier_salon($resa);
notifier_webhook($resa);

echo json_encode([
    'ok'          => true,
    'reservation' => $resa,
    'mail_client' => $mailClient,
    'mail_salon'  => $mailSalon,
], JSON_UNESCAPED_UNICODE);
