<?php
// 公开物流追踪查询：order_id + email 验证
require_once __DIR__ . '/config.php';

$orderId = intval($_GET['order_id'] ?? 0);
$email   = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);

if ($orderId <= 0 || !$email) sendJson(['error' => 'order_id and email required'], 422);

try {
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, customer_name, status, carrier, tracking_number, tracking_url,
                shipped_at, delivered_at, estimated_delivery, created_at
         FROM orders WHERE id = :id AND email = :email LIMIT 1'
    );
    $stmt->execute([':id' => $orderId, ':email' => $email]);
    $order = $stmt->fetch();
    if (!$order) sendJson(['error' => 'Order not found'], 404);

    $stmt = $db->prepare(
        'SELECT status, description, location, occurred_at
         FROM order_tracking_events WHERE order_id = :id ORDER BY occurred_at DESC LIMIT 50'
    );
    $stmt->execute([':id' => $orderId]);
    sendJson([
        'order' => $order,
        'events' => $stmt->fetchAll(),
    ]);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
