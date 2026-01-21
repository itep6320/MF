<?php
session_start();
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté et admin
if (!is_logged_in() || !is_admin()) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

// Vérifier le token CSRF
if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
    exit;
}

// Récupérer les données
$filmId = intval($_POST['film_id'] ?? 0);
$franchise = trim($_POST['franchise'] ?? '');

if ($filmId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID film invalide']);
    exit;
}

try {
    // Mettre à jour la franchise (peut être vide pour supprimer)
    $stmt = $pdo->prepare('UPDATE films SET franchise = ? WHERE id = ?');
    $stmt->execute([$franchise ?: null, $filmId]);
    
    echo json_encode([
        'success' => true,
        'franchise' => $franchise ?: null
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Erreur base de données: ' . $e->getMessage()
    ]);
}