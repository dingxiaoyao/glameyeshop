<?php
$pageTitle = 'Site Settings';
$activeNav = 'settings';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../api/lib/upload-hints.php';
?>
<h1>⚙️ <?= $lang === 'zh' ? '站点设置' : 'Site Settings' ?></h1>
<p class="muted" style="margin-bottom:1.5rem;">
  <?= $lang === 'zh' ? '按类别分组,选 tab 切换。改完点底部"Save"应用。' : 'Grouped by topic — click a tab to switch. Hit "Save" at the bottom when done.' ?>
</p>

<nav class="settings-tabs" id="settings-tabs">
  <a href="#branding"  class="settings-tab" data-tab="branding">🪄 <?= $lang === 'zh' ? '品牌 / 社交' : 'Branding' ?></a>
  <a href="#hero"      class="settings-tab" data-tab="hero">🎬 <?= $lang === 'zh' ? '首页 Hero' : 'Hero' ?></a>
  <a href="#payments"  class="settings-tab" data-tab="payments">💳 <?= $lang === 'zh' ? '支付' : 'Payments' ?></a>
  <a href="#signin"    class="settings-tab" data-tab="signin">🔑 <?= $lang === 'zh' ? '第三方登录' : 'Sign-in' ?></a>
  <a href="#email"     class="settings-tab" data-tab="email">📧 <?= $lang === 'zh' ? '邮件 / 客服' : 'Email & Support' ?></a>
  <a href="#images"    class="settings-tab" data-tab="images">🖼 <?= $lang === 'zh' ? '图片优化' : 'Images' ?></a>
  <a href="#seo"       class="settings-tab" data-tab="seo">🔒 <?= $lang === 'zh' ? 'SEO / 隐私' : 'SEO / Privacy' ?></a>
</nav>

<!-- ───── Branding ───── -->
<div class="settings-section" data-section="branding">
<div class="admin-card">
  <h3><?= $lang === 'zh' ? '站点地址' : 'Site Base URL' ?></h3>
  <p class="muted small" style="margin-bottom:.75rem;">
    <?= $lang === 'zh'
        ? '所有外部链接(Stripe success/cancel、订单邮件 link、退订 link、sitemap)都用这个 URL。换域名/迁移时来这里改。<strong>必须包含 https://</strong>。'
        : 'All external links (Stripe success/cancel URLs, order emails, unsubscribe links, sitemap) use this base. <strong>Must include https://</strong>.' ?>
  </p>
  <label>
    <span class="label-text">Site Base URL</span>
    <input type="url" data-key="site_base_url" placeholder="https://glameyeshop.com" required />
  </label>
</div>

<div class="admin-card">
  <h3>🛒 <?= $lang === 'zh' ? '结算流程' : 'Checkout' ?></h3>
  <label style="display:flex;align-items:center;gap:.65rem;cursor:pointer;padding:.5rem 0;">
    <input type="checkbox" data-key="require_login_for_checkout" data-bool="1" />
    <span>
      <strong><?= $lang === 'zh' ? '必须登录才能下单' : 'Require login before checkout' ?></strong>
      <small style="display:block;color:var(--text-muted);font-weight:400;margin-top:.15rem;">
        <?= $lang === 'zh'
            ? '关闭后允许 guest checkout(转化率高,但失去客户数据 + 复购联系方式)。'
            : 'Turn off to allow guest checkout (higher conversion, but lose customer email + repeat-purchase contact).' ?>
      </small>
    </span>
  </label>
</div>

<div class="admin-card">
  <h3>🌍 <?= $lang === 'zh' ? '国际下单 & 运费' : 'International Shipping' ?></h3>

  <style>
    .country-chip-wrap { display:flex; flex-wrap:wrap; gap:.35rem; padding:.5rem; background:var(--bg); border:1px solid var(--border); border-radius:6px; min-height:42px; align-items:center; }
    .country-chip { display:inline-flex; align-items:center; gap:.25rem; background:var(--bg-soft); padding:.2rem .5rem; border-radius:999px; font-size:.78rem; font-weight:500; color:var(--cream); border:1px solid var(--border-soft); }
    .country-chip .remove { cursor:pointer; opacity:.5; padding:0 .2rem; }
    .country-chip .remove:hover { opacity:1; color:var(--error,#c33); }
    .country-chip-input { border:0; background:transparent; outline:none; font-size:.85rem; flex:1; min-width:120px; padding:.2rem; color:var(--cream); }

    .zone-table { width:100%; border-collapse:collapse; margin:.5rem 0; }
    .zone-table th { font-size:.7rem; letter-spacing:1.2px; text-transform:uppercase; color:var(--text-muted); padding:.55rem .65rem; text-align:left; border-bottom:1px solid var(--border-soft); font-weight:600; }
    .zone-table td { padding:.4rem .55rem; border-bottom:1px solid var(--border-soft); vertical-align:middle; }
    .zone-table tr:last-child td { border-bottom:0; }
    .zone-table input[type="text"], .zone-table input[type="number"] { padding:.4rem .6rem; font-size:.85rem; width:100%; }
    .zone-table input[type="text"] { font-family:monospace; }
    .zone-table .zone-key { color:var(--gold); font-weight:600; font-family:monospace; font-size:.85rem; }
    .zone-remove-btn { background:transparent; border:0; cursor:pointer; color:var(--text-muted); font-size:1rem; padding:.25rem .5rem; }
    .zone-remove-btn:hover { color:var(--error,#c33); }
    .zone-default-row { background:rgba(184,146,78,.05); }
    .zone-default-row .zone-key { color:var(--text-muted); }
  </style>

  <h4 style="margin:1rem 0 .5rem;font-size:.95rem;color:var(--cream);">
    <?= $lang === 'zh' ? '🌐 可送达国家' : '🌐 Enabled countries' ?>
  </h4>
  <p class="muted small" style="margin:0 0 .5rem;">
    <?= $lang === 'zh'
        ? '点击下方常见国家添加,或在输入框输入 ISO 2 字母代码(如 MX、AE)按 Enter。已添加的国家会在 checkout 国家下拉里出现。'
        : 'Click presets below to add, or type an ISO 2-letter code (e.g. MX, AE) and press Enter. Added countries appear in the checkout country dropdown.' ?>
  </p>
  <div class="country-chip-wrap" id="country-chip-wrap">
    <span class="muted small" id="country-empty-hint" hidden>Click a preset or type a code →</span>
    <input type="text" class="country-chip-input" id="country-input" placeholder="Type ISO code (US, CA, GB…) + Enter" maxlength="2" autocomplete="off" />
  </div>
  <input type="hidden" data-key="enabled_countries" id="enabled-countries-hidden" />

  <div style="margin-top:.65rem;display:flex;flex-wrap:wrap;gap:.3rem;">
    <small class="muted" style="margin-right:.5rem;align-self:center;font-size:.75rem;">Quick add:</small>
    <?php
    $presets = [
      'US'=>'🇺🇸','CA'=>'🇨🇦','GB'=>'🇬🇧','AU'=>'🇦🇺','DE'=>'🇩🇪','FR'=>'🇫🇷',
      'IT'=>'🇮🇹','ES'=>'🇪🇸','NL'=>'🇳🇱','JP'=>'🇯🇵','SG'=>'🇸🇬','HK'=>'🇭🇰',
      'TW'=>'🇹🇼','CN'=>'🇨🇳','KR'=>'🇰🇷','MX'=>'🇲🇽','BR'=>'🇧🇷','AE'=>'🇦🇪',
      'IN'=>'🇮🇳','MY'=>'🇲🇾','TH'=>'🇹🇭','VN'=>'🇻🇳','ID'=>'🇮🇩','PH'=>'🇵🇭','NZ'=>'🇳🇿',
    ];
    foreach ($presets as $code => $emoji): ?>
      <button type="button" class="country-preset-btn" data-code="<?= $code ?>"
              style="font-size:.75rem;padding:.2rem .5rem;background:var(--bg-soft);border:1px solid var(--border-soft);border-radius:4px;cursor:pointer;color:var(--cream);">
        <?= $emoji ?> <?= $code ?>
      </button>
    <?php endforeach; ?>
  </div>

  <hr style="border:0;border-top:1px solid var(--border-soft);margin:1.5rem 0;">

  <h4 style="margin:0 0 .5rem;font-size:.95rem;color:var(--cream);">
    <?= $lang === 'zh' ? '🚚 运费分区' : '🚚 Shipping rates by zone' ?>
  </h4>
  <p class="muted small" style="margin:0 0 .5rem;">
    <?= $lang === 'zh'
        ? '每个国家用 ISO 代码(US/CA…)做 key。EU 和 ASIA 是通用 zone(具体国家未单列时走通用)。default 是最后兜底,所有未匹配国家走它。'
        : 'Key = country ISO code (US/CA…) for country-specific rates. EU and ASIA are fallback zones. default catches everything else.' ?>
  </p>

  <table class="zone-table" id="zone-table">
    <thead>
      <tr>
        <th style="width:25%;">Zone / Country</th>
        <th style="width:25%;">Shipping price (USD)</th>
        <th style="width:35%;">Free over (USD)</th>
        <th style="width:15%;"></th>
      </tr>
    </thead>
    <tbody id="zone-tbody"></tbody>
  </table>

  <button type="button" id="add-zone-btn" class="button button-outline" style="font-size:.8rem;padding:.4rem 1rem;margin-top:.5rem;">
    + Add zone or country
  </button>

  <input type="hidden" data-key="shipping_zones" id="shipping-zones-hidden" />

  <script>
    (function () {
      // ============ Country chip input ============
      const chipWrap = document.getElementById('country-chip-wrap');
      const chipInput = document.getElementById('country-input');
      const countriesHidden = document.getElementById('enabled-countries-hidden');
      const emptyHint = document.getElementById('country-empty-hint');
      let enabledCountries = [];

      function renderChips() {
        chipWrap.querySelectorAll('.country-chip').forEach(el => el.remove());
        const map = {US:'🇺🇸',CA:'🇨🇦',GB:'🇬🇧',AU:'🇦🇺',DE:'🇩🇪',FR:'🇫🇷',IT:'🇮🇹',ES:'🇪🇸',NL:'🇳🇱',JP:'🇯🇵',SG:'🇸🇬',HK:'🇭🇰',TW:'🇹🇼',CN:'🇨🇳',KR:'🇰🇷',MX:'🇲🇽',BR:'🇧🇷',AE:'🇦🇪',IN:'🇮🇳',MY:'🇲🇾',TH:'🇹🇭',VN:'🇻🇳',ID:'🇮🇩',PH:'🇵🇭',NZ:'🇳🇿',BE:'🇧🇪',AT:'🇦🇹',CH:'🇨🇭',IE:'🇮🇪',PT:'🇵🇹',PL:'🇵🇱',SE:'🇸🇪',NO:'🇳🇴',DK:'🇩🇰',FI:'🇫🇮',IL:'🇮🇱',SA:'🇸🇦',ZA:'🇿🇦'};
        emptyHint.hidden = enabledCountries.length > 0;
        enabledCountries.forEach(code => {
          const chip = document.createElement('span');
          chip.className = 'country-chip';
          chip.innerHTML = (map[code] || '🌐') + ' ' + code + ' <span class="remove" data-code="' + code + '">×</span>';
          chipWrap.insertBefore(chip, chipInput);
        });
        countriesHidden.value = JSON.stringify(enabledCountries);
      }

      function addCountry(code) {
        code = (code || '').trim().toUpperCase();
        if (!/^[A-Z]{2}$/.test(code)) return;
        if (enabledCountries.indexOf(code) === -1) {
          enabledCountries.push(code);
          renderChips();
        }
      }

      chipInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',' || e.key === ' ') {
          e.preventDefault();
          addCountry(chipInput.value);
          chipInput.value = '';
        } else if (e.key === 'Backspace' && chipInput.value === '' && enabledCountries.length > 0) {
          enabledCountries.pop();
          renderChips();
        }
      });
      chipWrap.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove')) {
          const code = e.target.dataset.code;
          enabledCountries = enabledCountries.filter(c => c !== code);
          renderChips();
        } else if (e.target === chipWrap) {
          chipInput.focus();
        }
      });
      document.querySelectorAll('.country-preset-btn').forEach(b => b.addEventListener('click', () => addCountry(b.dataset.code)));

      // ============ Zone table ============
      const zonesTbody = document.getElementById('zone-tbody');
      const zonesHidden = document.getElementById('shipping-zones-hidden');
      let zones = {};  // { US: {price, free_threshold}, ... }

      function renderZones() {
        zonesTbody.innerHTML = '';
        const keys = Object.keys(zones).sort((a, b) => {
          if (a === 'default') return 1;  // default 排末尾
          if (b === 'default') return -1;
          return a.localeCompare(b);
        });
        keys.forEach(k => {
          const z = zones[k] || {};
          const tr = document.createElement('tr');
          if (k === 'default') tr.className = 'zone-default-row';
          tr.innerHTML = `
            <td><span class="zone-key">${k}</span>${k === 'default' ? '<br><small class="muted" style="font-size:.7rem;">catches any country not listed</small>' : ''}</td>
            <td><input type="number" min="0" step="0.01" value="${Number(z.price || 0).toFixed(2)}" data-zone="${k}" data-field="price" /></td>
            <td><input type="number" min="0" step="0.01" value="${Number(z.free_threshold || 0).toFixed(2)}" data-zone="${k}" data-field="free_threshold" placeholder="0 = no free shipping" /></td>
            <td>${k === 'default' ? '' : `<button type="button" class="zone-remove-btn" data-zone="${k}" title="Remove zone">🗑️</button>`}</td>`;
          zonesTbody.appendChild(tr);
        });
        zonesHidden.value = JSON.stringify(zones);
      }

      zonesTbody.addEventListener('input', (e) => {
        const inp = e.target;
        if (inp.dataset.zone && inp.dataset.field) {
          const k = inp.dataset.zone;
          if (!zones[k]) zones[k] = { price: 0, free_threshold: 0 };
          zones[k][inp.dataset.field] = Number(inp.value) || 0;
          zonesHidden.value = JSON.stringify(zones);
        }
      });
      zonesTbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.zone-remove-btn');
        if (btn) {
          if (confirm('Remove zone "' + btn.dataset.zone + '"?')) {
            delete zones[btn.dataset.zone];
            renderZones();
          }
        }
      });

      document.getElementById('add-zone-btn').addEventListener('click', () => {
        const k = prompt('Zone key — use:\n  • Country ISO code (US, CA, JP…) for country-specific\n  • EU for European Union fallback\n  • ASIA for Asia-Pacific fallback\n  • A custom name like "NORTH_AMERICA"\n\nKey:', '');
        if (!k) return;
        const key = k.trim().toUpperCase().replace(/[^A-Z0-9_]/g, '');
        if (!key) { alert('Invalid key'); return; }
        if (zones[key]) { alert('Zone "' + key + '" already exists'); return; }
        zones[key] = { price: 9.99, free_threshold: 75 };
        renderZones();
      });

      // ============ Load from settings.php ============
      // 等外面的 load() 跑完后,读 hidden input 反序列化(load 会填 value)
      function tryParse() {
        try { enabledCountries = JSON.parse(countriesHidden.value || '[]'); } catch { enabledCountries = []; }
        try { zones = JSON.parse(zonesHidden.value || '{}'); } catch { zones = {}; }
        // 兜底:没有 default zone 时补一个
        if (!zones.default) zones.default = { price: 29.99, free_threshold: 150 };
        renderChips();
        renderZones();
      }
      // load() 在大 admin 里是 async,我们 polling 等它填值
      let polls = 0;
      const pollLoad = setInterval(() => {
        if (countriesHidden.value || polls++ > 30) {
          clearInterval(pollLoad);
          tryParse();
        }
      }, 100);
    })();
  </script>
</div>

<div class="admin-card">
  <h3>Social Media URLs</h3>
  <div class="form-group" id="settings-form">
    <label><span class="label-text">TikTok</span><input type="url" data-key="social_tiktok" placeholder="https://www.tiktok.com/@glameye" /></label>
    <label><span class="label-text">Instagram</span><input type="url" data-key="social_instagram" placeholder="https://instagram.com/glameye" /></label>
    <label><span class="label-text">YouTube</span><input type="url" data-key="social_youtube" placeholder="https://youtube.com/@glameye" /></label>
    <label><span class="label-text">Pinterest</span><input type="url" data-key="social_pinterest" placeholder="https://pinterest.com/glameye" /></label>
    <label><span class="label-text">Facebook</span><input type="url" data-key="social_facebook" placeholder="https://facebook.com/glameye" /></label>
  </div>
</div>

<div class="admin-card">
  <h3>Amazon Store</h3>
  <div class="form-group">
    <label>
      <span class="label-text">Amazon Store URL</span>
      <input type="url" data-key="amazon_store_url" placeholder="https://amazon.com/stores/glameye/page/..." />
    </label>
    <label>
      <span class="label-text">Status</span>
      <select data-key="amazon_status">
        <option value="coming_soon">Coming Soon</option>
        <option value="live">Live (show link in footer)</option>
        <option value="hidden">Hidden</option>
      </select>
    </label>
  </div>
</div>

</div><!-- /branding -->

<!-- ───── SEO / Privacy ───── -->
<div class="settings-section" data-section="seo">
<div class="admin-card" style="border-color: var(--warn);">
  <h3 style="color: var(--warn);">🚧 SEO / Search Engines</h3>
  <div class="form-group">
    <div class="checkbox-row">
      <input type="checkbox" data-key="seo_blocked" id="seo-blocked-toggle" data-bool="1" />
      <label for="seo-blocked-toggle">
        <strong style="color: var(--cream);">Block search engines</strong><br>
        <small class="muted"><?= $lang === 'zh' ? '勾选 = robots.txt 拦截 + 所有页面注入 noindex meta。准备正式发布时取消勾选即可。' : 'When checked: robots.txt blocks all crawlers + noindex meta injected on every page. Uncheck when ready to launch publicly.' ?></small>
      </label>
    </div>
  </div>
</div>

</div><!-- /seo -->

<!-- ───── Hero ───── -->
<div class="settings-section" data-section="hero">
<div class="admin-card">
  <h3>🎞️ Homepage Hero Slideshow</h3>
  <p class="muted small" style="margin-bottom:1rem;">
    <?= $lang === 'zh' ? '上传多张图（拖拽排序，第一张优先展示），首页将自动轮播。' : 'Upload multiple images (drag to reorder · first one shows first). They auto-rotate on the homepage.' ?>
  </p>
  <?= uploadHint('hero', $lang) ?>
  <div class="img-uploader">
    <div class="img-tiles" id="hero-tiles"></div>
    <label class="img-add-btn" data-hint="hero">
      <span style="font-size:1.5rem;">＋</span>
      <span>📤 <?= $lang === 'zh' ? '点击上传多张图' : 'Click to upload (multiple)' ?></span>
      <input type="file" accept="image/*" multiple hidden id="hero-upload" />
    </label>
    <small id="hero-status" class="muted" style="display:block; margin-top:.5rem;"></small>
  </div>
  <div class="form-row" style="margin-top:1rem;">
    <label><span class="label-text">Slide interval (ms)</span>
      <input type="number" data-key="hero_slide_interval" min="1500" step="500" value="5000" style="max-width:160px;" />
    </label>
  </div>
  <!-- hidden field: stored as JSON array of URLs -->
  <input type="hidden" data-key="hero_image_urls" id="hero-images-json" />
  <!-- legacy single field kept in sync (first image) for backward compat -->
  <input type="hidden" data-key="hero_image_url" id="hero-url-input" />
</div>

</div><!-- /hero -->

<!-- ───── Payments ───── -->
<div class="settings-section" data-section="payments">
<div class="admin-card" id="stripe-card">
  <?php
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'glameyeshop.com';
    $webhookUrl = $scheme . '://' . $host . '/api/stripe-webhook.php';
  ?>

  <style>
    /* Stripe wizard 样式 */
    .stripe-status-bar { display:flex; align-items:center; gap:1rem; padding:.85rem 1.1rem; border-radius:10px; margin-bottom:1.25rem; font-weight:500; }
    .stripe-status-bar.ok    { background:rgba(95,207,128,.12);  color:var(--success,#2c9); border:1px solid rgba(95,207,128,.3); }
    .stripe-status-bar.warn  { background:rgba(247,185,85,.12);  color:var(--warn,#d49f3a); border:1px solid rgba(247,185,85,.3); }
    .stripe-status-bar.empty { background:var(--bg-soft);         color:var(--text-muted);    border:1px solid var(--border-soft); }

    .stripe-steps { display:flex; gap:.5rem; margin-bottom:1.5rem; }
    .stripe-step  { flex:1; padding:.85rem .75rem; background:var(--bg-soft); border:1px solid var(--border-soft); border-radius:8px; text-align:center; font-size:.85rem; color:var(--text-muted); position:relative; cursor:pointer; transition:all .2s; }
    .stripe-step.done  { background:rgba(95,207,128,.08); color:var(--success,#2c9); border-color:rgba(95,207,128,.3); }
    .stripe-step.done::before { content:"✓ "; }
    .stripe-step.current { background:var(--bg-card); color:var(--gold); border-color:var(--gold); box-shadow:0 0 0 3px rgba(184,146,78,.15); }
    .stripe-step.current::before { content:"● "; }
    .stripe-step .step-num { display:block; font-size:.65rem; letter-spacing:1.5px; text-transform:uppercase; margin-bottom:.1rem; opacity:.7; }

    .stripe-panel { padding:1.25rem; background:var(--bg-soft); border-radius:10px; border-left:4px solid var(--gold); margin-bottom:1rem; }
    .stripe-panel h4 { margin:0 0 .85rem; font-size:1rem; color:var(--cream); display:flex; align-items:center; gap:.5rem; }
    .stripe-panel .panel-cta { display:inline-flex; align-items:center; gap:.4rem; background:var(--gold); color:#fff; padding:.5rem 1rem; border-radius:6px; text-decoration:none; font-size:.85rem; font-weight:500; margin:.25rem 0; }
    .stripe-panel .panel-cta:hover { background:var(--gold-dark); color:#fff; }

    .url-copy-row { display:flex; gap:.5rem; align-items:center; margin:.65rem 0; flex-wrap:wrap; }
    .url-copy-row code { background:var(--bg); padding:.5rem .75rem; border-radius:6px; border:1px solid var(--border); flex:1; min-width:0; overflow-x:auto; white-space:nowrap; font-size:.78rem; }
    .url-copy-row .copy-btn { padding:.5rem 1rem; font-size:.75rem; white-space:nowrap; background:var(--bg-card); color:var(--cream); border:1px solid var(--border); border-radius:6px; cursor:pointer; }
    .url-copy-row .copy-btn:hover { border-color:var(--gold); color:var(--gold); }

    .stripe-events { background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:.65rem .8rem; margin:.5rem 0; font-family:var(--mono,monospace); font-size:.72rem; line-height:1.85; }

    .stripe-collapsed-form { background:var(--bg-soft); padding:1rem 1.25rem; border-radius:8px; margin-top:1rem; }
    .stripe-collapsed-form summary { cursor:pointer; font-size:.85rem; color:var(--text-muted); padding:.25rem 0; }
    .stripe-collapsed-form summary:hover { color:var(--cream); }
    .stripe-collapsed-form[open] summary { color:var(--cream); margin-bottom:.85rem; }
  </style>

  <h3 style="margin-bottom:.85rem;">💳 Stripe Payment Gateway</h3>

  <!-- 顶部状态(JS 动态填充) -->
  <div class="stripe-status-bar empty" id="stripe-status">
    <span style="font-size:1.4rem;" id="stripe-status-icon">⏳</span>
    <div style="flex:1;">
      <div id="stripe-status-title">Loading status…</div>
      <small id="stripe-status-sub" style="opacity:.75;font-weight:400;font-size:.78rem;"></small>
    </div>
    <button type="button" id="stripe-ping-btn" class="button button-outline" style="padding:.45rem 1rem; font-size:.85rem;">
      🔌 Test connection
    </button>
  </div>

  <!-- 3 步进度条 -->
  <div class="stripe-steps" id="stripe-steps">
    <div class="stripe-step" data-step="1"><span class="step-num">Step 1</span>API keys</div>
    <div class="stripe-step" data-step="2"><span class="step-num">Step 2</span>Webhook</div>
    <div class="stripe-step" data-step="3"><span class="step-num">Step 3</span>Verify</div>
  </div>

  <!-- 当前步骤的提示卡(JS 动态显示哪一个) -->
  <div class="stripe-panel" id="stripe-panel-1" hidden>
    <h4>🔑 Step 1 — Get your API keys from Stripe</h4>
    <p style="margin:0 0 .5rem;font-size:.88rem;">Go to Stripe Dashboard, copy your <strong>Publishable key</strong> (pk_…) and <strong>Secret key</strong> (sk_…), paste them below.</p>
    <a href="https://dashboard.stripe.com/test/apikeys" target="_blank" rel="noopener" class="panel-cta">
      Open Stripe API keys page ↗
    </a>
  </div>

  <div class="stripe-panel" id="stripe-panel-2" hidden>
    <h4>🪝 Step 2 — Create a webhook endpoint</h4>
    <p style="margin:0 0 .5rem;font-size:.88rem;">1. Open the page below. 2. Paste this URL in <em>Endpoint URL</em>. 3. Select the 5 events. 4. Click Add endpoint. 5. Copy the <strong>Signing secret</strong> (whsec_…) and paste in the field below.</p>
    <a href="https://dashboard.stripe.com/test/webhooks/create" target="_blank" rel="noopener" class="panel-cta">
      Open Stripe webhook creation page ↗
    </a>
    <div style="margin-top:.85rem;">
      <small style="font-weight:600;color:var(--cream);">Webhook URL:</small>
      <div class="url-copy-row">
        <code id="webhook-url"><?= htmlspecialchars($webhookUrl) ?></code>
        <button type="button" class="copy-btn" id="copy-webhook-url">📋 Copy</button>
      </div>
    </div>
    <div style="margin-top:.5rem;">
      <small style="font-weight:600;color:var(--cream);">Events to subscribe (all 5):</small>
      <div class="stripe-events" id="stripe-events">checkout.session.completed
checkout.session.async_payment_succeeded
checkout.session.async_payment_failed
checkout.session.expired
charge.refunded</div>
      <button type="button" class="copy-btn" id="copy-events" style="font-size:.7rem;">📋 Copy all events</button>
    </div>
  </div>

  <div class="stripe-panel" id="stripe-panel-3" hidden style="border-left-color:var(--success,#2c9);">
    <h4>✓ Step 3 — Verify everything works</h4>
    <p style="margin:0 0 .65rem;font-size:.88rem;">All keys are saved. Click <strong>Test connection</strong> above to confirm Stripe accepts them. Then run a test order with this card:</p>
    <div class="url-copy-row" style="margin:.3rem 0;">
      <code><strong>4242 4242 4242 4242</strong> · any future date · any CVC · any ZIP</code>
    </div>
    <p style="margin:.5rem 0 0;font-size:.78rem;color:var(--text-muted);">
      Place a test order → check <a href="stripe-logs.php">📜 Webhook Logs</a> for the event → order should turn <strong>paid</strong>.
    </p>
  </div>

  <!-- 字段(在 panel 之外,但显隐受 JS 控制) -->
  <div class="form-group" id="stripe-form" style="margin-top:1.25rem;">
    <div class="form-row">
      <label><span class="label-text">Mode</span>
        <select data-key="stripe_mode" id="stripe-mode-select">
          <option value="test">Test (sandbox)</option>
          <option value="live">Live (real money)</option>
        </select>
      </label>
      <label><span class="label-text">Publishable Key (pk_…)</span>
        <input type="text" data-key="stripe_publishable_key" id="stripe-pk-input" placeholder="pk_test_… or pk_live_…" />
      </label>
    </div>
    <label><span class="label-text">Secret Key (sk_…) <small style="color:var(--warn);">— never shared with frontend</small></span>
      <input type="password" data-key="stripe_secret_key" id="stripe-sk-input" autocomplete="new-password" placeholder="sk_test_… or sk_live_…" />
    </label>
    <label><span class="label-text">Webhook Signing Secret (whsec_…)</span>
      <input type="password" data-key="stripe_webhook_secret" id="stripe-whsec-input" autocomplete="new-password" placeholder="whsec_…" />
    </label>
  </div>

  <script>
    (function () {
      var statusBar    = document.getElementById('stripe-status');
      var statusIcon   = document.getElementById('stripe-status-icon');
      var statusTitle  = document.getElementById('stripe-status-title');
      var statusSub    = document.getElementById('stripe-status-sub');
      var stepEls      = Array.from(document.querySelectorAll('.stripe-step'));
      var panel1       = document.getElementById('stripe-panel-1');
      var panel2       = document.getElementById('stripe-panel-2');
      var panel3       = document.getElementById('stripe-panel-3');
      var pkInput      = document.getElementById('stripe-pk-input');
      var skInput      = document.getElementById('stripe-sk-input');
      var whsecInput   = document.getElementById('stripe-whsec-input');
      var modeSel      = document.getElementById('stripe-mode-select');
      var pingBtn      = document.getElementById('stripe-ping-btn');
      var lastSafeMode = modeSel ? modeSel.value : 'test';
      var pingResult   = null;  // 测试结果显示在状态条上

      // 状态计算
      function calcStep() {
        var hasPk    = !!(pkInput.value.trim() || pkInput.placeholder.indexOf('●') > -1);  // 后端 mask 显示成 placeholder
        var hasSk    = !!(skInput.value.trim() || skInput.dataset.hasValue === '1');
        var hasWhsec = !!(whsecInput.value.trim() || whsecInput.dataset.hasValue === '1');
        if (!hasPk || !hasSk) return 1;
        if (!hasWhsec) return 2;
        return 3;  // 全配齐
      }

      function updateUI(pingState) {
        var step = calcStep();
        // 步骤进度条
        stepEls.forEach(function (el) {
          var n = parseInt(el.dataset.step, 10);
          el.classList.remove('done', 'current');
          if (n < step) el.classList.add('done');
          else if (n === step) el.classList.add('current');
        });
        // 显示当前步骤的 panel,隐藏其他
        panel1.hidden = step !== 1;
        panel2.hidden = step !== 2;
        panel3.hidden = step !== 3;

        // 状态条
        var mode = modeSel ? modeSel.value : 'test';
        if (pingState === 'ok') {
          statusBar.className = 'stripe-status-bar ok';
          statusIcon.textContent = '✓';
          statusTitle.textContent = 'Stripe connected · ' + mode.toUpperCase() + ' mode';
          statusSub.textContent = pingState.message || 'Ready to accept payments';
        } else if (pingState === 'error') {
          statusBar.className = 'stripe-status-bar warn';
          statusIcon.textContent = '⚠';
          statusTitle.textContent = 'Connection failed';
          statusSub.textContent = pingState.message || 'Check keys';
        } else if (step === 3) {
          statusBar.className = 'stripe-status-bar warn';
          statusIcon.textContent = '⚠';
          statusTitle.textContent = 'All keys saved — click Test connection';
          statusSub.textContent = mode === 'live' ? 'LIVE mode · real cards will be charged' : 'Test mode';
        } else {
          statusBar.className = 'stripe-status-bar empty';
          statusIcon.textContent = '○';
          statusTitle.textContent = 'Not configured (Step ' + step + ' of 3)';
          statusSub.textContent = step === 1
            ? 'Get pk_… and sk_… from Stripe Dashboard'
            : 'Add a webhook endpoint and paste the signing secret';
        }
      }

      // 监听输入变化(typed + ping 后)
      [pkInput, skInput, whsecInput, modeSel].forEach(function (el) {
        if (el) el.addEventListener('input', function () { updateUI(); checkPrefixMatch(); });
        if (el) el.addEventListener('change', function () { updateUI(); checkPrefixMatch(); });
      });

      // 实时 prefix 匹配检查:Mode=test 但 key=sk_live_ 或反之 → 警告
      function checkPrefixMatch() {
        var mode = modeSel.value;
        var pk = pkInput.value.trim();
        var sk = skInput.value.trim();
        var msgs = [];
        if (pk) {
          var pkExpected = (mode === 'live') ? 'pk_live_' : 'pk_test_';
          if (pk.indexOf(pkExpected) !== 0) {
            msgs.push('Publishable key should start with <code>' + pkExpected + '</code> for ' + mode.toUpperCase() + ' mode');
          }
        }
        if (sk) {
          var skExpected = (mode === 'live') ? 'sk_live_' : 'sk_test_';
          if (sk.indexOf(skExpected) !== 0) {
            msgs.push('Secret key should start with <code>' + skExpected + '</code> for ' + mode.toUpperCase() + ' mode');
          }
        }
        if (msgs.length > 0) {
          statusBar.className = 'stripe-status-bar warn';
          statusIcon.textContent = '⚠';
          statusTitle.textContent = 'Mode / key prefix mismatch';
          statusSub.innerHTML = msgs.join('<br>');
        }
      }
      // 初始也跑一次(load 后)
      setTimeout(checkPrefixMatch, 900);

      // P1#5: 当后端返回 has_value=true(secret 已配置但不返回明文),
      // 在 input 上加 placeholder ●●●● configured 提示
      function applyPlaceholders() {
        // load() 后端会标记 input[data-has-value]=true,这里读
        [skInput, whsecInput].forEach(function (el) {
          if (el && el.dataset.hasValue === '1' && !el.value) {
            el.placeholder = '●●●●●●●● configured — leave empty to keep';
          }
        });
      }
      // settings load 由外面 JS 加载所有 [data-key],我们在加载后稍延迟调用
      setTimeout(applyPlaceholders, 800);
      setTimeout(updateUI, 850);
      updateUI();

      // ── Webhook URL Copy ──
      function bindCopy(btnId, srcId) {
        var btn = document.getElementById(btnId);
        var src = document.getElementById(srcId);
        if (!btn || !src) return;
        btn.addEventListener('click', function () {
          var text = src.textContent.trim();
          var done = function () {
            var orig = btn.textContent;
            btn.textContent = '✓ Copied';
            setTimeout(function () { btn.textContent = orig.indexOf('events') > -1 ? '📋 Copy all events' : '📋 Copy'; }, 1500);
          };
          if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
              var r = document.createRange(); r.selectNode(src); window.getSelection().removeAllRanges(); window.getSelection().addRange(r);
            });
          } else {
            var r = document.createRange(); r.selectNode(src); window.getSelection().removeAllRanges(); window.getSelection().addRange(r);
          }
        });
      }
      bindCopy('copy-webhook-url', 'webhook-url');
      bindCopy('copy-events', 'stripe-events');

      // ── Mode 切换到 Live 时弹窗警告 ──
      if (modeSel) {
        modeSel.addEventListener('focus', function () { lastSafeMode = modeSel.value; });
        modeSel.addEventListener('change', function () {
          if (modeSel.value === 'live' && lastSafeMode !== 'live') {
            var confirmed = window.confirm(
              '⚠️ Switching to LIVE mode means real credit cards will be charged.\n\nBefore confirming:\n  1. You should have tested the full checkout in TEST mode\n  2. You should have registered a separate LIVE webhook endpoint in Stripe Dashboard\n  3. You should be ready to paste your live sk_live_/pk_live_/whsec_ keys below\n\nContinue?'
            );
            if (!confirmed) { modeSel.value = lastSafeMode; updateUI(); return; }
          }
          lastSafeMode = modeSel.value;
          updateUI();
        });
      }

      // ── Test connection 按钮 ──
      if (pingBtn) {
        pingBtn.addEventListener('click', async function () {
          // 前置检查 — 必须先 Save 才能测试
          //   场景 A: input 是空 + 后端也没 has_value → 完全没配置过
          //   场景 B: input 有值但 has_value 还不是 1 → 填了但还没 Save
          var skTyped     = skInput.value.trim() !== '';
          var skSaved     = skInput.dataset.hasValue === '1';
          var whsecTyped  = whsecInput.value.trim() !== '';
          var whsecSaved  = whsecInput.dataset.hasValue === '1';

          if (!skSaved && !skTyped) {
            statusBar.className = 'stripe-status-bar warn';
            statusIcon.textContent = '⚠';
            statusTitle.textContent = 'No Secret key yet';
            statusSub.innerHTML = 'Paste your <code>sk_test_…</code> in the Secret Key field below first, then click <strong>💾 Save All Settings</strong> at the top of the page.';
            return;
          }
          if (skTyped && !skSaved) {
            statusBar.className = 'stripe-status-bar warn';
            statusIcon.textContent = '⚠';
            statusTitle.textContent = 'Save first';
            statusSub.innerHTML = 'You pasted a Secret key but haven\'t saved it yet. Click <strong>💾 Save All Settings</strong> at the top, then try again.';
            // 主动滚到 Save 按钮 + 闪烁高亮
            var saveBtn = document.getElementById('save-all-btn');
            if (saveBtn) {
              saveBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
              saveBtn.style.boxShadow = '0 0 0 4px rgba(184,146,78,.45)';
              setTimeout(function () { saveBtn.style.boxShadow = ''; }, 2400);
            }
            return;
          }

          pingBtn.disabled = true;
          var origText = pingBtn.innerHTML;
          pingBtn.innerHTML = '⏳';
          statusBar.className = 'stripe-status-bar empty';
          statusIcon.textContent = '⏳';
          statusTitle.textContent = 'Calling Stripe…';
          statusSub.textContent = '';
          try {
            var r = await fetch('../api/admin-stripe-ping.php', { credentials: 'include' });
            var j = await r.json();
            if (j.ok) {
              statusBar.className = 'stripe-status-bar ok';
              statusIcon.textContent = '✓';
              statusTitle.textContent = 'Connected · ' + (j.mode || 'test').toUpperCase() + ' mode';
              statusSub.textContent = j.message;
              if (j.webhook_secret_warning) {
                statusBar.className = 'stripe-status-bar warn';
                statusIcon.textContent = '⚠';
                statusSub.textContent = j.webhook_secret_warning;
              }
            } else {
              statusBar.className = 'stripe-status-bar warn';
              statusIcon.textContent = '✗';
              statusTitle.textContent = 'Connection failed';
              statusSub.textContent = j.message;
            }
          } catch (err) {
            statusBar.className = 'stripe-status-bar warn';
            statusIcon.textContent = '✗';
            statusTitle.textContent = 'Network error';
            statusSub.textContent = err.message;
          } finally {
            pingBtn.disabled = false;
            pingBtn.innerHTML = origText;
          }
        });
      }

      // Step 点击 → 显示对应 panel(即使该步骤已完成,允许回顾)
      stepEls.forEach(function (el) {
        el.addEventListener('click', function () {
          var n = parseInt(el.dataset.step, 10);
          panel1.hidden = n !== 1;
          panel2.hidden = n !== 2;
          panel3.hidden = n !== 3;
        });
      });
    })();
  </script>
</div>

<div class="admin-card">
  <h3>💰 Payment Gateway · PayPal</h3>
  <p class="muted small" style="margin-bottom:1rem;">
    <?= $lang === 'zh' ? '在 developer.paypal.com 创建 App 后填入。' : 'Create an app at developer.paypal.com and paste keys here.' ?>
  </p>
  <div class="form-group">
    <div class="form-row">
      <label><span class="label-text">Mode</span>
        <select data-key="paypal_mode">
          <option value="sandbox">Sandbox (test)</option>
          <option value="live">Live (real money)</option>
        </select>
      </label>
      <label><span class="label-text">Client ID</span>
        <input type="text" data-key="paypal_client_id" placeholder="A…" />
      </label>
    </div>
    <label><span class="label-text">Secret <small style="color:var(--warn);">— never shared with frontend</small></span>
      <input type="password" data-key="paypal_secret" autocomplete="new-password" />
    </label>
  </div>
</div>

</div><!-- /payments -->

<!-- ───── Sign-in ───── -->
<div class="settings-section" data-section="signin">
<div class="admin-card">
  <h3>🔑 OAuth · Google Sign-In</h3>
  <p class="muted small" style="margin-bottom:1rem;">
    <?= $lang === 'zh' ? '在 console.cloud.google.com 创建 OAuth 2.0 Client。回调 URL：' : 'Create OAuth 2.0 Client at console.cloud.google.com. Redirect URL: ' ?>
    <code>https://glameyeshop.com/api/oauth/google.php</code>
  </p>
  <div class="form-group">
    <label><span class="label-text">Client ID</span>
      <input type="text" data-key="google_client_id" placeholder="…apps.googleusercontent.com" />
    </label>
    <label><span class="label-text">Client Secret</span>
      <input type="password" data-key="google_client_secret" autocomplete="new-password" />
    </label>
  </div>
</div>

<div class="admin-card">
  <h3>🎵 OAuth · TikTok Login</h3>
  <p class="muted small" style="margin-bottom:1rem;">
    <?= $lang === 'zh' ? '在 developers.tiktok.com 创建 App。回调 URL：' : 'Create app at developers.tiktok.com. Redirect URL: ' ?>
    <code>https://glameyeshop.com/api/oauth/tiktok.php</code>
  </p>
  <div class="form-group">
    <label><span class="label-text">Client Key</span>
      <input type="text" data-key="tiktok_client_key" />
    </label>
    <label><span class="label-text">Client Secret</span>
      <input type="password" data-key="tiktok_client_secret" autocomplete="new-password" />
    </label>
  </div>
</div>

</div><!-- /signin -->

<!-- ───── Email & Support ───── -->
<div class="settings-section" data-section="email">
<div class="admin-card" style="border-color: var(--gold-dark);">
  <h3>📧 <?= $lang === 'zh' ? '邮件 / 客服通知' : 'Email & Customer Support' ?></h3>
  <p class="muted small" style="margin-bottom:1rem;">
    <?= $lang === 'zh'
      ? '客户在网站浮窗发的留言会发到“管理员邮箱”。你的回复会从“发件人”邮箱发给客户。如果服务器没装 mail()，请配置 SMTP 中转(推荐 Gmail SMTP / SendGrid / Resend)。'
      : 'Chat-widget messages from customers are forwarded to the admin email. Your replies are sent from the "from" email to the customer. If your server lacks mail(), configure an SMTP relay (Gmail SMTP / SendGrid / Resend recommended).' ?>
  </p>
  <div class="form-group">
    <div class="form-row">
      <label><span class="label-text">Admin email <small class="muted">— 接收新留言提醒</small></span>
        <input type="email" data-key="admin_email" placeholder="you@example.com" />
      </label>
      <label><span class="label-text">From name</span>
        <input type="text" data-key="email_from_name" placeholder="GlamEye Support" />
      </label>
    </div>
    <label><span class="label-text">From email <small class="muted">— 自己域名 + DKIM 不会进垃圾箱</small></span>
      <input type="email" data-key="email_from_address" placeholder="support@glameyeshop.com" />
    </label>

    <hr style="border:0; border-top:1px solid var(--border-soft); margin:.5rem 0;">
    <div style="background: var(--bg-soft); padding: 1rem 1.25rem; border-radius: var(--radius); border-left: 3px solid var(--gold); margin: .5rem 0;">
      <p style="margin:0 0 .5rem; font-weight:600; color:var(--gold);">📮 Resend (recommended) — 现代 API,简单</p>
      <p class="muted small" style="margin:0 0 .65rem;">
        <?= $lang === 'zh'
            ? '在 <a href="https://resend.com" target="_blank">resend.com</a> 注册免费账号(每月 3000 封邮件免费),拿到 API key 粘进来。配置后 Mailer 自动优先用 Resend(下方 SMTP 仅作 fallback)。'
            : 'Sign up free at <a href="https://resend.com" target="_blank">resend.com</a> (3,000 emails/mo free). Paste the API key here — Mailer will automatically prefer Resend over SMTP.' ?>
      </p>
      <label><span class="label-text">Resend API key (re_…) <small style="color:var(--warn);">— never shared with frontend</small></span>
        <input type="password" data-key="resend_api_key" autocomplete="new-password" placeholder="re_…" />
      </label>
    </div>

    <p class="muted small">SMTP relay (fallback,如果 Resend 没配):</p>
    <div class="form-row">
      <label><span class="label-text">SMTP host</span>
        <input type="text" data-key="smtp_host" placeholder="smtp.gmail.com / smtp.sendgrid.net" />
      </label>
      <label><span class="label-text">Port</span>
        <input type="number" data-key="smtp_port" placeholder="587" min="1" max="65535" />
      </label>
    </div>
    <div class="form-row">
      <label><span class="label-text">SMTP user</span>
        <input type="text" data-key="smtp_user" autocomplete="off" placeholder="apikey / your@gmail.com" />
      </label>
      <label><span class="label-text">SMTP password</span>
        <input type="password" data-key="smtp_pass" autocomplete="new-password" placeholder="留空表示不修改" />
      </label>
    </div>
    <label><span class="label-text">Encryption</span>
      <select data-key="smtp_secure">
        <option value="tls">STARTTLS (port 587, 推荐)</option>
        <option value="ssl">Implicit TLS (port 465)</option>
        <option value="">None (port 25, 仅内网)</option>
      </select>
    </label>
  </div>
  <div style="margin-top:1rem;">
    <button id="test-email-btn" class="button button-outline button-sm">📤 Send test email to admin</button>
    <span id="test-email-fb" class="muted small" style="margin-left:.75rem;"></span>
  </div>
</div>

</div><!-- /email -->

<!-- ───── Images ───── -->
<div class="settings-section" data-section="images">
<div class="admin-card">
  <h3>🖼 <?= $lang === 'zh' ? '图片优化 / 回填' : 'Image Optimization / Backfill' ?></h3>
  <p class="muted small" style="margin-bottom:1rem;">
    <?= $lang === 'zh'
      ? '把上传的图片(/uploads/)和静态图(/images/)都生成 4 档响应式版本(320/640/1024/1600 × webp+jpg)。新上传的图会自动处理,这个按钮用来一次性补齐旧图。'
      : 'Generate the 4 responsive variants (320/640/1024/1600 × webp+jpg) for every image in /uploads/ and /images/. New uploads are processed automatically — use this to backfill older images.' ?>
  </p>
  <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center;">
    <button id="backfill-dry-btn" class="button button-outline button-sm">🔍 <?= $lang === 'zh' ? '先看看会处理什么(dry run)' : 'Dry run (preview)' ?></button>
    <button id="backfill-run-btn" class="button button-primary button-sm">⚙️ <?= $lang === 'zh' ? '开始处理' : 'Process all images' ?></button>
  </div>
  <pre id="backfill-output" style="background:var(--bg);border:1px solid var(--border-soft);border-radius:4px;padding:1rem;margin-top:1rem;max-height:300px;overflow:auto;font-size:.78rem;color:var(--text-muted);white-space:pre-wrap;display:none;"></pre>
</div>

<style>
  .img-uploader { background: var(--bg); padding: 1rem; border: 1px dashed var(--border); border-radius: 6px; }
  .img-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: .5rem; margin-bottom: .75rem; }
  .img-tiles:empty { display: none; }
  .img-tile { position: relative; aspect-ratio: 16/9; border-radius: 4px; overflow: hidden;
    background: var(--bg-card); border: 2px solid transparent; cursor: grab; transition: border-color .2s; }
  .img-tile:hover { border-color: var(--gold); }
  .img-tile.dragging { opacity: .4; }
  .img-tile.drag-over { border-color: var(--gold); border-style: dashed; }
  .img-tile.is-main { border-color: var(--gold); }
  .img-tile img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
  .img-tile .badge { position: absolute; top:4px; left:4px; background: var(--gold); color: var(--bg);
    font-size: 9px; font-weight:700; padding: 2px 6px; border-radius: 2px; letter-spacing: 1px; }
  .img-tile .order-num { position: absolute; bottom: 4px; left: 4px; background: rgba(0,0,0,0.7);
    color: var(--cream); font-size: 11px; font-weight: 600; padding: 1px 5px; border-radius: 2px; }
  .img-tile .del-x { position: absolute; top: 4px; right: 4px; width: 20px; height: 20px;
    background: rgba(0,0,0,0.7); color: var(--cream); border: none; border-radius: 50%;
    cursor: pointer; display:flex; align-items:center; justify-content:center; font-size: 14px; line-height: 1; }
  .img-tile .del-x:hover { background: var(--error); }
  .img-add-btn { display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:.25rem; padding: 1rem; background: var(--bg-card); border: 1px dashed var(--border);
    border-radius: 4px; cursor: pointer; color: var(--text-muted); font-size:.82rem; text-align:center;
    transition: all .2s; }
  .img-add-btn:hover { border-color: var(--gold); color: var(--gold); }
</style>

</div><!-- /images -->

<div class="settings-save-bar">
  <p class="form-feedback" id="settings-feedback" style="margin: 0; flex: 1;"></p>
  <button id="save-all-btn" class="button button-primary">💾 Save All Settings</button>
</div>

<script>
(async () => {
  const fb = document.getElementById('settings-feedback');
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // ---------- Tab switching ----------
  const tabs = document.querySelectorAll('.settings-tab');
  const sections = document.querySelectorAll('.settings-section');
  function activate(name) {
    let found = false;
    tabs.forEach(t => {
      const m = t.dataset.tab === name;
      t.classList.toggle('active', m);
      if (m) found = true;
    });
    sections.forEach(s => s.classList.toggle('active', s.dataset.section === name));
    if (!found && tabs.length) {
      // fallback to first
      tabs[0].classList.add('active');
      sections[0]?.classList.add('active');
      return tabs[0].dataset.tab;
    }
    return name;
  }
  // 默认 tab 来自 URL hash,否则 branding
  const initial = (location.hash || '#branding').replace(/^#/, '');
  activate(initial);
  tabs.forEach(t => t.addEventListener('click', (e) => {
    e.preventDefault();
    history.replaceState(null, '', '#' + t.dataset.tab);
    activate(t.dataset.tab);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }));
  window.addEventListener('hashchange', () => activate((location.hash || '#branding').replace(/^#/, '')));

  // ---------- Hero slideshow ----------
  let heroImages = [];
  const heroTilesEl   = document.getElementById('hero-tiles');
  const heroJsonField = document.getElementById('hero-images-json');
  const heroUrlField  = document.getElementById('hero-url-input');
  function syncHero() {
    heroJsonField.value = JSON.stringify(heroImages);
    heroUrlField.value  = heroImages[0] || '';
  }
  function renderHeroTiles() {
    heroTilesEl.innerHTML = heroImages.map((url, i) => `
      <div class="img-tile ${i===0?'is-main':''}" draggable="true" data-idx="${i}">
        <img src="${escape(url.startsWith('http') ? url : '..'+url)}" alt="" />
        ${i===0 ? '<span class="badge">FIRST</span>' : `<span class="order-num">${i+1}</span>`}
        <button type="button" class="del-x" data-idx="${i}" title="Remove">×</button>
      </div>`).join('');
    syncHero();
  }
  heroTilesEl.addEventListener('click', (e) => {
    const x = e.target.closest('.del-x'); if (!x) return;
    e.preventDefault();
    heroImages.splice(parseInt(x.dataset.idx, 10), 1);
    renderHeroTiles();
  });
  let heroDragSrc = null;
  heroTilesEl.addEventListener('dragstart', (e) => {
    const t = e.target.closest('.img-tile'); if (!t) return;
    heroDragSrc = parseInt(t.dataset.idx, 10);
    t.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move';
  });
  heroTilesEl.addEventListener('dragend', (e) => {
    const t = e.target.closest('.img-tile'); if (t) t.classList.remove('dragging');
    heroTilesEl.querySelectorAll('.img-tile').forEach(x => x.classList.remove('drag-over'));
  });
  heroTilesEl.addEventListener('dragover', (e) => {
    e.preventDefault();
    const t = e.target.closest('.img-tile'); if (!t) return;
    heroTilesEl.querySelectorAll('.img-tile').forEach(x => x.classList.remove('drag-over'));
    t.classList.add('drag-over');
  });
  heroTilesEl.addEventListener('drop', (e) => {
    e.preventDefault();
    const t = e.target.closest('.img-tile'); if (!t || heroDragSrc === null) return;
    const dst = parseInt(t.dataset.idx, 10);
    if (dst === heroDragSrc) return;
    const m = heroImages.splice(heroDragSrc, 1)[0];
    heroImages.splice(dst, 0, m);
    heroDragSrc = null; renderHeroTiles();
  });
  document.getElementById('hero-upload').addEventListener('change', async (e) => {
    const files = Array.from(e.target.files || []); if (!files.length) return;
    const status = document.getElementById('hero-status');
    let done = 0;
    for (const f of files) {
      status.textContent = `Uploading ${++done}/${files.length}…`; status.style.color = '';
      const fd = new FormData(); fd.append('file', f);
      try {
        const r = await fetch('../api/admin-upload.php', { method:'POST', credentials:'include', body: fd });
        const j = await r.json();
        if (j.success) { heroImages.push(j.url); renderHeroTiles(); }
        else { status.textContent = '✗ ' + (j.error || 'failed'); status.style.color = 'var(--error)'; }
      } catch (err) { status.textContent = '✗ ' + err.message; status.style.color = 'var(--error)'; }
    }
    status.textContent = `✓ ${heroImages.length} hero image(s). Drag to reorder · Click "Save All" to apply.`;
    status.style.color = 'var(--gold)';
    e.target.value = '';
  });

  // ---------- Load ----------
  try {
    const r = await fetch('../api/admin-settings.php', { credentials: 'include' });
    const j = await r.json();
    const map = {};
    const hasValueMap = {};  // P1#23: secret 字段后端只回 has_value 不回明文
    (j.settings || []).forEach(s => {
      map[s.key] = s.value;
      if (s.has_value) hasValueMap[s.key] = true;
    });
    document.querySelectorAll('[data-key]').forEach(el => {
      const v = map[el.dataset.key] || '';
      // 把 has_value 标记暴露到 dataset 给 Stripe wizard 等 UI 读
      if (hasValueMap[el.dataset.key]) el.dataset.hasValue = '1';
      if (el.dataset.bool) {
        el.checked = (v === '1');
      } else if (el.id === 'hero-images-json') {
        try { const arr = JSON.parse(v || '[]'); if (Array.isArray(arr)) heroImages = arr.filter(Boolean); } catch {}
        // fallback to legacy single hero_image_url if no array
        if (!heroImages.length && map['hero_image_url']) heroImages = [map['hero_image_url']];
        renderHeroTiles();
      } else if (el.id === 'hero-url-input') {
        // skip — managed by syncHero()
      } else {
        el.value = v;
      }
    });
    // defaults
    if (!document.querySelector('[data-key="hero_slide_interval"]').value) {
      document.querySelector('[data-key="hero_slide_interval"]').value = '5000';
    }
  } catch (e) { fb.textContent = 'Load failed'; fb.className = 'form-feedback error'; }

  // ---------- Backfill images ----------
  const backOut = document.getElementById('backfill-output');
  function appendOut(line) {
    backOut.style.display = 'block';
    backOut.textContent += line + '\n';
    backOut.scrollTop = backOut.scrollHeight;
  }
  async function runBackfill(dry) {
    backOut.textContent = ''; backOut.style.display = 'block';
    appendOut(dry ? '🔍 Dry run starting…' : '⚙️ Processing images (50 per batch, may take a few minutes)…');
    let offset = 0, total = null, processed = 0, skipped = 0, errors = 0;
    while (true) {
      try {
        const url = '../api/admin-backfill-images.php?limit=50&offset=' + offset + (dry ? '&dry=1' : '');
        const r = await fetch(url, { credentials: 'include' });
        const j = await r.json();
        if (total === null) {
          total = j.total_local_images || 0;
          appendOut('Total local images: ' + total);
        }
        processed += (j.processed || 0);
        skipped   += (j.skipped || 0);
        errors    += (j.errors || []).length;
        appendOut(`  batch @${offset}: processed ${j.processed}, skipped ${j.skipped}, errors ${(j.errors||[]).length}`);
        if (j.errors && j.errors.length) j.errors.slice(0, 5).forEach(e => appendOut('   ! ' + e));
        if (j.next_offset === null || j.next_offset === undefined) break;
        offset = j.next_offset;
      } catch (e) { appendOut('  FAIL: ' + e.message); break; }
    }
    appendOut(`\n✓ Done. Processed: ${processed}, skipped: ${skipped}, errors: ${errors}.`);
    if (!dry && processed > 0) appendOut('Tip: refresh your browser cache (Ctrl+Shift+R) to see the new variants.');
  }
  const dryBtn = document.getElementById('backfill-dry-btn');
  const runBtn = document.getElementById('backfill-run-btn');
  if (dryBtn) dryBtn.addEventListener('click', () => { dryBtn.disabled = runBtn.disabled = true; runBackfill(true).finally(() => { dryBtn.disabled = runBtn.disabled = false; }); });
  if (runBtn) runBtn.addEventListener('click', () => { dryBtn.disabled = runBtn.disabled = true; runBackfill(false).finally(() => { dryBtn.disabled = runBtn.disabled = false; }); });

  // ---------- Test email ----------
  const testBtn = document.getElementById('test-email-btn');
  if (testBtn) {
    testBtn.addEventListener('click', async () => {
      const tfb = document.getElementById('test-email-fb');
      tfb.textContent = 'Sending…'; tfb.style.color = 'var(--text-muted)';
      try {
        const r = await fetch('../api/admin-test-email.php', { credentials: 'include' });
        const j = await r.json();
        if (j.ok) { tfb.textContent = `✓ Sent to ${j.sent_to} via ${j.mode}`; tfb.style.color = 'var(--success)'; }
        else      { tfb.textContent = `✗ ${j.error || 'Failed'}`; tfb.style.color = 'var(--error)'; }
      } catch (e) { tfb.textContent = '✗ ' + e.message; tfb.style.color = 'var(--error)'; }
    });
  }

  document.getElementById('save-all-btn').addEventListener('click', async () => {
    fb.textContent = 'Saving…'; fb.className = 'form-feedback';
    syncHero();
    let ok = 0, fail = 0;
    for (const el of document.querySelectorAll('[data-key]')) {
      // Don't overwrite existing secret fields (passwords) with empty values
      if (el.type === 'password' && el.value === '') continue;
      const value = el.dataset.bool ? (el.checked ? '1' : '0') : el.value;
      try {
        const r = await fetch('../api/admin-settings.php', {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ key: el.dataset.key, value }),
        });
        const j = await r.json();
        if (j.success) ok++; else fail++;
      } catch (e) { fail++; }
    }
    if (fail === 0) { fb.textContent = `✓ Saved ${ok} settings`; fb.className = 'form-feedback success'; }
    else            { fb.textContent = `Saved ${ok}, failed ${fail}`; fb.className = 'form-feedback error'; }
  });
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
