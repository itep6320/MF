<?php
session_start();
require_once __DIR__ . '/functions.php';

// Vérifier que l'utilisateur est connecté
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$userId = current_user_id();

// Récupérer les films favoris de l'utilisateur
$stmt = $pdo->prepare('
    SELECT f.*, fav.date_ajout,
           (SELECT AVG(note) FROM notes WHERE film_id = f.id) as note_moyenne
    FROM films f
    INNER JOIN favoris fav ON f.id = fav.film_id
    WHERE fav.utilisateur_id = ?
    ORDER BY fav.date_ajout DESC
');
$stmt->execute([$userId]);
$favoris = $stmt->fetchAll();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="assets/img/favicon.ico" type="image/x-icon">
    <title>Mes Favoris — Mes Films</title>
    <link href="assets/css/style.css" rel="stylesheet">
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

<body class="min-h-screen bg-gray-100">

    <main class="container mx-auto p-3">
        <div class="mb-6">
            <p class="text-gray-600">
                Vous avez <?= count($favoris) ?> film<?= count($favoris) > 1 ? 's' : '' ?> dans vos favoris
            </p>
        </div>

        <?php if (empty($favoris)): ?>
            <div class="bg-white rounded shadow p-8 text-center">
                <p class="text-gray-500 text-lg mb-4">Vous n'avez pas encore de films favoris</p>
                <a href="index.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                    Parcourir les films
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <?php foreach ($favoris as $film): ?>
                    <div class="bg-white rounded shadow overflow-hidden hover:shadow-lg transition relative group">
                        <a href="film.php?id=<?= $film['id'] ?>">
                            <img src="<?= e($film['affiche'] ?: 'assets/img/no-poster.jpg') ?>"
                                alt="<?= e($film['titre']) ?>"
                                class="w-full h-auto">
                        </a>

                        <!-- Étoile de favori en overlay -->
                        <button
                            class="absolute top-2 right-2 bg-black bg-opacity-50 text-yellow-400 text-2xl w-10 h-10 rounded-full flex items-center justify-center hover:bg-opacity-70 transition favorite-remove"
                            data-film-id="<?= $film['id'] ?>"
                            title="Retirer des favoris">
                            ★
                        </button>

                        <div class="p-2">
                            <a href="film.php?id=<?= $film['id'] ?>" class="block">
                                <h3 class="font-semibold text-sm truncate"><?= e($film['titre']) ?></h3>
                                <?php if (!empty($film['annee'])): ?>
                                    <p class="text-xs text-gray-600"><?= e($film['annee']) ?></p>
                                <?php endif; ?>
                                <?php if ($film['note_moyenne']): ?>
                                    <p class="text-xs text-yellow-600">
                                        ★ <?= number_format($film['note_moyenne'], 1) ?>/5
                                    </p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    Ajouté le <?= date('d/m/Y', strtotime($film['date_ajout'])) ?>
                                </p>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        const csrfToken = "<?= $_SESSION['csrf_token'] ?>";

        // Gestion du retrait des favoris
        document.querySelectorAll('.favorite-remove').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (!confirm('Retirer ce film de vos favoris ?')) {
                    return;
                }

                const filmId = this.dataset.filmId;

                fetch('toggle_favorite.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `csrf=${encodeURIComponent(csrfToken)}&film_id=${filmId}&action=remove`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Recharger la page pour mettre à jour la liste
                            location.reload();
                        } else {
                            alert('Erreur: ' + (data.error || 'Impossible de retirer le favori'));
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Une erreur est survenue');
                    });
            });
        });
    </script>
</body>

</html>