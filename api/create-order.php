<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$productName = $_POST['product_name'] ?? 'GLAMEYE 眼镜';
$quantity = max(1, intval($_POST['quantity'] ?? 1));
$amount = floatval($_POST['amount'] ?? 0);
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone = trim($_POST['phone'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? 'stripe';

if (!$email || !$phone || $amount <= 0) {
    sendJson(['error' => 'Invalid order data'], 422);
}

try {
    $db = getDb();
    $stmt = $db->prepare('INSERT INTO orders (product_name, quantity, amount, email, phone, payment_method, status, created_at) VALUES (:product_name, :quantity, :amount, :email, :phone, :payment_method, :status, NOW())');
    $stmt->execute([
        ':product_name' => $productName,
        ':quantity' => $quantity,
        ':amount' => $amount,
        ':email' => $email,
        ':phone' => $phone,
        ':payment_method' => $paymentMethod,
        ':status' => 'pending',
    ]);
    $orderId = $db->lastInsertId();
    sendJson(['success' => true, 'order_id' => $orderId]);
} catch (PDOException $exception) {
    sendJson(['error' => $exception->getMessage()], 500);
}
