<?php
// ============================================================
// CAN-SPAM 退订端点(P0#5)
// GET  ?token=…&email=… — 显示确认 / 自动退订(取决于 mode)
// POST { token, email } — 确认退订
// 设计:
//   - 一键退订(US CAN-SPAM 要求最多 10 个工作日)— 我们做即时
//   - token 是不可猜的 48 hex,无需登录就能退订
//   - email 同时校验,防 token 泄露后批量退订他人
// ============================================================
require_once __DIR__ . '/config.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$email = filter_var($_GET['email'] ?? $_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

// 浏览器 GET 直接访问 → 渲染确认页面(避免预取自动退订)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$token || !preg_match('/^[a-f0-9]{48}$/', $token) || !$email) {
        http_response_code(400);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><body style="font-family:system-ui;padding:3rem;text-align:center;">';
        echo '<h1>Invalid unsubscribe link</h1>';
        echo '<p>This link is missing or malformed. Please email <a href="mailto:support@glameyeshop.com">support@glameyeshop.com</a> and we will unsubscribe you manually.</p>';
        echo '</body></html>';
        exit;
    }
    // 跳转到前端 unsubscribe.html(用户可以确认或撤销)
    header('Location: /unsubscribe.html?token=' . urlencode($token) . '&email=' . urlencode($email));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

if (!$token || !preg_match('/^[a-f0-9]{48}$/', $token)) {
    sendJson(['error' => 'Invalid token'], 400);
}
if (!$email) {
    sendJson(['error' => 'Invalid email'], 400);
}

try {
    $db = getDb();
    // 校验 token + email 同时匹配 — 防 token 泄露后被滥用
    $stmt = $db->prepare(
        'SELECT id, email, unsubscribed_at FROM newsletter_subscribers
         WHERE unsubscribe_token = :t AND email = :e LIMIT 1'
    );
    $stmt->execute([':t' => $token, ':e' => $email]);
    $row = $stmt->fetch();

    if (!$row) {
        // 即使不存在也返回成功(防"用 token 探测谁订阅")
        sendJson(['success' => true, 'message' => 'You are unsubscribed from marketing emails.']);
    }

    if ($row['unsubscribed_at']) {
        sendJson(['success' => true, 'message' => 'You were already unsubscribed.', 'already' => true]);
    }

    $db->prepare(
        'UPDATE newsletter_subscribers SET unsubscribed_at = NOW() WHERE id = :id'
    )->execute([':id' => $row['id']]);

    // 同步把 users 表的 is_subscribed 也关掉
    $db->prepare(
        'UPDATE users SET is_subscribed = 0 WHERE email = :e'
    )->execute([':e' => $email]);

    // 发退订确认邮件(transactional,不需要退订链接)
    try {
        require_once __DIR__ . '/lib/mailer.php';
        require_once __DIR__ . '/lib/email-templates.php';
        $tpl = EmailTemplates::unsubscribeConfirmation($email);
        Mailer::send($email, '', $tpl['subject'], $tpl['html']);
    } catch (Throwable $e) {
        error_log('[unsubscribe] confirmation email failed: ' . $e->getMessage());
    }

    sendJson(['success' => true, 'message' => 'You are unsubscribed from marketing emails. We won\'t email you again.']);
} catch (PDOException $e) {
    sendError('Unsubscribe failed', 500, $e);
}
