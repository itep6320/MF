<?php
session_start();
require_once __DIR__ . '/config.php';

// ✅ Vérification token
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

$filmId = $_SESSION['video_tokens'][$token]['film_id'];

// ✅ Récupération du chemin du fichier
$stmt = $pdo->prepare("SELECT chemin FROM films WHERE id=? LIMIT 1");
$stmt->execute([$filmId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit("Film introuvable");
}

$path = $row['chemin'];

if (!file_exists($path)) {
    http_response_code(404);
    exit("Fichier introuvable");
}

// ✅ Le navigateur peut fermer la connexion sans bloquer PHP
ignore_user_abort(true);

// ✅ En-têtes MP4 pour streaming progressif
header("Content-Type: video/mp4");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");
header("Accept-Ranges: none");

// ✅ Commande FFmpeg optimisée (lecture immédiate)
$ffmpeg  = '/var/packages/ffmpeg/target/bin/ffmpeg';

    if (file_exists($ffmpeg)) {
        $cmd = "$ffmpeg -loglevel error -i " . escapeshellarg($path) .
        "-vcodec libx264 -preset veryfast -tune fastdecode " .
        "-acodec aac -b:a 128k " .
        "-movflags frag_keyframe+empty_moov " .
        "-f mp4 -";

        // ✅ Démarre ffmpeg
        $proc = popen($cmd, 'r');
    }

if (!$proc) {
    http_response_code(500);
    exit("Erreur FFmpeg");
}

// ✅ Envoie les données en continu
while (!feof($proc)) {

    // Si l'utilisateur a quitté la page → on stoppe ffmpeg
    if (connection_aborted()) {
        pclose($proc);
        exit;
    }

    echo fread($proc, 4096);
    flush();
}

// ✅ Fermeture propre
pclose($proc);
exit;
