<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php'; // fichier où est défini $pdo
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$imdbID = $data['imdbID'] ?? '';

if ($id <= 0 || !$imdbID) {
    echo json_encode(['success' => false, 'error' => 'Film ID ou IMDb ID manquant']);
    exit;
}

$apiKey = '5cf17b3b';
$url = "https://www.omdbapi.com/?apikey={$apiKey}&i=" . urlencode($imdbID) . "&plot=full";

// utiliser cURL pour récupérer les données
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FAILONERROR, true);
$response = curl_exec($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'error' => curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

// parser la réponse JSON
$filmData = json_decode($response, true);
if (!$filmData || ($filmData['Response'] ?? '') !== 'True') {
    echo json_encode(['success' => false, 'error' => 'Film non trouvé sur OMDb']);
    exit;
}

// Traduction simple des genres en français
$genreEn = $filmData['Genre'] ?? '';
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
    'Documentary' => 'Documentaire'
];

$genreFR = implode(', ', array_map(function ($g) use ($genresFR) {
    return $genresFR[trim($g)] ?? $g;
}, explode(',', $genreEn)));

$description = $filmData['Plot'] ?? '';
$descriptionFR = translate_text_google($description, "fr", $traces);
$titre = $filmData['Title'] ?? '';
$annee = $filmData['Year'] ?? '';
$affiche = ($filmData['Poster'] && $filmData['Poster'] !== 'N/A') ? $filmData['Poster'] : null;

// mettre à jour la base
try {
    $update = $pdo->prepare('UPDATE films SET titre=?, annee=?, genre=?, description=?, affiche=? WHERE id=?');
    $update->execute([$titre, $annee, $genreFR, $descriptionFR, $affiche, $id]);

    echo json_encode(['success' => true, 'film' => [
        'titre' => $titre,
        'annee' => $annee,
        'genre' => $genreFR,
        'description' => $descriptionFR,
        'affiche' => $affiche
    ]]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
