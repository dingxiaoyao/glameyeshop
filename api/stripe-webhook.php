<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment-config.php';

// ============================================================
// Stripe Webhook 接收端点
// 必须验证 Stripe 签名(Stripe-Signature 头),否则可被伪造
// 文档:https://stripe.com/docs/webhooks/signatures
//
// 处理事件:
//   checkout.session.completed              → 订单 status pending → paid + 金额校验
//   checkout.session.async_payment_succeeded → 异步支付到账(银行转账等)
//   checkout.session.async_payment_failed    → 异步支付失败 → 还库存
//   checkout.session.expired                 → 用户没付款关闭页 → 还库存
//   charge.refunded                          → 退款 → 还库存
//
// 安全/正确性升级(P0/P1):
//   - event_id 幂等:同 event 重发不会重复 update + 不会插多条 tracking event
//   - 金额校验:amount_total 必须跟 orders.amount 一致才标 paid
//   - 库存释放:expired / failed / refunded 状态时反扣 order_items
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

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

function parseStripeSignatureHeader(string $header): array
{
    $items = ['t' => null, 'signatures' => []];
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) !== 2) continue;
        [$key, $value] = $kv;
        if ($key === 't')       $items['t'] = $value;
        elseif ($key === 'v1')  $items['signatures'][] = $value;
    }
    return $items;
}

$parsed = parseStripeSignatureHeader($sigHeader);
if (!$parsed['t'] || empty($parsed['signatures'])) {
    sendJson(['error' => 'Malformed signature'], 400);
}

// 5 分钟容差防重放
if (abs(time() - intval($parsed['t'])) > 300) {
    sendJson(['error' => 'Signature timestamp out of tolerance'], 400);
}

$signedPayload = $parsed['t'] . '.' . $payload;
$expected = hash_hmac('sha256', $signedPayload, $webhookSecret);

$valid = false;
foreach ($parsed['signatures'] as $candidate) {
    if (hash_equals($expected, $candidate)) { $valid = true; break; }
}
if (!$valid) {
    error_log('[STRIPE WEBHOOK] Invalid signature');
    sendJson(['error' => 'Invalid signature'], 400);
}

// ----- 签名通过,解析事件 -----
$event = json_decode($payload, true);
if (!is_array($event) || !isset($event['type'], $event['id'])) {
    sendJson(['error' => 'Invalid event payload'], 400);
}

$eventId = $event['id'];
$type    = $event['type'];
$obj     = $event['data']['object'] ?? [];

try {
    $db = getDb();

    // ============================================================
    // P0#2: event_id 幂等 — 重复事件直接返回 200(已处理)
    // ============================================================
    $resolveOrderIdInline = function(array $obj) use ($db): int {
        $oid = intval($obj['metadata']['order_id'] ?? 0);
        if ($oid > 0) return $oid;
        $cri = intval($obj['client_reference_id'] ?? 0);
        if ($cri > 0) return $cri;
        $sid = $obj['id'] ?? '';
        if ($sid) {
            $s = $db->prepare('SELECT id FROM orders WHERE payment_session_id = :sid LIMIT 1');
            $s->execute([':sid' => $sid]);
            $row = $s->fetch();
            if ($row) return intval($row['id']);
        }
        return 0;
    };

    $orderId = $resolveOrderIdInline($obj);

    $insEvt = $db->prepare(
        "INSERT IGNORE INTO stripe_webhook_events (event_id, type, order_id, raw_payload, received_at)
         VALUES (:eid, :type, :oid, :raw, NOW())"
    );
    $insEvt->execute([
        ':eid'  => $eventId,
        ':type' => $type,
        ':oid'  => $orderId > 0 ? $orderId : null,
        ':raw'  => mb_substr($payload, 0, 60000),
    ]);
    if ($insEvt->rowCount() === 0) {
        error_log("[STRIPE WEBHOOK] Duplicate event $eventId (type=$type) — already processed, skipping");
        sendJson(['received' => true, 'duplicate' => true, 'event_id' => $eventId]);
    }

    // ============================================================
    // 共享辅助:JOIN order_items 反扣库存 — 用于 expired/failed/refunded
    // ============================================================
    $restoreStock = function(int $orderId) use ($db): int {
        $items = $db->prepare(
            'SELECT product_id, quantity FROM order_items WHERE order_id = :oid'
        );
        $items->execute([':oid' => $orderId]);
        $rows = $items->fetchAll();
        $upd = $db->prepare(
            'UPDATE products SET stock = stock + :q WHERE id = :id'
        );
        $restored = 0;
        foreach ($rows as $r) {
            $upd->execute([':q' => intval($r['quantity']), ':id' => intval($r['product_id'])]);
            $restored += intval($r['quantity']);
        }
        return $restored;
    };

    // 记录处理结果(便于 admin 后台审计)
    $markProcessed = function(string $status, ?string $error = null) use ($db, $eventId, $orderId) {
        $db->prepare(
            "UPDATE stripe_webhook_events
                SET status = :st, order_id = COALESCE(order_id, :oid), error = :err, processed_at = NOW()
              WHERE event_id = :eid"
        )->execute([
            ':st'  => $status,
            ':oid' => $orderId > 0 ? $orderId : null,
            ':err' => $error,
            ':eid' => $eventId,
        ]);
    };

    // ============================================================
    // 事件分发
    // ============================================================
    if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
        $sessionId     = $obj['id'] ?? '';
        $paymentStatus = $obj['payment_status'] ?? '';      // paid / unpaid / no_payment_required
        $amountTotal   = intval($obj['amount_total'] ?? 0); // cents
        $currency      = strtoupper($obj['currency'] ?? 'USD');

        if ($orderId <= 0 || !$sessionId || $paymentStatus !== 'paid') {
            $markProcessed('skipped', "order=$orderId session=$sessionId payment_status=$paymentStatus");
            error_log("[STRIPE WEBHOOK] $type skipped (order=$orderId, payment_status=$paymentStatus)");
            sendJson(['received' => true]);
        }

        // P0#4: 金额校验 — Stripe 返回的 amount_total(cents)必须等于 orders.amount(USD)
        $o = $db->prepare('SELECT amount FROM orders WHERE id = :id LIMIT 1');
        $o->execute([':id' => $orderId]);
        $orderAmount = floatval($o->fetchColumn());
        $stripeAmount = $amountTotal / 100.0;
        if (abs($orderAmount - $stripeAmount) > 0.01) {
            // 金额对不上 — 不标 paid,记 amount_mismatch 等待人工核对
            $db->prepare(
                "UPDATE orders SET status='payment_amount_mismatch', updated_at=NOW()
                  WHERE id=:id AND status IN ('pending','expired')"
            )->execute([':id' => $orderId]);

            $errMsg = sprintf('Amount mismatch: order=%.2f stripe=%.2f', $orderAmount, $stripeAmount);
            $markProcessed('amount_mismatch', $errMsg);
            error_log("[STRIPE WEBHOOK] Order #$orderId payment_amount_mismatch: $errMsg");
            sendJson(['received' => true, 'flagged' => 'amount_mismatch']);
        }

        // 金额一致 → 标 paid(幂等:不会覆盖 paid/shipped/delivered)
        $upd = $db->prepare(
            "UPDATE orders SET status='paid', payment_session_id=:sid, updated_at=NOW()
              WHERE id=:id AND status IN ('pending', 'expired')"
        );
        $upd->execute([':sid' => $sessionId, ':id' => $orderId]);

        if ($upd->rowCount() > 0) {
            // 记 tracking_event,用 stripe_event_id 作 unique 防重复
            $db->prepare(
                "INSERT IGNORE INTO order_tracking_events
                    (order_id, status, description, stripe_event_id, created_at)
                 VALUES (:oid, 'paid', :desc, :eid, NOW())"
            )->execute([
                ':oid'  => $orderId,
                ':desc' => sprintf('Payment received via Stripe (%s %.2f)', $currency, $stripeAmount),
                ':eid'  => $eventId,
            ]);
            error_log("[STRIPE WEBHOOK] Order #$orderId marked paid (session $sessionId)");
        } else {
            error_log("[STRIPE WEBHOOK] Order #$orderId already advanced, no UPDATE applied");
        }

        $markProcessed('processed');
    } elseif ($type === 'checkout.session.expired') {
        // 用户开 Stripe 页 24h 没付款 — 还库存,标 expired
        if ($orderId > 0) {
            $upd = $db->prepare(
                "UPDATE orders SET status='expired', updated_at=NOW()
                  WHERE id=:id AND status='pending'"
            );
            $upd->execute([':id' => $orderId]);

            if ($upd->rowCount() > 0) {
                $qty = $restoreStock($orderId);
                $db->prepare(
                    "INSERT IGNORE INTO order_tracking_events
                        (order_id, status, description, stripe_event_id, created_at)
                     VALUES (:oid, 'expired', :desc, :eid, NOW())"
                )->execute([
                    ':oid'  => $orderId,
                    ':desc' => "Checkout session expired (24h unpaid). Restored $qty units to stock.",
                    ':eid'  => $eventId,
                ]);
                error_log("[STRIPE WEBHOOK] Order #$orderId expired, $qty units stock restored");
            }
        }
        $markProcessed('processed');
    } elseif ($type === 'checkout.session.async_payment_failed') {
        if ($orderId > 0) {
            $upd = $db->prepare(
                "UPDATE orders SET status='payment_failed', updated_at=NOW()
                  WHERE id=:id AND status IN ('pending', 'processing')"
            );
            $upd->execute([':id' => $orderId]);
            if ($upd->rowCount() > 0) {
                $qty = $restoreStock($orderId);
                $db->prepare(
                    "INSERT IGNORE INTO order_tracking_events
                        (order_id, status, description, stripe_event_id, created_at)
                     VALUES (:oid, 'payment_failed', :desc, :eid, NOW())"
                )->execute([
                    ':oid'  => $orderId,
                    ':desc' => "Async payment failed. Restored $qty units to stock.",
                    ':eid'  => $eventId,
                ]);
                error_log("[STRIPE WEBHOOK] Order #$orderId payment_failed, $qty units stock restored");
            }
        }
        $markProcessed('processed');
    } elseif ($type === 'charge.refunded') {
        // charge.refunded 的 obj 是 Charge,不是 Session。order_id 来自 payment_intent.metadata
        $refundOrderId = intval($obj['metadata']['order_id'] ?? 0);
        if ($refundOrderId > 0) {
            $isFullyRefunded = !empty($obj['refunded']);
            $amountRefunded  = intval($obj['amount_refunded'] ?? 0) / 100.0;
            $newStatus = $isFullyRefunded ? 'refunded' : 'partial_refund';

            // 全额退款 → 还库存。部分退款不动库存(运营人为处理)
            $stockNote = '';
            if ($isFullyRefunded) {
                // 只在订单还没被标 refunded 的情况下还库存(P0#2 防多次 charge.refunded 重复)
                $check = $db->prepare("SELECT status FROM orders WHERE id = :id LIMIT 1");
                $check->execute([':id' => $refundOrderId]);
                $currentStatus = $check->fetchColumn();
                if ($currentStatus !== 'refunded') {
                    $qty = $restoreStock($refundOrderId);
                    $stockNote = " · Restored $qty units to stock";
                }
            }

            $db->prepare(
                "UPDATE orders SET status=:st, updated_at=NOW() WHERE id=:id"
            )->execute([':st' => $newStatus, ':id' => $refundOrderId]);

            $db->prepare(
                "INSERT IGNORE INTO order_tracking_events
                    (order_id, status, description, stripe_event_id, created_at)
                 VALUES (:oid, :st, :desc, :eid, NOW())"
            )->execute([
                ':oid'  => $refundOrderId,
                ':st'   => $newStatus,
                ':desc' => sprintf('Refunded $%.2f via Stripe%s', $amountRefunded, $stockNote),
                ':eid'  => $eventId,
            ]);
            error_log("[STRIPE WEBHOOK] Order #$refundOrderId $newStatus, refunded \$$amountRefunded$stockNote");
            $orderId = $refundOrderId;  // 给 markProcessed 用
        }
        $markProcessed('processed');
    } else {
        // 未处理事件类型(账号、subscription 等),记下但不报错
        $markProcessed('ignored');
        error_log("[STRIPE WEBHOOK] unhandled event type: $type ($eventId)");
    }

    sendJson(['received' => true, 'event_id' => $eventId, 'type' => $type]);
} catch (Throwable $e) {
    error_log('[STRIPE WEBHOOK] Processing error: ' . $e->getMessage());
    // 尝试记录错误状态(忽略二次失败)
    try {
        $db->prepare(
            "UPDATE stripe_webhook_events SET status='error', error=:err, processed_at=NOW()
              WHERE event_id=:eid"
        )->execute([':err' => $e->getMessage(), ':eid' => $eventId]);
    } catch (Throwable $ignore) {}
    // 返回 500 让 Stripe 重试
    sendError('Webhook processing error', 500, $e);
}
