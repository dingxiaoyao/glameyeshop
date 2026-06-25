<?php
// Admin Google Merchant Center 配置 + 产品 feed 健康检查
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
        $flash = '✓ 已保存';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$db = getDb();
$brand = $db->query("SELECT `value` FROM site_settings WHERE `key`='brand_name' LIMIT 1")->fetchColumn() ?: 'GlamEye';
$gmcId = $db->query("SELECT `value` FROM site_settings WHERE `key`='gmc_merchant_id' LIMIT 1")->fetchColumn() ?: '';
$base  = $db->query("SELECT `value` FROM site_settings WHERE `key`='site_base_url' LIMIT 1")->fetchColumn() ?: 'https://glameyeshop.com';
$base  = rtrim($base, '/');

$checks = [];
$products = $db->query("SELECT id, sku, name, description, short_description, image_url, price, stock, is_active, IFNULL(is_bundle,0) as is_bundle FROM products WHERE is_active=1")->fetchAll();
$total = count($products);
$missingImg = 0;
$shortDesc = 0;
$skipped = 0;
foreach ($products as $p) {
    if ($p['is_bundle']) { $skipped++; continue; }
    if (!$p['image_url']) { $missingImg++; $skipped++; continue; }
    $d = trim($p['description'] ?: $p['short_description']);
    if (mb_strlen(strip_tags($d)) < 70) $shortDesc++;
}
$feedCount = $total - $skipped;

$legalPages = [
    'terms.html'    => ['英文' => 'Terms',    '中文' => '服务条款'],
    'privacy.html'  => ['英文' => 'Privacy',  '中文' => '隐私政策'],
    'refund.html'   => ['英文' => 'Refund',   '中文' => '退款政策'],
    'shipping.html' => ['英文' => 'Shipping', '中文' => '配送政策'],
];

require __DIR__ . '/_layout.php';
$csrf = adminCsrfToken();
$feedUrl = $base . '/merchant-feed.xml';
$zh = ($lang === 'zh');
?>
<h1 style="margin-top:0">🛍 <?= $zh ? 'Google Merchant Center 配置' : 'Google Merchant Center' ?></h1>
<p class="muted"><?= $zh
  ? '把产品同步到 Google Shopping(免费列表 + 付费 Shopping 广告)。下面按步骤操作,30 分钟搞定。'
  : 'Sync products to Google Shopping (free listings + paid Shopping ads).' ?></p>

<?php if ($flash): ?>
<div class="admin-card" style="border-left:3px solid <?= $flashType==='error'?'var(--error)':'var(--gold)' ?>;margin-bottom:1.5rem">
  <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<!-- ===== Feed URL ===== -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1rem">📡 <?= $zh ? '产品 Feed 地址' : 'Product Feed URL' ?></h3>
  <p><?= $zh ? '把下面这个 URL 提交到 Merchant Center →「产品 → Feed → 添加主要 Feed」:' : 'Submit this URL to Merchant Center → Products → Feeds → Add primary feed:' ?></p>
  <div style="margin:1rem 0;padding:1rem;background:var(--bg-soft);border-radius:6px;font-family:monospace;font-size:1rem;user-select:all;word-break:break-all">
    🔗 <strong style="color:var(--gold)"><?= htmlspecialchars($feedUrl) ?></strong>
  </div>
  <div style="display:flex;gap:.75rem;flex-wrap:wrap">
    <a href="<?= htmlspecialchars($feedUrl) ?>" target="_blank" class="button button-outline">🔍 <?= $zh ? '预览 XML' : 'Preview feed (raw XML)' ?></a>
    <a href="https://search.google.com/test/rich-results?url=<?= urlencode($feedUrl) ?>" target="_blank" class="button button-outline">✅ <?= $zh ? 'Google 富媒体测试' : 'Google Rich Results test' ?></a>
  </div>
  <p class="muted small" style="margin-top:.75rem"><?= $zh ? 'Google 会按你在 Merchant Center 设置的频率(默认每天)主动拉取这个 URL。' : 'Google will fetch this URL on the schedule you set in Merchant Center (default daily).' ?></p>
</div>

<!-- ===== 健康检查 ===== -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1rem">🩺 <?= $zh ? '提交前健康检查' : 'Pre-flight health check' ?></h3>
  <table class="admin-table" style="margin-top:1rem">
    <tr>
      <td style="width:60%"><?= $zh ? '📦 可入 feed 的产品数' : '📦 Products eligible for feed' ?></td>
      <td><strong><?= $feedCount ?></strong> / <?= $total ?>
        <?= $zh ? '上架(跳过 ' : 'active (' ?><?= $skipped ?>
        <?= $zh ? ' 个套装或无主图)' : ' skipped: bundles or no main image)' ?></td>
    </tr>
    <tr>
      <td><?= $zh ? '🖼 缺少主图的产品' : '🖼 Products missing main image' ?></td>
      <td><?php if ($missingImg === 0): ?>
        <span style="color:var(--success)">✓ 0</span>
      <?php else: ?>
        <span style="color:var(--error)">✗ <?= $missingImg ?>
          <?= $zh ? '(去「产品」页补图)' : '(fix in Products page)' ?></span>
      <?php endif; ?></td>
    </tr>
    <tr>
      <td><?= $zh ? '📝 描述太短(<70 字)的产品' : '📝 Products with short description (<70 chars)' ?></td>
      <td><?php if ($shortDesc === 0): ?>
        <span style="color:var(--success)">✓ <?= $zh ? '全部 OK' : 'all good' ?></span>
      <?php else: ?>
        <span style="color:var(--warn)">⚠ <?= $shortDesc ?>
          <?= $zh ? '(feed 已自动补齐,但建议手动改更准确)' : '(auto-padded in feed)' ?></span>
      <?php endif; ?></td>
    </tr>
    <tr>
      <td>🔒 HTTPS</td>
      <td><?php if (strpos($base, 'https://') === 0): ?>
        <span style="color:var(--success)">✓ <?= htmlspecialchars($base) ?></span>
      <?php else: ?>
        <span style="color:var(--error)">✗ <?= $zh ? 'Google 强制要求!' : 'Required by Google!' ?></span>
      <?php endif; ?></td>
    </tr>
    <?php foreach ($legalPages as $f => $labels): $label = $zh ? $labels['中文'] : $labels['英文']; $exists = file_exists(__DIR__ . '/../' . $f); ?>
    <tr>
      <td>📄 <?= htmlspecialchars($label) ?><?= $zh ? ' 页面' : ' page' ?></td>
      <td>
        <?php if ($exists): ?>
          <span style="color:var(--success)">✓</span>
          <a href="<?= htmlspecialchars($base . '/' . $f) ?>" target="_blank" class="muted small"><?= $zh ? '查看 ↗' : 'view ↗' ?></a>
        <?php else: ?>
          <span style="color:var(--error)">✗ <?= $zh ? '缺失 — Google 会拒绝 feed' : 'missing — Google will reject' ?></span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<!-- ===== 配置 ===== -->
<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1rem">⚙️ <?= $zh ? '基本设置' : 'Settings' ?></h3>
  <form method="post" style="display:grid;gap:1rem;max-width:520px;margin-top:1rem">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>" />
    <label>
      <span class="muted small"><?= $zh ? '品牌名(写入 feed 的 <g:brand>)' : 'Brand name (used as <g:brand>)' ?></span>
      <input type="text" name="brand_name" maxlength="70" required value="<?= htmlspecialchars($brand) ?>" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <label>
      <span class="muted small"><?= $zh ? 'GMC 商户 ID(可选,登录 Merchant Center 后右上角能看到)' : 'GMC Merchant ID (optional)' ?></span>
      <input type="text" name="gmc_merchant_id" maxlength="40" value="<?= htmlspecialchars($gmcId) ?>" placeholder="<?= $zh ? '例:1234567890' : 'e.g. 1234567890' ?>" style="width:100%;padding:.5rem;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);border-radius:4px" />
    </label>
    <button type="submit" class="button button-primary" style="justify-self:start"><?= $zh ? '保存' : 'Save' ?></button>
  </form>
</div>

<!-- ===== 中文配置流程(全展开,默认就显示) ===== -->
<?php if ($zh): ?>
<div class="admin-card" style="margin-bottom:1.5rem">
  <h3 style="margin-top:0;font-size:1.1rem">📖 完整配置流程(8 步,约 30 分钟)</h3>

  <div style="line-height:1.85;font-size:.95rem;color:var(--text)">

  <h4 style="color:var(--gold);margin-top:1.5rem;font-size:1rem">第 1 步 — 注册 Google Merchant Center 账号</h4>
  <ol style="padding-left:1.5rem">
    <li>打开 <a href="https://merchants.google.com/" target="_blank" style="color:var(--gold)">https://merchants.google.com/</a></li>
    <li>用 <code style="background:var(--bg-soft);padding:.1rem .4rem;border-radius:3px">info@glameyeshop.com</code>(和 Google Ads / GSC / GA 同一个账号)登录</li>
    <li>点 <strong>「立即开始」</strong> → 按提示填:
      <ul style="padding-left:1.5rem;margin-top:.3rem">
        <li>业务名称:<code>GlamEye</code></li>
        <li>国家/地区:<code>美国 (United States)</code></li>
        <li>时区:<code>America/Los_Angeles</code></li>
        <li>"是否在 Shopify 等平台?" → 选 <strong>「不,我用自己的网站」</strong></li>
      </ul>
    </li>
    <li>填业务信息:
      <ul style="padding-left:1.5rem;margin-top:.3rem">
        <li>网站 URL:<code>https://glameyeshop.com</code></li>
        <li>客服邮箱:<code>info@glameyeshop.com</code></li>
      </ul>
    </li>
  </ol>

  <h4 style="color:var(--gold);margin-top:1.5rem;font-size:1rem">第 2 步 — 验证 + 申领网站所有权</h4>
  <p>Google 要确认 glameyeshop.com 是你的。三种方式选一种:</p>
  <table class="admin-table" style="margin:.5rem 0">
    <tr><th style="width:120px">方式</th><th>操作</th><th>难度</th></tr>
    <tr><td><strong>A. Search Console 同步</strong></td>
        <td>如果你之前已经在 Google Search Console 验证过 glameyeshop.com 域名,Merchant Center 会<strong>自动检测并通过</strong>,直接点 Claim 即可</td>
        <td><span style="color:var(--success)">⭐ 最简单</span></td></tr>
    <tr><td>B. HTML meta 标签</td>
        <td>Google 会给一段 <code>&lt;meta name="google-site-verification" content="abc..." /&gt;</code>,把那段 content 字符串发给我,我帮你加到首页 head 里</td>
        <td><span style="color:var(--warn)">需我协助</span></td></tr>
    <tr><td>C. HTML 文件</td>
        <td>下载 google5xxx.html,放到网站根目录</td>
        <td><span class="muted">不推荐(残留垃圾文件)</span></td></tr>
  </table>

  <h4 style="color:var(--gold);margin-top:1.5rem;font-size:1rem">第 3 步 — 配置运费 + 退货政策</h4>
  <p>左侧菜单 → <strong>「工具与设置」→「运费和退货」</strong></p>
  <p><strong>运费</strong>:点 <strong>「+ 添加运费服务」</strong>:</p>
  <table class="admin-table" style="margin:.5rem 0">
    <tr><th style="width:200px">字段</th><th>填什么</th></tr>
    <tr><td>服务名称</td><td><code>Standard Shipping</code></td></tr>
    <tr><td>国家/地区</td><td><code>美国 (United States)</code></td></tr>
    <tr><td>币种</td><td><code>USD</code></td></tr>
    <tr><td>派送时间</td><td><code>3-7 个工作日</code></td></tr>
    <tr><td>运费</td><td><strong>固定运费 $5.99</strong>(可勾"满 $50 免运")</td></tr>
  </table>
  <p style="margin-top:1rem"><strong>退货政策</strong>:同界面切到「退货」→ <strong>「+ 添加退货政策」</strong>:</p>
  <table class="admin-table" style="margin:.5rem 0">
    <tr><th style="width:200px">字段</th><th>填什么</th></tr>
    <tr><td>退货窗口</td><td><code>14 天</code></td></tr>
    <tr><td>谁付退货运费</td><td><code>买家</code></td></tr>
    <tr><td>退货政策 URL</td><td><code><?= htmlspecialchars($base) ?>/refund.html</code></td></tr>
  </table>

  <h4 style="color:var(--gold);margin-top:1.5rem;font-size:1rem">第 4 步 — 提交产品 Feed(核心!)</h4>
  <ol style="padding-left:1.5rem">
    <li>左侧菜单 → <strong>「产品」→「Feed」</strong> → 右上角 <strong>「+ 添加主要 Feed」</strong></li>
    <li>配置:
      <table class="admin-table" style="margin:.5rem 0">
        <tr><th style="width:200px">字段</th><th>填什么</th></tr>
        <tr><td>销售国家/地区</td><td><code>美国</code></td></tr>
        <tr><td>语言</td><td><code>英语 (English)</code></td></tr>
        <tr><td>目标位置</td><td>✅ <strong>Shopping ads</strong> · ✅ <strong>免费商品详情列表</strong>(都勾)</td></tr>
        <tr><td>Feed 名称</td><td><code>GlamEye Live Feed</code></td></tr>
        <tr><td>输入方式</td><td><strong>「计划性抓取」(Scheduled fetch)</strong> ← 关键!</td></tr>
      </table>
    </li>
    <li>「计划性抓取」详细配置:
      <table class="admin-table" style="margin:.5rem 0">
        <tr><th style="width:200px">字段</th><th>填什么</th></tr>
        <tr><td><strong>抓取 URL</strong></td>
            <td><code style="color:var(--gold);user-select:all"><?= htmlspecialchars($feedUrl) ?></code> ← 复制上面那行</td></tr>
        <tr><td>抓取频率</td><td><code>每天 (Daily)</code></td></tr>
        <tr><td>抓取时间</td><td><code>凌晨 02:00 太平洋时间</code></td></tr>
        <tr><td>用户名 / 密码</td><td><strong>留空</strong>(我们的接口公开访问)</td></tr>
      </table>
    </li>
    <li>点 <strong>「创建 Feed」</strong> → Google 立即拉一次测试 → 1-5 分钟后看 Feed 列表的状态</li>
  </ol>

  <h4 style="color:var(--gold);margin-top:1.5rem;font-size:1rem">第 5 步 — 等审核(3-7 天)</h4>
  <p>Google 后台会做几件事:</p>
  <ul style="padding-left:1.5rem">
    <li><strong>24 小时内</strong>:爬几个产品 URL 检查页面真实存在 + 价格一致 + 政策页齐全</li>
    <li><strong>3-7 天</strong>:人工 + 算法审核账号合规</li>
    <li>通过后产品自动出现在 <a href="https://google.com/shopping" target="_blank" style="color:var(--gold)">google.com/shopping</a> 搜索结果里(<strong>免费!</strong>)</li>
  </ul>
  <p style="margin-top:.5rem">期间可能收到的邮件:</p>
  <table class="admin-table" style="margin:.5rem 0">
    <tr><th>邮件主题</th><th>含义</th><th>怎么办</th></tr>
    <tr><td>Account suspended</td><td>账号被封</td><td>大概率是缺政策页/价格不对/图不清。点邮件里 Request review</td></tr>
    <tr><td>Items disapproved</td><td>部分产品被拒</td><td>进 Diagnostics 看具体 SKU + 原因,改完重拉 feed</td></tr>
    <tr><td>Account verified ✓</td><td>通过</td><td>24h 内 google.com/shopping 能搜到</td></tr>
  </table>

  <h4 style="color:var(--gold);margin-top:1.5rem;font-size:1rem">第 6 步(可选)— 关联 Google Ads,投付费 Shopping 广告</h4>
  <ol style="padding-left:1.5rem">
    <li>Merchant Center 右上角齿轮 → <strong>「关联的账号」</strong> → <strong>Google Ads</strong> → <strong>Link</strong></li>
    <li>去 Google Ads → <strong>「+ 新广告系列」</strong> → 目标 <strong>「销售」</strong> → 类型 <strong>「Shopping」</strong></li>
    <li>选刚关联的 Merchant Center → <strong>「标准 Shopping 广告系列」</strong></li>
    <li>配置:
      <table class="admin-table" style="margin:.5rem 0">
        <tr><th>字段</th><th>新手推荐</th></tr>
        <tr><td>每日预算</td><td><code>$5/天</code> 起步,跑 1 周看数据</td></tr>
        <tr><td>出价策略</td><td><code>「最大化点击次数」</code> → 有 30+ 转化后换 Target ROAS</td></tr>
        <tr><td>网络</td><td>✅ Google 搜索 ✅ 搜索合作伙伴 ✅ YouTube</td></tr>
        <tr><td>地理位置</td><td><code>美国</code></td></tr>
      </table>
    </li>
  </ol>

  <h4 style="color:var(--gold);margin-top:1.5rem;font-size:1rem">第 7 步 — 部署 + 自我验证</h4>
  <p>本地终端跑:</p>
  <pre style="background:var(--bg-soft);padding:.75rem;border-radius:4px;font-size:.85rem;overflow:auto">cd ~/glameyeshop &amp;&amp; git push origin main</pre>
  <p>等 2-3 分钟部署完后,浏览器开 <code><?= htmlspecialchars($feedUrl) ?></code>,
  应该看到一堆 <code>&lt;item&gt;...&lt;/item&gt;</code> XML 输出 — 这就是 Google 会拉的数据。</p>

  <h4 style="color:var(--gold);margin-top:1.5rem;font-size:1rem">第 8 步 — 常见审核拒因 + 修法</h4>
  <table class="admin-table" style="margin:.5rem 0">
    <tr><th>拒因</th><th>修法</th></tr>
    <tr><td>缺退货政策页</td><td>回上面 GMC「运费和退货」填 refund.html 的完整 URL</td></tr>
    <tr><td>价格不一致(feed vs 落地页)</td><td>我们用同一 DB,不会出错。促销定时器可能错位 — 等 24h GMC 缓存刷新</td></tr>
    <tr><td>图片分辨率太低</td><td>主图必须 ≥ 250×250 像素。GlamEye 现有图 1024 像素,合规</td></tr>
    <tr><td>主图带促销文字</td><td>主图不能写 "Sale" / "Free Shipping"。GlamEye 现有图无此问题</td></tr>
    <tr><td>"Untrusted store" 新店警告</td><td>1) 启用 Google Customer Reviews 收评分 2) 接 Trustpilot 3) 等 30 天信任建立</td></tr>
  </table>

  <p style="margin-top:1.5rem;padding:1rem;background:rgba(212,169,85,.1);border-left:3px solid var(--gold);border-radius:4px">
    <strong>💡 一句话总结:</strong><br>
    1) 注册 GMC → 2) 验证 glameyeshop.com → 3) 配运费 + 退货 → 4) 提交 feed URL <code style="color:var(--gold)"><?= htmlspecialchars($feedUrl) ?></code> → 5) 等 3-7 天通过 → 6) 关联 Google Ads 投 Shopping
  </p>

  </div>
</div>

<!-- 后续优化 -->
<div class="admin-card">
  <h3 style="margin-top:0;font-size:1rem">🚀 通过审核后的优化清单</h3>
  <table class="admin-table" style="margin-top:1rem">
    <tr><th>优化项</th><th>怎么做</th><th>预期效果</th></tr>
    <tr><td>开启 Google Customer Reviews</td><td>GMC → 商家计划 → Customer Reviews → 启用</td><td>5⭐ 评分显示在 Shopping 卡片,CTR +30%</td></tr>
    <tr><td>Free listing 优化标题</td><td>把产品 title 改成 <code>GlamEye Naked 18mm Mink Cluster Lashes - DIY Extensions Reusable 20 Wears</code> 这种长尾关键词式</td><td>免费列表排名上升</td></tr>
    <tr><td>促销标签</td><td>GMC → 营销 → 促销活动(免费)</td><td>产品卡右上角显示 "10% off" 标签,点击率 ↑</td></tr>
    <tr><td>多国扩展</td><td>GMC → 国家/地区 → 添加(加拿大 / 英国 / 欧盟逐个加)</td><td>全球流量</td></tr>
  </table>
</div>

<?php else: ?>
<!-- 英文版精简版(原版) -->
<div class="admin-card">
  <h3 style="margin-top:0;font-size:1rem">📖 Setup walkthrough</h3>
  <p>Quick start:</p>
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
  <p class="muted small" style="margin-top:.75rem">Full Chinese guide: switch language to 中 in top-right.</p>
</div>
<?php endif; ?>
