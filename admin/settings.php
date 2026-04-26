<?php $pageTitle = 'Site Settings'; $activeNav = 'settings'; require __DIR__ . '/_layout.php'; ?>
<h1>⚙️ <?= $lang === 'zh' ? '站点设置' : 'Site Settings' ?></h1>
<p class="muted" style="margin-bottom:2rem;">
  <?= $lang === 'zh' ? '配置社交链接、Hero 图片、Amazon 店铺等。改完会立即生效。' : 'Configure social links, hero image, Amazon store etc. Changes apply immediately.' ?>
</p>

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

<div class="admin-card">
  <h3>🎞️ Homepage Hero Slideshow</h3>
  <p class="muted small" style="margin-bottom:1rem;">
    <?= $lang === 'zh' ? '上传多张图（拖拽排序，第一张优先展示），首页将自动轮播。' : 'Upload multiple images (drag to reorder · first one shows first). They auto-rotate on the homepage.' ?>
  </p>
  <div class="img-uploader">
    <div class="img-tiles" id="hero-tiles"></div>
    <label class="img-add-btn">
      <span style="font-size:1.5rem;">＋</span>
      <span>📤 <?= $lang === 'zh' ? '点击上传多张图' : 'Click to upload (multiple)' ?></span>
      <input type="file" accept="image/*" multiple hidden id="hero-upload" />
    </label>
    <small id="hero-status" class="muted" style="display:block; margin-top:.5rem;">Tip: 1920×1080+ landscape beauty photos work best.</small>
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

<div class="admin-card">
  <h3>💳 Payment Gateway · Stripe</h3>
  <p class="muted small" style="margin-bottom:1rem;">
    <?= $lang === 'zh' ? '在 dashboard.stripe.com 获取你的密钥。Webhook URL：' : 'Get your keys from dashboard.stripe.com. Webhook URL: ' ?>
    <code>https://glameyeshop.com/api/stripe-webhook.php</code>
  </p>
  <div class="form-group">
    <div class="form-row">
      <label><span class="label-text">Mode</span>
        <select data-key="stripe_mode">
          <option value="test">Test (sandbox)</option>
          <option value="live">Live (real money)</option>
        </select>
      </label>
      <label><span class="label-text">Publishable Key (pk_…)</span>
        <input type="text" data-key="stripe_publishable_key" placeholder="pk_test_… or pk_live_…" />
      </label>
    </div>
    <label><span class="label-text">Secret Key (sk_…) <small style="color:var(--warn);">— never shared with frontend</small></span>
      <input type="password" data-key="stripe_secret_key" autocomplete="new-password" placeholder="sk_test_… or sk_live_…" />
    </label>
    <label><span class="label-text">Webhook Signing Secret (whsec_…)</span>
      <input type="password" data-key="stripe_webhook_secret" autocomplete="new-password" placeholder="whsec_…" />
    </label>
  </div>
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
    <p class="muted small">SMTP relay (留空则用本机 mail()):</p>
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

<div style="display:flex; justify-content:flex-end; gap:.75rem;">
  <button id="save-all-btn" class="button button-primary">💾 Save All Settings</button>
</div>
<p class="form-feedback" id="settings-feedback" style="margin-top:1rem;"></p>

<script>
(async () => {
  const fb = document.getElementById('settings-feedback');
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

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
    (j.settings || []).forEach(s => map[s.key] = s.value);
    document.querySelectorAll('[data-key]').forEach(el => {
      const v = map[el.dataset.key] || '';
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
