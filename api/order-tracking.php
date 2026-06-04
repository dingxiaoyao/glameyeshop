<?php
// 公开物流追踪查询 — P0#1 安全升级跟 order-status.php 一致
//   - 推荐 order_id + token (?token=48hex / ?lt=…)
//   - 兼容 order_id + email — 加限频
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

    if ($token !== '') {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            sendJson(['error' => 'Order not found'], 404);
        }
        $stmt = $db->prepare(
            'SELECT id, customer_name, status, carrier, tracking_number, tracking_url,
                    shipped_at, delivered_at, estimated_delivery, created_at
             FROM orders WHERE id = :id AND lookup_token = :token LIMIT 1'
        );
        $stmt->execute([':id' => $orderId, ':token' => $token]);
        $order = $stmt->fetch();
    } else {
        $ip = rateLimitClientIp();
        $bucket = "order-tracking:$ip:" . mb_substr($email, 0, 80);
        rateLimitGuard($bucket, 5, 900, 'Too many tracking lookups. Please wait 15 minutes or use the tracking link from your shipping email.');

        $stmt = $db->prepare(
            'SELECT id, customer_name, status, carrier, tracking_number, tracking_url,
                    shipped_at, delivered_at, estimated_delivery, created_at
             FROM orders WHERE id = :id AND email = :email LIMIT 1'
        );
        $stmt->execute([':id' => $orderId, ':email' => $email]);
        $order = $stmt->fetch();
        if (!$order) rateLimitFail($bucket);
    }

    if (!$order) {
        sendJson(['error' => 'Order not found'], 404);
    }

    $stmt = $db->prepare(
        'SELECT status, description, location, occurred_at
         FROM order_tracking_events WHERE order_id = :id ORDER BY occurred_at DESC LIMIT 50'
    );
    $stmt->execute([':id' => $orderId]);
    sendJson([
        'order'  => $order,
        'events' => $stmt->fetchAll(),
    ]);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
