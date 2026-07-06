<?php
// 动态 robots.txt
// 根据 site_settings.seo_blocked 决定允许 or 屏蔽
require_once __DIR__ . '/api/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 分钟缓存

$blocked = false;
try {
    $db = getDb();
    $stmt = $db->prepare("SELECT `value` FROM site_settings WHERE `key` = 'seo_blocked'");
    $stmt->execute();
    $row = $stmt->fetch();
    $blocked = $row && $row['value'] === '1';
} catch (Throwable $e) {
    // DB down: 安全起见默认允许（避免误伤）
    $blocked = false;
}

if ($blocked) {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    echo "\n";
    echo "# Site is in pre-launch mode. Crawling disabled.\n";
    exit;
}

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /database/\n";
echo "Disallow: /uploads/\n";
echo "\n";
echo "# 注意:cart/checkout/order-success/account 等页 不放 Disallow —\n";
echo "# 让 Googlebot 能爬进去读页面自带的 <meta name=\"robots\" noindex,nofollow>,\n";
echo "# 才能从索引真正删除。robots.txt 只挡爬取不挡索引。\n";
echo "\n";
echo "User-agent: facebookexternalhit\n";
echo "Allow: /\n";
echo "\n";
echo "User-agent: Twitterbot\n";
echo "Allow: /\n";
echo "\n";
echo "Sitemap: https://glameyeshop.com/sitemap.xml\n";
