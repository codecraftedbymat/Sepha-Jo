<?php
/**
 * GET agenda.php?id=42
 * Renvoie l'événement .ics d'une réservation, à ouvrir dans un agenda.
 */

require_once __DIR__ . '/../../api/database.php';
require_once __DIR__ . '/../../includes/creneaux.php';
require_once __DIR__ . '/../../includes/notifications.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Réservation introuvable.');
}

$conn = (new Database())->connect();
$stmt = $conn->prepare('
    SELECT r.*, p.Service AS prestation, p.Delay
    FROM Reservations r
    JOIN Services p ON p.Id = r.ServiceId
    WHERE r.Id = :id
');
$stmt->execute([':id' => $id]);
$r = $stmt->fetch();

if (!$r) {
    http_response_code(404);
    exit('Réservation introuvable.');
}

$ics = generer_ics($r);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="rendez-vous.ics"');
header('Content-Length: ' . strlen($ics));
echo $ics;
