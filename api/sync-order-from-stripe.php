<?php
// ============================================================
// 兜底:订单状态从 Stripe 主动拉同步
// 用于 webhook 没到达 / 失败的情况(网络抖动、whsec 错、nginx 拦截等)
// GET ?order_id=X&token=lookup_token(48 hex)
// 后端查 orders.payment_session_id,调 Stripe GET sessions/{id} 拿真实状态:
//   - Stripe paid + 金额一致     → 同步 update orders.status='paid' + tracking event
//   - Stripe paid 但金额不一致   → orders.status='payment_amount_mismatch'(等人工核)
//   - Stripe expired              → orders.status='expired'
//   - Stripe 还在 open / pending  → 不动,返回当前状态
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment-config.php';
require_once __DIR__ . '/lib/rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$orderId = intval($_GET['order_id'] ?? 0);
$token   = trim((string)($_GET['token'] ?? $_GET['lt'] ?? ''));

if ($orderId <= 0)                              sendJson(['error' => 'order_id required'], 422);
if (!preg_match('/^[a-f0-9]{48}$/', $token))    sendJson(['error' => 'invalid token'], 403);

// 限频:同 IP 30 次/小时(防被刷)
$ip = rateLimitClientIp();
rateLimitGuard("sync-order:$ip", 30, 3600, 'Too many sync attempts');

try {
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, status, amount, payment_session_id, payment_method, lookup_token
         FROM orders WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) sendJson(['error' => 'order not found'], 404);

    if (!hash_equals((string)$order['lookup_token'], $token)) {
        sendJson(['error' => 'token mismatch'], 403);
    }

    // 已经是 paid/processing/shipped/delivered 等终态 → no-op,直接返回
    if (in_array($order['status'], ['paid', 'processing', 'shipped', 'delivered', 'refunded', 'partial_refund'], true)) {
        sendJson(['status' => $order['status'], 'already_synced' => true]);
    }

    if ($order['payment_method'] !== 'stripe' || !$order['payment_session_id']) {
        sendJson(['status' => $order['status'], 'reason' => 'Not a Stripe order or no session id']);
    }

    $secretKey = getPaymentConfig('stripe_secret_key');
    if ($secretKey === '') {
        sendJson(['status' => $order['status'], 'reason' => 'Stripe not configured'], 503);
    }

    // 调 Stripe 拿真实 session 状态
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($order['payment_session_id']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secretKey],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $err = json_decode($resp, true);
        error_log("[sync-order] Stripe API error: " . ($err['error']['message'] ?? "HTTP $httpCode"));
        sendJson(['status' => $order['status'], 'error' => 'Could not reach Stripe'], 502);
    }

    $session = json_decode($resp, true);
    $paymentStatus = $session['payment_status'] ?? '';
    $stripeAmount  = intval($session['amount_total'] ?? 0) / 100.0;
    $orderAmount   = floatval($order['amount']);

    // Stripe paid → 同步
    if ($paymentStatus === 'paid') {
        if (abs($orderAmount - $stripeAmount) > 0.01) {
            // 金额不一致 → 标 mismatch 不标 paid
            $db->prepare(
                "UPDATE orders SET status='payment_amount_mismatch', updated_at=NOW()
                  WHERE id=:id AND status IN ('pending','expired')"
            )->execute([':id' => $orderId]);
            error_log("[sync-order] #$orderId amount mismatch: order=$orderAmount stripe=$stripeAmount");
            sendJson(['status' => 'payment_amount_mismatch', 'synced' => true, 'note' => 'Stripe says paid but amount mismatch — admin will review']);
        }

        $upd = $db->prepare(
            "UPDATE orders SET status='paid', updated_at=NOW()
              WHERE id=:id AND status IN ('pending', 'expired')"
        );
        $upd->execute([':id' => $orderId]);

        if ($upd->rowCount() > 0) {
            // 记 tracking event(stripe_event_id 用 session_id 防 webhook 重复时再次插入)
            $eventKey = 'sync:' . $order['payment_session_id'];
            $db->prepare(
                "INSERT IGNORE INTO order_tracking_events
                    (order_id, status, description, stripe_event_id, created_at)
                 VALUES (:oid, 'paid', :desc, :eid, NOW())"
            )->execute([
                ':oid'  => $orderId,
                ':desc' => sprintf('Payment confirmed via Stripe sync (USD %.2f) — webhook was missed or delayed', $stripeAmount),
                ':eid'  => $eventKey,
            ]);
            error_log("[sync-order] #$orderId synced pending → paid via Stripe API");
        }
        sendJson(['status' => 'paid', 'synced' => true]);
    }

    // Stripe expired → 同步
    if ($session['status'] === 'expired') {
        $db->prepare(
            "UPDATE orders SET status='expired', updated_at=NOW()
              WHERE id=:id AND status='pending'"
        )->execute([':id' => $orderId]);
        sendJson(['status' => 'expired', 'synced' => true]);
    }

    // 其他情况(open / unpaid) → 不动,返回 Stripe 的状态供前端轮询
    sendJson([
        'status'         => $order['status'],
        'stripe_status'  => $session['status'] ?? null,
        'payment_status' => $paymentStatus,
        'note'           => 'Payment not completed yet on Stripe',
    ]);
} catch (PDOException $e) {
    sendError('Failed to sync', 500, $e);
}
