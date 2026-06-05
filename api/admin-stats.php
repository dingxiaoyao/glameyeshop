<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

try {
    $db = getDb();

    // KPI cards
    $kpi = [];

    // 今日订单/销售
    $today = $db->query(
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS rev
         FROM orders WHERE DATE(created_at) = CURDATE()
           AND status IN ('paid', 'processing', 'shipped', 'delivered')"
    )->fetch();
    $kpi['today_orders']   = (int)$today['cnt'];
    $kpi['today_revenue']  = (float)$today['rev'];

    // 本月订单/销售
    $month = $db->query(
        "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS rev
         FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())
           AND status IN ('paid', 'processing', 'shipped', 'delivered')"
    )->fetch();
    $kpi['month_orders']   = (int)$month['cnt'];
    $kpi['month_revenue']  = (float)$month['rev'];

    // 总数
    $kpi['total_orders']      = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $kpi['total_revenue']     = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('paid','processing','shipped','delivered')")->fetchColumn();
    $kpi['total_customers']   = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $kpi['total_products']    = (int)$db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();
    $kpi['pending_orders']    = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $kpi['low_stock']         = (int)$db->query("SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock < 30")->fetchColumn();
    $kpi['newsletter_subs']   = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1")->fetchColumn();

    // 30 天销售曲线
    $stmt = $db->query(
        "SELECT DATE(created_at) AS d,
                COUNT(*) AS orders,
                COALESCE(SUM(amount), 0) AS revenue
         FROM orders
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
           AND status IN ('paid', 'processing', 'shipped', 'delivered')
         GROUP BY DATE(created_at) ORDER BY d ASC"
    );
    $sales = $stmt->fetchAll();

    // 热销 SKU Top 10
    // 注意:orders 和 order_items 都有 product_name 字段,必须加 oi. 前缀
    // 否则 MySQL 报 "Column 'product_name' in field list is ambiguous"
    $stmt = $db->query(
        "SELECT oi.product_name, SUM(oi.quantity) AS qty, SUM(oi.line_total) AS rev
         FROM order_items oi
         JOIN orders o ON o.id = oi.order_id
         WHERE o.status IN ('paid','processing','shipped','delivered')
         GROUP BY oi.product_name
         ORDER BY qty DESC LIMIT 10"
    );
    $topProducts = $stmt->fetchAll();

    // 低库存预警
    $stmt = $db->query("SELECT id, sku, name, stock FROM products WHERE is_active = 1 AND stock < 30 ORDER BY stock ASC LIMIT 10");
    $lowStock = $stmt->fetchAll();

    // 最近订单
    $stmt = $db->query(
        "SELECT id, customer_name, amount, status, created_at
         FROM orders ORDER BY created_at DESC LIMIT 5"
    );
    $recentOrders = $stmt->fetchAll();

    sendJson([
        'kpi' => $kpi,
        'sales_30d' => $sales,
        'top_products' => $topProducts,
        'low_stock' => $lowStock,
        'recent_orders' => $recentOrders,
    ]);
} catch (Throwable $e) {
    // admin endpoint — 把异常类型+消息回吐到前端,方便定位
    // (stack trace 仍走 error_log,只暴露 class+message)
    error_log(sprintf('[admin-stats] %s | %s | %s:%d',
        get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    sendJson([
        'error' => sprintf('%s: %s', get_class($e), $e->getMessage()),
        'hint'  => 'See PHP error log for full stack trace.',
    ], 500);
}
