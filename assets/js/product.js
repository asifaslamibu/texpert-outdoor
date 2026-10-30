// ===== Product detail page — requires data.js + main.js =====

const product = productById(+new URLSearchParams(location.search).get('id'));

// Multi-word brands in the catalog; anything else uses the first word of the name
const BRANDS = ['Trophy Ridge', 'Carbon Express', 'Outdoor Edge', 'Butler Creek', 'Quaker Boy', 'Knight & Hale',
  'Scent Thief', 'Scent-A-Way', 'Wildlife Research', 'Stealth Cam', 'Adventure Medical', 'Primos Double Bull'];
const brandOf = name => (BRANDS.find(b => name.startsWith(b)) || name.split(' ')[0]).replace(/'s$/, '');

// Short category-specific copy for the description tab
const ABOUT = {
  'archery': 'Engineered for consistent, accurate shots and built to hold up season after season in the stand or on the range.',
  'outdoor-gear': 'Rugged, field-ready gear that packs light and performs when the weather turns and the trail gets long.',
  'knives-and-multi-tools': 'Sharp out of the box with a secure grip, made for field dressing, camp chores and everyday carry.',
  'hunting-accessories': 'Trusted by hunters for the details that matter: staying hidden, staying comfortable and bringing game in close.',
  'optics-and-sights': 'Clear, bright glass with dependable adjustments, so you can pick your target out early and hold steady.',
  'gun-cases-and-storage': 'Protects your gear in transit and storage with durable construction and secure closures.',
  'reloading-equipment': 'Precision-made for consistent, repeatable results at the reloading bench.'
};

if (!product) {
  $('#pdp').hidden = true;
  $$('.pdp-crumbs, .pdp-details, #related').forEach(el => { (el.closest('section') || el).hidden = true; });
  $('#pdpMissing').hidden = false;
  document.title = 'Product Not Found | Texpert Outdoors';
} else {
  const p = product;
  const brand = brandOf(p.name);
  const sku = `TXO-${String(p.id).padStart(5, '0')}`;
  document.title = `${p.name} | Texpert Outdoors`;

  // ===== Breadcrumb =====
  $('#breadcrumb').innerHTML = [['Home', 'index.html'], ['Shop', 'shop.html'], [p.categoryName, shopUrl(p.category)], [p.subName, shopUrl(p.category, p.sub)]]
    .map(([label, href]) => `<a href="${href}">${label}</a><i class="fa-solid fa-chevron-right"></i>`).join('')
    + `<span>${p.name}</span>`;

  // ===== Buy box =====
  $('#pdpImg').innerHTML = (p.tag ? `<span class="product-tag ${p.tag === 'Sale' ? 'tag-sale' : ''}">${p.tag}</span>` : '')
    + imgWithFallback(p.img, p.name, p.icon);
  $('#pdpBrand').textContent = brand;
  $('#pdpName').textContent = p.name;
  $('#pdpRating').innerHTML = `${stars(p.rating)}<small>${p.rating.toFixed(1)} (${p.reviews} reviews)</small>`;
  $('#pdpSku').textContent = `SKU: ${sku}`;
  $('#pdpPrice').innerHTML = p.oldPrice
    ? `<strong class="is-sale">${money(p.price)}</strong><del>${money(p.oldPrice)}</del><span class="pdp-save">Save ${money(p.oldPrice - p.price)}</span>`
    : `<strong>${money(p.price)}</strong>`;
  $('#pdpShip').textContent = p.price >= 75 ? 'Free shipping on this item' : `Free shipping on orders over $75`;

  // ===== Quantity =====
  const qtyInput = $('#qtyInput');
  const clampQty = () => { qtyInput.value = Math.min(99, Math.max(1, parseInt(qtyInput.value, 10) || 1)); };
  $('#qtyMinus').addEventListener('click', () => { qtyInput.value = +qtyInput.value - 1; clampQty(); });
  $('#qtyPlus').addEventListener('click', () => { qtyInput.value = +qtyInput.value + 1; clampQty(); });
  qtyInput.addEventListener('change', clampQty);

  // ===== Add to cart (uses cart helpers from main.js) =====
  const addBtn = $('#pdpAdd');
  addBtn.addEventListener('click', () => {
    clampQty();
    const qty = +qtyInput.value;
    const item = cart.find(i => i.id === p.id);
    item ? item.qty += qty : cart.push({ id: p.id, qty });
    save('outdoors_cart', cart);
    updateCounts();
    bump($('#cartCount'));
    showToast(`${qty} × ${p.name} added to cart`);
    addBtn.classList.add('added');
    addBtn.innerHTML = '<i class="fa-solid fa-check"></i> Added';
    setTimeout(() => {
      addBtn.classList.remove('added');
      addBtn.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Add to Cart';
    }, 1500);
  });

  // ===== Wishlist =====
  const wishBtn = $('#pdpWish');
  const renderWish = () => {
    const on = wishlist.includes(p.id);
    wishBtn.classList.toggle('active', on);
    wishBtn.innerHTML = `<i class="fa-${on ? 'solid' : 'regular'} fa-heart"></i>`;
  };
  wishBtn.addEventListener('click', () => {
    const on = wishlist.includes(p.id);
    wishlist = on ? wishlist.filter(w => w !== p.id) : [...wishlist, p.id];
    save('outdoors_wishlist', wishlist);
    updateCounts();
    bump($('#wishCount'));
    renderWish();
    showToast(on ? `${p.name} removed from wishlist` : `${p.name} added to wishlist`);
  });
  renderWish();

  // ===== Tabs =====
  $('#pdpDesc').innerHTML = `
    <p>The <strong>${p.name}</strong> from ${brand} is part of our ${p.subName.toLowerCase()} lineup. ${ABOUT[p.category] || ''}</p>
    <ul>
      <li>Genuine ${brand} product, brand new in original packaging</li>
      <li>Category: ${p.categoryName} › ${p.subName}</li>
      <li>Rated ${p.rating.toFixed(1)} out of 5 by ${p.reviews} customers</li>
      <li>Backed by our 60-day return policy</li>
    </ul>`;
  $('#pdpSpecs').innerHTML = [['Brand', brand], ['Category', p.categoryName], ['Type', p.subName], ['SKU', sku], ['Condition', 'New'], ['Warranty', 'Manufacturer warranty']]
    .map(([k, v]) => `<tr><th>${k}</th><td>${v}</td></tr>`).join('');
  $('#pdpReviews').innerHTML = `
    <div class="review-summary">
      <strong>${p.rating.toFixed(1)}</strong>
      <div><div class="rating">${stars(p.rating)}</div><span>Based on ${p.reviews} reviews</span></div>
    </div>`;

  $$('.tab').forEach(tab => tab.addEventListener('click', () => {
    $$('.tab, .tab-panel').forEach(el => el.classList.remove('active'));
    tab.classList.add('active');
    $(`.tab-panel[data-panel="${tab.dataset.tab}"]`).classList.add('active');
  }));
  $('#pdpRating').addEventListener('click', e => {
    e.preventDefault();
    $('#reviews').click();
    $('.pdp-details').scrollIntoView({ behavior: 'smooth' });
  });

  // ===== Related: same subcategory first, then same category =====
  const related = products
    .filter(r => r.id !== p.id && r.category === p.category)
    .sort((a, b) => (b.sub === p.sub) - (a.sub === p.sub))
    .slice(0, 4);
  $('#related').innerHTML = related.map(productCard).join('');
  $('#relatedAll').href = shopUrl(p.category);
  $('#relatedAll').textContent = `Shop All ${p.categoryName}`;
  if (!related.length) $('#related').closest('section').hidden = true;
}
