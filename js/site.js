// ── Scroll-triggered sticky header ──────────────────────────────────────────
// Only runs on hero-mode pages (index.php). Inner pages keep their
// green header — they're often short and can't scroll back past the
// threshold to dismiss the white state.
(function () {
  const hdr = document.querySelector('.site-header.hero-mode');
  if (!hdr) return;
  const THRESHOLD = 60;
  function onScroll() {
    hdr.classList.toggle('scrolled', window.scrollY > THRESHOLD);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
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

// ── Floating search bubble (FAB) ────────────────────────────────────────────
(function () {
  const fab = document.createElement('button');
  fab.className = 'search-fab';
  fab.setAttribute('data-search-open', '');
  fab.setAttribute('aria-label', 'Search & Ask AI');
  fab.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="22" height="22"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';
  document.body.appendChild(fab);
})();

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

/* ── Trustee election strip ───────────────────────────────────────────────────
 * A slim bar under the header on every page that loads this file.
 *
 * It lives here rather than in the markup because the site has no shared
 * header: all 31 public pages carry their own copy of the nav, so adding a bar
 * to each means 31 edits to put it up and 31 to take it down. This is one
 * block, and deleting it removes the bar everywhere.
 *
 * It removes itself after voting day. That check runs on the visitor's clock,
 * which can be wrong — acceptable for a banner, and it fails in the harmless
 * direction: someone with a badly set clock sees it a day early or late.
 * The homepage block in index.php uses the server's date and is the reliable
 * one. Dismissal is remembered per browser; if storage is unavailable the bar
 * simply shows again, which is better than not showing at all.
 * ------------------------------------------------------------------------- */
(function () {
  var LAST_DAY = '2026-10-17';           // general voting day
  var KEY      = 'bvtu-vote-bar-2026';

  function today() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0')
                           + '-' + String(d.getDate()).padStart(2, '0');
  }
  if (today() > LAST_DAY) return;

  try { if (localStorage.getItem(KEY) === 'dismissed') return; } catch (e) { /* no storage: show it */ }

  // Not on the pages it points at — nothing to advertise once you are there,
  // and on the map it would sit on top of the map. Not on the homepage either,
  // which carries the full block already; two versions of the same message on
  // one screen reads as clutter, not emphasis.
  // Not inside members/: those pages load this file as ../js/site.js, so these
  // document-relative links would 404 there — and the member tools are where
  // people go to file expenses, not somewhere to campaign at them.
  if (location.pathname.indexOf('/members/') !== -1) return;

  var here = location.pathname.split('/').pop();
  if (here === '' || here === 'index.php' ||
      here === 'trustee-zones.html' || here === 'trustee-candidates.php') return;

  // Run now if the document is already parsed. This file is loaded at the end
  // of <body> today, so DOMContentLoaded has not fired yet — but waiting on an
  // event that has already passed is a silent no-op, and that is a bad way to
  // find out someone added defer or moved the tag.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }

  function build() {
    var bar = document.createElement('div');
    bar.className = 'vote-bar';
    bar.innerHTML =
      '<div class="container">' +
        '<span><strong>School trustee election</strong> &middot; 17 October</span>' +
        '<a href="trustee-zones.html">Your zone &amp; who we endorse</a>' +
        '<span class="sep">|</span>' +
        '<a href="trustee-candidates.php">Candidate responses</a>' +
        '<button class="close" type="button" aria-label="Dismiss">&times;</button>' +
      '</div>';

    bar.querySelector('.close').addEventListener('click', function () {
      bar.dispatchEvent(new Event('vote-bar-closed'));
      bar.remove();
      document.body.classList.remove('has-vote-bar');
      document.body.style.paddingBottom = '';
      try { localStorage.setItem(KEY, 'dismissed'); } catch (e) { /* it comes back; fine */ }
    });

    document.body.appendChild(bar);
    document.body.classList.add('has-vote-bar');

    // The bar is fixed to the bottom, so without this it covers the last of the
    // footer. Measured rather than assumed: it wraps to two lines on a phone.
    function fit() {
      var h = bar.offsetHeight;
      document.body.style.paddingBottom = h + 'px';
      document.documentElement.style.setProperty('--vote-bar-h', h + 'px');
    }
    fit();
    window.addEventListener('resize', fit);
    // Dropped on dismiss, or fit() keeps firing against a detached node and
    // writes padding-bottom:0 over whatever the page set.
    bar.addEventListener('vote-bar-closed', function () {
      window.removeEventListener('resize', fit);
    });
  }
})();
