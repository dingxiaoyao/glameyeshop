<?php
/**
 * UGC (User-Generated Content) public API
 *
 * GET ?limit=12 — approved UGC submissions for public homepage grid
 *   returns: { items: [{ id, image_url, caption, instagram_handle, related_product? }] }
 *
 * POST (auth required, optional in MVP — homepage grid mainly admin-curated)
 *   body: { image_url, caption?, instagram_handle?, related_product_id? }
 *   - status starts as 'pending', visible only after admin approval
 */
require_once __DIR__ . '/config.php';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDb();

    if ($method === 'GET') {
        $limit = max(1, min(50, intval($_GET['limit'] ?? 12)));
        $sql = "SELECT u.id, u.image_url, u.caption, u.instagram_handle, u.related_product_id,
                       p.name AS product_name, p.sku AS product_sku
                FROM ugc_submissions u
                LEFT JOIN products p ON p.id = u.related_product_id
                WHERE u.status = 'approved'
                ORDER BY u.sort_order ASC, u.created_at DESC
                LIMIT $limit";
        sendJson(['items' => $db->query($sql)->fetchAll()]);
    }

    if ($method === 'POST') {
        $user = requireUser();
        $in = readInput();
        $imageUrl = trim((string)($in['image_url'] ?? ''));
        $caption  = trim((string)($in['caption'] ?? ''));
        $handle   = trim((string)($in['instagram_handle'] ?? ''), " \t\n\r\0\x0B@");
        $rpId     = !empty($in['related_product_id']) ? intval($in['related_product_id']) : null;

        if ($imageUrl === '')           sendJson(['error' => 'Missing image_url'], 422);
        if (mb_strlen($caption) > 500)  sendJson(['error' => 'Caption too long'], 422);
        if (mb_strlen($handle) > 100)   sendJson(['error' => 'Handle too long'], 422);

        $stmt = $db->prepare(
            "INSERT INTO ugc_submissions (user_id, image_url, caption, instagram_handle, related_product_id, status)
             VALUES (:uid, :img, :cap, :h, :rp, 'pending')"
        );
        $stmt->execute([
            ':uid' => $user['id'], ':img' => $imageUrl,
            ':cap' => $caption, ':h' => $handle, ':rp' => $rpId,
        ]);
        sendJson(['success' => true, 'id' => intval($db->lastInsertId()), 'status' => 'pending']);
    }

    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
