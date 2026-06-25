<?php
// Admin Merchant Center 配置 + 产品 feed 健康检查
$pageTitle = 'Merchant Center';
$activeNav = 'merchant-center';
require_once __DIR__ . '/../api/lib/admin-session.php';
require_once __DIR__ . '/../api/config.php';

$me = requireAdminSession();
$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf();
    try {
        $db = getDb();
        // 保存 brand_name + GMC merchant_id(可选)
        if (isset($_POST['brand_name'])) {
            $db->prepare("INSERT INTO site_settings (`key`,`value`) VALUES ('brand_name', :v)
                          ON DUPLICATE KEY UPDATE `value`=:v2")
               ->execute([':v' => trim($_POST['brand_name']), ':v2' => trim($_POST['brand_name'])]);
        }
        if (isset($_POST['gmc_merchant_id'])) {
            $db->prepare("INSERT INTO site_settings (`key`,`value`) VALUES ('gmc_merchant_id', :v)
                          ON DUPLICATE KEY UPDATE `value`=:v2")
               ->execute([':v' => trim($_POST['gmc_merchant_id']), ':v2' => trim($_POST['gmc_merchant_id'])]);
        }
        $flash = '✓ Saved.';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

// 拉当前设置
$db = getDb();
$brand = $db->query("SELECT `value` FROM site_settings WHERE `key`='brand_name' LIMIT 1")->fetchColumn() ?: 'GlamEye';
$gmcId = $db->query("SELECT `value` FROM site_settings WHERE `key`='gmc_merchant_id' LIMIT 1")->fetchColumn() ?: '';
$base  = $db->query("SELECT `value` FROM site_settings WHERE `key`='site_base_url' LIMIT 1")->fetchColumn() ?: 'https://glameyeshop.com';
$base  = rtrim($base, '/');

// 检查产品数据完整度
$checks = [];
$products = $db->query("SELECT id, sku, name, description, short_description, image_url, price, stock, is_active, IFNULL(is_bundle,0) as is_bundle FROM products WHERE is_active=1")->fetchAll();
$total = count($products);
$missingImg = 0;
$shortDesc = 0;
$skipped = 0;  // bundle 或没主图
foreach ($products as $p) {
    if ($p['is_bundle']) { $skipped++; continue; }
    if (!$p['image_url']) { $missingImg++; $skipped++; continue; }
    $d = trim($p['description'] ?: $p['short_description']);
    if (mb_strlen(strip_tags($d)) < 70) $shortDesc++;
}
$feedCount = $total - $skipped;

// 法务页 + HTTPS 检查
$legalPages = [
    'terms.html'    => 'Terms',
    'privacy.html'  => 'Privacy',
    'refund.html'   => 'Refund',
    'shipping.html' => 'Shipping',
];
$legalCheck = [];
foreach ($legalPages as $f => $label) {
    $legalCheck[$label] = file_exists(__DIR__ . '/../' . $f);
}

require __DIR__ . '/_layout.php';
$csrf = adminCsrfToken();
$feedUrl = $base . '/merchant-feed.xml';
?>
<h1 style="margin-top:0">🛍 <?= $lang === 'zh' ? 'Google Merchant Center 配置' : 'Google Merchant Center' ?></h1>
<p class="muted"><?= $lang === 'zh'
  ? '把产品同步到 Google Shopping(免费 listings + 付费 Shopping ads)。'
  : 'Sync products to Google Shopping (free listings + paid Shopping ads).' ?></p>

<?php if ($flash): ?>
<div class="admin-card" style="border-left:3px solid <?= $flashType==='error'?'var(--error)':'var(--gold)' ?>;margin-bottom:1.5rem">
  <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<!-- Feed URL -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1rem">📡 Product Feed URL</h3>
  <p>Submit this URL to Google Merchant Center → <strong>Products → Feeds → Add primary feed</strong>:</p>
  <div style="margin:1rem 0;padding:1rem;background:var(--bg-soft);border-radius:6px;font-family:monospace;font-size:1rem;user-select:all;word-break:break-all">
    🔗 <strong style="color:var(--gold)"><?= htmlspecialchars($feedUrl) ?></strong>
  </div>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <a href="<?= htmlspecialchars($feedUrl) ?>" target="_blank" class="button button-outline">🔍 Preview feed (raw XML)</a>
    <a href="https://search.google.com/test/rich-results?url=<?= urlencode($feedUrl) ?>" target="_blank" class="button button-outline">✅ Google Rich Results test</a>
  </div>
  <p class="muted small" style="margin-top:.75rem">Google will fetch this URL on the schedule you set in Merchant Center (default daily).</p>
</div>

<!-- 健康检查 -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1rem">🩺 Pre-flight health check</h3>
  <table class="admin-table" style="margin-top:1rem">
    <tr>
      <td style="width:60%">📦 Products eligible for feed</td>
      <td><strong><?= $feedCount ?></strong> / <?= $total ?> active (<?= $skipped ?> skipped: bundles or no main image)</td>
    </tr>
    <tr>
      <td>🖼 Products missing main image</td>
      <td><?php if ($missingImg === 0): ?><span style="color:var(--success)">✓ 0</span><?php else: ?><span style="color:var(--error)">✗ <?= $missingImg ?> (fix in Products page)</span><?php endif; ?></td>
    </tr>
    <tr>
      <td>📝 Products with short description (&lt;70 chars)</td>
      <td><?php if ($shortDesc === 0): ?><span style="color:var(--success)">✓ all good</span><?php else: ?><span style="color:var(--warn)">⚠ <?= $shortDesc ?> (auto-padded in feed, but consider improving)</span><?php endif; ?></td>
    </tr>
    <tr>
      <td>🔒 HTTPS</td>
      <td><?php if (strpos($base, 'https://') === 0): ?><span style="color:var(--success)">✓ <?= htmlspecialchars($base) ?></span><?php else: ?><span style="color:var(--error)">✗ Required by Google!</span><?php endif; ?></td>
    </tr>
    <?php foreach ($legalCheck as $label => $exists): ?>
    <tr>
      <td>📄 <?= $label ?> page</td>
      <td>
        <?php if ($exists): ?>
          <span style="color:var(--success)">✓</span>
          <a href="<?= htmlspecialchars($base . '/' . strtolower($label) . '.html') ?>" target="_blank" class="muted small">view ↗</a>
        <?php else: ?>
          <span style="color:var(--error)">✗ missing — Google will reject feed</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<!-- 配置 -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1rem">⚙️ Settings</h3>
  <form method="post" style="display:grid;gap:1rem;max-width:520px;margin-top:1rem">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <label>
      <span class="muted small">Brand name (used as &lt;g:brand&gt; in feed)</span>
      <input type="text" name="brand_name" maxlength="70" required value="<?= htmlspecialchars($brand) ?>" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <label>
      <span class="muted small">GMC Merchant ID (optional, for your reference — find it in Merchant Center top-right corner)</span>
      <input type="text" name="gmc_merchant_id" maxlength="40" value="<?= htmlspecialchars($gmcId) ?>" placeholder="e.g. 1234567890" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <button type="submit" class="button button-primary" style="justify-self:start">Save</button>
  </form>
</div>

<!-- 教程链接 -->
<div class="admin-card">
  <h3 style="margin-top:0;font-size:1rem">📖 Setup walkthrough</h3>
  <p>Full step-by-step guide is in the repo:</p>
  <code style="display:inline-block;padding:.4rem .75rem;background:var(--bg-soft);border-radius:4px;color:var(--gold)">MERCHANT-CENTER-SETUP.md</code>
  <p class="muted small" style="margin-top:.75rem">Quick start:</p>
  <ol style="padding-left:1.5rem;line-height:1.8">
    <li>Go to <a href="https://merchants.google.com/" target="_blank" style="color:var(--gold)">merchants.google.com</a> and sign in with info@glameyeshop.com</li>
    <li>Create new account → Pick "United States" → Add business info</li>
    <li>Verify domain (auto-detected if Search Console is set up)</li>
    <li>Set up shipping zones + tax</li>
    <li><strong>Products → Feeds → Add primary feed → Scheduled fetch</strong></li>
    <li>Paste the feed URL above ☝</li>
    <li>Wait 1-2 hours for first fetch + 3-7 days for review</li>
    <li>(Optional) Link Google Ads to enable paid Shopping campaigns</li>
  </ol>
</div>
