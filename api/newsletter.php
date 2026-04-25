<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(['error' => 'Method not allowed'], 405);

$in = readInput();
$email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) sendJson(['error' => 'Invalid email'], 422);

try {
    $db = getDb();
    $stmt = $db->prepare("INSERT IGNORE INTO newsletter_subscribers (email, source) VALUES (:e, 'newsletter_form')");
    $stmt->execute([':e' => $email]);
    sendJson(['success' => true]);
} catch (PDOException $e) {
    sendError('Subscribe failed', 500, $e);
}
