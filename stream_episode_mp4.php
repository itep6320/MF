<?php
require_once 'functions.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT chemin FROM episodes WHERE id = ?');
$stmt->execute([$id]);
$ep = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ep || empty($ep['chemin']) || !file_exists($ep['chemin'])) {
    http_response_code(404);
    exit('Fichier introuvable');
}

$path = $ep['chemin'];
$size = filesize($path);
$mime = mime_content_type($path) ?: 'video/mp4';

$start = 0;
$end = $size - 1;

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');

if (isset($_SERVER['HTTP_RANGE'])) {
    if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
        $start = (int)$matches[1];
        if ($matches[2] !== '') {
            $end = (int)$matches[2];
        }
        http_response_code(206);
    }
}

$length = $end - $start + 1;

header("Content-Length: $length");
header("Content-Range: bytes $start-$end/$size");

$fp = fopen($path, 'rb');
fseek($fp, $start);

$bufferSize = 8192;
while (!feof($fp) && ftell($fp) <= $end) {
    echo fread($fp, $bufferSize);
    flush();
}

fclose($fp);
exit;
