<?php $pageTitle = 'Stripe Webhook Logs'; $activeNav = 'settings'; require __DIR__ . '/_layout.php'; ?>
<style>
  .log-tabs { display: flex; gap: .5rem; margin-bottom: 1rem; flex-wrap: wrap; }
  .log-tab { padding: .45rem 1rem; background: transparent; color: var(--cream); border: 1px solid var(--border); border-radius: 999px; font-size: .75rem; letter-spacing: 1.5px; text-transform: uppercase; cursor: pointer; font-weight: 600; }
  .log-tab:hover { border-color: var(--gold); color: var(--gold); }
  .log-tab.active { background: var(--gold); color: #fff; border-color: var(--gold); }
  .log-card { background: var(--bg-card); border: 1px solid var(--border-soft); border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: .75rem; display: grid; grid-template-columns: auto 1fr auto; gap: 1rem; align-items: start; }
  .log-card.error { border-left: 3px solid var(--error); }
  .log-card.processed { border-left: 3px solid var(--success); }
  .log-card.duplicate { border-left: 3px solid var(--text-dim); opacity: .8; }
  .log-card.amount_mismatch { border-left: 3px solid var(--warn); }
  .log-status { font-size: .7rem; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; padding: .25rem .55rem; border-radius: 4px; white-space: nowrap; }
  .log-status.processed { background: rgba(95,207,128,.18); color: var(--success); }
  .log-status.duplicate { background: rgba(122,122,122,.18); color: var(--text-muted); }
  .log-status.error { background: rgba(238,90,90,.18); color: var(--error); }
  .log-status.amount_mismatch, .log-status.received, .log-status.ignored, .log-status.skipped { background: rgba(247,185,85,.18); color: var(--warn); }
  .log-meta { font-size: .82rem; color: var(--text-muted); margin-bottom: .25rem; }
  .log-type { color: var(--gold); font-family: var(--mono, monospace); font-weight: 600; }
  .log-payload { font-family: var(--mono, monospace); font-size: .72rem; color: var(--text-muted); background: var(--bg-soft); padding: .5rem .65rem; border-radius: 4px; margin-top: .5rem; max-height: 8em; overflow: auto; word-break: break-all; white-space: pre-wrap; }
</style>

<h1>📜 Stripe Webhook Logs</h1>
<p class="muted small" style="margin-bottom:1.25rem;">
  All Stripe webhook events received,with their processing result. Use this when an order is stuck in pending and you want to know whether the webhook actually arrived.
</p>

<div class="log-tabs" id="log-tabs">
  <button class="log-tab active" data-status="all">All</button>
  <button class="log-tab" data-status="processed">Processed</button>
  <button class="log-tab" data-status="duplicate">Duplicate</button>
  <button class="log-tab" data-status="error">Error</button>
  <button class="log-tab" data-status="amount_mismatch">Amount mismatch</button>
  <button class="log-tab" data-status="ignored">Ignored</button>
</div>

<div id="log-list"><p class="muted">Loading…</p></div>
<div id="log-pagination" style="margin-top:1rem;display:flex;gap:.5rem;justify-content:center;flex-wrap:wrap;"></div>

<script>
(() => {
  const list = document.getElementById('log-list');
  const tabs = document.getElementById('log-tabs');
  const pagination = document.getElementById('log-pagination');
  let currentStatus = 'all';
  let currentPage = 1;

  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function fmtDate(s) { try { return new Date(s).toLocaleString(); } catch { return s; } }

  async function load() {
    list.innerHTML = '<p class="muted">Loading…</p>';
    try {
      const r = await fetch('../api/admin-stripe-logs.php?status=' + currentStatus + '&page=' + currentPage + '&per=50', { credentials: 'include' });
      const j = await r.json();
      const events = j.events || [];
      if (!events.length) {
        list.innerHTML = '<p class="muted" style="padding:2rem 0;text-align:center;">No ' + (currentStatus === 'all' ? '' : currentStatus + ' ') + 'events.</p>';
        pagination.innerHTML = '';
        return;
      }
      list.innerHTML = events.map(card).join('');
      renderPagination(j.pagination);
    } catch (e) {
      list.innerHTML = '<p style="color:var(--error)">Failed: ' + escape(e.message) + '</p>';
    }
  }

  function card(e) {
    const orderLink = e.order_id
      ? `<a href="orders.php#order-${e.order_id}" style="color:var(--gold);">Order #${e.order_id}</a>`
      : '<span class="muted">no order</span>';
    return `
      <div class="log-card ${escape(e.status)}">
        <span class="log-status ${escape(e.status)}">${escape(e.status)}</span>
        <div>
          <div class="log-meta"><span class="log-type">${escape(e.type)}</span> · ${escape(e.event_id)}</div>
          <div class="log-meta">${orderLink} · received ${escape(fmtDate(e.received_at))}${e.processed_at ? ' · processed ' + escape(fmtDate(e.processed_at)) : ''}</div>
          ${e.error ? `<div style="color:var(--error);font-size:.85rem;margin-top:.35rem;">⚠ ${escape(e.error)}</div>` : ''}
          ${e.raw_excerpt ? `<details><summary style="cursor:pointer;font-size:.75rem;color:var(--gold);margin-top:.5rem;">View payload excerpt</summary><div class="log-payload">${escape(e.raw_excerpt)}…</div></details>` : ''}
        </div>
      </div>`;
  }

  function renderPagination(p) {
    if (!p || p.total_pages <= 1) { pagination.innerHTML = ''; return; }
    const btns = [];
    btns.push(`<button class="filter-btn" data-p="${Math.max(1, p.page - 1)}" ${p.page <= 1 ? 'disabled' : ''}>← Prev</button>`);
    const start = Math.max(1, p.page - 3);
    const end = Math.min(p.total_pages, p.page + 3);
    for (let i = start; i <= end; i++) {
      btns.push(`<button class="filter-btn${i === p.page ? ' active' : ''}" data-p="${i}">${i}</button>`);
    }
    btns.push(`<button class="filter-btn" data-p="${Math.min(p.total_pages, p.page + 1)}" ${p.page >= p.total_pages ? 'disabled' : ''}>Next →</button>`);
    pagination.innerHTML = btns.join('');
    pagination.querySelectorAll('button[data-p]').forEach(b => b.addEventListener('click', () => {
      currentPage = parseInt(b.dataset.p, 10);
      load();
    }));
  }

  tabs.addEventListener('click', (e) => {
    const btn = e.target.closest('.log-tab');
    if (!btn) return;
    document.querySelectorAll('.log-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    currentStatus = btn.dataset.status;
    currentPage = 1;
    load();
  });

  load();
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
