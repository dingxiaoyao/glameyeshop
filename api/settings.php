<?php
// Public site settings (social URLs, hero image, amazon status, etc.)
require_once __DIR__ . '/config.php';

try {
    $db = getDb();
    $stmt = $db->query("SELECT `key`, `value` FROM site_settings");
    $out = [];
    foreach ($stmt->fetchAll() as $row) $out[$row['key']] = $row['value'];
    sendJson($out);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
