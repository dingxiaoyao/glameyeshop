<?php
// 服务端读取支付凭据（仅 PHP 内部用）
// 不暴露任何 secret 给前端
require_once __DIR__ . '/config.php';

function getPaymentConfig(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        try {
            $db = getDb();
            // P1#14: LIKE 之前漏掉 site_base_url(stripe-checkout success_url 永远走 fallback,
            // 换域名会全断)。这里把所有 PHP 后端需要的非敏感 + 敏感配置一次性都缓存。
            $stmt = $db->query(
                "SELECT `key`, `value` FROM site_settings
                 WHERE `key` LIKE 'stripe_%'
                    OR `key` LIKE 'paypal_%'
                    OR `key` = 'site_base_url'
                    OR `key` LIKE 'resend_%'
                    OR `key` LIKE 'email_%'
                    OR `key` LIKE 'smtp_%'"
            );
            $cache = [];
            foreach ($stmt->fetchAll() as $r) $cache[$r['key']] = $r['value'];
        } catch (Throwable $e) { $cache = []; }
    }
    return $cache[$key] ?? $default;
}

function isStripeConfigured(): bool {
    return getPaymentConfig('stripe_secret_key') !== '' && getPaymentConfig('stripe_publishable_key') !== '';
}
function isPaypalConfigured(): bool {
    return getPaymentConfig('paypal_client_id') !== '' && getPaymentConfig('paypal_secret') !== '';
}

function stripeMode(): string  { return getPaymentConfig('stripe_mode', 'test') === 'live' ? 'live' : 'test'; }
function paypalMode(): string  { return getPaymentConfig('paypal_mode', 'sandbox') === 'live' ? 'live' : 'sandbox'; }
