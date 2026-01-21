<?php
require_once __DIR__ . '/config.php';
session_start();

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// CSRF
if (
    empty($_POST['csrf']) ||
    $_POST['csrf'] !== ($_SESSION['csrf_token'] ?? '')
) {
    die('CSRF invalide');
}

$commentId = (int)($_POST['comment_id'] ?? 0);
$filmId    = (int)($_POST['film_id'] ?? 0);
$contenu  = trim($_POST['contenu'] ?? '');

if ($commentId <= 0 || $filmId <= 0 || $contenu === '') {
    header('Location: film.php?id=' . $filmId);
    exit;
}

// Vérifier droits
$stmt = $pdo->prepare(
    'SELECT utilisateur_id FROM commentaires WHERE id = ?'
);
$stmt->execute([$commentId]);
$comment = $stmt->fetch();

if (!$comment) {
    die('Commentaire introuvable');
}

if (
    $comment['utilisateur_id'] !== current_user_id() &&
    !is_admin()
) {
    die('Accès interdit');
}

// Update
$stmt = $pdo->prepare(
    'UPDATE commentaires
     SET contenu = ?, date_modif = CURRENT_TIMESTAMP
     WHERE id = ?'
);
$stmt->execute([$contenu, $commentId]);

header('Location: film.php?id=' . $filmId);
exit;
