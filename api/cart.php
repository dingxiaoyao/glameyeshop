<?php
// ============================================================
// 服务端购物车 — 给登录用户跨设备 / 跨会话保留 cart (P1#15)
// GET  → { items: [...] }            读取服务器 cart
// POST { items: [...] } → { items: [...] }
//      合并 localStorage cart 到服务器:同 sku 数量取较大值,price/name/image 用新值
//      返回合并后的最终 cart 供前端覆盖 localStorage
// ============================================================
require_once __DIR__ . '/config.php';

$user = requireUser();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDb();

    if ($method === 'GET') {
        $stmt = $db->prepare('SELECT items_json FROM user_carts WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $user['id']]);
        $row = $stmt->fetch();
        $items = [];
        if ($row && $row['items_json']) {
            $arr = json_decode($row['items_json'], true);
            if (is_array($arr)) $items = $arr;
        }
        sendJson(['items' => $items]);
    }

    // DELETE → 清空服务端 cart(支付成功后 Cart.clear() 调用)
    if ($method === 'DELETE') {
        $db->prepare('DELETE FROM user_carts WHERE user_id = :uid')
           ->execute([':uid' => $user['id']]);
        sendJson(['success' => true, 'cleared' => true]);
    }

    if ($method === 'POST') {
        $in = readInput();
        $clientItems = is_array($in['items'] ?? null) ? $in['items'] : [];

        $validated = [];
        foreach ($clientItems as $it) {
            if (!is_array($it) || !isset($it['sku']) || !is_string($it['sku'])) continue;
            $sku = trim($it['sku']);
            if ($sku === '') continue;
            $price = floatval($it['price'] ?? 0);
            $qty   = max(1, min(50, intval($it['quantity'] ?? 1)));
            if (!is_finite($price) || $price < 0) continue;
            $validated[$sku] = [
                'sku'      => $sku,
                'name'     => mb_substr((string)($it['name']  ?? ''), 0, 200),
                'price'    => $price,
                'image'    => mb_substr((string)($it['image'] ?? ''), 0, 500),
                'quantity' => $qty,
            ];
        }

        $stmt = $db->prepare('SELECT items_json FROM user_carts WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $user['id']]);
        $serverIndexed = [];
        if ($row = $stmt->fetch()) {
            $arr = json_decode($row['items_json'], true);
            if (is_array($arr)) {
                foreach ($arr as $it) {
                    if (is_array($it) && isset($it['sku']) && is_string($it['sku'])) {
                        $serverIndexed[$it['sku']] = $it;
                    }
                }
            }
        }

        foreach ($validated as $sku => $cit) {
            if (isset($serverIndexed[$sku])) {
                $serverQty = intval($serverIndexed[$sku]['quantity'] ?? 0);
                $cit['quantity'] = min(50, max($serverQty, $cit['quantity']));
            }
            $serverIndexed[$sku] = $cit;
        }

        $finalItems = array_values($serverIndexed);
        if (count($finalItems) > 50) $finalItems = array_slice($finalItems, 0, 50);

        $json = json_encode($finalItems, JSON_UNESCAPED_UNICODE);
        $db->prepare(
            'INSERT INTO user_carts (user_id, items_json) VALUES (:uid, :j)
             ON DUPLICATE KEY UPDATE items_json = :j2, updated_at = NOW()'
        )->execute([':uid' => $user['id'], ':j' => $json, ':j2' => $json]);

        sendJson(['success' => true, 'items' => $finalItems]);
    }

    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Cart sync failed', 500, $e);
}
