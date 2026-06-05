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
    // 国际:运费按国家 zone 查表
    const shipInfo = calcShipping(discountedSubtotal);
    const shipping = shipInfo.cost;
    const tax = 0;
    const total = Math.max(0, discountedSubtotal + shipping + tax);
    $('#subtotal').textContent = fmt(subtotal);
    $('#shipping').textContent = shipping === 0 ? 'FREE' : fmt(shipping);
    $('#tax').textContent = fmt(tax);
    $('#total').textContent = fmt(total);
    // 显示 shipping hint(eta / free threshold)
    const hint = $('#shipping-hint');
    if (hint) {
      if (shipInfo.free_threshold && discountedSubtotal < shipInfo.free_threshold) {
        const remaining = (shipInfo.free_threshold - discountedSubtotal).toFixed(2);
        hint.textContent = '🚚 Add $' + remaining + ' more for FREE shipping to this country';
        hint.style.color = 'var(--gold)';
      } else if (shipInfo.cost === 0) {
        hint.textContent = '🎉 You qualify for FREE shipping';
        hint.style.color = 'var(--success, #2c9)';
      } else {
        hint.textContent = 'Standard shipping to your country: $' + shipInfo.cost.toFixed(2);
        hint.style.color = '';
      }
    }

    const discountRow = $('#discount-row');
    if (discount > 0 && appliedPromo) {
      discountRow.hidden = false;
      $('#discount-code-label').textContent = '(' + appliedPromo.code + ')';
      $('#discount-amount').textContent = '-' + fmt(discount);
    } else {
      discountRow.hidden = true;
    }
  }

  // 国际下单 — 加载国家清单 + 运费 zone 表
  let shippingZones = {};  // { US:{price,free_threshold}, default:{...} }
  let enabledCountries = [];
  // ISO → display name(常用国,其他显示 ISO code)
  const COUNTRY_NAMES = {
    US: '🇺🇸 United States', CA: '🇨🇦 Canada', GB: '🇬🇧 United Kingdom',
    AU: '🇦🇺 Australia', DE: '🇩🇪 Germany', FR: '🇫🇷 France', IT: '🇮🇹 Italy',
    ES: '🇪🇸 Spain', NL: '🇳🇱 Netherlands', JP: '🇯🇵 Japan', SG: '🇸🇬 Singapore',
    HK: '🇭🇰 Hong Kong', TW: '🇹🇼 Taiwan', CN: '🇨🇳 China', KR: '🇰🇷 South Korea',
    MX: '🇲🇽 Mexico', BR: '🇧🇷 Brazil', NZ: '🇳🇿 New Zealand', SE: '🇸🇪 Sweden',
    NO: '🇳🇴 Norway', DK: '🇩🇰 Denmark', FI: '🇫🇮 Finland', BE: '🇧🇪 Belgium',
    AT: '🇦🇹 Austria', CH: '🇨🇭 Switzerland', IE: '🇮🇪 Ireland', PT: '🇵🇹 Portugal',
    PL: '🇵🇱 Poland', AE: '🇦🇪 UAE', SA: '🇸🇦 Saudi Arabia', IL: '🇮🇱 Israel',
    IN: '🇮🇳 India', ID: '🇮🇩 Indonesia', MY: '🇲🇾 Malaysia', TH: '🇹🇭 Thailand',
    PH: '🇵🇭 Philippines', VN: '🇻🇳 Vietnam', ZA: '🇿🇦 South Africa',
  };
  // 通用 zone 兜底:EU 国家走 EU,亚洲走 ASIA
  const EU_ZONE = ['DE','FR','IT','ES','NL','BE','AT','SE','NO','DK','FI','IE','PT','PL','CH'];
  const ASIA_ZONE = ['JP','SG','HK','TW','CN','KR','MY','TH','PH','VN','ID','IN'];

  function lookupZone(country) {
    if (!country) return shippingZones.default || { price: 5.99, free_threshold: 50 };
    if (shippingZones[country]) return shippingZones[country];
    if (EU_ZONE.indexOf(country) > -1 && shippingZones.EU) return shippingZones.EU;
    if (ASIA_ZONE.indexOf(country) > -1 && shippingZones.ASIA) return shippingZones.ASIA;
    return shippingZones.default || { price: 5.99, free_threshold: 50 };
  }

  function calcShipping(subtotalAfterDiscount) {
    const country = $('#country-select') ? $('#country-select').value : 'US';
    const zone = lookupZone(country);
    const threshold = Number(zone.free_threshold) || 0;
    const price = Number(zone.price) || 0;
    const cost = (threshold > 0 && subtotalAfterDiscount >= threshold) ? 0 : price;
    return { cost: cost, free_threshold: threshold, price: price };
  }

  async function loadCountriesAndZones() {
    try {
      const r = await fetch('/api/settings.php');
      const s = await r.json();
      try { enabledCountries = JSON.parse(s.enabled_countries || '[]'); } catch { enabledCountries = ['US']; }
      try { shippingZones = JSON.parse(s.shipping_zones || '{}'); } catch { shippingZones = {}; }
      if (!enabledCountries.length) enabledCountries = ['US'];

      const sel = $('#country-select');
      if (!sel) return;
      sel.innerHTML = '<option value="">Select country…</option>' +
        enabledCountries.map(c => `<option value="${c}">${COUNTRY_NAMES[c] || c}</option>`).join('');

      // 默认 US
      if (enabledCountries.indexOf('US') > -1) sel.value = 'US';
      else sel.value = enabledCountries[0];

      sel.addEventListener('change', () => {
        renderCart();
        adjustFieldsForCountry(sel.value);
      });
      adjustFieldsForCountry(sel.value);
    } catch (e) { /* 网络出错时静默 */ }
  }

  function adjustFieldsForCountry(country) {
    const stateLabel = $('#state-label');
    const stateInput = $('#state-input');
    const postalLabel = $('#postal-label');
    const postalInput = $('#postal-input');
    if (!stateLabel || !stateInput || !postalLabel || !postalInput) return;
    // 各国 label 调整(英文,简洁)
    const map = {
      US: { state: 'State', postal: 'ZIP Code', postalPlaceholder: '12345', statePlaceholder: 'e.g. CA' },
      CA: { state: 'Province', postal: 'Postal Code', postalPlaceholder: 'A1A 1A1', statePlaceholder: 'e.g. ON' },
      GB: { state: 'County', postal: 'Postcode', postalPlaceholder: 'SW1A 1AA', statePlaceholder: 'e.g. Greater London' },
      AU: { state: 'State', postal: 'Postcode', postalPlaceholder: '2000', statePlaceholder: 'e.g. NSW' },
      JP: { state: 'Prefecture', postal: 'Postal Code', postalPlaceholder: '100-0001', statePlaceholder: 'e.g. Tokyo' },
      CN: { state: 'Province', postal: 'Postal Code', postalPlaceholder: '100000', statePlaceholder: 'e.g. 北京' },
      DE: { state: 'State', postal: 'PLZ', postalPlaceholder: '10115', statePlaceholder: 'e.g. Berlin' },
      FR: { state: 'Region', postal: 'Code Postal', postalPlaceholder: '75001', statePlaceholder: 'e.g. Île-de-France' },
    };
    const cfg = map[country] || { state: 'State / Province / Region', postal: 'Postal Code', postalPlaceholder: 'Postal code', statePlaceholder: 'State, province, or region' };
    stateLabel.innerHTML  = cfg.state + ' <span class="required">*</span>';
    postalLabel.innerHTML = cfg.postal + ' <span class="required">*</span>';
    postalInput.placeholder = cfg.postalPlaceholder;
    stateInput.placeholder  = cfg.statePlaceholder;
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
        country:        fd.get('country') || 'US',
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

  // 强制登录守门 — 未登录 + 需要登录 → 跳 login 带 redirect
  async function enforceLoginIfNeeded() {
    try {
      const r = await fetch('/api/settings.php');
      const s = await r.json();
      const requireLogin = s.require_login_for_checkout === '1';
      if (!requireLogin) return false;  // 允许 guest

      // 检查是否已登录
      const me = await fetch('/api/auth.php?action=me', { credentials: 'include' });
      const mj = await me.json();
      if (mj.user && mj.user.id) return false;  // 已登录,放行

      // 未登录 + 需要登录 → 用全屏 banner 引导,不直接跳转(让用户看清原因)
      const main = document.querySelector('main') || document.body;
      main.innerHTML = `
        <div class="container" style="max-width:480px;padding:5rem 1.25rem;text-align:center;">
          <div style="font-size:3.5rem;margin-bottom:1rem;">🔒</div>
          <h1 style="margin-bottom:.5rem;">Sign in to check out</h1>
          <p class="muted" style="margin-bottom:2rem;line-height:1.7;">
            To track your order, save your shipping address, and earn rewards on future purchases,
            please sign in or create a free account.
          </p>
          <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
            <a href="/login.html?redirect=%2Fcheckout.html" class="button button-primary">Sign in</a>
            <a href="/signup.html?redirect=%2Fcheckout.html" class="button button-outline">Create account</a>
          </div>
          <p class="muted small" style="margin-top:2rem;">
            Your cart items are saved — you'll come right back here after signing in.
          </p>
        </div>`;
      return true;  // 阻止后续 init
    } catch (e) {
      return false;  // 网络出错时不阻塞(后端守门兜底)
    }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    if (await enforceLoginIfNeeded()) return;  // 守门拦下就不继续 init
    await loadCountriesAndZones();  // 必须在 renderCart 前,运费需要 zones
    renderCart();
    bindCart();
    bindPaymentNote();
    bindSubmit();
    bindPromo();
    applyPaymentMethodVisibility();
  });
})();
