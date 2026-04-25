// ============================================================
// GlamEye - 结账页脚本（购物车渲染 + AJAX 提交）
// ============================================================
(function () {
  'use strict';

  function $(sel) { return document.querySelector(sel); }
  function fmt(n) { return '¥' + Number(n).toFixed(2); }

  function renderCart() {
    const cart = window.GlamEye?.Cart;
    if (!cart) return;
    const list = $('#cart-list');
    const summary = $('#summary-items');

    if (cart.items.length === 0) {
      list.innerHTML = '<p class="cart-empty">购物车为空。<a href="index.html#products">去挑选商品 →</a></p>';
      summary.innerHTML = '<p class="muted small">无商品</p>';
      $('#subtotal').textContent = '¥0.00';
      $('#total').textContent = '¥0.00';
      return;
    }

    list.innerHTML = cart.items.map((it) => `
      <div class="cart-row" data-name="${escapeHtml(it.name)}">
        <div class="cart-row-name">
          <strong>${escapeHtml(it.name)}</strong>
          <span class="muted small">${fmt(it.price)} / 件</span>
        </div>
        <div class="cart-row-qty">
          <button type="button" class="qty-btn" data-action="dec">−</button>
          <input type="number" class="qty-input" min="1" max="50" value="${it.quantity}" />
          <button type="button" class="qty-btn" data-action="inc">+</button>
        </div>
        <div class="cart-row-total">${fmt(it.price * it.quantity)}</div>
        <button type="button" class="cart-row-remove" aria-label="移除">×</button>
      </div>
    `).join('');

    summary.innerHTML = cart.items.map((it) => `
      <div class="summary-row">
        <span>${escapeHtml(it.name)} × ${it.quantity}</span>
        <span>${fmt(it.price * it.quantity)}</span>
      </div>
    `).join('');

    const total = cart.total();
    $('#subtotal').textContent = fmt(total);
    $('#total').textContent = fmt(total);
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function bindCartEvents() {
    $('#cart-list').addEventListener('click', (e) => {
      const cart = window.GlamEye?.Cart;
      const row = e.target.closest('.cart-row');
      if (!row || !cart) return;
      const name = row.dataset.name;
      if (e.target.matches('.qty-btn[data-action="inc"]')) {
        const it = cart.items.find((i) => i.name === name);
        if (it) cart.setQuantity(name, it.quantity + 1);
      } else if (e.target.matches('.qty-btn[data-action="dec"]')) {
        const it = cart.items.find((i) => i.name === name);
        if (it) cart.setQuantity(name, it.quantity - 1);
      } else if (e.target.matches('.cart-row-remove')) {
        cart.remove(name);
      }
    });
    $('#cart-list').addEventListener('change', (e) => {
      if (e.target.matches('.qty-input')) {
        const cart = window.GlamEye?.Cart;
        const row = e.target.closest('.cart-row');
        if (cart && row) cart.setQuantity(row.dataset.name, e.target.value);
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
          ? '您已选择 PayPal，提交后将跳转到 PayPal 支付。'
          : '您已选择 Stripe，提交后将进入信用卡支付。';
      });
    });
  }

  function bindCheckoutSubmit() {
    const form = $('#checkout-form');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fb = $('#checkout-feedback');
      const btn = form.querySelector('.submit-btn');
      const cart = window.GlamEye?.Cart;

      if (!cart || cart.items.length === 0) {
        fb.textContent = '❌ 购物车为空，请先添加商品';
        fb.className = 'form-feedback error';
        return;
      }
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      btn.disabled = true;
      btn.querySelector('.btn-text').hidden = true;
      btn.querySelector('.btn-loading').hidden = false;
      fb.textContent = '';

      const fd = new FormData(form);
      const payload = {
        customer_name: fd.get('customer_name'),
        email:         fd.get('email'),
        phone:         fd.get('phone'),
        address:       fd.get('address'),
        city:          fd.get('city'),
        postal_code:   fd.get('postal_code'),
        notes:         fd.get('notes'),
        payment_method: fd.get('payment_method'),
        items: cart.items.map((it) => ({ product_name: it.name, quantity: it.quantity })),
      };

      try {
        const r = await fetch('api/create-order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (j.success && j.order_id) {
          // 把 email 存到 sessionStorage，order-success.html 用它查订单
          sessionStorage.setItem('glameye_last_order', JSON.stringify({
            order_id: j.order_id, email: payload.email,
          }));
          cart.clear();
          window.location.href = j.next || ('/order-success.html?order_id=' + j.order_id);
        } else {
          fb.textContent = '❌ ' + (j.error || '下单失败');
          fb.className = 'form-feedback error';
          btn.disabled = false;
          btn.querySelector('.btn-text').hidden = false;
          btn.querySelector('.btn-loading').hidden = true;
        }
      } catch (err) {
        fb.textContent = '❌ 网络错误：' + err.message;
        fb.className = 'form-feedback error';
        btn.disabled = false;
        btn.querySelector('.btn-text').hidden = false;
        btn.querySelector('.btn-loading').hidden = true;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    renderCart();
    bindCartEvents();
    bindPaymentNote();
    bindCheckoutSubmit();
  });
})();
