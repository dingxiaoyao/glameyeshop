<?php
// ============================================================
// GLAMEYE configuration
// ============================================================

// 1. Database configuration（仅从环境变量读取；生产环境必须设置）
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'glameyeshop';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// 2. 生产环境保护：禁止使用空密码 root 账号
$appEnv = getenv('APP_ENV') ?: 'development';
if ($appEnv === 'production' && ($dbUser === 'root' || $dbPass === '')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server misconfiguration']);
    exit;
}

// 3. 服务端权威产品价格清单（防止客户端篡改）
//    新增产品时在此添加，价格变动只在服务端修改
const PRODUCT_CATALOG = [
    '经典方形眼镜' => 699.00,
    '轻盈商务眼镜' => 788.00,
    '复古圆形眼镜' => 798.00,
];

// 4. 业务参数限制
const MAX_QUANTITY_PER_ORDER = 50;
const ALLOWED_PAYMENT_METHODS = ['stripe', 'paypal'];

function getDb(): PDO
{
    global $dsn, $dbUser, $dbPass, $options;
    return new PDO($dsn, $dbUser, $dbPass, $options);
}

function sendJson($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 安全错误处理：记录详细错误到日志，向客户端返回通用消息
 */
function sendError(string $publicMessage, int $status = 500, ?Throwable $exception = null): void
{
    if ($exception !== null) {
        error_log(sprintf(
            '[GLAMEYE] %s | %s | %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
    }
    sendJson(['error' => $publicMessage], $status);
}

/**
 * HTTP Basic Auth 校验（用于 admin/* 与受保护的 API）
 * 凭据来自环境变量 ADMIN_USER 和 ADMIN_PASS（必须设置）
 */
function requireAdminAuth(): void
{
    $expectedUser = getenv('ADMIN_USER');
    $expectedPass = getenv('ADMIN_PASS');

    // 未配置凭据时拒绝访问，避免无意暴露
    if (!$expectedUser || !$expectedPass) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Admin credentials not configured.';
        exit;
    }

    $providedUser = $_SERVER['PHP_AUTH_USER'] ?? '';
    $providedPass = $_SERVER['PHP_AUTH_PW'] ?? '';

    // hash_equals 防止时序攻击
    $userOk = hash_equals($expectedUser, $providedUser);
    $passOk = hash_equals($expectedPass, $providedPass);

    if (!$userOk || !$passOk) {
        header('WWW-Authenticate: Basic realm="GLAMEYE Admin"');
        http_response_code(401);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Authentication required.';
        exit;
    }
}
