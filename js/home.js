// ============================================================
// GlamEye - Homepage (load products dynamically by category)
// ============================================================
(function () {
  'use strict';

  function escapeHtml(s) { return window.GlamEye.Fmt.escape(s); }
  function money(n)      { return window.GlamEye.Fmt.money(n); }

  function productCard(p) {
    const sale = p.compare_at_price && Number(p.compare_at_price) > Number(p.price);
    const badge = p.stock < 30 ? 'sale' : (p.id <= 3 ? 'bestseller' : '');
    const badgeText = p.stock < 30 ? 'Limited' : (p.id <= 3 ? 'Bestseller' : '');
    return `
      <article class="product-card" data-sku="${escapeHtml(p.sku)}" data-id="${p.id}">
        <div class="product-image">
          <img src="${escapeHtml(p.image_url)}" alt="${escapeHtml(p.name)}" loading="lazy" width="800" height="600" />
          ${badge ? `<span class="product-badge ${badge}">${badgeText}</span>` : ''}
          <button class="wishlist-btn" data-product="${p.id}" aria-label="Add to wishlist">♥</button>
        </div>
        <div class="product-info">
          <span class="product-cat">${escapeHtml(p.category)}</span>
          <h3>${escapeHtml(p.name)}</h3>
          <p>${escapeHtml(p.short_description || '')}</p>
          <div class="product-rating">★★★★★ <span class="reviews">(${100 + (p.id * 7)})</span></div>
          <div class="product-price-row">
            <span class="price">${money(p.price)}</span>
            ${sale ? `<span class="price-old">${money(p.compare_at_price)}</span>` : ''}
          </div>
          <button class="button button-primary button-block add-btn"
                  data-sku="${escapeHtml(p.sku)}"
                  data-name="${escapeHtml(p.name)}"
                  data-price="${p.price}"
                  data-image="${escapeHtml(p.image_url)}">
            Add to Cart
          </button>
        </div>
      </article>`;
  }

  async function loadCategory(category, container) {
    try {
      const r = await fetch('api/products.php?category=' + category);
      const j = await r.json();
      const products = j.products || [];
      if (products.length === 0) {
        container.innerHTML = '<p class="muted text-center">No products available.</p>';
        return;
      }
      container.innerHTML = products.map(productCard).join('');
    } catch (e) {
      container.innerHTML = '<p class="error">Failed to load products.</p>';
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    // 加载每个分类的产品
    document.querySelectorAll('.product-grid[data-category]').forEach((g) => {
      loadCategory(g.dataset.category, g);
    });

    // 委托：加车 + wishlist
    document.body.addEventListener('click', async (e) => {
      const btn = e.target.closest('.add-btn');
      if (btn) {
        e.preventDefault();
        window.GlamEye.Cart.add({
          sku: btn.dataset.sku,
          name: btn.dataset.name,
          price: parseFloat(btn.dataset.price),
          image: btn.dataset.image,
          quantity: 1,
        });
      }

      const wish = e.target.closest('.wishlist-btn');
      if (wish) {
        e.preventDefault();
        const auth = window.GlamEye.Auth;
        if (!auth.isLoggedIn()) {
          window.GlamEye.Notification.show('Please sign in to save items.', 'error');
          setTimeout(() => window.location.href = '/login.html?redirect=' + encodeURIComponent(location.pathname + location.hash), 800);
          return;
        }
        try {
          const r = await fetch('api/wishlist.php', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: parseInt(wish.dataset.product, 10) })
          });
          const j = await r.json();
          if (j.success) {
            wish.classList.add('active');
            window.GlamEye.Notification.show('Saved to wishlist ♥');
          }
        } catch (err) {
          window.GlamEye.Notification.show('Failed to save', 'error');
        }
      }
    });
  });
})();
