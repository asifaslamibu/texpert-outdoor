// ===== Shared site script (all pages) — requires data.js =====

// ===== Helpers =====
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
const money = n => `$${n.toFixed(2)}`;
const shopUrl = (cat, sub) => `shop.html?cat=${cat}${sub ? `&sub=${sub}` : ''}`;
const productUrl = p => `product.html?id=${p.id}`;

function imgWithFallback(src, alt, icon) {
  return `<img src="${src}" alt="${alt}" loading="lazy" data-icon="${icon}">`;
}

// Inline --bg urls are resolved against style.css (assets/css/) when var() is used there,
// so rewrite them to absolute urls based on the page.
$$('[style*="--bg"]').forEach(el => {
  const path = el.style.getPropertyValue('--bg').match(/url\(\s*['"]?([^'")]+)/)?.[1];
  if (path) el.style.setProperty('--bg', `url("${new URL(path, document.baseURI).href}")`);
});

// Replace broken images with an icon placeholder
document.addEventListener('error', e => {
  const img = e.target;
  if (img.tagName !== 'IMG' || !img.dataset.icon) return;
  img.outerHTML = `<div class="ph"><i class="fa-solid ${img.dataset.icon}"></i></div>`;
}, true);

function stars(rating) {
  let html = '';
  for (let i = 1; i <= 5; i++) {
    if (rating >= i) html += '<i class="fa-solid fa-star"></i>';
    else if (rating >= i - 0.5) html += '<i class="fa-solid fa-star-half-stroke"></i>';
    else html += '<i class="fa-regular fa-star"></i>';
  }
  return html;
}

function productCard(p) {
  const inWish = wishlist.includes(p.id);
  return `
    <div class="product">
      ${p.tag ? `<span class="product-tag ${p.tag === 'Sale' ? 'tag-sale' : ''}">${p.tag}</span>` : ''}
      <button class="product-wish${inWish ? ' active' : ''}" data-id="${p.id}" aria-label="Add to wishlist"><i class="fa-${inWish ? 'solid' : 'regular'} fa-heart"></i></button>
      <a href="${productUrl(p)}" class="product-img">${imgWithFallback(p.img, p.name, p.icon)}</a>
      ${p.reviews ? `<div class="rating">${stars(p.rating)}<small>(${p.reviews})</small></div>` : `<div class="product-brand">${p.brand}</div>`}
      <h3><a href="${productUrl(p)}">${p.name}</a></h3>
      <div class="price">
        <strong class="${p.oldPrice ? 'is-sale' : ''}">${money(p.price)}</strong>
        ${p.oldPrice ? `<del>${money(p.oldPrice)}</del>` : ''}
      </div>
      <button class="btn btn-primary add-cart" data-id="${p.id}"><i class="fa-solid fa-cart-shopping"></i> Add to Cart</button>
    </div>`;
}

let toastTimer;
function showToast(msg) {
  const toast = $('#toast');
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
}

function bump(el) {
  el.classList.add('bump');
  setTimeout(() => el.classList.remove('bump'), 200);
}

// JSON call to the PHP backend. Throws an Error with a customer-friendly message on failure.
async function apiCall(path, body) {
  let res;
  try {
    res = await fetch(path, {
      method: body ? 'POST' : 'GET',
      headers: body ? { 'Content-Type': 'application/json' } : {},
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin'
    });
  } catch {
    throw new Error('Could not reach the store. Check your connection and try again.');
  }
  const data = await res.json().catch(() => null);
  if (!data || !res.ok || !data.ok) {
    const err = new Error(data?.error || 'Something went wrong. Please try again or call +1-254-358-0017.');
    err.status = res.status;
    throw err;
  }
  return data;
}

// ===== Cart & Wishlist (persisted in localStorage) =====
function load(key) {
  try { return JSON.parse(localStorage.getItem(key)) || []; } catch { return []; }
}
function save(key, val) {
  try { localStorage.setItem(key, JSON.stringify(val)); } catch { /* storage unavailable */ }
}

let cart = load('outdoors_cart');
let wishlist = load('outdoors_wishlist');

function updateCounts() {
  $('#cartCount').textContent = cart.reduce((sum, item) => sum + item.qty, 0);
  const wish = $('#wishCount');
  if (wish) wish.textContent = wishlist.length;
}

// Delegated so it works for product cards rendered by any page script
document.addEventListener('click', e => {
  const cartBtn = e.target.closest('.add-cart');
  const wishBtn = e.target.closest('.product-wish');

  if (cartBtn) {
    const id = +cartBtn.dataset.id;
    const product = productById(id);
    const item = cart.find(i => i.id === id);
    item ? item.qty++ : cart.push({ id, qty: 1 });
    save('outdoors_cart', cart);
    updateCounts();
    bump($('#cartCount'));
    showToast(`${product.name} added to cart`);

    cartBtn.classList.add('added');
    cartBtn.innerHTML = '<i class="fa-solid fa-check"></i> Added';
    setTimeout(() => {
      cartBtn.classList.remove('added');
      cartBtn.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Add to Cart';
    }, 1500);
  }

  if (wishBtn) {
    const id = +wishBtn.dataset.id;
    const product = productById(id);
    const inList = wishlist.includes(id);
    wishlist = inList ? wishlist.filter(w => w !== id) : [...wishlist, id];
    wishBtn.classList.toggle('active', !inList);
    wishBtn.innerHTML = `<i class="fa-${inList ? 'regular' : 'solid'} fa-heart"></i>`;
    save('outdoors_wishlist', wishlist);
    updateCounts();
    if ($('#wishCount')) bump($('#wishCount'));
    showToast(inList ? `${product.name} removed from wishlist` : `${product.name} added to wishlist`);
  }
});

updateCounts();

// ===== Navigation (built from categories) =====
// Only categories / subcategories that have products are shown in menus
const stocked = categories.filter(c => c.count);
const stockedSubs = c => c.subs.filter(s => s.count);

const NAV_VISIBLE = 8;
const navList = $('#navList');
if (navList) {
  const page = location.pathname.split('/').pop() || 'index.html';
  const activeCat = new URLSearchParams(location.search).get('cat');

  $$('a', navList).forEach(a => {
    if (a.getAttribute('href') === page && !activeCat) a.classList.add('active');
  });

  navList.insertAdjacentHTML('beforeend', stocked.map((c, i) => `
    <li class="has-drop${i >= NAV_VISIBLE ? ' nav-extra' : ''}">
      <a href="${shopUrl(c.slug)}"${c.slug === activeCat ? ' class="active"' : ''}>${c.short} <i class="fa-solid fa-chevron-down"></i></a>
      <ul class="dropdown">
        ${stockedSubs(c).map(s => `<li><a href="${shopUrl(c.slug, s.slug)}">${s.name}</a></li>`).join('')}
        <li class="drop-all"><a href="${shopUrl(c.slug)}">Shop all ${c.short} <i class="fa-solid fa-arrow-right"></i></a></li>
      </ul>
    </li>`).join(''));

  const moreDrop = $('#navMoreDrop');
  if (moreDrop) {
    moreDrop.closest('.nav-more').hidden = stocked.length <= NAV_VISIBLE;
    moreDrop.innerHTML = stocked.slice(NAV_VISIBLE)
      .map(c => `<li><a href="${shopUrl(c.slug)}">${c.name}</a></li>`).join('')
      + '<li class="drop-all"><a href="categories.html">All Categories <i class="fa-solid fa-arrow-right"></i></a></li>';
  }
}

// ===== Mobile Menu =====
const nav = $('#nav');
const overlay = $('#navOverlay');

function toggleMenu(open) {
  nav.classList.toggle('open', open);
  overlay.classList.toggle('show', open);
  document.body.style.overflow = open ? 'hidden' : '';
}

$('#menuToggle').addEventListener('click', () => toggleMenu(true));
$('#navClose').addEventListener('click', () => toggleMenu(false));
overlay.addEventListener('click', () => toggleMenu(false));

$$('.nav-list .has-drop > a').forEach(link => {
  link.addEventListener('click', e => {
    if (window.innerWidth > 992) return;
    e.preventDefault();
    link.parentElement.classList.toggle('open');
  });
});

// ===== Search =====
$('#searchForm').addEventListener('submit', e => {
  e.preventDefault();
  const q = $('input', e.target).value.trim();
  if (q) location.href = `shop.html?q=${encodeURIComponent(q)}`;
});

// ===== Category directory (categories.html) =====
const directory = $('#catDirectory');
if (directory) {
  directory.innerHTML = stocked.map(c => `
    <div class="dir-col">
      <h3><a href="${shopUrl(c.slug)}">${c.name}</a></h3>
      <ul>${stockedSubs(c).map(s => `<li><a href="${shopUrl(c.slug, s.slug)}">${s.name} <span>(${s.count})</span></a></li>`).join('')}</ul>
    </div>`).join('');
  $$('.dir-count').forEach(el => { el.textContent = stocked.length; });
}

// ===== Footer sign-up forms (saved to the backend when it is available) =====
[['#signupForm', 'email'], ['#smsForm', 'sms']].forEach(([sel, type]) => {
  $(sel)?.addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.target;
    const value = $('input', form).value.trim();
    if (SITE.api) {
      try {
        await apiCall('api/subscribe.php', { type, value });
      } catch (err) {
        showToast(err.message);
        return;
      }
    }
    form.reset();
    showToast('Thanks for signing up!');
  });
});

// ===== Footer year =====
$('#year').textContent = new Date().getFullYear();
