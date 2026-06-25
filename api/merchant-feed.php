<?php
// ============================================================
// Google Merchant Center Product Feed (RSS 2.0 + g: namespace)
//
// 公开访问: GET /merchant-feed.xml (nginx/htaccess rewrite)
//          或直接 GET /api/merchant-feed.php
//
// Google Merchant Center 会按你在 GMC 后台配置的频率拉取这个 URL
// 规范: https://support.google.com/merchants/answer/7052112?hl=zh-CN
//
// 字段映射:
//   <g:id>              ← products.sku
//   <title>             ← products.name (5-150 chars)
//   <description>       ← products.description / short_description
//   <link>              ← https://glameyeshop.com/product.html?sku=...
//   <g:image_link>      ← products.image_url (绝对 URL)
//   <g:additional_image_link> × N ← products.gallery_urls (JSON array)
//   <g:availability>    ← in_stock / out_of_stock(根据 stock 推)
//   <g:price>           ← XX.XX USD
//   <g:sale_price>      ← compare_at_price 存在时,price 是 sale,compare 是 regular(我们反过来)
//   <g:condition>       ← new
//   <g:brand>           ← GlamEye (硬编码)
//   <g:gtin>            ← 留空(GlamEye 自有品牌)
//   <g:identifier_exists> ← no (无 gtin/mpn 时必填)
//   <g:google_product_category> ← 469 (Health & Beauty > Personal Care > Cosmetics > Eye Makeup > False Eyelashes)
//   <g:product_type>    ← category / style 拼接
//   <g:shipping>        ← 按 shipping_zones 展开
// ============================================================
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=900');  // 15 分钟缓存,GMC 多拉也没事

try {
    $db = getDb();

    // 站点 URL 基址(防 host 伪造)— 优先 site_settings.site_base_url
    $base = 'https://glameyeshop.com';
    try {
        $b = $db->query("SELECT `value` FROM site_settings WHERE `key`='site_base_url' LIMIT 1")->fetchColumn();
        if ($b && preg_match('#^https?://#', $b)) $base = rtrim($b, '/');
    } catch (Throwable $e) { /* swallow */ }

    // 品牌名(可从 site_settings.brand_name 拿,默认 GlamEye)
    $brandName = 'GlamEye';
    try {
        $b = $db->query("SELECT `value` FROM site_settings WHERE `key`='brand_name' LIMIT 1")->fetchColumn();
        if ($b) $brandName = $b;
    } catch (Throwable $e) {}

    // 运费 zones
    $zones = [];
    try {
        $z = $db->query("SELECT `value` FROM site_settings WHERE `key`='shipping_zones' LIMIT 1")->fetchColumn();
        $zones = json_decode($z ?: '{}', true) ?: [];
    } catch (Throwable $e) {}

    // 允许出现在 feed 里的国家 ISO 码集合 — 必须与 GMC 账号的 target country 完全匹配,
    // 否则会报 "no shipping eligibility for country XX"。默认仅 US。
    // 用户随后在 admin 设置里勾选其他国家逐个开通。
    $allowedCountries = ['US'];
    try {
        $fc = $db->query("SELECT `value` FROM site_settings WHERE `key`='feed_shipping_countries' LIMIT 1")->fetchColumn();
        if ($fc) {
            $arr = array_filter(array_map('trim', explode(',', strtoupper($fc))));
            if ($arr) $allowedCountries = $arr;
        }
    } catch (Throwable $e) {}
    $allowedCountriesSet = array_flip($allowedCountries);

    // 拉所有上架产品(非 bundle — bundle 是组合,GMC 通常不收)
    $stmt = $db->query("
        SELECT id, sku, name, short_description, description, category, style,
               length_mm, band_type, price, compare_at_price,
               image_url, gallery_urls, stock
        FROM products
        WHERE is_active = 1 AND IFNULL(is_bundle, 0) = 0
        ORDER BY id ASC
    ");
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<error>" . htmlspecialchars($e->getMessage()) . "</error>";
    exit;
}

// 把相对图片 URL 转绝对
function absUrl(string $u, string $base): string {
    if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    if ($u[0] !== '/') $u = '/' . $u;
    return $base . $u;
}

// 安全转 CDATA
function cdata(string $s): string {
    // 替换内嵌 ]]> 防破 CDATA
    $s = str_replace(']]>', ']]]]><![CDATA[>', $s);
    return '<![CDATA[' . $s . ']]>';
}

// 控制 title 长度(5-150)+ 自动塞品牌
function buildTitle(array $p, string $brand): string {
    $name = trim((string)$p['name']);
    // 已含品牌就别重复
    $hasBrand = stripos($name, $brand) !== false;
    $title = $hasBrand ? $name : ($brand . ' ' . $name);
    if (!empty($p['length_mm'])) $title .= ' ' . (int)$p['length_mm'] . 'mm';
    if (!empty($p['band_type'])) $title .= ' · ' . ucfirst($p['band_type']) . ' band';
    return mb_substr($title, 0, 150);
}

// 控制 description(70-5000 chars,无 HTML)
function buildDescription(array $p): string {
    $d = trim((string)($p['description'] ?: $p['short_description']));
    $d = strip_tags($d);
    $d = preg_replace('/\s+/u', ' ', $d);
    if (mb_strlen($d) < 70) {
        // 太短 — 用 name + category 凑
        $extra = ' Premium DIY cluster lash extensions from GlamEye.';
        if (!empty($p['style'])) $extra .= ' Style: ' . ucfirst($p['style']) . '.';
        if (!empty($p['length_mm'])) $extra .= ' Length: ' . (int)$p['length_mm'] . 'mm.';
        $extra .= ' Reusable, lightweight, easy to apply at home.';
        $d = trim($d . $extra);
    }
    return mb_substr($d, 0, 5000);
}

// 输出 XML(手写,不依赖 SimpleXML 避免 namespace 嵌套坑)
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
  <channel>
    <title><?= htmlspecialchars($brandName) ?> — Product Feed</title>
    <link><?= htmlspecialchars($base) ?></link>
    <description>GlamEye cluster lash extensions and beauty products feed for Google Merchant Center.</description>
<?php foreach ($products as $p):
    $sku   = (string)$p['sku'];
    $link  = $base . '/product.html?sku=' . urlencode($sku);
    $img   = absUrl((string)$p['image_url'], $base);
    if (!$img) continue;  // 没主图的 SKU 跳过 — GMC 必填

    $price = number_format((float)$p['price'], 2, '.', '');
    $compare = $p['compare_at_price'] ? number_format((float)$p['compare_at_price'], 2, '.', '') : null;
    // 我们的 schema:price 是销售价,compare_at_price 是划掉原价 → GMC 的 price = compare_at_price,sale_price = price
    $gmcPrice = $compare && (float)$compare > (float)$price ? $compare : $price;
    $gmcSale  = $compare && (float)$compare > (float)$price ? $price : null;

    $stock = (int)$p['stock'];
    $avail = $stock > 0 ? 'in_stock' : 'out_of_stock';

    $title = buildTitle($p, $brandName);
    $desc  = buildDescription($p);

    // 额外图(gallery)
    $extraImgs = [];
    if (!empty($p['gallery_urls'])) {
        $arr = json_decode($p['gallery_urls'], true);
        if (is_array($arr)) {
            foreach ($arr as $g) {
                $abs = absUrl((string)$g, $base);
                if ($abs && $abs !== $img) $extraImgs[] = $abs;
            }
        }
    }
    $extraImgs = array_slice($extraImgs, 0, 10);  // GMC 上限 10 张

    $productType = trim((string)$p['category'] . ($p['style'] ? ' > ' . $p['style'] : ''));
?>
    <item>
      <g:id><?= htmlspecialchars($sku) ?></g:id>
      <title><?= cdata($title) ?></title>
      <description><?= cdata($desc) ?></description>
      <link><?= htmlspecialchars($link) ?></link>
      <g:image_link><?= htmlspecialchars($img) ?></g:image_link>
<?php foreach ($extraImgs as $ei): ?>
      <g:additional_image_link><?= htmlspecialchars($ei) ?></g:additional_image_link>
<?php endforeach; ?>
      <g:availability><?= $avail ?></g:availability>
      <g:price><?= $gmcPrice ?> USD</g:price>
<?php if ($gmcSale): ?>
      <g:sale_price><?= $gmcSale ?> USD</g:sale_price>
<?php endif; ?>
      <g:condition>new</g:condition>
      <g:brand><?= htmlspecialchars($brandName) ?></g:brand>
      <g:identifier_exists>no</g:identifier_exists>
      <g:google_product_category>469</g:google_product_category>
      <g:product_type><?= htmlspecialchars($productType) ?: 'Lashes' ?></g:product_type>
      <g:age_group>adult</g:age_group>
      <g:gender>female</g:gender>
<?php
    // shipping by zone — 仅输出 GMC target country 内的国家,避免 "no shipping eligibility for country XX"
    $emittedCountries = [];  // 防止同一国家在多 zone 重复出现
    foreach ($zones as $zoneCode => $zoneCfg) {
        if ($zoneCode === 'default') continue;
        if (!isset($zoneCfg['price'])) continue;
        $countries = [];
        if ($zoneCode === 'EU')   $countries = ['DE','FR','IT','ES','NL','BE','SE','AT','IE','PT','FI','DK','PL'];
        elseif ($zoneCode === 'ASIA') $countries = ['JP','KR','SG','HK','TW','TH','MY'];
        elseif (preg_match('/^[A-Z]{2}$/', $zoneCode)) $countries = [$zoneCode];
        $shipPrice = number_format((float)$zoneCfg['price'], 2, '.', '');
        foreach ($countries as $c):
            // 关键过滤:不在 GMC allowlist 里的国家直接跳过
            if (!isset($allowedCountriesSet[$c])) continue;
            if (isset($emittedCountries[$c])) continue;
            $emittedCountries[$c] = true;
        ?>
      <g:shipping>
        <g:country><?= $c ?></g:country>
        <g:service>Standard</g:service>
        <g:price><?= $shipPrice ?> USD</g:price>
      </g:shipping>
<?php   endforeach;
    }
    // 兜底:如果 zones 里完全没匹配上 allowlist,至少给每个 allowlist 国家发一条 default 运费
    // (用 default zone 价格 / 没有就 0)
    foreach ($allowedCountries as $c) {
        if (isset($emittedCountries[$c])) continue;
        $fallbackPrice = isset($zones['default']['price']) ? (float)$zones['default']['price'] : 0.0;
        $fp = number_format($fallbackPrice, 2, '.', '');
        ?>
      <g:shipping>
        <g:country><?= htmlspecialchars($c) ?></g:country>
        <g:service>Standard</g:service>
        <g:price><?= $fp ?> USD</g:price>
      </g:shipping>
<?php
    }
?>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
