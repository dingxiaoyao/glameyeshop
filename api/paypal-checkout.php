<?php
require_once __DIR__ . '/config.php';

// PayPal Checkout 占位（同 stripe-checkout.php 的模式）
$orderId = intval($_GET['order_id'] ?? 0);
if ($orderId <= 0) { http_response_code(400); exit('Invalid order_id'); }

try {
    $db = getDb();
    $stmt = $db->prepare('SELECT id, amount FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) { http_response_code(404); exit('Order not found'); }

    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN"><head>
    <meta charset="UTF-8"><title>PayPal 支付（占位）- 订单 <?= $orderId ?></title>
    <link rel="stylesheet" href="/css/styles.css">
    </head><body>
    <main class="container" style="max-width:600px; padding:3rem 1rem;">
      <h1>🅿️ PayPal 支付（开发占位）</h1>
      <p>订单 #<?= $orderId ?> · 金额 ¥<?= htmlspecialchars((string)$order['amount']) ?></p>
      <p style="color:#666; margin:1rem 0;">
        ⚠️ 这是占位支付页。要启用真实 PayPal 支付，请在 <code>api/paypal-checkout.php</code> 接入 PayPal Server SDK。
      </p>
      <a href="/order-success.html?order_id=<?= $orderId ?>" class="button button-primary">模拟支付成功 →</a>
      <a href="/" class="button button-outline" style="margin-left:1rem;">返回首页</a>
    </main>
    </body></html>
    <?php
} catch (PDOException $e) {
    sendError('PayPal checkout failed', 500, $e);
}
