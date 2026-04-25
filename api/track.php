<?php
// ============================================================
// 访客追踪（被前端 sendBeacon 调用）
// 跳过 admin/api/uploads 路径
// 同 IP+path 60 秒内视为一次（防刷）
// ============================================================
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$input = readInput();
$path = trim((string)($input['path'] ?? ''));
$referer = trim((string)($input['referer'] ?? ''));

if (!$path || strlen($path) > 500) sendJson(['ok' => false]);
if (preg_match('#^/(admin/|api/|uploads/)#', $path)) sendJson(['ok' => false]);

$ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$ip = explode(',', $ip)[0]; // 取首个
$ip = trim($ip);
if (strlen($ip) > 45) $ip = substr($ip, 0, 45);

$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// 简单 bot 识别
$isBot = (bool)preg_match('/bot|crawler|spider|slurp|baiduspider|googlebot|bingbot|yandex|sogou|duckduck/i', $ua);

try {
    $db = getDb();
    // 60 秒内重复访问不再计
    $stmt = $db->prepare(
        'SELECT id FROM page_views
         WHERE ip = :ip AND path = :path AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)
         LIMIT 1'
    );
    $stmt->execute([':ip' => $ip, ':path' => $path]);
    if ($stmt->fetch()) sendJson(['ok' => true, 'dedup' => true]);

    $userId = currentUser()['id'] ?? null;
    $stmt = $db->prepare(
        'INSERT INTO page_views (path, ip, user_agent, referer, is_bot, user_id)
         VALUES (:path, :ip, :ua, :ref, :bot, :uid)'
    );
    $stmt->execute([
        ':path' => $path, ':ip' => $ip, ':ua' => $ua,
        ':ref' => substr($referer, 0, 500), ':bot' => $isBot ? 1 : 0,
        ':uid' => $userId,
    ]);
    sendJson(['ok' => true]);
} catch (PDOException $e) {
    error_log('[GlamEye track] ' . $e->getMessage());
    sendJson(['ok' => false]);
}
