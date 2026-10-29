// ===== Shop page — requires data.js + main.js =====

const PAGE_SIZE = 12;
const params = new URLSearchParams(location.search);
const catSlug = params.get('cat');
const subSlug = params.get('sub');
const query = (params.get('q') || '').trim().toLowerCase();

const cat = categories.find(c => c.slug === catSlug);
const sub = cat && cat.subs.find(s => s.slug === subSlug);
let shown = PAGE_SIZE;

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

// ===== Sidebar category tree =====
$('#catTree').innerHTML = `<li><a href="shop.html" class="${!cat ? 'active' : ''}">All Products <span>${products.length}</span></a></li>`
  + categories.map(c => `
    <li class="${c === cat ? 'open' : ''}">
      <a href="${shopUrl(c.slug)}" class="${c === cat && !sub ? 'active' : ''}">${c.name} <span>${c.count}</span></a>
      ${c === cat ? `<ul>${c.subs.map(s => `<li><a href="${shopUrl(c.slug, s.slug)}" class="${s === sub ? 'active' : ''}">${s.name} <span>${s.count}</span></a></li>`).join('')}</ul>` : ''}
    </li>`).join('');

// ===== Products =====
function filtered() {
  let list = products.filter(p =>
    (!cat || p.category === cat.slug) &&
    (!sub || p.sub === sub.slug) &&
    (!query || `${p.name} ${p.categoryName} ${p.subName}`.toLowerCase().includes(query)));

  const sorters = {
    'price-asc': (a, b) => a.price - b.price,
    'price-desc': (a, b) => b.price - a.price,
    rating: (a, b) => b.rating - a.rating,
    name: (a, b) => a.name.localeCompare(b.name)
  };
  const sorter = sorters[$('#sortSelect').value];
  return sorter ? [...list].sort(sorter) : list;
}

function render() {
  const list = filtered();
  const grid = $('#shopGrid');
  grid.innerHTML = list.length
    ? list.slice(0, shown).map(productCard).join('')
    : '<p class="empty">No products found. <a href="shop.html">View all products</a></p>';
  $('#resultCount').textContent = list.length
    ? `Showing ${Math.min(shown, list.length)} of ${list.length} products`
    : '0 products';
  $('#loadMore').hidden = shown >= list.length;
}

if (params.get('sort')) $('#sortSelect').value = params.get('sort');
$('#sortSelect').addEventListener('change', () => { shown = PAGE_SIZE; render(); });
$('#loadMore').addEventListener('click', () => { shown += PAGE_SIZE; render(); });

// ===== Mobile filter drawer =====
function toggleSidebar(open) {
  $('#shopSidebar').classList.toggle('open', open);
  $('#sidebarOverlay').classList.toggle('show', open);
}
$('#filterBtn').addEventListener('click', () => toggleSidebar(true));
$('#sidebarClose').addEventListener('click', () => toggleSidebar(false));
$('#sidebarOverlay').addEventListener('click', () => toggleSidebar(false));

render();
