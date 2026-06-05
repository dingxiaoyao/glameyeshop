<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(['error' => 'Method not allowed'], 405);
}

$input = readInput();
$orderId = intval($input['order_id'] ?? 0);
$status  = (string)($input['status'] ?? '');
$force   = !empty($input['force']);  // admin 显式 override 时传 true

if ($orderId <= 0)                                    sendJson(['error' => 'Invalid order_id'], 422);
if (!in_array($status, ALLOWED_ORDER_STATUSES, true)) sendJson(['error' => 'Invalid status'], 422);

// P1#12: 订单状态机白名单 — 每个状态只能转换到合理的下游
// 避免运营误操作把 delivered 改回 pending、refunded 改回 paid 等
const STATUS_TRANSITIONS = [
    'pending'    => ['paid', 'cancelled', 'expired'],
    'paid'       => ['processing', 'cancelled', 'refunded'],
    'processing' => ['shipped', 'cancelled', 'refunded'],
    'shipped'    => ['delivered', 'refunded'],
    'delivered'  => ['refunded'],
    // 终态(不允许再转,除非 force=true)
    'expired'    => [],
    'cancelled'  => [],
    'refunded'   => [],
];

try {
    $db = getDb();
    $cur = $db->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1');
    $cur->execute([':id' => $orderId]);
    $row = $cur->fetch();
    if (!$row) sendJson(['error' => 'Order not found'], 404);

    $currentStatus = $row['status'];

    // 同状态无变化
    if ($currentStatus === $status) {
        sendJson(['success' => true, 'order_id' => $orderId, 'status' => $status, 'noop' => true]);
    }

    // 校验转换合法性
    $allowed = STATUS_TRANSITIONS[$currentStatus] ?? [];
    if (!in_array($status, $allowed, true)) {
        if (!$force) {
            sendJson([
                'error'    => "Invalid transition: $currentStatus → $status. Pass force=true to override.",
                'current'  => $currentStatus,
                'allowed'  => $allowed,
            ], 422);
        }
        // force=1 — 记 log 但允许
        error_log("[update-order-status] FORCE transition by admin: order #$orderId $currentStatus → $status");
    }

    $stmt = $db->prepare('UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id');
    $stmt->execute([':status' => $status, ':id' => $orderId]);

    // 记 tracking event
    try {
        $db->prepare(
            "INSERT INTO order_tracking_events (order_id, status, description, created_at)
             VALUES (:oid, :st, :desc, NOW())"
        )->execute([
            ':oid'  => $orderId,
            ':st'   => $status,
            ':desc' => sprintf('Status changed by admin: %s → %s%s', $currentStatus, $status, $force ? ' (forced)' : ''),
        ]);
    } catch (Throwable $e) { /* tracking event 失败不阻塞主操作 */ }

    sendJson(['success' => true, 'order_id' => $orderId, 'status' => $status, 'previous' => $currentStatus]);
} catch (PDOException $e) {
    sendError('Failed to update order', 500, $e);
}
