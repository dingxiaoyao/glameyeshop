<?php $pageTitle = 'Reviews'; $activeNav = 'reviews'; require __DIR__ . '/_layout.php'; ?>
<style>
  .rv-toolbar { display: flex; gap: .65rem; align-items: center; flex-wrap: wrap; margin-bottom: 1.25rem; }
  .rv-tab {
    padding: .55rem 1rem; background: transparent; color: var(--cream);
    border: 1px solid var(--border); border-radius: 999px;
    font-size: .78rem; letter-spacing: 1.5px; text-transform: uppercase;
    cursor: pointer; font-family: var(--sans); font-weight: 600;
  }
  .rv-tab:hover { border-color: var(--gold); color: var(--gold); }
  .rv-tab.active { background: var(--gold); color: #fff; border-color: var(--gold); }
  .rv-count { color: var(--text-muted); font-size: .85rem; margin-left: .5rem; }

  .rv-card {
    background: var(--bg-card); border: 1px solid var(--border-soft);
    border-radius: var(--radius-lg); padding: 1.25rem 1.5rem;
    margin-bottom: 1rem; display: grid; grid-template-columns: 80px 1fr auto; gap: 1.25rem;
    align-items: start;
  }
  .rv-card.is-pending  { border-left: 3px solid var(--warn); }
  .rv-card.is-approved { border-left: 3px solid var(--success); }
  .rv-card.is-rejected { border-left: 3px solid var(--error); opacity: .65; }
  .rv-prod-img { width: 80px; height: 80px; border-radius: var(--radius); overflow: hidden; background: var(--bg-soft); }
  .rv-prod-img img { width: 100%; height: 100%; object-fit: cover; display: block; }

  .rv-meta { font-size: .82rem; color: var(--text-muted); margin-bottom: .35rem; }
  .rv-meta strong { color: var(--cream); margin-right: .5rem; }
  .rv-stars { color: var(--gold); letter-spacing: 2px; font-size: .95rem; }
  .rv-title { font-family: var(--serif); font-size: 1.15rem; color: var(--cream); margin: .35rem 0; }
  .rv-body { color: var(--text); font-size: .92rem; line-height: 1.65; margin: .25rem 0; }

  .rv-pill {
    display: inline-block; padding: .15rem .55rem; border-radius: var(--radius-sm);
    font-size: .65rem; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase;
    font-family: var(--sans); margin-right: .35rem;
  }
  .rv-pill.verified { background: rgba(95, 207, 128, 0.18); color: var(--success); }
  .rv-pill.featured { background: rgba(184, 146, 78, 0.18); color: var(--gold); }
  .rv-pill.pending  { background: rgba(247, 185, 85, 0.18); color: var(--warn); }
  .rv-pill.rejected { background: rgba(238, 90, 90, 0.18); color: var(--error); }

  .rv-actions { display: flex; flex-direction: column; gap: .35rem; min-width: 130px; }
  .rv-actions button {
    padding: .5rem .75rem; border: 1px solid var(--border); background: var(--bg);
    border-radius: var(--radius); color: var(--cream); cursor: pointer;
    font-family: var(--sans); font-size: .72rem; font-weight: 600;
    letter-spacing: 1px; text-transform: uppercase; transition: all .2s;
  }
  .rv-actions button:hover { border-color: var(--gold); color: var(--gold); }
  .rv-actions button.danger:hover { border-color: var(--error); color: var(--error); }
  .rv-actions button.primary { background: var(--gold); color: #fff; border-color: var(--gold); }
  .rv-actions button.primary:hover { background: var(--gold-dark); color: #fff; }

  @media (max-width: 700px) {
    .rv-card { grid-template-columns: 60px 1fr; }
    .rv-actions { grid-column: 1 / -1; flex-direction: row; flex-wrap: wrap; min-width: 0; }
    .rv-prod-img { width: 60px; height: 60px; }
  }
</style>

<h1>★ Reviews</h1>
<p class="muted small" style="margin-bottom: 1.5rem;">Approve customer reviews to show them on the site. Feature the best ones for the homepage.</p>

<div class="admin-card">
  <div class="rv-toolbar" id="rv-tabs">
    <button class="rv-tab active" data-status="pending">Pending</button>
    <button class="rv-tab" data-status="approved">Approved</button>
    <button class="rv-tab" data-status="rejected">Rejected</button>
    <button class="rv-tab" data-status="all">All</button>
    <span class="rv-count" id="rv-count"></span>
  </div>
  <div id="rv-list"><p class="muted">Loading…</p></div>
</div>

<script>
(() => {
  const list  = document.getElementById('rv-list');
  const tabs  = document.getElementById('rv-tabs');
  const count = document.getElementById('rv-count');
  let currentStatus = 'pending';

  function escape(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }
  function stars(n) { return '★'.repeat(n) + '☆'.repeat(5 - n); }
  function fmtDate(s) { try { return new Date(s).toLocaleString(); } catch { return s; } }

  async function load() {
    list.innerHTML = '<p class="muted">Loading…</p>';
    try {
      const r = await fetch('../api/admin-reviews.php?status=' + currentStatus, { credentials: 'include' });
      const j = await r.json();
      if (!r.ok) throw new Error(j.error || 'Load failed');
      count.textContent = `(${j.total} total)`;
      const reviews = j.reviews || [];
      if (!reviews.length) {
        list.innerHTML = `<p class="muted" style="padding: 2rem 0; text-align: center;">No ${currentStatus === 'all' ? '' : currentStatus + ' '}reviews.</p>`;
        return;
      }
      list.innerHTML = reviews.map(card).join('');
    } catch (e) {
      list.innerHTML = `<p style="color: var(--error);">Failed: ${escape(e.message)}</p>`;
    }
  }

  function card(r) {
    const productLine = r.product_name
      ? `<a href="../product.html?sku=${escape(r.product_sku)}" target="_blank">${escape(r.product_name)}</a>`
      : '<em>(deleted product)</em>';
    const userLine = r.user_email ? escape(r.user_email) : '<em>(no user)</em>';
    const pills = [
      r.status === 'pending'  ? '<span class="rv-pill pending">Pending</span>' : '',
      r.status === 'rejected' ? '<span class="rv-pill rejected">Rejected</span>' : '',
      r.is_verified_buyer == 1 ? '<span class="rv-pill verified">✓ Verified</span>' : '',
      r.is_featured == 1 ? '<span class="rv-pill featured">★ Featured</span>' : '',
    ].join('');
    const photos = (r.photo_urls && r.photo_urls.length)
      ? `<div style="display:flex;gap:.4rem;margin-top:.5rem;">${r.photo_urls.map(u => `<img src="${escape(u)}" style="width:48px;height:48px;object-fit:cover;border-radius:4px;border:1px solid var(--border);">`).join('')}</div>`
      : '';

    const actions = [];
    if (r.status !== 'approved') actions.push(`<button class="primary" data-id="${r.id}" data-action="approve">Approve</button>`);
    if (r.status !== 'rejected') actions.push(`<button data-id="${r.id}" data-action="reject">Reject</button>`);
    if (r.is_featured == 1) {
      actions.push(`<button data-id="${r.id}" data-action="unfeature">Unfeature</button>`);
    } else {
      actions.push(`<button data-id="${r.id}" data-action="feature">Feature ★</button>`);
    }
    actions.push(`<button class="danger" data-id="${r.id}" data-action="delete">Delete</button>`);

    return `
      <div class="rv-card is-${escape(r.status)}">
        <div class="rv-prod-img">${r.product_image ? `<img src="${escape(r.product_image)}" alt="">` : ''}</div>
        <div>
          <div class="rv-meta">
            <strong>${escape(r.reviewer_name)}</strong>
            ${r.reviewer_location ? '· ' + escape(r.reviewer_location) : ''}
            · ${userLine}
            · <small>${escape(fmtDate(r.created_at))}</small>
          </div>
          <div class="rv-stars">${stars(r.rating)}</div>
          ${r.title ? `<div class="rv-title">${escape(r.title)}</div>` : ''}
          <div class="rv-body">${escape(r.body)}</div>
          ${photos}
          <div style="margin-top:.5rem;">${pills} <small class="muted">on ${productLine} · helpful: ${r.helpful_count}</small></div>
        </div>
        <div class="rv-actions">${actions.join('')}</div>
      </div>`;
  }

  tabs.addEventListener('click', (e) => {
    const btn = e.target.closest('.rv-tab');
    if (!btn) return;
    document.querySelectorAll('.rv-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    currentStatus = btn.dataset.status;
    load();
  });

  list.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const id = parseInt(btn.dataset.id, 10);
    const action = btn.dataset.action;
    if (action === 'delete' && !confirm('Delete this review permanently?')) return;
    btn.disabled = true; btn.textContent = '…';
    try {
      const r = await fetch('../api/admin-reviews.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, action }),
      });
      const j = await r.json();
      if (!r.ok || j.error) throw new Error(j.error || 'Action failed');
      load();
    } catch (err) {
      alert('Failed: ' + err.message);
      btn.disabled = false;
      load();
    }
  });

  load();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
