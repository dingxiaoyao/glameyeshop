<?php
$pageTitle = 'Google Ads Test';
$activeNav = 'ads-test';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/config.php';

$db = getDb();

// 从 settings 拉当前配置
$stmt = $db->prepare("SELECT `key`, `value` FROM site_settings WHERE `key` IN
    ('ga_measurement_id', 'google_ads_conversion_id', 'google_ads_conversion_label')");
$stmt->execute();
$cfg = [];
foreach ($stmt->fetchAll() as $r) $cfg[$r['key']] = $r['value'];

$gaId  = $cfg['ga_measurement_id'] ?? '';
$awId  = $cfg['google_ads_conversion_id'] ?? '';
$lbl   = $cfg['google_ads_conversion_label'] ?? '';
?>

<h1 style="margin-top:0">🧪 <?= $lang === 'zh' ? 'Google Ads 转化测试器' : 'Google Ads Conversion Tester' ?></h1>
<p class="muted">
  <?= $lang === 'zh'
    ? '不需要真实交易。点下方按钮 → 浏览器立刻 fire 一次 purchase + conversion 事件给 Google Ads。等 1-24 小时,Google Ads 后台 GlamEye Purchase 状态会变成「正在记录转化」。'
    : 'No real transaction needed. Click button → browser fires a purchase + conversion event to Google Ads. Wait 1-24h, the Conversion status flips to "Recording conversions".' ?>
</p>

<div class="admin-card" style="margin-bottom:1.5rem;">
  <h3 style="margin-top:0">📋 <?= $lang === 'zh' ? '当前配置' : 'Current configuration' ?></h3>
  <table class="admin-table">
    <tbody>
      <tr>
        <td style="width:200px;"><strong>GA4 Measurement ID</strong></td>
        <td><code style="color:var(--gold)"><?= htmlspecialchars($gaId ?: '(未设置)') ?></code></td>
        <td style="width:60px;text-align:right;">
          <?= $gaId ? '<span style="color:var(--success,#2c9)">✓</span>' : '<span style="color:var(--error)">✗</span>' ?>
        </td>
      </tr>
      <tr>
        <td><strong>Google Ads Conversion ID</strong></td>
        <td><code style="color:var(--gold)"><?= htmlspecialchars($awId ?: '(留空 — 用 GA4-based 模式)') ?></code></td>
        <td style="text-align:right;">
          <?= $awId ? '<span style="color:var(--success,#2c9)">✓</span>' : '<small class="muted">optional</small>' ?>
        </td>
      </tr>
      <tr>
        <td><strong>Google Ads Conversion Label</strong></td>
        <td><code style="color:var(--gold)"><?= htmlspecialchars($lbl ?: '(留空 — 用 GA4-based 模式)') ?></code></td>
        <td style="text-align:right;">
          <?= $lbl ? '<span style="color:var(--success,#2c9)">✓</span>' : '<small class="muted">optional</small>' ?>
        </td>
      </tr>
    </tbody>
  </table>
  <p class="muted small" style="margin-top:.75rem;">
    <?= $lang === 'zh'
      ? '💡 走新版 Google Ads UI(GA4-based)只需要 GA4 ID。下面会发的事件:'
      : '💡 Modern Google Ads UI (GA4-based) only needs GA4 ID. Events that will fire:' ?>
  </p>
  <ul style="font-size:.85rem;line-height:1.8;color:var(--text);padding-left:1.5rem;">
    <li><code>gtag('event', 'purchase', {...})</code> — GA4 ecommerce 事件(Google Ads 通过 GA4 同步收到)</li>
    <?php if ($awId && $lbl): ?>
    <li><code>gtag('event', 'conversion', {send_to: '<?= htmlspecialchars($awId) ?>/<?= htmlspecialchars($lbl) ?>', ...})</code> — 显式 Google Ads 转化</li>
    <?php endif; ?>
  </ul>
</div>

<?php if (!$gaId): ?>
<div class="admin-card" style="background:rgba(238,90,90,.08);border:1px solid var(--error);">
  <strong style="color:var(--error)">⚠ <?= $lang === 'zh' ? 'GA4 ID 未设置' : 'GA4 ID not set' ?></strong>
  <p class="muted small" style="margin-top:.5rem;">
    <?= $lang === 'zh'
      ? '先去 <a href="settings.php" style="color:var(--gold)">站点设置</a> 填 GA4 Measurement ID(G-…),否则测试事件无法上报。'
      : 'Go to <a href="settings.php" style="color:var(--gold)">Settings</a> and fill GA4 Measurement ID (G-…) first.' ?>
  </p>
</div>
<?php else: ?>

<!-- 加载真实 GA4 lib(用于发事件)-->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId) ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }
  gtag('js', new Date());
  gtag('config', '<?= htmlspecialchars($gaId) ?>');
  <?php if ($awId): ?>
  gtag('config', '<?= htmlspecialchars($awId) ?>');
  <?php endif; ?>
</script>

<div class="admin-card" style="background:rgba(95,207,128,.05);border:1px solid var(--success,#2c9);">
  <h3 style="margin-top:0">🚀 <?= $lang === 'zh' ? '一键 fire 测试事件' : 'Fire test events' ?></h3>

  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem;margin:1rem 0;">
    <label><span class="label-text">Test order ID</span>
      <input type="text" id="tst-id" value="TEST_<?= time() ?>" style="width:100%;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);padding:.4rem .6rem;border-radius:4px;font-family:ui-monospace;" />
    </label>
    <label><span class="label-text">Value (USD)</span>
      <input type="number" id="tst-value" value="99.00" step="0.01" min="1" style="width:100%;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);padding:.4rem .6rem;border-radius:4px;font-family:ui-monospace;" />
    </label>
    <label><span class="label-text">Item SKU</span>
      <input type="text" id="tst-sku" value="GE-CK-NATURAL" style="width:100%;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);padding:.4rem .6rem;border-radius:4px;font-family:ui-monospace;" />
    </label>
  </div>

  <button id="fire-btn" class="button button-primary" style="background:var(--success,#2c9);border-color:var(--success,#2c9);font-size:1rem;padding:.75rem 1.5rem;">
    🚀 <?= $lang === 'zh' ? 'Fire 一次假转化事件' : 'Fire one test conversion now' ?>
  </button>

  <div id="fire-result" style="margin-top:1.5rem;display:none;background:var(--bg);padding:1rem;border-radius:6px;border:1px solid var(--border-soft);">
    <h4 style="margin:0 0 .5rem;color:var(--success,#2c9)">✓ <?= $lang === 'zh' ? '事件已发送' : 'Events fired' ?></h4>
    <pre id="fire-log" style="margin:0;font-family:ui-monospace,Menlo,monospace;font-size:.78rem;line-height:1.6;color:var(--text);white-space:pre-wrap;"></pre>
    <p class="muted small" style="margin-top:.75rem;">
      <strong><?= $lang === 'zh' ? '接下来:' : 'Next:' ?></strong>
      <?= $lang === 'zh'
        ? '等 15分钟 - 24 小时,回 Google Ads → 工具 → 转化操作 → GlamEye Purchase 的 <strong>状态</strong> 列会变成 <strong>正在记录转化</strong>(或者至少看到 1 次最近转化)。'
        : 'Wait 15min-24h, then in Google Ads → Tools → Conversions → GlamEye Purchase, the Status column flips to <strong>Recording conversions</strong> (or at least 1 recent count).' ?>
    </p>
  </div>
</div>

<script>
document.getElementById('fire-btn').addEventListener('click', () => {
  const id    = document.getElementById('tst-id').value;
  const value = parseFloat(document.getElementById('tst-value').value) || 99;
  const sku   = document.getElementById('tst-sku').value || 'TEST-SKU';

  const log = [];
  function logLine(s) { log.push(s); }

  // 1) GA4 purchase event(Google Ads 通过 GA4 同步收)
  const purchaseEvent = {
    transaction_id: id,
    value: value,
    currency: 'USD',
    tax: 0,
    shipping: 0,
    items: [{
      item_id: sku,
      item_name: 'Test Item — ' + sku,
      price: value,
      quantity: 1,
    }],
  };
  gtag('event', 'purchase', purchaseEvent);
  logLine("[1/2] ✓ gtag('event', 'purchase', " + JSON.stringify(purchaseEvent, null, 2) + ")");

  <?php if ($awId && $lbl): ?>
  // 2) Google Ads conversion event(显式,如果配了 ID + Label)
  const convEvent = {
    send_to: '<?= htmlspecialchars($awId) ?>/<?= htmlspecialchars($lbl) ?>',
    value: value,
    currency: 'USD',
    transaction_id: id,
  };
  gtag('event', 'conversion', convEvent);
  logLine("\n[2/2] ✓ gtag('event', 'conversion', " + JSON.stringify(convEvent, null, 2) + ")");
  <?php else: ?>
  logLine("\n[2/2] ⊖ Google Ads conversion (skipped — using GA4-based mode)");
  <?php endif; ?>

  document.getElementById('fire-log').textContent = log.join('\n');
  document.getElementById('fire-result').style.display = 'block';

  // 自动改下次的 test id 避免重复
  document.getElementById('tst-id').value = 'TEST_' + Date.now();
});
</script>

<?php endif; ?>

<details class="admin-card" style="margin-top:2rem;">
  <summary style="cursor:pointer;font-family:var(--serif);color:var(--gold)">🔍 <?= $lang === 'zh' ? '怎么知道 Google 收到了?' : 'How to verify Google received the event?' ?></summary>
  <div style="margin-top:.75rem;font-size:.9rem;line-height:1.7;color:var(--text);">
    <p><strong><?= $lang === 'zh' ? '方式 1: GA4 实时报告(5 秒就能看到)' : 'Method 1: GA4 Realtime (5 second feedback)' ?></strong></p>
    <ol style="padding-left:1.5rem;">
      <li>另开 tab 进 <a href="https://analytics.google.com" target="_blank" style="color:var(--gold)">analytics.google.com</a></li>
      <li>选你的资源(<?= htmlspecialchars($gaId) ?>)</li>
      <li>左侧 <strong>Reports → Realtime</strong></li>
      <li>点上面的 🚀 Fire 按钮 → 5-10 秒后 GA4 Realtime 应该出现 <strong>+1 active user</strong> + <strong>purchase</strong> 事件</li>
      <li>看到 = 成功</li>
    </ol>
    <p><strong><?= $lang === 'zh' ? '方式 2: Google Ads 转化诊断(1-24h 后)' : 'Method 2: Google Ads conversion diagnostics' ?></strong></p>
    <ol style="padding-left:1.5rem;">
      <li>Google Ads → Tools → 转化操作 → 点 <strong>GlamEye Purchase</strong></li>
      <li>详情页 <strong>Webpages</strong> 或 <strong>Status</strong> 区</li>
      <li>状态从 <strong>无效</strong> / <strong>未验证</strong> 变成 <strong>正在记录转化</strong></li>
      <li>注意:不是实时,通常 3-12 小时延迟</li>
    </ol>
    <p><strong><?= $lang === 'zh' ? '方式 3: Tag Assistant(对话框验证)' : 'Method 3: Tag Assistant (modal verification)' ?></strong></p>
    <ol style="padding-left:1.5rem;">
      <li>Tag Assistant 弹窗"测试购买转化操作"开着</li>
      <li>新 tab 进本页 → 点 🚀 Fire 按钮</li>
      <li>回 Tag Assistant 点 <strong>"我想我已经触发了该转化"</strong></li>
      <li>显示绿勾 ✓</li>
    </ol>
  </div>
</details>
