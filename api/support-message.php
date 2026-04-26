<?php
// ============================================================
// 客服在线咨询 - 客户提交留言
// 入参 (JSON):
//   email     必填
//   name      可选(已登录用户自动用账户姓名)
//   subject   可选
//   body      必填(留言)
//   thread_token 可选(继续既有线程,localStorage 里的 token)
//   hp        蜜罐(机器人会填,真人留空)
// 行为:
//   1) 创建/继续 thread,插入一条 customer message
//   2) 邮件提醒 admin_email
//   3) 返回 thread_token + ticket(让前端 localStorage 记)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/mailer.php';

// GET ?token=xxx → 返回该线程的消息历史(让前端 widget 显示对话)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim((string)($_GET['token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) sendJson(['error' => 'Invalid token'], 422);
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT id, customer_email, customer_name, subject, status, last_sender,
                                     unread_for_customer, thread_token, updated_at
                              FROM support_threads WHERE thread_token=:t LIMIT 1");
        $stmt->execute([':t' => $token]);
        $thread = $stmt->fetch();
        if (!$thread) sendJson(['error' => 'Not found'], 404);

        $stmt = $db->prepare("SELECT id, sender, body, created_at FROM support_messages
                              WHERE thread_id=:id ORDER BY created_at ASC");
        $stmt->execute([':id' => $thread['id']]);
        $messages = $stmt->fetchAll();

        // 客户已读
        $db->prepare("UPDATE support_threads SET unread_for_customer=0 WHERE id=:id")
           ->execute([':id' => $thread['id']]);

        sendJson(['thread' => $thread, 'messages' => $messages]);
    } catch (PDOException $e) {
        sendError('Database error', 500, $e);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJson(['error' => 'Method not allowed'], 405);

$input = readInput();

// 蜜罐:正常表单不会有 hp 字段,机器人乱填一通会触发
if (!empty($input['hp'])) {
    sendJson(['success' => true, 'spam' => true]); // 静默丢弃
}

$email   = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$name    = trim((string)($input['name'] ?? ''));
$subject = trim((string)($input['subject'] ?? ''));
$body    = trim((string)($input['body'] ?? ''));
$token   = trim((string)($input['thread_token'] ?? ''));

if (!$email)                                 sendJson(['error' => 'Invalid email'], 422);
if ($body === '' || mb_strlen($body) > 4000) sendJson(['error' => 'Message must be 1-4000 chars'], 422);
if (mb_strlen($name) > 120)                  sendJson(['error' => 'Name too long'], 422);
if (mb_strlen($subject) > 200)               sendJson(['error' => 'Subject too long'], 422);
if ($token !== '' && !preg_match('/^[a-f0-9]{32}$/', $token)) $token = '';

// 频率限制:同 IP / email 一分钟内 ≤ 5 条
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $db = getDb();
    $stmt = $db->prepare("SELECT COUNT(*) FROM support_messages m
        JOIN support_threads t ON t.id = m.thread_id
        WHERE m.sender='customer' AND t.customer_email=:e AND m.created_at > NOW() - INTERVAL 60 SECOND");
    $stmt->execute([':e' => $email]);
    if ((int)$stmt->fetchColumn() >= 5) sendJson(['error' => 'Too many messages, please slow down'], 429);
} catch (Throwable $e) { /* 表还没建时跳过 */ }

// 已登录用户 → 拿 user_id 关联
$user = currentUser();
$userId = $user['id'] ?? null;
if (!$name && $user) {
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

try {
    $db = getDb();

    // 找现有线程:优先用 token,否则用 email + open
    $thread = null;
    if ($token) {
        $stmt = $db->prepare("SELECT * FROM support_threads WHERE thread_token=:t LIMIT 1");
        $stmt->execute([':t' => $token]);
        $thread = $stmt->fetch();
    }
    if (!$thread) {
        $stmt = $db->prepare("SELECT * FROM support_threads
                              WHERE customer_email=:e AND status='open'
                              ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([':e' => $email]);
        $thread = $stmt->fetch();
    }

    if (!$thread) {
        // 新建
        $newToken = bin2hex(random_bytes(16));
        $stmt = $db->prepare("INSERT INTO support_threads
            (customer_email, customer_name, user_id, subject, status, last_sender, unread_for_admin, thread_token)
            VALUES (:email, :name, :uid, :sub, 'open', 'customer', 1, :tok)");
        $stmt->execute([
            ':email' => $email, ':name' => $name, ':uid' => $userId,
            ':sub' => $subject ?: 'New inquiry from website',
            ':tok' => $newToken,
        ]);
        $threadId = (int)$db->lastInsertId();
        $thread = ['id' => $threadId, 'thread_token' => $newToken,
                   'customer_email' => $email, 'customer_name' => $name,
                   'subject' => $subject ?: 'New inquiry from website'];
    } else {
        // 继续:更新 last_sender + unread + 重开
        $db->prepare("UPDATE support_threads
                      SET last_sender='customer', unread_for_admin=1, status='open',
                          customer_name=COALESCE(NULLIF(:n,''), customer_name),
                          updated_at=NOW()
                      WHERE id=:id")
           ->execute([':n' => $name, ':id' => $thread['id']]);
    }

    // 插 message
    $db->prepare("INSERT INTO support_messages (thread_id, sender, body, created_at)
                  VALUES (:tid, 'customer', :b, NOW())")
       ->execute([':tid' => $thread['id'], ':b' => $body]);

    // 邮件提醒 admin
    $cfg = Mailer::loadConfig();
    $adminTo = $cfg['admin_email'] ?: '';
    if ($adminTo) {
        $safeName = htmlspecialchars($name ?: $email, ENT_QUOTES, 'UTF-8');
        $safeBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        $threadUrl = 'https://glameyeshop.com/admin/support.php?id=' . $thread['id'];
        $html = <<<HTML
<!DOCTYPE html><html><body style="font-family:Inter,Arial,sans-serif;color:#222;max-width:600px;margin:0 auto;padding:24px">
  <h2 style="color:#a8843a;margin:0 0 8px">📬 New inquiry from $safeName</h2>
  <p style="color:#666;margin:0 0 20px">From: <strong>$email</strong> &nbsp;·&nbsp; Thread #{$thread['id']}</p>
  <div style="background:#f6f1e8;border-left:3px solid #d4a955;padding:14px 18px;border-radius:4px;line-height:1.6">$safeBody</div>
  <p style="margin-top:24px"><a href="$threadUrl" style="background:#d4a955;color:#1a1408;padding:10px 22px;text-decoration:none;border-radius:4px;font-weight:600">Reply in admin →</a></p>
  <p style="color:#888;font-size:.85em;margin-top:30px">直接回复这封邮件也会发到客户邮箱。</p>
</body></html>
HTML;
        Mailer::send($adminTo, 'GlamEye Admin', "[GlamEye] New inquiry from $name", $html, $email);
    }

    sendJson([
        'success' => true,
        'thread_id' => (int)$thread['id'],
        'thread_token' => $thread['thread_token'],
        'message' => 'Got it — we will reply by email shortly.',
        'admin_notified' => (bool)$adminTo,
    ]);
} catch (PDOException $e) {
    sendError('Failed to send message', 500, $e);
}
