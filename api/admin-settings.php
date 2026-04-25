<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDb();

    if ($method === 'GET') {
        $stmt = $db->query("SELECT `key`, `value`, updated_at FROM site_settings ORDER BY `key`");
        sendJson(['settings' => $stmt->fetchAll()]);
    }

    if ($method === 'POST') {
        $in = readInput();
        $key = trim((string)($in['key'] ?? ''));
        $val = (string)($in['value'] ?? '');
        if (!$key) sendJson(['error' => 'key required'], 422);

        $stmt = $db->prepare(
            "INSERT INTO site_settings (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = :v2"
        );
        $stmt->execute([':k' => $key, ':v' => $val, ':v2' => $val]);
        sendJson(['success' => true]);
    }
    sendJson(['error' => 'Method not allowed'], 405);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
