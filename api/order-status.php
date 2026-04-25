<?php
require_once __DIR__ . '/config.php';

// 公开接口：用 order_id + email 校验，返回订单状态
$orderId = intval($_GET['order_id'] ?? 0);
$email   = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);

if ($orderId <= 0 || !$email) {
    sendJson(['error' => 'order_id and email required'], 422);
}

try {
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, customer_name, product_name, quantity, amount, payment_method, status, created_at
         FROM orders WHERE id = :id AND email = :email LIMIT 1'
    );
    $stmt->execute([':id' => $orderId, ':email' => $email]);
    $order = $stmt->fetch();
    if (!$order) sendJson(['error' => 'Order not found'], 404);

    $itemStmt = $db->prepare('SELECT product_name, quantity, line_total FROM order_items WHERE order_id = :id');
    $itemStmt->execute([':id' => $orderId]);
    $order['items'] = $itemStmt->fetchAll();

    sendJson(['order' => $order]);
} catch (PDOException $e) {
    sendError('Failed to fetch order', 500, $e);
}
