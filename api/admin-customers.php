<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

try {
    $db = getDb();
    $limit = max(1, min(500, intval($_GET['limit'] ?? 100)));
    $stmt = $db->prepare(
        'SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.is_subscribed, u.created_at,
                COALESCE(o.order_count, 0) AS order_count,
                COALESCE(o.total_spent, 0) AS total_spent
         FROM users u
         LEFT JOIN (
           SELECT user_id, COUNT(*) AS order_count, SUM(amount) AS total_spent
           FROM orders WHERE status IN ("paid","processing","shipped","delivered")
           GROUP BY user_id
         ) o ON o.user_id = u.id
         ORDER BY u.created_at DESC LIMIT ' . $limit
    );
    $stmt->execute();
    sendJson(['customers' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
