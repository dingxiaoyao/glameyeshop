<?php $pageTitle = 'Orders'; $activeNav = 'orders'; require __DIR__ . '/_layout.php'; ?>
<h1>📦 <?= htmlspecialchars(t('order_management')) ?></h1>

<div class="filter-bar">
  <span class="muted small"><?= htmlspecialchars(t('filter_status')) ?>:</span>
  <button class="filter-btn active" data-filter=""><?= htmlspecialchars(t('all')) ?></button>
  <button class="filter-btn" data-filter="pending"><?= htmlspecialchars(t('pending')) ?></button>
  <button class="filter-btn" data-filter="paid"><?= htmlspecialchars(t('paid')) ?></button>
  <button class="filter-btn" data-filter="processing"><?= htmlspecialchars(t('processing')) ?></button>
  <button class="filter-btn" data-filter="shipped"><?= htmlspecialchars(t('shipped')) ?></button>
  <button class="filter-btn" data-filter="delivered"><?= htmlspecialchars(t('delivered')) ?></button>
  <button class="filter-btn" data-filter="cancelled"><?= htmlspecialchars(t('cancelled')) ?></button>
  <button id="refresh-btn" class="filter-btn" style="margin-left: auto;">🔄 <?= htmlspecialchars(t('refresh')) ?></button>
</div>

<div class="admin-card" style="overflow-x: auto;">
  <div id="orders-container"><p class="muted"><?= htmlspecialchars(t('loading')) ?></p></div>
</div>

<script>
(function () {
  const container = document.getElementById('orders-container');
  let allowedStatuses = []; let currentFilter = '';
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function money(n) { return '$' + Number(n || 0).toFixed(2); }
  const params = new URLSearchParams(location.search);
  if (params.get('status')) currentFilter = params.get('status');

  function render(orders) {
    if (!orders.length) { container.innerHTML = '<p class="muted">' + T.no_orders + '</p>'; return; }
    const head = `<thead><tr>
      <th>${T.order_id}</th><th>${T.date}</th><th>${T.customer}</th>
      <th>${T.items}</th><th>${T.amount}</th><th>${T.payment}</th>
      <th>${T.address}</th><th>${T.status}</th>
    </tr></thead>`;
    const rows = orders.map((o) => {
      const items = (o.items || []).length
        ? o.items.map(i => `${escape(i.product_name)} × ${i.quantity}`).join('<br>')
        : `${escape(o.product_name)} × ${o.quantity}`;
      const addr = `${escape(o.address_line || '')} ${escape(o.city || '')} ${escape(o.state || '')} ${escape(o.postal_code || '')}`.trim();
      const opts = allowedStatuses.map((s) =>
        `<option value="${s}"${s === o.status ? ' selected' : ''}>${T[s] || s}</option>`).join('');
      return `<tr>
        <td><strong style="color:var(--gold)">#${o.id}</strong></td>
        <td><small>${escape(o.created_at)}</small></td>
        <td><strong>${escape(o.customer_name)}</strong><br>
            <small class="muted">${escape(o.email)}</small></td>
        <td>${items}</td>
        <td>${money(o.amount)}</td>
        <td><small>${escape(o.payment_method)}</small></td>
        <td><small class="muted">${addr || '-'}</small></td>
        <td><select class="status-select" data-id="${o.id}">${opts}</select></td>
      </tr>`;
    }).join('');
    container.innerHTML = `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
  }

  async function load() {
    container.innerHTML = '<p class="muted">' + T.loading + '</p>';
    try {
      const url = '../api/get-orders.php' + (currentFilter ? '?status=' + currentFilter : '');
      const r = await fetch(url, { credentials: 'include' });
      const j = await r.json();
      allowedStatuses = j.allowed_statuses || [];
      render(j.orders || []);
    } catch (e) {
      container.innerHTML = `<p style="color:var(--error)">${T.load_failed}: ${e.message}</p>`;
    }
  }

  container.addEventListener('change', async (e) => {
    if (!e.target.matches('.status-select')) return;
    const id = e.target.dataset.id, status = e.target.value;
    e.target.disabled = true;
    try {
      const r = await fetch('../api/update-order-status.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: id, status }),
      });
      const j = await r.json();
      if (!j.success) throw new Error(j.error || 'failed');
    } catch (err) { alert('Update failed: ' + err.message); load(); }
    finally { e.target.disabled = false; }
  });

  document.querySelectorAll('.filter-btn[data-filter]').forEach((b) => {
    if (b.dataset.filter === currentFilter) {
      document.querySelectorAll('.filter-btn[data-filter]').forEach(x => x.classList.remove('active'));
      b.classList.add('active');
    }
    b.addEventListener('click', () => {
      document.querySelectorAll('.filter-btn[data-filter]').forEach(x => x.classList.remove('active'));
      b.classList.add('active'); currentFilter = b.dataset.filter; load();
    });
  });
  document.getElementById('refresh-btn').addEventListener('click', load);
  load();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
