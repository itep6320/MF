<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$token = $_GET['token'] ?? '';
$errors = [];
$success = '';

if (!$token) {
    die('Lien invalide.');
}

// Vérifier token valide et non expiré
$stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE reset_token = ? AND reset_expire > NOW() LIMIT 1');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die('Token invalide ou expiré.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($password === '' || $password2 === '') {
        $errors[] = 'Tous les champs sont obligatoires.';
    } elseif ($password !== $password2) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    } else {
        // Mettre à jour le mot de passe et supprimer le token
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE utilisateurs SET password_hash = ?, reset_token = NULL, reset_expire = NULL WHERE id = ?');
        $stmt->execute([$hash, $user['id']]);
        $success = 'Mot de passe mis à jour. Vous pouvez maintenant vous connecter.';

        // Rediriger vers login.php avec message
        header('Location: login.php?reset=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Réinitialiser le mot de passe — Mes Films</title>
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="min-h-screen bg-gray-100">
    <main class="container mx-auto p-3 max-w-md">
        <h1 class="text-2xl font-bold mb-4">Réinitialiser le mot de passe</h1>

        <?php if ($errors): foreach ($errors as $err): ?>
                <div class="p-2 bg-red-200 text-red-800 mb-2 rounded"><?= e($err) ?></div>
        <?php endforeach;
        endif; ?>

        <form method="post">
            <label class="block mb-2">
                Nouveau mot de passe
                <input type="password" name="password" required class="w-full p-2 border rounded">
            </label>
            <label class="block mb-2">
                Confirmer
                <input type="password" name="password2" required class="w-full p-2 border rounded">
            </label>
            <button class="mt-3 p-2 bg-blue-600 text-white rounded w-full">Valider</button>
        </form>

    </main>
</body>

</html>