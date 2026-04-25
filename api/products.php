<?php
// 公开商品列表 API
require_once __DIR__ . '/config.php';

$category = $_GET['category'] ?? '';
$id = intval($_GET['id'] ?? 0);

try {
    $db = getDb();
    if ($id > 0) {
        $stmt = $db->prepare('SELECT id, sku, category, name, short_description, description, price, compare_at_price, image_url, stock FROM products WHERE id = :id AND is_active = 1');
        $stmt->execute([':id' => $id]);
        $p = $stmt->fetch();
        if (!$p) sendJson(['error' => 'Not found'], 404);
        sendJson(['product' => $p]);
    }
    $sql = 'SELECT id, sku, category, name, short_description, price, compare_at_price, image_url, stock FROM products WHERE is_active = 1';
    $params = [];
    if ($category && in_array($category, ['mink', 'faux', 'tools'], true)) {
        $sql .= ' AND category = :cat';
        $params[':cat'] = $category;
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendJson(['products' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
