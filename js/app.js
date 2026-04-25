// ============================================================
// GlamEye - 全站脚本（购物车、导航、通知）
// ============================================================
(function () {
  'use strict';

  // ============== Cart ==============
  const Cart = {
    items: [],
    storageKey: 'glameye_cart_v1',

    load() {
      try {
        const raw = localStorage.getItem(this.storageKey);
        this.items = raw ? JSON.parse(raw) : [];
        if (!Array.isArray(this.items)) this.items = [];
      } catch (e) {
        this.items = [];
      }
    },

    save() {
      try {
        localStorage.setItem(this.storageKey, JSON.stringify(this.items));
      } catch (e) {
        console.warn('Cart save failed:', e);
      }
      this.updateBadge();
      window.dispatchEvent(new CustomEvent('cart:update', { detail: this.items }));
    },

    add(name, price) {
      const existing = this.items.find((i) => i.name === name);
      if (existing) {
        existing.quantity = Math.min(50, existing.quantity + 1);
      } else {
        this.items.push({ name, price: Number(price) || 0, quantity: 1 });
      }
      this.save();
      Notification.show(`✨ 已加入购物车："${name}"`);
    },

    setQuantity(name, qty) {
      const it = this.items.find((i) => i.name === name);
      if (!it) return;
      const q = Math.max(0, Math.min(50, parseInt(qty, 10) || 0));
      if (q === 0) this.remove(name);
      else { it.quantity = q; this.save(); }
    },

    remove(name) {
      this.items = this.items.filter((i) => i.name !== name);
      this.save();
    },

    clear() {
      this.items = [];
      this.save();
    },

    total() {
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
        el.style.display = c > 0 ? 'inline-flex' : 'none';
      }
    },
  };

  // ============== Notification ==============
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
      n.className = `notification notification--${type}`;
      n.textContent = msg;
      this.container.appendChild(n);
      requestAnimationFrame(() => n.classList.add('show'));
      setTimeout(() => {
        n.classList.remove('show');
        setTimeout(() => n.remove(), 300);
      }, 2800);
    },
  };

  // ============== 全局函数 ==============
  function addToCart(name, price) { Cart.add(name, price); }

  // ============== 初始化 ==============
  document.addEventListener('DOMContentLoaded', () => {
    Cart.load();
    Cart.updateBadge();

    // 给 .add-to-cart-btn 绑定事件（从 data-product/data-price 读取）
    document.querySelectorAll('.add-to-cart-btn').forEach((btn) => {
      const card = btn.closest('[data-product]');
      if (!card) return;
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const name = card.getAttribute('data-product');
        const price = parseFloat(card.getAttribute('data-price') || '0');
        Cart.add(name, price);
      });
    });

    // 平滑滚动
    document.querySelectorAll('a[href^="#"]').forEach((a) => {
      a.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href === '#' || href.length < 2) return;
        const t = document.querySelector(href);
        if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      });
    });

    // 导航汉堡按钮
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

    // 批发询单 AJAX 提交
    const wholesaleForm = document.getElementById('wholesale-form');
    if (wholesaleForm) {
      wholesaleForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('wholesale-feedback');
        const data = Object.fromEntries(new FormData(wholesaleForm).entries());
        fb.textContent = '提交中...';
        fb.className = 'form-feedback';
        try {
          const r = await fetch('api/wholesale-lead.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
          });
          const j = await r.json();
          if (j.success) {
            fb.textContent = '✅ 已收到您的询单，我们会在 1 个工作日内联系您。';
            fb.className = 'form-feedback success';
            wholesaleForm.reset();
          } else {
            fb.textContent = '❌ ' + (j.error || '提交失败');
            fb.className = 'form-feedback error';
          }
        } catch (err) {
          fb.textContent = '❌ 网络错误，请稍后再试';
          fb.className = 'form-feedback error';
        }
      });
    }

    document.body.classList.add('loaded');
  });

  // ============== 暴露到全局 ==============
  window.GlamEye = { Cart, Notification };
  window.addToCart = addToCart;
})();
