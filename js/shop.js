// Shop page: list products with category filter
(function () {
  'use strict';
  const fmt = window.GlamEye.Fmt;
  const escape = (s) => fmt.escape(s);
  const money  = (n) => fmt.money(n);

  function card(p) {
    const sale = p.compare_at_price && Number(p.compare_at_price) > Number(p.price);
    const badge = p.is_new == 1 ? 'new' : (sale ? 'sale' : (p.is_bestseller == 1 ? 'bestseller' : ''));
    const badgeText = p.is_new == 1 ? 'New' : (sale ? 'Sale' : (p.is_bestseller == 1 ? 'Bestseller' : ''));
    return `
      <article class="product-card" data-id="${p.id}">
        <div class="product-image">
          <a href="product.html?sku=${escape(p.sku)}">
            <img src="${escape(p.image_url)}" alt="${escape(p.name)}" loading="lazy" />
          </a>
          ${badge ? `<span class="product-badge ${badge}">${badgeText}</span>` : ''}
          <button class="wishlist-btn" data-product="${p.id}">♡</button>
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

  async function loadProducts(category) {
    const container = document.getElementById('all-products');
    container.innerHTML = '<p class="muted text-center" style="grid-column: 1/-1;">Loading…</p>';
    try {
      const url = 'api/products.php' + (category ? '?category=' + category : '');
      const r = await fetch(url);
      const j = await r.json();
      const products = j.products || [];
      if (products.length === 0) {
        container.innerHTML = '<div class="empty-state" style="grid-column:1/-1;"><div class="icon">🔍</div><p>No products in this category.</p></div>';
        return;
      }
      container.innerHTML = products.map(card).join('');
    } catch (e) {
      container.innerHTML = '<p class="muted text-center" style="grid-column: 1/-1;">Failed to load.</p>';
    }
  }

  function setActiveFilter(cat) {
    document.querySelectorAll('.filter-chip').forEach((c) => c.classList.remove('active'));
    const target = document.querySelector(`.filter-chip[data-cat="${cat}"]`);
    if (target) target.classList.add('active');
    const titles = { mink: 'Mink Lashes', faux: 'Faux Mink (Vegan)', magnetic: 'Magnetic Lashes', tools: 'Tools & Accessories', '': 'Shop the Collection' };
    document.getElementById('shop-title').textContent = titles[cat] || titles[''];
  }

  document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(location.search);
    const initial = params.get('category') || '';
    setActiveFilter(initial);
    loadProducts(initial);

    document.getElementById('shop-filters').addEventListener('click', (e) => {
      const btn = e.target.closest('.filter-chip');
      if (!btn) return;
      const cat = btn.dataset.cat;
      const newUrl = cat ? `?category=${cat}` : location.pathname;
      history.replaceState(null, '', newUrl);
      setActiveFilter(cat);
      loadProducts(cat);
    });

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
