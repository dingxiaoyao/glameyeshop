<?php $pageTitle = 'Products'; $activeNav = 'products'; require __DIR__ . '/_layout.php'; ?>
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
  <h1 style="margin:0;">💄 <?= htmlspecialchars(t('product_management')) ?></h1>
  <button id="new-product-btn" class="button button-primary">+ <?= htmlspecialchars(t('add_product')) ?></button>
</div>

<div class="filter-bar">
  <button class="filter-btn active" data-cat=""><?= htmlspecialchars(t('all')) ?></button>
  <button class="filter-btn" data-cat="mink">Mink</button>
  <button class="filter-btn" data-cat="faux">Faux Mink</button>
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
        <label><span class="label-text"><?= htmlspecialchars(t('image_url')) ?></span>
          <input type="text" name="image_url" placeholder="/images/products/xxx.jpg" />
        </label>
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
    const rows = list.map((p) => `
      <tr style="opacity: ${p.is_active == 1 ? 1 : 0.5}">
        <td><img src="..${escape(p.image_url)}" style="width:48px; height:36px; object-fit:cover; border-radius:4px;" /></td>
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
      const j = await r.json();
      allProducts = j.products || []; render();
    } catch (e) {
      container.innerHTML = '<p style="color:var(--error)">' + T.load_failed + '</p>';
    }
  }

  function openModal(p = null) {
    fb.textContent = ''; fb.className = 'form-feedback';
    form.reset();
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
      form.image_url.value = p.image_url || '';
      form.stock.value = p.stock;
      form.sort_order.value = p.sort_order;
      form.is_active.checked = p.is_active == 1;
    } else {
      document.getElementById('modal-title').textContent = T.add_product;
      document.getElementById('p-id').value = '';
    }
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
