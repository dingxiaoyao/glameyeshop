<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

try {
    $db = getDb();
    $limit = max(1, min(200, intval($_GET['limit'] ?? 50)));
    $statusFilter = $_GET['status'] ?? '';

    if ($statusFilter && in_array($statusFilter, ALLOWED_ORDER_STATUSES, true)) {
        $stmt = $db->prepare(
            'SELECT id, customer_name, email, phone, address_line, city, postal_code,
                    product_name, quantity, amount, payment_method, status, created_at, updated_at
             FROM orders WHERE status = :status
             ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute([':status' => $statusFilter]);
    } else {
        $stmt = $db->prepare(
            'SELECT id, customer_name, email, phone, address_line, city, postal_code,
                    product_name, quantity, amount, payment_method, status, created_at, updated_at
             FROM orders ORDER BY created_at DESC LIMIT ' . $limit
        );
        $stmt->execute();
    }
    $orders = $stmt->fetchAll();

    // 拉每个订单的明细
    if (!empty($orders)) {
        $ids = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $itemStmt = $db->prepare(
            "SELECT order_id, product_name, unit_price, quantity, line_total
             FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC"
        );
        $itemStmt->execute($ids);
        $itemsByOrder = [];
        foreach ($itemStmt->fetchAll() as $row) {
            $itemsByOrder[$row['order_id']][] = $row;
        }
        foreach ($orders as &$o) {
            $o['items'] = $itemsByOrder[$o['id']] ?? [];
        }
    }

    sendJson([
        'orders' => $orders,
        'allowed_statuses' => ALLOWED_ORDER_STATUSES,
    ]);
} catch (PDOException $exception) {
    sendError('Failed to fetch orders', 500, $exception);
}
