<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Génère le token CSRF avant d'afficher le formulaire
$token = csrf_token();

// 🔒 Rate limiting simple (5 tentatives max par IP toutes les 15 min)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = 'login_attempts_' . md5($ip);

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = ['count' => 0, 'time' => time()];
}

// Reset après 15 minutes
if (time() - $_SESSION[$rate_key]['time'] > 900) {
    $_SESSION[$rate_key] = ['count' => 0, 'time' => time()];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Requête invalide (CSRF).';
    }

    // 🔒 Vérification rate limiting
    if ($_SESSION[$rate_key]['count'] >= 5) {
        $errors[] = 'Trop de tentatives. Réessayez dans 15 minutes.';
    }

    if (empty($errors)) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // 🔒 Validation basique
        if ($email === '' || $password === '') {
            $errors[] = 'Email et mot de passe requis.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        } elseif (strlen($password) > 255) {
            $errors[] = 'Mot de passe trop long.';
        } else {
            $stmt = $pdo->prepare('SELECT id, password_hash, username, actif, admin FROM utilisateurs WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['actif'] != 1) {
                    $errors[] = 'Compte non activé.';
                } else {
                    // 🔒 Régénération de l'ID de session après login
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = (bool)$user['admin'];
                    $_SESSION['last_activity'] = time();

                    // Reset des tentatives
                    unset($_SESSION[$rate_key]);

                    header('Location: index.php');
                    exit;
                }
            } else {
                // 🔒 Incrément du compteur de tentatives
                $_SESSION[$rate_key]['count']++;
                $errors[] = 'Identifiants invalides.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Connexion — Mes Films</title>
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="min-h-screen bg-gray-100">
    <main class="container mx-auto p-3 max-w-md">
        <h1 class="text-2xl font-bold mb-4">Connexion</h1>
        <?php if ($errors): foreach ($errors as $err): ?>
                <div class="p-2 bg-red-200 text-red-800 rounded mb-2"><?= e($err) ?></div>
        <?php endforeach;
        endif; ?>
        <form method="post">
            <!-- 🔒 Token CSRF -->
            <input type="hidden" name="csrf" value="<?= e($token) ?>">

            <label class="block mb-2">
                Email
                <input type="email" name="email" required maxlength="255" class="w-full p-2 border rounded" value="<?= e($_POST['email'] ?? '') ?>">
            </label>

            <label class="block mb-2">
                Mot de passe
                <input type="password" name="password" required maxlength="255" class="w-full p-2 border rounded">
            </label>

            <button class="mt-3 p-2 bg-blue-600 text-white rounded w-full">Se connecter</button>
        </form>
        <p class="mt-3">Pas de compte ? <a href="register.php" class="text-blue-600">Inscription</a></p>
    </main>
</body>

</html>