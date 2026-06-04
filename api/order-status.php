<?php
// 公开订单状态查询
// P0#1 安全升级:
//   - 推荐用 order_id + token(?token=48hex)— 永久 lookup_token,无法字典爆破
//   - 兼容旧 order_id + email(?email=…)— 加 per-IP+per-email 限频 5/15min
//   - 所有失败统一返回 404 防订单存在性枚举
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/rate-limit.php';

$orderId = intval($_GET['order_id'] ?? 0);
$token   = trim((string)($_GET['token'] ?? $_GET['lt'] ?? ''));
$email   = filter_var($_GET['email'] ?? '', FILTER_VALIDATE_EMAIL);

if ($orderId <= 0 || (!$token && !$email)) {
    sendJson(['error' => 'order_id and (token or email) required'], 422);
}

try {
    $db = getDb();

    // 路径 A:token-based(推荐)— 无需限频,token 自身就是 anti-enumeration
    if ($token !== '') {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            sendJson(['error' => 'Order not found'], 404);  // 统一 404 防枚举
        }
        $stmt = $db->prepare(
            'SELECT id, customer_name, product_name, quantity, amount, currency, payment_method, status, created_at
             FROM orders WHERE id = :id AND lookup_token = :token LIMIT 1'
        );
        $stmt->execute([':id' => $orderId, ':token' => $token]);
        $order = $stmt->fetch();
    } else {
        // 路径 B:email-based(兼容旧链接)— 加限频
        $ip = rateLimitClientIp();
        $bucket = "order-lookup:$ip:" . mb_substr($email, 0, 80);
        rateLimitGuard($bucket, 5, 900, 'Too many lookup attempts. Please wait 15 minutes or use the order link from your confirmation email.');

        $stmt = $db->prepare(
            'SELECT id, customer_name, product_name, quantity, amount, currency, payment_method, status, created_at
             FROM orders WHERE id = :id AND email = :email LIMIT 1'
        );
        $stmt->execute([':id' => $orderId, ':email' => $email]);
        $order = $stmt->fetch();

        if (!$order) {
            rateLimitFail($bucket);  // 记一次失败,5 次进 429
        }
    }

    if (!$order) {
        sendJson(['error' => 'Order not found'], 404);
    }

    $itemStmt = $db->prepare('SELECT product_name, quantity, line_total FROM order_items WHERE order_id = :id');
    $itemStmt->execute([':id' => $orderId]);
    $order['items'] = $itemStmt->fetchAll();

    sendJson(['order' => $order]);
} catch (PDOException $e) {
    sendError('Failed to fetch order', 500, $e);
}
