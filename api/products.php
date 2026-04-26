<?php
require_once __DIR__ . '/config.php';

$category = $_GET['category'] ?? '';
$id = intval($_GET['id'] ?? 0);
$sku = trim((string)($_GET['sku'] ?? ''));

try {
    $db = getDb();
    // SELECT * 防止 schema 缺列导致 SQL 整体报错。前端只用它需要的字段。
    if ($id > 0 || $sku) {
        $sql = 'SELECT * FROM products WHERE is_active = 1 AND ';
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
    $sql = 'SELECT * FROM products WHERE is_active = 1';
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
