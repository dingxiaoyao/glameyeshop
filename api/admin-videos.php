<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

$method = $_SERVER['REQUEST_METHOD'];
$id = intval($_GET['id'] ?? 0);

try {
    $db = getDb();

    if ($method === 'GET') {
        $stmt = $db->query(
            'SELECT id, video_id, creator_handle, video_url, title, description, cover_url,
                    related_product_id, is_featured, is_active, sort_order, views, created_at
             FROM tiktok_videos ORDER BY sort_order ASC, created_at DESC'
        );
        sendJson(['videos' => $stmt->fetchAll()]);
    }

    $in = readInput();

    if ($method === 'POST') {
        $videoUrl = trim((string)($in['video_url'] ?? ''));
        // 解析 TikTok URL → video_id + handle
        // 例：https://www.tiktok.com/@username/video/7123456789012345678
        if (!preg_match('#tiktok\.com/@([\w._-]+)/video/(\d+)#', $videoUrl, $m)) {
            sendJson(['error' => 'Invalid TikTok URL. Format: https://www.tiktok.com/@user/video/12345...'], 422);
        }
        $handle = $m[1];
        $videoId = $m[2];

        $title = trim((string)($in['title'] ?? ''));
        $desc  = trim((string)($in['description'] ?? ''));
        $cover = trim((string)($in['cover_url'] ?? ''));
        $relatedProduct = !empty($in['related_product_id']) ? intval($in['related_product_id']) : null;
        $featured = !empty($in['is_featured']) ? 1 : 0;
        $active   = isset($in['is_active']) ? (!empty($in['is_active']) ? 1 : 0) : 1;
        $sortOrder = intval($in['sort_order'] ?? 0);

        if ($id > 0) {
            $stmt = $db->prepare(
                'UPDATE tiktok_videos
                 SET video_id=:vid, creator_handle=:h, video_url=:url, title=:t, description=:d,
                     cover_url=:c, related_product_id=:rp, is_featured=:f, is_active=:a, sort_order=:so
                 WHERE id=:id'
            );
            $stmt->execute([
                ':vid' => $videoId, ':h' => $handle, ':url' => $videoUrl,
                ':t' => $title, ':d' => $desc, ':c' => $cover,
                ':rp' => $relatedProduct, ':f' => $featured, ':a' => $active, ':so' => $sortOrder,
                ':id' => $id,
            ]);
            sendJson(['success' => true, 'id' => $id]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO tiktok_videos (video_id, creator_handle, video_url, title, description, cover_url, related_product_id, is_featured, is_active, sort_order)
                 VALUES (:vid, :h, :url, :t, :d, :c, :rp, :f, :a, :so)'
            );
            $stmt->execute([
                ':vid' => $videoId, ':h' => $handle, ':url' => $videoUrl,
                ':t' => $title, ':d' => $desc, ':c' => $cover,
                ':rp' => $relatedProduct, ':f' => $featured, ':a' => $active, ':so' => $sortOrder,
            ]);
            sendJson(['success' => true, 'id' => (int)$db->lastInsertId()]);
        }
    }

    if ($method === 'DELETE') {
        if ($id <= 0) sendJson(['error' => 'Invalid id'], 422);
        $stmt = $db->prepare('DELETE FROM tiktok_videos WHERE id = :id');
        $stmt->execute([':id' => $id]);
        sendJson(['success' => true]);
    }

    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
