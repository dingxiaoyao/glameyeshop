<?php
// ============================================================
// Admin Stripe refund — POST { order_id, amount_cents?, reason? }
// 全额退款省略 amount_cents,部分退款传 cents 数字
// 调 Stripe Refunds API,webhook 收到 charge.refunded 后会更新 orders.status
// 但我们在响应里也立即把订单状态先改成 refunded / partial_refund (UX)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment-config.php';
requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$in = readInput();
$orderId    = intval($in['order_id'] ?? 0);
$amountCent = isset($in['amount_cents']) ? intval($in['amount_cents']) : 0;
$reason     = trim((string)($in['reason'] ?? ''));

if ($orderId <= 0) sendJson(['error' => 'Invalid order_id'], 422);

$secretKey = getPaymentConfig('stripe_secret_key');
if ($secretKey === '') {
    sendJson(['error' => 'Stripe not configured. Set the secret key in Settings before refunding.'], 503);
}

try {
    $db = getDb();
    $stmt = $db->prepare('SELECT id, payment_session_id, payment_method, amount, status, customer_name, email FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) sendJson(['error' => 'Order not found'], 404);

    if ($order['payment_method'] !== 'stripe' || !$order['payment_session_id']) {
        sendJson(['error' => 'This order was not paid via Stripe (or has no session id). Refund manually.'], 422);
    }
    if (in_array($order['status'], ['refunded', 'cancelled'], true)) {
        sendJson(['error' => 'Order is already ' . $order['status']], 409);
    }

    // 1) 拿 Checkout Session 找 payment_intent
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($order['payment_session_id']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secretKey],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $session = json_decode($resp, true);

    if ($httpCode !== 200 || empty($session['payment_intent'])) {
        sendJson(['error' => 'Could not look up Stripe session: ' . ($session['error']['message'] ?? 'unknown')], 502);
    }
    $paymentIntentId = $session['payment_intent'];

    // 2) 创建 Refund
    $refundParams = [
        'payment_intent'           => $paymentIntentId,
        'metadata[order_id]'       => (string)$orderId,
        'metadata[refunded_by]'    => 'admin:' . ($_SERVER['PHP_AUTH_USER'] ?? 'unknown'),
    ];
    if ($amountCent > 0) {
        $refundParams['amount'] = $amountCent;
    }
    if ($reason !== '') {
        // Stripe reason 只接受 duplicate / fraudulent / requested_by_customer
        // 自由文本放进 metadata
        $refundParams['reason'] = 'requested_by_customer';
        $refundParams['metadata[reason_note]'] = mb_substr($reason, 0, 500);
    }

    $ch = curl_init('https://api.stripe.com/v1/refunds');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $secretKey,
            'Stripe-Version: 2024-06-20',
        ],
        CURLOPT_POSTFIELDS     => http_build_query($refundParams),
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $refund = json_decode($resp, true);

    if ($httpCode !== 200 || empty($refund['id'])) {
        $err = $refund['error']['message'] ?? ('Stripe responded ' . $httpCode);
        error_log("[admin-refund] Order #$orderId Stripe error: $err / raw: $resp");
        sendJson(['error' => "Stripe refund failed: $err"], 502);
    }

    // 3) 立即标订单状态(webhook charge.refunded 也会处理,但 admin UI 立即看到)
    $orderAmountCents = (int) round(floatval($order['amount']) * 100);
    $isFullyRefunded = ($amountCent === 0) || (intval($refund['amount']) >= $orderAmountCents);
    $newStatus = $isFullyRefunded ? 'refunded' : 'partial_refund';

    $db->prepare('UPDATE orders SET status = :st, updated_at = NOW() WHERE id = :id')
       ->execute([':st' => $newStatus, ':id' => $orderId]);
    $db->prepare(
        "INSERT INTO order_tracking_events (order_id, status, description, created_at)
         VALUES (:oid, :st, :desc, NOW())"
    )->execute([
        ':oid'  => $orderId,
        ':st'   => $newStatus,
        ':desc' => sprintf('Refunded $%.2f via admin%s%s',
            intval($refund['amount']) / 100,
            $reason !== '' ? ' — ' . mb_substr($reason, 0, 200) : '',
            ' (Stripe refund ' . $refund['id'] . ')'
        ),
    ]);

    sendJson([
        'success'        => true,
        'refund_id'      => $refund['id'],
        'amount_refunded'=> intval($refund['amount']) / 100,
        'new_status'     => $newStatus,
    ]);
} catch (PDOException $e) {
    sendError('Failed to issue refund', 500, $e);
}
