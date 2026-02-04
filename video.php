<?php
require_once __DIR__ . '/functions.php';

// 🔹 Récupération ID vidéo
$videoId = intval($_GET['id'] ?? 0);
if ($videoId <= 0) {
    header('Location: index_videos.php');
    exit;
}

// 🔹 Charger la vidéo
$stmt = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
$stmt->execute([$videoId]);
$video = $stmt->fetch();
if (!$video) {
    exit('⚠️ Vidéo introuvable');
}

// 🔹 Gestion du visuel
$thumbDir = __DIR__ . '/thumbs/videos';
$visuel = 'assets/img/no-poster.jpg';
if (!empty($video['visuel']) && file_exists(__DIR__ . '/' . $video['visuel'])) {
    $visuel = $video['visuel'];
}

// 🔹 Regénération du visuel via ffmpeg si absent
$ffmpeg = '/usr/local/bin/ffmpeg'; // adapter selon ton Synology
if (file_exists($ffmpeg) && is_writable($thumbDir)) {
    $thumbFile = md5($video['chemin']) . '.jpg';
    $thumbPath = "$thumbDir/$thumbFile";

    if (!file_exists($thumbPath) || filesize($thumbPath) === 0) {
        $cmd = "$ffmpeg -y -loglevel error -ss 3 -i " . escapeshellarg($video['chemin']) .
            " -frames:v 1 -vf scale=320:-1 -q:v 3 " . escapeshellarg($thumbPath);
        shell_exec($cmd);

        if (file_exists($thumbPath) && filesize($thumbPath) > 0) {
            $visuel = 'thumbs/videos/' . $thumbFile;

            $upd = $pdo->prepare('UPDATE videos SET visuel = ? WHERE id = ?');
            $upd->execute([$visuel, $videoId]);
        }
    }
}

// 🔹 Formater la durée
$durationStr = '';
if (!empty($video['duree'])) {
    $h = floor($video['duree'] / 3600);
    $m = floor(($video['duree'] % 3600) / 60);
    $s = $video['duree'] % 60;
    $durationStr = ($h ? $h . 'h ' : '') . ($m ? $m . 'm ' : '') . $s . 's';
}

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <title><?= e($video['titre']) ?> — Mes Vidéos</title>
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="min-h-screen bg-gray-100">

    <header class="p-3 bg-white shadow sticky top-0 z-10">
        <div class="container mx-auto flex items-center justify-between">
            <h1 class="text-xl font-bold">
                <a href="index_videos.php">🎬 Mes Vidéos</a>
            </h1>
            <div class="flex items-center gap-3 text-sm">
                <?php if (is_logged_in()): ?>
                    Bonjour <strong><?= e($_SESSION['username']) ?></strong>
                    <span class="text-gray-400">|</span>
                    <a href="logout.php" class="text-blue-600 hover:underline">Se déconnecter</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto p-3">
        <div class="bg-white rounded shadow p-4 flex flex-col md:flex-row gap-4">

            <!-- Colonne gauche : visuel + vidéo -->
            <div class="md:w-1/3 flex-shrink-0">
                <img src="<?= e($visuel) ?>" alt="<?= e($video['titre']) ?>" class="w-full rounded mb-2">

                <?php if (!empty($video['chemin']) && file_exists($video['chemin'])): ?>
                    <?php $mime = mime_content_type($video['chemin']); ?>
                    <video controls class="w-full rounded mb-2">
                        <source src="stream_mp4.php?token=<?= $video['chemin'] ?>" type="<?= e($mime) ?>">
                        Votre navigateur ne supporte pas la lecture vidéo.
                    </video>
                <?php endif; ?>
            </div>

            <!-- Ligne avec Télécharger + Copier l'URL côte à côte -->
            <div class="flex gap-2 mt-2">
                <a href="download.php?token=<?= $video['chemin'] ?>"
                    class="flex-1 p-2 bg-gray-700 text-white rounded text-center hover:bg-gray-800">
                    Télécharger
                </a>

                <button id="copy-stream-url"
                    class="flex-1 p-2 bg-green-600 text-white rounded text-center hover:bg-green-700">
                    Copier l'URL du streaming
                </button>
            </div>

            <!-- Bouton Supprimer en dessous, pleine largeur -->
            <button id="delete-film-btn"
                class="mt-4 w-full p-2 bg-red-600 text-white rounded hover:bg-red-700">
                🗑️ Supprimer ce film
            </button>

            <!-- Colonne droite : infos essentielles -->
            <div class="md:w-2/3">
                <h2 class="text-2xl font-bold mb-2"><?= e($video['titre']) ?></h2>
                <div class="text-gray-600 mb-2">
                    <strong>Date :</strong> <?= e($video['date']) ?><br>
                    <strong>Format :</strong> <?= e($video['type']) ?><br>
                    <?php if ($durationStr): ?>
                        <strong>Durée :</strong> <?= e($durationStr) ?>
                    <?php endif; ?>
                </div>
                <div class="text-gray-800">
                    <?= nl2br(e($video['description'])) ?>
                </div>
            </div>

        </div>
    </main>

</body>

</html>