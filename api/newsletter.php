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
    // P0#5: 订阅时签发 unsubscribe_token(每个订阅一个,可放进所有未来邮件的退订 link)
    $unsubToken = bin2hex(random_bytes(24));
    $stmt = $db->prepare(
        "INSERT INTO newsletter_subscribers (email, source, unsubscribe_token)
              VALUES (:e, 'newsletter_form', :t)
         ON DUPLICATE KEY UPDATE
              unsubscribe_token = COALESCE(unsubscribe_token, :t2),
              unsubscribed_at   = NULL"  // 重新订阅清掉退订时间戳
    );
    $stmt->execute([':e' => $email, ':t' => $unsubToken, ':t2' => $unsubToken]);
    rateLimitFail($bucket);
    sendJson(['success' => true]);
} catch (PDOException $e) {
    sendError('Subscribe failed', 500, $e);
}
