<?php $pageTitle = 'TikTok Videos'; $activeNav = 'videos'; require __DIR__ . '/_layout.php'; ?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
  <h1 style="margin:0;">🎬 <?= $lang === 'zh' ? 'TikTok 视频管理' : 'TikTok Video Management' ?></h1>
  <button id="new-video-btn" class="button button-primary">+ <?= $lang === 'zh' ? '添加视频' : 'Add Video' ?></button>
</div>

<div class="admin-card" style="overflow-x: auto;">
  <div id="videos-container"><p class="muted">Loading…</p></div>
</div>

<div class="modal-backdrop" id="video-modal" style="display:none;">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title"><?= $lang === 'zh' ? '添加 TikTok 视频' : 'Add TikTok Video' ?></h3>
      <button class="modal-close" id="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="video-form" class="form-group">
        <input type="hidden" name="id" id="v-id" />
        <label><span class="label-text">TikTok URL <span class="required">*</span></span>
          <input type="url" name="video_url" required
                 placeholder="https://www.tiktok.com/@username/video/7123456789012345678" />
          <small class="muted" style="display:block; margin-top:.35rem;">Paste the full TikTok video URL — we'll auto-extract creator + video ID.</small>
        </label>
        <label><span class="label-text">Title</span>
          <input type="text" name="title" maxlength="255" placeholder="e.g. Drama Mink 22mm First Impression" />
        </label>
        <label><span class="label-text">Description</span>
          <textarea name="description" rows="2"></textarea>
        </label>
        <label><span class="label-text">Cover Image URL (optional)</span>
          <div style="display:flex; gap:.5rem;">
            <input type="text" name="cover_url" id="v-cover-url" style="flex:1;" />
            <label class="button button-outline button-sm" style="cursor:pointer; white-space:nowrap;">
              📤 Upload
              <input type="file" id="v-cover-upload" accept="image/*" hidden />
            </label>
          </div>
          <small id="v-cover-status" class="muted" style="display:block; margin-top:.35rem;"></small>
        </label>
        <div class="form-row">
          <label><span class="label-text">Related Product ID (optional)</span>
            <input type="number" name="related_product_id" placeholder="e.g. 5" />
          </label>
          <label><span class="label-text">Sort Order</span>
            <input type="number" name="sort_order" value="0" />
          </label>
        </div>
        <div class="checkbox-row">
          <input type="checkbox" name="is_featured" id="v-feat" value="1" />
          <label for="v-feat">Featured (show on homepage)</label>
        </div>
        <div class="checkbox-row">
          <input type="checkbox" name="is_active" id="v-act" value="1" checked />
          <label for="v-act">Active (visible on /videos.html)</label>
        </div>
        <p class="form-feedback" id="v-feedback"></p>
      </form>
    </div>
    <div class="modal-footer">
      <button class="button button-ghost" id="cancel-btn">Cancel</button>
      <button class="button button-primary" id="save-btn">Save</button>
    </div>
  </div>
</div>

<script>
(function () {
  const container = document.getElementById('videos-container');
  const modal = document.getElementById('video-modal');
  const form = document.getElementById('video-form');
  const fb = document.getElementById('v-feedback');
  let allVideos = [];

  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  function render() {
    if (!allVideos.length) { container.innerHTML = '<p class="muted">No videos yet. Click + Add Video.</p>'; return; }
    const head = `<thead><tr><th>#</th><th>Creator</th><th>Title</th><th>Featured</th><th>Status</th><th>Sort</th><th></th></tr></thead>`;
    const rows = allVideos.map(v => `
      <tr style="opacity:${v.is_active==1?1:0.5}">
        <td><strong style="color:var(--gold)">#${v.id}</strong></td>
        <td>@${escape(v.creator_handle)}</td>
        <td><strong>${escape(v.title||'Untitled')}</strong><br>
            <small><a href="${escape(v.video_url)}" target="_blank" rel="noopener">View on TikTok ↗</a></small></td>
        <td>${v.is_featured==1?'⭐':'—'}</td>
        <td><span class="status-badge ${v.is_active==1?'status-paid':'status-cancelled'}">${v.is_active==1?'Active':'Hidden'}</span></td>
        <td>${v.sort_order}</td>
        <td>
          <button class="filter-btn edit-btn" data-id="${v.id}">Edit</button>
          <button class="filter-btn del-btn" data-id="${v.id}" style="color:var(--error); border-color:var(--error)">Delete</button>
        </td>
      </tr>`).join('');
    container.innerHTML = `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
  }

  async function load() {
    container.innerHTML = '<p class="muted">Loading…</p>';
    try {
      const r = await fetch('../api/admin-videos.php', { credentials: 'include' });
      const j = await r.json();
      allVideos = j.videos || []; render();
    } catch (e) {
      container.innerHTML = '<p style="color:var(--error)">Load failed</p>';
    }
  }

  function openModal(v = null) {
    fb.textContent = ''; fb.className = 'form-feedback';
    form.reset();
    if (v) {
      document.getElementById('v-id').value = v.id;
      form.video_url.value = v.video_url;
      form.title.value = v.title || '';
      form.description.value = v.description || '';
      form.cover_url.value = v.cover_url || '';
      form.related_product_id.value = v.related_product_id || '';
      form.sort_order.value = v.sort_order;
      form.is_featured.checked = v.is_featured == 1;
      form.is_active.checked = v.is_active == 1;
    } else {
      document.getElementById('v-id').value = '';
    }
    modal.style.display = 'flex';
  }
  function closeModal() { modal.style.display = 'none'; }

  document.getElementById('new-video-btn').addEventListener('click', () => openModal());
  document.getElementById('modal-close').addEventListener('click', closeModal);
  document.getElementById('cancel-btn').addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  container.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.edit-btn');
    if (editBtn) {
      const v = allVideos.find(x => x.id == editBtn.dataset.id);
      if (v) openModal(v);
    }
    const delBtn = e.target.closest('.del-btn');
    if (delBtn) {
      if (!confirm('Delete this video?')) return;
      try {
        await fetch('../api/admin-videos.php?id='+delBtn.dataset.id, { method: 'DELETE', credentials: 'include' });
        load();
      } catch (e) { alert('Delete failed'); }
    }
  });

  // Cover upload
  document.getElementById('v-cover-upload').addEventListener('change', async (e) => {
    const f = e.target.files[0]; if (!f) return;
    const status = document.getElementById('v-cover-status');
    status.textContent = 'Uploading…';
    const fd = new FormData(); fd.append('file', f);
    try {
      const r = await fetch('../api/admin-upload.php', { method:'POST', credentials:'include', body: fd });
      const j = await r.json();
      if (j.success) {
        document.getElementById('v-cover-url').value = j.url;
        status.textContent = '✓ ' + j.url; status.style.color = 'var(--success)';
      } else { status.textContent = '✗ ' + (j.error || 'failed'); status.style.color = 'var(--error)'; }
    } catch (err) { status.textContent = '✗ Network error'; status.style.color = 'var(--error)'; }
    e.target.value = '';
  });

  document.getElementById('save-btn').addEventListener('click', async () => {
    const id = document.getElementById('v-id').value;
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_featured = data.is_featured ? 1 : 0;
    data.is_active = data.is_active ? 1 : 0;
    fb.textContent = 'Saving…'; fb.className = 'form-feedback';
    try {
      const url = '../api/admin-videos.php' + (id ? '?id='+id : '');
      const r = await fetch(url, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const j = await r.json();
      if (j.success) {
        fb.textContent = '✓ Saved'; fb.className = 'form-feedback success';
        setTimeout(() => { closeModal(); load(); }, 400);
      } else {
        fb.textContent = j.error || 'Save failed'; fb.className = 'form-feedback error';
      }
    } catch (e) { fb.textContent = 'Network error'; fb.className = 'form-feedback error'; }
  });

  load();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
