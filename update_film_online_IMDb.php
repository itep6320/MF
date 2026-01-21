<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php'; // fichier où est défini $pdo

header('Content-Type: application/json');

// Récupérer les données POST
$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$imdbID = $data['imdbID'] ?? '';

if ($id <= 0 || !$imdbID) {
    echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
    exit;
}

// ⚡ Appel API IMDb via RapidAPI
$imdbApiKey = 'efce289393msh934676cc0e6e5d2p153d44jsna1c814f30dc0';
$imdbHost = 'imdb8.p.rapidapi.com';

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://$imdbHost/title/get-overview-details?tconst=$imdbID",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-RapidAPI-Key: $imdbApiKey",
        "X-RapidAPI-Host: $imdbHost"
    ]
]);
$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo json_encode(['success' => false, 'message' => "Erreur API: $err"]);
    exit;
}

$filmData = json_decode($response, true);

if (!$filmData) {
    echo json_encode(['success' => false, 'message' => 'Réponse API invalide']);
    exit;
}


/* // Afficher la réponse brute JSON
    echo "<pre>";
    print_r($filmData);
    echo "</pre>";
    exit; */

$description = $filmData['plotSummary']['text'] ?? 'Aucune description disponible';
$affiche = $filmData['image']['url'] ?? '';
$genre = '';
if (!empty($filmData['genres']) && is_array($filmData['genres'])) {
    $genre = implode(', ', $filmData['genres']);
}

// Mettre à jour la base
try {
    // Récupération des genres (tableau)
    $genreEn = $filmData['genres'] ?? [];

    // Tableau de traduction
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
        'Mystery' => 'Mystère'
    ];

    // Traduction et conversion en chaîne
    $genre = implode(', ', array_map(function ($g) use ($genresFR) {
        return $genresFR[trim($g)] ?? $g;
    }, $genreEn));


    $titre = $filmData['title']['title'] ?? 'Titre inconnu';
    $annee = $filmData['title']['year'] ?? '????';
    $description = translate_text_google($filmData['plotOutline']['text'] ?? '', "fr", $traces);
    $affiche = $filmData['title']['image']['url'] ?? '';

    $stmt = $pdo->prepare('UPDATE films SET titre=?, annee=?, description=?, affiche=?, genre=? WHERE id=?');
    $stmt->execute([$titre, $annee, $description, $affiche, $genre, $id]);

    echo json_encode([
        'success' => true,
        'film' => [
            'titre' => $titre,
            'annee' => $annee,
            'description' => $description,
            'genre' => $genre,
            'affiche' => $affiche,
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données : ' . $e->getMessage()
    ]);
}
