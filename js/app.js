// ============================================================
// GlamEye - Global Scripts (Cart, Auth, Notifications, i18n)
// ============================================================
(function () {
  'use strict';

  // ============== Cart ==============
  const Cart = {
    items: [],
    storageKey: 'glameye_cart_v2',
    load() {
      try {
        const raw = localStorage.getItem(this.storageKey);
        this.items = raw ? JSON.parse(raw) : [];
        if (!Array.isArray(this.items)) this.items = [];
      } catch (e) { this.items = []; }
    },
    save() {
      try { localStorage.setItem(this.storageKey, JSON.stringify(this.items)); }
      catch (e) { console.warn('Cart save failed', e); }
      this.updateBadge();
      window.dispatchEvent(new CustomEvent('cart:update', { detail: this.items }));
    },
    add(item) {
      const existing = this.items.find((i) => i.sku === item.sku);
      if (existing) {
        existing.quantity = Math.min(50, existing.quantity + (item.quantity || 1));
      } else {
        this.items.push({
          sku: item.sku,
          name: item.name,
          price: Number(item.price) || 0,
          image: item.image || '',
          quantity: item.quantity || 1,
        });
      }
      this.save();
      Notification.show(`Added to cart: ${item.name}`);
    },
    setQuantity(sku, qty) {
      const it = this.items.find((i) => i.sku === sku);
      if (!it) return;
      const q = Math.max(0, Math.min(50, parseInt(qty, 10) || 0));
      if (q === 0) this.remove(sku);
      else { it.quantity = q; this.save(); }
    },
    remove(sku) {
      this.items = this.items.filter((i) => i.sku !== sku);
      this.save();
    },
    clear() { this.items = []; this.save(); },
    subtotal() {
      return this.items.reduce((s, i) => s + i.price * i.quantity, 0);
    },
    count() {
      return this.items.reduce((c, i) => c + i.quantity, 0);
    },
    updateBadge() {
      const el = document.getElementById('cart-count');
      if (el) {
        const c = this.count();
        el.textContent = c;
        el.classList.toggle('has-items', c > 0);
      }
    },
  };

  // ============== Notifications ==============
  const Notification = {
    container: null,
    ensure() {
      if (!this.container) {
        this.container = document.createElement('div');
        this.container.className = 'notification-container';
        document.body.appendChild(this.container);
      }
    },
    show(msg, type = 'success') {
      this.ensure();
      const n = document.createElement('div');
      n.className = 'notification' + (type === 'error' ? ' notification--error' : '');
      n.textContent = msg;
      this.container.appendChild(n);
      requestAnimationFrame(() => n.classList.add('show'));
      setTimeout(() => {
        n.classList.remove('show');
        setTimeout(() => n.remove(), 300);
      }, 2800);
    },
  };

  // ============== Auth helpers ==============
  const Auth = {
    user: null,
    async fetchMe() {
      try {
        const r = await fetch('api/auth.php?action=me', { credentials: 'include' });
        const j = await r.json();
        this.user = j.user || null;
        return this.user;
      } catch (e) { this.user = null; return null; }
    },
    isLoggedIn() { return !!this.user; },
    async logout() {
      try { await fetch('api/auth.php?action=logout', { method: 'POST', credentials: 'include' }); }
      catch (e) {}
      this.user = null;
      window.location.href = '/';
    },
  };

  // ============== Format ==============
  const Fmt = {
    money(n) { return '$' + Number(n || 0).toFixed(2); },
    escape(s) {
      return String(s ?? '').replace(/[&<>"']/g, (c) =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    }
  };

  // ============== Init ==============
  // ============== Page Tracking ==============
  function trackPageView() {
    try {
      const data = JSON.stringify({
        path: location.pathname + (location.search || ''),
        referer: document.referrer || '',
      });
      // sendBeacon: fire-and-forget, won't block page navigation
      if (navigator.sendBeacon) {
        navigator.sendBeacon('/api/track.php', new Blob([data], { type: 'application/json' }));
      } else {
        fetch('/api/track.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: data, keepalive: true }).catch(() => {});
      }
    } catch (e) {}
  }

  // ============== SEO Lock (noindex) ==============
  async function applySeoLock() {
    try {
      const r = await fetch('/api/settings.php', { cache: 'force-cache' });
      const s = await r.json();
      if (s.seo_blocked === '1') {
        // 注入 meta noindex（如果还没有）
        if (!document.querySelector('meta[name="robots"][content*="noindex"]')) {
          const m = document.createElement('meta');
          m.name = 'robots';
          m.content = 'noindex, nofollow, noarchive';
          document.head.appendChild(m);
        }
      }
    } catch (e) {}
  }

  document.addEventListener('DOMContentLoaded', async () => {
    Cart.load();
    Cart.updateBadge();
    trackPageView();
    applySeoLock();

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach((a) => {
      a.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href === '#' || href.length < 2) return;
        const t = document.querySelector(href);
        if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
      });
    });

    // Hamburger
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.site-nav');
    if (toggle && nav) {
      toggle.addEventListener('click', () => {
        const open = nav.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      nav.querySelectorAll('a').forEach((a) => {
        a.addEventListener('click', () => {
          nav.classList.remove('open');
          toggle.setAttribute('aria-expanded', 'false');
        });
      });
    }

    // Newsletter
    const newsletterForm = document.getElementById('newsletter-form');
    if (newsletterForm) {
      newsletterForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('newsletter-feedback');
        const data = Object.fromEntries(new FormData(newsletterForm).entries());
        fb.textContent = 'Subscribing...';
        fb.className = 'form-feedback';
        try {
          const r = await fetch('api/newsletter.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
          });
          const j = await r.json();
          if (j.success) {
            fb.textContent = '✨ Thanks! Check your inbox for 10% off.';
            fb.className = 'form-feedback success';
            newsletterForm.reset();
          } else {
            fb.textContent = j.error || 'Subscribe failed';
            fb.className = 'form-feedback error';
          }
        } catch (err) {
          fb.textContent = 'Network error. Please try again.';
          fb.className = 'form-feedback error';
        }
      });
    }

    // Auth state in header
    await Auth.fetchMe();
    const accountLink = document.getElementById('account-link');
    if (accountLink && Auth.isLoggedIn()) {
      accountLink.title = `Hi, ${Auth.user.first_name}`;
    }

    document.body.classList.add('loaded');
  });

  window.GlamEye = { Cart, Notification, Auth, Fmt };
})();
