<?php
/**
 * Reviews API
 *
 * GET endpoints:
 *   ?product_id=N            - approved reviews for product (paginated, ?page=1&per=10)
 *                              returns: { reviews:[], stats:{ count, avg, distribution:{1..5}} }
 *   ?featured=1&limit=3      - featured reviews for homepage (joined with product name)
 *   ?my=1                    - logged-in user's own reviews (any status)
 *
 * POST (auth required): submit a new review
 *   body: { product_id, rating(1-5), title?, body, photo_urls?[] }
 *   - one review per (user_id, product_id); reusing endpoint = update existing pending one
 *   - is_verified_buyer auto-set if user has a paid/shipped/delivered order_item with this product_id
 *   - status starts as 'pending' until admin approves
 */
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDb();

    if ($method === 'GET') {
        // ---- Featured (homepage) ----
        if (!empty($_GET['featured'])) {
            $limit = max(1, min(20, intval($_GET['limit'] ?? 3)));
            $sql = "SELECT r.id, r.product_id, r.reviewer_name, r.reviewer_location, r.rating,
                           r.title, r.body, r.photo_urls, r.is_verified_buyer, r.helpful_count, r.created_at,
                           p.name AS product_name, p.sku AS product_sku
                    FROM reviews r
                    LEFT JOIN products p ON p.id = r.product_id
                    WHERE r.status = 'approved' AND r.is_featured = 1
                    ORDER BY r.helpful_count DESC, r.created_at DESC
                    LIMIT $limit";
            $rows = $db->query($sql)->fetchAll();
            foreach ($rows as &$r) {
                $r['photo_urls'] = $r['photo_urls'] ? (json_decode($r['photo_urls'], true) ?: []) : [];
            }
            sendJson(['reviews' => $rows]);
        }

        // ---- My reviews ----
        if (!empty($_GET['my'])) {
            $user = requireUser();
            $stmt = $db->prepare(
                "SELECT r.*, p.name AS product_name, p.sku AS product_sku
                 FROM reviews r LEFT JOIN products p ON p.id = r.product_id
                 WHERE r.user_id = :uid
                 ORDER BY r.created_at DESC"
            );
            $stmt->execute([':uid' => $user['id']]);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['photo_urls'] = $r['photo_urls'] ? (json_decode($r['photo_urls'], true) ?: []) : [];
            }
            sendJson(['reviews' => $rows]);
        }

        // ---- By product (default) ----
        $productId = intval($_GET['product_id'] ?? 0);
        if ($productId <= 0) sendJson(['error' => 'Missing product_id'], 422);
        $page = max(1, intval($_GET['page'] ?? 1));
        $per  = max(1, min(50, intval($_GET['per'] ?? 10)));
        $offset = ($page - 1) * $per;

        // stats
        $statsStmt = $db->prepare(
            "SELECT COUNT(*) AS n, COALESCE(AVG(rating),0) AS avg
             FROM reviews WHERE product_id = :pid AND status = 'approved'"
        );
        $statsStmt->execute([':pid' => $productId]);
        $stats = $statsStmt->fetch() ?: ['n' => 0, 'avg' => 0];

        $distStmt = $db->prepare(
            "SELECT rating, COUNT(*) AS n
             FROM reviews WHERE product_id = :pid AND status = 'approved'
             GROUP BY rating"
        );
        $distStmt->execute([':pid' => $productId]);
        $dist = ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0];
        foreach ($distStmt->fetchAll() as $row) {
            $dist[strval($row['rating'])] = intval($row['n']);
        }

        $listStmt = $db->prepare(
            "SELECT id, reviewer_name, reviewer_location, rating, title, body, photo_urls,
                    is_verified_buyer, helpful_count, created_at
             FROM reviews
             WHERE product_id = :pid AND status = 'approved'
             ORDER BY is_featured DESC, helpful_count DESC, created_at DESC
             LIMIT $per OFFSET $offset"
        );
        $listStmt->execute([':pid' => $productId]);
        $rows = $listStmt->fetchAll();
        foreach ($rows as &$r) {
            $r['photo_urls'] = $r['photo_urls'] ? (json_decode($r['photo_urls'], true) ?: []) : [];
        }

        sendJson([
            'reviews' => $rows,
            'stats' => [
                'count' => intval($stats['n']),
                'avg'   => round(floatval($stats['avg']), 2),
                'distribution' => $dist,
            ],
            'pagination' => ['page' => $page, 'per' => $per],
        ]);
    }

    if ($method === 'POST') {
        $user = requireUser();
        $in = readInput();
        $productId = intval($in['product_id'] ?? 0);
        $rating    = intval($in['rating'] ?? 0);
        $title     = trim((string)($in['title'] ?? ''));
        $body      = trim((string)($in['body'] ?? ''));
        $photoUrls = $in['photo_urls'] ?? [];

        if ($productId <= 0)               sendJson(['error' => 'Missing product_id'], 422);
        if ($rating < 1 || $rating > 5)    sendJson(['error' => 'Rating must be 1-5'], 422);
        if (mb_strlen($body) < 10)         sendJson(['error' => 'Review body too short (min 10 chars)'], 422);
        if (mb_strlen($body) > 4000)       sendJson(['error' => 'Review body too long'], 422);
        if (mb_strlen($title) > 200)       sendJson(['error' => 'Title too long'], 422);
        if (!is_array($photoUrls))         $photoUrls = [];
        $photoUrls = array_slice(array_values(array_filter(array_map('strval', $photoUrls), 'strlen')), 0, 5);
        $photoJson = $photoUrls ? json_encode(array_values($photoUrls), JSON_UNESCAPED_UNICODE) : null;

        // verify product exists
        $prodStmt = $db->prepare("SELECT id FROM products WHERE id = :id LIMIT 1");
        $prodStmt->execute([':id' => $productId]);
        if (!$prodStmt->fetch()) sendJson(['error' => 'Product not found'], 404);

        // verified buyer? (user has paid/shipped/delivered order with this product)
        $vbStmt = $db->prepare(
            "SELECT 1 FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE oi.product_id = :pid AND o.user_id = :uid
               AND o.status IN ('paid', 'processing', 'shipped', 'delivered')
             LIMIT 1"
        );
        $vbStmt->execute([':pid' => $productId, ':uid' => $user['id']]);
        $isVerified = $vbStmt->fetch() ? 1 : 0;

        // reviewer display name
        $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        if ($displayName === '') $displayName = 'Verified Buyer';
        // truncate to 100
        $displayName = mb_substr($displayName, 0, 100);

        // upsert: 1 review per (user, product). If existing approved → block (avoid spam).
        // If existing pending → update it.
        $existStmt = $db->prepare(
            "SELECT id, status FROM reviews WHERE user_id = :uid AND product_id = :pid LIMIT 1"
        );
        $existStmt->execute([':uid' => $user['id'], ':pid' => $productId]);
        $existing = $existStmt->fetch();

        if ($existing && $existing['status'] === 'approved') {
            sendJson(['error' => 'You already reviewed this product. Edit not yet supported.'], 409);
        }

        if ($existing) {
            $upd = $db->prepare(
                "UPDATE reviews SET reviewer_name = :n, rating = :r, title = :t, body = :b,
                                    photo_urls = :ph, status = 'pending', is_verified_buyer = :vb,
                                    updated_at = NOW()
                 WHERE id = :id"
            );
            $upd->execute([
                ':n' => $displayName, ':r' => $rating, ':t' => $title, ':b' => $body,
                ':ph' => $photoJson, ':vb' => $isVerified, ':id' => $existing['id'],
            ]);
            sendJson(['success' => true, 'review_id' => intval($existing['id']), 'status' => 'pending']);
        } else {
            $ins = $db->prepare(
                "INSERT INTO reviews
                  (product_id, user_id, reviewer_name, rating, title, body, photo_urls, status, is_verified_buyer)
                 VALUES (:pid, :uid, :n, :r, :t, :b, :ph, 'pending', :vb)"
            );
            $ins->execute([
                ':pid' => $productId, ':uid' => $user['id'], ':n' => $displayName,
                ':r' => $rating, ':t' => $title, ':b' => $body, ':ph' => $photoJson, ':vb' => $isVerified,
            ]);
            sendJson(['success' => true, 'review_id' => intval($db->lastInsertId()), 'status' => 'pending']);
        }
    }

    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
