<?php
require_once __DIR__ . '/config.php';

// 🔒 Protection : seuls les admins peuvent lancer le scan
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès interdit']);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

// ⚙️ Chargement du chemin depuis .env
$envPath = __DIR__ . '/../.env-mf';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/.env-mf'; 
}
$env = parse_ini_file($envPath);
$rootPath = $env['FILMS_PATH'] ?? '/volume2/Films';

// 🎥 Extensions vidéo autorisées
$videoExtensions = ['mp4','mkv','avi','mov','wmv','m4v'];

// 🔧 Configuration TMDb
$tmdbApiKey = 'f75887c1f49c99a3abe4ff8a9c46c919'; // ⚠️ Remplace par ta clé TMDb
$tmdbBaseUrl = 'https://api.themoviedb.org/3';

// Fonctions utilitaires
function fetchFromTMDb($url): ?array {
    $response = @file_get_contents($url);
    if (!$response) return null;
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

// --- Fonction de mise à jour depuis TMDb ------------------------
function updateFilmFromTMDb(PDO $pdo, int $filmId, string $titre, string $tmdbApiKey, string $tmdbBaseUrl): bool {
    $searchUrl = "$tmdbBaseUrl/search/movie?api_key=" . urlencode($tmdbApiKey) . "&query=" . urlencode($titre) . "&language=fr-FR";
    $data = fetchFromTMDb($searchUrl);
    if (empty($data['results'])) {
        echo "❌ Aucun résultat TMDb pour : $titre\n";
        return false;
    }

    $result = $data['results'][0];
    $tmdbId = $result['id'] ?? null;
    $title = $result['title'] ?? $titre;
    $year = substr($result['release_date'] ?? '', 0, 4);
    $overview = $result['overview'] ?? '';
    $poster = !empty($result['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $result['poster_path'] : null;

    // 🔸 Détails pour obtenir les genres
    $genreStr = '';
    if ($tmdbId) {
        $detailUrl = "$tmdbBaseUrl/movie/$tmdbId?api_key=" . urlencode($tmdbApiKey) . "&language=fr-FR";
        $details = fetchFromTMDb($detailUrl);
        if (!empty($details['genres'])) {
            $genreStr = implode(', ', array_column($details['genres'], 'name'));
        }
    }

    $stmt = $pdo->prepare('UPDATE films SET titre=?, annee=?, description=?, genre=?, affiche=? WHERE id=?');
    $stmt->execute([$title, $year, $overview, $genreStr, $poster, $filmId]);

    echo "🎬 Film mis à jour depuis TMDb : $title ($year)\n";
    return true;
}

// --- Scan des fichiers -----------------------------------------
echo "=== 🧠 DÉBUT DU SCAN DES FILMS ===\n";

$existingStmt = $pdo->query('SELECT chemin, id, titre FROM films');
$existing = [];
foreach ($existingStmt->fetchAll() as $row) {
    $existing[$row['chemin']] = ['id' => $row['id'], 'titre' => $row['titre']];
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath));
$inserted = $skipped = $updated = 0;
$foundFiles = [];

foreach ($rii as $file) {
    if ($file->isDir()) continue;

    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, $videoExtensions)) continue;

    $full = $file->getPathname();
    $foundFiles[] = $full;

    $titre = trim(str_replace(['_', '.', '-', '1080p', '720p'], ' ', pathinfo($file->getFilename(), PATHINFO_FILENAME)));

    // --- Si déjà présent
    if (isset($existing[$full])) {
        $film = $existing[$full];
        if (empty($film['titre']) || strlen(trim($film['titre'])) < 2) {
            echo "🔄 Mise à jour des infos manquantes : $titre\n";
            updateFilmFromTMDb($pdo, $film['id'], $titre, $tmdbApiKey, $tmdbBaseUrl);
            $updated++;
        } else {
            $skipped++;
        }
        continue;
    }

    // --- Nouveau film
    $stmt = $pdo->prepare('INSERT INTO films (titre, chemin) VALUES (?, ?)');
    $stmt->execute([$titre, $full]);
    $filmId = (int)$pdo->lastInsertId();
    echo "➕ Nouveau fichier ajouté : $titre\n";

    updateFilmFromTMDb($pdo, $filmId, $titre, $tmdbApiKey, $tmdbBaseUrl);
    $inserted++;
}

// --- 🔥 Suppression des films dont le fichier n’existe plus -----
echo "\n=== 🧹 VÉRIFICATION DES FICHIERS MANQUANTS ===\n";
$deleted = 0;

foreach ($existing as $path => $film) {
    if (!in_array($path, $foundFiles) && !file_exists($path)) {
        $stmt = $pdo->prepare('DELETE FROM films WHERE id = ?');
        $stmt->execute([$film['id']]);
        echo "🗑️ Film supprimé (fichier introuvable) : {$film['titre']}\n";
        $deleted++;
    }
}

echo "\n=== ✅ SCAN TERMINÉ ===\n";
echo "Nouveaux : $inserted | Mis à jour : $updated | Ignorés : $skipped | Supprimés : $deleted\n";
