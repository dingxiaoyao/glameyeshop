<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(['error' => 'Method not allowed'], 405);

$in = readInput();
$email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
if (!$email) sendJson(['error' => 'Invalid email'], 422);

// P0#2: 防订阅枚举 / 邮件轰炸(单 IP 20 次/小时)
$bucket = 'newsletter:' . rateLimitClientIp();
rateLimitGuard($bucket, 20, 3600, 'Too many subscribe attempts.');

try {
    $db = getDb();
    $stmt = $db->prepare("INSERT IGNORE INTO newsletter_subscribers (email, source) VALUES (:e, 'newsletter_form')");
    $stmt->execute([':e' => $email]);
    // 即使 INSERT IGNORE 让重复邮件无效,也消耗 1 次配额(防"用接口判定邮件是否已订阅")
    rateLimitFail($bucket);
    sendJson(['success' => true]);
} catch (PDOException $e) {
    sendError('Subscribe failed', 500, $e);
}
