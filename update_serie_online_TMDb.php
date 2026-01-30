<?php
// update_serie_online_TMDb.php
session_start();
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

// Log des erreurs
error_log("=== Début update_serie_online_TMDb.php ===");

$input = json_decode(file_get_contents('php://input'), true);
error_log("Input reçu: " . print_r($input, true));

$serieId = intval($input['id'] ?? 0);
$tmdbID = $input['tmdbID'] ?? '';

error_log("Serie ID: $serieId, TMDb ID: $tmdbID");

if ($serieId <= 0 || !$tmdbID) {
    error_log("Erreur: Paramètres invalides");
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

// Configuration TMDb
$apiKey = 'f75887c1f49c99a3abe4ff8a9c46c919';
$baseUrl = 'https://api.themoviedb.org/3';
$imageBase = 'https://image.tmdb.org/t/p/w500';

// Récupérer les détails de la série
$detailsUrl = "{$baseUrl}/tv/{$tmdbID}?api_key={$apiKey}&language=fr-FR";
error_log("URL TMDb: $detailsUrl");

// Utiliser cURL au lieu de file_get_contents pour plus de robustesse
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $detailsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

error_log("Code HTTP: $httpCode");
error_log("Erreur cURL: $curlError");

if ($response === false || $httpCode !== 200) {
    error_log("Erreur: Impossible de récupérer les données TMDb. Code: $httpCode");
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur lors de la récupération des données TMDb',
        'debug' => [
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'url' => $detailsUrl
        ]
    ]);
    exit;
}

error_log("Réponse TMDb reçue: " . substr($response, 0, 200));

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erreur JSON: " . json_last_error_msg());
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur de décodage JSON: ' . json_last_error_msg()
    ]);
    exit;
}

error_log("Données décodées: " . print_r($data, true));

// Extraire les informations
$titre = $data['name'] ?? '';
$affiche = !empty($data['poster_path']) ? $imageBase . $data['poster_path'] : null;
$annee = !empty($data['first_air_date']) ? substr($data['first_air_date'], 0, 4) : null;
$nbSaisons = $data['number_of_seasons'] ?? 0;

// Traduction des genres
$genresFR = [
    'Action' => 'Action',
    'Action & Adventure' => 'Action & Aventure',
    'Adventure' => 'Aventure',
    'Comedy' => 'Comédie',
    'Drama' => 'Drame',
    'Horror' => 'Horreur',
    'Thriller' => 'Thriller',
    'Romance' => 'Romance',
    'Sci-Fi & Fantasy' => 'Science-fiction & Fantastique',
    'Science Fiction' => 'Science-fiction',
    'Animation' => 'Animation',
    'Documentary' => 'Documentaire',
    'Mystery' => 'Mystère',
    'Crime' => 'Policier',
    'Family' => 'Familial',
    'Kids' => 'Enfants',
    'Western' => 'Western',
    'War & Politics' => 'Guerre & Politique',
    'Soap' => 'Feuilleton',
    'Talk' => 'Talk-show',
    'Reality' => 'Téléréalité',
    'News' => 'Actualités'
];

$genresData = $data['genres'] ?? [];
$genresTraduites = array_map(function($g) use ($genresFR) {
    return $genresFR[$g['name']] ?? $g['name'];
}, $genresData);
$genre = implode(', ', $genresTraduites);

// Description avec traduction si disponible
$description = $data['overview'] ?? '';
$traces = [];
$descriptionFR = function_exists('translate_text_google') ? translate_text_google($description, "fr", $traces) : $description;
$descriptionFR = $descriptionFR ?: $description;

// Mise à jour dans la base de données
try {
    error_log("Tentative de mise à jour BD avec: titre=$titre, affiche=$affiche, genre=$genre, annee=$annee, nb_saisons=$nbSaisons");
    
    // Vérifier si la colonne tmdb_id existe, sinon l'ignorer
    $columns = $pdo->query("SHOW COLUMNS FROM series LIKE 'tmdb_id'")->fetchAll();
    $hasTmdbId = count($columns) > 0;
    
    if ($hasTmdbId) {
        $stmt = $pdo->prepare("
            UPDATE series 
            SET titre = ?, affiche = ?, description = ?, genre = ?, annee = ?, nb_saisons = ?, tmdb_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$titre, $affiche, $descriptionFR, $genre, $annee, $nbSaisons, $tmdbID, $serieId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE series 
            SET titre = ?, affiche = ?, description = ?, genre = ?, annee = ?, nb_saisons = ?
            WHERE id = ?
        ");
        $stmt->execute([$titre, $affiche, $descriptionFR, $genre, $annee, $nbSaisons, $serieId]);
    }
    
    error_log("Mise à jour réussie, lignes affectées: " . $stmt->rowCount());
    
    // Récupérer la série mise à jour
    $stmt = $pdo->prepare('SELECT * FROM series WHERE id = ?');
    $stmt->execute([$serieId]);
    $serie = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Série récupérée: " . print_r($serie, true));
    
    echo json_encode([
        'success' => true,
        'serie' => $serie,
        'tmdb_id' => $tmdbID // Retourner l'ID TMDb pour l'utiliser côté JavaScript
    ]);
    
} catch (PDOException $e) {
    error_log("Erreur PDO: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>