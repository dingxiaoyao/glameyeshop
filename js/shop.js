// Shop page: list products with category filter
(function () {
  'use strict';
  const fmt = window.GlamEye.Fmt;
  const Img = window.GlamEye.Img;
  const escape = (s) => fmt.escape(s);
  const money  = (n) => fmt.money(n);

  function card(p) {
    const sale = p.compare_at_price && Number(p.compare_at_price) > Number(p.price);
    const badge = p.is_new == 1 ? 'new' : (sale ? 'sale' : (p.is_bestseller == 1 ? 'bestseller' : ''));
    const badgeText = p.is_new == 1 ? 'New' : (sale ? 'Sale' : (p.is_bestseller == 1 ? 'Bestseller' : ''));
    const picture = Img.picture(p.image_url, 'card', { alt: p.name, loading: 'lazy' });
    // hover 第二张图(gallery_urls 第 1 张)
    let gal = p.gallery_urls;
    if (typeof gal === 'string' && gal.trim()) { try { gal = JSON.parse(gal); } catch { gal = []; } }
    const hoverUrl = (Array.isArray(gal) && gal[0]) || '';
    const hoverPic = hoverUrl ? Img.picture(hoverUrl, 'card', { alt: p.name + ' alternate', loading: 'lazy' }) : '';
    // TikTok Shop 深链存在时:卡右下角出现 🎵 小徽章,点击直跳 TikTok Shop(阻断卡片默认跳详情)
    const ttUrl = (p.tiktok_shop_url || '').trim();
    const ttBadge = ttUrl ? `
      <a class="tiktok-shop-badge" href="${escape(ttUrl)}" target="_blank" rel="noopener nofollow"
         data-sku="${escape(p.sku)}"
         title="Also on TikTok Shop"
         onclick="event.stopPropagation();">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5.8 20.1a6.34 6.34 0 0 0 10.86-4.43V8.85a8.16 8.16 0 0 0 4.77 1.52V6.93a4.85 4.85 0 0 1-1.84-.24z"/></svg>
      </a>` : '';
    return `
      <article class="product-card" data-id="${p.id}">
        <div class="product-image${hoverPic ? ' has-hover' : ''}">
          <a href="product.html?sku=${escape(p.sku)}">
            <span class="product-image-main">${picture}</span>
            ${hoverPic ? `<span class="product-image-hover">${hoverPic}</span>` : ''}
          </a>
          ${badge ? `<span class="product-badge ${badge}">${badgeText}</span>` : ''}
          <button class="wishlist-btn" data-product="${p.id}">♡</button>
          ${ttBadge}
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

  function applySparseClass(container, n) {
    container.classList.remove('sparse', 'sparse-1', 'sparse-2');
    if (n === 1) container.classList.add('sparse-1');
    else if (n === 2) container.classList.add('sparse-2');
    else if (n <= 4) container.classList.add('sparse');
  }

  async function loadProducts(category) {
    const container = document.getElementById('all-products');
    container.classList.remove('sparse','sparse-1','sparse-2');
    container.innerHTML = '<p class="muted text-center" style="grid-column: 1/-1;">Loading…</p>';
    try {
      const url = 'api/products.php' + (category ? '?category=' + category : '');
      const r = await fetch(url);
      const j = await r.json();
      let products = j.products || [];
      // 支持按风格筛选 — 优先按 p.style 字段(natural / wispy / dramatic / volume / cat-eye / glamour),
      // 兼容旧 ?style=glam(按 length_mm 兜底,给老链接续命)
      const params = new URLSearchParams(location.search);
      const style = (params.get('style') || '').toLowerCase();
      if (style) {
        const styleMatch = products.filter(p => (p.style || '').toLowerCase() === style);
        if (styleMatch.length > 0) {
          products = styleMatch;
        } else if (style === 'natural') {
          products = products.filter(p => Number(p.length_mm) > 0 && Number(p.length_mm) <= 18);
        } else if (style === 'glam') {
          products = products.filter(p => Number(p.length_mm) >= 20);
        } else {
          products = []; // 未知 style 显示空
        }
      }
      if (products.length === 0) {
        container.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><div class="icon">🔍</div><p>No products in this filter.</p><p class="muted small">Try clearing filters above.</p></div>';
        return;
      }
      applySparseClass(container, products.length);
      container.innerHTML = products.map(card).join('');
    } catch (e) {
      container.innerHTML = '<p class="muted text-center" style="grid-column: 1/-1;">Failed to load.</p>';
    }
  }

  function setActiveFilter(cat) {
    document.querySelectorAll('.filter-chip').forEach((c) => c.classList.remove('active'));
    const target = document.querySelector(`.filter-chip[data-cat="${cat}"]`);
    if (target) target.classList.add('active');
    // 标题随 category 或 style 切换 — style 优先(更精准)
    const params = new URLSearchParams(location.search);
    const style = (params.get('style') || '').toLowerCase();
    const titles = {
      'cluster-kit': 'All Cluster Kits',
      mink: 'Mink Lashes', faux: 'Faux Mink (Vegan)', magnetic: 'Magnetic Lashes',
      tools: 'Tools & Accessories', '': 'Shop the Collection',
    };
    const styleTitles = {
      natural: 'Natural Everyday Kits',
      wispy:   'Wispy & Fluffy Kits',
      dramatic:'Drama & Fox Eye Kits',
    };
    document.getElementById('shop-title').textContent = styleTitles[style] || titles[cat] || titles[''];
  }

  function setActiveStyle(style) {
    document.querySelectorAll('.filter-chip').forEach((c) => c.classList.remove('active'));
    const target = style
      ? document.querySelector(`.filter-chip[data-style="${style}"]`)
      : document.querySelector('.filter-chip[data-cat=""]');
    if (target) target.classList.add('active');
  }

  document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(location.search);
    const initialCat   = params.get('category') || '';
    const initialStyle = (params.get('style') || '').toLowerCase();
    if (initialStyle) setActiveStyle(initialStyle);
    else setActiveFilter(initialCat);
    loadProducts(initialCat);

    document.getElementById('shop-filters').addEventListener('click', (e) => {
      const btn = e.target.closest('.filter-chip');
      if (!btn) return;
      const cat   = btn.dataset.cat   || '';
      const style = btn.dataset.style || '';
      // 按 style 筛选(纯前端,不传给 API)
      const sp = new URLSearchParams();
      if (cat) sp.set('category', cat);
      if (style) sp.set('style', style);
      const qs = sp.toString();
      history.replaceState(null, '', qs ? '?' + qs : location.pathname);
      if (style) setActiveStyle(style);
      else setActiveFilter(cat);
      loadProducts(cat);
    });

    // Lash 101 guide cards:点击跳到对应分类 + 滚回顶部
    document.querySelectorAll('.guide-card').forEach((card) => {
      card.addEventListener('click', () => {
        const cat = card.dataset.cat || '';
        const style = card.dataset.style || '';
        const sp = new URLSearchParams();
        if (cat) sp.set('category', cat);
        if (style) sp.set('style', style);
        const qs = sp.toString();
        history.replaceState(null, '', qs ? '?' + qs : location.pathname);
        setActiveFilter(cat);
        loadProducts(cat);
        document.querySelector('.shop-filters').scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    // Newsletter form(本来在 home 由 app.js 接管,但 app.js 是统一注册的,无需重复)

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
      if (wish && !window.GlamEye.Auth.isLoggedIn()) {
        window.GlamEye.Notification.show('Sign in to save items', 'error');
        setTimeout(() => window.location.href = '/login.html?redirect=' + encodeURIComponent(location.pathname + location.search), 800);
      } else if (wish) {
        try {
          await fetch('api/wishlist.php', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: parseInt(wish.dataset.product, 10) }),
          });
          wish.classList.add('active'); wish.textContent = '♥';
          window.GlamEye.Notification.show('Saved ♥');
        } catch (err) { window.GlamEye.Notification.show('Failed', 'error'); }
      }
    });
  });
})();
