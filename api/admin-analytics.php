<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

try {
    $db = getDb();
    $excludeBots = !isset($_GET['bots']) || $_GET['bots'] === '0';
    $botClause = $excludeBots ? ' AND is_bot = 0' : '';

    // KPI
    $kpi = [];
    $kpi['today_views']    = (int)$db->query("SELECT COUNT(*) FROM page_views WHERE DATE(created_at) = CURDATE() $botClause")->fetchColumn();
    $kpi['today_uniq']     = (int)$db->query("SELECT COUNT(DISTINCT ip) FROM page_views WHERE DATE(created_at) = CURDATE() $botClause")->fetchColumn();
    $kpi['week_views']     = (int)$db->query("SELECT COUNT(*) FROM page_views WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) $botClause")->fetchColumn();
    $kpi['week_uniq']      = (int)$db->query("SELECT COUNT(DISTINCT ip) FROM page_views WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) $botClause")->fetchColumn();
    $kpi['month_views']    = (int)$db->query("SELECT COUNT(*) FROM page_views WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) $botClause")->fetchColumn();
    $kpi['total_views']    = (int)$db->query("SELECT COUNT(*) FROM page_views WHERE 1=1 $botClause")->fetchColumn();
    $kpi['total_uniq_ips'] = (int)$db->query("SELECT COUNT(DISTINCT ip) FROM page_views WHERE 1=1 $botClause")->fetchColumn();
    $kpi['bot_views']      = (int)$db->query("SELECT COUNT(*) FROM page_views WHERE is_bot = 1")->fetchColumn();

    // 30 天曲线
    $chart = $db->query(
        "SELECT DATE(created_at) AS d, COUNT(*) AS views, COUNT(DISTINCT ip) AS uniq
         FROM page_views
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) $botClause
         GROUP BY DATE(created_at) ORDER BY d ASC"
    )->fetchAll();

    // 热门页面 Top 10
    $topPages = $db->query(
        "SELECT path, COUNT(*) AS views, COUNT(DISTINCT ip) AS uniq
         FROM page_views WHERE 1=1 $botClause
         GROUP BY path ORDER BY views DESC LIMIT 15"
    )->fetchAll();

    // 来源 Top 10
    $topRefs = $db->query(
        "SELECT
           CASE
             WHEN referer = '' THEN '(direct)'
             ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, '/', 3), '://', -1)
           END AS source,
           COUNT(*) AS views
         FROM page_views WHERE 1=1 $botClause
         GROUP BY source ORDER BY views DESC LIMIT 15"
    )->fetchAll();

    // Top IP（不含 bot）
    $topIps = $db->query(
        "SELECT ip, COUNT(*) AS views, MAX(created_at) AS last_seen, GROUP_CONCAT(DISTINCT path ORDER BY created_at DESC SEPARATOR ' | ') AS recent_paths
         FROM page_views WHERE is_bot = 0
         GROUP BY ip ORDER BY views DESC LIMIT 20"
    )->fetchAll();
    // 截断 recent_paths 显示
    foreach ($topIps as &$r) {
        $r['recent_paths'] = mb_substr($r['recent_paths'], 0, 200);
    }
    unset($r);

    // 最近 30 条
    $recent = $db->query(
        "SELECT path, ip, user_agent, referer, is_bot, created_at
         FROM page_views ORDER BY id DESC LIMIT 50"
    )->fetchAll();

    sendJson([
        'kpi' => $kpi,
        'chart_30d' => $chart,
        'top_pages' => $topPages,
        'top_referrers' => $topRefs,
        'top_ips' => $topIps,
        'recent' => $recent,
    ]);
} catch (PDOException $e) {
    sendError('Analytics failed', 500, $e);
}
