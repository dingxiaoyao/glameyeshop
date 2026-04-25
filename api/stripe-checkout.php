<?php
require_once __DIR__ . '/config.php';

// ============================================================
// Stripe Checkout 跳转入口（占位实现）
// 真实集成需安装 Stripe PHP SDK：composer require stripe/stripe-php
// 然后用 \Stripe\Checkout\Session::create([...]) 创建支付会话
// 此处先返回一个能跑通流程的占位页面，标识订单状态
// ============================================================

$orderId = intval($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order_id');
}

try {
    $db = getDb();
    $stmt = $db->prepare('SELECT id, customer_name, amount, payment_method FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) { http_response_code(404); exit('Order not found'); }

    // TODO: 此处调用 Stripe API 创建 checkout session
    // 例：
    //   \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
    //   $session = \Stripe\Checkout\Session::create([...]);
    //   header('Location: ' . $session->url); exit;

    // 占位：渲染一个仿支付页（仅开发演示用）
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN"><head>
    <meta charset="UTF-8"><title>Stripe 支付（占位）- 订单 <?= $orderId ?></title>
    <link rel="stylesheet" href="/css/styles.css">
    </head><body>
    <main class="container" style="max-width:600px; padding:3rem 1rem;">
      <h1>💳 Stripe 支付（开发占位）</h1>
      <p>订单 #<?= $orderId ?> · 金额 ¥<?= htmlspecialchars((string)$order['amount']) ?></p>
      <p style="color:#666; margin:1rem 0;">
        ⚠️ 这是占位支付页。要启用真实 Stripe 支付，请在 <code>api/stripe-checkout.php</code> 接入 Stripe SDK。
      </p>
      <a href="/order-success.html?order_id=<?= $orderId ?>" class="button button-primary" style="margin-top:1rem;">
        模拟支付成功 →
      </a>
      <a href="/" class="button button-outline" style="margin-left:1rem;">返回首页</a>
    </main>
    </body></html>
    <?php
} catch (PDOException $e) {
    sendError('Stripe checkout failed', 500, $e);
}
