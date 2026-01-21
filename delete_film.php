<?php
require_once __DIR__ . '/config.php';

session_start();

// CSRF check
$csrf_post = $_POST['csrf'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf_post)) {
    http_response_code(403);
    exit('CSRF token invalide');
}

// Seuls les admins peuvent supprimer des films
if (!is_admin()) {
    http_response_code(403);
    exit('Accès refusé');
}

$film_id = intval($_POST['film_id'] ?? 0);
$delete_physical = isset($_POST['delete_physical']) && $_POST['delete_physical'] === '1';

if ($film_id > 0) {
    // Récupérer les infos du film
    $stmt = $pdo->prepare('SELECT chemin FROM films WHERE id = ?');
    $stmt->execute([$film_id]);
    $film = $stmt->fetch();

    if ($film) {
        // Supprimer de la base de données
        $pdo->beginTransaction();

        try {
            // Supprimer les commentaires
            $pdo->prepare('DELETE FROM commentaires WHERE film_id = ?')->execute([$film_id]);

            // Supprimer les notes
            $pdo->prepare('DELETE FROM notes WHERE film_id = ?')->execute([$film_id]);

            // Supprimer le film
            $pdo->prepare('DELETE FROM films WHERE id = ?')->execute([$film_id]);

            $pdo->commit();

            // Supprimer le fichier physique si demandé
            if ($delete_physical && !empty($film['chemin']) && file_exists($film['chemin'])) {
                unlink($film['chemin']);
            }

            header('Location: index.php?deleted=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            exit('Erreur lors de la suppression : ' . $e->getMessage());
        }
    }
}

header('Location: index.php');
exit;
