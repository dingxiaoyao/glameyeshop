<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$input = readInput();

// 1. 顾客信息
$customerName = trim((string)($input['customer_name'] ?? ''));
$email        = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone        = trim((string)($input['phone'] ?? ''));

// 2. 收货地址
$addressLine  = trim((string)($input['address'] ?? $input['address_line'] ?? ''));
$city         = trim((string)($input['city'] ?? ''));
$postalCode   = trim((string)($input['postal_code'] ?? ''));
$country      = trim((string)($input['country'] ?? 'CN'));

// 3. 商品（兼容两种输入：items 数组 或 单品 product_name+quantity）
$items = [];
if (isset($input['items']) && is_array($input['items'])) {
    foreach ($input['items'] as $it) {
        $items[] = [
            'product_name' => trim((string)($it['product_name'] ?? $it['name'] ?? '')),
            'quantity'     => intval($it['quantity'] ?? 1),
        ];
    }
} else {
    $items[] = [
        'product_name' => trim((string)($input['product_name'] ?? '')),
        'quantity'     => intval($input['quantity'] ?? 1),
    ];
}

$paymentMethod = $input['payment_method'] ?? 'stripe';
$notes         = trim((string)($input['notes'] ?? ''));

// ===== 校验 =====
if (!$customerName || mb_strlen($customerName) > 100) sendJson(['error' => 'Invalid customer name'], 422);
if (!$email)                                          sendJson(['error' => 'Invalid email'], 422);
if (!$phone || !preg_match('/^[0-9\-\+\s\(\)]{5,32}$/', $phone)) sendJson(['error' => 'Invalid phone'], 422);
if (!$addressLine || mb_strlen($addressLine) > 255)   sendJson(['error' => 'Invalid address'], 422);
if (!$city || mb_strlen($city) > 100)                 sendJson(['error' => 'Invalid city'], 422);
if (mb_strlen($postalCode) > 32)                      sendJson(['error' => 'Invalid postal code'], 422);
if (!in_array($paymentMethod, ALLOWED_PAYMENT_METHODS, true)) sendJson(['error' => 'Invalid payment method'], 422);
if (count($items) === 0 || count($items) > MAX_ITEMS_PER_ORDER) sendJson(['error' => 'Invalid items count'], 422);

// 服务端校验每个商品 + 计算金额
$totalAmount = 0.0;
foreach ($items as $idx => &$it) {
    if (!isset(PRODUCT_CATALOG[$it['product_name']])) {
        sendJson(['error' => "Unknown product: {$it['product_name']}"], 422);
    }
    if ($it['quantity'] < 1 || $it['quantity'] > MAX_QUANTITY_PER_ITEM) {
        sendJson(['error' => 'Invalid quantity'], 422);
    }
    $it['unit_price'] = PRODUCT_CATALOG[$it['product_name']];
    $it['line_total'] = round($it['unit_price'] * $it['quantity'], 2);
    $totalAmount += $it['line_total'];
}
unset($it);
$totalAmount = round($totalAmount, 2);

$firstItem = $items[0];

// ===== 写入数据库 =====
try {
    $db = getDb();
    $db->beginTransaction();

    $stmt = $db->prepare(
        'INSERT INTO orders
           (customer_name, email, phone, address_line, city, postal_code, country,
            product_name, quantity, amount, payment_method, status, notes, created_at)
         VALUES
           (:customer_name, :email, :phone, :address_line, :city, :postal_code, :country,
            :product_name, :quantity, :amount, :payment_method, :status, :notes, NOW())'
    );
    $stmt->execute([
        ':customer_name'   => $customerName,
        ':email'           => $email,
        ':phone'           => $phone,
        ':address_line'    => $addressLine,
        ':city'            => $city,
        ':postal_code'     => $postalCode,
        ':country'         => $country,
        ':product_name'    => $firstItem['product_name'],
        ':quantity'        => $firstItem['quantity'],
        ':amount'          => $totalAmount,
        ':payment_method'  => $paymentMethod,
        ':status'          => 'pending',
        ':notes'           => $notes,
    ]);
    $orderId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare(
        'INSERT INTO order_items (order_id, product_name, unit_price, quantity, line_total)
         VALUES (:order_id, :product_name, :unit_price, :quantity, :line_total)'
    );
    foreach ($items as $it) {
        $itemStmt->execute([
            ':order_id'     => $orderId,
            ':product_name' => $it['product_name'],
            ':unit_price'   => $it['unit_price'],
            ':quantity'     => $it['quantity'],
            ':line_total'   => $it['line_total'],
        ]);
    }

    $db->commit();

    sendJson([
        'success'  => true,
        'order_id' => $orderId,
        'amount'   => $totalAmount,
        'currency' => 'CNY',
        'next'     => $paymentMethod === 'stripe'
            ? "/api/stripe-checkout.php?order_id=$orderId"
            : "/api/paypal-checkout.php?order_id=$orderId",
    ]);
} catch (PDOException $exception) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    sendError('Failed to create order', 500, $exception);
}
