<?php
// update_serie_online_IMDb.php
session_start();
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$serieId = intval($input['id'] ?? 0);
$imdbID = $input['imdbID'] ?? '';

if ($serieId <= 0 || !$imdbID) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

// IMDb n'a pas d'API officielle, on utilise OMDb qui accède aux données IMDb
$apiKey = '5cf17b3b';

// Récupérer les détails via OMDb
$url = "http://www.omdbapi.com/?apikey={$apiKey}&i={$imdbID}&type=series&plot=full";
$response = @file_get_contents($url);

if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération des données']);
    exit;
}

$data = json_decode($response, true);

if ($data['Response'] === 'False') {
    echo json_encode(['success' => false, 'error' => $data['Error'] ?? 'Série non trouvée']);
    exit;
}

// Extraire les informations
$titre = $data['Title'] ?? '';
$affiche = ($data['Poster'] ?? '') !== 'N/A' ? $data['Poster'] : null;

// Extraire l'année
$annee = null;
if (!empty($data['Year'])) {
    preg_match('/^(\d{4})/', $data['Year'], $matches);
    $annee = $matches[1] ?? null;
}

// Nombre de saisons
$nbSaisons = !empty($data['totalSeasons']) ? (int)$data['totalSeasons'] : 0;

// Traduction des genres
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

$genresEn = ($data['Genre'] ?? '') !== 'N/A' ? $data['Genre'] : '';
$genresArray = array_filter(array_map('trim', explode(',', $genresEn)));
$genresTraduites = array_map(function($g) use ($genresFR) {
    return $genresFR[$g] ?? $g;
}, $genresArray);
$genre = implode(', ', $genresTraduites);

// Description avec traduction si disponible
$descriptionEn = ($data['Plot'] ?? '') !== 'N/A' ? $data['Plot'] : '';
$traces = [];
$description = function_exists('translate_text_google') ? translate_text_google($descriptionEn, "fr", $traces) : $descriptionEn;
$description = $description ?: $descriptionEn;

// Mise à jour dans la base de données
try {
    $stmt = $pdo->prepare("
        UPDATE series 
        SET titre = ?, affiche = ?, description = ?, genre = ?, annee = ?, nb_saisons = ?
        WHERE id = ?
    ");
    
    $stmt->execute([$titre, $affiche, $description, $genre, $annee, $nbSaisons, $serieId]);
    
    // Récupérer la série mise à jour
    $stmt = $pdo->prepare('SELECT * FROM series WHERE id = ?');
    $stmt->execute([$serieId]);
    $serie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'serie' => $serie
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>