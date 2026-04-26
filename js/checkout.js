// ============================================================
// GlamEye - Checkout Page
// ============================================================
(function () {
  'use strict';
  const $ = (s) => document.querySelector(s);
  const fmt = (n) => '$' + Number(n).toFixed(2);

  function renderCart() {
    const cart = window.GlamEye?.Cart;
    if (!cart) return;
    const list = $('#cart-list');
    const summary = $('#summary-items');

    if (cart.items.length === 0) {
      list.innerHTML = '<p class="cart-empty">Your cart is empty. <a href="/#shop">Browse our collection →</a></p>';
      summary.innerHTML = '<p class="muted small">No items</p>';
      $('#subtotal').textContent = '$0.00';
      $('#shipping').textContent = '$0.00';
      $('#tax').textContent = '$0.00';
      $('#total').textContent = '$0.00';
      return;
    }

    list.innerHTML = cart.items.map((it) => `
      <div class="cart-row" data-sku="${esc(it.sku)}">
        <img src="${esc(it.image)}" alt="${esc(it.name)}" />
        <div class="cart-row-name">
          <strong>${esc(it.name)}</strong>
          <span class="muted small">${fmt(it.price)} each</span>
        </div>
        <div class="cart-row-qty">
          <button type="button" class="qty-btn" data-action="dec">−</button>
          <input type="number" class="qty-input" min="1" max="50" value="${it.quantity}" />
          <button type="button" class="qty-btn" data-action="inc">+</button>
        </div>
        <div class="cart-row-total">${fmt(it.price * it.quantity)}</div>
        <button type="button" class="cart-row-remove" aria-label="Remove">×</button>
      </div>`).join('');

    summary.innerHTML = cart.items.map((it) => `
      <div class="summary-row">
        <span>${esc(it.name)} × ${it.quantity}</span>
        <span>${fmt(it.price * it.quantity)}</span>
      </div>`).join('');

    const subtotal = cart.subtotal();
    const shipping = subtotal >= 50 ? 0 : 5.99;
    const tax = 0;
    const total = subtotal + shipping + tax;
    $('#subtotal').textContent = fmt(subtotal);
    $('#shipping').textContent = subtotal >= 50 ? 'FREE' : fmt(shipping);
    $('#tax').textContent = fmt(tax);
    $('#total').textContent = fmt(total);
  }

  function esc(s) { return window.GlamEye.Fmt.escape(s); }

  function bindCart() {
    $('#cart-list').addEventListener('click', (e) => {
      const cart = window.GlamEye?.Cart;
      const row = e.target.closest('.cart-row');
      if (!row || !cart) return;
      const sku = row.dataset.sku;
      if (e.target.matches('.qty-btn[data-action="inc"]')) {
        const it = cart.items.find((i) => i.sku === sku);
        if (it) cart.setQuantity(sku, it.quantity + 1);
      } else if (e.target.matches('.qty-btn[data-action="dec"]')) {
        const it = cart.items.find((i) => i.sku === sku);
        if (it) cart.setQuantity(sku, it.quantity - 1);
      } else if (e.target.matches('.cart-row-remove')) {
        cart.remove(sku);
      }
    });
    $('#cart-list').addEventListener('change', (e) => {
      if (e.target.matches('.qty-input')) {
        const cart = window.GlamEye?.Cart;
        const row = e.target.closest('.cart-row');
        if (cart && row) cart.setQuantity(row.dataset.sku, e.target.value);
      }
    });
    window.addEventListener('cart:update', renderCart);
  }

  function bindPaymentNote() {
    const note = $('#payment-note');
    if (!note) return;
    document.querySelectorAll('input[name="payment_method"]').forEach((r) => {
      r.addEventListener('change', function () {
        note.textContent = this.value === 'paypal'
          ? 'You will be redirected to PayPal to complete payment.'
          : 'Secure 256-bit SSL encryption. We never store your card details.';
      });
    });
  }

  function bindSubmit() {
    const form = $('#checkout-form');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fb = $('#checkout-feedback');
      const btn = form.querySelector('.submit-btn');
      const cart = window.GlamEye?.Cart;

      if (!cart || cart.items.length === 0) {
        fb.textContent = 'Your cart is empty.';
        fb.className = 'form-feedback error';
        return;
      }
      // 防御:cart 数据有任何 price/quantity 异常时阻止下单,避免 0 元订单
      const bad = cart.items.find((i) => !(Number(i.price) > 0) || !(Number(i.quantity) > 0));
      if (bad) {
        fb.textContent = 'Cart contains an invalid item — please remove it or refresh the page.';
        fb.className = 'form-feedback error';
        return;
      }
      if (!(cart.subtotal() > 0)) {
        fb.textContent = 'Cart total is $0 — please re-add the item.';
        fb.className = 'form-feedback error';
        return;
      }
      if (!form.checkValidity()) { form.reportValidity(); return; }

      btn.disabled = true;
      btn.querySelector('.btn-text').hidden = true;
      btn.querySelector('.btn-loading').hidden = false;
      fb.textContent = '';

      const fd = new FormData(form);
      const payload = {
        customer_name:  fd.get('customer_name'),
        email:          fd.get('email'),
        phone:          fd.get('phone'),
        address:        fd.get('address'),
        address_line2:  fd.get('address_line2'),
        city:           fd.get('city'),
        state:          fd.get('state'),
        postal_code:    fd.get('postal_code'),
        country:        'US',
        notes:          fd.get('notes'),
        payment_method: fd.get('payment_method'),
        items: cart.items.map((it) => ({
          sku: it.sku, product_name: it.name, quantity: it.quantity,
        })),
      };

      try {
        const r = await fetch('api/create-order.php', {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (j.success && j.order_id) {
          sessionStorage.setItem('glameye_last_order', JSON.stringify({
            order_id: j.order_id, email: payload.email,
          }));
          cart.clear();
          window.location.href = j.next || ('/order-success.html?order_id=' + j.order_id);
        } else {
          fb.textContent = j.error || 'Order failed. Please try again.';
          fb.className = 'form-feedback error';
          resetBtn();
        }
      } catch (err) {
        fb.textContent = 'Network error: ' + err.message;
        fb.className = 'form-feedback error';
        resetBtn();
      }

      function resetBtn() {
        btn.disabled = false;
        btn.querySelector('.btn-text').hidden = false;
        btn.querySelector('.btn-loading').hidden = true;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    renderCart();
    bindCart();
    bindPaymentNote();
    bindSubmit();
  });
})();
