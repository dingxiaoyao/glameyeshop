<?php
/**
 * Admin UGC moderation
 *
 * GET ?status=pending|approved|rejected|all
 *   → { items:[ ... ], total }
 *
 * POST { action: 'create'|'approve'|'reject'|'delete'|'reorder', ... }
 *   create: { image_url, caption?, instagram_handle?, related_product_id?, status?='approved' }
 *   approve / reject / delete: { id }
 *   reorder: { ids:[1,4,2,...] } — bulk sort_order assignment
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
        $where = ($status === 'all') ? '1=1' : 'u.status = :st';
        $params = ($status === 'all') ? [] : [':st' => $status];

        $countStmt = $db->prepare("SELECT COUNT(*) FROM ugc_submissions u WHERE $where");
        $countStmt->execute($params);
        $total = intval($countStmt->fetchColumn());

        $sql = "SELECT u.*, p.name AS product_name, p.sku AS product_sku
                FROM ugc_submissions u
                LEFT JOIN products p ON p.id = u.related_product_id
                WHERE $where
                ORDER BY u.sort_order ASC, u.created_at DESC
                LIMIT 200";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        sendJson(['items' => $stmt->fetchAll(), 'total' => $total]);
    }

    if ($method === 'POST') {
        $in = readInput();
        $action = strval($in['action'] ?? '');
        $id = intval($in['id'] ?? 0);

        if ($action === 'create') {
            $imageUrl = trim((string)($in['image_url'] ?? ''));
            $caption  = trim((string)($in['caption'] ?? ''));
            $handle   = trim((string)($in['instagram_handle'] ?? ''), " \t\n\r\0\x0B@");
            $rpId     = !empty($in['related_product_id']) ? intval($in['related_product_id']) : null;
            $status   = in_array($in['status'] ?? 'approved', ['pending','approved','rejected'], true)
                          ? $in['status'] : 'approved';
            if ($imageUrl === '') sendJson(['error' => 'Missing image_url'], 422);
            $stmt = $db->prepare(
                "INSERT INTO ugc_submissions (image_url, caption, instagram_handle, related_product_id, status)
                 VALUES (:img, :cap, :h, :rp, :st)"
            );
            $stmt->execute([':img' => $imageUrl, ':cap' => $caption, ':h' => $handle, ':rp' => $rpId, ':st' => $status]);
            sendJson(['success' => true, 'id' => intval($db->lastInsertId())]);
        }

        if ($action === 'reorder') {
            $ids = $in['ids'] ?? [];
            if (!is_array($ids)) sendJson(['error' => 'ids must be array'], 422);
            $upd = $db->prepare("UPDATE ugc_submissions SET sort_order = :s WHERE id = :id");
            foreach ($ids as $i => $idVal) {
                $idInt = intval($idVal);
                if ($idInt > 0) $upd->execute([':s' => $i, ':id' => $idInt]);
            }
            sendJson(['success' => true]);
        }

        if ($id <= 0) sendJson(['error' => 'Missing id'], 422);
        switch ($action) {
            case 'approve':
                $db->prepare("UPDATE ugc_submissions SET status='approved' WHERE id=:id")->execute([':id' => $id]);
                break;
            case 'reject':
                $db->prepare("UPDATE ugc_submissions SET status='rejected' WHERE id=:id")->execute([':id' => $id]);
                break;
            case 'delete':
                $db->prepare("DELETE FROM ugc_submissions WHERE id=:id")->execute([':id' => $id]);
                break;
            default:
                sendJson(['error' => 'Unknown action'], 422);
        }
        sendJson(['success' => true]);
    }

    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
