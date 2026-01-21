<?php
require_once __DIR__ . '/config.php';

session_start();

// Simple CSRF check
$csrf_post = $_POST['csrf'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf_post)) {
    // Deny if CSRF invalid
    http_response_code(403);
    exit('CSRF token invalide');
}

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
$film_id = intval($_POST['film_id'] ?? 0);
$contenu = trim($_POST['contenu'] ?? '');
if ($film_id > 0 && $contenu !== '') {
    $stmt = $pdo->prepare('INSERT INTO commentaires (utilisateur_id, film_id, contenu) VALUES (?, ?, ?)');
    $stmt->execute([current_user_id(), $film_id, $contenu]);
}
header('Location: film.php?id=' . $film_id);
exit;
