<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php'; // fichier où est défini $pdo

header('Content-Type: application/json');

// Récupérer les données POST
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$tmdbID = $data['tmdbID'] ?? '';

if ($id <= 0 || !$tmdbID) {
    echo json_encode(['success' => false, 'error' => 'Film ID ou TMDb ID manquant']);
    exit;
}

// 🔑 Token TMDb
$tmdbToken = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmNzU4ODdjMWY0OWM5OWEzYWJlNGZmOGE5YzQ2YzkxOSIsIm5iZiI6MTUwNTExMTAxOC4wMTYsInN1YiI6IjU5YjYyYmU4YzNhMzY4MmIzZDAwZDdjMiIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.b3EecPxuq466bqWrGFNcBnKp9iC1XBf5ZL7qeFMxris';

// 🔹 Appel API TMDb
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.themoviedb.org/3/movie/$tmdbID?language=fr-FR");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $tmdbToken",
    "Content-Type: application/json;charset=utf-8"
]);

$response = curl_exec($ch);
if ($response === false) {
    echo json_encode(['success' => false, 'error' => curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

// 🔹 Vérification de la réponse JSON
$filmData = json_decode($response, true);
if (!$filmData || isset($filmData['status_code'])) {
    echo json_encode(['success' => false, 'error' => 'Film non trouvé sur TMDb']);
    exit;
}

// 🔹 Extraction des données
$titre = $filmData['title'] ?? 'Titre inconnu';

// Gestion de l'année
$anneeRaw = $filmData['release_date'] ?? null;
$annee = ($anneeRaw && preg_match('/^\d{4}/', $anneeRaw)) ? (int)substr($anneeRaw, 0, 4) : null;

// Genres
$genresArray = $filmData['genres'] ?? [];
$genres = !empty($genresArray) ? implode(', ', array_map(fn($g) => $g['name'], $genresArray)) : 'Genre inconnu';

// Description et traduction
$description = $filmData['overview'] ?? 'Aucune description disponible';
$descriptionFR = function_exists('translate_text_google') ? translate_text_google($description, "fr", $traces) : $description;
$descriptionFR = $descriptionFR ?: $description;

// Affiche
$affiche = !empty($filmData['poster_path']) ? "https://image.tmdb.org/t/p/w500{$filmData['poster_path']}" : 'assets/img/no-poster.jpg';

// 🔹 Mise à jour de la base
try {
    $update = $pdo->prepare('UPDATE films SET titre=?, annee=?, genre=?, description=?, affiche=? WHERE id=?');
    $update->execute([
        $titre,
        $annee,
        $genres,
        $descriptionFR,
        $affiche,
        $id
    ]);

    echo json_encode([
        'success' => true,
        'film' => [
            'titre' => $titre,
            'annee' => $annee ?: '',
            'genre' => $genres,
            'description' => $descriptionFR,
            'affiche' => $affiche
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de données : ' . $e->getMessage()]);
}
