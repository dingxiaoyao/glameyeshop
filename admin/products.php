<?php $pageTitle = 'Products'; $activeNav = 'products'; require __DIR__ . '/_layout.php'; ?>
<style>
  /* 列表缩略图 hover 切第二张(gallery_urls[0]) — 给运营快速预览替代图 */
  .admin-thumb-hover:hover .t-main  { opacity: 0; }
  .admin-thumb-hover:hover .t-hover { opacity: 1; }
</style>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
  <h1 style="margin:0;">💄 <?= htmlspecialchars(t('product_management')) ?></h1>
  <button id="new-product-btn" class="button button-primary">+ <?= htmlspecialchars(t('add_product')) ?></button>
</div>

<div class="filter-bar">
  <button class="filter-btn active" data-cat=""><?= htmlspecialchars(t('all')) ?></button>
  <button class="filter-btn" data-cat="mink"><?= $lang === "zh" ? "Mink (貂毛)" : "Mink" ?></button>
  <button class="filter-btn" data-cat="faux"><?= $lang === "zh" ? "Faux (素貂)" : "Faux Mink" ?></button>
  <button class="filter-btn" data-cat="magnetic"><?= $lang === "zh" ? "磁吸款" : "Magnetic" ?></button>
  <button class="filter-btn" data-cat="tools"><?= $lang === "zh" ? "工具" : "Tools" ?></button>
  <button class="filter-btn" data-cat="bundle">📦 <?= $lang === "zh" ? "套装" : "Bundles" ?></button>
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
            <select name="category" required id="p-category">
              <option value="cluster-kit"><?= $lang === "zh" ? "Cluster Kit(当前在售)" : "Cluster Kit (current line)" ?></option>
              <option value="mink">Mink</option>
              <option value="faux">Faux Mink</option>
              <option value="magnetic">Magnetic</option>
              <option value="tools">Tools</option>
              <option value="bundle">📦 <?= $lang === "zh" ? "Bundle(套装)" : "Bundle (set)" ?></option>
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
        <label>
          <span class="label-text">🎵 TikTok Shop URL
            <small class="muted" style="font-weight:400;font-size:.72rem;">
              (<?= $lang === 'zh' ? '该 SKU 在 TikTok Shop 的直链;留空则前台按钮跳全局 TikTok Shop 主页' : 'Deep link for this SKU on TikTok Shop; leave blank to fall back to the global TikTok Shop URL' ?>)
            </small>
          </span>
          <input type="url" name="tiktok_shop_url" maxlength="500"
                 placeholder="https://www.tiktok.com/shop/pdp/xxxxx"
                 style="font-family:monospace;font-size:.85rem;" />
        </label>
        <div class="checkbox-row">
          <input type="checkbox" name="is_active" id="p-active" value="1" checked />
          <label for="p-active"><?= htmlspecialchars(t('active')) ?> (<?= $lang === 'zh' ? '上架' : 'visible on shop' ?>)</label>
        </div>
        <div class="checkbox-row">
          <input type="checkbox" name="is_bundle" id="p-is-bundle" value="1" />
          <label for="p-is-bundle"><?= $lang === 'zh' ? '这是一个套装(组合多件单品)' : 'This is a bundle (set of multiple items)' ?></label>
        </div>
        <div id="p-bundle-fieldset" style="display:none; padding: 1rem; border: 1px solid var(--border-soft); border-radius: 4px; background: var(--bg-soft);">
          <label class="label-text" style="display:block;margin-bottom:.5rem;">
            <?= $lang === 'zh' ? 'Bundle 组件(已选)' : 'Bundle Components (selected)' ?>
          </label>

          <!-- 已选组件列表 — JS 动态渲染 -->
          <div id="p-bundle-picked" style="display:grid;gap:.5rem;margin-bottom:.75rem;"></div>

          <!-- 添加新组件 -->
          <div style="display:grid;grid-template-columns:1fr 100px auto;gap:.5rem;align-items:center;padding:.5rem;background:var(--bg);border-radius:4px;">
            <select id="p-bundle-sku-select" style="font-size:.85rem;">
              <option value=""><?= $lang === 'zh' ? '— 选一个产品 SKU —' : '— Pick a product SKU —' ?></option>
            </select>
            <input type="number" id="p-bundle-qty-input" value="1" min="1" max="20" placeholder="qty" style="font-size:.85rem;" />
            <button type="button" id="p-bundle-add-btn" class="button button-primary" style="font-size:.8rem;padding:.4rem .75rem;">
              + <?= $lang === 'zh' ? '添加' : 'Add' ?>
            </button>
          </div>

          <!-- 隐藏 JSON 字段 — JS 在每次 picked 改变后同步 -->
          <input type="hidden" name="bundle_items" id="p-bundle-items" />

          <p class="muted small" style="margin-top:.75rem;line-height:1.6;">
            <?= $lang === 'zh'
              ? '✦ 从下拉选一个真实 SKU + 数量 → 添加。卡片上可调数量、删除。<br>✦ Bundle 自身的 <strong>Price</strong> 字段就是套装价(已减折扣),<strong>Compare Price</strong> 用来显示划掉的原价。前台自动算出 “Save $X” = 各组件原价 × 数量 - 套装价。'
              : '✦ Pick a real SKU + qty from the dropdown → Add. Adjust qty or remove inline.<br>✦ The Bundle\'s <strong>Price</strong> field IS the bundle price (already discounted). <strong>Compare Price</strong> shows the strike-through list price. Frontend auto-computes “Save $X” = (component price × qty) − bundle price.' ?>
          </p>
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

  let showHidden = false;
  function render() {
    let list = currentCat ? allProducts.filter((p) => p.category === currentCat) : allProducts.slice();
    const hiddenCount = list.filter((p) => p.is_active != 1).length;
    if (!showHidden) list = list.filter((p) => p.is_active == 1);
    const toggleHtml = hiddenCount > 0
      ? `<div style="margin:.5rem 0 1rem;padding:.5rem .75rem;background:var(--bg-soft);border-radius:6px;font-size:.85rem;">
          <label style="cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;">
            <input type="checkbox" id="toggle-hidden" ${showHidden ? 'checked' : ''} />
            <span class="muted">${showHidden ? 'Hiding' : 'Show'} ${hiddenCount} deleted/inactive product${hiddenCount>1?'s':''}</span>
          </label>
        </div>`
      : '';
    if (!list.length) {
      container.innerHTML = toggleHtml + '<p class="muted">' + (hiddenCount > 0 ? 'No active products in this view. Toggle above to show deleted.' : 'No products.') + '</p>';
      bindToggle();
      return;
    }
    const head = `<thead><tr>
      <th></th><th>${T.sku}</th><th>${T.name}</th><th>${T.category}</th>
      <th>${T.price}</th><th>${T.stock}</th><th>${T.status}</th><th></th>
    </tr></thead>`;
    function thumbCell(url, p) {
      if (!url) {
        return `<td><div style="width:48px;height:36px;background:var(--bg-soft);border:1px dashed var(--border);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;color:var(--text-muted);">no img</div></td>`;
      }
      const src = url.startsWith('http') ? url : '..' + url;
      // 取 gallery 第二张图作为 hover 预览
      let hoverSrc = '';
      if (p && p.gallery_urls) {
        try {
          const gal = (typeof p.gallery_urls === 'string') ? JSON.parse(p.gallery_urls) : p.gallery_urls;
          if (Array.isArray(gal) && gal[0]) {
            const u = gal[0];
            hoverSrc = u.startsWith('http') ? u : '..' + u;
          }
        } catch {}
      }
      const onerr = `this.outerHTML='<div title=&quot;404: ${escape(url)}&quot; style=&quot;width:48px;height:36px;background:rgba(238,90,90,.08);border:1px solid var(--error);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:9px;color:var(--error);&quot;>404</div>'`;
      if (hoverSrc) {
        return `<td>
          <div class="admin-thumb-hover" style="position:relative;width:48px;height:36px;border-radius:4px;overflow:hidden;background:var(--bg-soft);">
            <img src="${escape(src)}" title="${escape(url)}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:opacity .25s;" onerror="${onerr}" class="t-main" />
            <img src="${escape(hoverSrc)}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .25s;" class="t-hover" />
          </div>
        </td>`;
      }
      return `<td><img src="${escape(src)}" title="${escape(url)}" style="width:48px;height:36px;object-fit:cover;border-radius:4px;background:var(--bg-soft);" onerror="${onerr}" /></td>`;
    }
    const rows = list.map((p) => `
      <tr style="opacity: ${p.is_active == 1 ? 1 : 0.5}">
        ${thumbCell(p.image_url, p)}
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
    container.innerHTML = toggleHtml + `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
    bindToggle();
  }
  function bindToggle() {
    const t = document.getElementById('toggle-hidden');
    if (t) t.addEventListener('change', (e) => { showHidden = e.target.checked; render(); });
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
          <button class="filter-btn" onclick="location.reload()" style="margin-top:.5rem;"><?= $lang === "zh" ? "重试" : "Retry" ?></button>
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
        <button type="button" class="del-x" data-idx="${i}" title="<?= $lang === 'zh' ? '删除' : 'Remove' ?>">×</button>
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
    let done = 0, failed = 0, lastErr = '';
    for (const f of files) {
      imgStatus.textContent = `Uploading ${++done}/${files.length} (${f.name})…`;
      imgStatus.style.color = '';
      const fd = new FormData(); fd.append('file', f);
      let r, txt = '', j = null;
      try {
        r = await fetch('../api/admin-upload.php', { method:'POST', credentials:'include', body: fd });
        txt = await r.text();
        try { j = JSON.parse(txt); } catch (_) {}
      } catch (netErr) {
        failed++; lastErr = 'Network: ' + (netErr.message || String(netErr));
        continue;
      }
      if (!r.ok || !j) {
        failed++;
        lastErr = `HTTP ${r.status} — ${(txt || '(empty)').slice(0, 300)}`;
        continue;
      }
      if (j.success) {
        imageList.push(j.url);
        renderTiles();
        if (j.process_error) console.warn('[upload] image processed with warnings:', j.process_error);
      } else {
        failed++;
        lastErr = j.error || 'Upload failed';
      }
    }
    if (failed > 0) {
      imgStatus.innerHTML = `✗ ${failed}/${files.length} failed — <code style="font-size:.78rem;background:rgba(238,90,90,.1);padding:.2rem .4rem;border-radius:3px;">${lastErr.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]))}</code>`;
      imgStatus.style.color = 'var(--error)';
    } else {
      imgStatus.textContent = `✓ ${imageList.length} image(s) total. Drag to reorder · first = main`;
      imgStatus.style.color = 'var(--gold)';
    }
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
      form.tiktok_shop_url.value = p.tiktok_shop_url || '';
      // bundle 字段(可视化 picker)
      const isBundleEl = document.getElementById('p-is-bundle');
      const biSet = document.getElementById('p-bundle-fieldset');
      isBundleEl.checked = p.is_bundle == 1;
      bundlePicked = [];
      if (p.bundle_items) {
        try {
          const arr = (typeof p.bundle_items === 'string') ? JSON.parse(p.bundle_items) : p.bundle_items;
          if (Array.isArray(arr)) bundlePicked = arr.map(x => ({ sku: x.sku, qty: parseInt(x.qty, 10) || 1 }));
        } catch {}
      }
      renderBundlePicked();
      biSet.style.display = isBundleEl.checked ? 'block' : 'none';
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
      bundlePicked = [];
      renderBundlePicked();
    }
    // 填充 bundle 的 SKU 下拉(在 p-id 已被设置后,排除自身)
    populateSkuOptions();
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
      let r, txt = '', j = null;
      try {
        r = await fetch('../api/admin-products.php?id=' + delBtn.dataset.id, {
          method: 'DELETE', credentials: 'include',
        });
        txt = await r.text();
        try { j = JSON.parse(txt); } catch (_) {}
      } catch (err) {
        alert('Delete failed — Network: ' + (err.message || String(err)));
        return;
      }
      if (!r.ok || !j) {
        alert(`Delete failed — HTTP ${r.status}\n\n${(txt || '(empty)').slice(0, 600)}`);
        return;
      }
      if (j.success) {
        // 小 toast 反馈 — 让用户明确知道删了哪个
        const productName = (allProducts.find((p) => p.id == delBtn.dataset.id) || {}).name || ('#' + delBtn.dataset.id);
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;background:var(--success,#2c9);color:#fff;padding:.75rem 1rem;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.2);z-index:9999;font-size:.9rem;';
        toast.textContent = '✓ Deleted: ' + productName;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
        load();
      } else {
        alert('Delete failed — ' + (j.error || 'unknown'));
      }
    }
  });

  // Bundle checkbox 切换 fieldset 显示;切换 category 也同步(兼容性)
  document.getElementById('p-is-bundle').addEventListener('change', (e) => {
    document.getElementById('p-bundle-fieldset').style.display = e.target.checked ? 'block' : 'none';
    if (e.target.checked) {
      document.getElementById('p-category').value = 'bundle';
    }
  });
  document.getElementById('p-category').addEventListener('change', (e) => {
    if (e.target.value === 'bundle') {
      document.getElementById('p-is-bundle').checked = true;
      document.getElementById('p-bundle-fieldset').style.display = 'block';
    }
  });

  // === Bundle 可视化组件 picker ===
  let bundlePicked = [];  // [{sku, qty}]
  const bSelect = document.getElementById('p-bundle-sku-select');
  const bQty    = document.getElementById('p-bundle-qty-input');
  const bAdd    = document.getElementById('p-bundle-add-btn');
  const bList   = document.getElementById('p-bundle-picked');
  const bHidden = document.getElementById('p-bundle-items');

  // 填充 SKU 下拉(排除 bundle 自身,避免自我嵌套)
  function populateSkuOptions() {
    // 清旧 option 留 placeholder
    bSelect.querySelectorAll('option:not(:first-child)').forEach(o => o.remove());
    const editingId = document.getElementById('p-id').value;
    const list = allProducts
      .filter(p => p.is_bundle != 1 && p.is_active == 1 && String(p.id) !== String(editingId))
      .sort((a, b) => (a.category || '').localeCompare(b.category || '') || a.name.localeCompare(b.name));
    let lastCat = '';
    let group = null;
    list.forEach(p => {
      if (p.category !== lastCat) {
        group = document.createElement('optgroup');
        group.label = p.category;
        bSelect.appendChild(group);
        lastCat = p.category;
      }
      const opt = document.createElement('option');
      opt.value = p.sku;
      opt.textContent = `${p.sku} · ${p.name} ($${Number(p.price).toFixed(2)})`;
      group.appendChild(opt);
    });
  }

  function renderBundlePicked() {
    if (!bundlePicked.length) {
      bList.innerHTML = '<p class="muted small" style="margin:0;padding:.5rem;text-align:center;background:var(--bg);border-radius:4px;">No components yet — add at least 1 below.</p>';
      bHidden.value = '';
      return;
    }
    let listTotal = 0;
    const bySku = Object.fromEntries(allProducts.map(p => [p.sku, p]));
    bList.innerHTML = bundlePicked.map((it, i) => {
      const p = bySku[it.sku];
      const lineTotal = p ? Number(p.price) * (it.qty || 1) : 0;
      listTotal += lineTotal;
      const thumb = (p && p.image_url)
        ? `<img src="..${p.image_url}" style="width:32px;height:32px;object-fit:cover;border-radius:3px;background:var(--bg);" onerror="this.style.display='none'" />`
        : `<div style="width:32px;height:32px;background:var(--bg);border-radius:3px;"></div>`;
      const meta = p
        ? `<strong>${escape(p.name)}</strong><br><small class="muted">${escape(p.sku)} · $${Number(p.price).toFixed(2)} ea</small>`
        : `<strong style="color:var(--error)">⚠ ${escape(it.sku)}</strong><br><small style="color:var(--error)">SKU not found — remove or fix</small>`;
      return `
        <div style="display:grid;grid-template-columns:32px 1fr 80px auto auto;gap:.5rem;align-items:center;padding:.5rem;background:var(--bg);border-radius:4px;">
          ${thumb}
          <div style="font-size:.85rem;">${meta}</div>
          <input type="number" data-bundle-idx="${i}" data-bundle-field="qty" value="${it.qty}" min="1" max="20" style="font-size:.85rem;" />
          <span style="font-size:.85rem;color:var(--gold);font-weight:600;white-space:nowrap;">$${lineTotal.toFixed(2)}</span>
          <button type="button" data-bundle-remove="${i}" class="filter-btn" style="font-size:.75rem;padding:.25rem .5rem;color:var(--error);border-color:var(--error);">×</button>
        </div>
      `;
    }).join('');
    // 显示总价 + 节省提示
    const bundlePriceField = parseFloat(document.querySelector('input[name=price]').value) || 0;
    const savings = listTotal - bundlePriceField;
    if (listTotal > 0) {
      bList.insertAdjacentHTML('beforeend', `
        <p class="muted small" style="margin:.5rem 0 0;padding:.5rem;background:var(--bg);border-radius:4px;text-align:right;">
          组件原价合计:<strong style="color:var(--text)">$${listTotal.toFixed(2)}</strong>
          ${bundlePriceField > 0 ? ` · 套装价:<strong style="color:var(--gold)">$${bundlePriceField.toFixed(2)}</strong>` : ''}
          ${savings > 0 ? ` · 节省:<strong style="color:var(--success,#2c9)">$${savings.toFixed(2)}</strong>` : ''}
        </p>
      `);
    }
    bHidden.value = JSON.stringify(bundlePicked);
  }

  bAdd.addEventListener('click', () => {
    const sku = bSelect.value;
    const qty = parseInt(bQty.value, 10) || 1;
    if (!sku) return;
    const existing = bundlePicked.find(x => x.sku === sku);
    if (existing) {
      existing.qty += qty;
    } else {
      bundlePicked.push({ sku, qty });
    }
    bSelect.value = '';
    bQty.value = 1;
    renderBundlePicked();
  });

  bList.addEventListener('input', (e) => {
    const t = e.target.closest('input[data-bundle-field=qty]');
    if (!t) return;
    const i = parseInt(t.dataset.bundleIdx, 10);
    bundlePicked[i].qty = Math.max(1, parseInt(t.value, 10) || 1);
    bHidden.value = JSON.stringify(bundlePicked);
  });
  bList.addEventListener('click', (e) => {
    const r = e.target.closest('[data-bundle-remove]');
    if (!r) return;
    const i = parseInt(r.dataset.bundleRemove, 10);
    bundlePicked.splice(i, 1);
    renderBundlePicked();
  });
  // 价格字段变化时,重算保存提示
  document.querySelector('input[name=price]').addEventListener('input', () => {
    if (document.getElementById('p-bundle-fieldset').style.display !== 'none') renderBundlePicked();
  });

  document.getElementById('save-btn').addEventListener('click', async () => {
    const id = document.getElementById('p-id').value;
    const data = Object.fromEntries(new FormData(form).entries());
    data.is_active = data.is_active ? 1 : 0;
    data.is_bundle = data.is_bundle ? 1 : 0;
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
