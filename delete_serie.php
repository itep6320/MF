<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

session_start();

/* CSRF */
if (
    empty($_POST['csrf']) ||
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])
) {
    http_response_code(403);
    exit('CSRF invalide');
}

if (!is_admin()) {
    http_response_code(403);
    exit('Accès refusé');
}

$serie_id = (int)($_POST['serie_id'] ?? 0);
$delete_physical = isset($_POST['delete_physical']);

if ($serie_id <= 0) {
    exit('ID série invalide');
}

/* Récupérer chemins */
$stmt = $pdo->prepare("
    SELECT chemin
    FROM episodes
    WHERE serie_id = ? AND chemin IS NOT NULL
");
$stmt->execute([$serie_id]);
$paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

$baseDir = $paths ? dirname($paths[0]) : null;

$pdo->beginTransaction();

try {
    $pdo->prepare("DELETE FROM episodes WHERE serie_id = ?")->execute([$serie_id]);
    $pdo->prepare("DELETE FROM series WHERE id = ?")->execute([$serie_id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    exit('Erreur SQL : ' . $e->getMessage());
}

/* Supprimer fichiers */
if ($delete_physical && $baseDir && is_dir($baseDir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $file) {
        $file->isDir() ? rmdir($file) : unlink($file);
    }
    rmdir($baseDir);
}

header('Location: index_series.php?deleted=1');
exit;
