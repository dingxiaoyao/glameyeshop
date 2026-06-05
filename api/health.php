<?php
// ============================================================
// 健康检查 — 给 uptime 监控 / load balancer / GitHub Actions 用
// GET /api/health.php → 200 OK { ok:true, db:true, ... } 或 503
// 不依赖 admin auth,但只返回布尔结果(不泄露 schema / DB 名)
// ============================================================
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

$result = [
    'ok'   => true,
    'time' => date('c'),
    'checks' => [],
];

// 1. DB 连接
try {
    $db = getDb();
    $row = $db->query('SELECT 1 AS one')->fetch();
    $result['checks']['db'] = (!empty($row) && intval($row['one']) === 1);
} catch (Throwable $e) {
    $result['checks']['db'] = false;
    $result['ok'] = false;
    error_log('[health] db check failed: ' . $e->getMessage());
}

// 2. 关键表存在
if ($result['checks']['db']) {
    try {
        $expectedTables = ['products', 'orders', 'users', 'site_settings'];
        foreach ($expectedTables as $t) {
            $s = $db->prepare("SELECT COUNT(*) FROM information_schema.tables
                               WHERE table_schema = DATABASE() AND table_name = :t");
            $s->execute([':t' => $t]);
            if (intval($s->fetchColumn()) !== 1) {
                $result['checks']['tables'] = false;
                $result['ok'] = false;
                break;
            }
        }
        $result['checks']['tables'] = $result['checks']['tables'] ?? true;
    } catch (Throwable $e) {
        $result['checks']['tables'] = false;
        $result['ok'] = false;
    }
}

// 3. uploads 目录可写
$uploadsDir = realpath(__DIR__ . '/../uploads');
$result['checks']['uploads_writable'] = ($uploadsDir && is_writable($uploadsDir));
if (!$result['checks']['uploads_writable']) $result['ok'] = false;

// 4. PHP 版本(只 warning,不影响 ok)
$result['php_version'] = PHP_VERSION;

http_response_code($result['ok'] ? 200 : 503);
echo json_encode($result);
