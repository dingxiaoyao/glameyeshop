<?php
require_once __DIR__ . '/config.php';
$user = requireUser();

try {
    $db = getDb();
    $stmt = $db->prepare(
        'SELECT id, customer_name, amount, currency, payment_method, status,
                carrier, tracking_number, tracking_url, shipped_at, delivered_at, estimated_delivery,
                is_test, created_at
         FROM orders WHERE user_id = :uid ORDER BY created_at DESC LIMIT 100'
    );
    $stmt->execute([':uid' => $user['id']]);
    $orders = $stmt->fetchAll();

    if (!empty($orders)) {
        $ids = array_column($orders, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $itemStmt = $db->prepare("SELECT order_id, product_name, quantity, line_total FROM order_items WHERE order_id IN ($ph)");
        $itemStmt->execute($ids);
        $byOrder = [];
        foreach ($itemStmt->fetchAll() as $r) $byOrder[$r['order_id']][] = $r;
        foreach ($orders as &$o) $o['items'] = $byOrder[$o['id']] ?? [];

        // Attach tracking events
        $evStmt = $db->prepare("SELECT order_id, status, location, description, occurred_at
                                 FROM order_tracking_events WHERE order_id IN ($ph)
                                 ORDER BY occurred_at DESC, id DESC");
        $evStmt->execute($ids);
        $byOrderEv = [];
        foreach ($evStmt->fetchAll() as $r) $byOrderEv[$r['order_id']][] = $r;
        foreach ($orders as &$o) $o['tracking_events'] = $byOrderEv[$o['id']] ?? [];
    }
    sendJson(['orders' => $orders]);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
