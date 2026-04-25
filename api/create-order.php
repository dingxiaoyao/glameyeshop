<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

// 1. 输入解析
$customerName = trim($_POST['customer_name'] ?? '');
$productName = trim($_POST['product_name'] ?? '');
$quantity = intval($_POST['quantity'] ?? 1);
$email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone = trim($_POST['phone'] ?? '');
$paymentMethod = $_POST['payment_method'] ?? 'stripe';

// 2. 严格校验
if (!$customerName || mb_strlen($customerName) > 100) {
    sendJson(['error' => 'Invalid customer name'], 422);
}
if (!$email) {
    sendJson(['error' => 'Invalid email'], 422);
}
if (!$phone || !preg_match('/^[0-9\-\+\s\(\)]{5,32}$/', $phone)) {
    sendJson(['error' => 'Invalid phone'], 422);
}
if (!isset(PRODUCT_CATALOG[$productName])) {
    sendJson(['error' => 'Unknown product'], 422);
}
if ($quantity < 1 || $quantity > MAX_QUANTITY_PER_ORDER) {
    sendJson(['error' => 'Invalid quantity'], 422);
}
if (!in_array($paymentMethod, ALLOWED_PAYMENT_METHODS, true)) {
    sendJson(['error' => 'Invalid payment method'], 422);
}

// 3. 服务端权威计算金额（无视客户端 amount，防篡改）
$unitPrice = PRODUCT_CATALOG[$productName];
$amount = round($unitPrice * $quantity, 2);

// 4. 写入数据库
try {
    $db = getDb();
    $stmt = $db->prepare(
        'INSERT INTO orders
            (customer_name, product_name, quantity, amount, email, phone, payment_method, status, created_at)
         VALUES
            (:customer_name, :product_name, :quantity, :amount, :email, :phone, :payment_method, :status, NOW())'
    );
    $stmt->execute([
        ':customer_name' => $customerName,
        ':product_name' => $productName,
        ':quantity' => $quantity,
        ':amount' => $amount,
        ':email' => $email,
        ':phone' => $phone,
        ':payment_method' => $paymentMethod,
        ':status' => 'pending',
    ]);
    $orderId = $db->lastInsertId();
    sendJson([
        'success' => true,
        'order_id' => $orderId,
        'amount' => $amount,
    ]);
} catch (PDOException $exception) {
    sendError('Failed to create order', 500, $exception);
}
