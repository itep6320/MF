<?php
require_once __DIR__ . '/config.php';
session_start();

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$serie_id = (int)($_POST['serie_id'] ?? 0);
$note    = (int)($_POST['note'] ?? 0);

if ($serie_id <= 0) {
    header('Location: index.php');
    exit;
}

if ($note >= 1 && $note <= 10) {
    $stmt = $pdo->prepare(
        'INSERT INTO notes (utilisateur_id, film_id, note, type)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            note = VALUES(note),
            date_modif = CURRENT_TIMESTAMP',
    );

    $stmt->execute([
        current_user_id(),
        $serie_id,
        $note,
        "serie"
    ]);
}

header('Location: series.php?id=' . $serie_id);
exit;
