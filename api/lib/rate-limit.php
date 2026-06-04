<?php
// ============================================================
// 通用限频中间件 — DB-backed sliding window
//
// 用法:
//   require_once __DIR__ . '/lib/rate-limit.php';
//   $bucket = 'order-lookup:' . rateLimitClientIp() . ':' . $email;
//   if (rateLimitFailCount($bucket, 900) >= 5) {
//       sendJson(['error' => 'Too many attempts, please wait 15 minutes'], 429);
//   }
//   // ...尝试操作...
//   if (失败) { rateLimitFail($bucket); }
//
// 设计原则:
//   - 只记录"失败"(成功不限频,避免合法用户因为多次查自己订单被锁)
//   - bucket 名包含 IP + 关键标识(email / user_id),防止单一维度被绕过
//   - DB 错误时降级为"允许"(避免 DB 抖动锁住整站),记日志
// ============================================================

require_once __DIR__ . '/../config.php';

/** 获取客户端真实 IP(信任 Cloudflare / Vultr 反向代理的 header) */
function rateLimitClientIp(): string {
    // 服务器自身的回环优先信任 X-Forwarded-For / CF-Connecting-IP
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trustedProxies = ['127.0.0.1', '::1'];
    if (in_array($remoteAddr, $trustedProxies, true)) {
        $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if ($cf) return trim($cf);
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) {
            $first = explode(',', $xff)[0];
            return trim($first);
        }
    }
    return $remoteAddr;
}

/** 记录一次失败尝试 */
function rateLimitFail(string $bucket): void {
    try {
        $db = getDb();
        $db->prepare('INSERT INTO rate_limit_log (bucket, ip) VALUES (:b, :ip)')
           ->execute([':b' => mb_substr($bucket, 0, 160), ':ip' => rateLimitClientIp()]);
    } catch (PDOException $e) {
        error_log('[RATE LIMIT] insert failed: ' . $e->getMessage());
    }
}

/** 返回当前窗口内(秒)的失败次数 */
function rateLimitFailCount(string $bucket, int $windowSeconds): int {
    try {
        $db = getDb();
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM rate_limit_log
             WHERE bucket = :b AND occurred_at > (NOW() - INTERVAL :sec SECOND)'
        );
        $stmt->execute([':b' => mb_substr($bucket, 0, 160), ':sec' => $windowSeconds]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('[RATE LIMIT] count failed: ' . $e->getMessage());
        return 0;  // DB 出错时不阻塞合法用户
    }
}

/** 成功后清掉历史失败记录(可选,适合登录这种"找回正确密码就清零"场景) */
function rateLimitClear(string $bucket): void {
    try {
        $db = getDb();
        $db->prepare('DELETE FROM rate_limit_log WHERE bucket = :b')
           ->execute([':b' => mb_substr($bucket, 0, 160)]);
    } catch (PDOException $e) {
        error_log('[RATE LIMIT] clear failed: ' . $e->getMessage());
    }
}

/** 守门:超限直接返 429 退出(便利包装) */
function rateLimitGuard(string $bucket, int $maxFails, int $windowSeconds, string $msg = 'Too many attempts'): void {
    if (rateLimitFailCount($bucket, $windowSeconds) >= $maxFails) {
        $retryAfter = $windowSeconds;
        header('Retry-After: ' . $retryAfter);
        sendJson(['error' => $msg, 'retry_after_seconds' => $retryAfter], 429);
    }
}

/** 定期清理(可在 admin / cron 调用,避免表无限增长) */
function rateLimitCleanup(int $olderThanSeconds = 86400): int {
    try {
        $db = getDb();
        $stmt = $db->prepare('DELETE FROM rate_limit_log WHERE occurred_at < (NOW() - INTERVAL :sec SECOND)');
        $stmt->execute([':sec' => $olderThanSeconds]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        return 0;
    }
}
