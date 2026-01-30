<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Inclure PHPMailer
require_once __DIR__ . '/assets/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/assets/PHPMailer/SMTP.php';
require_once __DIR__ . '/assets/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Veuillez saisir un email valide.';
    } else {
        // Vérifier si l'utilisateur existe
        $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Ne pas révéler que l'email n'existe pas
            $success = 'Si cet email existe, vous recevrez un lien pour réinitialiser le mot de passe.';
        } else {
            // Générer un token sécurisé
            $token = bin2hex(random_bytes(32));
            $expire = date('Y-m-d H:i:s', time() + 3600); // valable 1h

            // Sauvegarder dans la DB
            $stmt = $pdo->prepare('UPDATE utilisateurs SET reset_token = ?, reset_expire = ? WHERE id = ?');
            $stmt->execute([$token, $expire, $user['id']]);

            // Construire le lien de réinitialisation
            $resetLink = "https://tonsite.com/reset_password.php?token=$token";

            // Envoi du mail
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'thierry.enjalbert@gmail.com';   // ton email Gmail
                $mail->Password   = 'vndbrfvwalsayahc';             // mot de passe d'application Gmail
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->SMTPDebug  = 0;

                $mail->setFrom('no-reply@tonsite.com', 'Mes Films');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Réinitialisation de votre mot de passe';
                $mail->Body    = "Bonjour,<br><br>"
                    . "Vous avez demandé à réinitialiser votre mot de passe.<br>"
                    . "Cliquez sur ce lien pour le réinitialiser (valable 1h) :<br>"
                    . "<a href=\"$resetLink\">$resetLink</a><br><br>"
                    . "Si vous n'avez pas demandé cette réinitialisation, ignorez ce message.";

                $mail->send();
                $success = 'Si cet email existe, vous recevrez un lien pour réinitialiser le mot de passe.';
            } catch (Exception $e) {
                $errors[] = "Le mail n'a pas pu être envoyé. Erreur: {$mail->ErrorInfo}";
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
    <title>Mot de passe oublié — Mes Films</title>
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="min-h-screen bg-gray-100">
    <main class="container mx-auto p-3 max-w-md">
        <h1 class="text-2xl font-bold mb-4">Mot de passe oublié</h1>

        <?php if ($errors): foreach ($errors as $err): ?>
                <div class="p-2 bg-red-200 text-red-800 mb-2 rounded"><?= e($err) ?></div>
        <?php endforeach;
        endif; ?>

        <?php if ($success): ?>
            <div class="p-2 bg-green-200 text-green-800 mb-2 rounded"><?= e($success) ?></div>
        <?php endif; ?>

        <form method="post">
            <label class="block mb-2">
                Email
                <input type="email" name="email" required class="w-full p-2 border rounded" placeholder="Votre email">
            </label>
            <button class="mt-3 p-2 bg-blue-600 text-white rounded w-full">Envoyer le lien</button>
        </form>

        <p class="mt-4 text-center">
            <a href="login.php" class="text-blue-600 hover:underline">Retour à la connexion</a>
        </p>
    </main>
</body>

</html>