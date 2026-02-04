<?php
session_start();
require_once __DIR__ . '/functions.php';

if (!is_logged_in() || !is_admin() || (int)$_SESSION['admin_level'] !== 2) {
    header('Location: index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: videos.php');
    exit;
}

/* ==========================
   Charger la vidéo
========================== */
$stmt = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video) {
    echo 'Vidéo non trouvée';
    exit;
}

/* ==========================
   Vidéo précédente / suivante
========================== */
$prevStmt = $pdo->prepare('SELECT id, titre FROM videos WHERE id < ? ORDER BY id DESC LIMIT 1');
$prevStmt->execute([$id]);
$prevVideo = $prevStmt->fetch();

$nextStmt = $pdo->prepare('SELECT id, titre FROM videos WHERE id > ? ORDER BY id ASC LIMIT 1');
$nextStmt->execute([$id]);
$nextVideo = $nextStmt->fetch();

/* ==========================
   CSRF
========================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ==========================
   Commentaires
========================== */
$commentsStmt = $pdo->prepare('
    SELECT c.*, u.username 
    FROM commentaires c
    JOIN utilisateurs u ON u.id = c.utilisateur_id
    WHERE c.video_id = ? AND c.type = "video"
    ORDER BY c.date_creation DESC
');
$commentsStmt->execute([$id]);
$comments = $commentsStmt->fetchAll();

/* ==========================
   Note utilisateur
========================== */
$userNote = null;
if (is_logged_in()) {
    $userNote = get_user_note($pdo, current_user_id(), $id, 'video');
}

/* ==========================
   Token vidéo
========================== */
function generate_video_token($userId, $videoId)
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['video_tokens'][$token] = [
        'user_id' => $userId,
        'video_id' => $videoId,
        'expires' => time() + 10800
    ];
    return $token;
}

$videoToken = (is_logged_in() && is_admin())
    ? generate_video_token(current_user_id(), $video['id'])
    : null;

$videoPath = $video['chemin'];
$mime = @mime_content_type($videoPath);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title><?= e($video['titre']) ?> — Vidéo</title>
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<?php
$activeMenu = 'videos';
require 'header.php';
?>

<body class="bg-gray-100">

<main class="container mx-auto p-4">

    <h1 class="text-2xl font-bold mb-3"><?= e($video['titre']) ?></h1>

    <!-- ▶️ PLAYER -->
    <?php if ($videoToken && is_admin()): ?>

        <?php if ($mime === 'video/mp4'): ?>
            <video controls class="w-full rounded mb-3" onended="goToNextVideo()">
                <source src="stream_mp4.php?token=<?= $videoToken ?>" type="video/mp4">
            </video>
        <?php else: ?>
            <video controls class="w-full rounded mb-3">
                <source src="stream.php?token=<?= $videoToken ?>" type="video/mp4">
            </video>
        <?php endif; ?>

        <div class="flex gap-2 mb-4">
            <a href="download.php?token=<?= $videoToken ?>"
               class="flex-1 bg-gray-700 text-white p-2 rounded text-center">
                Télécharger
            </a>
            <button id="copy-url"
                class="flex-1 bg-green-600 text-white p-2 rounded">
                Copier URL
            </button>
        </div>

    <?php else: ?>
        <p class="text-red-600">Connexion administrateur requise pour la lecture.</p>
    <?php endif; ?>

    <!-- 📝 Description -->
    <div class="bg-white p-3 rounded shadow mb-4">
        <?= nl2br(e($video['description'])) ?>
    </div>

    <!-- ⏮️⏭️ Navigation -->
    <div class="flex gap-2 mb-4">
        <?php if ($prevVideo): ?>
            <a href="video.php?id=<?= $prevVideo['id'] ?>" class="bg-gray-600 text-white p-2 rounded">
                ⏮️ <?= e($prevVideo['titre']) ?>
            </a>
        <?php endif; ?>

        <?php if ($nextVideo): ?>
            <a href="video.php?id=<?= $nextVideo['id'] ?>" class="bg-blue-600 text-white p-2 rounded">
                ⏭️ <?= e($nextVideo['titre']) ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- ⭐ Notes -->
    <h3 class="font-semibold">Notes</h3>
    <?php $avg = get_average_note($pdo, $video['id'], 'video'); ?>
    <p>Moyenne : <?= $avg !== null ? $avg . ' / 10' : '—' ?></p>

    <?php if (is_logged_in()): ?>
        <form method="post" action="rate_video.php" class="mt-2">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
            <select name="note">
                <?php for ($i=1;$i<=10;$i++): ?>
                    <option value="<?= $i ?>" <?= $userNote==$i?'selected':'' ?>>
                        <?= $i ?>
                    </option>
                <?php endfor; ?>
            </select>
            <button class="bg-blue-600 text-white px-2 py-1 rounded">OK</button>
        </form>
    <?php endif; ?>

    <!-- 💬 Commentaires -->
    <hr class="my-4">
    <h3 class="font-semibold">Commentaires</h3>

    <?php if (is_logged_in()): ?>
        <form method="post" action="add_comment.php">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
            <textarea name="contenu" class="w-full p-2 border rounded"></textarea>
            <button class="mt-2 bg-blue-600 text-white p-2 rounded">Envoyer</button>
        </form>
    <?php endif; ?>

    <div class="mt-3">
        <?php foreach ($comments as $c): ?>
            <div class="bg-white p-2 rounded shadow mb-2">
                <strong><?= e($c['username']) ?></strong>
                <div><?= nl2br(e($c['contenu'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<script>
function goToNextVideo() {
    <?php if ($nextVideo): ?>
        location.href = 'video.php?id=<?= $nextVideo['id'] ?>';
    <?php endif; ?>
}

document.getElementById('copy-url')?.addEventListener('click', () => {
    navigator.clipboard.writeText(
        location.origin + '/stream.php?token=<?= $videoToken ?>'
    );
    alert('URL copiée');
});
</script>

</body>
</html>
