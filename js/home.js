// Homepage: load featured products
(function () {
  'use strict';
  const fmt = window.GlamEye.Fmt;
  function escape(s) { return fmt.escape(s); }
  function money(n)  { return fmt.money(n); }

  function card(p) {
    const sale = p.compare_at_price && Number(p.compare_at_price) > Number(p.price);
    return `
      <article class="product-card" data-id="${p.id}">
        <div class="product-image">
          <a href="product.html?sku=${escape(p.sku)}">
            <img src="${escape(p.image_url)}" alt="${escape(p.name)}" loading="lazy" />
          </a>
          ${sale ? '<span class="product-badge sale">Sale</span>' : ''}
          <button class="wishlist-btn" data-product="${p.id}" aria-label="Save">♡</button>
        </div>
        <div class="product-info">
          <span class="product-cat">${escape(p.category)}</span>
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
    const limit = parseInt(container.dataset.limit || '3', 10);
    try {
      const r = await fetch('api/products.php');
      const j = await r.json();
      const products = (j.products || []).slice(0, limit);
      container.innerHTML = products.map(card).join('');
    } catch (e) {
      container.innerHTML = '<p class="muted text-center">Failed to load featured products.</p>';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadFeatured();

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
