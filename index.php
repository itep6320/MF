<?php
require_once __DIR__ . '/functions.php';

// Compter tous les films
$countStmt = $pdo->query('SELECT COUNT(*) FROM films');
$totalFilms = $countStmt->fetchColumn();

// Lister tous les utilisateurs administatreurs
$adminStmt = $pdo->query('SELECT id, username, email FROM utilisateurs WHERE admin = 1');
$adminUsers = $adminStmt->fetchAll();

// Récupération des filtres
$search   = trim($_GET['q'] ?? '');
$genreRaw = trim($_GET['genre'] ?? '');
$year     = intval($_GET['year'] ?? 0);
$franchise = trim($_GET['franchise'] ?? '');
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
      if (!empty($item['value'])) {
        $genres[] = trim($item['value']);
      }
    }
  } else {
    $genres = array_map('trim', explode(',', $genreRaw));
  }
}

// 🔹 Construction de la requête SQL dynamique
$sql = 'SELECT * FROM films WHERE 1=1';
$params = [];

/* if ($search !== '') {
  $sql .= ' AND (titre LIKE ? OR description LIKE ?)';
  $params[] = "%$search%";
  $params[] = "%$search%";
} */
if ($search !== '') {
  $sql .= ' AND titre LIKE ?';
  $params[] = "%$search%";
}
if ($franchise !== '') {
  $sql .= ' AND franchise LIKE ?';
  $params[] = "%$franchise%";
}
if (!empty($genres)) {
  $likeParts = [];
  foreach ($genres as $g) {
    $likeParts[] = "genre LIKE ?";
    $params[] = "%$g%";
  }
  $sql .= ' AND (' . implode(' OR ', $likeParts) . ')';
}
if ($year > 0) {
  $sql .= ' AND annee = ?';
  $params[] = $year;
}


// 🔹 Total filtré
$countSql = preg_replace('/SELECT \* FROM/', 'SELECT COUNT(*) FROM', $sql, 1);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// ✅ AJOUT — filtre films sans mise à jour
$orderFields = [
    1 => 'date_ajout DESC, titre ASC',
    0 => 'annee DESC, titre ASC'
];

$sql .= ' ORDER BY ' . $orderFields[(int)$noUpdate] . '
          LIMIT ' . (int)$perPage . '
          OFFSET ' . (int)$offset;

// 🔹 Films paginés
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$films = $stmt->fetchAll();

// 🔹 Debug (optionnel)
if (isset($_GET['debug'])) {
  echo '<pre style="background:#fff;padding:10px;border:1px solid #ccc;">';
  echo "SQL:\n" . htmlspecialchars($sql) . "\n\n";
  echo "Params:\n" . htmlspecialchars(print_r($params, true));
  echo '</pre>';
}
?>
<!doctype html>
<html lang="fr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
  <title>Mes Films</title>
  <link href="assets/css/style.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
</head>

<body class="min-h-screen bg-gray-100">
  <header class="p-3 bg-white shadow sticky top-0 z-10">
    <div class="container mx-auto flex items-center justify-between">
      <h1 class="text-xl font-bold">
        🎬 Mes Films
        <span class="text-gray-500 text-sm">
          (<?= intval($totalFilms) ?> total • <?= intval($total) ?> filtrés)
        </span>
      </h1>

      <div class="flex items-center gap-3 text-sm">
        <?php if (is_logged_in()): ?>
          <div class="flex items-center gap-1">
            Bonjour <strong><?= e($_SESSION['username']) ?></strong>
            <?php if (is_admin()): ?>
              <span class="ml-1 px-2 py-0.5 text-xs bg-red-600 text-white rounded-full">admin</span>
            <?php endif; ?>
          </div>
          <span class="text-gray-400">|</span>
          <a href="logout.php" class="text-blue-600 hover:underline">Se déconnecter</a>

          <div id="notif-wrapper" class="inline relative ml-4">
            <button id="notif-btn" class="relative">
              🔔
              <span id="notif-count" class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full text-xs px-1 hidden"></span>
            </button>

            <div id="notif-panel" class="hidden absolute right-0 mt-2 w-80 bg-white border shadow-lg rounded-lg z-50">
              <div class="p-2 border-b font-bold flex justify-between">
                <span>Notifications</span>
                <?php if (is_admin()): ?>
                  <button id="add-note-btn" class="text-blue-600 text-sm">＋ Ajouter</button>
                <?php endif; ?>
              </div>
              <div id="notif-list" class="max-h-64 overflow-y-auto"></div>
            </div>
          </div>
          <button id="scan-films" title="Scanner les nouveaux films"
            class="ml-3 p-2 bg-green-600 text-white rounded flex items-center hover:bg-green-700 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18M3 14h18M3 18h18" />
            </svg>
            Scan
          </button>
        <?php else: ?>
          <a href="login.php" class="text-blue-600 hover:underline">Se connecter</a>
          <span class="text-gray-400">|</span>
          <a href="register.php" class="text-blue-600 hover:underline">S'inscrire</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main class="container mx-auto p-3">
    <form method="get" class="mb-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 items-end">
      <!-- Recherche par titre -->
      <div class="flex flex-col">
        <label class="text-sm text-gray-600 mb-1">Titre</label>
        <input name="q" value="<?= e($search) ?>" placeholder="Recherche titre..." class="p-2 border rounded focus:ring focus:ring-blue-200">
      </div>

      <!-- Genres -->
      <div class="flex flex-col">
        <label class="text-sm text-gray-600 mb-1">Genres</label>
        <input id="genre" name="genre" value="<?= e($genreRaw) ?>" placeholder="Genres..." class="p-2 border rounded focus:ring focus:ring-blue-200">
      </div>

      <!-- Année -->
      <div class="flex flex-col">
        <label class="text-sm text-gray-600 mb-1">Année</label>
        <input name="year" type="number" value="<?= $year ?: '' ?>" placeholder="Année" class="p-2 border rounded focus:ring focus:ring-blue-200">
      </div>

      <!-- Franchise -->
      <div class="flex flex-col">
        <label class="text-sm text-gray-600 mb-1">Franchise</label>
        <input name="franchise" value="<?= e($franchise) ?>" placeholder="Franchise..." class="p-2 border rounded focus:ring focus:ring-blue-200">
      </div>

      <!-- Films sans mise à jour -->
      <div class="flex items-center gap-2">
        <input type="checkbox" name="no_update" value="1" <?= $noUpdate ? 'checked' : '' ?> class="h-4 w-4">
        <label class="text-sm text-gray-700">Nouveaux en 1er</label>
      </div>

      <!-- Boutons -->
      <div class="flex flex-col gap-2">
        <button style="border-top-width: 2px; border-bottom-width: 2px;"
          class="p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
          Rechercher
        </button>

        <a href="index.php"
          style="border-top-width: 2px; border-bottom-width: 2px;"
          class="p-2 bg-green-600 text-white rounded hover:bg-blue-700 transition flex items-center justify-center text-center">
          Réinitialiser
        </a>
      </div>
    </form>

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
      <?php foreach ($films as $film): ?>
        <div class="card bg-white p-2 rounded shadow hover:shadow-md transition">
          <a href="film.php?id=<?= e($film['id']) ?>">
            <img src="<?= e($film['affiche'] ?: 'assets/img/no-poster.jpg') ?>"
              alt="<?= e($film['titre']) ?>"
              class="w-full h-48 object-cover rounded">
            <h3 class="mt-2 font-semibold truncate"><?= e($film['titre']) ?></h3>
          </a>
          <div class="text-sm text-gray-600"><?= e($film['annee']) ?> • <?= e($film['genre']) ?></div>
          <div class="mt-2 flex items-center justify-between">
            <?php $avg = get_average_note($pdo, (int)$film['id']); ?>
            <strong><?= $avg ? e($avg) . ' ★' : '—' ?></strong>

            <?php
            $videoPath = $film['chemin'] ?? '';
            if ($videoPath && file_exists($videoPath)) {
              $mime = @mime_content_type($videoPath);
              if ($mime !== 'video/mp4'):
            ?>
                <span title="Format non MP4" class="text-yellow-500">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 5a7 7 0 110 14 7 7 0 010-14z" />
                  </svg>
                </span>
            <?php
              endif;
            }
            ?>
            <?php if (!empty($film['franchise'])): ?>
              <span
                class="ml-2 text-yellow-500 cursor-help"
                title="<?= e($film['franchise']) ?>">
                ⭐
              </span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="mt-6 flex gap-2 justify-center flex-wrap">
        <?php
        // Construit la base de la query pour conserver tous les filtres
        $baseQuery = [
          'q' => $search,
          'genre' => $genreRaw,
          'year' => $year,
          'franchise' => $franchise
        ];
        if ($noUpdate == 1) {
          $baseQuery['no_update'] = 1;
        }
        ?>

        <?php if ($page > 1): ?>
          <?php $prevQuery = http_build_query(array_merge($baseQuery, ['page' => $page - 1])); ?>
          <a href="?<?= $prevQuery ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">Précédent</a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
          $pageQuery = http_build_query(array_merge($baseQuery, ['page' => $p]));
        ?>
          <a href="?<?= $pageQuery ?>"
            class="px-3 py-1 rounded <?= ($p == $page) ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?>">
            <?= $p ?>
          </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <?php $nextQuery = http_build_query(array_merge($baseQuery, ['page' => $page + 1])); ?>
          <a href="?<?= $nextQuery ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-gray-300">Suivant</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- Fenêtre modale d'ajout de notification -->
  <div id="note-modal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-xl w-96 p-4">
      <h2 class="text-lg font-bold mb-3">Nouvelle note / notification</h2>
      <form method="post" action="add_notification.php" class="space-y-3">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">

        <textarea name="contenu" rows="3" required
          class="w-full border rounded-lg p-2 text-sm focus:ring focus:ring-blue-200"
          placeholder="Texte de la note..."></textarea>

        <div>
          <label class="text-sm text-gray-600 block mb-1">Cible (optionnel)</label>
          <select name="utilisateur_cible_id" class="w-full border rounded-lg p-2 text-sm">
            <option value="">— Tous les utilisateurs —</option>
            <?php foreach ($adminUsers as $u): ?>
              <option value="<?= $u['id'] ?>"><?= e($u['username']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" id="cancel-note"
            class="px-3 py-1 bg-gray-200 rounded-lg hover:bg-gray-300 text-sm">Annuler</button>
          <button type="submit"
            class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">Envoyer</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    window.csrfToken = "<?= e($_SESSION['csrf_token']) ?>";
  </script>
  <script src="assets/js/app.js"></script>
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