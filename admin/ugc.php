<?php $pageTitle = 'UGC Wall'; $activeNav = 'ugc'; require __DIR__ . '/_layout.php'; ?>
<style>
  .ugc-toolbar { display: flex; gap: .65rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.25rem; }
  .ugc-tab {
    padding: .55rem 1rem; background: transparent; color: var(--cream);
    border: 1px solid var(--border); border-radius: 999px;
    font-size: .78rem; letter-spacing: 1.5px; text-transform: uppercase;
    cursor: pointer; font-family: var(--sans); font-weight: 600;
  }
  .ugc-tab:hover { border-color: var(--gold); color: var(--gold); }
  .ugc-tab.active { background: var(--gold); color: #fff; border-color: var(--gold); }

  .ugc-grid {
    display: grid; gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  }
  .ugc-card {
    background: var(--bg-card); border: 1px solid var(--border-soft);
    border-radius: var(--radius-lg); overflow: hidden;
    display: flex; flex-direction: column;
  }
  .ugc-card.is-pending { border-left: 3px solid var(--warn); }
  .ugc-card.is-rejected { opacity: .55; }
  .ugc-img { aspect-ratio: 1; background: var(--bg-soft); overflow: hidden; }
  .ugc-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .ugc-meta { padding: .85rem 1rem; flex: 1; display: flex; flex-direction: column; gap: .35rem; font-size: .82rem; }
  .ugc-handle { color: var(--gold); font-weight: 600; }
  .ugc-caption { color: var(--text); line-height: 1.5; }
  .ugc-product { color: var(--text-muted); font-size: .75rem; }
  .ugc-actions { display: flex; gap: .35rem; padding: .65rem 1rem; border-top: 1px solid var(--border-soft); flex-wrap: wrap; }
  .ugc-actions button {
    flex: 1 1 auto; padding: .4rem .6rem; border: 1px solid var(--border);
    background: var(--bg); color: var(--cream); border-radius: var(--radius);
    cursor: pointer; font-family: var(--sans); font-size: .65rem;
    font-weight: 600; letter-spacing: 1px; text-transform: uppercase; transition: all .2s;
  }
  .ugc-actions button:hover { border-color: var(--gold); color: var(--gold); }
  .ugc-actions button.primary { background: var(--gold); color: #fff; border-color: var(--gold); }
  .ugc-actions button.danger:hover { border-color: var(--error); color: var(--error); }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem; flex-wrap: wrap; gap: .75rem;">
  <h1 style="margin:0;">📸 UGC Wall</h1>
  <button class="button button-primary" id="ugc-add-btn">+ Add Submission</button>
</div>
<p class="muted small" style="margin-bottom: 1.5rem;">Customer photos that show on the homepage. Upload them on behalf of customers (e.g. from Instagram tags), or approve user-submitted ones here.</p>

<div class="admin-card">
  <div class="ugc-toolbar" id="ugc-tabs">
    <button class="ugc-tab" data-status="pending">Pending</button>
    <button class="ugc-tab active" data-status="approved">Approved</button>
    <button class="ugc-tab" data-status="rejected">Rejected</button>
    <button class="ugc-tab" data-status="all">All</button>
    <span class="muted small" id="ugc-count" style="margin-left:.5rem;"></span>
  </div>
  <div id="ugc-list"><p class="muted">Loading…</p></div>
</div>

<!-- Add Modal -->
<div class="modal-backdrop" id="ugc-modal" style="display:none;">
  <div class="modal" style="max-width: 540px;">
    <div class="modal-header">
      <h3>Add UGC Submission</h3>
      <button class="modal-close" id="ugc-modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="ugc-form" class="form-group">
        <label><span class="label-text">Photo upload</span>
          <div style="display:flex; gap:.5rem; align-items:center;">
            <input type="file" id="ugc-file" accept="image/*" style="flex: 1;" />
            <small class="muted" id="ugc-upload-status"></small>
          </div>
        </label>
        <label><span class="label-text">Image URL (or paste)</span>
          <input type="text" name="image_url" id="ugc-image-url" placeholder="/uploads/xxx.jpg" required />
        </label>
        <div id="ugc-preview" style="display:none; aspect-ratio:1; max-width:200px; border-radius:6px; overflow:hidden; background:var(--bg-soft); border:1px solid var(--border-soft);">
          <img id="ugc-preview-img" alt="" style="width:100%;height:100%;object-fit:cover;" />
        </div>
        <label><span class="label-text">Instagram handle <span class="muted small">(no @)</span></span>
          <input type="text" name="instagram_handle" maxlength="100" placeholder="customer_username" />
        </label>
        <label><span class="label-text">Caption <span class="muted small">(optional)</span></span>
          <textarea name="caption" rows="2" maxlength="500" placeholder="What product? Why they love it?"></textarea>
        </label>
        <label><span class="label-text">Related product</span>
          <select name="related_product_id" id="ugc-product-select">
            <option value="">— None —</option>
          </select>
        </label>
        <p class="form-feedback" id="ugc-feedback"></p>
      </form>
    </div>
    <div class="modal-footer">
      <button class="button button-ghost" id="ugc-cancel-btn">Cancel</button>
      <button class="button button-primary" id="ugc-save-btn">Save & Approve</button>
    </div>
  </div>
</div>

<script>
(() => {
  const list  = document.getElementById('ugc-list');
  const tabs  = document.getElementById('ugc-tabs');
  const count = document.getElementById('ugc-count');
  const modal = document.getElementById('ugc-modal');
  const form  = document.getElementById('ugc-form');
  const fb    = document.getElementById('ugc-feedback');
  const productSel = document.getElementById('ugc-product-select');
  const file  = document.getElementById('ugc-file');
  const upStatus = document.getElementById('ugc-upload-status');
  const imgUrlIn = document.getElementById('ugc-image-url');
  const preview  = document.getElementById('ugc-preview');
  const previewImg = document.getElementById('ugc-preview-img');
  let currentStatus = 'approved';

  function escape(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function imgSrc(u) { return u && !u.startsWith('http') ? '..' + u : (u || ''); }

  async function load() {
    list.innerHTML = '<p class="muted">Loading…</p>';
    try {
      const r = await fetch('../api/admin-ugc.php?status=' + currentStatus, { credentials: 'include' });
      const j = await r.json();
      if (!r.ok) throw new Error(j.error || 'Failed');
      count.textContent = `(${j.total} total)`;
      const items = j.items || [];
      if (!items.length) {
        list.innerHTML = `<p class="muted" style="padding: 2rem 0; text-align: center;">No ${currentStatus === 'all' ? '' : currentStatus + ' '}submissions.</p>`;
        return;
      }
      list.innerHTML = '<div class="ugc-grid">' + items.map(card).join('') + '</div>';
    } catch (e) {
      list.innerHTML = `<p style="color: var(--error);">Failed: ${escape(e.message)}</p>`;
    }
  }
  function card(u) {
    const actions = [];
    if (u.status !== 'approved') actions.push(`<button class="primary" data-id="${u.id}" data-action="approve">Approve</button>`);
    if (u.status !== 'rejected') actions.push(`<button data-id="${u.id}" data-action="reject">Reject</button>`);
    actions.push(`<button class="danger" data-id="${u.id}" data-action="delete">Delete</button>`);
    return `
      <div class="ugc-card is-${escape(u.status)}">
        <div class="ugc-img"><img src="${escape(imgSrc(u.image_url))}" alt=""></div>
        <div class="ugc-meta">
          ${u.instagram_handle ? `<div class="ugc-handle">@${escape(u.instagram_handle)}</div>` : ''}
          ${u.caption ? `<div class="ugc-caption">${escape(u.caption)}</div>` : ''}
          ${u.product_name ? `<div class="ugc-product">📌 ${escape(u.product_name)}</div>` : ''}
          <small class="muted">${escape(u.status)} · ${escape(u.created_at)}</small>
        </div>
        <div class="ugc-actions">${actions.join('')}</div>
      </div>`;
  }

  // Load products list for dropdown
  async function loadProducts() {
    try {
      const r = await fetch('../api/admin-products.php', { credentials: 'include' });
      const j = await r.json();
      const opts = (j.products || [])
        .filter(p => p.is_active == 1)
        .map(p => `<option value="${p.id}">${escape(p.name)} (${escape(p.sku)})</option>`)
        .join('');
      productSel.innerHTML = '<option value="">— None —</option>' + opts;
    } catch {}
  }

  tabs.addEventListener('click', (e) => {
    const btn = e.target.closest('.ugc-tab');
    if (!btn) return;
    document.querySelectorAll('.ugc-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    currentStatus = btn.dataset.status; load();
  });

  list.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const id = parseInt(btn.dataset.id, 10);
    const action = btn.dataset.action;
    if (action === 'delete' && !confirm('Delete this submission permanently?')) return;
    btn.disabled = true; btn.textContent = '…';
    try {
      const r = await fetch('../api/admin-ugc.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, action }),
      });
      const j = await r.json();
      if (!r.ok || j.error) throw new Error(j.error || 'Failed');
      load();
    } catch (err) { alert('Failed: ' + err.message); load(); }
  });

  document.getElementById('ugc-add-btn').addEventListener('click', () => {
    fb.textContent = ''; fb.className = 'form-feedback';
    form.reset(); preview.style.display = 'none'; upStatus.textContent = '';
    modal.style.display = 'flex';
    if (!productSel.options.length || productSel.options.length < 2) loadProducts();
  });
  document.getElementById('ugc-modal-close').addEventListener('click', () => modal.style.display = 'none');
  document.getElementById('ugc-cancel-btn').addEventListener('click', () => modal.style.display = 'none');

  imgUrlIn.addEventListener('input', () => {
    const v = imgUrlIn.value.trim();
    if (v) {
      previewImg.src = imgSrc(v);
      preview.style.display = 'block';
    } else {
      preview.style.display = 'none';
    }
  });

  file.addEventListener('change', async (e) => {
    const f = e.target.files[0];
    if (!f) return;
    upStatus.textContent = 'Uploading…';
    const fd = new FormData(); fd.append('file', f);
    try {
      const r = await fetch('../api/admin-upload.php', { method:'POST', credentials:'include', body: fd });
      const j = await r.json();
      if (j.success) {
        imgUrlIn.value = j.url;
        previewImg.src = imgSrc(j.url);
        preview.style.display = 'block';
        upStatus.textContent = '✓ uploaded';
        upStatus.style.color = 'var(--gold)';
      } else {
        upStatus.textContent = '✗ ' + (j.error || 'failed');
        upStatus.style.color = 'var(--error)';
      }
    } catch (err) { upStatus.textContent = '✗ ' + err.message; upStatus.style.color = 'var(--error)'; }
  });

  document.getElementById('ugc-save-btn').addEventListener('click', async () => {
    const data = Object.fromEntries(new FormData(form).entries());
    if (!data.image_url) { fb.textContent = 'Image required'; fb.className = 'form-feedback error'; return; }
    data.action = 'create';
    data.status = 'approved';
    fb.textContent = 'Saving…'; fb.className = 'form-feedback';
    try {
      const r = await fetch('../api/admin-ugc.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const j = await r.json();
      if (j.success) {
        fb.textContent = '✓ saved'; fb.className = 'form-feedback success';
        setTimeout(() => { modal.style.display = 'none'; load(); }, 400);
      } else {
        fb.textContent = j.error || 'Save failed'; fb.className = 'form-feedback error';
      }
    } catch (err) { fb.textContent = 'Network error'; fb.className = 'form-feedback error'; }
  });

  load();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
