<?php
session_start();
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (
    empty($data['csrf']) ||
    $data['csrf'] !== $_SESSION['csrf_token']
) {
    echo json_encode(['success' => false, 'error' => 'CSRF invalide']);
    exit;
}

$episodeId   = (int)($data['episode_id'] ?? 0);
$titre       = trim($data['titre'] ?? '');
$description = trim($data['description'] ?? '');

if ($episodeId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Épisode invalide']);
    exit;
}

$stmt = $pdo->prepare("
    UPDATE episodes
    SET titre_episode = :titre,
        description_episode = :description
    WHERE id = :id
");

$stmt->execute([
    'titre' => $titre,
    'description' => $description,
    'id' => $episodeId
]);

echo json_encode(['success' => true]);
