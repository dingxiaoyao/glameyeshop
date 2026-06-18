<?php
// public GET — 列出所有 active 的 before/after 对
require_once __DIR__ . '/config.php';

try {
    $db = getDb();
    $stmt = $db->query(
        "SELECT id, before_image_url, before_label, after_image_url, after_label, alt_text
         FROM before_after_pairs
         WHERE is_active = 1
         ORDER BY sort_order ASC, id ASC"
    );
    $pairs = $stmt->fetchAll();

    // 顺手把可配置的副标题/footer 一起返回
    $stmt = $db->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN ('ba_section_subtitle','ba_section_footer')");
    $stmt->execute();
    $copy = [];
    foreach ($stmt->fetchAll() as $r) $copy[$r['key']] = $r['value'];

    sendJson([
        'pairs' => $pairs,
        'subtitle' => $copy['ba_section_subtitle'] ?? '',
        'footer'   => $copy['ba_section_footer'] ?? '',
    ]);
} catch (Throwable $e) {
    error_log('[before-after] ' . get_class($e) . ': ' . $e->getMessage());
    sendJson(['pairs' => [], 'subtitle' => '', 'footer' => ''], 200);  // 降级返回空,首页 hide section
}
