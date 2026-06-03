<?php
// Admin only: 验证当前配置的 Stripe secret key 是否可用
// 调 Stripe /v1/balance(读账户余额是最轻的 secret key 验证端点)
// 200 = key 有效;401 = key 无效;其他 = 网络/配置错
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/payment-config.php';
requireAdminAuth();

$secretKey = getPaymentConfig('stripe_secret_key');
$mode      = stripeMode();

if ($secretKey === '') {
    sendJson([
        'ok'      => false,
        'message' => 'No secret key configured. Paste your sk_test_… or sk_live_… in the Stripe section and save first.',
        'mode'    => $mode,
    ]);
}

// 模式 vs key 前缀
$expectedPrefix = ($mode === 'live') ? 'sk_live_' : 'sk_test_';
if (strpos($secretKey, $expectedPrefix) !== 0) {
    sendJson([
        'ok'      => false,
        'message' => "Mode is '$mode' but secret key prefix is wrong (expected $expectedPrefix…). Update either the mode or the key.",
        'mode'    => $mode,
    ]);
}

// 调 Stripe /v1/balance
$ch = curl_init('https://api.stripe.com/v1/balance');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $secretKey,
        'Stripe-Version: 2024-06-20',
    ],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($resp === false) {
    sendJson([
        'ok'      => false,
        'message' => 'Could not reach api.stripe.com: ' . $curlErr,
        'mode'    => $mode,
    ]);
}

$data = json_decode($resp, true);

if ($httpCode === 200 && isset($data['object']) && $data['object'] === 'balance') {
    // 计算可用余额(美分 → USD)
    $available = 0;
    foreach (($data['available'] ?? []) as $b) {
        if (($b['currency'] ?? '') === 'usd') {
            $available = ($b['amount'] ?? 0) / 100;
            break;
        }
    }
    $pending = 0;
    foreach (($data['pending'] ?? []) as $b) {
        if (($b['currency'] ?? '') === 'usd') {
            $pending = ($b['amount'] ?? 0) / 100;
            break;
        }
    }

    // 同时验证 webhook secret 是否填了(不能远程验证,只能本地存在性检查)
    $whsec = getPaymentConfig('stripe_webhook_secret');
    $hasWebhookSecret = ($whsec !== '');

    sendJson([
        'ok'              => true,
        'mode'            => $mode,
        'livemode'        => !empty($data['livemode']),
        'available_usd'   => $available,
        'pending_usd'     => $pending,
        'webhook_secret_present' => $hasWebhookSecret,
        'webhook_secret_warning' => $hasWebhookSecret ? null
            : 'No webhook secret set — orders will be created but won\'t auto-update to paid.',
        'message'         => sprintf(
            'Stripe %s mode connected · USD available $%.2f · pending $%.2f%s',
            $mode,
            $available,
            $pending,
            $hasWebhookSecret ? '' : ' · ⚠ webhook secret missing'
        ),
    ]);
}

if ($httpCode === 401) {
    sendJson([
        'ok'      => false,
        'mode'    => $mode,
        'message' => 'Stripe rejected the secret key (HTTP 401). Double-check you copied the full key including the sk_test_/sk_live_ prefix.',
    ]);
}

// 其他错误(403 / 5xx 等)
$errMsg = $data['error']['message'] ?? "Stripe returned HTTP $httpCode";
sendJson([
    'ok'      => false,
    'mode'    => $mode,
    'message' => $errMsg,
    'http'    => $httpCode,
]);
