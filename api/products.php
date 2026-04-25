<?php
require_once __DIR__ . '/config.php';

$category = $_GET['category'] ?? '';
$id = intval($_GET['id'] ?? 0);
$sku = trim((string)($_GET['sku'] ?? ''));

try {
    $db = getDb();
    if ($id > 0 || $sku) {
        $sql = 'SELECT id, sku, category, style, name, short_description, description,
                       length_mm, band_type, reusable_count,
                       price, compare_at_price, image_url, gallery_urls, stock,
                       is_bestseller, is_new
                FROM products WHERE is_active = 1 AND ';
        if ($id > 0) { $sql .= 'id = :v'; $params = [':v' => $id]; }
        else         { $sql .= 'sku = :v'; $params = [':v' => $sku]; }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $p = $stmt->fetch();
        if (!$p) sendJson(['error' => 'Not found'], 404);
        if (!empty($p['gallery_urls'])) {
            $g = json_decode($p['gallery_urls'], true);
            $p['gallery_urls'] = is_array($g) ? $g : [];
        } else {
            $p['gallery_urls'] = [];
        }
        sendJson(['product' => $p]);
    }
    $sql = 'SELECT id, sku, category, style, name, short_description,
                   length_mm, band_type, reusable_count,
                   price, compare_at_price, image_url, stock,
                   is_bestseller, is_new
            FROM products WHERE is_active = 1';
    $params = [];
    if ($category && in_array($category, ['mink', 'faux', 'magnetic', 'tools'], true)) {
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
