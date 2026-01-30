<?php
session_start();
require_once __DIR__ . '/config.php';

/* =========================
   1. Vérification du token
   ========================= */
if (!isset($_GET['token'])) {
    http_response_code(403);
    exit("Token manquant");
}

$token = $_GET['token'];

if (
    empty($_SESSION['video_tokens'][$token]) ||
    $_SESSION['video_tokens'][$token]['expires'] < time()
) {
    http_response_code(403);
    exit("Token invalide ou expiré");
}

$episodeId = $_SESSION['video_tokens'][$token]['episode_id'];

/* =========================
   2. Récupération du fichier
   ========================= */
$stmt = $pdo->prepare("SELECT chemin FROM episodes WHERE id=? LIMIT 1");
$stmt->execute([$episodeId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit("Épisode introuvable");
}

$path = $row['chemin'];

if (!file_exists($path)) {
    http_response_code(404);
    exit("Fichier introuvable");
}

/* =========================
   3. Infos fichier
   ========================= */
$filesize = filesize($path);
$start = 0;
$end = $filesize - 1;
$length = $filesize;

/* =========================
   4. Gestion HTTP Range
   ========================= */
if (isset($_SERVER['HTTP_RANGE'])) {

    if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = intval($matches[1]);
        if ($matches[2] !== '') {
            $end = intval($matches[2]);
        }
        $length = ($end - $start) + 1;

        http_response_code(206);
        header("Content-Range: bytes $start-$end/$filesize");
    }
}

/* =========================
   5. Headers vidéo HTML5
   ========================= */
header("Content-Type: video/mp4");
header("Accept-Ranges: bytes");
header("Content-Length: $length");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/* =========================
   6. Envoi du fichier
   ========================= */
$fp = fopen($path, 'rb');
fseek($fp, $start);

$buffer = 8192;

while (!feof($fp) && ftell($fp) <= $end) {
    if (connection_aborted()) {
        fclose($fp);
        exit;
    }

    $bytesToRead = min($buffer, $end - ftell($fp) + 1);
    echo fread($fp, $bytesToRead);
    flush();
}

fclose($fp);
exit;
