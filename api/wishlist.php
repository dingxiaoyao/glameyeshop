<?php
require_once __DIR__ . '/config.php';
$user = requireUser();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDb();
    if ($method === 'GET') {
        $stmt = $db->prepare(
            'SELECT p.id, p.sku, p.name, p.price, p.image_url, w.created_at AS added_at
             FROM user_wishlist w JOIN products p ON p.id = w.product_id
             WHERE w.user_id = :uid AND p.is_active = 1
             ORDER BY w.created_at DESC'
        );
        $stmt->execute([':uid' => $user['id']]);
        sendJson(['items' => $stmt->fetchAll()]);
    }

    $in = readInput();
    $productId = intval($in['product_id'] ?? 0);
    if ($productId <= 0) sendJson(['error' => 'Invalid product_id'], 422);

    if ($method === 'POST') {
        $stmt = $db->prepare('INSERT IGNORE INTO user_wishlist (user_id, product_id) VALUES (:u, :p)');
        $stmt->execute([':u' => $user['id'], ':p' => $productId]);
        sendJson(['success' => true]);
    }
    if ($method === 'DELETE') {
        $stmt = $db->prepare('DELETE FROM user_wishlist WHERE user_id = :u AND product_id = :p');
        $stmt->execute([':u' => $user['id'], ':p' => $productId]);
        sendJson(['success' => true]);
    }
    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
