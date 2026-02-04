<?php
// update_episode_online_TMDb.php
session_start();
require_once __DIR__ . '/functions.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

error_log("=== Début update_episode_online_TMDb.php ===");

$input = json_decode(file_get_contents('php://input'), true);
error_log("Input reçu: " . print_r($input, true));

$episodeId = intval($input['episode_id'] ?? 0);
$tmdbSerieId = intval($input['tmdb_serie_id'] ?? 0);
$saison = intval($input['saison'] ?? 0);
$numeroEpisode = intval($input['numero_episode'] ?? 0);

error_log("Episode ID: $episodeId, TMDb Serie ID: $tmdbSerieId, Saison: $saison, Episode: $numeroEpisode");

if ($episodeId <= 0 || $tmdbSerieId <= 0 || $saison <= 0 || $numeroEpisode <= 0) {
    error_log("Episode hors saisons");
    echo json_encode(['success' => false, 'error' => 'Episode hors saisons']);
    exit;
}

// Configuration TMDb
$apiKey = 'f75887c1f49c99a3abe4ff8a9c46c919';
$baseUrl = 'https://api.themoviedb.org/3';
$imageBase = 'https://image.tmdb.org/t/p/w500';

// Récupérer les détails de l'épisode
$episodeUrl = "{$baseUrl}/tv/{$tmdbSerieId}/season/{$saison}/episode/{$numeroEpisode}?api_key={$apiKey}&language=fr-FR";
error_log("URL TMDb Episode: $episodeUrl");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $episodeUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

error_log("Code HTTP: $httpCode");

if ($response === false || $httpCode !== 200) {
    error_log("Erreur: Impossible de récupérer les données TMDb. Code: $httpCode");
    echo json_encode([
        'success' => false,
        'error' => 'Épisode non trouvé sur TMDb',
        'debug' => ['http_code' => $httpCode, 'curl_error' => $curlError]
    ]);
    exit;
}

$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erreur JSON: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Erreur de décodage JSON']);
    exit;
}

error_log("Données épisode: " . print_r($data, true));

// Extraire les informations de l'épisode
$titreEpisode = trim($data['name'] ?? '');
$description = trim($data['overview'] ?? '');

// Si titre est générique "Episode X" ET description vide, on considère que ce n'est pas un vrai épisode
if (preg_match('/^[ÉE]pisode\s+\d+$/iu', $titreEpisode) && empty($description)) {
    error_log("Titre générique détecté sans description, pas de mise à jour");
    echo json_encode(['success' => false, 'error' => 'Aucun épisode valide trouvé sur TMDb']);
    exit;
}

// Traduction de la description si disponible
$traces = [];
$descriptionFR = function_exists('translate_text_google') ? translate_text_google($description, "fr", $traces) : $description;
$descriptionFR = $descriptionFR ?: $description;

// Image de l'épisode (optionnel)
$stillPath = !empty($data['still_path']) ? $imageBase . $data['still_path'] : null;

// Mise à jour dans la base de données
try {
    error_log("Tentative de mise à jour BD épisode: titre=$titreEpisode");

    $stmt = $pdo->prepare("
        UPDATE episodes 
        SET titre_episode = ?, description_episode = ?
        WHERE id = ?
    ");

    $stmt->execute([$titreEpisode, $descriptionFR, $episodeId]);

    error_log("Mise à jour réussie, lignes affectées: " . $stmt->rowCount());

    // Récupérer l'épisode mis à jour
    $stmt = $pdo->prepare('SELECT * FROM episodes WHERE id = ?');
    $stmt->execute([$episodeId]);
    $episode = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("Épisode récupéré: " . print_r($episode, true));

    echo json_encode([
        'success' => true,
        'episode' => $episode,
        'still_path' => $stillPath
    ]);
} catch (PDOException $e) {
    error_log("Erreur PDO: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
}
