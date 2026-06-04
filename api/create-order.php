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

// Test-account handling: if logged-in user is flagged as test, mark this order as test
// and route through a no-op confirmation instead of real Stripe/PayPal.
// P0#5: test 账号无论选什么 payment_method,都强制走 test 通道,不创建 Stripe Session
$isTest = 0;
if ($user && !empty($user['is_test_account'])) {
    $isTest = 1;
}

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
    // P0#1: 生成一次性 checkout_token(15min,防 Stripe checkout 入口 IDOR)
    // 仅非测试订单需要 checkout_token(测试订单直接进 confirmation 页,不经 Stripe)
    $checkoutToken = $isTest ? null : bin2hex(random_bytes(24));  // 48 hex chars

    // P0#1 (v2): 生成永久 lookup_token 让客户后续查订单 — 替代用 email 查询的 IDOR 模式
    $lookupToken = bin2hex(random_bytes(24));  // 48 hex chars

    // P0#5: 强制覆盖 payment_method 为 'test' 以正确反映"未走真实网关"
    $effectivePaymentMethod = $isTest ? 'test' : $paymentMethod;

    $db->beginTransaction();

    // P0#6: 锁定所有相关产品行(SELECT … FOR UPDATE)防并发超卖
    // 两单同时抢最后一件时,后到的事务会等前一个 commit/rollback 后再读到真实 stock
    $productIds = array_unique(array_map(fn($it) => (int)$it['product_id'], $resolvedItems));
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $lockStmt = $db->prepare(
        "SELECT id, stock, name FROM products WHERE id IN ($placeholders) FOR UPDATE"
    );
    $lockStmt->execute($productIds);
    $lockedRows = [];
    foreach ($lockStmt->fetchAll() as $row) {
        $lockedRows[(int)$row['id']] = $row;
    }

    // 锁后用真实 stock 重新检查 — 跟事务外早期检查重复,但这一次是"权威"的
    // 累加同一 product_id 的 quantity(虽然前端不会重复,但防御性兜底)
    $neededByProduct = [];
    foreach ($resolvedItems as $it) {
        $pid = (int)$it['product_id'];
        $neededByProduct[$pid] = ($neededByProduct[$pid] ?? 0) + (int)$it['quantity'];
    }
    foreach ($neededByProduct as $pid => $needed) {
        $row = $lockedRows[$pid] ?? null;
        $available = $row ? (int)$row['stock'] : 0;
        if (!$row || $needed > $available) {
            $db->rollBack();
            $name = $row['name'] ?? ('product#' . $pid);
            sendJson([
                'error'     => "Insufficient stock for $name (needed: $needed, available: $available)",
                'sku'       => null,
                'requested' => $needed,
                'available' => $available,
            ], 409);
        }
    }

    $stmt = $db->prepare(
        'INSERT INTO orders
           (user_id, customer_name, email, phone, address_line, address_line2,
            city, state, postal_code, country,
            product_name, quantity, subtotal, shipping, tax, amount, currency,
            payment_method, status, notes, is_test, checkout_token,
            checkout_token_expires_at, lookup_token, created_at)
         VALUES
           (:user_id, :customer_name, :email, :phone, :address_line, :address_line2,
            :city, :state, :postal_code, :country,
            :product_name, :quantity, :subtotal, :shipping, :tax, :amount, :currency,
            :payment_method, :status, :notes, :is_test, :checkout_token,
            :token_exp, :lookup_token, NOW())'
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
        ':payment_method' => $effectivePaymentMethod,
        ':status'         => $isTest ? 'paid' : 'pending',  // test orders auto-marked paid
        ':notes'          => $isTest ? trim($notes . " [TEST ORDER — no real charge]") : $notes,
        ':is_test'        => $isTest,
        ':checkout_token' => $checkoutToken,
        ':token_exp'      => $isTest ? null : date('Y-m-d H:i:s', time() + 900), // 15min
        ':lookup_token'   => $lookupToken,
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
        'is_test'  => $isTest,
        'lookup_token' => $lookupToken,  // 客户用此 token 后续查订单状态(替代 email)
        // Test orders skip payment entirely and go straight to confirmation;
        // 真订单的 next URL 带 checkout_token,后端校验 token 防 IDOR(P0#1)
        'next'     => $isTest
            ? "/order-confirmation.html?order_id=$orderId&test=1&lt=$lookupToken"
            : ($paymentMethod === 'stripe'
                ? "/api/stripe-checkout.php?order_id=$orderId&t=$checkoutToken"
                : "/api/paypal-checkout.php?order_id=$orderId&t=$checkoutToken"),
    ]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    sendError('Failed to create order', 500, $e);
}
