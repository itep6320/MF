<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    http_response_code(403);
    exit;
}

$userId = current_user_id();

// Notifications visibles pour tous OU ciblées sur l’utilisateur
$stmt = $pdo->prepare("
    SELECT c.id, c.contenu, c.date_creation, u.username 
    FROM commentaires c
    JOIN utilisateurs u ON c.utilisateur_id = u.id
    WHERE c.est_notification = 1
      AND (c.utilisateur_cible_id IS NULL OR c.utilisateur_cible_id = ?)
    ORDER BY c.date_creation DESC
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($notifications);
