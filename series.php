<?php
session_start();
require_once __DIR__ . '/functions.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: series.php');
    exit;
}

// Charger la série
$stmt = $pdo->prepare('SELECT * FROM series WHERE id = ?');
$stmt->execute([$id]);
$serie = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$serie) {
    echo 'Série non trouvée';
    exit;
}

// Série précédente / suivante
$prevStmt = $pdo->prepare('SELECT id, titre FROM series WHERE id < ? ORDER BY id DESC LIMIT 1');
$prevStmt->execute([$id]);
$prevSerie = $prevStmt->fetch();

$nextStmt = $pdo->prepare('SELECT id, titre FROM series WHERE id > ? ORDER BY id ASC LIMIT 1');
$nextStmt->execute([$id]);
$nextSerie = $nextStmt->fetch();

// Charger les épisodes
$episodesStmt = $pdo->prepare('SELECT * FROM episodes WHERE serie_id = ? ORDER BY saison ASC, numero_episode ASC');
$episodesStmt->execute([$id]);
$episodes = $episodesStmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les dernières lectures pour tous les épisodes de cette série
$lastViews = [];
if (is_logged_in()) {
    $lastViewsStmt = $pdo->prepare('
        SELECT 
            hl.episode_id,
            hl.date_lecture,
            COUNT(*) as view_count
        FROM historique_lectures hl
        JOIN episodes e ON hl.episode_id = e.id
        WHERE hl.utilisateur_id = ? AND e.serie_id = ?
        GROUP BY hl.episode_id
        HAVING hl.date_lecture = MAX(hl.date_lecture)
    ');
    $lastViewsStmt->execute([current_user_id(), $id]);

    foreach ($lastViewsStmt->fetchAll() as $view) {
        $lastViews[$view['episode_id']] = [
            'date' => $view['date_lecture'],
            'count' => $view['view_count']
        ];
    }
}

// Regroupement par saison
$saisons = [];
foreach ($episodes as $ep) {
    $saisons[$ep['saison']][] = $ep;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// MAJ du commentaire
$commentsStmt = $pdo->prepare('
    SELECT c.*, u.username 
    FROM commentaires c
    JOIN utilisateurs u ON c.utilisateur_id = u.id
    WHERE c.film_id = ? AND c.type = ?
    ORDER BY c.date_creation DESC
');
$commentsStmt->execute([$id, 'serie']);
$comments = $commentsStmt->fetchAll();

//  utilisateur
$userNote = null;
if (is_logged_in()) {
    $userNote = get_user_note($pdo, current_user_id(), $id, 'serie');
}

// Extraire le nom du fichier depuis le chemin
$searchPrefill = '';
if (!empty($serie['chemin'])) {
    $searchPrefill = pathinfo($serie['chemin'], PATHINFO_FILENAME);
}
?>

<!doctype html>

<html lang="fr">

<head>
    <meta charset="utf-8">
    <title><?= e($serie['titre']) ?> — Mes Séries</title>
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<!-- Scripts JavaScript -->
<script src="assets/js/app_series.js"></script>
<script src="assets/js/episode_search.js"></script>

<?php
$activeMenu = 'series';
$showScan = true;
$scanId = 'scan-series';
$scanTitle = 'Scanner les nouvelles séries';
$scanLabel = 'Scan';

require 'header.php';
?>
<script src="assets/js/app.js"></script>

<body class="bg-gray-100 min-h-screen">

    <main class="container mx-auto p-4">
        <div class="flex flex-col md:flex-row gap-6">

            <!-- GAUCHE -->
            <aside class="w-full md:w-1/3">
                <img src="<?= e($serie['affiche'] ?: 'assets/img/no-poster.jpg') ?>" class="rounded shadow mb-3 w-full">

                <!-- Informations saisons/épisodes -->
                <div class="bg-gray-100 rounded p-3 mb-3">
                    <div class="flex items-center gap-2 text-sm">
                        <?php
                        // Compter le nombre total d'épisodes
                        // Total épisodes (inclut les pilotes, c’est logique)
                        $totalEpisodes = count($episodes);

                        // Nombre de saisons réelles (exclut saison 0)
                        $nbSaisons = count(array_filter(
                            array_keys($saisons),
                            fn($s) => (int)$s !== 0
                        ));
                        ?>

                        <span class="font-semibold text-gray-700">
                            📺 <span id="serie-nb-saisons"><?= $nbSaisons ?></span> saison<?= $nbSaisons > 1 ? 's' : '' ?>
                        </span>
                        <span class="text-gray-400">•</span>
                        <span class="font-semibold text-gray-700">
                            🎬 <span id="serie-nb-episodes"><?= $totalEpisodes ?></span> épisode<?= $totalEpisodes > 1 ? 's' : '' ?>
                        </span>
                    </div>
                </div>

                <p id="serie-description" class="mt-3 text-sm"><?= nl2br(e($serie['description'])) ?></p>

                <?php if (is_admin() && is_logged_in()): ?>
                    <button id="delete-serie-btn"
                        class="mt-4 w-full p-2 bg-red-600 text-white rounded hover:bg-red-700">
                        🗑️ Supprimer cette série
                    </button>
                <?php endif; ?>
            </aside>

            <!-- DROITE - Section des épisodes modifiée -->
            <section class="flex-1">
                <h2 id="serie-titre" class="text-2xl font-bold"><?= e($serie['titre']) ?></h2>
                <p id="serie-annee" class="text-sm text-gray-600"><?= e($serie['annee']) ?>
                    <span id="serie-genre"><?= e($serie['genre']) ?></span>
                </p>

                <!-- Navigation série précédente/suivante -->
                <div class="flex gap-2 mb-4">
                    <?php if ($prevSerie): ?>
                        <a href="series.php?id=<?= $prevSerie['id'] ?>" class="p-2 bg-gray-600 text-white rounded">⏮️ <?= e($prevSerie['titre']) ?></a>
                    <?php endif; ?>
                    <?php if ($nextSerie): ?>
                        <a href="series.php?id=<?= $nextSerie['id'] ?>" class="p-2 bg-blue-600 text-white rounded">▶️ <?= e($nextSerie['titre']) ?></a>
                    <?php endif; ?>
                </div>

                <!-- 🔍 Recherche TMDb simplifiée -->
                <div class="my-4">
                    <h3 class="font-semibold">Mettre à jour depuis un serie en ligne</h3>

                    <div class="mb-2">
                        <label class="mr-2"><input type="radio" name="api" value="TMDb" checked> TMDb</label>
                    </div>

                    <input type="text" id="serie-search" value="<?= e($searchPrefill) ?>"
                        placeholder="Rechercher un serie..." class="w-full p-2 border rounded">
                    <ul id="search-results" class="border rounded mt-1 bg-white max-h-40 overflow-y-auto hidden"></ul>
                </div>

                <h3 class="text-xl font-semibold mb-3">Saisons</h3>

                <!-- Episodes par saison -->
                <?php foreach ($saisons as $s => $eps): ?>
                    <div class="mb-4 bg-white rounded shadow">
                        <button
                            type="button"
                            onclick="updateAllEpisodesInSeason(<?= $s ?>)"
                            class="mb-2 px-3 py-1 bg-purple-600 text-white rounded hover:bg-purple-700 text-sm">
                            🔄 Mettre à jour <?= $s == 0 ? 'Pilotes et Hors Saisons' : 'la saison ' . (int)$s ?>
                        </button>
                        <button type="button" class="w-full p-3 font-semibold bg-gray-200 flex justify-between items-center"
                            onclick="this.nextElementSibling.classList.toggle('hidden')">
                            <span>
                                <?= $s == 0
                                    ? 'Pilotes et Hors Saisons'
                                    : 'Saison ' . (int)$s
                                ?>
                                (<?= count($eps) ?> épisodes)
                            </span>
                            <svg class="w-5 h-5 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div class="hidden">
                            <?php foreach ($eps as $ep): ?>
                                <?php
                                $exists = (bool)$ep['fichier_existe'];
                                $type = $ep['video_type'] ?? '';
                                $token  = generate_episode_video_token(current_user_id(), $ep['id']);
                                ?>


                                <div class="border-t p-3 hover:bg-gray-50" data-episode-id="<?= $ep['id'] ?>">
                                    <div class="flex gap-4">

                                        <!-- CONTENU (PREND TOUT L'ESPACE) -->
                                        <div class="flex-1 min-w-0">
                                            <div
                                                class="font-medium episode-titre break-words"
                                                contenteditable="true"
                                                spellcheck="false">
                                                <?= e(cleanEpisodeTitle($ep)) ?>
                                            </div>

                                            <div
                                                class="text-sm text-gray-600 mt-1 episode-description break-words">
                                                <?= e($ep['description_episode'] ?: 'Aucune description') ?>
                                            </div>


                                            <?php if ($type): ?>
                                                <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-200 text-gray-700 uppercase">
                                                    <?= e($type) ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if (isset($lastViews[$ep['id']])): ?>
                                                <span class="text-xs text-green-600">
                                                    📅 Vu le <?= date('d/m/Y à H:i', strtotime($lastViews[$ep['id']]['date'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- ACTIONS (COLONNE FIXE) -->
                                        <div class="flex flex-col gap-2 text-sm whitespace-nowrap">
                                            <?php if ($exists): ?>

                                                <?php if ($type === 'mp4'): ?>
                                                    <a href="stream_episode_mp4.php?id=<?= $ep['id'] ?>" target="_blank"
                                                        class="text-blue-600 hover:underline">
                                                        ▶️ Regarder
                                                    </a>
                                                <?php else: ?>
                                                    <a href="stream_episode.php?token=<?= $token ?>" target="_blank"
                                                        class="text-blue-600 hover:underline">
                                                        ▶️ Regarder
                                                    </a>
                                                <?php endif; ?>

                                                <a href="download_episode.php?id=<?= $ep['id'] ?>"
                                                    class="text-green-600 hover:underline">
                                                    ⬇️ Télécharger
                                                </a>

                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Fichier absent</span>
                                            <?php endif; ?>
                                            <button
                                                type="button"
                                                class="btn-search-episode px-2 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 flex-shrink-0"
                                                data-episode-id="<?= $ep['id'] ?>"
                                                data-saison="<?= $s ?>"
                                                data-numero="<?= $ep['numero_episode'] ?>"
                                                title="Mettre à jour depuis TMDb">
                                                🔍 MAJ
                                            </button>
                                            <?php if (is_admin()): ?>
                                                <button
                                                    type="button"
                                                    class="btn-edit-episode px-2 py-1 bg-yellow-500 text-white text-xs rounded hover:bg-yellow-600"
                                                    data-episode-id="<?= $ep['id'] ?>">
                                                    ✏️ Éditer
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Lien vers Bande Annonce dans youtube -->
                <?php
                $youtubeQuery = urlencode($serie['titre'] . ' trailer');
                $trailerUrl = "https://www.youtube.com/results?search_query={$youtubeQuery}";
                ?>

                <a href="<?= $trailerUrl ?>"
                    target="_blank"
                    class="inline-block mt-2 p-2 bg-green-600 text-white rounded hover:bg-red-700 transition">
                    🎬 Voir la bande-annonce sur YouTube
                </a>

                <!-- Lien vers fiche serie sur AlloCiné via Google -->
                <?php
                // Prépare la requête Google pour rechercher le serie sur AlloCiné
                $googleQuery = urlencode($serie['titre'] . ' site:allocine.fr');
                $allocineUrl = "https://www.google.com/search?q={$googleQuery}";
                ?>

                <a href="<?= $allocineUrl ?>"
                    target="_blank"
                    class="inline-block mt-2 p-2 bg-green-600 text-white rounded hover:bg-red-700 transition">
                    ℹ️ Voir plus d'infos sur AlloCiné
                </a>

                <!-- Notes -->
                <h3 class="font-semibold">Notes</h3>
                <?php $avg = get_average_note($pdo, (int)$serie['id'], "serie"); ?>
                <div>
                    <strong>Moyenne :</strong>
                    <?= $avg !== null ? $avg . ' / 10' : '—' ?>
                </div>
                <?php if (is_logged_in()): ?>
                    <form method="post" action="rate_serie.php" class="mt-2">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="serie_id" value="<?= $id ?>">
                        <input type="hidden" name="type" value="serie">
                        <select name="note" required>
                            <option value="">--</option>
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?= $i ?>" <?= ($userNote == $i ? 'selected' : '') ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <button class="ml-2 px-2 py-1 bg-blue-600 text-white rounded">Enregistrer</button>
                    </form>
                <?php endif; ?>

                <hr class="my-4">

                <!-- Commentaires -->
                <h3 class="font-semibold">Commentaires</h3>
                <?php if (is_logged_in()): ?>
                    <form method="post" action="add_comment.php">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="film_id" value="<?= $id ?>">
                        <input type="hidden" name="type" value="serie">
                        <textarea name="contenu" rows="3" class="w-full border rounded"></textarea>
                        <button class="mt-2 p-2 bg-blue-600 text-white rounded">Envoyer</button>
                    </form>
                <?php endif; ?>

                <?php foreach ($comments as $c): ?>
                    <div class="bg-white p-2 mt-2 rounded shadow">
                        <strong><?= e($c['username']) ?></strong> — <?= e($c['date_creation']) ?><br>
                        <?= nl2br(e($c['contenu'])) ?>
                    </div>
                <?php endforeach; ?>

            </section>
        </div>
    </main>

    <!--  Modale de confirmation de suppression -->
    <div id="delete-serie-modal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">

        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">⚠️ Confirmer la suppression</h3>

            <p class="mb-4">
                Cette action supprimera :
            <ul class="list-disc ml-5 text-sm mt-2">
                <li>la série</li>
                <li>toutes les saisons</li>
                <li>tous les épisodes</li>
                <li>les commentaires et notes</li>
            </ul>
            </p>

            <form method="post" action="delete_serie.php">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="serie_id" value="<?= (int)$serie['id'] ?>">

                <label class="flex items-center mb-4">
                    <input type="checkbox" name="delete_physical" value="1" class="mr-2">
                    <span>Supprimer aussi les fichiers et le dossier de la série</span>
                </label>

                <div class="flex gap-2">
                    <button type="button" id="cancel-delete-serie"
                        class="flex-1 p-2 bg-gray-300 rounded hover:bg-gray-400">
                        Annuler
                    </button>

                    <button type="submit"
                        class="flex-1 p-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Variables JavaScript -->
    <script>
        const sérieId = <?= (int)$serie['id'] ?>;
        const csrfToken = '<?= e($_SESSION['csrf_token']) ?>';

        // ID TMDb de la série - essayer de le récupérer depuis la base de données
        let tmdbSerieId = <?= isset($serie['tmdb_id']) ? (int)$serie['tmdb_id'] : 0 ?>;

        // Si tmdb_id n'existe pas dans la table, le définir globalement
        if (typeof window.tmdbSerieId === 'undefined') {
            window.tmdbSerieId = tmdbSerieId;
        }

        console.log("🎬 ID Série:", sérieId, "| ID TMDb:", tmdbSerieId || window.tmdbSerieId || "Non défini");

        // Suppression de la série
        const deleteSerieBtn = document.getElementById('delete-serie-btn');
        const deleteSerieModal = document.getElementById('delete-serie-modal');
        const cancelDeleteSerie = document.getElementById('cancel-delete-serie');

        if (deleteSerieBtn && deleteSerieModal) {
            deleteSerieBtn.addEventListener('click', () => {
                deleteSerieModal.classList.remove('hidden');
            });

            cancelDeleteSerie.addEventListener('click', () => {
                deleteSerieModal.classList.add('hidden');
            });

            deleteSerieModal.addEventListener('click', (e) => {
                if (e.target === deleteSerieModal) {
                    deleteSerieModal.classList.add('hidden');
                }
            });
        }

        // 🆕 Gestion du bouton de scan des séries
        const scanSeriesBtn = document.getElementById('scan-series');
        if (scanSeriesBtn) {
            scanSeriesBtn.addEventListener('click', async () => {
                if (!confirm('Lancer le scan des séries ? Cela peut prendre plusieurs minutes.')) {
                    return;
                }

                // Créer la modal de résultats
                const modal = document.createElement('div');
                modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4';
                modal.innerHTML = `
                    <div class="bg-white rounded-lg shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col">
                        <div class="p-4 border-b flex justify-between items-center bg-gray-50 rounded-t-lg">
                            <h3 class="text-xl font-bold">📺 Scan des séries en cours...</h3>
                            <button id="close-scan-modal" class="text-gray-500 hover:text-gray-700 text-2xl font-bold">&times;</button>
                        </div>
                        <div class="p-6 overflow-y-auto flex-1">
                            <pre id="scan-output" class="bg-gray-900 text-green-400 p-4 rounded font-mono text-sm whitespace-pre-wrap min-h-[400px]">⏳ Initialisation du scan...</pre>
                        </div>
                        <div class="p-4 border-t bg-gray-50 rounded-b-lg">
                            <button id="reload-page-btn" class="w-full p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition disabled:bg-gray-400" disabled>
                                🔄 Recharger la page
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);

                const output = document.getElementById('scan-output');
                const closeBtn = document.getElementById('close-scan-modal');
                const reloadBtn = document.getElementById('reload-page-btn');

                // Fermer la modal
                closeBtn.addEventListener('click', () => {
                    modal.remove();
                });

                // Recharger la page
                reloadBtn.addEventListener('click', () => {
                    location.reload();
                });

                try {
                    const response = await fetch('scan_series.php');

                    if (!response.ok) {
                        throw new Error(`Erreur HTTP ${response.status}`);
                    }

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let fullText = '';

                    // Lire le flux progressivement
                    while (true) {
                        const {
                            done,
                            value
                        } = await reader.read();
                        if (done) break;

                        const chunk = decoder.decode(value, {
                            stream: true
                        });
                        fullText += chunk;
                        output.textContent = fullText;

                        // Auto-scroll vers le bas
                        output.scrollTop = output.scrollHeight;
                    }

                    // Activer le bouton de rechargement
                    reloadBtn.disabled = false;
                    reloadBtn.textContent = '✅ Scan terminé - Recharger la page';

                } catch (error) {
                    output.textContent = `❌ Erreur lors du scan :\n${error.message}`;
                    console.error('Erreur scan:', error);
                }
            });
        }
    </script>
</body>

</html>