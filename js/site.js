// ── Scroll-triggered sticky header ──────────────────────────────────────────
(function () {
  const hdr = document.querySelector('.site-header');
  if (!hdr) return;
  const THRESHOLD = 60;
  function onScroll() {
    hdr.classList.toggle('scrolled', window.scrollY > THRESHOLD);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll(); // set correct state on page load
})();

// ── Mobile nav toggle ───────────────────────────────────────────────────────
const toggle = document.querySelector('.nav-toggle');
const nav    = document.getElementById('main-nav');

if (toggle && nav) {
  toggle.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open);
  });

  // Close nav when a non-dropdown link is clicked
  nav.querySelectorAll('a').forEach(link => {
    if (!link.closest('.has-dropdown')) {
      link.addEventListener('click', () => {
        nav.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    }
  });

  document.addEventListener('click', e => {
    if (!nav.contains(e.target) && !toggle.contains(e.target)) {
      nav.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
}

// ── Mobile dropdown toggle ──────────────────────────────────────────────────
document.querySelectorAll('.has-dropdown > a').forEach(link => {
  link.addEventListener('click', e => {
    if (window.innerWidth <= 680) {
      e.preventDefault();
      const li = link.closest('.has-dropdown');
      // Close other open dropdowns
      document.querySelectorAll('.has-dropdown.open').forEach(el => {
        if (el !== li) el.classList.remove('open');
      });
      li.classList.toggle('open');
    }
  });
});
