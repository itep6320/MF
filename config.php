<?php
// Config loader - expects .env in parent directory for shared setup like your other app
$envPath = __DIR__ . '/../.env-mf';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/.env-mf'; // fallback to local .env
}

if (!file_exists($envPath)) {
    error_log("Fichier .env introuvable");
    die("Erreur de configuration");
}

$env = parse_ini_file($envPath);

// DB
$host = $env['DB_HOST'];
$dbname = $env['DB_NAME'];
$username = $env['DB_USER'];
$password = $env['DB_PASS'];
$dbPort = $env['DB_PORT'];
$charset = $env['DB_CHARSET'];

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=$charset;port=$dbPort",
        $username,
        $password,
        $options
    );
} catch (PDOException $e) {
    die("Erreur PDO : " . $e->getMessage());
}

session_start();

// helper: current user
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function is_admin(): bool {
    return !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
