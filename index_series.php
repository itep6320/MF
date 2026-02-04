0<?php
    require_once __DIR__ . '/functions.php';

    /* =========================
   Comptage total séries
========================= */
    $countStmt = $pdo->query('SELECT COUNT(*) FROM series');
    $totalSeries = (int)$countStmt->fetchColumn();

    /* =========================
   Admins (notifications)
========================= */
    $adminStmt = $pdo->query('SELECT id, username FROM utilisateurs WHERE admin = 1');
    $adminUsers = $adminStmt->fetchAll();

    /* =========================
   Filtres
========================= */
    $search   = trim($_GET['q'] ?? '');
    $genre    = trim($_GET['genre'] ?? '');
    $year     = (int)($_GET['year'] ?? 0);
    $noUpdate = isset($_GET['no_update']) ? 1 : 0;

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 24;
    $offset  = ($page - 1) * $perPage;

    // 🔹 Décodage des genres (Tagify JSON ou texte simple)
    $genres = [];
    if ($genre !== '') {
        $decoded = json_decode($genre, true);
        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!empty($item['value'])) {
                    $genres[] = trim($item['value']);
                }
            }
        } else {
            $genres = array_map('trim', explode(',', $genre));
        }
    }

    /* =========================
   SQL dynamique
========================= */
    $sql = "
  SELECT 
    s.*,
    COUNT(e.id) AS nb_episodes
  FROM series s
  LEFT JOIN episodes e ON e.serie_id = s.id
  WHERE 1=1
";

    $params = [];

    if ($search !== '') {
        $sql .= " AND s.titre LIKE ?";
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

    if ($year > 0) {
        $sql .= " AND s.annee = ?";
        $params[] = $year;
    }

    $sql .= " GROUP BY s.id";

    /* =========================
   Total filtré
========================= */
    $countSql = "SELECT COUNT(*) FROM ($sql) t";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = max(1, ceil($total / $perPage));

    /* =========================
   Ordre
========================= */
    $order = $noUpdate
        ? 's.date_ajout DESC, s.titre ASC'
        : 's.annee DESC, s.titre ASC';

    $sql .= " ORDER BY $order LIMIT $perPage OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $series = $stmt->fetchAll();
    ?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Mes Séries</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="assets/img/favicon.ico">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
</head>

<script src="assets/js/app_series.js"></script>
<script src="assets/js/app.js"></script>

<?php
$activeMenu = 'series';
$showScan = true;
$scanId = 'scan-series';
$scanTitle = 'Scanner les nouvelles séries';
$scanLabel = 'Scan';

require 'header.php';
?>

<body class="min-h-screen bg-gray-100">

    <!-- ================= MAIN ================= -->
    <main class="container mx-auto p-3">
        <div class="mb-3 flex justify-between items-center">
            <span class="text-gray-500 text-sm">
                (<?= $totalSeries ?> total • <?= $total ?> filtrées)
            </span>

            <button type="button"
                id="toggle-filters"
                class="text-sm text-blue-600 hover:underline flex items-center gap-1">
                <span id="toggle-text">Filtres</span>
                <svg id="toggle-icon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>

        <div id="filters-wrapper" class="transition-all duration-300 overflow-hidden">
            <form method="get"
                class="mb-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 items-end">

                <div>
                    <label class="text-sm text-gray-600">Titre</label>
                    <input name="q" value="<?= e($search) ?>" class="p-2 border rounded w-full">
                </div>

                <div class="flex flex-col">
                    <label class="text-sm text-gray-600 mb-1">Genres</label>
                    <input id="genre" name="genre" value="<?= e($genre) ?>" placeholder="Genres..." class="p-2 border rounded focus:ring focus:ring-blue-200">
                </div>

                <div>
                    <label class="text-sm text-gray-600">Année</label>
                    <input name="year" type="number" value="<?= $year ?: '' ?>" class="p-2 border rounded w-full">
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" name="no_update" value="1" <?= $noUpdate ? 'checked' : '' ?>>
                    <label class="text-sm">Nouveaux en 1er</label>
                </div>

                <div class="flex flex-col gap-2">
                    <button class="p-2 bg-blue-600 text-white rounded">Rechercher</button>
                    <a href="index_series.php"
                        class="p-2 bg-green-600 text-white rounded text-center">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>

        <!-- ================= GRID ================= -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            <?php foreach ($series as $serie): ?>
                <div class="bg-white p-2 rounded shadow hover:shadow-md">
                    <a href="series.php?id=<?= $serie['id'] ?>">
                        <img src="<?= e($serie['affiche'] ?: 'assets/img/no-poster.jpg') ?>"
                            class="w-full h-48 object-cover rounded">
                        <h3 class="mt-2 font-semibold truncate"><?= e($serie['titre']) ?></h3>
                    </a>

                    <div class="text-sm text-gray-600">
                        <?= e($serie['annee']) ?> • <?= e($serie['genre']) ?>
                    </div>

                    <div class="text-sm mt-1">
                        📀 <?= (int)$serie['nb_saisons'] ?> saison(s)<br>
                        🎞️ <?= (int)$serie['nb_episodes'] ?> épisode(s)
                    </div>

                    <?php $avg = get_average_note($pdo, (int)$serie['id'], "serie"); ?>
                    <strong><?= $avg ? e($avg) . ' ★' : '—' ?></strong>
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
                    'genre' => $genre,
                    'year' => $year,
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
    <script>
        const toggleBtn = document.getElementById('toggle-text');
        const wrapper = document.getElementById('filters-wrapper');
        const text = document.getElementById('toggle-text');
        const icon = document.getElementById('toggle-icon');

        let open = false; // ❌ fermé au départ

        toggleBtn.addEventListener('click', () => {
            open = !open;

            if (open) {
                wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
                icon.classList.remove('rotate-180');
            } else {
                wrapper.style.maxHeight = '0px';
                icon.classList.add('rotate-180');
            }
        });

        // Init fermé au chargement
        window.addEventListener('load', () => {
            wrapper.style.maxHeight = '0px';
            icon.classList.add('rotate-180');
        });
    </script>
</body>

</html>