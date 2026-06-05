<?php $pageTitle = 'Customers'; $activeNav = 'customers'; require __DIR__ . '/_layout.php'; ?>
<h1>👥 <?= htmlspecialchars(t('customer_management')) ?></h1>

<div class="admin-card" style="margin-bottom:1rem;">
  <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
    <input type="search" id="cust-search" placeholder="🔍 Search email / name / phone…" style="flex:1;min-width:240px;padding:.5rem .85rem;" />
    <span id="cust-summary" class="muted small"></span>
  </div>
</div>

<div class="admin-card" style="overflow-x: auto;">
  <div id="customers-container"><p class="muted"><?= htmlspecialchars(t('loading')) ?></p></div>
  <div id="cust-pagination" style="margin-top:1rem;display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;"></div>
</div>

<script>
(() => {
  const container = document.getElementById('customers-container');
  const pagination = document.getElementById('cust-pagination');
  const searchInput = document.getElementById('cust-search');
  const summary = document.getElementById('cust-summary');
  let currentPage = 1;
  let searchQuery = '';
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function money(n) { return '$' + Number(n || 0).toFixed(2); }

  async function load() {
    try {
      const qs = new URLSearchParams({ page: currentPage, per_page: 50 });
      if (searchQuery) qs.set('search', searchQuery);
      const r = await fetch('../api/admin-customers.php?' + qs.toString(), { credentials: 'include' });
      const j = await r.json();
      render(j);
    } catch (e) {
      container.innerHTML = '<p style="color:var(--error)">Failed to load: ' + e.message + '</p>';
    }
  }

  function render(j) {
    const customers = j.customers || [];
    const p = j.pagination || {};
    summary.textContent = `${p.total || 0} customer(s) · page ${p.page || 1} of ${p.total_pages || 1}`;
    if (!customers.length) { container.innerHTML = '<p class="muted">No customers match.</p>'; pagination.innerHTML = ''; return; }
    const head = `<thead><tr>
      <th>#</th><th>${T.email}</th><th>${T.name}</th><th>${T.phone}</th>
      <th>OAuth</th><th>Test</th>
      <th>${T.orders_count}</th><th>${T.spent}</th><th>${T.subscribed}</th><th>${T.date}</th>
    </tr></thead>`;
    const rows = customers.map((c) => `
      <tr>
        <td><strong style="color:var(--gold)">#${c.id}</strong></td>
        <td>${escape(c.email)}${c.is_test_account == 1 ? ' <span class="status-badge status-pending">TEST</span>' : ''}</td>
        <td>${escape(c.first_name)} ${escape(c.last_name)}</td>
        <td><small class="muted">${escape(c.phone || '—')}</small></td>
        <td>${c.oauth_provider ? '<small style="color:var(--gold);">'+escape(c.oauth_provider)+'</small>' : '<small class="muted">password</small>'}</td>
        <td>
          <label style="cursor:pointer; display:inline-flex; align-items:center; gap:.25rem;">
            <input type="checkbox" class="test-toggle" data-id="${c.id}" ${c.is_test_account == 1 ? 'checked' : ''} />
            <span style="font-size:.7rem; color:var(--text-muted);">test</span>
          </label>
        </td>
        <td><strong>${c.order_count}</strong></td>
        <td style="color:var(--gold);"><strong>${money(c.total_spent)}</strong></td>
        <td>${c.is_subscribed == 1 ? '✓' : '—'}</td>
        <td><small class="muted">${escape(c.created_at)}</small></td>
      </tr>`).join('');
    container.innerHTML = `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
    container.querySelectorAll('.test-toggle').forEach(el => el.addEventListener('change', async (e) => {
      const r = await fetch('../api/admin-test-account.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: e.target.dataset.id, is_test_account: e.target.checked ? 1 : 0 }),
      });
      const j = await r.json();
      if (!j.success) { alert(j.error || 'failed'); e.target.checked = !e.target.checked; }
    }));

    // 分页按钮
    const tp = p.total_pages || 1;
    if (tp > 1) {
      const pages = [];
      pages.push(`<button class="filter-btn" data-p="${Math.max(1, p.page - 1)}" ${p.page <= 1 ? 'disabled' : ''}>← Prev</button>`);
      const start = Math.max(1, p.page - 3);
      const end = Math.min(tp, p.page + 3);
      for (let i = start; i <= end; i++) {
        pages.push(`<button class="filter-btn${i === p.page ? ' active' : ''}" data-p="${i}">${i}</button>`);
      }
      pages.push(`<button class="filter-btn" data-p="${Math.min(tp, p.page + 1)}" ${p.page >= tp ? 'disabled' : ''}>Next →</button>`);
      pagination.innerHTML = pages.join('');
      pagination.querySelectorAll('button[data-p]').forEach(b => b.addEventListener('click', () => {
        currentPage = parseInt(b.dataset.p, 10) || 1;
        load();
      }));
    } else {
      pagination.innerHTML = '';
    }
  }

  // search debounce
  let searchTimer;
  searchInput.addEventListener('input', (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      searchQuery = e.target.value.trim();
      currentPage = 1;
      load();
    }, 300);
  });

  load();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
