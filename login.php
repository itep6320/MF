<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$token = csrf_token();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_key = 'login_attempts_' . md5($ip);

if (!isset($_SESSION[$rate_key])) $_SESSION[$rate_key] = ['count' => 0, 'time' => time()];
if (time() - $_SESSION[$rate_key]['time'] > 900) $_SESSION[$rate_key] = ['count' => 0, 'time' => time()];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) $errors[] = 'Requête invalide (CSRF).';
    if ($_SESSION[$rate_key]['count'] >= 5) $errors[] = 'Trop de tentatives. Réessayez dans 15 minutes.';

    if (empty($errors)) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') $errors[] = 'Email et mot de passe requis.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
        elseif (strlen($password) > 255) $errors[] = 'Mot de passe trop long.';
        else {
            $stmt = $pdo->prepare('SELECT id, password_hash, username, actif, admin, ip_inscription FROM utilisateurs WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['actif'] != 1) {
                    $errors[] = 'Compte non activé.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['last_activity'] = time();
                    // Vérification IP pour accès admin
                    $current_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

                    if ($user['admin']) {
                        // Séparer toutes les IP autorisées par espace
                        $ips = preg_split('/\s+/', trim($user['ip_inscription']));

                        if (!in_array($current_ip, $ips) && $user['admin'] != 2) {
                            $_SESSION['is_admin'] = false;
                            $_SESSION['notice'] = "⚠️ Accès admin désactivé pour cette IP.\nMerci de contacter votre administrateur.";
                        } else {
                            $_SESSION['is_admin'] = true;
                        }
                    } else {
                        $_SESSION['is_admin'] = false;
                    }

                    // MAJ IP et date
                    $stmt = $pdo->prepare('UPDATE utilisateurs SET ip_last_login = ?, date_last_login = NOW() WHERE id = ?');
                    $stmt->execute([$current_ip, $user['id']]);

                    unset($_SESSION[$rate_key]);
                    header('Location: index.php');
                    exit;
                }
            } else {
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
        <?php if (isset($_GET['reset']) && $_GET['reset'] == 1): ?>
            <div class="p-2 bg-green-200 text-green-800 mb-2 rounded text-center">
                Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.
            </div>
        <?php endif; ?>
        <h1 class="text-2xl font-bold mb-4">Connexion</h1>
        <?php if ($errors): foreach ($errors as $err): ?>
                <div class="p-2 bg-red-200 text-red-800 rounded mb-2"><?= e($err) ?></div>
        <?php endforeach;
        endif; ?>
        <form method="post">
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
        <p class="mt-3">
            Pas de compte ? <a href="register.php" class="text-blue-600">Inscription</a> |
            <a href="forgot_password.php" class="text-blue-600">Mot de passe oublié</a>
        </p>
    </main>
</body>

</html>