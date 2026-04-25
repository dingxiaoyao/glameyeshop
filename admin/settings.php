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

<div class="admin-card">
  <h3>Hero Image</h3>
  <div class="form-group">
    <label>
      <span class="label-text">Homepage Hero Background URL</span>
      <input type="url" data-key="hero_image_url" placeholder="https://images.unsplash.com/..." />
      <small class="muted" style="display:block; margin-top:.35rem;">Tip: Use a 1920x1080+ portrait beauty photo. Unsplash hot-links work great.</small>
    </label>
  </div>
</div>

<div style="display:flex; justify-content:flex-end; gap:.75rem;">
  <button id="save-all-btn" class="button button-primary">💾 Save All Settings</button>
</div>
<p class="form-feedback" id="settings-feedback" style="margin-top:1rem;"></p>

<script>
(async () => {
  const fb = document.getElementById('settings-feedback');
  // Load
  try {
    const r = await fetch('../api/admin-settings.php', { credentials: 'include' });
    const j = await r.json();
    const map = {};
    (j.settings || []).forEach(s => map[s.key] = s.value);
    document.querySelectorAll('[data-key]').forEach(el => {
      el.value = map[el.dataset.key] || '';
    });
  } catch (e) { fb.textContent = 'Load failed'; fb.className = 'form-feedback error'; }

  document.getElementById('save-all-btn').addEventListener('click', async () => {
    fb.textContent = 'Saving…'; fb.className = 'form-feedback';
    let ok = 0, fail = 0;
    for (const el of document.querySelectorAll('[data-key]')) {
      try {
        const r = await fetch('../api/admin-settings.php', {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ key: el.dataset.key, value: el.value }),
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
