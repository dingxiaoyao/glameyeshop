<?php $pageTitle = 'Customer Support'; $activeNav = 'support'; require __DIR__ . '/_layout.php'; ?>
<style>
  .support-layout { display: grid; grid-template-columns: 360px 1fr; gap: 1.25rem; min-height: 70vh; }
  .support-list { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); display: flex; flex-direction: column; overflow: hidden; }
  .support-list-head { padding: 1rem 1.1rem; border-bottom: 1px solid var(--border-soft); }
  .support-list-stats { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .8rem; color: var(--text-muted); margin-top: .5rem; }
  .support-list-stats b { color: var(--gold); margin-right: .25rem; }
  .support-list-search { width: 100%; margin-top: .65rem; }
  .support-list-search input {
    width: 100%; padding: .55rem .85rem;
    background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius);
    color: var(--cream); font-size: .85rem;
  }
  .support-tabs { display: flex; gap: .25rem; padding: .5rem; border-bottom: 1px solid var(--border-soft); }
  .support-tab {
    flex: 1; padding: .5rem .75rem;
    background: transparent; border: 1px solid transparent;
    color: var(--cream); cursor: pointer;
    font-size: .72rem; letter-spacing: 1px; text-transform: uppercase;
    border-radius: var(--radius); transition: all .2s;
  }
  .support-tab:hover { color: var(--gold); }
  .support-tab.active { background: var(--bg); border-color: var(--gold-dark); color: var(--gold); }
  .support-threads { overflow-y: auto; flex: 1; }
  .thread-row {
    display: block; padding: .85rem 1.1rem;
    border-bottom: 1px solid var(--border-soft);
    cursor: pointer; transition: background .15s;
    text-decoration: none; color: inherit;
  }
  .thread-row:hover { background: var(--bg); }
  .thread-row.active { background: var(--bg); border-left: 3px solid var(--gold); padding-left: calc(1.1rem - 3px); }
  .thread-row.unread { background: rgba(212, 169, 85, 0.06); }
  .thread-row.unread::before { content: '●'; color: var(--gold); margin-right: .35rem; font-size: .7rem; }
  .thread-row strong { display: block; color: var(--cream); font-size: .9rem; }
  .thread-row small { display: block; color: var(--text-muted); font-size: .72rem; margin-top: .15rem; }
  .thread-row .preview { color: var(--text); font-size: .82rem; line-height: 1.4; margin-top: .35rem;
                         overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
  .thread-row .row-meta { display: flex; justify-content: space-between; gap: .5rem; align-items: center; margin-top: .35rem; }
  .thread-row .row-meta small { margin-top: 0; }

  .support-detail { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); display: flex; flex-direction: column; overflow: hidden; }
  .detail-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; color: var(--text-muted); padding: 4rem; text-align: center; }
  .detail-empty .icon { font-size: 3rem; opacity: .5; margin-bottom: 1rem; }
  .detail-head { padding: 1.1rem 1.25rem; border-bottom: 1px solid var(--border-soft); display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; }
  .detail-head h2 { color: var(--cream); margin: 0 0 .35rem; font-size: 1.2rem; }
  .detail-head small { color: var(--text-muted); font-size: .82rem; }
  .detail-status { font-size: .68rem; letter-spacing: 1.5px; padding: .25rem .65rem; border-radius: 999px; text-transform: uppercase; font-weight: 700; }
  .detail-status.open      { background: rgba(247,185,85,.15); color: var(--warn); }
  .detail-status.replied   { background: rgba(95,207,128,.15); color: var(--success); }
  .detail-status.closed    { background: rgba(154,141,117,.15); color: var(--text-muted); }
  .detail-messages { flex: 1; overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
  .det-msg-row { display: flex; flex-direction: column; }
  .det-msg-row.customer { align-items: flex-start; }
  .det-msg-row.admin    { align-items: flex-end; }
  .det-msg-bubble { max-width: 80%; padding: .75rem 1rem; border-radius: 12px; font-size: .92rem; line-height: 1.5; word-wrap: break-word; white-space: pre-wrap; }
  .det-msg-row.customer .det-msg-bubble { background: var(--bg); border: 1px solid var(--border-soft); color: var(--cream); border-bottom-left-radius: 3px; }
  .det-msg-row.admin    .det-msg-bubble { background: var(--gold); color: var(--bg); border-bottom-right-radius: 3px; }
  .det-msg-time { font-size: .7rem; color: var(--text-dim); margin-top: .25rem; padding: 0 .35rem; }

  .reply-form { border-top: 1px solid var(--border-soft); padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: .65rem; }
  .reply-form textarea {
    width: 100%; min-height: 90px;
    background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius);
    color: var(--cream); padding: .75rem 1rem; font-family: var(--sans); font-size: .9rem; resize: vertical;
  }
  .reply-form textarea:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(212,169,85,.15); }
  .reply-form-actions { display: flex; gap: .5rem; justify-content: space-between; align-items: center; flex-wrap: wrap; }
  .reply-form-actions label { color: var(--text-muted); font-size: .8rem; display: flex; align-items: center; gap: .35rem; }

  @media (max-width: 960px) {
    .support-layout { grid-template-columns: 1fr; }
  }
</style>

<h1>💬 <?= $lang === 'zh' ? '客户咨询' : 'Customer Support' ?></h1>
<p class="muted" style="margin-bottom: 1.5rem;">
  <?= $lang === 'zh'
    ? '客户在网站浮窗发的留言会显示在这里;你的回复会自动发邮件给客户。'
    : 'Customer chat-widget messages appear here. Your replies are emailed back automatically.' ?>
</p>

<div class="support-layout">
  <aside class="support-list">
    <div class="support-list-head">
      <strong style="color:var(--cream);"><?= $lang === 'zh' ? '所有线程' : 'All threads' ?></strong>
      <div class="support-list-stats" id="stats"></div>
      <div class="support-list-search">
        <input type="search" id="search" placeholder="<?= $lang === 'zh' ? '搜索邮箱、姓名、主题…' : 'Search email, name, subject…' ?>">
      </div>
    </div>
    <div class="support-tabs">
      <button class="support-tab active" data-status="all"><?= $lang === 'zh' ? '全部' : 'All' ?></button>
      <button class="support-tab" data-status="open"><?= $lang === 'zh' ? '待回复' : 'Open' ?></button>
      <button class="support-tab" data-status="replied"><?= $lang === 'zh' ? '已回复' : 'Replied' ?></button>
      <button class="support-tab" data-status="closed"><?= $lang === 'zh' ? '已关闭' : 'Closed' ?></button>
    </div>
    <div class="support-threads" id="threads">
      <p class="muted" style="padding: 2rem; text-align: center;"><?= htmlspecialchars(t('loading')) ?></p>
    </div>
  </aside>

  <section class="support-detail" id="detail">
    <div class="detail-empty">
      <div class="icon">💬</div>
      <p><?= $lang === 'zh' ? '从左侧选一条线程开始回复' : 'Pick a thread on the left to reply' ?></p>
    </div>
  </section>
</div>

<script>
(() => {
  function escape(s) { return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function fmtTime(s) {
    if (!s) return '';
    try { return new Date(s.replace(' ', 'T') + 'Z').toLocaleString(); }
    catch (e) { return s; }
  }

  const threadsEl = document.getElementById('threads');
  const detailEl  = document.getElementById('detail');
  const searchEl  = document.getElementById('search');
  const statsEl   = document.getElementById('stats');
  let currentStatus = 'all';
  let currentId = null;

  async function loadList() {
    const params = new URLSearchParams();
    if (currentStatus && currentStatus !== 'all') params.set('status', currentStatus);
    const q = searchEl.value.trim();
    if (q) params.set('q', q);
    threadsEl.innerHTML = '<p class="muted" style="padding:2rem;text-align:center;">Loading…</p>';
    try {
      const r = await fetch('../api/admin-support-list.php?' + params.toString(), { credentials: 'include' });
      const j = await r.json();
      const ts = j.threads || [];
      const s = j.stats || {};
      statsEl.innerHTML = `
        <span><b>${s.unread || 0}</b>unread</span>
        <span><b>${s.open_count || 0}</b>open</span>
        <span><b>${s.replied_count || 0}</b>replied</span>
        <span><b>${s.closed_count || 0}</b>closed</span>
      `;
      if (!ts.length) {
        threadsEl.innerHTML = '<p class="muted" style="padding:2rem;text-align:center;">No threads.</p>';
        return;
      }
      threadsEl.innerHTML = ts.map(t => `
        <a class="thread-row ${t.unread_for_admin == 1 ? 'unread' : ''} ${currentId == t.id ? 'active' : ''}"
           href="#" data-id="${t.id}">
          <strong>${escape(t.customer_name || t.customer_email)}</strong>
          <small>${escape(t.customer_email)}</small>
          <div class="preview">${escape(t.last_message || t.subject || '—')}</div>
          <div class="row-meta">
            <small>${escape(fmtTime(t.updated_at))}</small>
            <span class="detail-status ${escape(t.status)}">${escape(t.status)}</span>
          </div>
        </a>
      `).join('');
    } catch (e) {
      threadsEl.innerHTML = '<p style="color:var(--error);padding:2rem;">Failed: ' + escape(e.message) + '</p>';
    }
  }

  async function loadDetail(id) {
    currentId = id;
    document.querySelectorAll('.thread-row').forEach(r => r.classList.toggle('active', r.dataset.id == id));
    detailEl.innerHTML = '<p class="muted" style="padding:2rem;text-align:center;">Loading…</p>';
    try {
      const r = await fetch('../api/admin-support-list.php?id=' + id, { credentials: 'include' });
      const j = await r.json();
      const t = j.thread, ms = j.messages || [];
      detailEl.innerHTML = `
        <div class="detail-head">
          <div>
            <h2>${escape(t.customer_name || t.customer_email)} <small>· #${t.id}</small></h2>
            <small><a href="mailto:${escape(t.customer_email)}" style="color:var(--gold);">${escape(t.customer_email)}</a> · ${escape(t.subject || '')}</small>
          </div>
          <span class="detail-status ${escape(t.status)}">${escape(t.status)}</span>
        </div>
        <div class="detail-messages" id="det-msgs">
          ${ms.map(m => `
            <div class="det-msg-row ${escape(m.sender)}">
              <div class="det-msg-bubble">${escape(m.body)}</div>
              <div class="det-msg-time">${escape(m.sender === 'admin' ? (m.admin_user || 'You') : 'Customer')} · ${escape(fmtTime(m.created_at))}</div>
            </div>
          `).join('')}
        </div>
        <form class="reply-form" id="reply-form">
          <textarea name="body" placeholder="Type your reply…" required maxlength="8000"></textarea>
          <div class="reply-form-actions">
            <label><input type="checkbox" name="close"> Close thread after sending</label>
            <button type="submit" class="button button-primary button-sm">Send reply &amp; email</button>
          </div>
          <p class="muted small" id="reply-fb"></p>
        </form>
      `;
      const msgs = document.getElementById('det-msgs');
      if (msgs) msgs.scrollTop = msgs.scrollHeight;

      document.getElementById('reply-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = e.target;
        const fb = document.getElementById('reply-fb');
        const btn = f.querySelector('button[type=submit]');
        btn.disabled = true; btn.textContent = 'Sending…';
        fb.textContent = '';
        try {
          const res = await fetch('../api/admin-support-reply.php', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              thread_id: t.id,
              body: f.body.value.trim(),
              close: f.close.checked ? 1 : 0,
            }),
          });
          const j = await res.json();
          if (!res.ok || !j.success) throw new Error(j.error || 'Failed');
          fb.style.color = j.email_sent ? 'var(--success)' : 'var(--warn)';
          fb.textContent = j.email_sent
            ? `Reply sent. Email delivered via ${j.email_mode}.`
            : `Reply saved, but email failed: ${j.email_error || ''}. Check Settings → Email.`;
          // 重载详情 + 列表
          await loadDetail(t.id);
          loadList();
        } catch (err) {
          fb.style.color = 'var(--error)';
          fb.textContent = err.message;
          btn.disabled = false; btn.textContent = 'Send reply & email';
        }
      });
    } catch (e) {
      detailEl.innerHTML = '<p style="color:var(--error);padding:2rem;">Failed: ' + escape(e.message) + '</p>';
    }
  }

  threadsEl.addEventListener('click', (e) => {
    const row = e.target.closest('.thread-row');
    if (row) { e.preventDefault(); loadDetail(parseInt(row.dataset.id, 10)); }
  });
  document.querySelectorAll('.support-tab').forEach((tab) => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.support-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentStatus = tab.dataset.status;
      loadList();
    });
  });
  let searchTimer;
  searchEl.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadList, 300);
  });

  loadList();

  // 直接打开某个线程:支持 ?id=N
  const sp = new URLSearchParams(location.search);
  const directId = parseInt(sp.get('id') || '0', 10);
  if (directId > 0) loadDetail(directId);
})();
</script>
<?php require __DIR__ . '/_footer.php'; ?>
