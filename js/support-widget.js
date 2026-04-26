// ============================================================
// GlamEye - 在线客服浮窗
// 右下角悬浮按钮,点击展开聊天框。
// 客户填邮箱 + 留言 → POST /api/support-message.php
// localStorage 记 thread_token,下次打开继续同一线程,显示历史。
// 邮箱里收到的"继续对话"链接会带 ?support=<token>,自动恢复线程并打开窗口。
// ============================================================
(function () {
  'use strict';
  if (window.__glameyeSupportLoaded) return;
  window.__glameyeSupportLoaded = true;

  const STORAGE_KEY = 'glameye_support_v1';
  const API_URL    = '/api/support-message.php';

  // 跳过 admin 后台
  if (location.pathname.indexOf('/admin') === 0) return;

  function load() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); }
    catch (e) { return {}; }
  }
  function save(data) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch (e) {}
  }
  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  function build() {
    const root = document.createElement('div');
    root.className = 'support-widget';
    root.innerHTML = `
      <button class="support-fab" aria-label="Chat with us" type="button">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <span class="support-fab-label">Chat</span>
        <span class="support-fab-dot" hidden></span>
      </button>
      <section class="support-panel" hidden role="dialog" aria-label="Customer support chat">
        <header class="support-head">
          <div>
            <strong>GlamEye Support</strong>
            <small>Reply by email · usually within a few hours</small>
          </div>
          <button class="support-close" aria-label="Close" type="button">×</button>
        </header>
        <div class="support-body">
          <div class="support-messages" hidden></div>
          <form class="support-form">
            <label class="support-label">Your email <span class="required">*</span></label>
            <input type="email" name="email" required autocomplete="email" placeholder="you@example.com">
            <label class="support-label">Your name (optional)</label>
            <input type="text" name="name" autocomplete="name" maxlength="120" placeholder="Sara">
            <label class="support-label">How can we help? <span class="required">*</span></label>
            <textarea name="body" required rows="4" maxlength="4000" placeholder="Ask us anything — sizing, shipping, returns…"></textarea>
            <input type="text" name="hp" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;pointer-events:none">
            <button type="submit" class="support-submit">Send message</button>
            <p class="support-feedback"></p>
            <p class="support-fineprint">We'll reply by email — please add support@glameyeshop.com to your contacts so it doesn't go to spam.</p>
          </form>
        </div>
      </section>
    `;
    document.body.appendChild(root);
    return root;
  }

  function fmtTime(s) {
    if (!s) return '';
    try {
      const d = new Date(s.replace(' ', 'T') + 'Z');
      return d.toLocaleString();
    } catch (e) { return s; }
  }

  async function loadThread(token, msgsEl) {
    try {
      const r = await fetch(API_URL + '?token=' + encodeURIComponent(token), { credentials: 'include' });
      const j = await r.json();
      if (!j.thread) return null;
      renderMessages(msgsEl, j.messages || []);
      return j;
    } catch (e) { return null; }
  }

  function renderMessages(el, messages) {
    if (!messages.length) { el.hidden = true; return; }
    el.hidden = false;
    el.innerHTML = messages.map(m => `
      <div class="support-msg support-msg--${esc(m.sender)}">
        <div class="support-msg-bubble">${esc(m.body).replace(/\n/g, '<br>')}</div>
        <small class="support-msg-time">${esc(m.sender === 'admin' ? 'GlamEye' : 'You')} · ${esc(fmtTime(m.created_at))}</small>
      </div>
    `).join('');
    el.scrollTop = el.scrollHeight;
  }

  document.addEventListener('DOMContentLoaded', () => {
    const root = build();
    const fab     = root.querySelector('.support-fab');
    const dot     = root.querySelector('.support-fab-dot');
    const panel   = root.querySelector('.support-panel');
    const closeBtn= root.querySelector('.support-close');
    const form    = root.querySelector('.support-form');
    const fb      = root.querySelector('.support-feedback');
    const submit  = root.querySelector('.support-submit');
    const msgsEl  = root.querySelector('.support-messages');
    const emailEl = form.querySelector('[name=email]');
    const nameEl  = form.querySelector('[name=name]');

    const data = load();

    // 自动填:已登录用户从 GlamEye.Auth 拿 email/姓名
    if (window.GlamEye && window.GlamEye.Auth && window.GlamEye.Auth.fetchMe) {
      window.GlamEye.Auth.fetchMe().then(u => {
        if (u && u.email && !emailEl.value) emailEl.value = u.email;
        if (u && !nameEl.value) {
          const n = [u.first_name, u.last_name].filter(Boolean).join(' ');
          if (n) nameEl.value = n;
        }
      }).catch(() => {});
    }
    // 也从 localStorage 预填
    if (data.email && !emailEl.value) emailEl.value = data.email;
    if (data.name  && !nameEl.value)  nameEl.value  = data.name;

    function open() {
      panel.hidden = false;
      fab.setAttribute('aria-expanded', 'true');
      requestAnimationFrame(() => panel.classList.add('open'));
      // 打开时若有 token,拉一下历史,顺便清未读小红点
      if (data.thread_token) {
        loadThread(data.thread_token, msgsEl).then((j) => {
          if (j && j.thread) {
            // 已读,清掉本地的红点
            data.unread = 0; save(data);
            dot.hidden = true;
          }
        });
      }
    }
    function close() {
      panel.classList.remove('open');
      setTimeout(() => panel.hidden = true, 220);
      fab.setAttribute('aria-expanded', 'false');
    }

    fab.addEventListener('click', () => panel.hidden ? open() : close());
    closeBtn.addEventListener('click', close);

    // URL 带 ?support=<token> → 自动打开 + 加载该线程
    const sp = new URLSearchParams(location.search);
    const urlToken = sp.get('support');
    if (urlToken && /^[a-f0-9]{32}$/.test(urlToken)) {
      data.thread_token = urlToken;
      data.unread = 1; // 邮件入口默认有新消息提醒
      save(data);
    }

    // 红点提醒(localStorage 标记的未读)
    if (data.unread) dot.hidden = false;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      fb.textContent = ''; fb.className = 'support-feedback';
      const payload = {
        email: emailEl.value.trim(),
        name : nameEl.value.trim(),
        body : form.body.value.trim(),
        hp   : form.hp.value || '',
        thread_token: data.thread_token || '',
      };
      if (!payload.email || !payload.body) {
        fb.textContent = 'Please fill in email and message.';
        fb.classList.add('error');
        return;
      }
      submit.disabled = true; submit.textContent = 'Sending…';
      try {
        const r = await fetch(API_URL, {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (!r.ok || !j.success) throw new Error(j.error || 'Failed');
        // 持久化 token + email/name
        data.thread_token = j.thread_token;
        data.email = payload.email; data.name = payload.name;
        data.unread = 0;
        save(data);
        // 把刚发的也插入历史显示
        const now = new Date().toISOString().slice(0,19).replace('T',' ');
        const cur = msgsEl.hidden ? [] : Array.from(msgsEl.querySelectorAll('.support-msg')).map(()=>null);
        // 简化:重拉
        await loadThread(data.thread_token, msgsEl);
        form.body.value = '';
        fb.textContent = j.message || 'Got it — we will reply by email.';
        fb.classList.add('success');
      } catch (err) {
        fb.textContent = err.message || 'Send failed. Please try again.';
        fb.classList.add('error');
      } finally {
        submit.disabled = false; submit.textContent = 'Send message';
      }
    });
  });
})();
