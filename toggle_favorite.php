<?php
session_start();
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Vous devez être connecté']);
    exit;
}

// Vérifier le token CSRF
if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
    exit;
}

// Récupérer les paramètres
$filmId = intval($_POST['film_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($filmId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de film invalide']);
    exit;
}

if (!in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'error' => 'Action invalide']);
    exit;
}

$userId = current_user_id();

try {
    if ($action === 'add') {
        // Vérifier si le favori n'existe pas déjà
        $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM favoris WHERE utilisateur_id = ? AND film_id = ?');
        $checkStmt->execute([$userId, $filmId]);
        
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Ce film est déjà dans vos favoris']);
            exit;
        }
        
        // Ajouter aux favoris
        $stmt = $pdo->prepare('INSERT INTO favoris (utilisateur_id, film_id, date_ajout) VALUES (?, ?, NOW())');
        $stmt->execute([$userId, $filmId]);
        
        echo json_encode(['success' => true, 'message' => 'Film ajouté aux favoris']);
        
    } else { // remove
        // Retirer des favoris
        $stmt = $pdo->prepare('DELETE FROM favoris WHERE utilisateur_id = ? AND film_id = ?');
        $stmt->execute([$userId, $filmId]);
        
        echo json_encode(['success' => true, 'message' => 'Film retiré des favoris']);
    }
    
} catch (PDOException $e) {
    error_log("Erreur favoris: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la modification des favoris']);
}