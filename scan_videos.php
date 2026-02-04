<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in() || ($_SESSION['admin_level'] ?? 0) < 2) {
    http_response_code(403);
    exit('⛔ Accès réservé aux administrateurs niveau 2');
}

header('Content-Type: text/html; charset=utf-8');
echo "<pre>";

echo "=== 🧠 DÉBUT DU SCAN DES VIDÉOS ===\n\n";

// 🔧 CONFIG
$videoRoot = '/volume1/video';
$thumbDir  = __DIR__ . '/thumbs/videos';

$ffprobe = '/var/packages/ffmpeg/target/bin/ffprobe';
$ffmpeg  = '/var/packages/ffmpeg/target/bin/ffmpeg';

$allowedExt = ['mp4', 'mkv', 'avi', 'mov', 'webm'];

if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0775, true);
}

// 🔎 Vidéos déjà en base
$stmt = $pdo->query("SELECT chemin FROM videos");
$existing = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

// 📊 Stats
$added = 0;
$skipped = 0;
$ignored = [];

// 🔁 Scan récursif
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($videoRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($it as $file) {

    $fullPath = $file->getPathname();

    // 🚫 Ignorer dossiers système Synology
    if (strpos($fullPath, '/@eaDir/') !== false || strpos($fullPath, '@SynoEAStream') !== false) {
        continue;
    }

    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

    // Extension non supportée → silencieux
    if (!in_array($ext, $allowedExt)) {
        continue;
    }

    // Lisibilité
    if (!is_readable($fullPath)) {
        $ignored[] = [
            'file' => $fullPath,
            'reason' => 'Fichier non lisible (permissions)'
        ];
        continue;
    }

    // Déjà en base → ignoré silencieusement
    if (isset($existing[$fullPath])) {
        $skipped++;
        continue;
    }

    // 🎬 Métadonnées
    $titre = pathinfo($fullPath, PATHINFO_FILENAME);
    $mime  = mime_content_type($fullPath);
    $size  = filesize($fullPath);

    // 📅 Date (modification)
    $date = date('Y-m-d', filemtime($fullPath));

    // ⏱️ Durée réelle (ffprobe)
    $duree = null;
    if (file_exists($ffprobe)) {
        $cmd = "$ffprobe -v error -show_entries format=duration -of csv=p=0 " . escapeshellarg($fullPath);
        $out = shell_exec($cmd);
        if ($out !== null && is_numeric(trim($out))) {
            $duree = (int) round($out);
        }
    }

    // Fallback durée (approximation taille)
    if (!$duree && $size > 0) {
        $bitrate = 5_000_000; // 5 Mbps
        $duree = (int) round(($size * 8) / $bitrate);
    }

    // 🖼️ Visuel (thumbnail)
    $thumbFile = md5($fullPath) . '.jpg';
    $thumbPath = "$thumbDir/$thumbFile";
    $visuel = null;

    if (file_exists($ffmpeg)) {
        $cmd = "$ffmpeg -y -ss 5 -i " . escapeshellarg($fullPath) .
               " -frames:v 1 -q:v 2 " . escapeshellarg($thumbPath) . " 2>/dev/null";
        shell_exec($cmd);

        if (file_exists($thumbPath)) {
            $visuel = 'thumbs/videos/' . $thumbFile;
        }
    }

    // 💾 INSERT
    $stmt = $pdo->prepare("
        INSERT INTO videos
        (titre, chemin, type, taille, date, duree, visuel)
        VALUES
        (:titre, :chemin, :type, :taille, :date, :duree, :visuel)
    ");

    $stmt->execute([
        'titre'  => $titre,
        'chemin' => $fullPath,
        'type'   => substr($mime, 0, 100),
        'taille' => $size,
        'date'   => $date,
        'duree'  => $duree,
        'visuel' => $visuel
    ]);

    echo "➕ Vidéo ajoutée : $titre ($date, $mime, durée : {$duree}s)\n";
    $added++;
}

// 📋 Résumé
echo "\n=== ✅ SCAN TERMINÉ ===\n";
echo "Nouvelles : $added | Déjà en base : $skipped | Ignorées : " . count($ignored) . "\n";

// ⚠️ Détails ignorés
if (!empty($ignored)) {
    echo "\n=== ⚠️ FICHIERS IGNORÉS ===\n";
    foreach ($ignored as $i) {
        echo "⛔ {$i['file']} → {$i['reason']}\n";
    }
}

echo "</pre>";
