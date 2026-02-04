<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$tmdbID = $data['tmdbID'] ?? '';

if ($id <= 0 || !$tmdbID) {
    echo json_encode(['success' => false, 'error' => 'Film ID ou TMDb ID manquant']);
    exit;
}

$tmdbToken = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmNzU4ODdjMWY0OWM5OWEzYWJlNGZmOGE5YzQ2YzkxOSIsIm5iZiI6MTUwNTExMTAxOC4wMTYsInN1YiI6IjU5YjYyYmU4YzNhMzY4MmIzZDAwZDdjMiIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.b3EecPxuq466bqWrGFNcBnKp9iC1XBf5ZL7qeFMxris';

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

$filmData = json_decode($response, true);
if (!$filmData || isset($filmData['status_code'])) {
    echo json_encode(['success' => false, 'error' => 'Film non trouvé sur TMDb']);
    exit;
}

$titre = $filmData['title'] ?? 'Titre inconnu';

$anneeRaw = $filmData['release_date'] ?? null;
$annee = ($anneeRaw && preg_match('/^\d{4}/', $anneeRaw)) ? (int)substr($anneeRaw, 0, 4) : null;

$genresFR = [
    'Action' => 'Action',
    'Adventure' => 'Aventure',
    'Comedy' => 'Comédie',
    'Drama' => 'Drame',
    'Horror' => 'Horreur',
    'Thriller' => 'Thriller',
    'Romance' => 'Romance',
    'Sci-Fi' => 'Science-fiction',
    'Animation' => 'Animation',
    'Documentary' => 'Documentaire',
    'Mystery' => 'Mystère',
    'Crime' => 'Policier',
    'Family' => 'Familial',
    'Fantasy' => 'Fantastique',
    'Western' => 'Western',
    'War' => 'Guerre',
    'Biography' => 'Biographie',
    'History' => 'Histoire',
    'Music' => 'Musical',
    'Sport' => 'Sport'
];

$genresTmdb = $filmData['genres'] ?? [];
$genresList = [];
foreach ($genresTmdb as $g) {
    $enName = $g['name'] ?? '';
    $genresList[] = $genresFR[$enName] ?? $enName;
}
$genres = !empty($genresList) ? implode(', ', $genresList) : 'Genre inconnu';

$description = $filmData['overview'] ?? 'Aucune description disponible';
$descriptionFR = function_exists('translate_text_google') ? translate_text_google($description, "fr", $traces) : $description;
$descriptionFR = $descriptionFR ?: $description;

$affiche = !empty($filmData['poster_path']) ? "https://image.tmdb.org/t/p/w500{$filmData['poster_path']}" : 'assets/img/no-poster.jpg';

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

exit;
