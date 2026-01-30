<?php
require_once __DIR__ . '/config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

error_log('POST data: ' . print_r($_POST, true));

// Simple CSRF check
$csrf_post = $_POST['csrf'] ?? '';
$type = $_POST['type'] ?? 'film';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf_post)) {
    error_log('CSRF token invalide');
    http_response_code(403);
    exit('CSRF token invalide');
}

if (!is_logged_in()) {
    error_log('Utilisateur non connecté');
    header('Location: login.php');
    exit;
}

$user_id = current_user_id();
error_log('Utilisateur connecté, ID=' . $user_id);

$film_id = intval($_POST['film_id'] ?? 0);
$contenu = trim($_POST['contenu'] ?? '');

if ($film_id > 0 && $contenu !== '') {
    $stmt = $pdo->prepare('INSERT INTO commentaires (utilisateur_id, film_id, contenu, type) VALUES (?, ?, ?, ?)');
    $success = $stmt->execute([$user_id, $film_id, $contenu, $type]);
    if (!$success) {
        $errorInfo = $stmt->errorInfo();
        error_log('Erreur SQL: ' . print_r($errorInfo, true));
    } else {
        error_log('Commentaire inséré avec succès');
    }
} else {
    error_log("film_id ou contenu invalide : film_id=$film_id, contenu='$contenu'");
}

if ($type != "film")
    header('Location: series.php?id=' . $film_id);
else
    header('Location: film.php?id=' . $film_id);
exit;
