<?php
// ============================================================
// GlamEye configuration
// ============================================================

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'glameyeshop';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// P0#3: 严守门 — root + 空密码组合 = 任何 PHP RCE 即拿全库,绝对不可接受
$appEnv = getenv('APP_ENV') ?: 'development';
$host   = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = in_array($host, ['localhost', '127.0.0.1', '127.0.0.1:8000', 'localhost:8000'], true)
               || strpos($host, '.local') !== false
               || strpos($host, ':8') !== false;  // 任何端口的本地开发

if ($dbUser === 'root' && $dbPass === '') {
    // 生产环境或非本机访问 → 直接拒绝启动,不允许裸 root 跑生产
    if ($appEnv === 'production' || !$isLocalhost) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Server misconfiguration: DB must use a dedicated user (not root) with a password. See STRIPE-SETUP.md or wait for the next deploy to auto-provision /etc/glameye/db.conf.',
        ]);
        error_log('[CONFIG] FATAL: DB root+empty credentials in production-like environment, refusing to start');
        exit;
    }
    // 本机开发模式 — 允许但警告
    error_log('[CONFIG] WARNING: running with DB root+empty credentials (dev mode, host=' . $host . ')');
}

const MAX_QUANTITY_PER_ITEM = 50;
const MAX_ITEMS_PER_ORDER = 30;
const ALLOWED_PAYMENT_METHODS = ['stripe', 'paypal'];
const ALLOWED_ORDER_STATUSES = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

const SESSION_COOKIE = 'glameye_sid';
const SESSION_LIFETIME = 60 * 60 * 24 * 30;   // 30 天

function getDb(): PDO {
    global $dsn, $dbUser, $dbPass, $options;
    return new PDO($dsn, $dbUser, $dbPass, $options);
}

function sendJson($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError(string $publicMessage, int $status = 500, ?Throwable $exception = null): void {
    if ($exception !== null) {
        error_log(sprintf('[GlamEye] %s | %s | %s:%d',
            get_class($exception), $exception->getMessage(),
            $exception->getFile(), $exception->getLine()));
    }
    sendJson(['error' => $publicMessage], $status);
}

function readInput(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function requireAdminAuth(): void {
    $expectedUser = getenv('ADMIN_USER');
    $expectedPass = getenv('ADMIN_PASS');
    if (!$expectedUser || !$expectedPass) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Admin credentials not configured.';
        exit;
    }
    $u = $_SERVER['PHP_AUTH_USER'] ?? '';
    $p = $_SERVER['PHP_AUTH_PW'] ?? '';

    // P0#2 (审计 P1#22): 防 BasicAuth 字典攻击 — 同 IP 20 次失败/15min 锁
    // 注意:rate-limit lib 依赖 DB,放在 hash_equals 之前 require 避免循环依赖
    require_once __DIR__ . '/lib/rate-limit.php';
    $ip = rateLimitClientIp();
    $bucket = "admin-basic-auth:$ip";
    if (rateLimitFailCount($bucket, 900) >= 20) {
        header('Retry-After: 900');
        http_response_code(429);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Too many failed admin login attempts. Wait 15 minutes.';
        exit;
    }

    if (!hash_equals($expectedUser, $u) || !hash_equals($expectedPass, $p)) {
        rateLimitFail($bucket);
        header('WWW-Authenticate: Basic realm="GlamEye Admin"');
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Authentication required.';
        exit;
    }
    // 成功 — 清掉历史失败
    rateLimitClear($bucket);
}

// ============================================================
// 用户 session（PHP native session）
// ============================================================
function startUserSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(SESSION_COOKIE);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function currentUser(): ?array {
    startUserSession();
    if (empty($_SESSION['user_id'])) return null;
    static $cached = null;
    if ($cached !== null && $cached['id'] === $_SESSION['user_id']) return $cached;
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT id, email, first_name, last_name, phone, is_subscribed, email_verified,
                                       is_test_account, oauth_provider, avatar_url, created_at
                              FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $cached = $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        $cached = null;
    }
    return $cached;
}

function requireUser(): array {
    $u = currentUser();
    if (!$u) sendJson(['error' => 'Authentication required'], 401);
    return $u;
}

/**
 * 从 DB 获取活跃产品价格（取代硬编码 catalog）
 */
function getProductByName(string $name): ?array {
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT id, sku, name, price, stock FROM products WHERE name = :name AND is_active = 1 LIMIT 1');
        $stmt->execute([':name' => $name]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function getProductBySku(string $sku): ?array {
    try {
        $db = getDb();
        $stmt = $db->prepare('SELECT id, sku, name, price, stock FROM products WHERE sku = :sku AND is_active = 1 LIMIT 1');
        $stmt->execute([':sku' => $sku]);
        return $stmt->fetch() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}
