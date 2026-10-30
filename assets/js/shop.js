// ===== Shop page — requires data.js + main.js =====

const PAGE_SIZE = 12;
const params = new URLSearchParams(location.search);
const catSlug = params.get('cat');
const subSlug = params.get('sub');
const query = (params.get('q') || '').trim().toLowerCase();

const cat = categories.find(c => c.slug === catSlug);
const sub = cat && cat.subs.find(s => s.slug === subSlug);
let shown = PAGE_SIZE;

const PRICE_PRESETS = [['Under $25', 0, 25], ['$25 – $50', 25, 50], ['$50 – $100', 50, 100], ['$100 – $200', 100, 200], ['$200 & Up', 200, null]];
const RATINGS = [4.5, 4];

// Filter state, seeded from the URL so filtered views can be shared
const state = {
  min: params.get('min') ? +params.get('min') : null,
  max: params.get('max') ? +params.get('max') : null,
  sale: params.get('sale') === '1',
  new: params.get('new') === '1',
  rating: params.get('rating') ? +params.get('rating') : null,
  brands: new Set((params.get('brand') || '').split(',').filter(Boolean))
};

// ===== Title & breadcrumb =====
const crumbs = [['Home', 'index.html'], ['Shop', 'shop.html']];
if (cat) crumbs.push([cat.name, shopUrl(cat.slug)]);
if (sub) crumbs.push([sub.name]);
if (query) crumbs.push([`Search: "${params.get('q')}"`]);

const last = crumbs.pop();
$('#breadcrumb').innerHTML = crumbs.map(([label, href]) => `<a href="${href}">${label}</a><i class="fa-solid fa-chevron-right"></i>`).join('')
  + `<span>${last[0]}</span>`;

const title = sub ? sub.name : cat ? cat.name : query ? `Results for "${params.get('q')}"` : 'All Products';
$('#shopTitle').textContent = title;
document.title = `${title} | Texpert Outdoors`;

// ===== Sidebar category tree (stocked categories only) =====
$('#catTree').innerHTML = `<li><a href="shop.html" class="${!cat ? 'active' : ''}">All Products <span>${products.length}</span></a></li>`
  + categories.filter(c => c.count || c === cat).map(c => `
    <li class="${c === cat ? 'open' : ''}">
      <a href="${shopUrl(c.slug)}" class="${c === cat && !sub ? 'active' : ''}">${c.name} <span>${c.count}</span></a>
      ${c === cat ? `<ul>${c.subs.filter(s => s.count || s === sub).map(s => `<li><a href="${shopUrl(c.slug, s.slug)}" class="${s === sub ? 'active' : ''}">${s.name} <span>${s.count}</span></a></li>`).join('')}</ul>` : ''}
    </li>`).join('');

// ===== Filtering =====
// Products in the current category / subcategory / search, before sidebar filters
const scope = products.filter(p =>
  (!cat || p.category === cat.slug) &&
  (!sub || p.sub === sub.slug) &&
  (!query || `${p.name} ${p.brand} ${p.categoryName} ${p.subName}`.toLowerCase().includes(query)));

const TESTS = {
  price: p => (state.min == null || p.price >= state.min) && (state.max == null || p.price <= state.max),
  sale: p => !state.sale || !!p.oldPrice,
  new: p => !state.new || p.tag === 'New',
  rating: p => state.rating == null || p.rating >= state.rating,
  brand: p => !state.brands.size || state.brands.has(p.brand)
};
// Every test except one facet, so each facet's counts reflect the other active filters
const passesExcept = (p, skip) => Object.entries(TESTS).every(([key, test]) => key === skip || test(p));

function filtered() {
  const list = scope.filter(p => passesExcept(p, null));
  const sorters = {
    'price-asc': (a, b) => a.price - b.price,
    'price-desc': (a, b) => b.price - a.price,
    rating: (a, b) => b.rating - a.rating || b.reviews - a.reviews,
    name: (a, b) => a.name.localeCompare(b.name)
  };
  const sorter = sorters[$('#sortSelect').value];
  return sorter ? [...list].sort(sorter) : list;
}

// ===== Facet rendering =====
const form = $('#filters');

function renderFacets() {
  form.min.value = state.min ?? '';
  form.max.value = state.max ?? '';
  $('#priceChips').innerHTML = PRICE_PRESETS.map(([label, min, max]) => {
    const n = scope.filter(p => passesExcept(p, 'price') && p.price >= min && (max == null || p.price <= max)).length;
    const on = state.min === min && state.max === max;
    return `<button type="button" class="chip${on ? ' on' : ''}" data-min="${min}" data-max="${max ?? ''}" ${n || on ? '' : 'disabled'}>${label} <span>${n}</span></button>`;
  }).join('');

  form.sale.checked = state.sale;
  form.new.checked = state.new;
  $('#saleCount').textContent = scope.filter(p => passesExcept(p, 'sale') && p.oldPrice).length;
  $('#newCount').textContent = scope.filter(p => passesExcept(p, 'new') && p.tag === 'New').length;

  $('#ratingOpts').innerHTML = RATINGS.map(r => {
    const n = scope.filter(p => passesExcept(p, 'rating') && p.rating >= r).length;
    return `<label class="check"><input type="radio" name="rating" value="${r}" ${state.rating === r ? 'checked' : ''}>
      <span class="rating">${stars(r)}</span> &amp; Up <span>${n}</span></label>`;
  }).join('') + `<label class="check"><input type="radio" name="rating" value="" ${state.rating == null ? 'checked' : ''}> Any rating</label>`;

  const counts = {};
  scope.filter(p => passesExcept(p, 'brand')).forEach(p => { counts[p.brand] = (counts[p.brand] || 0) + 1; });
  state.brands.forEach(b => { counts[b] = counts[b] || 0; });
  const search = $('#brandSearch');
  const term = search.value.trim().toLowerCase();
  const brands = Object.keys(counts).sort((a, b) => a.localeCompare(b)).filter(b => !term || b.toLowerCase().includes(term));
  $('#brandList').innerHTML = brands.length
    ? brands.map(b => `<label class="check"><input type="checkbox" name="brand" value="${b}" ${state.brands.has(b) ? 'checked' : ''}> ${b} <span>${counts[b]}</span></label>`).join('')
    : '<p class="filter-empty">No brands match.</p>';
  search.hidden = Object.keys(counts).length < 8;
}

function renderActive() {
  const chips = [];
  if (state.min != null || state.max != null) {
    chips.push(['price', state.max == null ? `${money(state.min)} & up` : `${money(state.min ?? 0)} – ${money(state.max)}`]);
  }
  if (state.sale) chips.push(['sale', 'On Sale']);
  if (state.new) chips.push(['new', 'New Arrivals']);
  if (state.rating != null) chips.push(['rating', `${state.rating}★ & Up`]);
  state.brands.forEach(b => chips.push([`brand:${b}`, b]));

  const box = $('#activeFilters');
  box.hidden = !chips.length;
  box.innerHTML = chips.map(([key, label]) => `<button type="button" class="chip on" data-remove="${key}">${label} <i class="fa-solid fa-xmark"></i></button>`).join('')
    + (chips.length ? '<button type="button" class="clear-all" data-remove="all">Clear all</button>' : '');
  $('#filterBtn').innerHTML = `<i class="fa-solid fa-sliders"></i> Filters${chips.length ? ` (${chips.length})` : ''}`;
}

function syncUrl() {
  const p = new URLSearchParams();
  if (catSlug) p.set('cat', catSlug);
  if (subSlug) p.set('sub', subSlug);
  if (params.get('q')) p.set('q', params.get('q'));
  if (state.min != null) p.set('min', state.min);
  if (state.max != null) p.set('max', state.max);
  if (state.sale) p.set('sale', '1');
  if (state.new) p.set('new', '1');
  if (state.rating != null) p.set('rating', state.rating);
  if (state.brands.size) p.set('brand', [...state.brands].join(','));
  const sort = $('#sortSelect').value;
  if (sort !== 'default') p.set('sort', sort);
  history.replaceState(null, '', `${location.pathname}${p.toString() ? `?${p}` : ''}`);
}

// ===== Products =====
function render() {
  const list = filtered();
  const filtersOn = !$('#activeFilters').hidden;
  $('#shopGrid').innerHTML = list.length
    ? list.slice(0, shown).map(productCard).join('')
    : `<div class="empty-state">
        <i class="fa-solid fa-magnifying-glass"></i>
        <h2>${scope.length ? 'No Matches' : 'Coming Soon'}</h2>
        <p>${scope.length ? 'No products match these filters. Try removing one.' : 'We are stocking this category now. Check back soon.'}</p>
        ${filtersOn ? '<button type="button" class="btn btn-primary" data-remove="all">Clear Filters</button>' : '<a href="shop.html" class="btn btn-primary">Shop All Products</a>'}
      </div>`;
  $('#resultCount').textContent = list.length
    ? `Showing ${Math.min(shown, list.length)} of ${list.length} products`
    : '0 products';
  $('#loadMore').hidden = shown >= list.length;
  $('#showResults').textContent = `Show ${list.length} Result${list.length === 1 ? '' : 's'}`;
}

function update() {
  shown = PAGE_SIZE;
  renderFacets();
  renderActive();
  render();
  syncUrl();
}

// ===== Events =====
const num = v => (v === '' || isNaN(+v) ? null : Math.max(0, +v));

form.addEventListener('change', e => {
  const t = e.target;
  if (t.name === 'min' || t.name === 'max') {
    state.min = num(form.min.value);
    state.max = num(form.max.value);
    if (state.min != null && state.max != null && state.min > state.max) [state.min, state.max] = [state.max, state.min];
  }
  if (t.name === 'sale') state.sale = t.checked;
  if (t.name === 'new') state.new = t.checked;
  if (t.name === 'rating') state.rating = t.value ? +t.value : null;
  if (t.name === 'brand') t.checked ? state.brands.add(t.value) : state.brands.delete(t.value);
  update();
});
form.addEventListener('submit', e => e.preventDefault());

$('#priceChips').addEventListener('click', e => {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  const min = +chip.dataset.min;
  const max = chip.dataset.max === '' ? null : +chip.dataset.max;
  const on = state.min === min && state.max === max;
  state.min = on ? null : min;
  state.max = on ? null : max;
  update();
});

$('#brandSearch').addEventListener('input', renderFacets);

// Remove a single active filter, or all of them
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-remove]');
  if (!btn) return;
  const key = btn.dataset.remove;
  if (key === 'all') Object.assign(state, { min: null, max: null, sale: false, new: false, rating: null, brands: new Set() });
  else if (key === 'price') state.min = state.max = null;
  else if (key === 'rating') state.rating = null;
  else if (key.startsWith('brand:')) state.brands.delete(key.slice(6));
  else state[key] = false;
  update();
});

if (params.get('sort')) $('#sortSelect').value = params.get('sort');
$('#sortSelect').addEventListener('change', () => { shown = PAGE_SIZE; render(); syncUrl(); });
$('#loadMore').addEventListener('click', () => { shown += PAGE_SIZE; render(); });

// ===== Mobile filter drawer =====
function toggleSidebar(open) {
  $('#shopSidebar').classList.toggle('open', open);
  $('#sidebarOverlay').classList.toggle('show', open);
  document.body.style.overflow = open ? 'hidden' : '';
}
$('#filterBtn').addEventListener('click', () => toggleSidebar(true));
$('#sidebarClose').addEventListener('click', () => toggleSidebar(false));
$('#sidebarOverlay').addEventListener('click', () => toggleSidebar(false));
$('#showResults').addEventListener('click', () => toggleSidebar(false));

update();
