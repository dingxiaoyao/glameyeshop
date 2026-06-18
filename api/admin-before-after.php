<?php
// admin CRUD for before_after_pairs
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/before-after-schema.php';
requireAdminAuth();

set_exception_handler(function ($e) {
    error_log(sprintf('[admin-before-after] %s | %s | %s:%d',
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
ensureBeforeAfterSchema($db);  // self-healing: 表不存在自动建 + 种子

if ($method === 'GET') {
    $stmt = $db->query("SELECT * FROM before_after_pairs ORDER BY sort_order ASC, id ASC");
    // 也带上当前副标题/footer 让 admin 一次拉
    $copyStmt = $db->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN ('ba_section_subtitle','ba_section_footer')");
    $copyStmt->execute();
    $copy = [];
    foreach ($copyStmt->fetchAll() as $r) $copy[$r['key']] = $r['value'];
    sendJson([
        'pairs' => $stmt->fetchAll(),
        'subtitle' => $copy['ba_section_subtitle'] ?? '',
        'footer'   => $copy['ba_section_footer'] ?? '',
    ]);
}

$in = readInput();

if ($method === 'POST') {
    // POST 同时支持:更新 site copy + insert/update pair
    if (!empty($in['_action']) && $in['_action'] === 'update_copy') {
        $subtitle = trim((string)($in['subtitle'] ?? ''));
        $footer   = trim((string)($in['footer'] ?? ''));
        $stmt = $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (:k, :v)
                              ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute([':k' => 'ba_section_subtitle', ':v' => $subtitle]);
        $stmt->execute([':k' => 'ba_section_footer',   ':v' => $footer]);
        sendJson(['success' => true]);
    }

    $beforeUrl   = trim((string)($in['before_image_url'] ?? ''));
    $beforeLabel = trim((string)($in['before_label'] ?? 'Before'));
    $afterUrl    = trim((string)($in['after_image_url'] ?? ''));
    $afterLabel  = trim((string)($in['after_label'] ?? 'After'));
    $alt         = trim((string)($in['alt_text'] ?? ''));
    $sort        = intval($in['sort_order'] ?? 0);
    $active      = !empty($in['is_active']) ? 1 : 0;

    if (!$beforeUrl || !$afterUrl) sendJson(['error' => 'Both images required'], 422);
    if (mb_strlen($beforeLabel) > 100 || mb_strlen($afterLabel) > 100) sendJson(['error' => 'Label too long (max 100)'], 422);

    if ($id > 0) {
        $stmt = $db->prepare(
            'UPDATE before_after_pairs SET before_image_url=:bi, before_label=:bl,
                                            after_image_url=:ai, after_label=:al,
                                            alt_text=:alt, sort_order=:sort, is_active=:active
             WHERE id=:id'
        );
        $stmt->execute([
            ':bi' => $beforeUrl, ':bl' => $beforeLabel,
            ':ai' => $afterUrl,  ':al' => $afterLabel,
            ':alt' => $alt, ':sort' => $sort, ':active' => $active, ':id' => $id,
        ]);
        sendJson(['success' => true, 'id' => $id]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO before_after_pairs (before_image_url, before_label, after_image_url, after_label, alt_text, sort_order, is_active)
             VALUES (:bi, :bl, :ai, :al, :alt, :sort, :active)'
        );
        $stmt->execute([
            ':bi' => $beforeUrl, ':bl' => $beforeLabel,
            ':ai' => $afterUrl,  ':al' => $afterLabel,
            ':alt' => $alt, ':sort' => $sort, ':active' => $active,
        ]);
        sendJson(['success' => true, 'id' => (int)$db->lastInsertId()]);
    }
}

if ($method === 'DELETE') {
    if ($id <= 0) sendJson(['error' => 'Invalid id'], 422);
    $stmt = $db->prepare('DELETE FROM before_after_pairs WHERE id = :id');
    $stmt->execute([':id' => $id]);
    sendJson(['success' => true]);
}

sendJson(['error' => 'Method not allowed'], 405);
