<?php $pageTitle = 'Media Library'; $activeNav = 'media'; require __DIR__ . '/_layout.php'; ?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
  <h1 style="margin:0;">🖼 <?= $lang === 'zh' ? '媒体库' : 'Media Library' ?></h1>
  <label class="button button-primary" style="cursor:pointer;">
    + <?= $lang === 'zh' ? '上传文件' : 'Upload' ?>
    <input type="file" id="upload-input" accept="image/*,video/*" multiple hidden />
  </label>
</div>

<p class="muted" style="margin-bottom:1.5rem;">
  <?= $lang === 'zh' ? '图片：≤ 10MB（jpg/png/webp/gif）· 视频：≤ 200MB（mp4/webm/mov）· 上传后右键复制 URL，粘贴到产品/设置中。' : 'Images ≤ 10MB · Videos ≤ 200MB · Right-click to copy URL after upload.' ?>
</p>

<div id="upload-progress" style="display:none; padding:1rem; background:var(--bg-card); border-radius:8px; margin-bottom:1rem;">
  <div id="upload-status" class="muted small">Uploading…</div>
  <div style="background:var(--bg); height:6px; border-radius:3px; overflow:hidden; margin-top:.5rem;">
    <div id="upload-bar" style="background:var(--gold); height:100%; width:0%; transition:width .2s;"></div>
  </div>
</div>

<div id="media-grid" style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr));">
  <p class="muted">Loading…</p>
</div>

<style>
  .media-card {
    background: var(--bg-card);
    border: 1px solid var(--border-soft);
    border-radius: var(--radius);
    overflow: hidden;
    position: relative;
    transition: border-color .2s;
  }
  .media-card:hover { border-color: var(--gold); }
  .media-thumb { aspect-ratio: 1; overflow: hidden; background: var(--bg); position: relative; }
  .media-thumb img { width:100%; height:100%; object-fit: cover; }
  .media-thumb video { width:100%; height:100%; object-fit: cover; }
  .media-thumb .video-badge {
    position: absolute; top: .5rem; right: .5rem;
    background: rgba(0,0,0,0.8); color: var(--gold);
    padding: .15rem .4rem; border-radius: 3px; font-size: .65rem;
  }
  .media-meta { padding: .65rem; }
  .media-url {
    width: 100%; padding: .35rem .5rem;
    background: var(--bg); border: 1px solid var(--border);
    color: var(--cream); font-size: .7rem; font-family: monospace;
    border-radius: 3px; cursor: text;
  }
  .media-actions { display: flex; gap: .35rem; margin-top: .5rem; }
  .media-actions button {
    flex: 1; padding: .35rem; font-size: .7rem;
    background: var(--bg); border: 1px solid var(--border);
    color: var(--cream); border-radius: 3px; cursor: pointer;
  }
  .media-actions button:hover { border-color: var(--gold); color: var(--gold); }
  .media-actions .del-btn:hover { border-color: var(--error); color: var(--error); }
</style>

<script>
(function () {
  const grid = document.getElementById('media-grid');
  const input = document.getElementById('upload-input');
  const progress = document.getElementById('upload-progress');
  const statusEl = document.getElementById('upload-status');
  const bar = document.getElementById('upload-bar');

  async function loadMedia() {
    grid.innerHTML = '<p class="muted">Loading…</p>';
    try {
      const r = await fetch('../api/admin-media.php', { credentials: 'include' });
      const j = await r.json();
      const files = j.files || [];
      if (!files.length) { grid.innerHTML = '<p class="muted">No files yet. Click + Upload to get started.</p>'; return; }
      grid.innerHTML = files.map(f => `
        <div class="media-card">
          <div class="media-thumb">
            ${f.is_video
              ? `<video src="${esc(f.url)}" muted></video><span class="video-badge">VIDEO</span>`
              : `<img src="${esc(f.url)}" alt="${esc(f.name)}" loading="lazy" />`}
          </div>
          <div class="media-meta">
            <input class="media-url" value="${esc(f.url)}" readonly onclick="this.select()" />
            <div class="media-actions">
              <button class="copy-btn" data-url="${esc(f.url)}">Copy</button>
              <button class="del-btn" data-url="${esc(f.url)}">Delete</button>
            </div>
            <p class="muted small" style="margin-top:.35rem;">${(f.size/1024).toFixed(0)} KB</p>
          </div>
        </div>`).join('');
    } catch (e) { grid.innerHTML = '<p style="color:var(--error)">Load failed</p>'; }
  }
  function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  input.addEventListener('change', async (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;
    progress.style.display = 'block';
    let done = 0;
    for (const f of files) {
      statusEl.textContent = `Uploading ${f.name} (${done+1}/${files.length})…`;
      bar.style.width = ((done / files.length) * 100) + '%';
      const fd = new FormData();
      fd.append('file', f);
      try {
        const r = await fetch('../api/admin-upload.php', {
          method: 'POST', credentials: 'include', body: fd,
        });
        const j = await r.json();
        if (!j.success) statusEl.textContent = '⚠ ' + (j.error || 'failed');
      } catch (err) { statusEl.textContent = '⚠ Network error'; }
      done++;
    }
    bar.style.width = '100%';
    statusEl.textContent = `✓ Uploaded ${done} file(s)`;
    setTimeout(() => { progress.style.display = 'none'; loadMedia(); input.value = ''; }, 1200);
  });

  grid.addEventListener('click', async (e) => {
    const copyBtn = e.target.closest('.copy-btn');
    if (copyBtn) {
      try { await navigator.clipboard.writeText(location.origin + copyBtn.dataset.url); copyBtn.textContent = '✓ Copied'; setTimeout(() => copyBtn.textContent = 'Copy', 1500); }
      catch (err) { alert('Copy failed - select the URL field manually'); }
    }
    const delBtn = e.target.closest('.del-btn');
    if (delBtn) {
      if (!confirm('Delete this file? Products/settings using this URL will break.')) return;
      try {
        await fetch('../api/admin-media.php?url=' + encodeURIComponent(delBtn.dataset.url), { method: 'DELETE', credentials: 'include' });
        loadMedia();
      } catch (e) { alert('Delete failed'); }
    }
  });

  loadMedia();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
