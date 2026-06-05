<?php
// P1#17: 动态 sitemap — 从 DB 拿当前在售产品 + 静态页 + 法务页
// .htaccess 把 /sitemap.xml 重写到这里
header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');  // 1h 缓存
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/payment-config.php';

$base = rtrim(getPaymentConfig('site_base_url', '') ?: 'https://glameyeshop.com', '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

function urlEntry(string $loc, string $changefreq = 'weekly', string $priority = '0.7', ?string $lastmod = null): string {
    $out = "  <url><loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>";
    if ($lastmod) $out .= "<lastmod>$lastmod</lastmod>";
    $out .= "<changefreq>$changefreq</changefreq><priority>$priority</priority></url>\n";
    return $out;
}

// 静态主要页
echo urlEntry("$base/",                 'weekly',  '1.0');
echo urlEntry("$base/shop.html",        'weekly',  '0.95');
echo urlEntry("$base/about.html",       'monthly', '0.7');
echo urlEntry("$base/wholesale.html",   'monthly', '0.7');
echo urlEntry("$base/contact.html",     'monthly', '0.6');
echo urlEntry("$base/login.html",       'yearly',  '0.4');
echo urlEntry("$base/signup.html",      'yearly',  '0.5');
echo urlEntry("$base/track-order.html", 'monthly', '0.5');

// 法务页(seo_blocked=1 时建议不被索引,但保留在 sitemap)
echo urlEntry("$base/terms.html",       'monthly', '0.4');
echo urlEntry("$base/privacy.html",     'monthly', '0.4');
echo urlEntry("$base/refund.html",      'monthly', '0.4');
echo urlEntry("$base/shipping.html",    'monthly', '0.4');

// 风格分类(shop.html?style=…)
foreach (['natural', 'wispy', 'dramatic'] as $style) {
    echo urlEntry("$base/shop.html?style=$style", 'weekly', '0.85');
}

// 动态产品 SKU(只列在售的)
try {
    $db = getDb();
    $stmt = $db->query("SELECT sku, updated_at FROM products
                        WHERE is_active = 1
                        ORDER BY is_bestseller DESC, sort_order ASC");
    while ($r = $stmt->fetch()) {
        $sku = $r['sku'];
        $lastmod = $r['updated_at'] ? substr($r['updated_at'], 0, 10) : null;
        echo urlEntry("$base/product.html?sku=" . urlencode($sku), 'weekly', '0.8', $lastmod);
    }
} catch (Throwable $e) {
    error_log('[sitemap] DB fetch failed: ' . $e->getMessage());
}

echo '</urlset>' . "\n";
