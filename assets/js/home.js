// ===== Homepage sections — requires data.js + main.js =====

const discount = p => Math.round((1 - p.price / p.oldPrice) * 100);
const productLink = p => shopUrl(p.category, p.sub);
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

// ===== Hot Buys: biggest discounts first =====
const hotBuys = products.filter(p => p.oldPrice).sort((a, b) => discount(b) - discount(a)).slice(0, 10);
$('#hotBuys').innerHTML = hotBuys.map(p => `
  <a href="${productLink(p)}" class="deal">
    <div class="deal-img">
      <span class="deal-badge">-${discount(p)}%</span>
      ${imgWithFallback(p.img, p.name, p.icon)}
    </div>
    <p class="deal-name">${p.name}</p>
    <p class="deal-price"><strong>${money(p.price)}</strong> <del>${money(p.oldPrice)}</del></p>
  </a>`).join('');
if (!hotBuys.length) hideSection($('#hotBuys'));

// ===== Hunting essentials grid =====
const essentials = products.filter(p => p.category === 'hunting-accessories').slice(0, 8);
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

// ===== Footer sign-up forms =====
['#signupForm', '#smsForm'].forEach(sel => {
  $(sel).addEventListener('submit', e => {
    e.preventDefault();
    e.target.reset();
    showToast('Thanks for signing up!');
  });
});
