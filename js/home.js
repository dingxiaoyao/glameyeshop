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
    // Stock urgency: 库存 < 20 显示 "Only X left"。P2 接真实 reviews 后,(100 + id*7) 的伪评论数会替换。
    const stockUrgent = (p.stock != null && p.stock > 0 && Number(p.stock) < 20);
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
          <p class="product-quick-bullet">${escape(p.short_description || '')}</p>
          <div class="product-rating">★★★★★ <span class="reviews">(${100 + (p.id * 7)})</span></div>
          ${stockUrgent ? `<span class="product-stock-urgency">Only ${p.stock} left</span>` : ''}
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
        const supportsWebp = (() => {
          try { return document.createElement('canvas').toDataURL('image/webp').indexOf('data:image/webp') === 0; }
          catch (e) { return false; }
        })();
        const cssW = window.innerWidth;
        const heroSize = cssW < 768 ? 640 : (cssW < 1600 ? 1024 : 1600);
        const ext = supportsWebp ? 'webp' : 'jpg';
        const variantOf = (u) => {
          if (/^https?:\/\//i.test(u)) return u;
          return Img.variant(u, heroSize, ext);
        };

        // 先用原图渲染所有 slide(任何情况下都能看到图);
        // 然后逐个 new Image() 预加载 variant,加载成功才替换为更轻的版本;
        // variant 404/失败时保持原图,不破图。
        slidesEl.innerHTML = heroImages.map((url, i) =>
          `<div class="hero-slide ${i===0?'active':''}" data-orig="${escape(url)}" style="background-image: url('${escape(url)}');"></div>`
        ).join('');

        // 后台升级:variant 加载成功后无缝替换
        slidesEl.querySelectorAll('.hero-slide').forEach((slide) => {
          const orig = slide.dataset.orig;
          const v = variantOf(orig);
          if (v === orig) return; // 远程 URL,不变体
          const probe = new Image();
          probe.onload = () => { slide.style.backgroundImage = `url('${v}')`; };
          probe.onerror = () => {/* 保持原图,啥也不做 */};
          probe.src = v;
        });
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

  // P2: 真实 bundles —— 从 DB 拿 is_bundle=1 的产品,bundle_items JSON 内含组件 SKU
  // 加车走标准流程:bundle 本身是一行 product,price 已经是套装价,组件仅作展示
  async function loadBundles() {
    const container = document.getElementById('featured-bundles');
    if (!container) return;
    try {
      const [bundlesRes, allRes] = await Promise.all([
        fetch('api/products.php?bundles_only=1').then(r => r.json()),
        fetch('api/products.php?include_bundles=1').then(r => r.json()),
      ]);
      const bundles = (bundlesRes.products || []).slice(0, 3);
      const allProducts = allRes.products || [];
      const bySku = Object.fromEntries(allProducts.map(p => [p.sku, p]));

      if (!bundles.length) {
        container.innerHTML = '<p class="muted text-center" style="grid-column:1/-1;">Curated bundles coming soon.</p>';
        return;
      }

      container.innerHTML = bundles.map((b) => {
        let items = [];
        try { items = JSON.parse(b.bundle_items || '[]'); } catch { items = []; }
        const components = items.map(it => bySku[it.sku]).filter(Boolean);
        // 计算原价 & 节省
        const listTotal = items.reduce((s, it) => {
          const p = bySku[it.sku];
          return p ? s + Number(p.price) * (Number(it.qty) || 1) : s;
        }, 0);
        const bundlePrice = Number(b.price);
        const savings = Math.max(0, listTotal - bundlePrice);

        const productsList = components.map(p => p.name).join(' · ');
        const imgs = components.slice(0, 3).map(p =>
          `<div>${Img.picture(p.image_url, 'card', { alt: p.name, loading: 'lazy' })}</div>`
        ).join('');

        return `
          <a class="bundle-card" href="product.html?sku=${escape(b.sku)}">
            ${savings > 0 ? `<span class="bundle-savings-badge">Save $${savings.toFixed(0)}</span>` : ''}
            <div class="bundle-card-imgs">${imgs}</div>
            <div class="bundle-card-info">
              <h3>${escape(b.name)}</h3>
              <p class="bundle-products">${escape(productsList)}</p>
              <p style="color: var(--text-muted); font-size: .85rem; line-height: 1.5;">${escape(b.short_description || '')}</p>
              <div class="bundle-price-row">
                <span class="price">${money(bundlePrice)}</span>
                ${listTotal > bundlePrice ? `<span class="price-old">${money(listTotal)}</span>` : ''}
                ${savings > 0 ? `<span class="bundle-saved">— save $${savings.toFixed(0)}</span>` : ''}
              </div>
            </div>
          </a>`;
      }).join('');
    } catch (e) {
      container.innerHTML = '<p class="muted text-center" style="grid-column:1/-1;">Curated bundles coming soon.</p>';
    }
  }

  // P2: 真实 featured reviews 替换硬编码 3 卡(管理员在 admin/reviews.php 标记 is_featured)
  async function loadFeaturedReviews() {
    const container = document.getElementById('featured-reviews');
    if (!container) return;
    try {
      const r = await fetch('api/reviews.php?featured=1&limit=3');
      const j = await r.json();
      const reviews = j.reviews || [];
      // 没 featured 评论时,保留 HTML 里的 fallback 静态内容(已 SSR 在 index.html)。
      if (!reviews.length) return;
      container.innerHTML = reviews.map(rv => {
        const stars = '★'.repeat(rv.rating) + '☆'.repeat(5 - rv.rating);
        const author = `${rv.reviewer_name}${rv.reviewer_location ? ', ' + rv.reviewer_location : ''}${rv.is_verified_buyer == 1 ? ' · Verified Buyer' : ''}`;
        return `
          <div class="review-card">
            <div class="review-stars">${stars}</div>
            <p class="review-text">"${escape(rv.body)}"</p>
            <p class="review-author">— ${escape(author)}</p>
          </div>`;
      }).join('');
    } catch (e) { /* graceful — keep SSR fallback */ }
  }

  // P2: UGC 墙 — 客户晒图,管理员审核后展示
  async function loadUGC() {
    const container = document.getElementById('ugc-grid');
    if (!container) return;
    try {
      const r = await fetch('api/ugc.php?limit=12');
      const j = await r.json();
      const items = j.items || [];
      if (!items.length) {
        container.innerHTML = `<p class="muted text-center" style="grid-column:1/-1; padding: 2rem 0;">
          Be the first to be featured — tag <strong style="color:var(--gold);">@glameye</strong> on Instagram!
        </p>`;
        return;
      }
      container.innerHTML = items.map(u => {
        const link = u.product_sku ? `product.html?sku=${escape(u.product_sku)}` : (u.instagram_handle ? `https://instagram.com/${escape(u.instagram_handle)}` : '#');
        const target = u.product_sku ? '' : ' target="_blank" rel="noopener"';
        return `
          <a class="ugc-tile" href="${link}"${target}>
            <img src="${escape(u.image_url)}" alt="${escape(u.caption || '')}" loading="lazy">
            ${u.instagram_handle ? `<span class="ugc-tile-handle">@${escape(u.instagram_handle)}</span>` : ''}
            ${u.caption ? `<div class="ugc-tile-overlay">${escape(u.caption)}</div>` : ''}
          </a>`;
      }).join('');
    } catch (e) {
      container.innerHTML = '<p class="muted text-center" style="grid-column:1/-1;">Photos coming soon.</p>';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadFeatured();
    loadBundles();
    loadFeaturedReviews();
    loadUGC();
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
