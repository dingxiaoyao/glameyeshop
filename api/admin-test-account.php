<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$userId = intval($body['user_id'] ?? 0);
$flag   = !empty($body['is_test_account']) ? 1 : 0;

if ($userId <= 0) sendError('Invalid user_id', 400);

try {
    $db = getDb();
    $stmt = $db->prepare('UPDATE users SET is_test_account = :f WHERE id = :id');
    $stmt->execute([':f' => $flag, ':id' => $userId]);
    sendJson(['success' => true, 'user_id' => $userId, 'is_test_account' => $flag]);
} catch (PDOException $e) {
    sendError('Update failed', 500, $e);
}
