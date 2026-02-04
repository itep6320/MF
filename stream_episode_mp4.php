<?php
require_once 'functions.php';

$id = (int)($_GET['id'] ?? 0);

// ✅ LOG 1 : Début de la requête
error_log("=== STREAM_EPISODE_MP4 ===");
error_log("Episode ID: $id");
error_log("User ID: " . (is_logged_in() ? current_user_id() : 'non connecté'));
error_log("HTTP Range: " . ($_SERVER['HTTP_RANGE'] ?? 'aucun'));
error_log("Request URI: " . $_SERVER['REQUEST_URI']);

$stmt = $pdo->prepare('SELECT chemin FROM episodes WHERE id = ?');
$stmt->execute([$id]);
$ep = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ep || empty($ep['chemin']) || !file_exists($ep['chemin'])) {
    http_response_code(404);
    exit('Fichier introuvable');
}

// ✅ ENREGISTRER LA LECTURE UNIQUEMENT SI PAS DE RANGE (= première requête)
if (is_logged_in() && !isset($_SERVER['HTTP_RANGE'])) {
    try {
        error_log("📝 Mise à jour dernière lecture (UPSERT)...");

        $stmt = $pdo->prepare('
            INSERT INTO historique_lectures (utilisateur_id, episode_id, date_lecture, type)
            VALUES (?, ?, NOW(), 2)
            ON DUPLICATE KEY UPDATE
                date_lecture = NOW()
        ');
        $stmt->execute([current_user_id(), $id]);

        error_log("✅ Dernière lecture mise à jour (UPSERT)");
    } catch (Exception $e) {
        error_log("❌ Erreur UPSERT lecture: " . $e->getMessage());
    }
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