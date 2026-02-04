<?php
require_once __DIR__ . '/functions.php';

if (!is_logged_in() || !is_admin() || (int)$_SESSION['admin_level'] !== 2) {
    header('Location: index.php');
    exit;
}

// Compter toutes les vidéos
$countStmt = $pdo->query('SELECT COUNT(*) FROM videos');
$totalVideos = $countStmt->fetchColumn();

// Lister tous les utilisateurs administrateurs
$adminStmt = $pdo->query('SELECT id, username, email FROM utilisateurs WHERE admin = 1');
$adminUsers = $adminStmt->fetchAll();

// Récupération des filtres
$search   = trim($_GET['q'] ?? '');
$genreRaw = trim($_GET['genre'] ?? '');
$date     = trim($_GET['date'] ?? '');
$noUpdate = isset($_GET['no_update']) ? 1 : 0;
$page     = max(1, intval($_GET['page'] ?? 1));
$perPage  = 24;
$offset   = ($page - 1) * $perPage;

// 🔹 Décodage des genres (Tagify JSON ou texte simple)
$genres = [];
if ($genreRaw !== '') {
    $decoded = json_decode($genreRaw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (!empty($item['value'])) $genres[] = trim($item['value']);
        }
    } else {
        $genres = array_map('trim', explode(',', $genreRaw));
    }
}

// 🔹 Construction de la requête SQL dynamique
$sql = 'SELECT * FROM videos WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND titre LIKE ?';
    $params[] = "%$search%";
}

if (!empty($genres)) {
    $likeParts = [];
    foreach ($genres as $g) {
        $likeParts[] = "genre LIKE ?";
        $params[] = "%$g%";
    }
    $sql .= ' AND (' . implode(' OR ', $likeParts) . ')';
}

if ($date !== '') {
    $sql .= ' AND date = ?';
    $params[] = $date;
}

// 🔹 Total filtré
$countSql = preg_replace('/SELECT \* FROM/', 'SELECT COUNT(*) FROM', $sql, 1);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// ✅ AJOUT — filtre vidéos sans mise à jour
$orderFields = [
    1 => 'date_ajout DESC, titre ASC',
    0 => 'date DESC, titre ASC'
];

$sql .= ' ORDER BY ' . $orderFields[(int)$noUpdate] . '
          LIMIT ' . (int)$perPage . '
          OFFSET ' . (int)$offset;

// 🔹 Vidéos paginées
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll();

?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <title>Mes Vidéos</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
</head>

<body class="min-h-screen bg-gray-100">

    <header class="p-3 bg-white shadow sticky top-0 z-10">
        <div class="container mx-auto flex items-center justify-between">
            <h1 class="text-xl font-bold">
                <a href="index_videos.php">🎬 Mes Vidéos</a>
                <span class="text-gray-500 text-sm">(<?= $totalVideos ?> total • <?= $total ?> filtrées)</span>
            </h1>
            <div class="flex items-center gap-3 text-sm">
                <?php if (is_logged_in()): ?>
                    Bonjour <strong><?= e($_SESSION['username']) ?></strong>
                    <?php if (is_admin()): ?>
                        <span class="ml-1 px-2 py-0.5 text-xs bg-red-600 text-white rounded-full">admin</span>
                    <?php endif; ?>
                    <span class="text-gray-400">|</span>
                    <a href="logout.php" class="text-blue-600 hover:underline">Se déconnecter</a>
                <?php else: ?>
                    <a href="login.php" class="text-blue-600 hover:underline">Se connecter</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto p-3">

        <form method="get" class="mb-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 items-end">
            <div class="flex flex-col">
                <label class="text-sm text-gray-600 mb-1">Titre</label>
                <input name="q" value="<?= e($search) ?>" placeholder="Recherche titre..." class="p-2 border rounded focus:ring focus:ring-blue-200">
            </div>
            <div class="flex flex-col">
                <label class="text-sm text-gray-600 mb-1">Genres</label>
                <input id="genre" name="genre" value="<?= e($genreRaw) ?>" placeholder="Genres..." class="p-2 border rounded focus:ring focus:ring-blue-200">
            </div>
            <div class="flex flex-col">
                <label class="text-sm text-gray-600 mb-1">Date</label>
                <input name="date" value="<?= e($date) ?>" placeholder="YYYY-MM-DD" class="p-2 border rounded focus:ring focus:ring-blue-200">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="no_update" value="1" <?= $noUpdate ? 'checked' : '' ?> class="h-4 w-4">
                <label class="text-sm text-gray-700">Nouveaux en 1er</label>
            </div>
            <div class="flex flex-col gap-2">
                <button class="p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">Rechercher</button>
                <a href="index_videos.php" class="p-2 bg-green-600 text-white rounded hover:bg-blue-700 transition flex items-center justify-center text-center">Réinitialiser</a>
            </div>
        </form>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            <?php foreach ($videos as $video): ?>
                <div class="card bg-white p-2 rounded shadow hover:shadow-md transition">
                    <a href="video.php?id=<?= e($video['id']) ?>">
                        <img src="<?= e($video['visuel'] ?: 'assets/img/no-poster.jpg') ?>"
                            alt="<?= e($video['titre']) ?>"
                            class="w-full h-48 object-cover rounded">
                        <h3 class="mt-2 font-semibold truncate"><?= e($video['titre']) ?></h3>
                    </a>
                    <div class="text-sm text-gray-600"><?= e($video['date']) ?> • <?= e($video['genre']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex gap-2 justify-center flex-wrap">
                <?php
                $baseQuery = ['q' => $search, 'genre' => $genreRaw, 'date' => $date];
                if ($noUpdate) $baseQuery['no_update'] = 1;

                for ($p = 1; $p <= $totalPages; $p++):
                    $pageQuery = http_build_query(array_merge($baseQuery, ['page' => $p]));
                ?>
                    <a href="?<?= $pageQuery ?>" class="px-3 py-1 rounded <?= ($p == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    </main>

    <script>
        const input = document.querySelector('#genre');
        const tagify = new Tagify(input, {
            whitelist: ["Action", "Aventure", "Comédie", "Drame", "Fantastique", "Horreur", "Romance", "Thriller", "Science-Fiction", "Animation", "Mystère", "Documentaire"],
            enforceWhitelist: false,
            dropdown: {
                enabled: 1,
                closeOnSelect: false
            }
        });
    </script>
</body>

</html>