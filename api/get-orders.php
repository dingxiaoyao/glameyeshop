<?php
require_once __DIR__ . '/config.php';

try {
    $db = getDb();
    $stmt = $db->query('SELECT id, product_name, quantity, amount, email, phone, payment_method, status, created_at FROM orders ORDER BY created_at DESC LIMIT 20');
    $orders = $stmt->fetchAll();
    sendJson(['orders' => $orders]);
} catch (PDOException $exception) {
    sendJson(['error' => $exception->getMessage()], 500);
}
