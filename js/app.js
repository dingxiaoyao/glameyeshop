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

  // ============== Responsive Images ==============
  // 命名约定:abc.jpg 同目录有 abc-320.webp / abc-320.jpg / abc-640.{webp,jpg} / abc-1024.{webp,jpg} / abc-1600.{webp,jpg}
  // 远程 URL(unsplash 等)直接返回原值,不组装 variant。
  const Img = {
    SIZES: [320, 640, 1024, 1600],
    /** 取一个 variant URL。url 是数据库里存的原图路径。 */
    variant(url, width, ext = 'webp') {
      if (!url || typeof url !== 'string') return url;
      if (/^https?:\/\//i.test(url)) return url; // 远程不动
      const m = url.match(/^(.*)\.(jpe?g|png|webp)$/i);
      if (!m) return url;
      // 已经是 variant 形式 abc-640.jpg → 去掉后缀再换
      const stem = m[1].replace(/-(\d{2,4})$/, '');
      return `${stem}-${width}.${ext}`;
    },
    /** 生成 <picture> 字符串。用 width-descriptor srcset + sizes,让浏览器按
     *  "卡片实际占多少 css 像素 × dpr"挑最小够用的档,不会再 retina 一律选最大。
     *  context:
     *    'thumb'  详情页缩略 / 购物车小图 (~80-120 css px)  → srcset 320,640
     *    'card'   列表卡片 (~160-280 css px)               → srcset 320,640
     *    'detail' 详情页主图 (~400-700 css px)             → srcset 640,1024,1600
     *    'hero'   全屏 hero (~100vw)                       → srcset 640,1024,1600 + eager
     *  attrs.sizes 可覆盖默认 sizes(给 detail 这种主图特别有用)
     */
    picture(url, context = 'card', attrs = {}) {
      const esc = Fmt.escape;
      const isRemote = /^https?:\/\//i.test(url || '');
      const altAttr = `alt="${esc(attrs.alt || '')}"`;
      const cls     = attrs.class ? ` class="${esc(attrs.class)}"` : '';
      const loading = attrs.loading || 'lazy';
      const fp      = attrs.fetchpriority ? ` fetchpriority="${esc(attrs.fetchpriority)}"` : '';
      const decode  = ` decoding="${esc(attrs.decoding || 'async')}"`;
      // 远程 URL 直接 <img>,不组 srcset
      if (isRemote || !url) {
        return `<img src="${esc(url || '')}" ${altAttr}${cls} loading="${esc(loading)}"${fp}${decode}>`;
      }
      // 默认 sizes(可被 attrs.sizes 覆盖)
      let widths, defaultSizes, baseW;
      if (context === 'thumb') {
        widths = [320, 640];
        // 详情页缩略一行 5 个 ≤ 100px / 购物车 72-120px。retina 就 320 物理 px,几乎都用 320 档。
        defaultSizes = '(max-width: 640px) 80px, 120px';
        baseW = 320;
      } else if (context === 'detail') {
        widths = [640, 1024, 1600];
        // 详情页主图 PDP:窄屏 100vw,桌面 ~50vw 但 max 700px(CSS aspect-ratio:1)
        defaultSizes = '(max-width: 960px) 100vw, min(50vw, 700px)';
        baseW = 1024;
      } else if (context === 'hero') {
        widths = [640, 1024, 1600];
        defaultSizes = '100vw';
        baseW = 1024;
      } else { // card
        widths = [320, 640];
        // mobile 2列 50vw;tablet 3列 33vw;desktop 4列 25vw 但 max 320px
        defaultSizes = '(max-width: 640px) 50vw, (max-width: 1024px) 33vw, min(25vw, 320px)';
        baseW = 640;
      }
      const sizesAttr = ` sizes="${esc(attrs.sizes || defaultSizes)}"`;
      const webpSrcset = widths.map(w => `${Img.variant(url, w, 'webp')} ${w}w`).join(', ');
      const jpgSrcset  = widths.map(w => `${Img.variant(url, w, 'jpg')}  ${w}w`).join(', ');
      const base = Img.variant(url, baseW, 'jpg');

      const finalLoading = (context === 'hero') ? 'eager' : loading;
      const finalFp      = (context === 'hero' && !attrs.fetchpriority) ? ' fetchpriority="high"' : fp;
      const fallback = esc(url);
      const onerr = ` onerror="this.onerror=null;this.src='${fallback}';"`;
      return `<picture>
        <source type="image/webp" srcset="${esc(webpSrcset)}"${sizesAttr}>
        <source type="image/jpeg" srcset="${esc(jpgSrcset)}"${sizesAttr}>
        <img src="${esc(base)}" ${altAttr}${cls} loading="${esc(finalLoading)}"${finalFp}${decode}${sizesAttr}${onerr}>
      </picture>`;
    },
    /** 给已有的 <img> 元素就地升级:换成 picture 包裹(用于不方便整段重写 HTML 的场景) */
    upgrade(imgEl, context = 'card') {
      if (!imgEl || imgEl.dataset.upgraded) return;
      const url = imgEl.getAttribute('src');
      if (!url) return;
      const wrapper = document.createElement('div');
      wrapper.innerHTML = Img.picture(url, context, {
        alt: imgEl.getAttribute('alt') || '',
        class: imgEl.getAttribute('class') || '',
        loading: imgEl.getAttribute('loading') || 'lazy',
      });
      const pic = wrapper.firstElementChild;
      imgEl.parentNode.replaceChild(pic, imgEl);
      pic.querySelector('img').dataset.upgraded = '1';
    },
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

    // Hamburger — 没有 .nav-toggle 的页面自动注入一个，避免移动端导航消失
    const nav = document.querySelector('.site-nav');
    let toggle = document.querySelector('.nav-toggle');
    if (!toggle && nav) {
      const headerInner = document.querySelector('.site-header .container');
      if (headerInner) {
        toggle = document.createElement('button');
        toggle.className = 'nav-toggle';
        toggle.setAttribute('aria-label', 'Open menu');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.innerHTML = '<span></span><span></span><span></span>';
        // 插到 brand 之后、nav 之前
        const brand = headerInner.querySelector('.brand');
        if (brand && brand.nextSibling) {
          headerInner.insertBefore(toggle, brand.nextSibling);
        } else {
          headerInner.appendChild(toggle);
        }
      }
    }
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
      // 点击外部关闭
      document.addEventListener('click', (e) => {
        if (!nav.classList.contains('open')) return;
        if (e.target.closest('.site-nav') || e.target.closest('.nav-toggle')) return;
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
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

  window.GlamEye = { Cart, Notification, Auth, Fmt, Img };

  // ============== 自动加载在线客服浮窗 ==============
  // 后台不加载;其他页面 defer 加载,避免阻塞首屏
  if (location.pathname.indexOf('/admin') !== 0) {
    const s = document.createElement('script');
    s.src = '/js/support-widget.js';
    s.defer = true;
    document.head.appendChild(s);
  }
})();
