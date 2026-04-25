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

// 生产环境保护
$appEnv = getenv('APP_ENV') ?: 'development';
if ($appEnv === 'production' && ($dbUser === 'root' || $dbPass === '')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server misconfiguration']);
    exit;
}

// 服务端权威产品价格清单（防客户端篡改）
const PRODUCT_CATALOG = [
    '经典方形眼镜' => 699.00,
    '轻盈商务眼镜' => 788.00,
    '复古圆形眼镜' => 798.00,
];

const MAX_QUANTITY_PER_ITEM = 50;
const MAX_ITEMS_PER_ORDER = 20;
const ALLOWED_PAYMENT_METHODS = ['stripe', 'paypal'];
const ALLOWED_ORDER_STATUSES = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];

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
        error_log(sprintf(
            '[GlamEye] %s | %s | %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
    }
    sendJson(['error' => $publicMessage], $status);
}

/**
 * HTTP Basic Auth 校验
 */
function requireAdminAuth(): void {
    $expectedUser = getenv('ADMIN_USER');
    $expectedPass = getenv('ADMIN_PASS');

    if (!$expectedUser || !$expectedPass) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Admin credentials not configured.';
        exit;
    }

    $providedUser = $_SERVER['PHP_AUTH_USER'] ?? '';
    $providedPass = $_SERVER['PHP_AUTH_PW'] ?? '';

    if (!hash_equals($expectedUser, $providedUser) || !hash_equals($expectedPass, $providedPass)) {
        header('WWW-Authenticate: Basic realm="GlamEye Admin"');
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Authentication required.';
        exit;
    }
}

/**
 * 解析 JSON 或 form 输入
 */
function readInput(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}
