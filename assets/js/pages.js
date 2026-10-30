// ===== Cart, checkout, account and form pages — requires data.js + main.js =====
// With the PHP backend (SITE.api) orders, messages and accounts are saved to the database.
// Without it, checkout / contact / track fall back to opening the visitor's email app.

const STORE_EMAIL = 'sales@texpertoutdoors.com';
const STORE_PHONE = '+1-254-358-0017';

const cartLines = () => cart.map(i => ({ p: productById(i.id), qty: i.qty })).filter(l => l.p);
const subtotalOf = lines => lines.reduce((sum, l) => sum + l.p.price * l.qty, 0);
const shippingFor = (subtotal, pickup) => (pickup || subtotal >= SITE.freeOver ? 0 : SITE.flatRate);
const mailto = (subject, body) => `mailto:${STORE_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const emptyState = (icon, title, text, cta = '<a href="shop.html" class="btn btn-primary">Start Shopping</a>') => `
  <div class="empty-state">
    <i class="fa-solid ${icon}"></i>
    <h2>${title}</h2>
    <p>${text}</p>
    ${cta}
  </div>`;

function setBusy(btn, busy, label) {
  if (!btn) return;
  if (busy) btn.dataset.label = btn.innerHTML;
  btn.disabled = busy;
  btn.innerHTML = busy ? `<i class="fa-solid fa-spinner fa-spin"></i> ${label || 'Please wait...'}` : btn.dataset.label;
}

function formError(form, message) {
  let box = $('.form-error', form);
  if (!box) {
    box = document.createElement('p');
    box.className = 'form-error';
    box.setAttribute('role', 'alert');
    form.prepend(box);
  }
  box.hidden = !message;
  box.textContent = message || '';
  if (message) box.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function summaryHtml(lines, pickup, withButton) {
  const subtotal = subtotalOf(lines);
  const shipping = shippingFor(subtotal, pickup);
  const toFree = SITE.freeOver - subtotal;
  return `
    <h2>Order Summary</h2>
    ${withButton ? '' : `<ul class="summary-items">${lines.map(l => `
      <li><span class="summary-thumb">${imgWithFallback(l.p.img, l.p.name, l.p.icon)}<em>${l.qty}</em></span><span>${l.p.name}</span><strong>${money(l.p.price * l.qty)}</strong></li>`).join('')}</ul>`}
    <dl>
      <dt>Subtotal</dt><dd>${money(subtotal)}</dd>
      <dt>${pickup ? 'Store Pickup' : 'Shipping'}</dt><dd>${shipping ? money(shipping) : 'Free'}</dd>
      <dt>Tax</dt><dd>Confirmed with you</dd>
      <dt class="total">Estimated Total</dt><dd class="total">${money(subtotal + shipping)}</dd>
    </dl>
    ${!pickup && toFree > 0 ? `<p class="free-ship">Add ${money(toFree)} more for free shipping.</p>` : ''}
    ${withButton ? '<a href="checkout.html" class="btn btn-primary btn-block">Checkout</a><a href="shop.html" class="continue">Continue Shopping</a>' : ''}`;
}

// ===== Cart page =====
function renderCart() {
  const lines = cartLines();
  if (!lines.length) {
    $('#cartPage').innerHTML = emptyState('fa-cart-shopping', 'Your Cart Is Empty', 'Looks like you have not added anything yet.');
    return;
  }
  $('#cartItems').innerHTML = `
    <div class="cart-head"><span>Product</span><span>Quantity</span><span>Total</span></div>
    ${lines.map(({ p, qty }) => `
    <div class="cart-row">
      <a href="${productUrl(p)}" class="cart-thumb">${imgWithFallback(p.img, p.name, p.icon)}</a>
      <div class="cart-name">
        <a href="${productUrl(p)}">${p.name}</a>
        <span>${money(p.price)} each${p.stock != null && p.stock < qty ? ` · <b class="warn">Only ${p.stock} in stock</b>` : ''}</span>
        <button class="cart-remove" data-id="${p.id}">Remove</button>
      </div>
      <div class="qty">
        <button type="button" data-id="${p.id}" data-step="-1" aria-label="Decrease quantity"><i class="fa-solid fa-minus"></i></button>
        <input type="number" value="${qty}" min="1" max="99" data-id="${p.id}" aria-label="Quantity">
        <button type="button" data-id="${p.id}" data-step="1" aria-label="Increase quantity"><i class="fa-solid fa-plus"></i></button>
      </div>
      <strong class="cart-line">${money(p.price * qty)}</strong>
    </div>`).join('')}`;
  $('#cartSummary').innerHTML = summaryHtml(lines, false, true);
}

function setQty(id, qty) {
  cart = qty < 1 ? cart.filter(i => i.id !== id) : cart.map(i => (i.id === id ? { ...i, qty: Math.min(99, qty) } : i));
  save('outdoors_cart', cart);
  updateCounts();
  renderCart();
}

if ($('#cartItems')) {
  $('#cartPage').addEventListener('click', e => {
    const step = e.target.closest('[data-step]');
    const remove = e.target.closest('.cart-remove');
    if (step) {
      const id = +step.dataset.id;
      setQty(id, cart.find(i => i.id === id).qty + +step.dataset.step);
    }
    if (remove) {
      const id = +remove.dataset.id;
      showToast(`${productById(id).name} removed from cart`);
      setQty(id, 0);
    }
  });
  $('#cartPage').addEventListener('change', e => {
    if (e.target.matches('.qty input')) setQty(+e.target.dataset.id, parseInt(e.target.value, 10) || 1);
  });
  renderCart();
}

// ===== Checkout page =====
const checkoutForm = $('#checkoutForm');
if (checkoutForm) {
  const isPickup = () => checkoutForm.delivery.value === 'pickup';
  const payByCard = () => SITE.stripe && checkoutForm.payment?.value === 'card';
  const renderSummary = () => { $('#checkoutSummary').innerHTML = summaryHtml(cartLines(), isPickup(), false); };

  const renderMode = () => {
    const btn = $('#checkoutForm button[type=submit]');
    if (!SITE.api) {
      $('#checkoutNote').textContent = `Placing your order opens your email app with your order details addressed to ${STORE_EMAIL}. Send it, and our team will call you to confirm stock, total and payment.`;
    } else if (payByCard()) {
      $('#checkoutNote').textContent = 'You will be taken to Stripe\'s secure payment page to pay by card. We never see or store your card details.';
      btn.innerHTML = '<i class="fa-solid fa-lock"></i> Continue to Payment';
    } else {
      $('#checkoutNote').textContent = 'Your order is sent straight to our team. We will call you to confirm stock and take payment, or you can pay when you pick up in store.';
      btn.textContent = 'Place Order';
    }
  };

  if (!cartLines().length) {
    $('#checkoutPage').innerHTML = emptyState('fa-cart-shopping', 'Your Cart Is Empty', 'Add some gear to your cart before checking out.');
  } else {
    $('#paymentFields').hidden = !(SITE.api && SITE.stripe);

    // Pre-fill from the signed-in customer's account
    if (SITE.api) {
      apiCall('api/account.php').then(({ customer: c }) => {
        if (!c) return;
        const fill = (name, value) => { const el = checkoutForm[name]; if (el && !el.value && value) el.value = value; };
        fill('email', c.email); fill('phone', c.phone); fill('first', c.first); fill('last', c.last);
        fill('pfirst', c.first); fill('plast', c.last); fill('address', c.address); fill('city', c.city); fill('state', c.state); fill('zip', c.zip);
        $('#accountHint').innerHTML = `Signed in as <strong>${esc(c.email)}</strong>. This order will appear in <a href="account.html">your account</a>.`;
      }).catch(() => {});
    }

    // Switch required fields between shipping address and pickup name
    checkoutForm.addEventListener('change', e => {
      if (e.target.name === 'delivery') {
        const pickup = isPickup();
        $('#shipFields').hidden = pickup;
        $('#pickupFields').hidden = !pickup;
        $$('#shipFields input').forEach(i => { i.required = !pickup; });
        $$('#pickupFields input').forEach(i => { i.required = pickup; });
        renderSummary();
      }
      if (e.target.name === 'payment') renderMode();
    });

    checkoutForm.addEventListener('submit', async e => {
      e.preventDefault();
      formError(checkoutForm, '');
      if (!checkoutForm.checkValidity()) { checkoutForm.reportValidity(); return; }
      const f = Object.fromEntries(new FormData(checkoutForm));
      const pickup = isPickup();
      const details = {
        email: f.email, phone: f.phone, delivery: pickup ? 'pickup' : 'ship',
        first: pickup ? f.pfirst : f.first, last: pickup ? f.plast : f.last,
        address: f.address, city: f.city, state: f.state, zip: f.zip, notes: f.notes
      };

      if (SITE.api) {
        const btn = $('#checkoutForm button[type=submit]');
        setBusy(btn, true, payByCard() ? 'Opening secure payment...' : 'Placing order...');
        try {
          const res = await apiCall('api/checkout.php', { ...details, payment: payByCard() ? 'card' : 'later', items: cart });
          cart = [];
          save('outdoors_cart', cart);
          updateCounts();
          location.href = res.redirect || `order-success.html?order=${encodeURIComponent(res.order_no)}&token=${res.token}`;
        } catch (err) {
          setBusy(btn, false);
          formError(checkoutForm, err.message);
        }
        return;
      }

      // ----- No backend: send the order by email -----
      const lines = cartLines();
      const subtotal = subtotalOf(lines);
      const shipping = shippingFor(subtotal, pickup);
      const orderNo = `TXO-${Date.now().toString().slice(-6)}`;
      const body = [
        `Order number: ${orderNo}`, '',
        ...lines.map(l => `${l.qty} x ${l.p.name} (${money(l.p.price)}) = ${money(l.p.price * l.qty)}`), '',
        `Subtotal: ${money(subtotal)}`,
        `${pickup ? 'Store pickup' : 'Shipping'}: ${shipping ? money(shipping) : 'Free'}`,
        `Estimated total: ${money(subtotal + shipping)}`, '',
        `Name: ${details.first} ${details.last}`, `Email: ${f.email}`, `Phone: ${f.phone}`,
        pickup ? 'Delivery: Pick up in store' : `Ship to: ${f.address}, ${f.city}, ${f.state} ${f.zip}`,
        f.notes ? `Notes: ${f.notes}` : ''
      ].join('\n');
      const link = mailto(`New Order ${orderNo}`, body);
      cart = [];
      save('outdoors_cart', cart);
      updateCounts();
      $('#checkoutPage').hidden = true;
      const done = $('#orderDone');
      done.hidden = false;
      done.innerHTML = `
        <i class="fa-solid fa-envelope-circle-check"></i>
        <h2>Almost Done: Send Your Order Email</h2>
        <p>Your email app should have opened with order <strong>${orderNo}</strong> addressed to ${STORE_EMAIL}. Press send and our team will call you at ${esc(f.phone)} to confirm stock, your total and payment.</p>
        <div class="nf-actions">
          <a href="${link}" class="btn btn-primary">Open Email Again</a>
          <a href="shop.html" class="btn btn-outline">Continue Shopping</a>
        </div>
        <p class="form-hint">Email not opening? Call ${STORE_PHONE} and quote order ${orderNo}.</p>`;
      location.href = link;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    renderSummary();
    renderMode();
  }
}

// ===== Order confirmation page =====
const orderBox = $('#orderBox');
if (orderBox) {
  const q = new URLSearchParams(location.search);
  const ref = { order: q.get('order') || '', token: q.get('token') || '' };
  const sessionId = q.get('session_id');

  const render = o => {
    const paid = o.payment_status === 'paid';
    const awaitingCard = o.payment_method === 'card' && !paid;
    const icon = paid || o.payment_method === 'later' ? 'fa-circle-check' : 'fa-circle-exclamation';
    const title = paid ? 'Thank You, Payment Received'
      : awaitingCard ? (q.get('cancelled') ? 'Payment Not Completed' : 'Confirming Your Payment...')
      : 'Thank You for Your Order';
    const message = paid
      ? `We have received your payment. A confirmation is on its way to <strong>${esc(o.email)}</strong>.`
      : awaitingCard
        ? (q.get('cancelled')
          ? 'Your order is saved, but the card payment was not completed. You can try again below, or call us to pay by phone.'
          : 'Stripe is still confirming your payment. This page will update in a moment.')
        : `Our team will call you at <strong>${esc(o.phone)}</strong> to confirm stock and payment${o.delivery === 'pickup' ? ', or you can pay when you pick up' : ''}.`;
    orderBox.innerHTML = `
      <i class="fa-solid ${icon} order-icon${awaitingCard ? ' warn' : ''}"></i>
      <h2>${title}</h2>
      <p class="order-no">Order <strong>${esc(o.order_no)}</strong> · ${esc(o.status)}</p>
      <p>${message}</p>
      ${awaitingCard ? '<button class="btn btn-primary" id="payAgain"><i class="fa-solid fa-lock"></i> Pay by Card</button>' : ''}
      <div class="order-lines">
        ${o.items.map(i => `<div><span>${i.qty} × ${esc(i.name)}</span><strong>${money(i.price * i.qty)}</strong></div>`).join('')}
        <div><span>${o.delivery === 'pickup' ? 'Store Pickup' : 'Shipping'}</span><strong>${o.shipping ? money(o.shipping) : 'Free'}</strong></div>
        <div class="total"><span>Total</span><strong>${money(o.total)}</strong></div>
      </div>
      <p class="form-hint">Keep your order number. You can check its status any time on <a href="track-order.html">Track an Order</a>.</p>
      <div class="nf-actions"><a href="shop.html" class="btn btn-outline">Continue Shopping</a></div>`;

    $('#payAgain')?.addEventListener('click', async e => {
      setBusy(e.currentTarget, true, 'Opening secure payment...');
      try {
        location.href = (await apiCall('api/pay.php', ref)).redirect;
      } catch (err) {
        setBusy(e.currentTarget, false);
        showToast(err.message);
      }
    });
  };

  const load = async (tries = 0) => {
    try {
      const { order } = await apiCall(`api/order.php?order=${encodeURIComponent(ref.order)}&token=${encodeURIComponent(ref.token)}${sessionId ? `&session_id=${encodeURIComponent(sessionId)}` : ''}`);
      render(order);
      // The webhook may land a few seconds after the redirect
      if (sessionId && order.payment_status !== 'paid' && tries < 5) setTimeout(() => load(tries + 1), 2500);
    } catch (err) {
      orderBox.innerHTML = emptyState('fa-circle-question', 'Order Not Found', `${esc(err.message)} If you placed an order, call us at ${STORE_PHONE}.`);
    }
  };
  if (ref.order && ref.token) load();
  else orderBox.innerHTML = emptyState('fa-circle-question', 'Order Not Found', 'This link is incomplete.');
}

// ===== Wishlist page =====
const wishGrid = $('#wishGrid');
function renderWishlist() {
  const items = wishlist.map(productById).filter(Boolean);
  $('#wishInfo').textContent = items.length ? `${items.length} saved item${items.length === 1 ? '' : 's'}` : '';
  wishGrid.innerHTML = items.length
    ? items.map(productCard).join('')
    : emptyState('fa-heart', 'Your Wishlist Is Empty', 'Tap the heart on any product to save it here.');
}
if (wishGrid) {
  // main.js toggles the wishlist first (its listener was added earlier), then we re-render
  document.addEventListener('click', e => { if (e.target.closest('.product-wish')) renderWishlist(); });
  renderWishlist();
}

// ===== Contact form =====
const contactForm = $('#contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', async e => {
    e.preventDefault();
    formError(contactForm, '');
    const f = Object.fromEntries(new FormData(contactForm));
    if (!SITE.api) {
      location.href = mailto(`${f.topic}: ${f.name}`, `${f.message}\n\nName: ${f.name}\nEmail: ${f.email}${f.phone ? `\nPhone: ${f.phone}` : ''}`);
      showToast('Opening your email app...');
      return;
    }
    const btn = $('button[type=submit]', contactForm);
    setBusy(btn, true, 'Sending...');
    try {
      await apiCall('api/contact.php', f);
      contactForm.innerHTML = `
        <div class="form-success">
          <i class="fa-solid fa-circle-check"></i>
          <h2>Message Sent</h2>
          <p>Thanks, ${esc(f.name)}. We usually reply within one business day at ${esc(f.email)}.</p>
        </div>`;
    } catch (err) {
      setBusy(btn, false);
      formError(contactForm, err.message);
    }
  });
}

// ===== Track order =====
const trackForm = $('#trackForm');
if (trackForm) {
  const STEPS = ['new', 'confirmed', 'processing', 'shipped', 'completed'];
  trackForm.addEventListener('submit', async e => {
    e.preventDefault();
    formError(trackForm, '');
    const f = Object.fromEntries(new FormData(trackForm));
    if (!SITE.api) {
      location.href = mailto(`Order status request: ${f.order}`, `Hi, could you send me the status of order ${f.order}?\n\nEmail on the order: ${f.email}`);
      showToast('Opening your email app...');
      return;
    }
    const btn = $('button[type=submit]', trackForm);
    setBusy(btn, true, 'Looking up...');
    try {
      const { order: o } = await apiCall('api/track.php', f);
      const steps = o.delivery === 'pickup' ? ['new', 'confirmed', 'processing', 'ready_for_pickup', 'completed'] : STEPS;
      const at = steps.indexOf(o.status_key);
      $('#trackResult').innerHTML = `
        <div class="track-card">
          <div class="track-head"><h2>Order ${esc(o.order_no)}</h2><span>Placed ${esc(o.date)}</span></div>
          ${o.status_key === 'cancelled' ? '<p class="form-error">This order was cancelled.</p>' : `
          <ol class="track-steps">${steps.map((s, i) => `<li class="${i <= at ? 'done' : ''}">${s.replace(/_/g, ' ')}</li>`).join('')}</ol>`}
          <p><strong>Status:</strong> ${esc(o.status)} · <strong>Payment:</strong> ${esc(o.payment)}${o.tracking ? ` · <strong>Tracking:</strong> ${esc(o.tracking)}` : ''}</p>
          <ul class="track-items">${o.items.map(i => `<li>${i.qty} × ${esc(i.name)}</li>`).join('')}</ul>
          <p><strong>Total:</strong> ${money(o.total)}</p>
        </div>`;
    } catch (err) {
      $('#trackResult').innerHTML = '';
      formError(trackForm, err.message);
    } finally {
      setBusy(btn, false);
    }
  });
  const q = new URLSearchParams(location.search);
  if (q.get('order')) trackForm.order.value = q.get('order');
}

// ===== Account page =====
const accountPage = $('#accountPage');
if (accountPage) {
  if (!SITE.api) {
    accountPage.innerHTML = emptyState('fa-user', 'Accounts Unavailable', `Customer accounts are temporarily unavailable. Questions about an order? Call ${STORE_PHONE}.`, '<a href="track-order.html" class="btn btn-primary">Track an Order</a>');
  } else {
    const show = customer => {
      $('#authView').hidden = !!customer;
      $('#dashView').hidden = !customer;
      if (!customer) return;
      $('#hello').textContent = `Hi, ${customer.first}`;
      $('#memberSince').textContent = `Member since ${customer.since}`;
      const pf = $('#profileForm');
      ['first', 'last', 'phone', 'address', 'city', 'state', 'zip'].forEach(k => { pf[k].value = customer[k] || ''; });
      $('#profileEmail').textContent = customer.email;
      loadOrders();
    };

    const loadOrders = async () => {
      const box = $('#orderList');
      try {
        const { orders } = await apiCall('api/account.php?action=orders');
        box.innerHTML = orders.length ? orders.map(o => `
          <div class="acct-order">
            <div><strong>${esc(o.order_no)}</strong><span>${esc(o.date)} · ${o.items.reduce((n, i) => n + i.qty, 0)} item(s)</span></div>
            <div><span class="status">${esc(o.status)}</span><strong>${money(o.total)}</strong></div>
            <p>${o.items.map(i => `${i.qty} × ${esc(i.name)}`).join(', ')}</p>
          </div>`).join('')
          : emptyState('fa-box-open', 'No Orders Yet', 'When you place an order while signed in, it shows up here.');
      } catch (err) {
        box.innerHTML = `<p class="form-error">${esc(err.message)}</p>`;
      }
    };

    // Login / register tabs
    $$('.auth-tab').forEach(tab => tab.addEventListener('click', () => {
      $$('.auth-tab').forEach(t => t.classList.toggle('active', t === tab));
      $('#loginForm').hidden = tab.dataset.tab !== 'login';
      $('#registerForm').hidden = tab.dataset.tab !== 'register';
    }));

    const bindForm = (form, action, onDone, busyLabel) => form.addEventListener('submit', async e => {
      e.preventDefault();
      formError(form, '');
      const btn = $('button[type=submit]', form);
      setBusy(btn, true, busyLabel);
      try {
        const res = await apiCall('api/account.php', { action, ...Object.fromEntries(new FormData(form)) });
        onDone(res, form);
      } catch (err) {
        formError(form, err.message);
      } finally {
        setBusy(btn, false);
      }
    });

    bindForm($('#loginForm'), 'login', res => show(res.customer), 'Signing in...');
    bindForm($('#registerForm'), 'register', res => { show(res.customer); showToast('Welcome to Texpert Outdoors!'); }, 'Creating account...');
    bindForm($('#profileForm'), 'update', () => showToast('Your details were saved.'), 'Saving...');
    bindForm($('#passwordForm'), 'password', (res, form) => { form.reset(); showToast('Password changed.'); }, 'Saving...');
    $('#logoutBtn').addEventListener('click', async () => {
      await apiCall('api/account.php', { action: 'logout' }).catch(() => {});
      show(null);
      showToast('You are signed out.');
    });

    apiCall('api/account.php').then(res => show(res.customer)).catch(err => {
      accountPage.innerHTML = emptyState('fa-triangle-exclamation', 'Something Went Wrong', esc(err.message), '<a href="account.html" class="btn btn-primary">Try Again</a>');
    });
  }
}
