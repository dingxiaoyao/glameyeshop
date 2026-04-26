<?php $pageTitle = 'Products'; $activeNav = 'products'; require __DIR__ . '/_layout.php'; ?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
  <h1 style="margin:0;">💄 <?= htmlspecialchars(t('product_management')) ?></h1>
  <button id="new-product-btn" class="button button-primary">+ <?= htmlspecialchars(t('add_product')) ?></button>
</div>

<div class="filter-bar">
  <button class="filter-btn active" data-cat=""><?= htmlspecialchars(t('all')) ?></button>
  <button class="filter-btn" data-cat="mink">Mink</button>
  <button class="filter-btn" data-cat="faux">Faux Mink</button>
  <button class="filter-btn" data-cat="magnetic">Magnetic</button>
  <button class="filter-btn" data-cat="tools">Tools</button>
</div>

<div class="admin-card" style="overflow-x: auto;">
  <div id="products-container"><p class="muted"><?= htmlspecialchars(t('loading')) ?></p></div>
</div>

<!-- Modal -->
<div class="modal-backdrop" id="product-modal" style="display:none;">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title"><?= htmlspecialchars(t('add_product')) ?></h3>
      <button class="modal-close" id="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="product-form" class="form-group">
        <input type="hidden" name="id" id="p-id" />
        <div class="form-row">
          <label><span class="label-text"><?= htmlspecialchars(t('sku')) ?> *</span>
            <input type="text" name="sku" required maxlength="64" />
          </label>
          <label><span class="label-text"><?= htmlspecialchars(t('category')) ?> *</span>
            <select name="category" required>
              <option value="mink">Mink</option>
              <option value="faux">Faux Mink</option>
              <option value="magnetic">Magnetic</option>
              <option value="tools">Tools</option>
            </select>
          </label>
        </div>
        <label><span class="label-text"><?= htmlspecialchars(t('name')) ?> *</span>
          <input type="text" name="name" required maxlength="200" />
        </label>
        <label><span class="label-text"><?= htmlspecialchars(t('short_description')) ?></span>
          <input type="text" name="short_description" maxlength="500" />
        </label>
        <label><span class="label-text"><?= htmlspecialchars(t('description')) ?></span>
          <textarea name="description" rows="3"></textarea>
        </label>
        <div class="form-row">
          <label><span class="label-text"><?= htmlspecialchars(t('price')) ?> ($) *</span>
            <input type="number" name="price" step="0.01" min="0" required />
          </label>
          <label><span class="label-text"><?= htmlspecialchars(t('compare_price')) ?> ($)</span>
            <input type="number" name="compare_at_price" step="0.01" min="0" />
          </label>
        </div>
        <label><span class="label-text"><?= $lang === 'zh' ? '商品图片 + 视频（拖拽排序，第一张作为主图）' : 'Product Images + Videos (drag to reorder · first = main)' ?></span></label>
        <?php require_once __DIR__ . '/../api/lib/upload-hints.php'; echo uploadHint('product', $lang); ?>
        <p class="muted small" style="margin:-.4rem 0 .65rem;">
          <?= $lang === 'zh'
            ? '✓ 支持图片(jpg/png/webp,≤10MB)和视频(mp4/webm/mov,≤200MB)。视频会跟图片混排展示在详情页。'
            : '✓ Accepts images (jpg/png/webp ≤10MB) and videos (mp4/webm/mov ≤200MB). Videos will be mixed inline with photos on the detail page.' ?>
        </p>
        <div class="img-uploader" id="p-img-uploader">
          <div class="img-tiles" id="p-img-tiles"></div>
          <label class="img-add-btn" id="p-img-add" data-hint="product">
            <span style="font-size:1.5rem;">＋</span>
            <span>📤 <?= $lang === 'zh' ? '点击上传图片或视频(多选)' : 'Click to upload images or videos (multiple)' ?></span>
            <input type="file" accept="image/*,video/*" multiple hidden id="p-img-input" />
          </label>
          <small id="p-img-status" class="muted" style="display:block; margin-top:.5rem;"></small>
        </div>
        <input type="hidden" name="image_url" id="p-image-url" />
        <input type="hidden" name="gallery_urls" id="p-gallery" />
        <div class="form-row">
          <label><span class="label-text"><?= htmlspecialchars(t('stock')) ?></span>
            <input type="number" name="stock" min="0" value="100" />
          </label>
          <label><span class="label-text"><?= htmlspecialchars(t('sort_order')) ?></span>
            <input type="number" name="sort_order" value="0" />
          </label>
        </div>
        <div class="checkbox-row">
          <input type="checkbox" name="is_active" id="p-active" value="1" checked />
          <label for="p-active"><?= htmlspecialchars(t('active')) ?> (<?= $lang === 'zh' ? '上架' : 'visible on shop' ?>)</label>
        </div>
        <p class="form-feedback" id="p-feedback"></p>
      </form>
    </div>
    <div class="modal-footer">
      <button class="button button-ghost" id="cancel-btn"><?= htmlspecialchars(t('cancel')) ?></button>
      <button class="button button-primary" id="save-btn"><?= htmlspecialchars(t('save')) ?></button>
    </div>
  </div>
</div>

<script>
(function () {
  const container = document.getElementById('products-container');
  const modal = document.getElementById('product-modal');
  const form = document.getElementById('product-form');
  const fb = document.getElementById('p-feedback');
  let currentCat = '';
  let allProducts = [];

  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function money(n) { return '$' + Number(n || 0).toFixed(2); }

  function render() {
    const list = currentCat ? allProducts.filter((p) => p.category === currentCat) : allProducts;
    if (!list.length) { container.innerHTML = '<p class="muted">No products.</p>'; return; }
    const head = `<thead><tr>
      <th></th><th>${T.sku}</th><th>${T.name}</th><th>${T.category}</th>
      <th>${T.price}</th><th>${T.stock}</th><th>${T.status}</th><th></th>
    </tr></thead>`;
    function thumbCell(url) {
      if (!url) {
        return `<td><div style="width:48px;height:36px;background:var(--bg-soft);border:1px dashed var(--border);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;color:var(--text-muted);">no img</div></td>`;
      }
      // url 是 /uploads/... 或 /images/... 的绝对路径,从 admin/ 目录看要加 ".."
      const src = url.startsWith('http') ? url : '..' + url;
      return `<td><img src="${escape(src)}" title="${escape(url)}" style="width:48px;height:36px;object-fit:cover;border-radius:4px;background:var(--bg-soft);" onerror="this.outerHTML='<div title=&quot;404: ${escape(url)}&quot; style=&quot;width:48px;height:36px;background:rgba(238,90,90,.08);border:1px solid var(--error);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;color:var(--error);&quot;>404</div>';" /></td>`;
    }
    const rows = list.map((p) => `
      <tr style="opacity: ${p.is_active == 1 ? 1 : 0.5}">
        ${thumbCell(p.image_url)}
        <td><small style="color:var(--gold);">${escape(p.sku)}</small></td>
        <td><strong>${escape(p.name)}</strong><br><small class="muted">${escape(p.short_description || '')}</small></td>
        <td>${escape(p.category)}</td>
        <td>${money(p.price)}${p.compare_at_price ? `<br><small class="muted" style="text-decoration:line-through">${money(p.compare_at_price)}</small>` : ''}</td>
        <td style="color: ${p.stock < 30 ? 'var(--warn)' : 'var(--cream)'}"><strong>${p.stock}</strong></td>
        <td><span class="status-badge ${p.is_active == 1 ? 'status-paid' : 'status-cancelled'}">${p.is_active == 1 ? T.active : '—'}</span></td>
        <td>
          <button class="filter-btn edit-btn" data-id="${p.id}">${T.edit}</button>
          <button class="filter-btn del-btn" data-id="${p.id}" style="color: var(--error); border-color: var(--error)">${T.delete}</button>
        </td>
      </tr>`).join('');
    container.innerHTML = `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
  }

  async function load() {
    container.innerHTML = '<p class="muted">' + T.loading + '</p>';
    try {
      const r = await fetch('../api/admin-products.php', { credentials: 'include' });
      const text = await r.text();
      let j;
      try { j = JSON.parse(text); }
      catch (parseErr) {
        // 服务器返回非 JSON(PHP fatal HTML / 500 错误页等)— 显示前 400 字符方便排查
        container.innerHTML = `<div style="padding:1rem;background:var(--bg-soft);border-radius:6px;color:var(--error)">
          <strong>Load failed (HTTP ${r.status})</strong>
          <p class="muted small" style="margin:.5rem 0 0">Server returned non-JSON. First 400 chars of response:</p>
          <pre style="background:var(--bg);padding:.75rem;border-radius:4px;font-size:.75rem;overflow:auto;max-height:200px;margin:.5rem 0 0;white-space:pre-wrap;">${escape(text.slice(0, 400))}</pre>
          <button class="filter-btn" onclick="location.reload()" style="margin-top:.5rem;">Retry</button>
        </div>`;
        return;
      }
      if (!r.ok) {
        container.innerHTML = `<p style="color:var(--error)">HTTP ${r.status}: ${escape(j.error || 'Unknown')}</p>`;
        return;
      }
      allProducts = j.products || [];
      render();
    } catch (e) {
      container.innerHTML = `<p style="color:var(--error)">Network error: ${escape(e.message || String(e))}</p>`;
    }
  }

  // === Tile-based image uploader ===
  // imageList[0] is always the main image; the rest are gallery
  let imageList = [];
  const tilesEl     = document.getElementById('p-img-tiles');
  const imgInput    = document.getElementById('p-img-input');
  const imgStatus   = document.getElementById('p-img-status');
  const imgUrlField = document.getElementById('p-image-url');
  const galleryField= document.getElementById('p-gallery');

  function syncFields() {
    imgUrlField.value  = imageList[0] || '';
    galleryField.value = imageList.length > 1 ? JSON.stringify(imageList.slice(1)) : '';
  }
  function isVideoUrl(u) { return /\.(mp4|webm|mov|m4v)(\?|$)/i.test(u || ''); }
  function renderTiles() {
    tilesEl.innerHTML = imageList.map((url, i) => {
      const src = url.startsWith('http') ? url : '..' + url;
      const safeUrl = escape(url);
      const safeSrc = escape(src);
      const mediaTag = isVideoUrl(url)
        ? `<video src="${safeSrc}" muted playsinline preload="metadata" title="${safeUrl}" style="width:100%;height:100%;object-fit:cover;pointer-events:none;"></video>
           <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.18);pointer-events:none;">
             <div style="width:28px;height:28px;background:rgba(0,0,0,.6);border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.85rem;backdrop-filter:blur(4px);">▶</div>
           </div>`
        : `<img src="${safeSrc}" alt="" title="${safeUrl}"
                onerror="this.style.display='none';this.nextElementSibling?.classList.remove('hidden');" />`;
      return `<div class="img-tile ${i===0?'is-main':''}" draggable="true" data-idx="${i}">
        ${mediaTag}
        <div class="img-broken hidden" style="display:none">
          <div class="icon">⚠</div>
          <div>not found</div>
          <code>${safeUrl}</code>
        </div>
        ${i===0 ? '<span class="badge">MAIN</span>' : `<span class="order-num">${i+1}</span>`}
        <button type="button" class="del-x" data-idx="${i}" title="Remove">×</button>
      </div>`;
    }).join('');
    // 图片 onerror 时显示 .img-broken
    tilesEl.querySelectorAll('.img-tile').forEach(t => {
      const img = t.querySelector('img');
      const broken = t.querySelector('.img-broken');
      if (img && broken) {
        img.addEventListener('error', () => { img.style.display = 'none'; broken.style.display = 'flex'; });
      }
    });
    syncFields();
  }
  // delete tile
  tilesEl.addEventListener('click', (e) => {
    const x = e.target.closest('.del-x');
    if (!x) return;
    e.preventDefault();
    imageList.splice(parseInt(x.dataset.idx, 10), 1);
    renderTiles();
  });
  // drag-drop reorder (HTML5)
  let dragSrcIdx = null;
  tilesEl.addEventListener('dragstart', (e) => {
    const tile = e.target.closest('.img-tile');
    if (!tile) return;
    dragSrcIdx = parseInt(tile.dataset.idx, 10);
    tile.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  tilesEl.addEventListener('dragend', (e) => {
    const tile = e.target.closest('.img-tile');
    if (tile) tile.classList.remove('dragging');
    tilesEl.querySelectorAll('.img-tile').forEach(t => t.classList.remove('drag-over'));
  });
  tilesEl.addEventListener('dragover', (e) => {
    e.preventDefault();
    const tile = e.target.closest('.img-tile');
    if (!tile) return;
    tilesEl.querySelectorAll('.img-tile').forEach(t => t.classList.remove('drag-over'));
    tile.classList.add('drag-over');
  });
  tilesEl.addEventListener('drop', (e) => {
    e.preventDefault();
    const tile = e.target.closest('.img-tile');
    if (!tile || dragSrcIdx === null) return;
    const dstIdx = parseInt(tile.dataset.idx, 10);
    if (dstIdx === dragSrcIdx) return;
    const moved = imageList.splice(dragSrcIdx, 1)[0];
    imageList.splice(dstIdx, 0, moved);
    dragSrcIdx = null;
    renderTiles();
  });
  // upload (multiple)
  imgInput.addEventListener('change', async (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;
    let done = 0;
    for (const f of files) {
      imgStatus.textContent = `Uploading ${++done}/${files.length}…`;
      imgStatus.style.color = '';
      const fd = new FormData(); fd.append('file', f);
      try {
        const r = await fetch('../api/admin-upload.php', { method:'POST', credentials:'include', body: fd });
        const j = await r.json();
        if (j.success) {
          imageList.push(j.url);
          renderTiles();
        } else {
          imgStatus.textContent = '✗ ' + (j.error || 'failed');
          imgStatus.style.color = 'var(--error)';
        }
      } catch (err) {
        imgStatus.textContent = '✗ ' + (err.message || 'Network error');
        imgStatus.style.color = 'var(--error)';
      }
    }
    imgStatus.textContent = `✓ ${imageList.length} image(s) total. Drag to reorder · first = main`;
    imgStatus.style.color = 'var(--gold)';
    e.target.value = '';
  });

  function openModal(p = null) {
    fb.textContent = ''; fb.className = 'form-feedback';
    imgStatus.textContent = '';
    form.reset();
    imageList = [];
    if (p) {
      document.getElementById('modal-title').textContent = T.edit + ' · ' + p.name;
      document.getElementById('p-id').value = p.id;
      form.sku.value = p.sku;
      form.category.value = p.category;
      form.name.value = p.name;
      form.short_description.value = p.short_description || '';
      form.description.value = p.description || '';
      form.price.value = p.price;
      form.compare_at_price.value = p.compare_at_price || '';
      form.stock.value = p.stock;
      form.sort_order.value = p.sort_order;
      form.is_active.checked = p.is_active == 1;
      // populate tiles
      if (p.image_url) imageList.push(p.image_url);
      let extras = [];
      if (p.gallery_urls) {
        try {
          const arr = (typeof p.gallery_urls === 'string') ? JSON.parse(p.gallery_urls) : p.gallery_urls;
          if (Array.isArray(arr)) extras = arr.filter(Boolean);
        } catch {}
      }
      imageList = imageList.concat(extras);
    } else {
      document.getElementById('modal-title').textContent = T.add_product;
      document.getElementById('p-id').value = '';
    }
    renderTiles();
    modal.style.display = 'flex';
  }

  function closeModal() { modal.style.display = 'none'; }

  document.getElementById('new-product-btn').addEventListener('click', () => openModal());
  document.getElementById('modal-close').addEventListener('click', closeModal);
  document.getElementById('cancel-btn').addEventListener('click', closeModal);

  container.addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.edit-btn');
    if (editBtn) {
      const p = allProducts.find((x) => x.id == editBtn.dataset.id);
      if (p) openModal(p);
    }
    const delBtn = e.target.closest('.del-btn');
    if (delBtn) {
      if (!confirm(T.confirm_delete)) return;
      try {
        const r = await fetch('../api/admin-products.php?id=' + delBtn.dataset.id, {
          method: 'DELETE', credentials: 'include',
        });
        const j = await r.json();
        if (j.success) load();
        else alert(j.error || 'Delete failed');
      } catch (err) { alert('Delete failed'); }
    }
  });

  document.getElementById('save-btn').addEventListener('click', async () => {
    const id = document.getElementById('p-id').value;
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_active = data.is_active ? 1 : 0;
    fb.textContent = 'Saving...'; fb.className = 'form-feedback';
    try {
      const url = '../api/admin-products.php' + (id ? '?id=' + id : '');
      const r = await fetch(url, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const j = await r.json();
      if (j.success) {
        fb.textContent = '✓ ' + T.save; fb.className = 'form-feedback success';
        setTimeout(() => { closeModal(); load(); }, 400);
      } else {
        fb.textContent = j.error || 'Save failed'; fb.className = 'form-feedback error';
      }
    } catch (err) { fb.textContent = 'Network error'; fb.className = 'form-feedback error'; }
  });

  document.querySelectorAll('.filter-btn[data-cat]').forEach((b) => {
    b.addEventListener('click', () => {
      document.querySelectorAll('.filter-btn[data-cat]').forEach(x => x.classList.remove('active'));
      b.classList.add('active'); currentCat = b.dataset.cat; render();
    });
  });

  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  load();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
