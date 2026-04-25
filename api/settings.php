<?php
// 公开 site_settings（前端可读）— 仅返回安全的 key
require_once __DIR__ . '/config.php';

const PUBLIC_SETTING_KEYS = [
    'social_tiktok', 'social_instagram', 'social_youtube', 'social_pinterest', 'social_facebook',
    'amazon_store_url', 'amazon_status',
    'hero_image_url', 'hero_image_urls', 'hero_slide_interval',
    'seo_blocked',
    'stripe_publishable_key',  // pk_xxx 是公开的，不是 secret
    'stripe_mode', 'paypal_mode',
    'paypal_client_id',        // PayPal SDK init 需要 (公开)
];

// Internal keys used to derive boolean "enabled" flags (not exposed)
const _SECRET_KEYS_FOR_DERIVATION = [
    'google_client_id',  'google_client_secret',
    'tiktok_client_key', 'tiktok_client_secret',
];

try {
    $db = getDb();
    $allKeys = array_merge(PUBLIC_SETTING_KEYS, _SECRET_KEYS_FOR_DERIVATION);
    $placeholders = implode(',', array_fill(0, count($allKeys), '?'));
    $stmt = $db->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN ($placeholders)");
    $stmt->execute($allKeys);
    $rawAll = [];
    foreach ($stmt->fetchAll() as $row) $rawAll[$row['key']] = $row['value'];

    $out = [];
    foreach (PUBLIC_SETTING_KEYS as $k) $out[$k] = $rawAll[$k] ?? '';
    // derive enabled flags from whether both client_id and secret are configured
    $out['oauth_google_enabled'] = (!empty($rawAll['google_client_id']) && !empty($rawAll['google_client_secret'])) ? '1' : '0';
    $out['oauth_tiktok_enabled'] = (!empty($rawAll['tiktok_client_key']) && !empty($rawAll['tiktok_client_secret'])) ? '1' : '0';
    sendJson($out);
} catch (PDOException $e) {
    sendError('Failed', 500, $e);
}
