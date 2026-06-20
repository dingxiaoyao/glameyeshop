<?php
// public GET — 拉当前 active 的第一个 banner(按 sort_order)
// 同时返回当前 user 的关键统计(订单数/总消费/收藏数)— 让 account 页一次性拿全部数据
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/account-banner-schema.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $db = getDb();
    ensureAccountBannerSchema($db);

    $stmt = $db->query(
        "SELECT id, image_url, title, subtitle, cta_text, cta_url
         FROM account_banners
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC
         LIMIT 1"
    );
    $banner = $stmt->fetch() ?: null;

    // 顺手返回 user 统计 — 如果有 session
    startUserSession();
    $stats = null;
    if (!empty($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $ord = $db->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS spent
             FROM orders
             WHERE user_id = :uid AND status IN ('paid','processing','shipped','delivered')"
        );
        $ord->execute([':uid' => $uid]);
        $o = $ord->fetch();
        $w = $db->prepare("SELECT COUNT(*) FROM wishlist_items WHERE user_id = :uid");
        try {
            $w->execute([':uid' => $uid]);
            $wishCount = (int)$w->fetchColumn();
        } catch (Throwable $_) { $wishCount = 0; }  // 表不存在容错
        $pending = $db->prepare(
            "SELECT COUNT(*) FROM orders
             WHERE user_id = :uid AND status IN ('pending','paid','processing','shipped')"
        );
        $pending->execute([':uid' => $uid]);

        $stats = [
            'orders_count' => (int)$o['cnt'],
            'total_spent'  => (float)$o['spent'],
            'wishlist_count' => $wishCount,
            'active_orders' => (int)$pending->fetchColumn(),
        ];
    }

    sendJson(['banner' => $banner, 'stats' => $stats]);
} catch (Throwable $e) {
    error_log('[account-banner] ' . get_class($e) . ': ' . $e->getMessage());
    sendJson(['banner' => null, 'stats' => null], 200);
}
