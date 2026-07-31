<?php
/**
 * GET creneaux.php?prestation=3&date=2026-08-12
 * Renvoie les créneaux de la journée avec leur disponibilité.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../api/database.php';
require_once __DIR__ . '/../../includes/creneaux.php';

$prestationId = (int) ($_GET['prestation'] ?? 0);
$date         = $_GET['date'] ?? '';

if ($prestationId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Paramètres invalides.']);
    exit;
}

$conn = (new Database())->connect();

$stmt = $conn->prepare('SELECT id, nom, duree, prix FROM prestations WHERE id = :id AND actif = 1');
$stmt->execute([':id' => $prestationId]);
$prestation = $stmt->fetch();

if (!$prestation) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Prestation introuvable.']);
    exit;
}

$creneaux = creneaux_du_jour($conn, $date, (int) $prestation['duree']);

echo json_encode([
    'ok'       => true,
    'ferme'    => jour_ferme($date),
    'date'     => $date,
    'longue'   => fmt_date_longue($date),
    'creneaux' => $creneaux,
], JSON_UNESCAPED_UNICODE);
