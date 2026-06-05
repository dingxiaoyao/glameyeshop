<?php
// ============================================================
// User authentication endpoints
// Routes (action via ?action= or JSON body):
//   POST  signup    - register new user
//   POST  login     - email + password
//   POST  logout    - clear session
//   GET   me        - current user info
//   POST  update    - update profile
//   POST  change-password
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/rate-limit.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ("$method:$action") {
        case 'POST:signup':           handleSignup(); break;
        case 'POST:login':            handleLogin(); break;
        case 'POST:logout':           handleLogout(); break;
        case 'GET:me':                handleMe(); break;
        case 'POST:update':           handleUpdate(); break;
        case 'POST:change-password':  handleChangePassword(); break;
        // P1#8 + P1#9
        case 'POST:forgot-password':  handleForgotPassword(); break;
        case 'POST:reset-password':   handleResetPassword(); break;
        case 'GET:verify-email':      handleVerifyEmail(); break;
        case 'POST:resend-verify':    handleResendVerify(); break;
        default: sendJson(['error' => 'Unknown action'], 400);
    }
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
} catch (Throwable $e) {
    sendError('Server error', 500, $e);
}

/** 通用:用 Mailer + EmailTemplates 发邮件,失败 log 但不抛 */
function sendAuthEmail(string $to, string $name, string $kind, ...$args): bool {
    try {
        require_once __DIR__ . '/lib/mailer.php';
        require_once __DIR__ . '/lib/email-templates.php';
        $tpl = null;
        if ($kind === 'reset') {
            $tpl = EmailTemplates::passwordReset($to, $name, $args[0]);
        } elseif ($kind === 'verify') {
            $tpl = EmailTemplates::emailVerification($to, $name, $args[0]);
        }
        if ($tpl) {
            $r = Mailer::send($to, $name, $tpl['subject'], $tpl['html']);
            return !empty($r['ok']);
        }
    } catch (Throwable $e) {
        error_log('[auth] sendAuthEmail failed: ' . $e->getMessage());
    }
    return false;
}

/** 取站点 base URL */
function siteBaseUrl(): string {
    require_once __DIR__ . '/payment-config.php';
    $base = getPaymentConfig('site_base_url', '');
    if ($base && preg_match('#^https?://#', $base)) return rtrim($base, '/');
    return 'https://glameyeshop.com';
}

function handleSignup(): void {
    $in = readInput();
    $email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $pwd   = (string)($in['password'] ?? '');
    $first = trim((string)($in['first_name'] ?? ''));
    $last  = trim((string)($in['last_name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    $sub   = !empty($in['is_subscribed']) ? 1 : 0;

    if (!$email)               sendJson(['error' => 'Invalid email'], 422);
    if (strlen($pwd) < 8)      sendJson(['error' => 'Password must be at least 8 characters'], 422);
    if (!$first)               sendJson(['error' => 'First name required'], 422);
    if (mb_strlen($first) > 100 || mb_strlen($last) > 100) sendJson(['error' => 'Name too long'], 422);

    // P0#2: 防注册爆破 / 邮件枚举(同 IP 10 次失败/小时 锁)
    $ip = rateLimitClientIp();
    $bucket = "signup:$ip";
    rateLimitGuard($bucket, 10, 3600, 'Too many signup attempts. Please wait an hour.');

    $db = getDb();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        rateLimitFail($bucket);  // 邮件已存在也算一次失败,防"用枚举判定是否已注册"
        sendJson(['error' => 'Email already registered'], 409);
    }

    $hash = password_hash($pwd, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (email, password_hash, first_name, last_name, phone, is_subscribed) VALUES (:email, :hash, :first, :last, :phone, :sub)');
    $stmt->execute([
        ':email' => $email, ':hash' => $hash, ':first' => $first,
        ':last'  => $last,  ':phone' => $phone ?: null, ':sub' => $sub,
    ]);
    $uid = (int)$db->lastInsertId();

    if ($sub) {
        $stmt = $db->prepare('INSERT IGNORE INTO newsletter_subscribers (email, source) VALUES (:e, :s)');
        $stmt->execute([':e' => $email, ':s' => 'signup']);
    }

    // P1#9: 自动发送邮箱验证邮件
    try {
        $verifyToken = bin2hex(random_bytes(24));
        $verifyExp   = date('Y-m-d H:i:s', time() + 86400 * 2);  // 48h
        $db->prepare(
            'UPDATE users SET email_verify_token = :t, email_verify_expires_at = :exp WHERE id = :id'
        )->execute([':t' => $verifyToken, ':exp' => $verifyExp, ':id' => $uid]);
        $verifyUrl = siteBaseUrl() . '/api/auth.php?action=verify-email&token=' . urlencode($verifyToken);
        sendAuthEmail($email, $first, 'verify', $verifyUrl);
    } catch (Throwable $e) {
        error_log('[signup] verify email failed: ' . $e->getMessage());
    }

    startUserSession();
    $_SESSION['user_id'] = $uid;
    sendJson(['success' => true, 'user' => ['id' => $uid, 'email' => $email, 'first_name' => $first]]);
}

function handleLogin(): void {
    $in = readInput();
    $email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $pwd   = (string)($in['password'] ?? '');

    if (!$email || !$pwd) sendJson(['error' => 'Email and password required'], 422);

    // P0#2: 防密码字典攻击 — IP+email 5 次失败/15min 锁
    // 同时维护一个 IP-only bucket(50 次/15min)防同 IP 跨 email 撞库
    $ip = rateLimitClientIp();
    $bucketEmail = "login:$ip:" . mb_substr($email, 0, 80);
    $bucketIp    = "login-ip:$ip";
    rateLimitGuard($bucketEmail, 5, 900, 'Too many login attempts for this email. Wait 15 minutes or reset your password.');
    rateLimitGuard($bucketIp,   50, 900, 'Too many login attempts from your network. Wait 15 minutes.');

    $db = getDb();
    $stmt = $db->prepare('SELECT id, email, password_hash, first_name FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // 时序攻击防护：始终调用 password_verify
    $hash = $user['password_hash'] ?? '$2y$10$invalidhashinvalidhashinvalidhashinvalidhashinvalidhash';
    if (!password_verify($pwd, $hash) || !$user) {
        rateLimitFail($bucketEmail);
        rateLimitFail($bucketIp);
        sendJson(['error' => 'Invalid email or password'], 401);
    }

    // 登录成功 — 清掉 email-bucket(同 IP-bucket 不清,防"成功一次刷掉跨账号撞库累积")
    rateLimitClear($bucketEmail);

    startUserSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    sendJson(['success' => true, 'user' => [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'first_name' => $user['first_name']
    ]]);
}

function handleLogout(): void {
    startUserSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(SESSION_COOKIE, '', time() - 3600,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    sendJson(['success' => true]);
}

function handleMe(): void {
    $u = currentUser();
    if (!$u) sendJson(['user' => null]);
    sendJson(['user' => $u]);
}

function handleUpdate(): void {
    $u = requireUser();
    $in = readInput();
    $first = trim((string)($in['first_name'] ?? $u['first_name']));
    $last  = trim((string)($in['last_name']  ?? $u['last_name']));
    $phone = trim((string)($in['phone']      ?? $u['phone'] ?? ''));
    $sub   = isset($in['is_subscribed']) ? (!empty($in['is_subscribed']) ? 1 : 0) : (int)$u['is_subscribed'];

    if (!$first || mb_strlen($first) > 100) sendJson(['error' => 'Invalid first name'], 422);

    $db = getDb();
    $stmt = $db->prepare('UPDATE users SET first_name=:f, last_name=:l, phone=:p, is_subscribed=:s WHERE id=:id');
    $stmt->execute([
        ':f' => $first, ':l' => $last, ':p' => $phone ?: null, ':s' => $sub, ':id' => $u['id'],
    ]);
    sendJson(['success' => true]);
}

/**
 * P1#8: 忘记密码 — 发重置邮件
 * 不论邮箱是否存在都返回成功(防枚举)
 */
function handleForgotPassword(): void {
    $in = readInput();
    $email = filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL);
    if (!$email) sendJson(['error' => 'Invalid email'], 422);

    // 限频:同 email + IP 5 次/小时
    $ip = rateLimitClientIp();
    $bucket = "forgot:$ip:" . mb_substr($email, 0, 80);
    rateLimitGuard($bucket, 5, 3600, 'Too many password reset requests for this email. Wait an hour.');

    $db = getDb();
    $stmt = $db->prepare('SELECT id, email, first_name FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // 1 小时过期
        $token = bin2hex(random_bytes(24));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $db->prepare(
            'INSERT INTO password_reset_tokens (token, user_id, expires_at, requested_ip)
             VALUES (:t, :uid, :exp, :ip)'
        )->execute([':t' => $token, ':uid' => $user['id'], ':exp' => $expires, ':ip' => $ip]);

        // 用 base URL + reset-password.html?token=xxx
        $resetUrl = siteBaseUrl() . '/reset-password.html?token=' . urlencode($token);
        sendAuthEmail($user['email'], $user['first_name'] ?? '', 'reset', $resetUrl);
    }
    rateLimitFail($bucket);  // 不论用户是否存在都消耗一次配额(防枚举)

    sendJson(['success' => true, 'message' => 'If an account exists for this email, a reset link has been sent.']);
}

/**
 * P1#8: 重置密码 — 用 token 校验后改密码
 */
function handleResetPassword(): void {
    $in = readInput();
    $token = trim((string)($in['token'] ?? ''));
    $pwd   = (string)($in['new_password'] ?? '');

    if (!preg_match('/^[a-f0-9]{48}$/', $token)) sendJson(['error' => 'Invalid token'], 400);
    if (strlen($pwd) < 8) sendJson(['error' => 'Password must be at least 8 characters'], 422);

    // 同 IP 重置 token 校验失败 10 次/小时(防爆破)
    $ip = rateLimitClientIp();
    $bucket = "reset:$ip";
    rateLimitGuard($bucket, 10, 3600, 'Too many reset attempts');

    $db = getDb();
    $stmt = $db->prepare(
        "SELECT prt.token, prt.user_id, prt.expires_at, prt.used_at, u.email
         FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token = :t LIMIT 1"
    );
    $stmt->execute([':t' => $token]);
    $row = $stmt->fetch();

    if (!$row || $row['used_at'] !== null) {
        rateLimitFail($bucket);
        sendJson(['error' => 'Reset link is invalid or already used'], 400);
    }
    if (strtotime($row['expires_at']) < time()) {
        rateLimitFail($bucket);
        sendJson(['error' => 'Reset link has expired. Please request a new one.'], 400);
    }

    // 改密码 + 标 token used
    $hash = password_hash($pwd, PASSWORD_BCRYPT);
    $db->beginTransaction();
    try {
        $db->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
           ->execute([':h' => $hash, ':id' => $row['user_id']]);
        $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE token = :t')
           ->execute([':t' => $token]);
        // 也清掉该 user 的其他未用 token(一次性策略)
        $db->prepare('UPDATE password_reset_tokens SET used_at = NOW()
                      WHERE user_id = :uid AND used_at IS NULL AND token != :t')
           ->execute([':uid' => $row['user_id'], ':t' => $token]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    // 清掉该用户的登录限频(因为已经证明拥有邮箱控制权)
    rateLimitClear("login:$ip:" . mb_substr($row['email'], 0, 80));

    sendJson(['success' => true, 'message' => 'Password updated. You can now log in.']);
}

/**
 * P1#9: 验证邮箱 — GET ?token=...
 */
function handleVerifyEmail(): void {
    $token = trim((string)($_GET['token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        sendJson(['error' => 'Invalid verification link'], 400);
    }

    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, email, email_verified, email_verify_expires_at
         FROM users WHERE email_verify_token = :t LIMIT 1'
    );
    $stmt->execute([':t' => $token]);
    $user = $stmt->fetch();
    if (!$user) sendJson(['error' => 'Verification link is invalid or already used'], 400);

    if (intval($user['email_verified']) === 1) {
        sendJson(['success' => true, 'already' => true, 'message' => 'Email was already verified.']);
    }
    if ($user['email_verify_expires_at'] && strtotime($user['email_verify_expires_at']) < time()) {
        sendJson(['error' => 'Verification link has expired. Please request a new one.', 'expired' => true], 400);
    }

    $db->prepare(
        'UPDATE users SET email_verified = 1, email_verify_token = NULL, email_verify_expires_at = NULL
         WHERE id = :id'
    )->execute([':id' => $user['id']]);

    sendJson(['success' => true, 'message' => 'Email verified! You can close this tab.']);
}

/**
 * P1#9: 重发验证邮件
 */
function handleResendVerify(): void {
    $u = requireUser();
    if (intval($u['email_verified'] ?? 0) === 1) {
        sendJson(['success' => true, 'already' => true]);
    }

    $ip = rateLimitClientIp();
    $bucket = "resend-verify:" . $u['id'];
    rateLimitGuard($bucket, 3, 3600, 'Too many resend requests. Try again later.');

    $token = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + 86400 * 2);  // 48h
    $db = getDb();
    $db->prepare(
        'UPDATE users SET email_verify_token = :t, email_verify_expires_at = :exp WHERE id = :id'
    )->execute([':t' => $token, ':exp' => $expires, ':id' => $u['id']]);

    $verifyUrl = siteBaseUrl() . '/api/auth.php?action=verify-email&token=' . urlencode($token);
    sendAuthEmail($u['email'], $u['first_name'] ?? '', 'verify', $verifyUrl);
    rateLimitFail($bucket);

    sendJson(['success' => true, 'message' => 'Verification email sent.']);
}

function handleChangePassword(): void {
    $u = requireUser();
    $in = readInput();
    $old = (string)($in['current_password'] ?? '');
    $new = (string)($in['new_password'] ?? '');
    if (strlen($new) < 8) sendJson(['error' => 'New password must be at least 8 characters'], 422);

    // P0#2: 防 session 被劫后猜旧密码 — 同账户 5 次失败/15min 锁
    $bucket = 'change-pwd:' . (int)$u['id'];
    rateLimitGuard($bucket, 5, 900, 'Too many password change attempts. Wait 15 minutes.');

    $db = getDb();
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $u['id']]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($old, $row['password_hash'])) {
        rateLimitFail($bucket);
        sendJson(['error' => 'Current password is incorrect'], 401);
    }
    rateLimitClear($bucket);

    $hash = password_hash($new, PASSWORD_BCRYPT);
    $stmt = $db->prepare('UPDATE users SET password_hash=:h WHERE id=:id');
    $stmt->execute([':h' => $hash, ':id' => $u['id']]);
    sendJson(['success' => true]);
}
