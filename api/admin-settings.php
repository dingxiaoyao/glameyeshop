<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

// P1#23: admin 看自己的配置但 secret 不返回明文 — admin 账号被攻破 = 一锅端
// 改成只返回 has_value 布尔(前端 placeholder 显示 "●●● configured")
// POST 时空 value 视为"不修改",避免每次保存把 secret 清空
const SECRET_KEYS = [
    'stripe_secret_key', 'stripe_webhook_secret',
    'paypal_secret',
    'google_client_secret', 'tiktok_client_secret',
    'resend_api_key',
    'smtp_pass',
];

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDb();

    if ($method === 'GET') {
        $stmt = $db->query("SELECT `key`, `value`, updated_at FROM site_settings ORDER BY `key`");
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            if (in_array($r['key'], SECRET_KEYS, true)) {
                $hasValue = ($r['value'] !== '' && $r['value'] !== null);
                $r['value']     = '';        // 不返回明文
                $r['has_value'] = $hasValue; // 前端用这个判断是否已配置
            }
        }
        unset($r);
        sendJson(['settings' => $rows]);
    }

    if ($method === 'POST') {
        $in = readInput();
        $key = trim((string)($in['key'] ?? ''));
        $val = (string)($in['value'] ?? '');
        if (!$key) sendJson(['error' => 'key required'], 422);

        // P1#23: secret 字段提交空值视为"不修改"(否则 admin 每次保存都要重新粘 secret)
        if (in_array($key, SECRET_KEYS, true) && $val === '') {
            sendJson(['success' => true, 'skipped' => 'empty secret left unchanged']);
        }

        $stmt = $db->prepare(
            "INSERT INTO site_settings (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = :v2"
        );
        $stmt->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
        sendJson(['success' => true]);
    }
    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
