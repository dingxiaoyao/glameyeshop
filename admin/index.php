<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/i18n.php';
requireAdminAuth();
$lang = adminLang();
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'zh' ? 'zh-CN' : 'en' ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex,nofollow" />
  <title>GlamEye <?= htmlspecialchars(t('admin_panel')) ?></title>
  <link rel="icon" href="../favicon.ico" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display&family=Inter:wght@300;400;500;600;700&display=swap" />
  <link rel="stylesheet" href="../css/styles.css" />
  <style>
    .admin-table { width:100%; border-collapse:collapse; font-size:.85rem; background:var(--black-card); border-radius:12px; overflow:hidden; }
    .admin-table th { background:var(--black-soft); padding:.75rem; text-align:left; border-bottom:1px solid var(--border); color:var(--gold); font-family:inherit; font-weight:600; letter-spacing:1px; text-transform:uppercase; font-size:.75rem; }
    .admin-table td { padding:.75rem; border-bottom:1px solid var(--border-soft); vertical-align:top; color:var(--cream); }
    .admin-table tr:hover { background:rgba(212,169,85,0.05); }
    .status-select { padding:.4rem; border-radius:4px; border:1px solid var(--border); background:var(--black); color:var(--cream); font-size:.8rem; }
    .status-select:focus { outline: none; border-color: var(--gold); }
    .filter-bar { display:flex; gap:.5rem; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; }
    .filter-btn { padding:.5rem 1rem; background:var(--black-card); border:1px solid var(--border); color:var(--cream); border-radius:4px; cursor:pointer; font-size:.8rem; letter-spacing:1px; text-transform:uppercase; }
    .filter-btn.active { background:var(--gold); color:var(--black); border-color:var(--gold); }
    .stat-card { background:var(--black-card); padding:1.5rem; border-radius:12px; border:1px solid var(--border); }
    .stat-num { font-size:2rem; font-weight:700; color:var(--gold); font-family: 'Playfair Display', serif; }
    .stat-label { color:var(--text-muted); font-size:.8rem; letter-spacing:1px; text-transform:uppercase; }
    .lang-switch { display:inline-flex; gap:.25rem; }
    .lang-switch a { padding:.25rem .5rem; border:1px solid var(--border); border-radius:4px; color:var(--cream); font-size:.75rem; }
    .lang-switch a.active { background:var(--gold); color:var(--black); border-color:var(--gold); }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container">
      <a href="../" class="brand">
        <img src="../images/logo.png" alt="GlamEye" class="brand-logo" width="160" height="36" />
        <span style="margin-left:.75rem; color:var(--gold); font-size:.85rem; letter-spacing:2px; text-transform:uppercase;">Admin</span>
      </a>
      <nav class="site-nav">
        <span class="lang-switch">
          <a href="?lang=en" class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
          <a href="?lang=zh" class="<?= $lang === 'zh' ? 'active' : '' ?>">中</a>
        </span>
        <span style="color:var(--text-muted); font-size:.85rem;">
          👤 <?= htmlspecialchars($_SERVER['PHP_AUTH_USER'] ?? 'admin') ?>
        </span>
        <a href="../">← <?= htmlspecialchars(t('back_to_site')) ?></a>
      </nav>
    </div>
  </header>

  <main class="container" style="padding: 2rem 1rem;">
    <h1 style="color: var(--cream);">📦 <?= htmlspecialchars(t('order_management')) ?></h1>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin:1.5rem 0;">
      <div class="stat-card"><div class="stat-num" id="stat-total">-</div><div class="stat-label"><?= htmlspecialchars(t('total_orders')) ?></div></div>
      <div class="stat-card"><div class="stat-num" id="stat-pending">-</div><div class="stat-label"><?= htmlspecialchars(t('pending')) ?></div></div>
      <div class="stat-card"><div class="stat-num" id="stat-paid">-</div><div class="stat-label"><?= htmlspecialchars(t('paid')) ?></div></div>
      <div class="stat-card"><div class="stat-num" id="stat-revenue">-</div><div class="stat-label"><?= htmlspecialchars(t('total_revenue')) ?></div></div>
    </div>

    <div class="filter-bar">
      <span><?= htmlspecialchars(t('filter_status')) ?>:</span>
      <button class="filter-btn active" data-filter=""><?= htmlspecialchars(t('all')) ?></button>
      <button class="filter-btn" data-filter="pending"><?= htmlspecialchars(t('pending')) ?></button>
      <button class="filter-btn" data-filter="paid"><?= htmlspecialchars(t('paid')) ?></button>
      <button class="filter-btn" data-filter="processing"><?= htmlspecialchars(t('processing')) ?></button>
      <button class="filter-btn" data-filter="shipped"><?= htmlspecialchars(t('shipped')) ?></button>
      <button class="filter-btn" data-filter="delivered"><?= htmlspecialchars(t('delivered')) ?></button>
      <button class="filter-btn" data-filter="cancelled"><?= htmlspecialchars(t('cancelled')) ?></button>
      <button id="refresh-btn" class="filter-btn" style="margin-left:auto;">🔄 <?= htmlspecialchars(t('refresh')) ?></button>
    </div>

    <div id="orders-container" style="overflow-x:auto;">
      <p class="muted"><?= htmlspecialchars(t('loading')) ?></p>
    </div>
  </main>

  <script>
    const T = <?= tjs() ?>;
    (function () {
      const container = document.getElementById('orders-container');
      let allowedStatuses = [];
      let currentFilter = '';

      function escape(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) =>
          ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
      }

      function renderTable(orders) {
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
            <td>${escape(o.created_at)}</td>
            <td><strong>${escape(o.customer_name)}</strong><br>
                <small class="muted">${escape(o.email)}</small><br>
                <small class="muted">${escape(o.phone)}</small></td>
            <td>${items}</td>
            <td><strong>$${escape(o.amount)}</strong></td>
            <td>${escape(o.payment_method)}</td>
            <td><small class="muted">${addr || '-'}</small></td>
            <td><select class="status-select" data-id="${o.id}">${opts}</select></td>
          </tr>`;
        }).join('');
        container.innerHTML = `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
      }

      function updateStats(orders) {
        document.getElementById('stat-total').textContent = orders.length;
        document.getElementById('stat-pending').textContent = orders.filter(o => o.status === 'pending').length;
        document.getElementById('stat-paid').textContent = orders.filter(o => ['paid','shipped','delivered'].includes(o.status)).length;
        const rev = orders.filter(o => ['paid','shipped','delivered'].includes(o.status)).reduce((s, o) => s + Number(o.amount), 0);
        document.getElementById('stat-revenue').textContent = '$' + rev.toFixed(2);
      }

      async function load() {
        container.innerHTML = '<p class="muted">' + T.loading + '</p>';
        try {
          const url = '../api/get-orders.php' + (currentFilter ? '?status=' + currentFilter : '');
          const r = await fetch(url, { credentials: 'include' });
          if (!r.ok) throw new Error('HTTP ' + r.status);
          const j = await r.json();
          allowedStatuses = j.allowed_statuses || [];
          updateStats(j.orders || []);
          renderTable(j.orders || []);
        } catch (e) {
          container.innerHTML = `<p style="color:var(--error);">${T.load_failed}: ${e.message}</p>`;
        }
      }

      container.addEventListener('change', async (e) => {
        if (!e.target.matches('.status-select')) return;
        const id = e.target.dataset.id;
        const newStatus = e.target.value;
        e.target.disabled = true;
        try {
          const r = await fetch('../api/update-order-status.php', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: id, status: newStatus }),
          });
          const j = await r.json();
          if (!j.success) throw new Error(j.error || 'failed');
        } catch (err) { alert('Update failed: ' + err.message); load(); }
        finally { e.target.disabled = false; }
      });

      document.querySelectorAll('.filter-btn[data-filter]').forEach((b) => {
        b.addEventListener('click', () => {
          document.querySelectorAll('.filter-btn[data-filter]').forEach(x => x.classList.remove('active'));
          b.classList.add('active'); currentFilter = b.dataset.filter; load();
        });
      });
      document.getElementById('refresh-btn').addEventListener('click', load);
      load();
    })();
  </script>
</body>
</html>
