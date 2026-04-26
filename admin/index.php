<?php $pageTitle = 'Dashboard'; $activeNav = 'dashboard'; require __DIR__ . '/_layout.php'; ?>
<h1>📊 <?= htmlspecialchars(t('dashboard')) ?></h1>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-label"><?= htmlspecialchars(t('today_sales')) ?></div>
    <div class="kpi-value" id="kpi-today-rev">—</div>
    <div class="kpi-trend" id="kpi-today-cnt">— <?= htmlspecialchars(t('orders')) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label"><?= htmlspecialchars(t('month_sales')) ?></div>
    <div class="kpi-value" id="kpi-month-rev">—</div>
    <div class="kpi-trend" id="kpi-month-cnt">— <?= htmlspecialchars(t('orders')) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label"><?= htmlspecialchars(t('total_revenue')) ?></div>
    <div class="kpi-value" id="kpi-total-rev">—</div>
    <div class="kpi-trend" id="kpi-total-cnt">— <?= htmlspecialchars(t('orders')) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label"><?= htmlspecialchars(t('total_customers')) ?></div>
    <div class="kpi-value" id="kpi-customers">—</div>
    <div class="kpi-trend" id="kpi-subs">— <?= htmlspecialchars(t('subscribers')) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label"><?= htmlspecialchars(t('pending_orders')) ?></div>
    <div class="kpi-value" id="kpi-pending">—</div>
    <div class="kpi-trend"><a href="orders.php?status=pending"><?= htmlspecialchars(t('orders')) ?> →</a></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label"><?= htmlspecialchars(t('low_stock')) ?></div>
    <div class="kpi-value" id="kpi-lowstock" style="color: var(--warn);">—</div>
    <div class="kpi-trend"><a href="products.php"><?= htmlspecialchars(t('products')) ?> →</a></div>
  </div>
</div>

<div class="admin-card">
  <h3><?= htmlspecialchars(t('sales_30d')) ?></h3>
  <div class="chart-bars" id="sales-chart"></div>
  <div class="chart-labels" id="sales-labels"></div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
  <div class="admin-card">
    <h3><?= htmlspecialchars(t('top_products')) ?></h3>
    <div id="top-products"></div>
  </div>
  <div class="admin-card">
    <h3><?= htmlspecialchars(t('low_stock_alert')) ?></h3>
    <div id="low-stock-list"></div>
  </div>
</div>

<div class="admin-card">
  <h3><?= htmlspecialchars(t('recent_orders')) ?></h3>
  <div id="recent-orders"></div>
</div>

<script>
(async () => {
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function money(n) { return '$' + Number(n || 0).toFixed(2); }
  try {
    const r = await fetch('../api/admin-stats.php', { credentials: 'include' });
    const j = await r.json();
    const k = j.kpi;
    document.getElementById('kpi-today-rev').textContent  = money(k.today_revenue);
    document.getElementById('kpi-today-cnt').textContent  = k.today_orders + ' ' + T.orders;
    document.getElementById('kpi-month-rev').textContent  = money(k.month_revenue);
    document.getElementById('kpi-month-cnt').textContent  = k.month_orders + ' ' + T.orders;
    document.getElementById('kpi-total-rev').textContent  = money(k.total_revenue);
    document.getElementById('kpi-total-cnt').textContent  = k.total_orders + ' ' + T.orders;
    document.getElementById('kpi-customers').textContent  = k.total_customers;
    document.getElementById('kpi-subs').textContent       = k.newsletter_subs + ' ' + T.subscribers;
    document.getElementById('kpi-pending').textContent    = k.pending_orders;
    document.getElementById('kpi-lowstock').textContent   = k.low_stock;

    // Sales chart
    const sales = j.sales_30d || [];
    const maxRev = Math.max(...sales.map((d) => Number(d.revenue)), 1);
    const chartEl = document.getElementById('sales-chart');
    const labelsEl = document.getElementById('sales-labels');
    chartEl.innerHTML = '';
    labelsEl.innerHTML = '';
    if (sales.length === 0) {
      chartEl.innerHTML = '<div style="margin:auto; color:var(--text-muted)">No sales data yet</div>';
    } else {
      sales.forEach((d) => {
        const h = (Number(d.revenue) / maxRev * 100) + '%';
        const bar = document.createElement('div');
        bar.className = 'chart-bar';
        bar.style.height = h;
        bar.dataset.tooltip = `${d.d}: ${money(d.revenue)} · ${d.orders} ${T.orders}`;
        chartEl.appendChild(bar);
        const lbl = document.createElement('span');
        lbl.textContent = d.d.slice(5);
        labelsEl.appendChild(lbl);
      });
    }

    // Top products
    const tp = j.top_products || [];
    document.getElementById('top-products').innerHTML = tp.length
      ? '<table class="admin-table"><tbody>' + tp.map((p) =>
          `<tr><td>${escape(p.product_name)}</td><td style="text-align:right;">${p.qty} sold</td><td style="text-align:right; color:var(--gold);">${money(p.rev)}</td></tr>`
        ).join('') + '</tbody></table>'
      : '<p class="muted">No data yet.</p>';

    // Low stock
    const ls = j.low_stock || [];
    document.getElementById('low-stock-list').innerHTML = ls.length
      ? '<table class="admin-table"><tbody>' + ls.map((p) =>
          `<tr><td>${escape(p.name)}</td><td><small class="muted">${escape(p.sku)}</small></td><td style="text-align:right; color:var(--warn);"><strong>${p.stock}</strong></td></tr>`
        ).join('') + '</tbody></table>'
      : '<p class="muted">All stocked up ✓</p>';

    // Recent orders
    const ro = j.recent_orders || [];
    document.getElementById('recent-orders').innerHTML = ro.length
      ? '<table class="admin-table"><thead><tr><th>#</th><th>' + T.customer + '</th><th>' + T.amount + '</th><th>' + T.status + '</th><th>' + T.date + '</th></tr></thead><tbody>'
        + ro.map((o) => `<tr>
            <td><a href="orders.php">#${o.id}</a></td>
            <td>${escape(o.customer_name)}</td>
            <td>${money(o.amount)}</td>
            <td><span class="status-badge status-${escape(o.status)}">${T[o.status] || o.status}</span></td>
            <td><small class="muted">${escape(o.created_at)}</small></td>
          </tr>`).join('')
        + '</tbody></table>'
      : '<p class="muted">No orders yet.</p>';
  } catch (e) {
    document.querySelector('.admin-main').insertAdjacentHTML('beforeend',
      '<p style="color:var(--error)">Failed to load stats: ' + e.message + '</p>');
  }
})();
</script>
<!-- ============== System self-check (debug panel) ============== -->
<details class="admin-card" style="margin-top:2rem;">
  <summary style="cursor:pointer; font-family:var(--serif); font-size:1.1rem; color:var(--cream);">
    🔧 <?= $lang === 'zh' ? '系统自检 (出问题时点开)' : 'System Self-Check (open this when something is broken)' ?>
  </summary>
  <p class="muted small" style="margin-top:.75rem;">
    <?= $lang === 'zh'
      ? '调以下 API 检查响应。出问题时把结果截图发给开发者,能直接看到 HTTP 状态码 + 服务器返回的实际内容。'
      : 'Pings each API and shows the raw response. If something is broken, screenshot this so the developer sees the exact HTTP status + payload.' ?>
  </p>
  <div style="display:flex; gap:.5rem; flex-wrap:wrap; margin:1rem 0;">
    <button class="filter-btn" data-check="../api/admin-products.php">admin-products</button>
    <button class="filter-btn" data-check="../api/products.php">products (public)</button>
    <button class="filter-btn" data-check="../api/admin-settings.php">admin-settings</button>
    <button class="filter-btn" data-check="../api/admin-stats.php">admin-stats</button>
    <button class="filter-btn" data-check="../api/admin-customers.php">admin-customers</button>
    <button class="filter-btn" data-check="../api/admin-leads.php">admin-leads</button>
    <button class="filter-btn" data-check="../api/admin-test-email.php">admin-test-email</button>
    <button class="filter-btn" data-check="all" style="border-color:var(--gold); color:var(--gold);">▶ Run all</button>
  </div>
  <div id="diag-output" style="background:var(--bg); border:1px solid var(--border-soft); border-radius:6px; padding:1rem; font-family:ui-monospace,Menlo,monospace; font-size:.78rem; color:var(--text); max-height:480px; overflow:auto; white-space:pre-wrap;">Click a button above to test an endpoint.</div>
</details>

<script>
(() => {
  const out = document.getElementById('diag-output');
  function append(line) { out.textContent += line + '\n'; out.scrollTop = out.scrollHeight; }
  async function ping(url) {
    append('▶ GET ' + url);
    const t0 = Date.now();
    try {
      const r = await fetch(url, { credentials: 'include' });
      const dt = Date.now() - t0;
      const text = await r.text();
      const isJson = text.trim().startsWith('{') || text.trim().startsWith('[');
      append(`  HTTP ${r.status} (${dt}ms, ${text.length}B) ${isJson ? 'JSON ✓' : 'NON-JSON ⚠'}`);
      if (text.length > 500) append('  ' + text.slice(0, 500).replace(/\n/g, '\n  ') + '\n  ... [truncated]');
      else append('  ' + text.replace(/\n/g, '\n  '));
      append('');
    } catch (e) {
      append('  NETWORK ERROR: ' + (e.message || String(e)));
      append('');
    }
  }
  document.querySelectorAll('[data-check]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const url = btn.dataset.check;
      out.textContent = `[${new Date().toISOString()}]\n\n`;
      if (url === 'all') {
        for (const b of document.querySelectorAll('[data-check]:not([data-check="all"])')) {
          await ping(b.dataset.check);
        }
      } else {
        await ping(url);
      }
    });
  });
})();
</script>

<?php require __DIR__ . '/_footer.php'; ?>
