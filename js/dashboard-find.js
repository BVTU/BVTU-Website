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

  function apply() {
    var q = box.value.trim().toLowerCase();
    if (!q) {
      // Back to the page as it was served, rather than to whatever the last
      // search left behind.
      items.forEach(function (el) { el.style.display = ''; });
      panels.forEach(function (g) { g.style.display = ''; });
      if (quickSection) quickSection.style.display = '';
      empty.style.display = 'none';
      count.textContent = '';
      return;
    }

    // Every word has to land somewhere, so "grant csv" finds the review page
    // and not everything mentioning either word.
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

    // A heading with nothing under it reads as an empty shelf.
    panels.forEach(function (g) {
      var any = [].slice.call(g.querySelectorAll('[data-find]'))
                  .some(function (el) { return el.style.display !== 'none'; });
      g.style.display = any ? '' : 'none';
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
