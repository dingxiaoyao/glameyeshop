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
      <th>Tracking</th><th>${T.status}</th><th></th>
    </tr></thead>`;
    const rows = orders.map((o) => {
      const items = (o.items || []).length
        ? o.items.map(i => `${escape(i.product_name)} × ${i.quantity}`).join('<br>')
        : `${escape(o.product_name)} × ${o.quantity}`;
      const opts = allowedStatuses.map((s) =>
        `<option value="${s}"${s === o.status ? ' selected' : ''}>${T[s] || s}</option>`).join('');
      const tracking = o.tracking_number
        ? `<small style="color:var(--gold);">${escape(o.carrier || '')} ${escape(o.tracking_number)}</small>`
        : '<small class="muted">—</small>';
      return `<tr style="${o.is_test == 1 ? 'background: rgba(247,185,85,0.05);' : ''}">
        <td><strong style="color:var(--gold)">#${o.id}</strong>${o.is_test == 1 ? '<br><span class="status-badge status-pending" style="font-size:9px;">TEST</span>' : ''}</td>
        <td><small>${escape(o.created_at)}</small></td>
        <td><strong>${escape(o.customer_name)}</strong><br>
            <small class="muted">${escape(o.email)}</small></td>
        <td>${items}</td>
        <td>${money(o.amount)}</td>
        <td><small>${escape(o.payment_method)}</small></td>
        <td>${tracking}</td>
        <td><select class="status-select" data-id="${o.id}">${opts}</select></td>
        <td><button class="filter-btn track-btn" data-order='${escape(JSON.stringify(o))}'>📦 Tracking</button></td>
      </tr>`;
    }).join('');
    container.innerHTML = `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
    container.querySelectorAll('.track-btn').forEach(b => b.addEventListener('click', () => openTrackingModal(JSON.parse(b.dataset.order))));
  }

  function openTrackingModal(o) {
    let modalEl = document.getElementById('tracking-modal');
    if (!modalEl) {
      modalEl = document.createElement('div');
      modalEl.id = 'tracking-modal';
      modalEl.className = 'modal-backdrop';
      modalEl.innerHTML = `
        <div class="modal" style="max-width: 700px;">
          <div class="modal-header">
            <h3 id="tm-title">Tracking</h3>
            <button class="modal-close" onclick="document.getElementById('tracking-modal').style.display='none'">×</button>
          </div>
          <div class="modal-body" id="tm-body"></div>
        </div>`;
      document.body.appendChild(modalEl);
      modalEl.addEventListener('click', e => { if (e.target === modalEl) modalEl.style.display = 'none'; });
    }
    document.getElementById('tm-title').textContent = `📦 Order #${o.id} Tracking`;
    document.getElementById('tm-body').innerHTML = `
      <div class="form-group">
        <div class="form-row">
          <label><span class="label-text">Carrier</span>
            <select id="tm-carrier">
              <option value="">— Select —</option>
              <option value="USPS"  ${o.carrier === 'USPS' ? 'selected' : ''}>USPS</option>
              <option value="UPS"   ${o.carrier === 'UPS'  ? 'selected' : ''}>UPS</option>
              <option value="FedEx" ${o.carrier === 'FedEx' ? 'selected' : ''}>FedEx</option>
              <option value="DHL"   ${o.carrier === 'DHL'  ? 'selected' : ''}>DHL</option>
              <option value="OTHER" ${o.carrier === 'OTHER' ? 'selected' : ''}>Other</option>
            </select>
          </label>
          <label><span class="label-text">Tracking Number</span>
            <input type="text" id="tm-tracknum" value="${escape(o.tracking_number || '')}" />
          </label>
        </div>
        <label><span class="label-text">Estimated Delivery (optional)</span>
          <input type="date" id="tm-estimated" value="${escape(o.estimated_delivery || '')}" />
        </label>
        <button class="button button-primary button-sm" id="tm-save-info">💾 Save Carrier Info</button>
        <hr style="border:none; border-top:1px solid var(--border); margin: 1.5rem 0;">
        <h4 style="color:var(--cream); margin-bottom:.75rem;">Add Tracking Event</h4>
        <div class="form-row">
          <label><span class="label-text">Status</span>
            <select id="tm-event-status">
              <option value="paid">Paid</option>
              <option value="processing">Processing</option>
              <option value="shipped" selected>Shipped</option>
              <option value="in_transit">In Transit</option>
              <option value="out_for_delivery">Out for Delivery</option>
              <option value="delivered">Delivered</option>
              <option value="exception">Exception</option>
              <option value="returned">Returned</option>
            </select>
          </label>
          <label><span class="label-text">Location</span>
            <input type="text" id="tm-event-loc" placeholder="e.g. Los Angeles, CA" />
          </label>
        </div>
        <label><span class="label-text">Description</span>
          <input type="text" id="tm-event-desc" placeholder="e.g. Package picked up by carrier" />
        </label>
        <button class="button button-primary button-sm" id="tm-add-event">+ Add Event</button>
        <hr style="border:none; border-top:1px solid var(--border); margin: 1.5rem 0;">
        <h4 style="color:var(--cream); margin-bottom:.75rem;">Event History</h4>
        <div id="tm-events"><p class="muted">Loading…</p></div>
      </div>`;
    modalEl.style.display = 'flex';

    async function refreshEvents() {
      const r = await fetch('../api/admin-order-tracking.php?order_id=' + o.id, { credentials:'include' });
      const j = await r.json();
      const events = j.events || [];
      document.getElementById('tm-events').innerHTML = events.length
        ? events.map(e => `
          <div style="padding:.65rem; background:var(--bg); border-left:3px solid var(--gold); border-radius:4px; margin-bottom:.5rem;">
            <div><strong style="color:var(--gold);">${escape(e.status)}</strong> · <small class="muted">${escape(e.occurred_at)}</small></div>
            ${e.location ? '<div><small>📍 '+escape(e.location)+'</small></div>' : ''}
            ${e.description ? '<div><small class="muted">'+escape(e.description)+'</small></div>' : ''}
          </div>`).join('')
        : '<p class="muted">No tracking events yet.</p>';
    }
    refreshEvents();

    document.getElementById('tm-save-info').onclick = async () => {
      const body = {
        order_id: o.id,
        carrier: document.getElementById('tm-carrier').value,
        tracking_number: document.getElementById('tm-tracknum').value,
        estimated_delivery: document.getElementById('tm-estimated').value,
      };
      const r = await fetch('../api/admin-order-tracking.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
      const j = await r.json();
      if (j.success) { alert('Saved'); load(); } else alert(j.error || 'failed');
    };
    document.getElementById('tm-add-event').onclick = async () => {
      const body = {
        order_id: o.id,
        event_status: document.getElementById('tm-event-status').value,
        event_location: document.getElementById('tm-event-loc').value,
        event_description: document.getElementById('tm-event-desc').value,
      };
      const r = await fetch('../api/admin-order-tracking.php', { method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
      const j = await r.json();
      if (j.success) {
        document.getElementById('tm-event-loc').value = '';
        document.getElementById('tm-event-desc').value = '';
        refreshEvents(); load();
      } else alert(j.error || 'failed');
    };
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
