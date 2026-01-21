<?php
require_once __DIR__ . '/config.php';

session_start();

// CSRF check
$csrf_post = $_POST['csrf'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf_post)) {
    http_response_code(403);
    exit('CSRF token invalide');
}

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$comment_id = intval($_POST['comment_id'] ?? 0);
$film_id = intval($_POST['film_id'] ?? 0);

if ($comment_id > 0) {
    // Vérifier que l'utilisateur est propriétaire du commentaire ou admin
    $stmt = $pdo->prepare('SELECT utilisateur_id FROM commentaires WHERE id = ?');
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();

    if ($comment && ($comment['utilisateur_id'] == current_user_id() || is_admin())) {
        $deleteStmt = $pdo->prepare('DELETE FROM commentaires WHERE id = ?');
        $deleteStmt->execute([$comment_id]);
    }
}

header('Location: film.php?id=' . $film_id);
exit;
