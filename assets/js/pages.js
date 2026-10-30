// ===== Cart, checkout, wishlist and form pages — requires data.js + main.js =====

const SHIP_FREE_AT = 75;
const SHIP_FLAT = 9.95;
const STORE_EMAIL = 'sales@texpertoutdoors.com';

const cartLines = () => cart.map(i => ({ p: productById(i.id), qty: i.qty })).filter(l => l.p);
const subtotalOf = lines => lines.reduce((sum, l) => sum + l.p.price * l.qty, 0);
const shippingFor = (subtotal, pickup) => (pickup || subtotal >= SHIP_FREE_AT ? 0 : SHIP_FLAT);
const mailto = (subject, body) => `mailto:${STORE_EMAIL}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
const emptyState = (icon, title, text) => `
  <div class="empty-state">
    <i class="fa-solid ${icon}"></i>
    <h2>${title}</h2>
    <p>${text}</p>
    <a href="shop.html" class="btn btn-primary">Start Shopping</a>
  </div>`;

function summaryHtml(lines, pickup, withButton) {
  const subtotal = subtotalOf(lines);
  const shipping = shippingFor(subtotal, pickup);
  const toFree = SHIP_FREE_AT - subtotal;
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
const cartItems = $('#cartItems');
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
        <span>${money(p.price)} each</span>
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

if (cartItems) {
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
  const renderSummary = () => { $('#checkoutSummary').innerHTML = summaryHtml(cartLines(), isPickup(), false); };

  if (!cartLines().length) {
    $('#checkoutPage').innerHTML = emptyState('fa-cart-shopping', 'Your Cart Is Empty', 'Add some gear to your cart before checking out.');
  } else {
    // Switch required fields between shipping address and pickup name
    checkoutForm.addEventListener('change', e => {
      if (e.target.name !== 'delivery') return;
      const pickup = isPickup();
      $('#shipFields').hidden = pickup;
      $('#pickupFields').hidden = !pickup;
      $$('#shipFields input').forEach(i => { i.required = !pickup; });
      $$('#pickupFields input').forEach(i => { i.required = pickup; });
      renderSummary();
    });

    checkoutForm.addEventListener('submit', e => {
      e.preventDefault();
      if (!checkoutForm.checkValidity()) { checkoutForm.reportValidity(); return; }
      const f = Object.fromEntries(new FormData(checkoutForm));
      const lines = cartLines();
      const pickup = isPickup();
      const subtotal = subtotalOf(lines);
      const shipping = shippingFor(subtotal, pickup);
      const orderNo = `TXO-${Date.now().toString().slice(-6)}`;
      const body = [
        `Order number: ${orderNo}`, '',
        ...lines.map(l => `${l.qty} x ${l.p.name} (${money(l.p.price)}) = ${money(l.p.price * l.qty)}`), '',
        `Subtotal: ${money(subtotal)}`,
        `${pickup ? 'Store pickup' : 'Shipping'}: ${shipping ? money(shipping) : 'Free'}`,
        `Estimated total: ${money(subtotal + shipping)}`, '',
        `Name: ${pickup ? `${f.pfirst} ${f.plast}` : `${f.first} ${f.last}`}`,
        `Email: ${f.email}`, `Phone: ${f.phone}`,
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
        <p>Your email app should have opened with order <strong>${orderNo}</strong> addressed to ${STORE_EMAIL}. Press send and our team will call you at ${f.phone} to confirm stock, your total and payment.</p>
        <div class="nf-actions">
          <a href="${link}" class="btn btn-primary">Open Email Again</a>
          <a href="shop.html" class="btn btn-outline">Continue Shopping</a>
        </div>
        <p class="form-hint">Email not opening? Call +1-254-358-0017 and quote order ${orderNo}.</p>`;
      location.href = link;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    renderSummary();
  }
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

// ===== Contact & track forms (open the visitor's email app) =====
const contactForm = $('#contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', e => {
    e.preventDefault();
    const f = Object.fromEntries(new FormData(contactForm));
    location.href = mailto(`${f.topic}: ${f.name}`, `${f.message}\n\nName: ${f.name}\nEmail: ${f.email}${f.phone ? `\nPhone: ${f.phone}` : ''}`);
    showToast('Opening your email app...');
  });
}

const trackForm = $('#trackForm');
if (trackForm) {
  trackForm.addEventListener('submit', e => {
    e.preventDefault();
    const f = Object.fromEntries(new FormData(trackForm));
    location.href = mailto(`Order status request: ${f.order}`, `Hi, could you send me the status of order ${f.order}?\n\nEmail on the order: ${f.email}`);
    showToast('Opening your email app...');
  });
}
