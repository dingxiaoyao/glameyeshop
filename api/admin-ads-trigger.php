<?php
// 服务器端 fire GA4 purchase 事件 — 绕过浏览器拦截
// 用 GA4 Measurement Protocol: POST https://www.google-analytics.com/mp/collect
// 优点:不依赖浏览器,广告拦截 / 隐私模式 / Tag Assistant 都不影响
// 需要 GA4 API Secret(从 GA4 后台 → Data Stream → Measurement Protocol API secrets 创建)

require_once __DIR__ . '/config.php';
requireAdminAuth();

set_exception_handler(function ($e) {
    error_log('[ads-trigger] ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => $e->getMessage()]);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'POST required'], 405);
}

$in = readInput();
$txnId = trim((string)($in['transaction_id'] ?? ('TEST_' . time())));
$value = floatval($in['value'] ?? 99);
$sku   = trim((string)($in['sku'] ?? 'GE-CK-NATURAL'));
$name  = trim((string)($in['name'] ?? 'Test Item — ' . $sku));

$db = getDb();
$cfg = [];
$stmt = $db->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN
    ('ga_measurement_id','ga4_api_secret')");
$stmt->execute();
foreach ($stmt->fetchAll() as $r) $cfg[$r['key']] = $r['value'];

$gaId  = $cfg['ga_measurement_id'] ?? '';
$apiKey = $cfg['ga4_api_secret'] ?? '';

if (!$gaId || !$apiKey) {
    sendJson([
        'error' => 'Missing GA4 config',
        'hint'  => 'admin/settings.php → 填 GA4 Measurement ID + GA4 API Secret(从 GA4 → 数据流 → Measurement Protocol API secrets 创建)',
        'has_ga_id'     => !!$gaId,
        'has_api_secret'=> !!$apiKey,
    ], 422);
}

// 生成稳定的 client_id(同一个 admin 复用,看起来像同一用户)
$clientId = (string)(crc32($_SERVER['REMOTE_ADDR'] ?? 'admin') . '.' . substr(md5($gaId), 0, 8));

// 构造 GA4 purchase 事件
$payload = [
    'client_id' => $clientId,
    'events' => [[
        'name' => 'purchase',
        'params' => [
            'transaction_id' => $txnId,
            'value'    => $value,
            'currency' => 'USD',
            'tax'      => 0,
            'shipping' => 0,
            'items' => [[
                'item_id'   => $sku,
                'item_name' => $name,
                'price'     => $value,
                'quantity'  => 1,
            ]],
            // 强制 debug mode:让事件立刻出现在 GA4 DebugView
            'debug_mode' => '1',
            // 标记测试事件,让 ML 不算入 attribution
            'engagement_time_msec' => '1',
        ],
    ]],
];

$url = "https://www.google-analytics.com/mp/collect?measurement_id="
     . urlencode($gaId) . "&api_secret=" . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

// GA4 Measurement Protocol 成功是 204 No Content
$success = ($code === 204);

// 同时打 debug endpoint 拿验证反馈(/mp/collect 不给反馈,/debug/mp/collect 给)
$debugUrl = str_replace('/mp/collect', '/debug/mp/collect', $url);
$ch2 = curl_init($debugUrl);
curl_setopt_array($ch2, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$debugResp = curl_exec($ch2);
$debugCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$debugData = $debugResp ? json_decode($debugResp, true) : null;

sendJson([
    'success' => $success,
    'http_code' => $code,
    'ga_id' => $gaId,
    'client_id' => $clientId,
    'transaction_id' => $txnId,
    'value' => $value,
    'sku' => $sku,
    'curl_error' => $err ?: null,
    'debug' => [
        'http_code' => $debugCode,
        'validation' => $debugData,
        'note' => $debugCode === 200 && empty($debugData['validationMessages'])
            ? '✓ Payload 通过 GA4 验证,事件已成功发送给 GA4'
            : '⚠ 见 validationMessages 看 GA4 报的具体问题',
    ],
    'next_steps' => [
        'GA4 Realtime' => 'analytics.google.com → 选 ' . $gaId . ' → Reports → Realtime → 5-30 秒后看到 +1 user + purchase 事件',
        'GA4 DebugView' => 'analytics.google.com → 管理 → DebugView → 立刻看到详细事件(因为带了 debug_mode=1)',
        'Google Ads' => '等 3-12 小时,转化操作状态变 "正在记录转化"',
    ],
]);
