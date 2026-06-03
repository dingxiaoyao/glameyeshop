<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment-config.php';

// ============================================================
// Stripe Webhook 接收端点
// 必须验证 Stripe 签名(Stripe-Signature 头),否则可被伪造
// 文档:https://stripe.com/docs/webhooks/signatures
//
// 处理事件:
//   checkout.session.completed              → 订单 status pending → paid
//   checkout.session.async_payment_succeeded → 异步支付到账(银行转账等)
//   checkout.session.async_payment_failed    → 异步支付失败
//   checkout.session.expired                 → 用户没付款关闭页 → 订单 status pending → expired
//   charge.refunded                          → 退款 → 订单 status → refunded
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

// Webhook secret 从 DB 读(via payment-config.php),跟 Stripe Dashboard 那个 whsec_xxx 保持一致
$webhookSecret = getPaymentConfig('stripe_webhook_secret');
if ($webhookSecret === '') {
    error_log('[STRIPE WEBHOOK] webhook secret not configured in Settings');
    sendJson(['error' => 'Webhook not configured'], 503);
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === false || $sigHeader === '') {
    sendJson(['error' => 'Missing payload or signature'], 400);
}

/**
 * 解析 Stripe-Signature 头:
 *   t=1614265330,v1=hex_signature,v1=hex_signature
 */
function parseStripeSignatureHeader(string $header): array
{
    $items = ['t' => null, 'signatures' => []];
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) !== 2) {
            continue;
        }
        [$key, $value] = $kv;
        if ($key === 't') {
            $items['t'] = $value;
        } elseif ($key === 'v1') {
            $items['signatures'][] = $value;
        }
    }
    return $items;
}

$parsed = parseStripeSignatureHeader($sigHeader);
if (!$parsed['t'] || empty($parsed['signatures'])) {
    sendJson(['error' => 'Malformed signature'], 400);
}

// 5 分钟容差,防止重放攻击
$tolerance = 300;
if (abs(time() - intval($parsed['t'])) > $tolerance) {
    sendJson(['error' => 'Signature timestamp out of tolerance'], 400);
}

$signedPayload = $parsed['t'] . '.' . $payload;
$expected = hash_hmac('sha256', $signedPayload, $webhookSecret);

$valid = false;
foreach ($parsed['signatures'] as $candidate) {
    if (hash_equals($expected, $candidate)) {
        $valid = true;
        break;
    }
}

if (!$valid) {
    error_log('[STRIPE WEBHOOK] Invalid signature for event payload');
    sendJson(['error' => 'Invalid signature'], 400);
}

// ----- 签名通过,解析事件 -----
$event = json_decode($payload, true);
if (!is_array($event) || !isset($event['type'])) {
    sendJson(['error' => 'Invalid event payload'], 400);
}

$type = $event['type'];
$obj  = $event['data']['object'] ?? [];
$eventId = $event['id'] ?? '';

try {
    $db = getDb();

    // 共享辅助:根据 session.id 或 metadata.order_id 找订单
    $resolveOrderId = function(array $obj) use ($db): int {
        // 1) metadata.order_id
        $oid = intval($obj['metadata']['order_id'] ?? 0);
        if ($oid > 0) return $oid;
        // 2) client_reference_id
        $cri = intval($obj['client_reference_id'] ?? 0);
        if ($cri > 0) return $cri;
        // 3) 反查 payment_session_id
        $sid = $obj['id'] ?? '';
        if ($sid) {
            $s = $db->prepare('SELECT id FROM orders WHERE payment_session_id = :sid LIMIT 1');
            $s->execute([':sid' => $sid]);
            $row = $s->fetch();
            if ($row) return intval($row['id']);
        }
        return 0;
    };

    if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
        // 已完成的 checkout session
        $orderId       = $resolveOrderId($obj);
        $sessionId     = $obj['id'] ?? '';
        $paymentStatus = $obj['payment_status'] ?? '';     // paid / unpaid / no_payment_required
        $amountTotal   = intval($obj['amount_total'] ?? 0); // cents
        $currency      = strtoupper($obj['currency'] ?? 'USD');

        if ($orderId > 0 && $sessionId && $paymentStatus === 'paid') {
            // 幂等:已经是 paid/processing/shipped/delivered 的不再覆盖
            $upd = $db->prepare(
                "UPDATE orders SET status='paid', payment_session_id=:sid, updated_at=NOW()
                 WHERE id=:id AND status IN ('pending', 'expired')"
            );
            $upd->execute([':sid' => $sessionId, ':id' => $orderId]);
            $affected = $upd->rowCount();

            if ($affected > 0) {
                // 记 tracking event(订单时间线显示"Payment received")
                $evt = $db->prepare(
                    "INSERT INTO order_tracking_events (order_id, status, description, created_at)
                     VALUES (:oid, 'paid', :desc, NOW())"
                );
                $evt->execute([
                    ':oid'  => $orderId,
                    ':desc' => sprintf('Payment received via Stripe (%s %.2f)', $currency, $amountTotal / 100),
                ]);
                error_log("[STRIPE WEBHOOK] Order #$orderId marked paid (session $sessionId)");
            } else {
                error_log("[STRIPE WEBHOOK] Order #$orderId already in advanced state, no update applied");
            }
        } else {
            error_log("[STRIPE WEBHOOK] checkout.session.completed received but order/payment not resolved: order_id=$orderId, status=$paymentStatus");
        }
    } elseif ($type === 'checkout.session.expired') {
        // 用户开了 Stripe 页但没付款关闭(24h 后过期)
        $orderId   = $resolveOrderId($obj);
        $sessionId = $obj['id'] ?? '';
        if ($orderId > 0 && $sessionId) {
            $db->prepare(
                "UPDATE orders SET status='expired', updated_at=NOW()
                 WHERE id=:id AND status='pending'"
            )->execute([':id' => $orderId]);
            error_log("[STRIPE WEBHOOK] Order #$orderId expired");
        }
    } elseif ($type === 'checkout.session.async_payment_failed') {
        $orderId = $resolveOrderId($obj);
        if ($orderId > 0) {
            $db->prepare(
                "UPDATE orders SET status='payment_failed', updated_at=NOW()
                 WHERE id=:id AND status IN ('pending', 'processing')"
            )->execute([':id' => $orderId]);
            error_log("[STRIPE WEBHOOK] Order #$orderId payment failed");
        }
    } elseif ($type === 'charge.refunded') {
        // 退款 — Stripe charge.refunded 事件里 obj 是 Charge,不是 Session,要从 payment_intent 反查
        // 简化处理:metadata.order_id 仍可用(我们在 stripe-checkout 已经把 order_id 塞到 payment_intent.metadata)
        $orderId = intval($obj['metadata']['order_id'] ?? 0);
        if ($orderId > 0) {
            $isFullyRefunded = !empty($obj['refunded']);
            $newStatus = $isFullyRefunded ? 'refunded' : 'partial_refund';
            $db->prepare(
                "UPDATE orders SET status=:st, updated_at=NOW() WHERE id=:id"
            )->execute([':st' => $newStatus, ':id' => $orderId]);

            $db->prepare(
                "INSERT INTO order_tracking_events (order_id, status, description, created_at)
                 VALUES (:oid, :st, :desc, NOW())"
            )->execute([
                ':oid'  => $orderId,
                ':st'   => $newStatus,
                ':desc' => $isFullyRefunded ? 'Order fully refunded via Stripe' : 'Partial refund issued via Stripe',
            ]);
            error_log("[STRIPE WEBHOOK] Order #$orderId marked $newStatus");
        }
    } else {
        // 未处理事件类型,记录但不报错
        error_log("[STRIPE WEBHOOK] unhandled event type: $type (event_id=$eventId)");
    }

    sendJson(['received' => true, 'event_id' => $eventId, 'type' => $type]);
} catch (Throwable $e) {
    error_log('[STRIPE WEBHOOK] Processing error: ' . $e->getMessage());
    // 返回 500 让 Stripe 重试(最多 3 天指数退避)
    sendError('Webhook processing error', 500, $e);
}
