<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

// --- Configuration ---
$tmdbApiKey = 'f75887c1f49c99a3abe4ff8a9c46c919';

// --- Fonction utilitaire : appel TMDb via cURL ---
function tmdb_request($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // utile sur NAS Synology
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Synology-TMDB/1.0'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        $log = date('Y-m-d H:i:s') . " ❌ TMDb error: HTTP $httpCode | $curlErr | URL: $url\n";
        file_put_contents(__DIR__ . '/tmdb_error.log', $log, FILE_APPEND);
        return null;
    }

    return json_decode($response, true);
}

// --- Vérification du paramètre ---
if (empty($_GET['titre'])) {
    echo json_encode(['success' => false, 'error' => 'Titre manquant']);
    exit;
}

// --- Correction essentielle : dé-échappage HTML ---
$titre = trim($_GET['titre']);
$titre = html_entity_decode($titre, ENT_QUOTES, 'UTF-8');

// Correction secondaire (au cas où) pour retirer des backslashes
$titre = stripslashes($titre);

// --- Étape 1 : recherche du film ---
$urlSearch = "https://api.themoviedb.org/3/search/movie?api_key=$tmdbApiKey&query=" . urlencode($titre) . "&language=fr-FR";
$data = tmdb_request($urlSearch);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Erreur connexion TMDb (voir tmdb_error.log)']);
    exit;
}

if (empty($data['results'])) {
    echo json_encode(['success' => false, 'error' => 'Film non trouvé sur TMDb']);
    exit;
}

// --- Étape 2 : détails du film ---
$movieId = $data['results'][0]['id'];
$urlDetails = "https://api.themoviedb.org/3/movie/$movieId?api_key=$tmdbApiKey&language=fr-FR";
$details = tmdb_request($urlDetails);

if (!$details) {
    echo json_encode(['success' => false, 'error' => 'Erreur récupération détails film (voir tmdb_error.log)']);
    exit;
}

// --- Étape 3 : collection / franchise ---
$franchise = $details['belongs_to_collection']['name'] ?? null;

// ✅ Mise à jour auto dans la base si le film existe
$stmt = $pdo->prepare("UPDATE films SET franchise = :franchise WHERE titre = :titre");
$stmt->execute([':franchise' => $franchise, ':titre' => $titre]);

echo json_encode([
    'success' => true,
    'titre' => $titre,
    'tmdb_id' => $movieId,
    'franchise' => $franchise
]);
