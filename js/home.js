// Homepage: load featured products + featured videos + social icons + hero from settings
(function () {
  'use strict';
  const fmt = window.GlamEye.Fmt;
  const Img = window.GlamEye.Img;
  const escape = (s) => fmt.escape(s);
  const money  = (n) => fmt.money(n);

  function productCard(p) {
    const sale = p.compare_at_price && Number(p.compare_at_price) > Number(p.price);
    const badge = p.is_new == 1 ? 'new' : (sale ? 'sale' : (p.is_bestseller == 1 ? 'bestseller' : ''));
    const badgeText = p.is_new == 1 ? 'New' : (sale ? 'Sale' : (p.is_bestseller == 1 ? 'Bestseller' : ''));
    const picture = Img.picture(p.image_url, 'card', { alt: p.name, loading: 'lazy' });
    return `
      <article class="product-card" data-id="${p.id}">
        <div class="product-image">
          <a href="product.html?sku=${escape(p.sku)}">${picture}</a>
          ${badge ? `<span class="product-badge ${badge}">${badgeText}</span>` : ''}
          <button class="wishlist-btn" data-product="${p.id}" aria-label="Save">♡</button>
        </div>
        <div class="product-info">
          <span class="product-cat">${escape(p.category)}${p.length_mm ? ' · ' + p.length_mm + 'mm' : ''}</span>
          <h3><a href="product.html?sku=${escape(p.sku)}" style="color:inherit;">${escape(p.name)}</a></h3>
          <p>${escape(p.short_description || '')}</p>
          <div class="product-rating">★★★★★ <span class="reviews">(${100 + (p.id * 7)})</span></div>
          <div class="product-price-row">
            <span class="price">${money(p.price)}</span>
            ${sale ? `<span class="price-old">${money(p.compare_at_price)}</span>` : ''}
          </div>
          <button class="button button-primary button-block add-btn"
                  data-sku="${escape(p.sku)}" data-name="${escape(p.name)}"
                  data-price="${p.price}" data-image="${escape(p.image_url)}">
            Add to Cart
          </button>
        </div>
      </article>`;
  }

  async function loadFeatured() {
    const container = document.getElementById('featured-products');
    if (!container) return;
    const limit = parseInt(container.dataset.limit || '4', 10);
    try {
      const r = await fetch('api/products.php');
      const j = await r.json();
      // 优先 bestseller，其次 new，再按排序
      const products = (j.products || [])
        .sort((a, b) => (b.is_bestseller || 0) - (a.is_bestseller || 0))
        .slice(0, limit);
      container.innerHTML = products.map(productCard).join('');
    } catch (e) {
      container.innerHTML = '<p class="muted text-center" style="grid-column:1/-1;">Failed to load products.</p>';
    }
  }

  async function loadFeaturedVideos() {
    const container = document.getElementById('featured-videos');
    if (!container) return;
    try {
      const r = await fetch('api/videos.php?featured=1&limit=4');
      const j = await r.json();
      const videos = j.videos || [];
      if (videos.length === 0) {
        container.innerHTML = `
          <div class="empty-state" style="grid-column:1/-1;">
            <div class="icon">🎬</div>
            <p class="muted">No videos yet — partnering with creators soon!</p>
            <a href="videos.html" class="button button-outline" style="margin-top:1rem;">Visit TikTok page →</a>
          </div>`;
        // 加载 TikTok embed.js
        if (!document.querySelector('script[src*="tiktok.com/embed.js"]')) {
          const s = document.createElement('script');
          s.async = true; s.src = 'https://www.tiktok.com/embed.js';
          document.body.appendChild(s);
        }
        return;
      }
      container.innerHTML = videos.map((v) => `
        <div style="background: var(--bg-card); border: 1px solid var(--border-soft); border-radius: var(--radius-lg); overflow: hidden;">
          <div style="aspect-ratio: 9/16; background: var(--bg);">
            <iframe src="https://www.tiktok.com/embed/v2/${escape(v.video_id)}" style="width:100%; height:100%; border:0;" allowfullscreen scrolling="no"></iframe>
          </div>
          <div style="padding: 1rem;">
            <p style="color: var(--gold); font-size: .85rem;">@${escape(v.creator_handle)}</p>
            ${v.title ? `<p style="color: var(--cream); margin-top:.25rem;">${escape(v.title)}</p>` : ''}
          </div>
        </div>`).join('');
      // load tiktok embed script
      if (!document.querySelector('script[src*="tiktok.com/embed.js"]')) {
        const s = document.createElement('script');
        s.async = true; s.src = 'https://www.tiktok.com/embed.js';
        document.body.appendChild(s);
      }
    } catch (e) {
      container.innerHTML = '<p class="muted text-center" style="grid-column:1/-1;">Failed to load videos.</p>';
    }
  }

  async function loadSettings() {
    try {
      const settings = await fetch('api/settings.php').then(r => r.json());
      // Hero slideshow
      let heroImages = [];
      if (settings.hero_image_urls) {
        try {
          const arr = JSON.parse(settings.hero_image_urls);
          if (Array.isArray(arr) && arr.length) heroImages = arr;
        } catch {}
      }
      if (!heroImages.length && settings.hero_image_url) {
        heroImages = [settings.hero_image_url];
      }
      const slidesEl = document.getElementById('hero-slides');
      const dotsEl = document.getElementById('hero-dots');
      if (slidesEl && heroImages.length) {
        // 浏览器 webp 支持探测(2010年后基本都有,但保留兜底)
        const supportsWebp = (() => {
          try { return document.createElement('canvas').toDataURL('image/webp').indexOf('data:image/webp') === 0; }
          catch (e) { return false; }
        })();
        // hero 是模糊背景 + 文字浮在上面,中等像素密度足够。
        // 窄屏(<768 css px)无论 dpr 都用 640(~50KB);桌面用 1024(~165KB);超宽 4K 才上 1600。
        const cssW = window.innerWidth;
        const heroSize = cssW < 768 ? 640 : (cssW < 1600 ? 1024 : 1600);
        const ext = supportsWebp ? 'webp' : 'jpg';
        const heroVariant = (u) => {
          if (/^https?:\/\//i.test(u)) return u;
          return Img.variant(u, heroSize, ext);
        };
        slidesEl.innerHTML = heroImages.map((url, i) =>
          `<div class="hero-slide ${i===0?'active':''}" style="background-image: url('${escape(heroVariant(url))}');"></div>`
        ).join('');
        if (dotsEl && heroImages.length > 1) {
          dotsEl.innerHTML = heroImages.map((_, i) =>
            `<button class="hero-dot ${i===0?'active':''}" data-idx="${i}" aria-label="Slide ${i+1}"></button>`
          ).join('');
          // 自动轮播
          let cur = 0;
          const slides = slidesEl.querySelectorAll('.hero-slide');
          const dots = dotsEl.querySelectorAll('.hero-dot');
          const interval = parseInt(settings.hero_slide_interval, 10) || 5000;
          function go(i) {
            slides[cur].classList.remove('active'); dots[cur].classList.remove('active');
            cur = (i + slides.length) % slides.length;
            slides[cur].classList.add('active'); dots[cur].classList.add('active');
          }
          let timer = setInterval(() => go(cur + 1), interval);
          dots.forEach(d => d.addEventListener('click', () => {
            clearInterval(timer); go(parseInt(d.dataset.idx, 10));
            timer = setInterval(() => go(cur + 1), interval);
          }));
        }
      }
      // Social icons
      const social = document.getElementById('social-icons');
      if (social) {
        const icons = {
          social_tiktok:    { icon: '🎵', label: 'TikTok' },
          social_instagram: { icon: '📷', label: 'Instagram' },
          social_youtube:   { icon: '▶',  label: 'YouTube' },
          social_pinterest: { icon: '📌', label: 'Pinterest' },
          social_facebook:  { icon: 'f',  label: 'Facebook' },
        };
        const html = Object.entries(icons)
          .filter(([k]) => settings[k])
          .map(([k, v]) => `<a href="${settings[k]}" target="_blank" rel="noopener" aria-label="${v.label}" class="social-icon">${v.icon}</a>`)
          .join('');
        // Amazon
        if (settings.amazon_status === 'live' && settings.amazon_store_url) {
          social.innerHTML = html + `<a href="${settings.amazon_store_url}" target="_blank" rel="noopener" class="social-icon" aria-label="Amazon">🛒</a>`;
        } else if (settings.amazon_status === 'coming_soon') {
          social.innerHTML = html + `<span class="social-icon disabled" title="Amazon Coming Soon">🛒</span>`;
        } else {
          social.innerHTML = html;
        }
      }
    } catch (e) { /* graceful degrade */ }
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadFeatured();
    loadFeaturedVideos();
    loadSettings();

    // Add to cart delegation
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.add-btn');
      if (btn) {
        e.preventDefault();
        window.GlamEye.Cart.add({
          sku: btn.dataset.sku, name: btn.dataset.name,
          price: parseFloat(btn.dataset.price), image: btn.dataset.image, quantity: 1,
        });
      }
      const wish = e.target.closest('.wishlist-btn');
      if (wish) {
        e.preventDefault();
        const auth = window.GlamEye.Auth;
        if (!auth.isLoggedIn()) {
          window.GlamEye.Notification.show('Sign in to save items', 'error');
          setTimeout(() => window.location.href = '/login.html?redirect=' + encodeURIComponent(location.pathname), 800);
          return;
        }
        try {
          await fetch('api/wishlist.php', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: parseInt(wish.dataset.product, 10) }),
          });
          wish.classList.add('active');
          wish.textContent = '♥';
          window.GlamEye.Notification.show('Saved to wishlist ♥');
        } catch (err) {
          window.GlamEye.Notification.show('Save failed', 'error');
        }
      }
    });
  });
})();
