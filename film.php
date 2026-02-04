<?php
session_start();
require_once __DIR__ . '/functions.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Charger le film
$stmt = $pdo->prepare('SELECT * FROM films WHERE id = ?');
$stmt->execute([$id]);
$film = $stmt->fetch();
if (!$film) {
    echo 'Film non trouvé';
    exit;
}

// Lister tous les utilisateurs administatreurs
$adminStmt = $pdo->query('SELECT id, username, email FROM utilisateurs WHERE admin = 1');
$adminUsers = $adminStmt->fetchAll();

// Film précédent et suivant
$prevStmt = $pdo->prepare('SELECT id, titre FROM films WHERE id < ? ORDER BY id DESC LIMIT 1');
$prevStmt->execute([$id]);
$prevFilm = $prevStmt->fetch();

$nextStmt = $pdo->prepare('SELECT id, titre FROM films WHERE id > ? ORDER BY id ASC LIMIT 1');
$nextStmt->execute([$id]);
$nextFilm = $nextStmt->fetch();

// CSRF token
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les commentaires
$commentsStmt = $pdo->prepare('
    SELECT c.*, u.username 
    FROM commentaires c 
    JOIN utilisateurs u ON c.utilisateur_id = u.id 
    WHERE c.film_id = ? AND c.type = ?
    ORDER BY c.date_creation DESC
');
$commentsStmt->execute([$id, 'film']);
$comments = $commentsStmt->fetchAll();

// Note utilisateur
$userNote = null;
if (is_logged_in()) {
    $userNote = get_user_note($pdo, current_user_id(), $id);
}

// Vérifier si le film est dans les favoris de l'utilisateur
$isFavorite = false;
if (is_logged_in()) {
    $favStmt = $pdo->prepare('SELECT COUNT(*) FROM favoris WHERE utilisateur_id = ? AND film_id = ?');
    $favStmt->execute([current_user_id(), $id]);
    $isFavorite = $favStmt->fetchColumn() > 0;
}

// Récupérer la dernière lecture de ce film par l'utilisateur
$lastView = null;
if (is_logged_in()) {
    $lastViewStmt = $pdo->prepare('
        SELECT date_lecture 
        FROM historique_lectures 
        WHERE utilisateur_id = ? AND film_id = ? 
        ORDER BY date_lecture DESC 
        LIMIT 1
    ');
    $lastViewStmt->execute([current_user_id(), $id]);
    $lastView = $lastViewStmt->fetch();
}

// Générer un token vidéo
function generate_video_token($userId, $filmId)
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['video_tokens'][$token] = [
        'user_id' => $userId,
        'film_id' => $filmId,
        'expires' => time() + 10800 // 3h
    ];
    return $token;
}

$videoToken = (is_admin() && is_logged_in())
    ? generate_video_token(current_user_id(), $film['id'])
    : null;

// Extraire le nom du fichier depuis le chemin
$searchPrefill = '';
if (!empty($film['chemin'])) {
    $searchPrefill = pathinfo($film['chemin'], PATHINFO_FILENAME);
}

// Récupérer toutes les franchises existantes pour suggestions
$franchisesStmt = $pdo->query('SELECT DISTINCT franchise FROM films WHERE franchise IS NOT NULL AND franchise != "" ORDER BY franchise');
$existingFranchises = $franchisesStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <title><?= e($film['titre']) ?> — Mes Films</title>
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Style pour l'étoile de favori */
        .favorite-star {
            cursor: pointer;
            font-size: 1.5rem;
            transition: all 0.2s ease;
            display: inline-block;
            user-select: none;
        }
        .favorite-star:hover {
            transform: scale(1.2);
        }
        .favorite-star.is-favorite {
            color: #fbbf24; /* Jaune */
        }
        .favorite-star.not-favorite {
            color: #d1d5db; /* Gris */
        }
    </style>
</head>

<?php
$activeMenu = 'films';
$showScan = true;
$scanId = 'scan-films';
$scanTitle = 'Scanner les nouveaux films';
$scanLabel = 'Scan';

require 'header.php';
?>

<script src="assets/js/app.js"></script>

<body class="min-h-screen bg-gray-100" data-film-id="<?= $film['id'] ?>">

    <main class="container mx-auto p-3">
        <div class="flex flex-col md:flex-row gap-4">

            <!-- Colonne gauche -->
            <div class="w-full md:w-1/3">
                <img src="<?= e($film['affiche'] ?: 'assets/img/no-poster.jpg') ?>"
                    alt="<?= e($film['titre']) ?>" class="w-full rounded mb-2">
                <?php if (is_admin() && is_logged_in() && $videoToken):
                    $videoPath = $film['chemin'];
                    $mime = @mime_content_type($videoPath); ?>
                    <?php if ($mime === 'video/mp4'): ?>

                        <!-- ✅ Lecture native rapide -->
                        <video controls class="w-full rounded mb-2" onended="goToNextFilm()">
                            <source src="stream_mp4.php?token=<?= $videoToken ?>" type="video/mp4">
                            Votre navigateur ne supporte pas la lecture vidéo.
                        </video>
                        <p class="text-green-600 text-sm mt-1">
                            Format du fichier : <?= e($mime) ?><br>
                        </p>

                    <?php else: ?>

                        <!-- ✅ Transcodage dynamique uniquement SI nécessaire -->
                        <video controls class="w-full rounded mb-2" preload="none"
                            onplay="console.log('⏳ FFmpeg transcodage activé');">
                            <source src="stream.php?token=<?= $videoToken ?>" type="video/mp4">
                            Votre navigateur ne supporte pas la lecture vidéo.
                        </video>

                        <p class="text-red-600 text-sm mt-1">
                            ⚠️ Format non compatible : <?= e($mime) ?> — Lecture via transcodage à la volée.
                        </p>

                    <?php endif; ?>

                    <!-- Ligne avec Télécharger + Copier l'URL côte à côte -->
                    <div class="flex gap-2 mt-2">
                        <a href="download.php?token=<?= $videoToken ?>"
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

                    <!-- Toast -->
                    <div id="toast"
                        class="fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded shadow-lg opacity-0 transition-opacity duration-300">
                        URL copiée dans le presse-papier !
                    </div>
                <?php endif; ?>

            </div>

            <!-- Colonne droite -->
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                    <h2 class="text-2xl font-bold"><?= e($film['titre']) ?></h2>
                    <?php if (is_logged_in()): ?>
                        <span 
                            id="favorite-star" 
                            class="favorite-star <?= $isFavorite ? 'is-favorite' : 'not-favorite' ?>"
                            title="<?= $isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>"
                            data-film-id="<?= $film['id'] ?>"
                            data-is-favorite="<?= $isFavorite ? '1' : '0' ?>">
                            <?= $isFavorite ? '★' : '☆' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-gray-600"><?= e($film['annee']) ?> •
                    <span id="film-genre"><?= e($film['genre']) ?></span>
                </p>
                <?php if ($lastView): ?>
                    <div class="mt-3 p-2 bg-blue-50 border-l-4 border-blue-500 rounded">
                        <p class="text-sm text-blue-800">
                            <strong>📅 Dernière visualisation :</strong>
                            <?= date('d/m/Y à H:i', strtotime($lastView['date_lecture'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
                <!-- Franchise -->
                <div class="mt-3">
                    <div class="flex items-center gap-2">
                        <strong class="text-sm">Franchise:</strong>
                        <div id="franchise-display" class="flex items-center gap-2">
                            <?php if (!empty($film['franchise'])): ?>
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">
                                    🎬 <?= e($film['franchise']) ?>
                                    <?php if (is_admin()): ?>
                                        <button onclick="removeFranchise()" class="text-purple-600 hover:text-purple-900 ml-1">×</button>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400 text-sm">Aucune franchise</span>
                            <?php endif; ?>
                        </div>
                        <?php if (is_admin()): ?>
                            <button onclick="openFranchiseModal()" class="text-blue-600 hover:text-blue-800 text-sm">
                                ✏️ Modifier
                            </button>
                            <button onclick="searchFranchiseTMDb()" class="text-green-600 hover:text-green-800 text-sm" title="Rechercher dans TMDb">
                                🔍 TMDb
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Navigation entre films -->
                <div class="flex gap-2 mt-3">
                    <?php if ($prevFilm): ?>
                        <a href="film.php?id=<?= e($prevFilm['id']) ?>"
                            class="p-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition">
                            ⏮️ Film précédent : <?= e($prevFilm['titre']) ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($nextFilm): ?>
                        <a href="film.php?id=<?= e($nextFilm['id']) ?>"
                            class="p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                            🎬 Film suivant : <?= e($nextFilm['titre']) ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Description du film -->
                <hr class="my-4">
                <div class="mt-4">
                    <div id="film-description"><?= nl2br(e($film['description'])) ?></div>
                </div>
                <hr class="my-4">

                <!-- Lien vers Bande Annonce dans youtube -->
                <?php
                $youtubeQuery = urlencode($film['titre'] . ' trailer');
                $trailerUrl = "https://www.youtube.com/results?search_query={$youtubeQuery}";
                ?>

                <a href="<?= $trailerUrl ?>"
                    target="_blank"
                    class="inline-block mt-2 p-2 bg-green-600 text-white rounded hover:bg-red-700 transition">
                    🎬 Voir la bande-annonce sur YouTube
                </a>

                <!-- Lien vers fiche film sur AlloCiné via Google -->
                <?php
                // Prépare la requête Google pour rechercher le film sur AlloCiné
                $googleQuery = urlencode($film['titre'] . ' site:allocine.fr');
                $allocineUrl = "https://www.google.com/search?q={$googleQuery}";
                ?>

                <a href="<?= $allocineUrl ?>"
                    target="_blank"
                    class="inline-block mt-2 p-2 bg-green-600 text-white rounded hover:bg-red-700 transition">
                    ℹ️ Voir plus d'infos sur AlloCiné
                </a>

                <!-- 🔍 Recherche en ligne -->
                <div class="my-4">
                    <h3 class="font-semibold">Mettre à jour depuis un film en ligne</h3>

                    <div class="mb-2">
                        <label class="mr-2"><input type="radio" name="api" value="OMDb" checked> OMDb</label>
                        <label class="mr-2"><input type="radio" name="api" value="IMDb"> IMDb</label>
                        <label class="mr-2"><input type="radio" name="api" value="TMDb"> TMDb</label>
                    </div>

                    <input type="text" id="film-search" value="<?= e($searchPrefill) ?>"
                        placeholder="Rechercher un film..." class="w-full p-2 border rounded">
                    <ul id="search-results" class="border rounded mt-1 bg-white max-h-40 overflow-y-auto hidden"></ul>
                </div>

                <hr class="my-4">

                <!-- Notes -->
                <h3 class="font-semibold">Notes</h3>
                <?php $avg = get_average_note($pdo, (int)$film['id'], "film"); ?>
                <div>
                    <strong>Moyenne :</strong>
                    <?= $avg !== null ? $avg . ' / 10' : '—' ?>
                </div>
                <?php if (is_logged_in()): ?>
                    <form method="post" action="rate_film.php" class="inline-block mt-2">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="film_id" value="<?= e($film['id']) ?>">

                        <label>Ma note (sur 10):
                            <select name="note" required>
                                <option value="">--</option>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($userNote == $i) ? 'selected' : '' ?>>
                                        <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </label>

                        <button class="p-1 bg-blue-600 text-white rounded">Enregistrer</button>
                    </form>
                <?php else: ?>
                    <p><a href="login.php">Connectez-vous pour noter</a></p>
                <?php endif; ?>

                <hr class="my-4">

                <!-- Commentaires -->
                <h3 class="font-semibold">Commentaires</h3>
                <?php if (is_logged_in()): ?>
                    <form method="post" action="add_comment.php">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="film_id" value="<?= e($film['id']) ?>">
                        <textarea name="contenu" rows="3" class="w-full p-2 border rounded"
                            placeholder="Ajouter un commentaire..."></textarea>
                        <button class="mt-2 p-2 bg-blue-600 text-white rounded">Envoyer</button>
                    </form>
                <?php else: ?>
                    <p><a href="login.php">Connectez-vous pour commenter</a></p>
                <?php endif; ?>

                <div class="mt-4">
                    <?php foreach ($comments as $c): ?>
                        <div class="p-2 bg-white rounded shadow mb-2">
                            <div class="text-sm text-gray-700 flex justify-between items-start">
                                <div>
                                    <strong><?= e($c['username']) ?></strong> — <em><?= e($c['date_creation']) ?></em>
                                    <?php if (!empty($c['date_modif'])): ?>
                                        <span class="text-xs text-gray-400 ml-1">(modifié)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (is_logged_in() && ($c['utilisateur_id'] == current_user_id() || is_admin())): ?>
                                    <div class="flex items-center">
                                        <button
                                            class="text-blue-600 text-sm p-1 hover:text-blue-800"
                                            onclick="editComment(<?= (int)$c['id'] ?>)">
                                            ✏️
                                        </button>

                                        <form method="post"
                                            action="delete_comment.php"
                                            class="inline-block -ml-1">
                                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="comment_id" value="<?= e($c['id']) ?>">
                                            <input type="hidden" name="film_id" value="<?= e($film['id']) ?>">
                                            <button
                                                type="submit"
                                                class="text-red-600 text-sm p-1 hover:text-red-800">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                            </div>
                            <div class="mt-1" id="comment-text-<?= $c['id'] ?>">
                                <?= nl2br(e($c['contenu'])) ?>
                            </div>

                            <form method="post"
                                action="edit_comment.php"
                                id="comment-form-<?= $c['id'] ?>"
                                class="hidden mt-1">

                                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="comment_id" value="<?= e($c['id']) ?>">
                                <input type="hidden" name="film_id" value="<?= e($film['id']) ?>">

                                <textarea name="contenu" rows="3"
                                    class="w-full p-2 border rounded"><?= e($c['contenu']) ?></textarea>

                                <div class="flex gap-2 mt-1">
                                    <button class="px-2 py-1 bg-blue-600 text-white rounded text-sm">
                                        Enregistrer
                                    </button>
                                    <button type="button"
                                        class="px-2 py-1 bg-gray-300 rounded text-sm"
                                        onclick="cancelEdit(<?= (int)$c['id'] ?>)">
                                        Annuler
                                    </button>
                                </div>
                            </form>

                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal suppression film -->
    <div id="delete-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">⚠️ Confirmer la suppression</h3>
            <p class="mb-4">Êtes-vous sûr de vouloir supprimer ce film ?</p>
            <form method="post" action="delete_film.php">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="film_id" value="<?= e($film['id']) ?>">
                <label class="flex items-center mb-4">
                    <input type="checkbox" name="delete_physical" value="1" class="mr-2">
                    <span>Supprimer également le fichier physique</span>
                </label>
                <div class="flex gap-2">
                    <button type="button" id="cancel-delete"
                        class="flex-1 p-2 bg-gray-300 rounded hover:bg-gray-400">Annuler</button>
                    <button type="submit"
                        class="flex-1 p-2 bg-red-600 text-white rounded hover:bg-red-700">Supprimer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal modification franchise -->
    <div id="franchise-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">🎬 Modifier la franchise</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Nom de la franchise</label>
                <input type="text" id="franchise-input"
                    value="<?= e($film['franchise'] ?? '') ?>"
                    placeholder="Ex: Marvel Cinematic Universe"
                    class="w-full p-2 border rounded focus:ring-2 focus:ring-purple-500"
                    list="franchise-suggestions">
                <datalist id="franchise-suggestions">
                    <?php foreach ($existingFranchises as $franchise): ?>
                        <option value="<?= e($franchise) ?>">
                        <?php endforeach; ?>
                </datalist>
                <p class="text-xs text-gray-500 mt-1">Suggestions basées sur vos franchises existantes</p>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="closeFranchiseModal()"
                    class="flex-1 p-2 bg-gray-300 rounded hover:bg-gray-400">Annuler</button>
                <button type="button" onclick="saveFranchise()"
                    class="flex-1 p-2 bg-purple-600 text-white rounded hover:bg-purple-700">Enregistrer</button>
            </div>
        </div>
    </div>

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
        const filmId = <?= (int)$film['id'] ?>;
        const csrfToken = '<?= e($_SESSION['csrf_token']) ?>';

        function goToNextFilm() {
            <?php if ($nextFilm): ?>
                window.location.href = 'film.php?id=<?= e($nextFilm['id']) ?>';
            <?php else: ?>
                alert("C'était le dernier film !");
            <?php endif; ?>
        }

        // Gestion des commentaires
        function editComment(id) {
            document.getElementById('comment-text-' + id).classList.add('hidden');
            document.getElementById('comment-form-' + id).classList.remove('hidden');
        }

        function cancelEdit(id) {
            document.getElementById('comment-form-' + id).classList.add('hidden');
            document.getElementById('comment-text-' + id).classList.remove('hidden');
        }

        // Gestion de la franchise
        function openFranchiseModal() {
            document.getElementById('franchise-modal').classList.remove('hidden');
            document.getElementById('franchise-input').focus();
        }

        function closeFranchiseModal() {
            document.getElementById('franchise-modal').classList.add('hidden');
        }

        function searchFranchiseTMDb() {
            const titre = '<?= e($film['titre']) ?>';

            fetch(`search_franchise_tmdb.php?titre=${encodeURIComponent(titre)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.franchise) {
                            document.getElementById('franchise-input').value = data.franchise;
                            alert(`Franchise trouvée: ${data.franchise}`);
                            location.reload();
                        } else {
                            alert('Aucune franchise trouvée pour ce film sur TMDb.');
                        }
                    } else {
                        alert('Erreur: ' + (data.error || 'Recherche échouée'));
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de la recherche de la franchise.');
                });
        }

        function saveFranchise() {
            const franchise = document.getElementById('franchise-input').value.trim();

            fetch('update_franchise.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `csrf=${encodeURIComponent(csrfToken)}&film_id=${filmId}&franchise=${encodeURIComponent(franchise)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + (data.error || 'Impossible de mettre à jour la franchise'));
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue');
                });
        }

        function removeFranchise() {
            if (!confirm('Voulez-vous vraiment retirer cette franchise ?')) return;

            fetch('update_franchise.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `csrf=${encodeURIComponent(csrfToken)}&film_id=${filmId}&franchise=`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + (data.error || 'Impossible de supprimer la franchise'));
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue');
                });
        }

        // Fermer la modal avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeFranchiseModal();
            }
        });

        // Fermer en cliquant en dehors
        document.getElementById('franchise-modal').addEventListener('click', (e) => {
            if (e.target.id === 'franchise-modal') {
                closeFranchiseModal();
            }
        });
    </script>
    <script>
        window.csrfToken = "<?= $_SESSION['csrf_token'] ?>";
    </script>
  
    <!-- Empêcher que la vidéo continue à télécharger en quittant la page -->
    <script>
        window.addEventListener("beforeunload", function() {
            const video = document.querySelector("video");
            if (video) {
                video.pause();
                video.src = "";
                video.load();
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Chargement dynamique des scripts d'API (OMDb / IMDb / TMDb)
            const apiRadios = document.querySelectorAll('input[name="api"]');
            let currentApi = document.querySelector('input[name="api"]:checked').value;
            let currentListener = null;

            function loadApiJs(api) {
                const oldScript = document.getElementById('api-js');
                if (oldScript) oldScript.remove();

                // Retire l'ancien listener si présent
                const searchInput = document.getElementById('film-search');
                if (currentListener && searchInput) {
                    searchInput.removeEventListener('input', currentListener);
                    currentListener = null;
                }

                const script = document.createElement('script');
                script.src = `assets/js/${api}.js`;
                script.id = 'api-js';
                script.onload = () => {
                    console.log(`🎬 ${api}.js chargé`);
                    // Chaque script doit exposer une fonction d'init qui retourne le listener (ou l'attache)
                    if (api === 'OMDb' && typeof initOmdbSearch === 'function') currentListener = initOmdbSearch();
                    if (api === 'IMDb' && typeof initIMDbSearch === 'function') currentListener = initIMDbSearch();
                    if (api === 'TMDb' && typeof initTMDbSearch === 'function') currentListener = initTMDbSearch();
                };
                document.body.appendChild(script);
            }

            // Chargement initial
            loadApiJs(currentApi);

            // Changement d'API par radio
            apiRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    currentApi = radio.value;
                    loadApiJs(currentApi);
                });
            });

            // --- Copie de l'URL de streaming et toast
            const copyBtn = document.getElementById('copy-stream-url');
            const toast = document.getElementById('toast');

            if (copyBtn && toast) {
                copyBtn.addEventListener('click', () => {
                    const baseUrl = window.location.origin + '/mf/';
                    const streamUrl = baseUrl + "stream.php?token=<?= $videoToken ?>";

                    navigator.clipboard.writeText(streamUrl).then(() => {
                        toast.classList.remove('opacity-0');
                        toast.classList.add('opacity-100');

                        setTimeout(() => {
                            toast.classList.remove('opacity-100');
                            toast.classList.add('opacity-0');
                        }, 2500);
                    }).catch(err => {
                        console.error('Erreur lors de la copie : ', err);
                        alert('Impossible de copier l\'URL.');
                    });
                });
            }

            // --- Modal de suppression
            const deleteBtn = document.getElementById('delete-film-btn');
            const modal = document.getElementById('delete-modal');
            const cancelBtn = document.getElementById('cancel-delete');

            if (deleteBtn && modal) {
                deleteBtn.addEventListener('click', () => {
                    modal.classList.remove('hidden');
                });

                cancelBtn.addEventListener('click', () => {
                    modal.classList.add('hidden');
                });

                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                    }
                });
            }
            
            // --- Gestion des favoris
            const favoriteStar = document.getElementById('favorite-star');
            if (favoriteStar) {
                favoriteStar.addEventListener('click', function() {
                    const filmId = this.dataset.filmId;
                    const isFavorite = this.dataset.isFavorite === '1';
                    
                    fetch('toggle_favorite.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `csrf=${encodeURIComponent(csrfToken)}&film_id=${filmId}&action=${isFavorite ? 'remove' : 'add'}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Inverser l'état visuel
                            if (isFavorite) {
                                this.textContent = '☆';
                                this.classList.remove('is-favorite');
                                this.classList.add('not-favorite');
                                this.dataset.isFavorite = '0';
                                this.title = 'Ajouter aux favoris';
                            } else {
                                this.textContent = '★';
                                this.classList.remove('not-favorite');
                                this.classList.add('is-favorite');
                                this.dataset.isFavorite = '1';
                                this.title = 'Retirer des favoris';
                            }
                        } else {
                            alert('Erreur: ' + (data.error || 'Impossible de modifier les favoris'));
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Une erreur est survenue');
                    });
                });
            }
        });
    </script>
    <!-- Tracking de visionnage -->
    <script src="assets/js/video_tracking.js"></script>
    <script>
        // Initialiser le tracking pour ce film
        document.addEventListener('DOMContentLoaded', function() {
            initVideoTracking('film', <?= (int)$film['id'] ?>, 0.9);
        });
    </script>
</body>

</html>