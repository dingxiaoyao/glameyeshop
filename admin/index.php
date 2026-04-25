<?php
require_once __DIR__ . '/../api/config.php';
requireAdminAuth();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex,nofollow" />
  <title>GlamEye 管理面板</title>
  <link rel="icon" href="../favicon.ico" sizes="any" />
  <link rel="stylesheet" href="../css/styles.css" />
  <style>
    .admin-table { width:100%; border-collapse:collapse; font-size:.9rem; background:#fff; }
    .admin-table th { background:#f3f4f6; padding:.6rem; text-align:left; border-bottom:2px solid var(--border); }
    .admin-table td { padding:.6rem; border-bottom:1px solid var(--border); vertical-align:top; }
    .admin-table tr:hover { background:#fafbfc; }
    .status-select { padding:.3rem; border-radius:6px; border:1px solid var(--border); font-size:.85rem; }
    .status-pending { color:#f59e0b; }
    .status-paid { color:#16a34a; }
    .status-shipped { color:#2563eb; }
    .status-completed { color:#16a34a; font-weight:600; }
    .status-cancelled, .status-refunded { color:#dc2626; }
    .filter-bar { display:flex; gap:.5rem; align-items:center; margin-bottom:1rem; flex-wrap:wrap; }
    .filter-btn { padding:.4rem 1rem; background:#fff; border:1px solid var(--border); border-radius:6px; cursor:pointer; font-size:.85rem; }
    .filter-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
    .stat-card { background:#fff; padding:1.25rem; border-radius:12px; border:1px solid var(--border); }
    .stat-num { font-size:1.75rem; font-weight:700; color:var(--primary); }
    .stat-label { color:var(--text-muted); font-size:.85rem; }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container">
      <a href="../index.html" class="brand">
        <img src="../images/logo.png" alt="GlamEye" class="brand-logo" width="160" height="40" />
        <span style="margin-left:.75rem; color:var(--text-muted); font-size:.9rem;">Admin</span>
      </a>
      <nav class="site-nav">
        <span style="color:var(--text-muted); font-size:.9rem;">
          👤 <?= htmlspecialchars($_SERVER['PHP_AUTH_USER'] ?? 'admin', ENT_QUOTES, 'UTF-8') ?>
        </span>
        <a href="../index.html">返回前台</a>
      </nav>
    </div>
  </header>

  <main class="container" style="padding: 2rem 1rem;">
    <h1>📦 订单管理</h1>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin:1.5rem 0;">
      <div class="stat-card"><div class="stat-num" id="stat-total">-</div><div class="stat-label">总订单</div></div>
      <div class="stat-card"><div class="stat-num" id="stat-pending">-</div><div class="stat-label">待支付</div></div>
      <div class="stat-card"><div class="stat-num" id="stat-paid">-</div><div class="stat-label">已支付</div></div>
      <div class="stat-card"><div class="stat-num" id="stat-revenue">-</div><div class="stat-label">总销售额</div></div>
    </div>

    <div class="filter-bar">
      <span>状态筛选：</span>
      <button class="filter-btn active" data-filter="">全部</button>
      <button class="filter-btn" data-filter="pending">待支付</button>
      <button class="filter-btn" data-filter="paid">已支付</button>
      <button class="filter-btn" data-filter="processing">处理中</button>
      <button class="filter-btn" data-filter="shipped">已发货</button>
      <button class="filter-btn" data-filter="completed">已完成</button>
      <button class="filter-btn" data-filter="cancelled">已取消</button>
      <button id="refresh-btn" class="filter-btn" style="margin-left:auto;">🔄 刷新</button>
    </div>

    <div id="orders-container" style="overflow-x:auto;">
      <p>加载中...</p>
    </div>
  </main>

  <script>
    (function () {
      const container = document.getElementById('orders-container');
      let allowedStatuses = [];
      let currentFilter = '';

      function escape(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) =>
          ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])
        );
      }

      function statusClass(s) {
        return 'status-' + String(s).replace(/[^a-z]/g, '');
      }

      function renderTable(orders) {
        if (!orders.length) {
          container.innerHTML = '<p>暂无订单。</p>';
          return;
        }
        const head = `
          <thead><tr>
            <th>#</th><th>时间</th><th>客户</th><th>商品</th>
            <th>金额</th><th>支付</th><th>地址</th><th>状态</th>
          </tr></thead>`;
        const rows = orders.map((o) => {
          const items = (o.items || []).length
            ? o.items.map(i => `${escape(i.product_name)} × ${i.quantity}`).join('<br>')
            : `${escape(o.product_name)} × ${o.quantity}`;
          const addr = `${escape(o.address_line || '')} ${escape(o.city || '')} ${escape(o.postal_code || '')}`.trim();
          const statusOpts = allowedStatuses.map(s =>
            `<option value="${s}"${s === o.status ? ' selected' : ''}>${s}</option>`
          ).join('');
          return `<tr>
            <td>#${o.id}</td>
            <td>${escape(o.created_at)}</td>
            <td><strong>${escape(o.customer_name)}</strong><br>
                <small>${escape(o.email)}</small><br>
                <small>${escape(o.phone)}</small></td>
            <td>${items}</td>
            <td><strong>¥${escape(o.amount)}</strong></td>
            <td>${escape(o.payment_method)}</td>
            <td><small>${addr || '-'}</small></td>
            <td>
              <select class="status-select ${statusClass(o.status)}" data-id="${o.id}">${statusOpts}</select>
            </td>
          </tr>`;
        }).join('');
        container.innerHTML = `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
      }

      function updateStats(orders) {
        document.getElementById('stat-total').textContent = orders.length;
        document.getElementById('stat-pending').textContent = orders.filter(o => o.status === 'pending').length;
        document.getElementById('stat-paid').textContent = orders.filter(o => ['paid','shipped','completed'].includes(o.status)).length;
        const rev = orders.filter(o => ['paid','shipped','completed'].includes(o.status)).reduce((s, o) => s + Number(o.amount), 0);
        document.getElementById('stat-revenue').textContent = '¥' + rev.toFixed(2);
      }

      async function load() {
        container.innerHTML = '<p>加载中...</p>';
        try {
          const url = '../api/get-orders.php' + (currentFilter ? '?status=' + currentFilter : '');
          const r = await fetch(url, { credentials: 'include' });
          if (!r.ok) throw new Error('HTTP ' + r.status);
          const j = await r.json();
          allowedStatuses = j.allowed_statuses || [];
          const orders = j.orders || [];
          updateStats(orders);
          renderTable(orders);
        } catch (e) {
          container.innerHTML = `<p style="color:var(--error);">加载失败：${e.message}</p>`;
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
          if (!j.success) throw new Error(j.error || '更新失败');
          e.target.className = 'status-select ' + statusClass(newStatus);
        } catch (err) {
          alert('更新失败：' + err.message);
          load();
        } finally {
          e.target.disabled = false;
        }
      });

      document.querySelectorAll('.filter-btn[data-filter]').forEach((b) => {
        b.addEventListener('click', () => {
          document.querySelectorAll('.filter-btn[data-filter]').forEach(x => x.classList.remove('active'));
          b.classList.add('active');
          currentFilter = b.dataset.filter;
          load();
        });
      });
      document.getElementById('refresh-btn').addEventListener('click', load);

      load();
    })();
  </script>
</body>
</html>
