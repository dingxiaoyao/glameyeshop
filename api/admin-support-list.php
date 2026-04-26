<?php
// ============================================================
// Admin: 列出客服线程 + 单线程消息
//   GET                 → 所有线程列表(可 ?status=open|closed|replied|all,?q= 搜邮箱/主题)
//   GET ?id=N           → 单个线程的所有消息(顺便清掉 admin 的未读标记)
// ============================================================
require_once __DIR__ . '/config.php';
requireAdminAuth();

$id = intval($_GET['id'] ?? 0);

try {
    $db = getDb();
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM support_threads WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $thread = $stmt->fetch();
        if (!$thread) sendJson(['error' => 'Not found'], 404);

        $stmt = $db->prepare("SELECT id, sender, body, admin_user, created_at
                              FROM support_messages WHERE thread_id=:id ORDER BY created_at ASC");
        $stmt->execute([':id' => $id]);
        $messages = $stmt->fetchAll();

        // 清 admin 端未读
        $db->prepare("UPDATE support_threads SET unread_for_admin=0 WHERE id=:id")
           ->execute([':id' => $id]);

        sendJson(['thread' => $thread, 'messages' => $messages]);
    }

    $status = strtolower(trim((string)($_GET['status'] ?? 'all')));
    $q      = trim((string)($_GET['q'] ?? ''));
    $where = []; $params = [];
    if (in_array($status, ['open','replied','closed'], true)) {
        $where[] = 'status = :st'; $params[':st'] = $status;
    }
    if ($q !== '') {
        $where[] = '(customer_email LIKE :q OR customer_name LIKE :q OR subject LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }
    $sql = 'SELECT t.*, (SELECT body FROM support_messages
                         WHERE thread_id=t.id ORDER BY created_at DESC LIMIT 1) AS last_message,
                       (SELECT COUNT(*) FROM support_messages WHERE thread_id=t.id) AS message_count
            FROM support_threads t';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY unread_for_admin DESC, updated_at DESC LIMIT 200';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $threads = $stmt->fetchAll();

    // 顶部摘要
    $stats = $db->query("SELECT
        SUM(unread_for_admin=1) AS unread,
        SUM(status='open')      AS open_count,
        SUM(status='replied')   AS replied_count,
        SUM(status='closed')    AS closed_count
        FROM support_threads")->fetch();

    sendJson(['threads' => $threads, 'stats' => $stats]);
} catch (PDOException $e) {
    sendError('Database error', 500, $e);
}
