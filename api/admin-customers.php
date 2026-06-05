<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

try {
    $db = getDb();
    // P2: 分页 — page + per_page,返回 total 供前端计算页数
    $perPage = max(10, min(200, intval($_GET['per_page'] ?? 50)));
    $page    = max(1, intval($_GET['page'] ?? 1));
    $offset  = ($page - 1) * $perPage;
    $search  = trim((string)($_GET['search'] ?? ''));

    $whereSql  = '';
    $bindParams = [];
    if ($search !== '') {
        $whereSql = 'WHERE (u.email LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q OR u.phone LIKE :q)';
        $bindParams[':q'] = '%' . $search . '%';
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u $whereSql");
    $countStmt->execute($bindParams);
    $total = intval($countStmt->fetchColumn());

    $listSql = "SELECT u.id, u.email, u.first_name, u.last_name, u.phone, u.is_subscribed,
                       u.is_test_account, u.email_verified, u.oauth_provider, u.created_at,
                       COALESCE(o.order_count, 0) AS order_count,
                       COALESCE(o.total_spent, 0) AS total_spent
                FROM users u
                LEFT JOIN (
                  SELECT user_id, COUNT(*) AS order_count, SUM(amount) AS total_spent
                  FROM orders WHERE status IN ('paid','processing','shipped','delivered')
                                 AND is_test = 0
                  GROUP BY user_id
                ) o ON o.user_id = u.id
                $whereSql
                ORDER BY u.created_at DESC
                LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($listSql);
    $stmt->execute($bindParams);

    sendJson([
        'customers' => $stmt->fetchAll(),
        'pagination' => [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ],
        'search' => $search,
    ]);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
