/**
 * dashboard-find.js — filter what you can do here.
 *
 * An enhancement, not a requirement: the page ships every group open and every
 * link in the HTML, so with scripting off the box does nothing and nothing is
 * missing. Nothing is hidden until somebody types.
 *
 * It filters on a data-find string built server-side from each action's label
 * and its keywords, so "money" finds Expenses & Grants and "qr" finds the link
 * shortener without either word being on screen.
 */
(function () {
  'use strict';
  var box = document.getElementById('dashfind');
  if (!box) return;

  var count  = document.getElementById('dashcount');
  var empty  = document.getElementById('dashempty');
  var panels = [].slice.call(document.querySelectorAll('[data-panel]'));
  var items  = [].slice.call(document.querySelectorAll('[data-find]'));
  var quick  = document.getElementById('dashquick');
  var quickSection = quick ? quick.closest('[data-section]') : null;

  /* ── Minimize / Expand ────────────────────────────────────────────────
   * The buttons ship with `hidden` so a page without scripting has no dead
   * control and nothing collapsed. Taking them over here is what makes them
   * real.
   *
   * $preSearch remembers what the member had collapsed before they searched,
   * because a search opens whatever it matches: without it, clearing the box
   * would leave every panel open and quietly undo their choices. */
  var toggles   = [].slice.call(document.querySelectorAll('.dash-toggle'));
  var preSearch = null;

  function setOpen(btn, open) {
    var body = document.getElementById(btn.getAttribute('aria-controls'));
    if (!body) return;
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    // hidden, not a class: it takes the content out of the accessibility tree
    // as well as off the screen, which is what minimizing should mean.
    if (open) body.removeAttribute('hidden');
    else      body.setAttribute('hidden', '');
    var verb = open ? 'Minimize' : 'Expand';
    var txt  = btn.querySelector('.dash-toggle-text');
    if (txt) txt.textContent = verb;
    // The visible word is the same on every panel; the accessible name says
    // which one, and has to follow the state like the word does.
    var what = btn.getAttribute('data-label');
    if (what) btn.setAttribute('aria-label', verb + ' ' + what);
  }
  function isOpen(btn) { return btn.getAttribute('aria-expanded') !== 'false'; }

  toggles.forEach(function (btn) {
    btn.removeAttribute('hidden');
    btn.addEventListener('click', function () {
      setOpen(btn, !isOpen(btn));
      // Collapsing by hand during a search makes that the new intent.
      if (preSearch) preSearch[btn.getAttribute('aria-controls')] = isOpen(btn);
    });
  });

  function apply() {
    var q = box.value.trim().toLowerCase();
    if (!q) {
      // Back to the page as it was served, rather than to whatever the last
      // search left behind.
      items.forEach(function (el) { el.style.display = ''; });
      panels.forEach(function (g) { g.style.display = ''; });
      if (quickSection) quickSection.style.display = '';
      // Back to what they had chosen before they typed, not to all-open.
      if (preSearch) {
        toggles.forEach(function (btn) {
          var want = preSearch[btn.getAttribute('aria-controls')];
          if (want !== undefined) setOpen(btn, want);
        });
        preSearch = null;
      }
      empty.style.display = 'none';
      count.textContent = '';
      return;
    }

    // Every word has to land somewhere, so "grant csv" finds the review page
    // and not everything mentioning either word.
    // Taken once, on the first keystroke of a search, so later keystrokes do
    // not record the opened-for-matching state as the member's own.
    if (!preSearch) {
      preSearch = {};
      toggles.forEach(function (btn) {
        preSearch[btn.getAttribute('aria-controls')] = isOpen(btn);
      });
    }

    var words = q.split(/\s+/);
    /* Counted per DESTINATION, not per row. The three quick cards are
     * shortcuts to pages that also appear in their group, so counting
     * elements reported "2 matches" for one page. */
    var hits = {};
    items.forEach(function (el) {
      var hay = el.getAttribute('data-find') || '';
      var hit = words.every(function (w) { return hay.indexOf(w) !== -1; });
      el.style.display = hit ? '' : 'none';
      if (hit) hits[el.getAttribute('data-dest') || hay] = true;
    });
    var shown = Object.keys(hits).length;

    // A heading with nothing under it reads as an empty shelf. A panel that
    // DOES match is opened for the duration, so a hit is never hidden inside
    // something the member had minimized.
    panels.forEach(function (g) {
      var any = [].slice.call(g.querySelectorAll('[data-find]'))
                  .some(function (el) { return el.style.display !== 'none'; });
      g.style.display = any ? '' : 'none';
      var btn = g.querySelector('.dash-toggle');
      if (btn && any) setOpen(btn, true);
    });
    if (quickSection) {
      var anyQuick = [].slice.call(quick.querySelectorAll('[data-find]'))
                       .some(function (el) { return el.style.display !== 'none'; });
      // The whole section, so its "Quick access" heading goes with the cards.
      quickSection.style.display = anyQuick ? '' : 'none';
    }

    empty.style.display = shown ? 'none' : 'block';
    // Spoken, not just drawn: role="status" on the paragraph announces it.
    count.textContent = shown === 1 ? '1 match' : shown + ' matches';
  }

  box.addEventListener('input', apply);
  box.addEventListener('search', apply);   // the X in a type="search" field

  /* Capture, and stop the event dead.
   *
   * search.js binds the same chord on document to open the site-wide search
   * overlay, and it is loaded first — so without this both fire and you get
   * the overlay on top of a focused dashboard box. On this page the nearer
   * search is the one that should answer the shortcut; the overlay is still a
   * click on the magnifier, and "/" still opens it. */
  document.addEventListener('keydown', function (e) {
    if (!(e.metaKey || e.ctrlKey) || (e.key !== 'k' && e.key !== 'K')) return;
    /* While the global overlay is up it owns the chord: taking it then moved
     * focus to a box behind the dialog, where the typing went nowhere
     * visible. search.js locks the body when its overlay opens, so that is
     * the signal — not a handle on an element this file should not know. */
    if (document.body.classList.contains('so-lock')) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    box.focus();
    box.select();
  }, true);

  // Escape clears rather than only blurring, so the page comes back. Not in
  // the capture handler above: search.js wants Escape for closing its overlay.
  box.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && box.value !== '') {
      e.stopPropagation();
      box.value = '';
      apply();
    }
  });
})();
