<?php
require_once __DIR__ . '/config.php';

// simple esc
function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// compute average rating
function get_average_note(PDO $pdo, int $film_id): ?float
{
    $stmt = $pdo->prepare(
        'SELECT ROUND(AVG(note), 2) AS avgnote FROM notes WHERE film_id = ?'
    );
    $stmt->execute([$film_id]);
    $avg = $stmt->fetchColumn();
    return $avg !== false ? (float)$avg : null;
}

// fetch user's note
function get_user_note(PDO $pdo, int $user_id, int $film_id): ?int
{
    $stmt = $pdo->prepare('SELECT note FROM notes WHERE utilisateur_id = ? AND film_id = ?');
    $stmt->execute([$user_id, $film_id]);
    $r = $stmt->fetch();
    return $r ? (int)$r['note'] : null;
}

// Traduction gratuite via LibreTranslate avec segmentation
function translate_text_google($text, $target = "fr", &$traces)
{
    $traces[] = "Traduction texte via Google Translate...";

    // Nettoyer HTML et entités
    $text_clean = html_entity_decode(strip_tags($text));

    // Diviser le texte en segments de 500 caractères pour fiabilité
    $segments = str_split($text_clean, 500);
    $translated = "";

    foreach ($segments as $i => $segment) {
        $traces[] = "Traduction segment " . ($i + 1) . " : " . substr($segment, 0, 80) . "...";
        $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=" . $target . "&dt=t&q=" . urlencode($segment);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($resp, true);
        if ($result && isset($result[0])) {
            foreach ($result[0] as $item) {
                $translated .= $item[0];
            }
        } else {
            $traces[] = "Erreur traduction segment " . ($i + 1) . ", texte original conservé.";
            $translated .= $segment;
        }
    }

    $traces[] = "Traduction terminée.";
    return $translated;
}

// Génération du token CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Vérification du token CSRF
function verify_csrf_token(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
