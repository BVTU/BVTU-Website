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

// ── Header menus ────────────────────────────────────────────────────────────
// One controller for the hamburger and the section menus, at every width.
//
// Each section has a link (goes to the section page) and a separate button
// (opens its menu). Closed menus carry `hidden`, so they are neither shown nor
// reachable by Tab. The old version opened menus by CSS :hover/:focus-within
// above 1280px and by tapping the link below 681px — and between the two
// nothing at all could open them on a tablet.
//
// "Collapsed" (hamburger) mode is read from the page, not from a pixel width
// written here: if the hamburger button is displayed, the header is collapsed.
// So the CSS breakpoint is the only breakpoint.
(function () {
  const header = document.querySelector('.site-header');
  const toggle = document.querySelector('.nav-toggle');
  const nav    = document.getElementById('main-nav');
  if (!header || !nav) return;

  const items = Array.prototype.slice.call(nav.querySelectorAll('.has-dropdown'));
  const HOVER_CLOSE_MS = 250;
  // Some WebKit views expose mouse events without the PointerEvent API.
  // Use their standard mouse events, but only on hardware that can hover.
  const hasPointerEvents = 'PointerEvent' in window;
  function isMouseHover(e) {
    return hasPointerEvents ? e.pointerType === 'mouse'
      : window.matchMedia('(any-hover: hover)').matches;
  }
  let suppressHover = null;       // the item a user just closed while pointing at it

  function collapsed() {
    return !!toggle && getComputedStyle(toggle).display !== 'none';
  }
  function partsOf(li) {
    return { btn: li.querySelector('.nav-sub-toggle'), menu: li.querySelector('.dropdown') };
  }
  function isOpen(li) {
    const p = partsOf(li);
    return !!p.menu && !p.menu.hidden;
  }

  // Keep a desktop menu on screen: centred under its section, then nudged in
  // from whichever edge it would cross.
  function fit(li) {
    const menu = partsOf(li).menu;
    if (!menu) return;
    menu.style.setProperty('--dd-shift', '0px');
    if (collapsed()) return;
    const r = menu.getBoundingClientRect();
    const vw = document.documentElement.clientWidth || window.innerWidth;
    let shift = 0;
    if (r.right > vw - 8) shift = (vw - 8) - r.right;
    if (r.left + shift < 8) shift = 8 - r.left;
    if (shift) menu.style.setProperty('--dd-shift', Math.round(shift) + 'px');
  }

  function open(li, how) {
    items.forEach(other => { if (other !== li) close(other); });
    const p = partsOf(li);
    if (!p.btn || !p.menu) return;
    clearTimeout(li._navTimer);
    p.menu.hidden = false;
    p.btn.setAttribute('aria-expanded', 'true');
    li.classList.add('open');
    li._navHow = how;
    fit(li);
  }
  function close(li) {
    const p = partsOf(li);
    clearTimeout(li._navTimer);
    if (!p.btn || !p.menu) return;
    p.menu.hidden = true;
    p.btn.setAttribute('aria-expanded', 'false');
    li.classList.remove('open');
    li._navHow = '';
  }
  function closeAll() { items.forEach(close); }

  function setNavOpen(on) {
    if (!toggle) return;
    nav.classList.toggle('open', on);
    toggle.setAttribute('aria-expanded', on ? 'true' : 'false');
    if (!on) {
      closeAll();
      suppressHover = null;
    }
  }

  if (toggle) {
    toggle.addEventListener('click', () => setNavOpen(!nav.classList.contains('open')));
  }

  items.forEach(li => {
    const p = partsOf(li);
    if (!p.btn || !p.menu) return;

    // A native button, so Enter and Space arrive here as clicks.
    p.btn.addEventListener('click', e => {
      e.preventDefault();
      // Moving onto the button can open the menu by hover before the click
      // arrives. That first click pins it open; a second click closes it.
      if (isOpen(li) && li._navHow !== 'hover') {
        close(li);
        // Still under the pointer: do not let hover reopen what was just closed.
        if (li.matches(':hover')) suppressHover = li;
      } else {
        open(li, 'click');
      }
    });

    // A physical mouse can hover in either layout once navigation is visible.
    // Touch stays button-driven; hovering never overrides an explicit close.
    li.addEventListener(hasPointerEvents ? 'pointerenter' : 'mouseenter', e => {
      if (!isMouseHover(e) || (collapsed() && !nav.classList.contains('open'))) return;
      clearTimeout(li._navTimer);
      if (suppressHover === li || isOpen(li)) return;
      open(li, 'hover');
    });
    li.addEventListener(hasPointerEvents ? 'pointerleave' : 'mouseleave', e => {
      if (!isMouseHover(e) || (collapsed() && !nav.classList.contains('open'))) return;
      if (suppressHover === li) suppressHover = null;
      if (li._navHow !== 'hover') return;
      li._navTimer = setTimeout(() => close(li), HOVER_CLOSE_MS);
    });

    // Tabbing out of a desktop menu closes it. Only when focus has actually
    // gone somewhere else: a click on the menu's own padding moves focus to
    // nothing, and that is the click-outside handler's business, not this one.
    li.addEventListener('focusout', e => {
      const to = e.relatedTarget;
      if (!to || li.contains(to) || collapsed()) return;
      close(li);
    });
  });

  // Choosing a link closes everything — it matters for in-page links, where
  // the page does not change and the menu would otherwise stay open over it.
  nav.addEventListener('click', e => {
    const a = e.target.closest ? e.target.closest('a[href]') : null;
    if (!a) return;
    closeAll();
    if (toggle && nav.classList.contains('open')) setNavOpen(false);
  });

  document.addEventListener('click', e => {
    const t = e.target;
    items.forEach(li => { if (isOpen(li) && !li.contains(t)) close(li); });
    if (toggle && nav.classList.contains('open') && !nav.contains(t) && !toggle.contains(t)) {
      setNavOpen(false);
    }
  });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape' && e.key !== 'Esc') return;
    const openLi = items.filter(isOpen)[0];
    if (openLi) {
      const btn = partsOf(openLi).btn;
      const hadFocus = openLi.contains(document.activeElement);
      close(openLi);
      if (openLi.matches(':hover')) suppressHover = openLi;
      if (hadFocus && btn) btn.focus();
      e.preventDefault();
      return;
    }
    if (toggle && nav.classList.contains('open')) {
      const hadFocus = nav.contains(document.activeElement);
      setNavOpen(false);
      if (hadFocus || document.activeElement === toggle) toggle.focus();
      e.preventDefault();
    }
  });

  // Crossing between the bar and the hamburger starts both from closed, so a
  // menu opened in one layout is never left half-open in the other.
  let wasCollapsed = collapsed();
  window.addEventListener('resize', () => {
    const now = collapsed();
    if (now !== wasCollapsed) {
      wasCollapsed = now;
      closeAll();
      setNavOpen(false);
      return;
    }
    items.forEach(li => { if (isOpen(li)) fit(li); });
  });
})();

// ── Floating search bubble (FAB) ────────────────────────────────────────────
(function () {
  const fab = document.createElement('button');
  fab.className = 'search-fab';
  fab.setAttribute('data-search-open', '');
  fab.setAttribute('aria-label', 'Search & Ask AI');
  fab.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="22" height="22"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';
  document.body.appendChild(fab);
})();

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
      here === 'trustee-zones.php' || here === 'trustee-candidates.php') return;

  // Run now if the document is already parsed. This file is loaded at the end
  // of <body> today, so DOMContentLoaded has not fired yet — but waiting on an
  // event that has already passed is a silent no-op, and that is a bad way to
  // find out someone added defer or moved the tag.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', build);
  } else {
    build();
  }

  /*
   * css/style.css is served with max-age=604800, so a visitor who came by in
   * the last week still holds a copy from before these rules existed. They get
   * fresh HTML and stale CSS, and an unstyled bar would drop as plain text at
   * the foot of the page — worse than no bar at all. So the bar only appears
   * once its styles have actually arrived. Each visitor picks it up when their
   * cached copy expires; the homepage block is not affected, because index.php
   * asks for the stylesheet by a versioned URL.
   */
  function stylesReady() {
    var probe = document.createElement('div');
    probe.className = 'vote-bar';
    probe.style.cssText = 'visibility:hidden';
    document.body.appendChild(probe);
    var fixed = getComputedStyle(probe).position === 'fixed';
    probe.remove();
    return fixed;
  }

  function build() {
    if (!stylesReady()) return;
    var bar = document.createElement('div');
    bar.className = 'vote-bar';
    bar.innerHTML =
      '<div class="container">' +
        '<span><strong>School trustee election</strong> &middot; 17 October</span>' +
        '<a href="trustee-zones.php">Find your voting zone</a>' +
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

/* ── Page-view beacon ─────────────────────────────────────────────────────────
 * Tells track.php which page was opened. One file reaches all 31 public pages,
 * which is why this lives here rather than in markup.
 *
 * It sends no identifier of any kind. The server derives a day-scoped visitor
 * hash from the request itself and stores neither the IP nor the user-agent —
 * see members/analytics-db.php for what is and is not kept.
 *
 * It declines to send at all when the visitor has asked not to be tracked, and
 * it never runs inside members/: what a logged-in member reads is not counted.
 * ------------------------------------------------------------------------- */
(function () {
  // Do Not Track and Global Privacy Control. Honoured because a request not to
  // be counted is easy to respect and the numbers are fine without those few.
  var n = navigator;
  if (n.globalPrivacyControl === true) return;
  var dnt = n.doNotTrack || window.doNotTrack || n.msDoNotTrack;
  if (dnt === '1' || dnt === 'yes') return;

  if (location.pathname.indexOf('/members/') !== -1) return;
  if (location.protocol !== 'http:' && location.protocol !== 'https:') return;

  /*
   * fetch first, with credentials omitted.
   *
   * sendBeacon always sends same-origin cookies, so on a page where someone is
   * signed in their session cookie would ride along — which contradicts
   * "sends no identifier of any kind". fetch with keepalive survives the page
   * being closed just as beacon does; beacon is the fallback for browsers
   * without keepalive, where the alternative is no measurement at all.
   */
  function post(body) {
    try {
      if (window.fetch && 'keepalive' in new Request('')) {
        fetch('/track.php', { method: 'POST', body: body, keepalive: true,
                              credentials: 'omit', mode: 'same-origin' })
          .catch(function () {});
      } else if (n.sendBeacon) {
        // As a Blob with an explicit type: sendBeacon posts URLSearchParams as
        // text/plain, and PHP only fills $_POST for form encodings, so the hit
        // would arrive and be thrown away.
        n.sendBeacon('/track.php',
          new Blob([body.toString()], { type: 'application/x-www-form-urlencoded' }));
      }
    } catch (e) { /* analytics never breaks a page */ }
  }

  /*
   * Which links people actually use.
   *
   * Four kinds, because they answer different questions: 'doc' is a document
   * being downloaded, 'out' is somebody leaving for another site, 'go' is a
   * bvtu.ca/go/ short link, and 'int' is movement around this site. Only a
   * path, or a host and path, is ever sent — never a query string, which on an
   * outbound search or a form link is where personal detail would sit.
   */
  var DOC = /\.(pdf|docx?|pptx?|xlsx?|csv)$/i;

  document.addEventListener('click', function (ev) {
    // Bubble phase, after the page's own handlers, and only for clicks that
    // were allowed to proceed. (The old mobile nav cancelled taps on its
    // dropdown parents; in capture phase those were counted as visits to pages
    // nobody opened. Any handler that cancels a click is still respected.)
    if (ev.defaultPrevented) return;
    if (ev.button !== undefined && ev.button !== 0) return;   // not a left click

    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a) return;

    var href = a.getAttribute('href') || '';
    if (!href || href.charAt(0) === '#' || /^(mailto|tel|javascript):/i.test(href)) return;

    var url;
    try { url = new URL(href, location.href); } catch (e) { return; }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;

    var kind, target;
    if (url.host !== location.host) {
      kind = 'out';
      target = url.host + url.pathname;          // no query string
    } else if (/^\/go\//.test(url.pathname)) {
      kind = 'go';
      target = url.pathname;
    } else if (DOC.test(url.pathname)) {
      kind = 'doc';
      target = url.pathname;
    } else {
      kind = 'int';
      target = url.pathname;
    }

    var body = new URLSearchParams();
    body.set('p', location.pathname);
    body.set('k', kind);
    body.set('t', target.slice(0, 255));
    post(body);
  });

  function send() {
    try {
      var body = new URLSearchParams();
      body.set('p', location.pathname);
      // Only the referring host leaves the browser, never the full URL. The
      // server keeps a category and a hostname and nothing else, and a search
      // referrer's query string — which is the personal part — has no reason to
      // travel at all.
      var ref = '';
      try { ref = document.referrer ? new URL(document.referrer).hostname : ''; }
      catch (e) { ref = ''; }
      body.set('r', ref);
      post(body);
    } catch (e) { /* analytics never breaks a page */ }
  }

  // After load, so measuring never competes with rendering.
  if (document.readyState === 'complete') send();
  else window.addEventListener('load', send);
})();
