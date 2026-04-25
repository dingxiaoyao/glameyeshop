<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$input = readInput();
$orderId = intval($input['order_id'] ?? 0);
$status  = (string)($input['status'] ?? '');

if ($orderId <= 0)                                       sendJson(['error' => 'Invalid order_id'], 422);
if (!in_array($status, ALLOWED_ORDER_STATUSES, true))    sendJson(['error' => 'Invalid status'], 422);

try {
    $db = getDb();
    $stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $orderId]);
    if ($stmt->rowCount() === 0) {
        sendJson(['error' => 'Order not found'], 404);
    }
    sendJson(['success' => true, 'order_id' => $orderId, 'status' => $status]);
} catch (PDOException $e) {
    sendError('Failed to update order', 500, $e);
}
