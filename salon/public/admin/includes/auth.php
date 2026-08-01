<?php
/**
 * Inclus en tête de chaque page de l'admin.
 * Démarre la session, bloque les accès non authentifiés et expose $conn.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 3) . '/api/database.php';
require_once dirname(__DIR__, 3) . '/includes/config.php';

$database = new Database();
$conn = $database->connect();

/* --- Garde d'authentification ---------------------------------------- */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* --- Expiration de session après 2 h d'inactivité --------------------- */
$timeout = 2 * 60 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    session_unset();
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}
$_SESSION['last_activity'] = time();

/* --- Jeton CSRF pour les formulaires modifiant des données ------------ */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            http_response_code(403);
            die('Requête invalide.');
        }
    }
}

/* --- Petits utilitaires d'affichage ----------------------------------- */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fmt_date(string $sqlDatetime): string
{
    $ts = strtotime($sqlDatetime);
    $jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
    $mois  = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    return $jours[(int) date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $mois[(int) date('n', $ts) - 1];
}

function fmt_heure(string $sqlDatetime): string
{
    return date('H\hi', strtotime($sqlDatetime));
}

function initiales(string $nom): string
{
    $parts = preg_split('/\s+/', trim($nom));
    $out = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
    if (count($parts) > 1) {
        $out .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    return $out;
}
