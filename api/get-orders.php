<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

try {
    $db = getDb();
    $limit = max(1, min(200, intval($_GET['limit'] ?? 100)));
    $statusFilter = $_GET['status'] ?? '';

    $sql = 'SELECT id, user_id, customer_name, email, phone, address_line, address_line2,
                   city, state, postal_code, country,
                   product_name, quantity, subtotal, shipping, tax, amount, currency,
                   payment_method, status, notes,
                   carrier, tracking_number, tracking_url, shipped_at, delivered_at, estimated_delivery,
                   is_test, created_at, updated_at
            FROM orders';
    $params = [];
    if ($statusFilter && in_array($statusFilter, ALLOWED_ORDER_STATUSES, true)) {
        $sql .= ' WHERE status = :status';
        $params[':status'] = $statusFilter;
    }
    $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    if (!empty($orders)) {
        $ids = array_column($orders, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $itemStmt = $db->prepare(
            "SELECT order_id, product_name, sku, unit_price, quantity, line_total
             FROM order_items WHERE order_id IN ($ph) ORDER BY id ASC"
        );
        $itemStmt->execute($ids);
        $byOrder = [];
        foreach ($itemStmt->fetchAll() as $row) $byOrder[$row['order_id']][] = $row;
        foreach ($orders as &$o) $o['items'] = $byOrder[$o['id']] ?? [];
    }

    sendJson([
        'orders' => $orders,
        'allowed_statuses' => ALLOWED_ORDER_STATUSES,
    ]);
} catch (PDOException $e) {
    sendError('Failed to fetch orders', 500, $e);
}
