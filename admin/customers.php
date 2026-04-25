<?php $pageTitle = 'Customers'; $activeNav = 'customers'; require __DIR__ . '/_layout.php'; ?>
<h1>👥 <?= htmlspecialchars(t('customer_management')) ?></h1>

<div class="admin-card" style="overflow-x: auto;">
  <div id="customers-container"><p class="muted"><?= htmlspecialchars(t('loading')) ?></p></div>
</div>

<script>
(async () => {
  const container = document.getElementById('customers-container');
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function money(n) { return '$' + Number(n || 0).toFixed(2); }
  try {
    const r = await fetch('../api/admin-customers.php', { credentials: 'include' });
    const j = await r.json();
    const customers = j.customers || [];
    if (!customers.length) { container.innerHTML = '<p class="muted">No customers yet.</p>'; return; }
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
  } catch (e) {
    container.innerHTML = '<p style="color:var(--error)">Failed to load: ' + e.message + '</p>';
  }
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
