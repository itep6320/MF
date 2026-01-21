<?php
require_once __DIR__ . '/config.php';

if (!is_admin()) {
    http_response_code(403);
    exit('Accès refusé');
}

$csrf = $_POST['csrf'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    exit('Jeton CSRF invalide');
}

$contenu = trim($_POST['contenu'] ?? '');
$utilisateur_cible_id = $_POST['utilisateur_cible_id'] !== '' ? intval($_POST['utilisateur_cible_id']) : null;

if ($contenu !== '') {
    $stmt = $pdo->prepare('
        INSERT INTO commentaires (utilisateur_id, utilisateur_cible_id, film_id, contenu, est_notification)
        VALUES (?, ?, NULL, ?, 1)
    ');
    $stmt->execute([current_user_id(), $utilisateur_cible_id, $contenu]);
}

// Retour à la page précédente
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
