<?php
require_once __DIR__ . '/config.php';

// 仅管理员可访问
requireAdminAuth();

try {
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, customer_name, product_name, quantity, amount, email, phone, payment_method, status, created_at
         FROM orders
         ORDER BY created_at DESC
         LIMIT 50'
    );
    $stmt->execute();
    $orders = $stmt->fetchAll();
    sendJson(['orders' => $orders]);
} catch (PDOException $exception) {
    sendError('Failed to fetch orders', 500, $exception);
}
