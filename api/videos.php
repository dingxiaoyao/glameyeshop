<?php
// Public TikTok videos API
require_once __DIR__ . '/config.php';

try {
    $db = getDb();
    $featured = !empty($_GET['featured']);
    $limit = max(1, min(50, intval($_GET['limit'] ?? 20)));

    $sql = 'SELECT id, video_id, creator_handle, video_url, title, description, cover_url, related_product_id, is_featured, views
            FROM tiktok_videos WHERE is_active = 1';
    if ($featured) $sql .= ' AND is_featured = 1';
    $sql .= ' ORDER BY sort_order ASC, created_at DESC LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute();
    sendJson(['videos' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
