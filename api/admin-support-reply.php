<?php
// ============================================================
// Admin: 在某线程下回复
//   POST { thread_id, body, [close: 1] }
// 行为:
//   1) 插一条 admin message
//   2) thread.status = 'replied'(若 close=1 则 'closed'),
//      last_sender='admin', unread_for_customer=1, unread_for_admin=0
//   3) 邮件发到 customer_email
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/mailer.php';
requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(['error' => 'Method not allowed'], 405);

$input    = readInput();
$threadId = intval($input['thread_id'] ?? 0);
$body     = trim((string)($input['body'] ?? ''));
$close    = !empty($input['close']);

if ($threadId <= 0)                          sendJson(['error' => 'Invalid thread_id'], 422);
if ($body === '' || mb_strlen($body) > 8000) sendJson(['error' => 'Reply must be 1-8000 chars'], 422);

try {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM support_threads WHERE id=:id LIMIT 1");
    $stmt->execute([':id' => $threadId]);
    $thread = $stmt->fetch();
    if (!$thread) sendJson(['error' => 'Thread not found'], 404);

    $adminUser = $_SERVER['PHP_AUTH_USER'] ?? 'admin';

    $db->prepare("INSERT INTO support_messages (thread_id, sender, body, admin_user, created_at)
                  VALUES (:tid, 'admin', :b, :u, NOW())")
       ->execute([':tid' => $threadId, ':b' => $body, ':u' => $adminUser]);

    $newStatus = $close ? 'closed' : 'replied';
    $db->prepare("UPDATE support_threads
                  SET status=:s, last_sender='admin',
                      unread_for_customer=1, unread_for_admin=0, updated_at=NOW()
                  WHERE id=:id")
       ->execute([':s' => $newStatus, ':id' => $threadId]);

    // 给客户发邮件
    $cfg = Mailer::loadConfig();
    $name  = $thread['customer_name'] ?: 'there';
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
    $token = $thread['thread_token'];
    $continueUrl = 'https://glameyeshop.com/?support=' . $token; // 前端 support widget 会读这个 token
    $html = <<<HTML
<!DOCTYPE html><html><body style="font-family:Inter,Arial,sans-serif;color:#222;max-width:600px;margin:0 auto;padding:24px">
  <h2 style="color:#a8843a;margin:0 0 8px">Hi $safeName 👋</h2>
  <p style="color:#666;margin:0 0 20px">Reply from GlamEye support · Thread #{$thread['id']}</p>
  <div style="background:#f6f1e8;border-left:3px solid #d4a955;padding:14px 18px;border-radius:4px;line-height:1.6">$safeBody</div>
  <p style="margin-top:24px"><a href="$continueUrl" style="background:#d4a955;color:#1a1408;padding:10px 22px;text-decoration:none;border-radius:4px;font-weight:600">Continue the conversation →</a></p>
  <p style="color:#888;font-size:.85em;margin-top:30px">直接回复这封邮件也会回到我们的工单系统里。</p>
  <hr style="border:0;border-top:1px solid #eee;margin:30px 0">
  <p style="color:#aaa;font-size:.8em;text-align:center">GlamEye · Made in California · Cruelty-Free</p>
</body></html>
HTML;
    $mailRes = Mailer::send(
        $thread['customer_email'],
        $thread['customer_name'] ?: '',
        'Re: ' . ($thread['subject'] ?: 'Your inquiry'),
        $html,
        $cfg['email_from_address']
    );

    sendJson([
        'success' => true,
        'thread_id' => $threadId,
        'status' => $newStatus,
        'email_sent' => $mailRes['ok'],
        'email_mode' => $mailRes['mode'],
        'email_error' => $mailRes['error'] ?? null,
    ]);
} catch (PDOException $e) {
    sendError('Failed to reply', 500, $e);
}
