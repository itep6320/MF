<?php
session_start();
require_once __DIR__ . '/functions.php';

// --- Configuration de sécurité ---
const FILMS_PATH = '/volume2/Films'; // à adapter à ton chemin réel

/**
 * Vérifie que le fichier est bien dans le dossier autorisé.
 * Empêche les accès par traversal (../../etc/passwd).
 */
function validate_video_path_allowed(string $path): bool
{
    $base = realpath(FILMS_PATH);
    $real = realpath($path);
    if ($real === false || $base === false) return false;
    return str_starts_with($real, $base);
}

// --- Vérification du token ---
$token = $_GET['token'] ?? '';
if (!$token || !isset($_SESSION['video_tokens'][$token])) {
    http_response_code(403);
    exit('Accès refusé');
}

$data = $_SESSION['video_tokens'][$token];

// ✅ Libérer le verrou sur la session pour ne pas bloquer les autres requêtes
session_write_close();

// --- Vérifier expiration du token ---
if ($data['expires'] < time()) {
    unset($_SESSION['video_tokens'][$token]);
    http_response_code(403);
    exit('Token expiré');
}

// --- Vérifier utilisateur ---
if (!isset($data['user_id']) || $data['user_id'] != 1) {
    http_response_code(403);
    exit('Token invalide');
}

// --- Récupération du chemin du film ---
$stmt = $pdo->prepare('SELECT chemin FROM films WHERE id = ?');
$stmt->execute([$data['film_id']]);
$film = $stmt->fetch();

if (!$film) {
    http_response_code(404);
    exit('Film introuvable');
}

$path = $film['chemin'];

// --- Validation du chemin ---
if (!validate_video_path_allowed($path) || !file_exists($path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Fichier introuvable ou non autorisé : ' . $path);
}

// --- Ouverture du fichier ---
$size = filesize($path);
if ($size === false || $size <= 0) {
    http_response_code(500);
    exit('Taille fichier invalide: $path');
}

$fm = fopen($path, 'rb');
if ($fm === false) {
    http_response_code(500);
    exit('Impossible d\'ouvrir le fichier');
}

// --- Gestion de la plage demandée (HTTP Range) ---
$start = 0;
$end = $size - 1;
$httpRange = $_SERVER['HTTP_RANGE'] ?? '';

if ($httpRange && preg_match('/bytes=(\d+)-(\d*)/', $httpRange, $matches)) {
    $start = intval($matches[1]);
    if (!empty($matches[2])) {
        $end = intval($matches[2]);
    }
    if ($start > $end || $end >= $size) {
        http_response_code(416); // Range Not Satisfiable
        fclose($fm);
        exit;
    }
    header("HTTP/1.1 206 Partial Content");
} else {
    header("HTTP/1.1 200 OK");
}

$end = min($end, $size - 1);
$length = $end - $start + 1;

// --- En-têtes HTTP ---
header("Content-Type: video/mp4");
header("Accept-Ranges: bytes");
header("Content-Length: $length");
if ($httpRange) {
    header("Content-Range: bytes $start-$end/$size");
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

// --- Envoi des données ---
while (ob_get_level()) {
    ob_end_clean();
}
fseek($fm, $start);

$buffer = 8192;
while (!feof($fm) && ftell($fm) <= $end) {
    if (ftell($fm) + $buffer > $end) {
        $buffer = $end - ftell($fm) + 1;
    }
    echo fread($fm, $buffer);
    flush();
}

fclose($fm);
exit;
