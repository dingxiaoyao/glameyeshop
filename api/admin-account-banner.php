<?php
// Admin CRUD for account_banners
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/account-banner-schema.php';
requireAdminAuth();

set_exception_handler(function ($e) {
    error_log(sprintf('[admin-account-banner] %s | %s | %s:%d',
        get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'error' => sprintf('%s: %s', get_class($e), $e->getMessage()),
        'hint'  => 'See PHP error log for stack trace.',
    ], JSON_UNESCAPED_UNICODE);
});

$method = $_SERVER['REQUEST_METHOD'];
$id     = intval($_GET['id'] ?? 0);
$db     = getDb();
ensureAccountBannerSchema($db);

if ($method === 'GET') {
    $stmt = $db->query("SELECT * FROM account_banners ORDER BY sort_order ASC, id ASC");
    sendJson(['banners' => $stmt->fetchAll()]);
}

$in = readInput();

if ($method === 'POST') {
    $img    = trim((string)($in['image_url'] ?? ''));
    $title  = trim((string)($in['title'] ?? ''));
    $sub    = trim((string)($in['subtitle'] ?? ''));
    $cta    = trim((string)($in['cta_text'] ?? ''));
    $url    = trim((string)($in['cta_url'] ?? ''));
    $sort   = intval($in['sort_order'] ?? 0);
    $active = !empty($in['is_active']) ? 1 : 0;

    if (!$img) sendJson(['error' => 'Image required'], 422);

    if ($id > 0) {
        $stmt = $db->prepare(
            'UPDATE account_banners SET image_url=:img, title=:t, subtitle=:st,
                                         cta_text=:ct, cta_url=:cu,
                                         sort_order=:sort, is_active=:active
             WHERE id=:id'
        );
        $stmt->execute([
            ':img' => $img, ':t' => $title, ':st' => $sub,
            ':ct' => $cta, ':cu' => $url,
            ':sort' => $sort, ':active' => $active, ':id' => $id,
        ]);
        sendJson(['success' => true, 'id' => $id]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO account_banners (image_url, title, subtitle, cta_text, cta_url, sort_order, is_active)
             VALUES (:img, :t, :st, :ct, :cu, :sort, :active)'
        );
        $stmt->execute([
            ':img' => $img, ':t' => $title, ':st' => $sub,
            ':ct' => $cta, ':cu' => $url,
            ':sort' => $sort, ':active' => $active,
        ]);
        sendJson(['success' => true, 'id' => (int)$db->lastInsertId()]);
    }
}

if ($method === 'DELETE') {
    if ($id <= 0) sendJson(['error' => 'Invalid id'], 422);
    $stmt = $db->prepare('DELETE FROM account_banners WHERE id = :id');
    $stmt->execute([':id' => $id]);
    sendJson(['success' => true]);
}

sendJson(['error' => 'Method not allowed'], 405);
