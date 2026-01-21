<?php
// IMDb.php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// ⚡ Clé et Host RapidAPI
$imdbApiKey = 'efce289393msh934676cc0e6e5d2p153d44jsna1c814f30dc0';
$imdbHost = 'imdb8.p.rapidapi.com';

// Récupérer la recherche depuis l'URL
$query = trim($_GET['q'] ?? '');
if (!$query) {
    echo json_encode(['d' => []]);
    exit;
}

// URL RapidAPI
$url = "https://{$imdbHost}/auto-complete?q=" . urlencode($query);

// cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-RapidAPI-Key: $imdbApiKey",
        "X-RapidAPI-Host: $imdbHost"
    ]
]);

$response = curl_exec($ch);
if ($response === false) {
    echo json_encode(['d' => [], 'error' => curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

// Vérifier le JSON
$data = json_decode($response, true);
if (!$data || !isset($data['d'])) {
    echo json_encode(['d' => [], 'error' => 'Réponse IMDb non valide']);
    exit;
}

// Retour JSON uniforme
echo json_encode($data);
