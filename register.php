<?php
require_once __DIR__ . '/config.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';
  $password2 = $_POST['password2'] ?? '';

  if ($email === '' || $username === '' || $password === '') {
    $errors[] = 'Tous les champs sont obligatoires.';
  } elseif ($password !== $password2) {
    $errors[] = 'Les mots de passe ne correspondent pas.';
  } else {
    // check email
    $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      $errors[] = 'Email déjà utilisé.';
    } else {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('INSERT INTO utilisateurs (email, password_hash, username, actif) VALUES (?, ?, ?, 1)');
      $stmt->execute([$email, $hash, $username]);
      header('Location: login.php');
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="fr">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inscription — Mes Films</title>
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="min-h-screen bg-gray-100">
  <main class="container mx-auto p-3 max-w-md">
    <h1 class="text-2xl font-bold mb-4">Inscription</h1>
    <?php if ($errors): foreach ($errors as $err): ?>
        <div class="p-2 bg-red-200 text-red-800 mb-2"><?= e($err) ?></div>
    <?php endforeach;
    endif; ?>
    <form method="post">
      <label>Nom d'utilisateur<input type="text" name="username" class="w-full p-2 border rounded"></label>
      <label class="mt-2">Email<input type="email" name="email" class="w-full p-2 border rounded"></label>
      <label class="mt-2">Mot de passe<input type="password" name="password" class="w-full p-2 border rounded"></label>
      <label class="mt-2">Confirmer<input type="password" name="password2" class="w-full p-2 border rounded"></label>
      <button class="mt-3 p-2 bg-green-600 text-white rounded">S'inscrire</button>
    </form>
    <p class="mt-4 text-center">
      Déjà inscrit ?
      <a href="login.php"
        class="inline-block ml-2 px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
        Connexion
      </a>
    </p>
  </main>
</body>

</html>