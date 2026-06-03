<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment-config.php';

// ============================================================
// Stripe Checkout 跳转入口
// 流程:create-order.php 创建订单(status=pending) → 跳转到这里 →
//      调 Stripe API 创建 Checkout Session → redirect 用户到 session.url →
//      用户在 Stripe 托管页面填卡 → 付款成功后 Stripe POST 到 stripe-webhook.php →
//      webhook 把订单 status pending → paid,跳 order-success.html
// 不依赖 Stripe SDK / composer,直接用 PHP curl 调 REST API,部署零依赖。
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$orderId = intval($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order_id');
}

try {
    // 检查 Stripe 是否已配置
    $secretKey = getPaymentConfig('stripe_secret_key');
    if ($secretKey === '') {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Stripe Not Configured</title></head><body style="font-family:system-ui;padding:3rem;max-width:600px;margin:auto;">';
        echo '<h1>Stripe not configured</h1>';
        echo '<p>Admin must set the Stripe secret key in <a href="/admin/settings.php">Settings</a> before payment can be processed.</p>';
        echo '<p><a href="/cart.html">← Back to cart</a></p>';
        echo '</body></html>';
        exit;
    }

    // 验证 secret key 跟当前模式一致(test 模式必须用 sk_test_,live 模式必须用 sk_live_)
    $mode = stripeMode(); // test or live
    $expectedPrefix = ($mode === 'live') ? 'sk_live_' : 'sk_test_';
    if (strpos($secretKey, $expectedPrefix) !== 0) {
        error_log("[STRIPE] Secret key mode mismatch: expected $expectedPrefix prefix for mode=$mode");
        http_response_code(503);
        exit('Stripe secret key does not match the configured mode (test/live). Admin must update Settings.');
    }

    $db = getDb();

    // 读订单
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        http_response_code(404);
        exit('Order not found');
    }

    // 已支付 / 已发货的订单不应再走 checkout
    if (in_array($order['status'], ['paid', 'processing', 'shipped', 'delivered'], true)) {
        header('Location: /order-success.html?order_id=' . $orderId);
        exit;
    }

    // 读订单 items(明细 → Stripe line items)
    $itemsStmt = $db->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
    $itemsStmt->execute([':oid' => $orderId]);
    $items = $itemsStmt->fetchAll();
    if (!$items) {
        http_response_code(400);
        exit('Order has no items');
    }

    // 构造 Stripe Checkout Session 参数(form-urlencoded 嵌套数组语法)
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'glameyeshop.com';
    $base   = $scheme . '://' . $host;

    $params = [
        'mode'                 => 'payment',
        'success_url'          => $base . '/order-success.html?order_id=' . $orderId . '&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'           => $base . '/checkout.html?cancelled=1&order_id=' . $orderId,
        'customer_email'       => $order['email'],
        'client_reference_id'  => (string)$orderId,
        'metadata[order_id]'   => (string)$orderId,
        'payment_intent_data[metadata][order_id]' => (string)$orderId,
        // 允许收集 shipping address(虽然下单时已经填了,Stripe 这边作 secondary verify)
        // 'shipping_address_collection[allowed_countries][]' => 'US',
    ];

    // ----- 商品行 -----
    $i = 0;
    foreach ($items as $it) {
        $unitAmountCents = (int) round(floatval($it['unit_price']) * 100);
        if ($unitAmountCents <= 0) continue;
        $params["line_items[$i][price_data][currency]"]                = 'usd';
        $params["line_items[$i][price_data][product_data][name]"]      = mb_substr($it['product_name'], 0, 250);
        $params["line_items[$i][price_data][product_data][metadata][sku]"] = $it['sku'] ?? '';
        $params["line_items[$i][price_data][unit_amount]"]             = $unitAmountCents;
        $params["line_items[$i][quantity]"]                            = max(1, intval($it['quantity']));
        $i++;
    }

    // ----- 运费(作为单独 line item,Stripe 上没专门的 shipping field) -----
    $shipping = floatval($order['shipping'] ?? 0);
    if ($shipping > 0) {
        $params["line_items[$i][price_data][currency]"]           = 'usd';
        $params["line_items[$i][price_data][product_data][name]"] = 'Shipping';
        $params["line_items[$i][price_data][unit_amount]"]        = (int) round($shipping * 100);
        $params["line_items[$i][quantity]"]                       = 1;
        $i++;
    }

    if ($i === 0) {
        http_response_code(400);
        exit('No valid line items to charge');
    }

    // ----- 调用 Stripe API -----
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $secretKey,
            'Stripe-Version: 2024-06-20',
        ],
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        error_log('[STRIPE] curl error: ' . $curlErr);
        http_response_code(502);
        exit('Could not reach Stripe API. ' . htmlspecialchars($curlErr));
    }

    $session = json_decode($resp, true);

    if ($httpCode !== 200 || empty($session['url']) || empty($session['id'])) {
        $errMsg = $session['error']['message'] ?? ('Stripe responded ' . $httpCode);
        error_log('[STRIPE] Checkout creation failed (' . $httpCode . '): ' . $resp);
        http_response_code(502);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Error</title></head><body style="font-family:system-ui;padding:3rem;max-width:600px;margin:auto;">';
        echo '<h1>Could not start payment</h1>';
        echo '<p>Stripe rejected the checkout request: <code>' . htmlspecialchars($errMsg) . '</code></p>';
        echo '<p>Order #' . $orderId . ' was saved. You can return to <a href="/checkout.html?order_id=' . $orderId . '">your cart</a> and try again.</p>';
        echo '<p style="color:#999;font-size:.85rem;">If this keeps happening, please email us at <a href="mailto:support@glameyeshop.com">support@glameyeshop.com</a> with order #' . $orderId . '.</p>';
        echo '</body></html>';
        exit;
    }

    // ----- 保存 Stripe session.id 到订单(webhook 用它定位订单) -----
    $db->prepare('UPDATE orders SET payment_session_id = :sid, updated_at = NOW() WHERE id = :id')
       ->execute([':sid' => $session['id'], ':id' => $orderId]);

    // ----- 跳转到 Stripe 托管支付页 -----
    header('Location: ' . $session['url']);
    exit;
} catch (PDOException $e) {
    error_log('[STRIPE] DB error in stripe-checkout: ' . $e->getMessage());
    http_response_code(500);
    exit('Database error during checkout');
} catch (Throwable $e) {
    error_log('[STRIPE] Unexpected error in stripe-checkout: ' . $e->getMessage());
    http_response_code(500);
    exit('Unexpected error during checkout');
}
