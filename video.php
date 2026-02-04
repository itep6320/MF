<?php
session_start();
require_once __DIR__ . '/functions.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Charger le video
$stmt = $pdo->prepare('SELECT * FROM videos WHERE id = ?');
$stmt->execute([$id]);
$video = $stmt->fetch();
if (!$video) {
    echo 'Film non trouvé';
    exit;
}

// Lister tous les utilisateurs administatreurs
$adminStmt = $pdo->query('SELECT id, username, email FROM utilisateurs WHERE admin = 1');
$adminUsers = $adminStmt->fetchAll();

// Film précédent et suivant
$prevStmt = $pdo->prepare('SELECT id, titre FROM videos WHERE id < ? ORDER BY id DESC LIMIT 1');
$prevStmt->execute([$id]);
$prevFilm = $prevStmt->fetch();

$nextStmt = $pdo->prepare('SELECT id, titre FROM videos WHERE id > ? ORDER BY id ASC LIMIT 1');
$nextStmt->execute([$id]);
$nextFilm = $nextStmt->fetch();

// CSRF token
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Récupérer la dernière lecture de ce video par l'utilisateur
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
function generate_video_token($userId, $videoId)
{
    $token = bin2hex(random_bytes(32));
    $_SESSION['video_tokens'][$token] = [
        'user_id' => $userId,
        'video_id' => $videoId,
        'expires' => time() + 10800 // 3h
    ];
    return $token;
}

$videoToken = (is_admin() && is_logged_in())
    ? generate_video_token(current_user_id(), $video['id'])
    : null;

// Extraire le nom du fichier depuis le chemin
$searchPrefill = '';
if (!empty($video['chemin'])) {
    $searchPrefill = pathinfo($video['chemin'], PATHINFO_FILENAME);
}

?>

<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <title><?= e($video['titre']) ?> — Mes Films</title>
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
            color: #fbbf24;
            /* Jaune */
        }

        .favorite-star.not-favorite {
            color: #d1d5db;
            /* Gris */
        }
    </style>
</head>

<?php
$activeMenu = 'videos';
$showScan = true;
$scanId = 'scan-videos';
$scanTitle = 'Scanner les nouveaux videos';
$scanLabel = 'Scan';

require 'header.php';
?>

<script src="assets/js/app.js"></script>

<body class="min-h-screen bg-gray-100" data-video-id="<?= $video['id'] ?>">

    <main class="container mx-auto p-3">
        <div class="flex flex-col md:flex-row gap-4">

            <!-- Colonne gauche -->
            <div class="w-full md:w-1/3">
                <img src="<?= e($video['visuel'] ?: 'assets/img/no-poster.jpg') ?>"
                    alt="<?= e($video['titre']) ?>" class="w-full rounded mb-2">
                <?php if (is_admin() && is_logged_in() && $videoToken):
                    $videoPath = $video['chemin'];
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
                    <button id="delete-video-btn"
                        class="mt-4 w-full p-2 bg-red-600 text-white rounded hover:bg-red-700">
                        🗑️ Supprimer ce video
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
                    <h2 class="text-2xl font-bold"><?= e($video['titre']) ?></h2>
                    <?php if (is_logged_in()): ?>
                        <span
                            id="favorite-star"
                            class="favorite-star <?= $isFavorite ? 'is-favorite' : 'not-favorite' ?>"
                            title="<?= $isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>"
                            data-video-id="<?= $video['id'] ?>"
                            data-is-favorite="<?= $isFavorite ? '1' : '0' ?>">
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-gray-600"><?= e($video['date']) ?> •
                    <span id="video-genre"><?= e($video['genre']) ?></span>
                </p>
                <?php if ($lastView): ?>
                    <div class="mt-3 p-2 bg-blue-50 border-l-4 border-blue-500 rounded">
                        <p class="text-sm text-blue-800">
                            <strong>📅 Dernière visualisation :</strong>
                            <?= date('d/m/Y à H:i', strtotime($lastView['date_lecture'])) ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Navigation entre videos -->
                <div class="flex gap-2 mt-3">
                    <?php if ($prevFilm): ?>
                        <a href="video.php?id=<?= e($prevFilm['id']) ?>"
                            class="p-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition">
                            ⏮️ Film précédent : <?= e(truncate($prevFilm['titre'], 20)) ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($nextFilm): ?>
                        <a href="video.php?id=<?= e($nextFilm['id']) ?>"
                            class="p-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                            🎬 Film suivant : <?= e(truncate($nextFilm['titre'], 20)) ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Description du video -->
                <hr class="my-4">
                <div class="mt-4">
                    <div id="video-description"><?= nl2br(e($video['description'])) ?></div>
                </div>
                <hr class="my-4">
                
            </div>
        </div>
    </main>

    <!-- Modal suppression video -->
    <div id="delete-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">⚠️ Confirmer la suppression</h3>
            <p class="mb-4">Êtes-vous sûr de vouloir supprimer ce video ?</p>
            <form method="post" action="delete_video.php">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="video_id" value="<?= e($video['id']) ?>">
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
                    value="<?= e($video['franchise'] ?? '') ?>"
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
        const videoId = <?= (int)$video['id'] ?>;
        const csrfToken = '<?= e($_SESSION['csrf_token']) ?>';

        function goToNextFilm() {
            <?php if ($nextFilm): ?>
                window.location.href = 'video.php?id=<?= e($nextFilm['id']) ?>';
            <?php else: ?>
                alert("C'était le dernier video !");
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
            const titre = '<?= e($video['titre']) ?>';

            fetch(`search_franchise_tmdb.php?titre=${encodeURIComponent(titre)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.franchise) {
                            document.getElementById('franchise-input').value = data.franchise;
                            alert(`Franchise trouvée: ${data.franchise}`);
                            location.reload();
                        } else {
                            alert('Aucune franchise trouvée pour ce video sur TMDb.');
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
                    body: `csrf=${encodeURIComponent(csrfToken)}&video_id=${videoId}&franchise=${encodeURIComponent(franchise)}`
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
                    body: `csrf=${encodeURIComponent(csrfToken)}&video_id=${videoId}&franchise=`
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
                const searchInput = document.getElementById('video-search');
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
            const deleteBtn = document.getElementById('delete-video-btn');
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
                    const videoId = this.dataset.videoId;

                    fetch('toggle_favorite.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `csrf=${encodeURIComponent(csrfToken)}&video_id=${videoId}&action=${isFavorite ? 'remove' : 'add'}`
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
        // Initialiser le tracking pour ce video
        document.addEventListener('DOMContentLoaded', function() {
            initVideoTracking('video', <?= (int)$video['id'] ?>, 0.9);
        });
    </script>
</body>

</html>