<?php
/**
 * Admin Reviews moderation
 *
 * GET ?status=pending|approved|rejected|all (default pending)
 *     ?page=1&per=50
 *   → { reviews:[ ...joined product fields... ], total }
 *
 * POST { id, action: 'approve'|'reject'|'feature'|'unfeature'|'delete' }
 *   → { success:true, review:{...} | null }
 */
require_once __DIR__ . '/config.php';
requireAdminAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDb();

    if ($method === 'GET') {
        $status = $_GET['status'] ?? 'pending';
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }
        $page = max(1, intval($_GET['page'] ?? 1));
        $per  = max(1, min(200, intval($_GET['per'] ?? 50)));
        $offset = ($page - 1) * $per;

        $where = ($status === 'all') ? '1=1' : 'r.status = :st';
        $params = ($status === 'all') ? [] : [':st' => $status];

        $countStmt = $db->prepare("SELECT COUNT(*) FROM reviews r WHERE $where");
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        $sql = "SELECT r.id, r.product_id, r.user_id, r.order_id, r.reviewer_name, r.reviewer_location,
                       r.rating, r.title, r.body, r.photo_urls, r.status, r.is_verified_buyer,
                       r.is_featured, r.helpful_count, r.created_at, r.updated_at,
                       p.name AS product_name, p.sku AS product_sku, p.image_url AS product_image,
                       u.email AS user_email
                FROM reviews r
                LEFT JOIN products p ON p.id = r.product_id
                LEFT JOIN users u ON u.id = r.user_id
                WHERE $where
                ORDER BY r.created_at DESC
                LIMIT $per OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['photo_urls'] = $r['photo_urls'] ? (json_decode($r['photo_urls'], true) ?: []) : [];
        }
        sendJson(['reviews' => $rows, 'total' => $total]);
    }

    if ($method === 'POST') {
        $in = readInput();
        $id     = intval($in['id'] ?? 0);
        $action = strval($in['action'] ?? '');
        if ($id <= 0) sendJson(['error' => 'Missing id'], 422);

        switch ($action) {
            case 'approve':
                $db->prepare("UPDATE reviews SET status='approved', updated_at=NOW() WHERE id=:id")
                   ->execute([':id' => $id]);
                break;
            case 'reject':
                $db->prepare("UPDATE reviews SET status='rejected', is_featured=0, updated_at=NOW() WHERE id=:id")
                   ->execute([':id' => $id]);
                break;
            case 'feature':
                // featuring auto-approves
                $db->prepare("UPDATE reviews SET status='approved', is_featured=1, updated_at=NOW() WHERE id=:id")
                   ->execute([':id' => $id]);
                break;
            case 'unfeature':
                $db->prepare("UPDATE reviews SET is_featured=0, updated_at=NOW() WHERE id=:id")
                   ->execute([':id' => $id]);
                break;
            case 'delete':
                $db->prepare("DELETE FROM reviews WHERE id=:id")->execute([':id' => $id]);
                sendJson(['success' => true, 'review' => null]);
                break;
            default:
                sendJson(['error' => 'Unknown action'], 422);
        }

        $r = $db->prepare("SELECT * FROM reviews WHERE id = :id");
        $r->execute([':id' => $id]);
        $review = $r->fetch() ?: null;
        if ($review && $review['photo_urls']) {
            $review['photo_urls'] = json_decode($review['photo_urls'], true) ?: [];
        } elseif ($review) {
            $review['photo_urls'] = [];
        }
        sendJson(['success' => true, 'review' => $review]);
    }

    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
