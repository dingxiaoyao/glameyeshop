<?php
// Admin 看 stripe_webhook_events 历史 — 排查支付/退款问题
// GET ?status=received|processed|duplicate|error|all  ?page=1  ?per=50
require_once __DIR__ . '/config.php';
requireAdminAuth();

try {
    $db = getDb();
    $status = $_GET['status'] ?? 'all';
    $allowed = ['received', 'processed', 'duplicate', 'error', 'ignored', 'amount_mismatch', 'skipped', 'all'];
    if (!in_array($status, $allowed, true)) $status = 'all';

    $page = max(1, intval($_GET['page'] ?? 1));
    $per  = max(10, min(200, intval($_GET['per'] ?? 50)));
    $offset = ($page - 1) * $per;

    $where = ($status === 'all') ? '1=1' : 'status = :st';
    $params = ($status === 'all') ? [] : [':st' => $status];

    $count = $db->prepare("SELECT COUNT(*) FROM stripe_webhook_events WHERE $where");
    $count->execute($params);
    $total = intval($count->fetchColumn());

    $sql = "SELECT event_id, type, order_id, status, error,
                   LEFT(raw_payload, 600) AS raw_excerpt,
                   received_at, processed_at
            FROM stripe_webhook_events
            WHERE $where
            ORDER BY received_at DESC
            LIMIT $per OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    sendJson([
        'events' => $stmt->fetchAll(),
        'pagination' => [
            'page'        => $page,
            'per_page'    => $per,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $per),
        ],
        'status_filter' => $status,
    ]);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
