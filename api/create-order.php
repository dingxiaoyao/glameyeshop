<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$user = currentUser();   // 可能为 null（guest checkout）
$input = readInput();

// 1. Customer info
$customerName = trim((string)($input['customer_name'] ?? ''));
$email        = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone        = trim((string)($input['phone'] ?? ''));

// 2. Shipping address
$addressLine  = trim((string)($input['address'] ?? $input['address_line'] ?? ''));
$addressLine2 = trim((string)($input['address_line2'] ?? ''));
$city         = trim((string)($input['city'] ?? ''));
$state        = trim((string)($input['state'] ?? ''));
$postalCode   = trim((string)($input['postal_code'] ?? ''));
$country      = trim((string)($input['country'] ?? 'US'));

// 3. Items
$items = [];
if (isset($input['items']) && is_array($input['items'])) {
    foreach ($input['items'] as $it) {
        $items[] = [
            'product_name' => trim((string)($it['product_name'] ?? $it['name'] ?? '')),
            'sku'          => trim((string)($it['sku'] ?? '')),
            'quantity'     => max(1, intval($it['quantity'] ?? 1)),
        ];
    }
} else {
    $items[] = [
        'product_name' => trim((string)($input['product_name'] ?? '')),
        'sku'          => trim((string)($input['sku'] ?? '')),
        'quantity'     => max(1, intval($input['quantity'] ?? 1)),
    ];
}

$paymentMethod = $input['payment_method'] ?? 'stripe';
$notes         = trim((string)($input['notes'] ?? ''));

// ====== Validation ======
if (!$customerName || mb_strlen($customerName) > 200) sendJson(['error' => 'Invalid customer name'], 422);
if (!$email)                                          sendJson(['error' => 'Invalid email'], 422);
if (!$phone || !preg_match('/^[0-9\-\+\s\(\)]{5,32}$/', $phone)) sendJson(['error' => 'Invalid phone'], 422);
if (!$addressLine || mb_strlen($addressLine) > 255)   sendJson(['error' => 'Invalid address'], 422);
if (!$city)                                           sendJson(['error' => 'City required'], 422);
if (!$state || mb_strlen($state) > 64)                sendJson(['error' => 'State required'], 422);
if (!$postalCode || !preg_match('/^[A-Za-z0-9\- ]{3,12}$/', $postalCode)) sendJson(['error' => 'Invalid ZIP/postal code'], 422);
if (!in_array($paymentMethod, ALLOWED_PAYMENT_METHODS, true)) sendJson(['error' => 'Invalid payment method'], 422);
if (count($items) === 0 || count($items) > MAX_ITEMS_PER_ORDER) sendJson(['error' => 'Invalid items count'], 422);

// 服务端权威查产品价格 + 校验库存
$resolvedItems = [];
$subtotal = 0.0;
$db = getDb();
foreach ($items as $it) {
    $prod = null;
    if (!empty($it['sku'])) {
        $prod = getProductBySku($it['sku']);
    }
    if (!$prod && !empty($it['product_name'])) {
        $prod = getProductByName($it['product_name']);
    }
    if (!$prod) {
        sendJson(['error' => 'Unknown product: ' . ($it['product_name'] ?: $it['sku'])], 422);
    }
    if ($it['quantity'] > MAX_QUANTITY_PER_ITEM) sendJson(['error' => 'Quantity exceeds limit'], 422);
    if ($it['quantity'] > $prod['stock']) sendJson(['error' => "Insufficient stock for {$prod['name']}"], 422);

    $line = round($prod['price'] * $it['quantity'], 2);
    $resolvedItems[] = [
        'product_id'   => (int)$prod['id'],
        'product_name' => $prod['name'],
        'sku'          => $prod['sku'],
        'unit_price'   => (float)$prod['price'],
        'quantity'     => $it['quantity'],
        'line_total'   => $line,
    ];
    $subtotal += $line;
}
$subtotal = round($subtotal, 2);

// 简化运费：subtotal >= $50 免邮，否则 $5.99
$shipping = $subtotal >= 50 ? 0.00 : 5.99;
// 税费暂为 0（下一步可接 TaxJar 等）
$tax = 0.00;
$total = round($subtotal + $shipping + $tax, 2);

try {
    $db->beginTransaction();
    $stmt = $db->prepare(
        'INSERT INTO orders
           (user_id, customer_name, email, phone, address_line, address_line2,
            city, state, postal_code, country,
            product_name, quantity, subtotal, shipping, tax, amount, currency,
            payment_method, status, notes, created_at)
         VALUES
           (:user_id, :customer_name, :email, :phone, :address_line, :address_line2,
            :city, :state, :postal_code, :country,
            :product_name, :quantity, :subtotal, :shipping, :tax, :amount, :currency,
            :payment_method, :status, :notes, NOW())'
    );
    $first = $resolvedItems[0];
    $stmt->execute([
        ':user_id'        => $user['id'] ?? null,
        ':customer_name'  => $customerName,
        ':email'          => $email,
        ':phone'          => $phone,
        ':address_line'   => $addressLine,
        ':address_line2'  => $addressLine2,
        ':city'           => $city,
        ':state'          => $state,
        ':postal_code'    => $postalCode,
        ':country'        => $country,
        ':product_name'   => $first['product_name'],
        ':quantity'       => $first['quantity'],
        ':subtotal'       => $subtotal,
        ':shipping'       => $shipping,
        ':tax'            => $tax,
        ':amount'         => $total,
        ':currency'       => 'USD',
        ':payment_method' => $paymentMethod,
        ':status'         => 'pending',
        ':notes'          => $notes,
    ]);
    $orderId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, sku, unit_price, quantity, line_total)
         VALUES (:order_id, :product_id, :product_name, :sku, :unit_price, :quantity, :line_total)'
    );
    foreach ($resolvedItems as $it) {
        $itemStmt->execute([
            ':order_id'     => $orderId,
            ':product_id'   => $it['product_id'],
            ':product_name' => $it['product_name'],
            ':sku'          => $it['sku'],
            ':unit_price'   => $it['unit_price'],
            ':quantity'     => $it['quantity'],
            ':line_total'   => $it['line_total'],
        ]);
    }

    // 扣减库存
    $stockStmt = $db->prepare('UPDATE products SET stock = GREATEST(0, stock - :q) WHERE id = :id');
    foreach ($resolvedItems as $it) {
        $stockStmt->execute([':q' => $it['quantity'], ':id' => $it['product_id']]);
    }

    $db->commit();

    sendJson([
        'success'  => true,
        'order_id' => $orderId,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'tax'      => $tax,
        'amount'   => $total,
        'currency' => 'USD',
        'next'     => $paymentMethod === 'stripe'
            ? "/api/stripe-checkout.php?order_id=$orderId"
            : "/api/paypal-checkout.php?order_id=$orderId",
    ]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    sendError('Failed to create order', 500, $e);
}
