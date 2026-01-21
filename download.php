<?php
session_start();
require_once __DIR__ . '/functions.php';

// --- Configuration de sécurité ---
const FILMS_PATH = '/volume2/Films'; // à adapter à ton chemin réel

/**
 * Vérifie que le fichier est bien dans le dossier autorisé.
 * Empêche les accès par traversal (../../etc/passwd).
 */
function validate_video_path_allowed(string $path): bool {
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

// ✅ Libérer le verrou de session avant d'envoyer le fichier
session_write_close();

// --- Vérifier expiration et utilisateur ---
if ($data['expires'] < time() || !is_admin() || !is_logged_in() || current_user_id() != $data['user_id']) {
    unset($_SESSION['video_tokens'][$token]);
    http_response_code(403);
    exit('Token expiré ou invalide');
}

// --- Récupération du fichier ---
$stmt = $pdo->prepare('SELECT chemin, titre FROM films WHERE id = ?');
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
    exit('Fichier introuvable ou non autorisé');
}

$filename = basename($film['titre']) . '.mp4';
$filesize = filesize($path);

while (ob_get_level()) { ob_end_clean(); }

// --- En-têtes sécurisés ---
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// --- Envoi du fichier par morceaux ---
$chunkSize = 1024 * 1024; // 1 Mo
$handle = fopen($path, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Impossible d\'ouvrir le fichier');
}

while (!feof($handle)) {
    echo fread($handle, $chunkSize);
    flush();
}

fclose($handle);
exit;
