<?php
session_start();
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

$type = $_POST['type'] ?? ''; // 'film' ou 'episode'
$id = intval($_POST['id'] ?? 0);

if ($id <= 0 || !in_array($type, ['film', 'episode'])) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

try {
    if ($type === 'film') {
        // Vérifier que le film existe
        $checkStmt = $pdo->prepare('SELECT id FROM films WHERE id = ?');
        $checkStmt->execute([$id]);

        if (!$checkStmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Film non trouvé']);
            exit;
        }

        // Enregistrer la lecture du film
        $stmt = $pdo->prepare('
            INSERT INTO historique_lectures (utilisateur_id, episode_id, date_lecture)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                date_lecture = NOW()
        ');
        $stmt->execute([current_user_id(), $id]);
    } else if ($type === 'episode') {
        // Vérifier que l'épisode existe
        $checkStmt = $pdo->prepare('SELECT id FROM episodes WHERE id = ?');
        $checkStmt->execute([$id]);

        if (!$checkStmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Épisode non trouvé']);
            exit;
        }

        // Enregistrer la lecture de l'épisode
        $stmt = $pdo->prepare('
            INSERT INTO historique_lectures (utilisateur_id, episode_id, date_lecture, type)
            VALUES (?, ?, NOW(), 2)
            ON DUPLICATE KEY UPDATE
                date_lecture = NOW()
        ');
        $stmt->execute([current_user_id(), $id]);
    }

    echo json_encode(['success' => true, 'type' => $type, 'id' => $id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
