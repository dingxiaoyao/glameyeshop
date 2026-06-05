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
    // P1#13: 应用 promo discount(如有)— discountedSubtotal 用于运费判定
    const discount = appliedPromo ? Number(appliedPromo.discount) || 0 : 0;
    const discountedSubtotal = Math.max(0, subtotal - discount);
    const shipping = discountedSubtotal >= 50 ? 0 : 5.99;
    const tax = 0;
    const total = Math.max(0, discountedSubtotal + shipping + tax);
    $('#subtotal').textContent = fmt(subtotal);
    $('#shipping').textContent = discountedSubtotal >= 50 ? 'FREE' : fmt(shipping);
    $('#tax').textContent = fmt(tax);
    $('#total').textContent = fmt(total);

    const discountRow = $('#discount-row');
    if (discount > 0 && appliedPromo) {
      discountRow.hidden = false;
      $('#discount-code-label').textContent = '(' + appliedPromo.code + ')';
      $('#discount-amount').textContent = '-' + fmt(discount);
    } else {
      discountRow.hidden = true;
    }
  }

  // P1#13: promo code state + handlers
  let appliedPromo = null;  // { code, type, value, discount }

  function bindPromo() {
    const input = $('#promo-input');
    const btn   = $('#promo-apply');
    const fb    = $('#promo-feedback');
    const applied = $('#promo-applied');
    const formWrap = $('#promo-form-wrap');
    const codeDisplay = $('#promo-code-display');
    const removeBtn = $('#promo-remove');
    if (!input || !btn) return;

    async function applyCode() {
      const cart = window.GlamEye?.Cart;
      if (!cart) return;
      const code = input.value.trim().toUpperCase();
      if (!code) {
        fb.textContent = 'Enter a code first';
        fb.style.color = 'var(--error,#c33)';
        return;
      }
      btn.disabled = true;
      const origText = btn.textContent;
      btn.textContent = '…';
      try {
        const r = await fetch('/api/validate-promo.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ code, subtotal: cart.subtotal() }),
        });
        const j = await r.json();
        if (j.valid) {
          appliedPromo = { code: j.code, type: j.type, value: j.value, discount: j.discount };
          fb.textContent = j.message;
          fb.style.color = 'var(--success,#2c9)';
          codeDisplay.textContent = j.code + ' · ' + j.message;
          applied.hidden = false;
          formWrap.style.display = 'none';
          renderCart();
        } else {
          fb.textContent = j.message || 'Invalid code';
          fb.style.color = 'var(--error,#c33)';
        }
      } catch (e) {
        fb.textContent = 'Network error';
        fb.style.color = 'var(--error,#c33)';
      } finally {
        btn.disabled = false;
        btn.textContent = origText;
      }
    }

    btn.addEventListener('click', applyCode);
    input.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); applyCode(); } });
    if (removeBtn) {
      removeBtn.addEventListener('click', () => {
        appliedPromo = null;
        applied.hidden = true;
        formWrap.style.display = 'block';
        input.value = '';
        fb.textContent = '';
        renderCart();
      });
    }
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
        // P1#13: promo code(后端会重新校验,前端值不可信)
        promo_code: appliedPromo ? appliedPromo.code : null,
      };

      try {
        const r = await fetch('api/create-order.php', {
          method: 'POST', credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const j = await r.json();
        if (j.success && j.order_id) {
          // P0#1: 存 lookup_token 替代 email — order-success/track 用它查订单
          sessionStorage.setItem('glameye_last_order', JSON.stringify({
            order_id: j.order_id,
            email: payload.email,           // 留着兼容,但 next URL 已带 lookup token
            lookup_token: j.lookup_token,    // 新:永久 token
          }));
          cart.clear();
          // 默认 success URL 加上 lookup_token,避免依赖 sessionStorage(关浏览器丢)
          const fallbackSuccess = '/order-success.html?order_id=' + j.order_id +
            (j.lookup_token ? '&lt=' + j.lookup_token : '');
          window.location.href = j.next || fallbackSuccess;
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

  // P1#3: 根据 admin 配置动态显示/隐藏 Stripe / PayPal 支付选项
  // 未配置的方式 disable 并隐藏 radio,避免用户选了之后跳到 503 错误页(且库存已扣)
  async function applyPaymentMethodVisibility() {
    try {
      const r = await fetch('api/settings.php', { credentials: 'omit' });
      const s = await r.json();
      const stripeOn = s.stripe_enabled === '1';
      const paypalOn = s.paypal_enabled === '1';
      const stripeRow  = document.querySelector('input[name="payment_method"][value="stripe"]')?.closest('.checkbox-row');
      const paypalRow  = document.querySelector('input[name="payment_method"][value="paypal"]')?.closest('.checkbox-row');
      const stripeRadio = document.getElementById('pay-stripe');
      const paypalRadio = document.getElementById('pay-paypal');
      if (stripeRow && !stripeOn) { stripeRow.style.display = 'none'; if (stripeRadio) { stripeRadio.disabled = true; stripeRadio.checked = false; } }
      if (paypalRow && !paypalOn) { paypalRow.style.display = 'none'; if (paypalRadio) { paypalRadio.disabled = true; paypalRadio.checked = false; } }
      // 兜底:两个都关 → 给个清晰提示且禁用提交
      if (!stripeOn && !paypalOn) {
        const note = document.getElementById('payment-note');
        if (note) {
          note.textContent = 'Payment is temporarily unavailable. The site owner has not configured a payment gateway yet.';
          note.style.color = 'var(--error, #c33)';
        }
        const submitBtn = document.querySelector('.submit-btn');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.title = 'Payment unavailable'; }
      } else if (!stripeOn && paypalOn && paypalRadio) {
        // 只有 PayPal 可用 → 默认选 PayPal
        paypalRadio.checked = true;
      } else if (stripeOn && !paypalOn && stripeRadio) {
        stripeRadio.checked = true;
      }
    } catch (e) {
      // 拿不到 settings 不阻塞下单(用户看到的可能仍是 Stripe + PayPal,
      // 服务端会用 503 错误页接住)
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    renderCart();
    bindCart();
    bindPaymentNote();
    bindSubmit();
    bindPromo();
    applyPaymentMethodVisibility();
  });
})();
