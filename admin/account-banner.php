<?php $pageTitle = 'Account Banner'; $activeNav = 'account-banner'; require __DIR__ . '/_layout.php'; ?>
<style>
  .ab-row { display: grid; grid-template-columns: 200px 1fr auto; gap: 1rem; padding: 1rem; background: var(--bg-soft); border-radius: 8px; margin-bottom: 1rem; align-items: start; }
  .ab-thumb { aspect-ratio: 16/9; background: var(--bg); border-radius: 6px; overflow: hidden; cursor: pointer; position: relative; }
  .ab-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .ab-thumb .ph { display:flex; align-items:center; justify-content:center; height:100%; color:var(--text-muted); font-size:.8rem; }
  .ab-controls { display: grid; gap: .5rem; }
  .ab-controls label { font-size:.7rem; color:var(--text-muted); letter-spacing:1px; text-transform:uppercase; }
  .ab-controls input, .ab-controls textarea { width:100%; background:var(--bg); border:1px solid var(--border-soft); color:var(--text); padding:.4rem .6rem; border-radius:4px; font-size:.85rem; }
</style>

<h1 style="margin-top:0">🖼 <?= $lang === "zh" ? "账户后台广告位" : "Account Page Banner" ?></h1>
<p class="muted"><?= $lang === "zh" ? "登录后买家在 /account.html 看到的图形广告。最多展示 1 个(active 中 sort 最小的)。" : "Promotional banner shown to signed-in customers on /account.html. Only the first active (lowest sort_order) is displayed." ?></p>

<div style="display:flex;justify-content:space-between;align-items:center;margin:1rem 0;">
  <h2 style="margin:0;font-size:1.2rem"><?= $lang === "zh" ? "广告列表" : "Banners" ?></h2>
  <button id="new-banner-btn" class="button button-primary">+ <?= $lang === "zh" ? "添加广告" : "Add banner" ?></button>
</div>

<div id="banners-list">
  <p class="muted"><?= $lang === "zh" ? "加载中…" : "Loading…" ?></p>
</div>

<input type="file" id="hidden-upload" accept="image/*" hidden />

<script>
(async () => {
  const list = document.getElementById('banners-list');
  const hiddenUpload = document.getElementById('hidden-upload');
  let banners = [];
  let uploadTarget = null;

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const imgSrc = (url) => url ? (url.startsWith('http') ? url : '..' + url) : '';

  async function load() {
    list.innerHTML = '<p class="muted">Loading…</p>';
    let r, txt = '', j = null;
    try {
      r = await fetch('../api/admin-account-banner.php', { credentials: 'include' });
      txt = await r.text();
      j = JSON.parse(txt);
    } catch (e) {
      list.innerHTML = `<p style="color:var(--error)">Network: ${esc(e.message)}</p>`;
      return;
    }
    if (!r.ok || j.error) {
      list.innerHTML = `<p style="color:var(--error)">${esc(j?.error || 'HTTP ' + r.status)}</p>`;
      return;
    }
    banners = j.banners || [];
    render();
  }

  function row(b, i) {
    return `
      <div class="ab-row" data-idx="${i}">
        <div class="ab-thumb" data-idx="${i}" title="<?= $lang === 'zh' ? '点击上传图片' : 'Click to upload image' ?>">
          ${b.image_url
            ? `<img src="${esc(imgSrc(b.image_url))}" onerror="this.outerHTML='<div class=ph>404: ${esc(b.image_url)}</div>'" />`
            : '<div class="ph">+ Upload</div>'}
        </div>
        <div class="ab-controls">
          <label><?= $lang === "zh" ? "标题" : "Title" ?>
            <input type="text" data-field="title" data-idx="${i}" value="${esc(b.title || '')}" placeholder="<?= $lang === 'zh' ? '例:会员独享 9 折' : 'e.g. Members get 10% off' ?>" />
          </label>
          <label><?= $lang === "zh" ? "副标题/描述" : "Subtitle" ?>
            <input type="text" data-field="subtitle" data-idx="${i}" value="${esc(b.subtitle || '')}" placeholder="<?= $lang === 'zh' ? '例:首单 $50+ 免邮' : 'e.g. Free shipping over $50' ?>" />
          </label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;">
            <label>CTA <?= $lang === "zh" ? "按钮文案" : "button text" ?>
              <input type="text" data-field="cta_text" data-idx="${i}" value="${esc(b.cta_text || '')}" placeholder="<?= $lang === 'zh' ? '例:立即选购 →' : 'e.g. Shop now →' ?>" />
            </label>
            <label>CTA <?= $lang === "zh" ? "跳转 URL" : "URL" ?>
              <input type="text" data-field="cta_url" data-idx="${i}" value="${esc(b.cta_url || '')}" placeholder="/shop.html" />
            </label>
          </div>
          <div style="display:grid;grid-template-columns:120px auto;gap:.5rem;align-items:center;">
            <label><?= $lang === "zh" ? "排序" : "Sort" ?>
              <input type="number" data-field="sort_order" data-idx="${i}" value="${b.sort_order ?? 0}" />
            </label>
            <label style="display:flex;align-items:center;gap:.5rem;margin-top:1rem;cursor:pointer;font-size:.85rem;text-transform:none;letter-spacing:0;color:var(--text);">
              <input type="checkbox" data-field="is_active" data-idx="${i}" ${b.is_active == 1 ? 'checked' : ''} />
              <span><?= $lang === "zh" ? "上架(显示在 account 页)" : "Active (visible on account page)" ?></span>
            </label>
          </div>
        </div>
        <div style="display:grid;gap:.4rem">
          <button class="button button-primary save-btn" data-idx="${i}" style="font-size:.8rem"><?= $lang === "zh" ? "保存" : "Save" ?></button>
          <button class="button button-outline del-btn" data-idx="${i}" style="font-size:.8rem;color:var(--error);border-color:var(--error)"><?= $lang === "zh" ? "删除" : "Delete" ?></button>
        </div>
      </div>
    `;
  }

  function render() {
    if (!banners.length) {
      list.innerHTML = '<p class="muted"><?= $lang === "zh" ? "暂无广告。点击上方 \"+ 添加广告\" 创建一个。" : "No banners yet. Click \"+ Add banner\" above." ?></p>';
      return;
    }
    list.innerHTML = banners.map(row).join('');
  }

  list.addEventListener('click', (e) => {
    const t = e.target.closest('.ab-thumb');
    if (t) {
      uploadTarget = parseInt(t.dataset.idx, 10);
      hiddenUpload.value = '';
      hiddenUpload.click();
    }
  });
  hiddenUpload.addEventListener('change', async (e) => {
    const f = e.target.files[0];
    if (!f || uploadTarget == null) return;
    const fd = new FormData(); fd.append('file', f);
    const r = await fetch('../api/admin-upload.php', { method: 'POST', credentials: 'include', body: fd });
    const txt = await r.text();
    let j = null;
    try { j = JSON.parse(txt); } catch (_) {}
    if (!r.ok || !j || j.error) { alert('Upload failed: ' + (j?.error || 'HTTP ' + r.status)); return; }
    banners[uploadTarget].image_url = j.url;
    render();
  });

  list.addEventListener('input', (e) => {
    const inp = e.target.closest('input[data-field]');
    if (!inp) return;
    const i = parseInt(inp.dataset.idx, 10);
    const f = inp.dataset.field;
    if (inp.type === 'checkbox') banners[i][f] = inp.checked ? 1 : 0;
    else if (inp.type === 'number') banners[i][f] = parseInt(inp.value, 10) || 0;
    else banners[i][f] = inp.value;
  });

  list.addEventListener('click', async (e) => {
    const sb = e.target.closest('.save-btn');
    if (sb) {
      const i = parseInt(sb.dataset.idx, 10);
      const b = banners[i];
      if (!b.image_url) { alert('Upload an image first'); return; }
      sb.textContent = 'Saving…';
      const u = b.id > 0 ? `../api/admin-account-banner.php?id=${b.id}` : '../api/admin-account-banner.php';
      const r = await fetch(u, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(b),
      });
      const j = await r.json();
      if (j.error) { alert('Save failed: ' + j.error); sb.textContent = 'Save'; return; }
      if (j.id && !b.id) b.id = j.id;
      sb.textContent = '✓ Saved';
      setTimeout(() => sb.textContent = 'Save', 1500);
    }
    const db = e.target.closest('.del-btn');
    if (db) {
      if (!confirm('Delete this banner?')) return;
      const i = parseInt(db.dataset.idx, 10);
      const b = banners[i];
      if (b.id > 0) {
        const r = await fetch(`../api/admin-account-banner.php?id=${b.id}`, { method: 'DELETE', credentials: 'include' });
        const j = await r.json();
        if (j.error) { alert(j.error); return; }
      }
      banners.splice(i, 1);
      render();
    }
  });

  document.getElementById('new-banner-btn').addEventListener('click', () => {
    banners.push({
      id: 0, image_url: '', title: '', subtitle: '',
      cta_text: 'Shop now →', cta_url: '/shop.html',
      sort_order: banners.length + 1, is_active: 1,
    });
    render();
  });

  load();
})();
</script>
