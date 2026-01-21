<?php
require_once __DIR__ . '/config.php';

/* ------------------------ LOGGING SQL ------------------------ */
function log_sql($message) {
    $logFile = __DIR__ . '/sql_delete_notification.log';
    $entry = date('Y-m-d H:i:s') . ' | ' . $message . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

/* ------------------------ AUTH ------------------------ */
if (!is_logged_in()) {
    log_sql("REFUSED: user not logged in");
    header('Location: login.php');
    exit;
}

/* ------------------------ CSRF ------------------------ */
$csrf_post = $_POST['csrf'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrf_post)) {
    log_sql("REFUSED: invalid CSRF for user_id=" . current_user_id());
    http_response_code(403);
    exit('CSRF token invalide');
}

/* ------------------------ GET NOTE ------------------------ */
$note_id = intval($_POST['note_id'] ?? 0);
log_sql("REQUEST: user_id=" . current_user_id() . " wants to delete note_id=" . $note_id);

if ($note_id > 0) {

    $stmt = $pdo->prepare('SELECT utilisateur_id FROM commentaires WHERE id = ? AND est_notification = 1');
    $stmt->execute([$note_id]);
    $note = $stmt->fetch();

    if (!$note) {
        log_sql("FAILED: note_id=$note_id does not exist or est_notification != 1");
    } else {
        $owner = $note['utilisateur_id'];
        $current = current_user_id();
        $isAdmin = is_admin();

        log_sql("FOUND: note_id=$note_id owner_id=$owner | current_user=$current | admin=$isAdmin");

        if ($isAdmin || $owner == $current) {
            $delete = $pdo->prepare('DELETE FROM commentaires WHERE id = ?');
            $delete->execute([$note_id]);
            log_sql("SUCCESS: deleted note_id=$note_id");
        } else {
            log_sql("REFUSED: unauthorized delete attempt by user_id=$current on note_id=$note_id");
        }
    }
}

/* ------------------------ REDIRECT ------------------------ */
$prev = $_SERVER['HTTP_REFERER'] ?? 'index.php';
log_sql("REDIRECT: returning to $prev");

header('Location: ' . $prev);
exit;
