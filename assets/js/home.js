// ===== Homepage sections — requires data.js + main.js =====

const discount = p => Math.round((1 - p.price / p.oldPrice) * 100);
const productLink = productUrl;
const hideSection = el => { el.closest('section').hidden = true; };

// ===== Category row: stocked categories, cover = priciest product in each =====
$('#catRow').innerHTML = categories.filter(c => c.count).slice(0, 6).map(c => {
  const cover = products.filter(p => p.category === c.slug).sort((a, b) => b.price - a.price)[0];
  return `
  <a href="${shopUrl(c.slug)}" class="cat-chip">
    <div class="cat-chip-img">${imgWithFallback(cover.img, c.name, c.icon)}</div>
    <h3>${c.short}</h3>
    <span>${c.count} products</span>
  </a>`;
}).join('');

// Picks one in-stock item per section in turn (priciest first) until `limit` items are chosen
const pickAcross = (subs, limit, skip = /$^/) => {
  const bySub = subs.map(sub => products.filter(p => p.sub === sub && p.stock !== 0 && !skip.test(p.name)).sort((a, b) => b.price - a.price));
  const picks = [];
  for (let round = 0; picks.length < limit && bySub.some(list => list[round]); round++) {
    bySub.forEach(list => { if (list[round] && picks.length < limit) picks.push(list[round]); });
  }
  return picks;
};

// ===== Hot Buys: biggest discounts first; featured knives, shooting and optics when nothing is on sale =====
let hotBuys = products.filter(p => p.oldPrice).sort((a, b) => discount(b) - discount(a)).slice(0, 10);
if (hotBuys.length < 4) {
  // Guns lead the row (the store carries air rifles and pistols, no firearms), then knives, optics and shooting gear
  const noToys = /\b(electric|airsoft)\b/i;
  const guns = pickAcross(['air-rifles-and-pistols'], 6, noToys);
  const gear = pickAcross(['folding-knives', 'red-dot-sights', 'shooting-rests-and-bags', 'multi-tools', 'handgun-sights',
    'axes-and-hatchets', 'targets-and-accessories'], 6, noToys);
  hotBuys = guns.flatMap((gun, i) => [gun, gear[i]]).filter(Boolean);
  $('#hotBuysTitle').textContent = 'Featured Gear';
  $('#hotBuysLink').textContent = 'Shop All';
}
$('#hotBuys').innerHTML = hotBuys.map(p => `
  <a href="${productLink(p)}" class="deal">
    <div class="deal-img">
      ${p.oldPrice ? `<span class="deal-badge">-${discount(p)}%</span>` : ''}
      ${imgWithFallback(p.img, p.name, p.icon)}
    </div>
    <p class="deal-name">${p.name}</p>
    <p class="deal-price"><strong>${money(p.price)}</strong> ${p.oldPrice ? `<del>${money(p.oldPrice)}</del>` : ''}</p>
  </a>`).join('');
if (!hotBuys.length) hideSection($('#hotBuys'));

// ===== Hunting essentials grid =====
// Field gear only (no detergents, sprays or cases)
const essentials = pickAcross(['crossbows', 'broadheads', 'game-calls', 'fixed-blade-knives', 'bipods-and-tripods', 'blinds-and-seating',
  'bow-releases', 'scopes', 'slings', 'shooting-rests-and-bags', 'red-dot-sights', 'arrows'], 8, /\b(case|electric|airsoft|submachine)\b/i);
$('#essentials').innerHTML = essentials.map(productCard).join('');
if (!essentials.length) hideSection($('#essentials'));

// ===== Horizontal scrollers =====
$$('.scroll-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const track = $('#' + btn.dataset.target);
    const dir = btn.classList.contains('next') ? 1 : -1;
    track.scrollBy({ left: dir * track.clientWidth * 0.8, behavior: 'smooth' });
  });
});

// ===== Hero Slider =====
const slides = $$('.hero-slide');
const dotsWrap = $('#heroDots');
let current = 0;
let autoplay;

slides.forEach((_, i) => {
  const dot = document.createElement('button');
  dot.setAttribute('aria-label', `Go to slide ${i + 1}`);
  dot.addEventListener('click', () => { goTo(i); restart(); });
  dotsWrap.appendChild(dot);
});
const dots = $$('button', dotsWrap);

function goTo(index) {
  slides[current].classList.remove('active');
  dots[current].classList.remove('active');
  current = (index + slides.length) % slides.length;
  slides[current].classList.add('active');
  dots[current].classList.add('active');
}

function restart() {
  clearInterval(autoplay);
  autoplay = setInterval(() => goTo(current + 1), 6000);
}

$('#heroPrev').addEventListener('click', () => { goTo(current - 1); restart(); });
$('#heroNext').addEventListener('click', () => { goTo(current + 1); restart(); });

let touchX = 0;
$('#hero').addEventListener('touchstart', e => { touchX = e.touches[0].clientX; }, { passive: true });
$('#hero').addEventListener('touchend', e => {
  const diff = e.changedTouches[0].clientX - touchX;
  if (Math.abs(diff) > 50) { goTo(current + (diff < 0 ? 1 : -1)); restart(); }
});

goTo(0);
restart();
