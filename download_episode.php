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
$filename = basename($path);
$filesize = filesize($path);

// ⚠️ IMPORTANT : vider tout buffer PHP
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $filesize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');
header('Expires: 0');

$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(403);
    exit('Accès refusé');
}

$chunkSize = 8192;
while (!feof($fp)) {
    echo fread($fp, $chunkSize);
    flush();
}

fclose($fp);
exit;
