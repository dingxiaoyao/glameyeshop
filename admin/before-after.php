<?php $pageTitle = 'Before & After'; $activeNav = 'before-after'; require __DIR__ . '/_layout.php'; ?>
<style>
  .ba-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: center; padding: 1rem; background: var(--bg-soft); border-radius: 8px; margin-bottom: 1rem; }
  .ba-thumb { aspect-ratio: 1; background: var(--bg); border-radius: 6px; overflow: hidden; position: relative; }
  .ba-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .ba-thumb-label { position: absolute; bottom: .35rem; left: .35rem; right: .35rem; padding: .2rem .4rem; background: rgba(0,0,0,.6); color: #fff; font-size: .65rem; text-align: center; border-radius: 3px; }
  .ba-upload-area { aspect-ratio: 1; border: 2px dashed var(--border); border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: border-color .2s; }
  .ba-upload-area:hover { border-color: var(--gold); }
  .ba-controls { display: grid; gap: .5rem; }
  .ba-controls input, .ba-controls textarea { width: 100%; background: var(--bg); border: 1px solid var(--border-soft); color: var(--text); padding: .4rem .6rem; border-radius: 4px; font-size: .85rem; }
  .ba-controls label { font-size: .7rem; color: var(--text-muted); letter-spacing: 1px; text-transform: uppercase; }
</style>

<h1 style="margin-top:0">📸 <?= $lang === "zh" ? "对比图" : "Before & After" ?></h1>
<p class="muted"><?= $lang === "zh" ? "首页 “Real Results” 区块的对照图。暂不支持拖拽排序,改 sort_order 数字即可。" : "Homepage &quot;Real Results&quot; comparison pairs. Drag-and-drop won&apos;t work yet — use sort_order numbers." ?></p>

<div class="admin-card" style="margin-bottom:2rem;">
  <h3 style="margin-top:0;font-size:1rem"><?= $lang === "zh" ? "区块文案" : "Section copy" ?></h3>
  <div style="display:grid;gap:.75rem">
    <label><?= $lang === "zh" ? "副标题(显示在 Before & After 标题下)" : "Subtitle (shown under &quot;Before & After&quot; title)" ?>
      <input type="text" id="copy-subtitle" style="width:100%;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);padding:.5rem;border-radius:4px;" />
    </label>
    <label><?= $lang === "zh" ? "底部说明(显示在对照下方)" : "Footer disclaimer (shown below the pairs)" ?>
      <input type="text" id="copy-footer" style="width:100%;background:var(--bg);border:1px solid var(--border-soft);color:var(--text);padding:.5rem;border-radius:4px;" />
    </label>
    <button id="save-copy" class="button button-primary" style="justify-self:start"><?= $lang === "zh" ? "保存文案" : "Save copy" ?></button>
    <span id="copy-fb" class="form-feedback"></span>
  </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
  <h2 style="margin:0;font-size:1.2rem"><?= $lang === "zh" ? "对照列表" : "Pairs" ?></h2>
  <button id="new-pair-btn" class="button button-primary">+ <?= $lang === "zh" ? "添加对照" : "Add pair" ?></button>
</div>

<div id="pairs-list">
  <p class="muted">Loading…</p>
</div>

<input type="file" id="hidden-upload" accept="image/*" hidden />

<script>
(async () => {
  const fb = document.getElementById('copy-fb');
  const list = document.getElementById('pairs-list');
  const hiddenUpload = document.getElementById('hidden-upload');
  let pairs = [];
  let uploadTarget = null;  // { pairIdx, side: 'before'|'after' }

  function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function imgSrc(url) { return url ? (url.startsWith('http') ? url : '..' + url) : ''; }

  async function load() {
    list.innerHTML = '<p class="muted">Loading…</p>';
    let r, txt = '', j = null;
    try {
      r = await fetch('../api/admin-before-after.php', { credentials: 'include' });
      txt = await r.text();
      j = JSON.parse(txt);
    } catch (e) {
      list.innerHTML = `<p style="color:var(--error)">Load failed: HTTP ${r?.status}<br><pre>${esc(txt.slice(0,400))}</pre></p>`;
      return;
    }
    if (!r.ok || j.error) {
      list.innerHTML = `<p style="color:var(--error)">${esc(j.error || 'HTTP ' + r.status)}</p>`;
      return;
    }
    document.getElementById('copy-subtitle').value = j.subtitle || '';
    document.getElementById('copy-footer').value   = j.footer   || '';
    pairs = j.pairs || [];
    render();
  }

  function pairRow(p, idx) {
    return `
      <div class="ba-row" data-idx="${idx}">
        <div class="ba-thumb" data-side="before" data-idx="${idx}" style="cursor:pointer" title="<?= $lang === "zh" ? "点击上传新图" : "Click to upload new image" ?>">
          ${p.before_image_url ? `<img src="${esc(imgSrc(p.before_image_url))}" onerror="this.outerHTML='<div style=padding:1rem;text-align:center;font-size:.7rem;color:var(--error)>404</div>'" />` : '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);font-size:.8rem">+ Upload</div>'}
          <div class="ba-thumb-label">${esc(p.before_label || 'Before')}</div>
        </div>
        <div class="ba-thumb" data-side="after" data-idx="${idx}" style="cursor:pointer" title="<?= $lang === 'zh' ? '点击上传新图' : 'Click to upload new image' ?>">
          ${p.after_image_url ? `<img src="${esc(imgSrc(p.after_image_url))}" onerror="this.outerHTML='<div style=padding:1rem;text-align:center;font-size:.7rem;color:var(--error)>404</div>'" />` : '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);font-size:.8rem">+ Upload</div>'}
          <div class="ba-thumb-label">${esc(p.after_label || 'After')}</div>
        </div>
        <div class="ba-controls">
          <label><?= $lang === "zh" ? "Before 标签" : "Before label" ?>
            <input type="text" data-field="before_label" data-idx="${idx}" value="${esc(p.before_label || '')}" placeholder="<?= $lang === 'zh' ? '例:Before' : 'e.g. Before' ?>" />
          </label>
          <label><?= $lang === "zh" ? "After 标签" : "After label" ?>
            <input type="text" data-field="after_label" data-idx="${idx}" value="${esc(p.after_label || '')}" placeholder="<?= $lang === 'zh' ? '例:After · 真客户名 18mm' : 'e.g. After · Naked 18mm' ?>" />
          </label>
          <label><?= $lang === "zh" ? "排序" : "Sort order" ?>
            <input type="number" data-field="sort_order" data-idx="${idx}" value="${(p.sort_order ?? 0)}" style="max-width:80px" />
          </label>
          <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
            <input type="checkbox" data-field="is_active" data-idx="${idx}" ${p.is_active == 1 ? 'checked' : ''} />
            <span><?= $lang === "zh" ? "上架(显示在首页)" : "Active (show on homepage)" ?></span>
          </label>
        </div>
        <div style="display:grid;gap:.4rem">
          <button class="button button-primary save-btn" data-idx="${idx}" style="font-size:.8rem"><?= $lang === "zh" ? "保存" : "Save" ?></button>
          <button class="button button-outline del-btn" data-idx="${idx}" style="font-size:.8rem;color:var(--error);border-color:var(--error)"><?= $lang === "zh" ? "删除" : "Delete" ?></button>
        </div>
      </div>
    `;
  }

  function render() {
    if (!pairs.length) {
      list.innerHTML = '<p class="muted">No pairs yet. Click "+ Add pair" above.</p>';
      return;
    }
    list.innerHTML = pairs.map(pairRow).join('');
  }

  // 上传图(点击缩略图触发)
  list.addEventListener('click', (e) => {
    const thumb = e.target.closest('.ba-thumb');
    if (thumb) {
      uploadTarget = { idx: parseInt(thumb.dataset.idx, 10), side: thumb.dataset.side };
      hiddenUpload.value = '';
      hiddenUpload.click();
    }
  });
  hiddenUpload.addEventListener('change', async (e) => {
    const f = e.target.files[0];
    if (!f || !uploadTarget) return;
    const fd = new FormData(); fd.append('file', f);
    let r, txt = '', j = null;
    try {
      r = await fetch('../api/admin-upload.php', { method:'POST', credentials:'include', body: fd });
      txt = await r.text();
      j = JSON.parse(txt);
    } catch (err) {
      alert('Upload network error: ' + (err.message || err));
      return;
    }
    if (!r.ok || !j) { alert('Upload failed — HTTP ' + r.status + '\n\n' + txt.slice(0, 300)); return; }
    if (j.error) { alert('Upload error: ' + j.error); return; }
    if (j.success) {
      const { idx, side } = uploadTarget;
      pairs[idx][side + '_image_url'] = j.url;
      render();
    }
  });

  // 字段编辑
  list.addEventListener('input', (e) => {
    const inp = e.target.closest('input[data-field]');
    if (!inp) return;
    const idx = parseInt(inp.dataset.idx, 10);
    const field = inp.dataset.field;
    if (inp.type === 'checkbox') pairs[idx][field] = inp.checked ? 1 : 0;
    else if (inp.type === 'number') pairs[idx][field] = parseInt(inp.value, 10) || 0;
    else pairs[idx][field] = inp.value;
  });

  // 保存某行
  list.addEventListener('click', async (e) => {
    const save = e.target.closest('.save-btn');
    if (save) {
      const idx = parseInt(save.dataset.idx, 10);
      const p = pairs[idx];
      save.textContent = 'Saving…';
      let r, txt = '', j = null;
      try {
        const u = p.id > 0 ? `../api/admin-before-after.php?id=${p.id}` : '../api/admin-before-after.php';
        r = await fetch(u, {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(p),
        });
        txt = await r.text();
        j = JSON.parse(txt);
      } catch (err) {
        alert('Save network error: ' + (err.message || err));
        save.textContent = 'Save';
        return;
      }
      if (!r.ok || j.error) {
        alert('Save failed: ' + (j?.error || 'HTTP ' + r.status));
        save.textContent = 'Save';
        return;
      }
      save.textContent = '✓ Saved';
      if (j.id && !p.id) p.id = j.id;
      setTimeout(() => save.textContent = 'Save', 1500);
    }
    const del = e.target.closest('.del-btn');
    if (del) {
      if (!confirm('Delete this pair? Cannot undo.')) return;
      const idx = parseInt(del.dataset.idx, 10);
      const p = pairs[idx];
      if (p.id > 0) {
        const r = await fetch(`../api/admin-before-after.php?id=${p.id}`, { method: 'DELETE', credentials: 'include' });
        const j = await r.json();
        if (j.error) { alert('Delete failed: ' + j.error); return; }
      }
      pairs.splice(idx, 1);
      render();
    }
  });

  // 新增空 pair
  document.getElementById('new-pair-btn').addEventListener('click', () => {
    pairs.push({
      id: 0, before_image_url: '', before_label: 'Before',
      after_image_url: '', after_label: 'After',
      alt_text: '', sort_order: pairs.length + 1, is_active: 1,
    });
    render();
  });

  // 保存 copy
  document.getElementById('save-copy').addEventListener('click', async () => {
    fb.textContent = 'Saving…'; fb.style.color = '';
    const r = await fetch('../api/admin-before-after.php', {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        _action: 'update_copy',
        subtitle: document.getElementById('copy-subtitle').value,
        footer: document.getElementById('copy-footer').value,
      }),
    });
    const j = await r.json();
    if (j.success) { fb.textContent = '✓ Saved'; fb.style.color = 'var(--success,#2c9)'; }
    else { fb.textContent = '✗ ' + (j.error || 'failed'); fb.style.color = 'var(--error)'; }
    setTimeout(() => fb.textContent = '', 3000);
  });

  load();
})();
</script>
