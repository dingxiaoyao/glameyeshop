<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

$method = $_SERVER['REQUEST_METHOD'];
$id     = intval($_GET['id'] ?? 0);

try {
    $db = getDb();

    if ($method === 'GET') {
        // SELECT * 防止某条 ALTER 漏跑导致整体 500;新加列自动出现,旧 schema 也不会报错
        $stmt = $db->prepare('SELECT * FROM products ORDER BY sort_order ASC, id ASC');
        $stmt->execute();
        sendJson(['products' => $stmt->fetchAll()]);
    }

    $in = readInput();

    if ($method === 'POST') {
        // 校验
        $sku       = trim((string)($in['sku'] ?? ''));
        $category  = trim((string)($in['category'] ?? ''));
        $name      = trim((string)($in['name'] ?? ''));
        $shortDesc = trim((string)($in['short_description'] ?? ''));
        $desc      = trim((string)($in['description'] ?? ''));
        $price     = floatval($in['price'] ?? 0);
        $cmpPrice  = $in['compare_at_price'] !== '' && $in['compare_at_price'] !== null ? floatval($in['compare_at_price']) : null;
        $imageUrl  = trim((string)($in['image_url'] ?? ''));
        $stock     = intval($in['stock'] ?? 0);
        $isActive  = !empty($in['is_active']) ? 1 : 0;
        $sortOrder = intval($in['sort_order'] ?? 0);
        $isBundle  = !empty($in['is_bundle']) ? 1 : 0;
        // bundle_items 接受 JSON 字符串 或 数组 [{sku, qty}, ...]
        $biRaw = $in['bundle_items'] ?? '';
        if (is_array($biRaw)) {
            $biJson = json_encode(array_values($biRaw), JSON_UNESCAPED_UNICODE);
        } else {
            $biJson = trim((string)$biRaw);
            if ($biJson !== '') {
                $decoded = json_decode($biJson, true);
                if (!is_array($decoded)) sendJson(['error' => 'bundle_items must be a JSON array'], 422);
                $biJson = json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
            }
        }
        if ($biJson === '') $biJson = null;
        if ($isBundle && !$biJson) sendJson(['error' => 'Bundle requires bundle_items'], 422);

        // gallery_urls: 前端可能传 JSON string 或数组
        $galleryRaw = $in['gallery_urls'] ?? '';
        if (is_array($galleryRaw)) {
            $galleryJson = json_encode(array_values(array_filter($galleryRaw, 'is_string')));
        } else {
            $galleryJson = trim((string)$galleryRaw);
            // 验证是合法 JSON 数组
            if ($galleryJson !== '') {
                $decoded = json_decode($galleryJson, true);
                if (!is_array($decoded)) $galleryJson = '';
            }
        }
        if ($galleryJson === '') $galleryJson = null;

        if (!$sku || mb_strlen($sku) > 64)              sendJson(['error' => 'Invalid SKU'], 422);
        if (!in_array($category, ['mink','faux','magnetic','tools','bundle'], true)) sendJson(['error' => 'Invalid category'], 422);
        if (!$name || mb_strlen($name) > 200)            sendJson(['error' => 'Invalid name'], 422);
        if ($price <= 0)                                 sendJson(['error' => 'Invalid price'], 422);
        if ($stock < 0)                                  sendJson(['error' => 'Invalid stock'], 422);

        if ($id > 0) {
            // UPDATE
            $stmt = $db->prepare(
                'UPDATE products SET sku=:sku, category=:cat, name=:name, short_description=:short,
                                     description=:desc, price=:price, compare_at_price=:cmp,
                                     image_url=:img, gallery_urls=:gallery,
                                     stock=:stock, is_active=:active, sort_order=:sort,
                                     is_bundle=:isb, bundle_items=:bi
                 WHERE id=:id'
            );
            $stmt->execute([
                ':sku' => $sku, ':cat' => $category, ':name' => $name,
                ':short' => $shortDesc, ':desc' => $desc,
                ':price' => $price, ':cmp' => $cmpPrice,
                ':img' => $imageUrl, ':gallery' => $galleryJson,
                ':stock' => $stock,
                ':active' => $isActive, ':sort' => $sortOrder,
                ':isb' => $isBundle, ':bi' => $biJson, ':id' => $id,
            ]);
            sendJson(['success' => true, 'id' => $id]);
        } else {
            // INSERT
            $stmt = $db->prepare(
                'INSERT INTO products (sku, category, name, short_description, description,
                                       price, compare_at_price, image_url, gallery_urls,
                                       stock, is_active, sort_order, is_bundle, bundle_items)
                 VALUES (:sku, :cat, :name, :short, :desc, :price, :cmp, :img, :gallery,
                         :stock, :active, :sort, :isb, :bi)'
            );
            try {
                $stmt->execute([
                    ':sku' => $sku, ':cat' => $category, ':name' => $name,
                    ':short' => $shortDesc, ':desc' => $desc,
                    ':price' => $price, ':cmp' => $cmpPrice,
                    ':img' => $imageUrl, ':gallery' => $galleryJson,
                    ':stock' => $stock,
                    ':active' => $isActive, ':sort' => $sortOrder,
                    ':isb' => $isBundle, ':bi' => $biJson,
                ]);
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) sendJson(['error' => 'SKU already exists'], 409);
                throw $e;
            }
            sendJson(['success' => true, 'id' => (int)$db->lastInsertId()]);
        }
    }

    if ($method === 'DELETE') {
        if ($id <= 0) sendJson(['error' => 'Invalid id'], 422);
        // 软删除 - 仅设 is_active=0，避免外键失败
        $stmt = $db->prepare('UPDATE products SET is_active = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        sendJson(['success' => true]);
    }

    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
