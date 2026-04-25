<?php $pageTitle = 'Wholesale Leads'; $activeNav = 'leads'; require __DIR__ . '/_layout.php'; ?>
<h1>✉️ <?= htmlspecialchars(t('lead_management')) ?></h1>

<div class="admin-card" style="overflow-x: auto;">
  <div id="leads-container"><p class="muted"><?= htmlspecialchars(t('loading')) ?></p></div>
</div>

<script>
(async () => {
  const container = document.getElementById('leads-container');
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  try {
    const r = await fetch('../api/admin-leads.php', { credentials: 'include' });
    const j = await r.json();
    const leads = j.leads || [];
    if (!leads.length) { container.innerHTML = '<p class="muted">No leads yet.</p>'; return; }
    const head = `<thead><tr>
      <th>#</th><th>${T.company}</th><th>${T.customer}</th><th>${T.email}</th>
      <th>${T.phone}</th><th>${T.message}</th><th>${T.date}</th>
    </tr></thead>`;
    const rows = leads.map((l) => `
      <tr>
        <td><strong style="color:var(--gold)">#${l.id}</strong></td>
        <td><strong>${escape(l.company)}</strong></td>
        <td>${escape(l.contact)}</td>
        <td><a href="mailto:${escape(l.email)}">${escape(l.email)}</a></td>
        <td><small>${escape(l.phone || '—')}</small></td>
        <td><small class="muted" style="display:block; max-width:300px;">${escape(l.message || '—')}</small></td>
        <td><small class="muted">${escape(l.created_at)}</small></td>
      </tr>`).join('');
    container.innerHTML = `<table class="admin-table">${head}<tbody>${rows}</tbody></table>`;
  } catch (e) {
    container.innerHTML = '<p style="color:var(--error)">Failed: ' + e.message + '</p>';
  }
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
