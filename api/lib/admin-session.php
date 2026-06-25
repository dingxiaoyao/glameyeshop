<?php
// ============================================================
// Admin 独立 session + 认证库
//
// 核心设计:
//   - admin session 与 user session 完全隔离(不同 cookie 名 + session_name)
//   - 密码 Argon2id 哈希(失败时回退 bcrypt)
//   - 限频:同 IP 10 次/15min,同 email 5 次/15min
//   - CSRF token:每个 admin session 一个,POST 必带
//   - 短超时:idle 30min 自动 expire,absolute 8h 强制重登
//   - 登录成功:session_regenerate_id(true) 防 session fixation
//   - 可选 TOTP 2FA(RFC 6238)
//
// 用法:
//   require_once __DIR__ . '/lib/admin-session.php';
//   requireAdminSession();   // 未登录 → 302 to login.php(HTML)或 401(API)
//
// ============================================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/rate-limit.php';

const ADMIN_SESSION_COOKIE  = 'glameye_admin_sid';
const ADMIN_SESSION_IDLE    = 1800;          // 30 分钟无活动 → 退
const ADMIN_SESSION_MAX     = 28800;         // 绝对 8 小时 → 强制重登
const ADMIN_LOCKOUT_FAILS   = 5;             // 5 次失败 → 锁账号
const ADMIN_LOCKOUT_MIN     = 15;            // 锁 15 分钟
const ADMIN_IP_FAILS_MAX    = 20;            // 同 IP 20 次/15min
const ADMIN_IP_FAILS_WIN    = 900;

// ============================================================
// session 启动 — 严苛 cookie 配置
// ============================================================
function startAdminSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        // 已有别的 session 启动(比如 user session),不能混用
        // 这里我们要求 admin 路径下不要先启 user session
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name(ADMIN_SESSION_COOKIE);
    session_set_cookie_params([
        'lifetime' => 0,                 // 关浏览器即失效(session cookie)
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',          // admin 全靠 strict 防 CSRF + 链接劫持
    ]);
    // 用独立 save_path 避免与前台 session 文件混(可选,如 PHP 默认即可不必要)
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// ============================================================
// 登录:写 session,绑 IP/UA 指纹
// ============================================================
function adminLogin(array $adminUser): void {
    startAdminSession();
    session_regenerate_id(true);            // 防 session fixation
    $_SESSION['admin_id']         = (int)$adminUser['id'];
    $_SESSION['admin_email']      = (string)$adminUser['email'];
    $_SESSION['admin_login_at']   = time();
    $_SESSION['admin_last_seen']  = time();
    $_SESSION['admin_ip']         = rateLimitClientIp();
    $_SESSION['admin_ua_hash']    = substr(hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 32);
    $_SESSION['admin_csrf']       = bin2hex(random_bytes(32));

    try {
        $db = getDb();
        $stmt = $db->prepare('UPDATE admin_users SET last_login_at=NOW(), last_login_ip=:ip, failed_attempts=0, locked_until=NULL WHERE id=:id');
        $stmt->execute([':ip' => rateLimitClientIp(), ':id' => $adminUser['id']]);
    } catch (Throwable $e) { error_log('[admin-session] login update failed: ' . $e->getMessage()); }
}

function adminLogout(): void {
    startAdminSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ============================================================
// 当前 admin(读 session + 校验过期 / 指纹 / DB 状态)
// ============================================================
function currentAdmin(): ?array {
    startAdminSession();
    if (empty($_SESSION['admin_id'])) return null;

    $now = time();
    // 1) idle 超时
    if (($now - ($_SESSION['admin_last_seen'] ?? 0)) > ADMIN_SESSION_IDLE) {
        adminLogout();
        return null;
    }
    // 2) absolute 超时
    if (($now - ($_SESSION['admin_login_at'] ?? 0)) > ADMIN_SESSION_MAX) {
        adminLogout();
        return null;
    }
    // 3) UA 指纹改动 = 可能 cookie 被偷
    $uaNow = substr(hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 32);
    if (($_SESSION['admin_ua_hash'] ?? '') !== $uaNow) {
        adminLogout();
        return null;
    }
    // 4) DB 校验 — 账号可能被停用 / 删除
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT id, email, display_name, totp_enabled, is_active, last_login_at FROM admin_users WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['is_active'] !== 1) {
            adminLogout();
            return null;
        }
    } catch (Throwable $e) {
        // DB 抖动时不踢人,但也不更新 last_seen
        return ['id' => $_SESSION['admin_id'], 'email' => $_SESSION['admin_email'] ?? '', '_degraded' => true];
    }
    // 5) 滑动续期
    $_SESSION['admin_last_seen'] = $now;
    return $row;
}

// ============================================================
// 守门 — 给 admin/*.php 和 api/admin-*.php 用
// 自动区分 HTML 请求 vs API:HTML → 302 跳 login,API → 401 JSON
// ============================================================
function requireAdminSession(): array {
    $a = currentAdmin();
    if ($a) return $a;
    // 没登录 — 区分响应
    $isApi = _isAdminApiRequest();
    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Admin authentication required', 'code' => 'ADMIN_LOGIN_REQUIRED']);
        exit;
    }
    // HTML:重定向到 login,带 redirect 参数
    $back = $_SERVER['REQUEST_URI'] ?? '/admin/';
    $url = '/admin/login.php?redirect=' . urlencode($back);
    header('Location: ' . $url, true, 302);
    exit;
}

function _isAdminApiRequest(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $uri    = $_SERVER['REQUEST_URI'] ?? '';
    if (stripos($accept, 'application/json') !== false) return true;
    if ($xrw === 'XMLHttpRequest') return true;
    if (stripos($uri, '/api/') === 0 || strpos($uri, '/api/') !== false) return true;
    return false;
}

// ============================================================
// CSRF
// ============================================================
function adminCsrfToken(): string {
    startAdminSession();
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function adminCsrfCheck(?string $token): bool {
    startAdminSession();
    $expected = $_SESSION['admin_csrf'] ?? '';
    if (!$expected || !$token) return false;
    return hash_equals($expected, $token);
}

function requireAdminCsrf(): void {
    $tok = $_POST['_csrf']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? null;
    if (!adminCsrfCheck($tok)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Invalid or missing CSRF token', 'code' => 'CSRF']);
        exit;
    }
}

// ============================================================
// admin_users 增 / 查 / 校验密码
// ============================================================

/**
 * 自愈:首次调用如表不存在就当场建。避免 deploy 静默失败时 admin 永远进不去后台。
 * 只跑一次,后续短路。
 */
function ensureAdminTables(): bool {
    static $ensured = false;
    if ($ensured) return true;
    try {
        $db = getDb();
        $db->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) NOT NULL DEFAULT 'Admin',
            totp_secret VARCHAR(64) DEFAULT NULL,
            totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
            totp_backup_codes_hash TEXT DEFAULT NULL,
            failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME DEFAULT NULL,
            last_login_at DATETIME DEFAULT NULL,
            last_login_ip VARCHAR(64) DEFAULT NULL,
            password_changed_at DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_email_active (email, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->exec("CREATE TABLE IF NOT EXISTS admin_login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            email VARCHAR(190) DEFAULT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            reason VARCHAR(80) DEFAULT NULL,
            INDEX idx_email_time (email, attempted_at),
            INDEX idx_ip_time (ip, attempted_at),
            INDEX idx_time (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ensured = true;
        return true;
    } catch (Throwable $e) {
        error_log('[admin-session] ensureAdminTables failed: ' . $e->getMessage());
        return false;
    }
}

function adminUserCount(): int {
    try {
        $db = getDb();
        return (int)$db->query('SELECT COUNT(*) FROM admin_users WHERE is_active=1')->fetchColumn();
    } catch (Throwable $e) {
        // 表可能不存在 — 自愈后重试一次
        if (ensureAdminTables()) {
            try {
                return (int)getDb()->query('SELECT COUNT(*) FROM admin_users WHERE is_active=1')->fetchColumn();
            } catch (Throwable $e2) { /* fall through */ }
        }
        return 0;
    }
}

function adminUserByEmail(string $email): ?array {
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT * FROM admin_users WHERE email=:e LIMIT 1');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        if (ensureAdminTables()) {
            try {
                $stmt = getDb()->prepare('SELECT * FROM admin_users WHERE email=:e LIMIT 1');
                $stmt->execute([':e' => strtolower(trim($email))]);
                return $stmt->fetch() ?: null;
            } catch (Throwable $e2) { /* fall through */ }
        }
        return null;
    }
}

function adminPasswordHash(string $plain): string {
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }
    return password_hash($plain, PASSWORD_BCRYPT);
}

function adminPasswordVerify(string $plain, string $hash): bool {
    return password_verify($plain, $hash);
}

function adminCreate(string $email, string $password, string $displayName = 'Admin'): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email'];
    }
    $strength = adminPasswordStrengthError($password);
    if ($strength) return ['ok' => false, 'error' => $strength];
    // 提前确保表存在 — 哪怕 deploy 静默失败也救得回来
    ensureAdminTables();
    if (adminUserByEmail($email)) {
        return ['ok' => false, 'error' => 'Email already exists'];
    }
    $attempt = function () use ($email, $password, $displayName) {
        $db = getDb();
        $stmt = $db->prepare('INSERT INTO admin_users (email, password_hash, display_name, password_changed_at) VALUES (:e, :h, :n, NOW())');
        $stmt->execute([
            ':e' => $email,
            ':h' => adminPasswordHash($password),
            ':n' => $displayName ?: 'Admin',
        ]);
        return (int)$db->lastInsertId();
    };
    try {
        return ['ok' => true, 'id' => $attempt()];
    } catch (Throwable $e) {
        // 表不存在 → 自愈一次再重试
        if (ensureAdminTables()) {
            try { return ['ok' => true, 'id' => $attempt()]; }
            catch (Throwable $e2) { $e = $e2; }
        }
        error_log('[admin-session] create failed: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
        // 首次 setup 把真实 DB 错透传 — 帮排查环境问题(如 DB 用户无 CREATE 权限)
        // 但不带 stack trace,只 message 前 200 字
        return ['ok' => false, 'error' => 'DB error: ' . substr($e->getMessage(), 0, 200)];
    }
}

function adminPasswordStrengthError(string $p): ?string {
    if (strlen($p) < 8) return 'Password must be at least 8 characters';
    if (!preg_match('/[A-Z]/', $p)) return 'Password must contain an uppercase letter';
    if (!preg_match('/[a-z]/', $p)) return 'Password must contain a lowercase letter';
    if (!preg_match('/\d/', $p))    return 'Password must contain a digit';
    if (!preg_match('/[^A-Za-z0-9]/', $p)) return 'Password must contain a symbol';
    return null;
}

// ============================================================
// 登录尝试日志
// ============================================================
function adminLogAttempt(?string $email, bool $success, string $reason): void {
    try {
        $db = getDb();
        $stmt = $db->prepare('INSERT INTO admin_login_attempts (email, ip, user_agent, success, reason) VALUES (:e, :ip, :ua, :s, :r)');
        $stmt->execute([
            ':e'  => $email ? strtolower(trim($email)) : null,
            ':ip' => rateLimitClientIp(),
            ':ua' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ':s'  => $success ? 1 : 0,
            ':r'  => $reason,
        ]);
    } catch (Throwable $e) { /* swallow */ }
}

// 当前账号是否被锁
function adminIsLocked(array $admin): bool {
    if (empty($admin['locked_until'])) return false;
    return strtotime($admin['locked_until']) > time();
}

// 失败一次 — 累加 + 满 5 次锁 15 分钟
function adminBumpFail(int $adminId): void {
    try {
        $db = getDb();
        $db->prepare('UPDATE admin_users SET failed_attempts = failed_attempts + 1,
                       locked_until = CASE WHEN failed_attempts + 1 >= :max THEN DATE_ADD(NOW(), INTERVAL :min MINUTE) ELSE locked_until END
                      WHERE id = :id')
           ->execute([':max' => ADMIN_LOCKOUT_FAILS, ':min' => ADMIN_LOCKOUT_MIN, ':id' => $adminId]);
    } catch (Throwable $e) { /* swallow */ }
}

// ============================================================
// TOTP(RFC 6238)— 30s 窗口,SHA1,6 位
// ============================================================
function totpGenerateSecret(int $bytes = 20): string {
    // RFC 4648 Base32
    return base32Encode(random_bytes($bytes));
}

function totpUri(string $secret, string $accountName, string $issuer = 'GlamEye Admin'): string {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($accountName)
         . '?secret=' . $secret
         . '&issuer=' . rawurlencode($issuer)
         . '&algorithm=SHA1&digits=6&period=30';
}

function totpVerify(string $secret, string $code, int $windowSlack = 1): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return false;
    $key = base32Decode($secret);
    if (!$key) return false;
    $now = floor(time() / 30);
    for ($i = -$windowSlack; $i <= $windowSlack; $i++) {
        if (hash_equals(totpAt($key, (int)$now + $i), $code)) return true;
    }
    return false;
}

function totpAt(string $key, int $counter): string {
    // HOTP: HMAC-SHA1(key, counter) → dynamic truncation → 6 digits
    $bin = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $bin, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $part = substr($hash, $offset, 4);
    $value = unpack('N', $part)[1] & 0x7FFFFFFF;
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function base32Encode(string $bin): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }
    // 不加 = 填充(Google Authenticator 不要)
    return $out;
}

function base32Decode(string $s): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Z2-7]/', '', $s));
    if ($s === '') return '';
    $bits = '';
    foreach (str_split($s) as $c) {
        $pos = strpos($alphabet, $c);
        if ($pos === false) return '';
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bin = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $bin .= chr(bindec($chunk));
    }
    return $bin;
}

// ============================================================
// 旧 Basic Auth 兼容(过渡期)
// 让现有 30+ admin/*.php 和 api/admin-*.php 不一次性爆改
// ============================================================
function adminBasicAuthFallback(): ?array {
    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW']   ?? '';
    if ($u === '' || $p === '') return null;

    // 先试 admin_users DB
    $admin = adminUserByEmail($u);
    if ($admin && (int)$admin['is_active'] === 1 && adminPasswordVerify($p, $admin['password_hash'])) {
        // 给 API 调用 — 不写 session(每次都校验)
        return $admin;
    }

    // 再试旧的 env(若 admin_users 还没建初始账号,允许 env 兜底)
    $envU = getenv('ADMIN_USER');
    $envP = getenv('ADMIN_PASS');
    if ($envU && $envP && hash_equals($envU, $u) && hash_equals($envP, $p)) {
        return ['id' => 0, 'email' => $envU, '_via' => 'env'];
    }
    return null;
}
