<?php
// 订单物流追踪管理（admin）
require_once __DIR__ . '/config.php';
requireAdminAuth();

$method = $_SERVER['REQUEST_METHOD'];

const CARRIER_URLS = [
    'USPS'   => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=',
    'UPS'    => 'https://www.ups.com/track?tracknum=',
    'FedEx'  => 'https://www.fedex.com/fedextrack/?trknbr=',
    'DHL'    => 'https://www.dhl.com/en/express/tracking.html?AWB=',
    'USPS_INTL' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=',
    'OTHER'  => '',
];
const ALLOWED_TRACK_STATUSES = ['created','paid','processing','shipped','in_transit','out_for_delivery','delivered','exception','returned'];

try {
    $db = getDb();

    if ($method === 'GET') {
        // 列出某订单的所有 tracking events
        $orderId = intval($_GET['order_id'] ?? 0);
        if ($orderId <= 0) sendJson(['error' => 'order_id required'], 422);
        $stmt = $db->prepare(
            'SELECT id, status, description, location, occurred_at, created_at
             FROM order_tracking_events WHERE order_id = :id
             ORDER BY occurred_at DESC, id DESC'
        );
        $stmt->execute([':id' => $orderId]);
        $events = $stmt->fetchAll();
        // 同时返回订单主体的 tracking 信息
        $stmt = $db->prepare('SELECT carrier, tracking_number, tracking_url, shipped_at, delivered_at, estimated_delivery FROM orders WHERE id = :id');
        $stmt->execute([':id' => $orderId]);
        sendJson([
            'order' => $stmt->fetch() ?: null,
            'events' => $events,
            'carriers' => array_keys(CARRIER_URLS),
            'allowed_statuses' => ALLOWED_TRACK_STATUSES,
        ]);
    }

    if ($method === 'POST') {
        $in = readInput();
        $orderId = intval($in['order_id'] ?? 0);
        if ($orderId <= 0) sendJson(['error' => 'order_id required'], 422);

        // 更新订单的物流主信息
        if (isset($in['carrier']) || isset($in['tracking_number'])) {
            $carrier = trim((string)($in['carrier'] ?? ''));
            $trackNum = trim((string)($in['tracking_number'] ?? ''));
            $estimated = $in['estimated_delivery'] ?? null;
            // 自动生成 tracking_url
            $trackUrl = '';
            if (!empty($in['tracking_url'])) {
                $trackUrl = trim((string)$in['tracking_url']);
            } elseif ($carrier && isset(CARRIER_URLS[$carrier]) && CARRIER_URLS[$carrier] && $trackNum) {
                $trackUrl = CARRIER_URLS[$carrier] . urlencode($trackNum);
            }

            $stmt = $db->prepare(
                'UPDATE orders SET carrier=:c, tracking_number=:t, tracking_url=:u, estimated_delivery=:e WHERE id=:id'
            );
            $stmt->execute([
                ':c' => $carrier ?: null,
                ':t' => $trackNum ?: null,
                ':u' => $trackUrl ?: null,
                ':e' => $estimated ?: null,
                ':id' => $orderId,
            ]);
        }

        // 添加新 event
        if (!empty($in['event_status'])) {
            $status = $in['event_status'];
            if (!in_array($status, ALLOWED_TRACK_STATUSES, true)) sendJson(['error' => 'Invalid status'], 422);

            $desc = trim((string)($in['event_description'] ?? ''));
            $loc  = trim((string)($in['event_location'] ?? ''));
            $occurred = $in['event_occurred_at'] ?? null;

            $stmt = $db->prepare(
                'INSERT INTO order_tracking_events (order_id, status, description, location, occurred_at)
                 VALUES (:id, :s, :d, :l, COALESCE(:o, NOW()))'
            );
            $stmt->execute([
                ':id' => $orderId, ':s' => $status,
                ':d' => $desc, ':l' => $loc,
                ':o' => $occurred ?: null,
            ]);

            // 同步更新订单主状态 + shipped_at/delivered_at
            $orderUpdates = [];
            $params = [':id' => $orderId];
            if (in_array($status, ['shipped','in_transit','out_for_delivery','delivered','exception','returned','paid','processing'], true)) {
                $orderUpdates[] = 'status = :s'; $params[':s'] = $status;
            }
            if ($status === 'shipped') {
                $orderUpdates[] = 'shipped_at = COALESCE(shipped_at, NOW())';
            }
            if ($status === 'delivered') {
                $orderUpdates[] = 'delivered_at = COALESCE(delivered_at, NOW())';
            }
            if (!empty($orderUpdates)) {
                $sql = 'UPDATE orders SET ' . implode(', ', $orderUpdates) . ' WHERE id = :id';
                $db->prepare($sql)->execute($params);
            }
        }

        sendJson(['success' => true]);
    }

    if ($method === 'DELETE') {
        $eventId = intval($_GET['event_id'] ?? 0);
        if ($eventId <= 0) sendJson(['error' => 'event_id required'], 422);
        $stmt = $db->prepare('DELETE FROM order_tracking_events WHERE id = :id');
        $stmt->execute([':id' => $eventId]);
        sendJson(['success' => true]);
    }

    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
