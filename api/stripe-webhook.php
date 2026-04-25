<?php
require_once __DIR__ . '/config.php';

// ============================================================
// Stripe Webhook 接收端点
// 必须验证 Stripe 签名（Stripe-Signature 头），否则可被伪造
// 文档：https://stripe.com/docs/webhooks/signatures
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
if ($webhookSecret === '') {
    sendError('Webhook not configured', 503);
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === false || $sigHeader === '') {
    sendJson(['error' => 'Missing payload or signature'], 400);
}

/**
 * 解析 Stripe-Signature 头：
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

// 5 分钟容差，防止重放攻击
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
    sendJson(['error' => 'Invalid signature'], 400);
}

// 签名通过，可解析事件
$event = json_decode($payload, true);
if (!is_array($event) || !isset($event['type'])) {
    sendJson(['error' => 'Invalid event payload'], 400);
}

// TODO: 根据 $event['type'] 更新订单状态（例如 checkout.session.completed）
// 此处仅返回成功，避免 Stripe 重试
error_log('[GLAMEYE] Stripe webhook received: ' . $event['type']);

sendJson(['received' => true]);
