/**
 * BVTU Site Search — Algolia-powered
 * Instant overlay search with keyboard shortcuts
 */
(function () {
  'use strict';

  const CFG = {
    appId:     'IUEMJN3YMB',
    searchKey: 'f743d9e8113b01fbb593d0d5ea592854',
    index:     'bvtu_content',
  };

  let debounceTimer = null;
  let overlayEl     = null;
  let isOpen        = false;

  // ── Algolia REST query ──────────────────────────────────────────
  async function algoliaSearch(query) {
    const url = `https://${CFG.appId}-dsn.algolia.net/1/indexes/${CFG.index}/query`;
    const resp = await fetch(url, {
      method: 'POST',
      headers: {
        'X-Algolia-Application-Id': CFG.appId,
        'X-Algolia-API-Key':        CFG.searchKey,
        'Content-Type':             'application/json',
      },
      body: JSON.stringify({
        query,
        hitsPerPage:            8,
        attributesToHighlight:  ['title'],
        attributesToSnippet:    ['content:25'],
        snippetEllipsisText:    '…',
      }),
    });
    if (!resp.ok) throw new Error('Search error');
    const data = await resp.json();
    return data.hits || [];
  }

  // ── Icons ───────────────────────────────────────────────────────
  const ICONS = {
    search:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="20" height="20"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>`,
    page:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>`,
    document: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`,
    close:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><path d="M18 6 6 18M6 6l12 12"/></svg>`,
  };

  // ── Build overlay DOM (once) ────────────────────────────────────
  function buildOverlay() {
    const el = document.createElement('div');
    el.id = 'bvtu-search-overlay';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Search');
    el.innerHTML = `
      <div class="so-backdrop"></div>
      <div class="so-box">
        <div class="so-input-row">
          <span class="so-icon">${ICONS.search}</span>
          <input id="so-input"
                 type="search"
                 placeholder="Search BVTU…"
                 autocomplete="off"
                 spellcheck="false"
                 aria-label="Search">
          <button id="so-close" aria-label="Close search">${ICONS.close}</button>
        </div>
        <ul id="so-results" role="listbox" aria-label="Search results"></ul>
        <div class="so-footer">
          <span><kbd>↵</kbd> all results</span>
          <span><kbd>Esc</kbd> close</span>
          <span><kbd>/</kbd> open</span>
          <a class="so-algolia" href="https://www.algolia.com" target="_blank" rel="noopener" tabindex="-1">
            Search by Algolia
          </a>
        </div>
      </div>`;
    document.body.appendChild(el);

    // Bind events
    el.querySelector('.so-backdrop').addEventListener('click', close);
    document.getElementById('so-close').addEventListener('click', close);

    const input = document.getElementById('so-input');
    input.addEventListener('input', onInput);
    input.addEventListener('keydown', onKeydown);

    return el;
  }

  // ── Open / Close ────────────────────────────────────────────────
  function open() {
    if (isOpen) return;
    if (!overlayEl) overlayEl = buildOverlay();
    overlayEl.classList.add('open');
    document.body.classList.add('so-lock');
    isOpen = true;
    // Small delay so transition plays before focus
    setTimeout(() => {
      const input = document.getElementById('so-input');
      if (input) input.focus();
    }, 40);
  }

  function close() {
    if (!isOpen || !overlayEl) return;
    overlayEl.classList.remove('open');
    document.body.classList.remove('so-lock');
    isOpen = false;
    const input = document.getElementById('so-input');
    if (input) input.value = '';
    renderResults([], '');
  }

  // ── Input handling ──────────────────────────────────────────────
  function onInput(e) {
    clearTimeout(debounceTimer);
    const q = e.target.value.trim();
    if (!q) { renderResults([], ''); return; }
    debounceTimer = setTimeout(() => runSearch(q), 180);
  }

  function onKeydown(e) {
    if (e.key === 'Enter') {
      const q = (document.getElementById('so-input')?.value || '').trim();
      if (q) {
        // Determine path prefix based on current page location
        const base = document.querySelector('meta[name="site-root"]')?.content || '';
        window.location.href = base + 'search.php?q=' + encodeURIComponent(q);
      }
    }
  }

  // ── Search + render ─────────────────────────────────────────────
  async function runSearch(q) {
    const list = document.getElementById('so-results');
    if (!list) return;
    list.innerHTML = '<li class="so-state">Searching…</li>';
    try {
      const hits = await algoliaSearch(q);
      renderResults(hits, q);
    } catch {
      list.innerHTML = '<li class="so-state so-error">Search unavailable — please try again.</li>';
    }
  }

  function renderResults(hits, q) {
    const list = document.getElementById('so-results');
    if (!list) return;

    if (!hits.length) {
      list.innerHTML = q
        ? `<li class="so-state">No results for <strong>${esc(q)}</strong></li>`
        : '';
      return;
    }

    list.innerHTML = hits.map(hit => {
      const title   = hit._highlightResult?.title?.value   ?? esc(hit.title ?? '');
      const snippet = hit._snippetResult?.content?.value   ?? '';
      const icon    = ICONS[hit.type] ?? ICONS.page;
      const badge   = hit.members_only
        ? '<span class="so-members-badge">Members</span>'
        : '';
      const typeClass = esc(hit.type ?? 'page');

      return `
        <li role="option">
          <a href="${esc(hit.url ?? '#')}" class="so-result">
            <span class="so-result-icon so-result-icon--${typeClass}">${icon}</span>
            <span class="so-result-body">
              <span class="so-result-title">${title}</span>
              ${snippet ? `<span class="so-result-snippet">${snippet}</span>` : ''}
            </span>
            ${badge}
          </a>
        </li>`;
    }).join('');
  }

  // ── Helpers ─────────────────────────────────────────────────────
  function esc(str) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(str ?? '').replace(/[&<>"']/g, c => map[c]);
  }

  // ── Keyboard shortcuts ──────────────────────────────────────────
  document.addEventListener('keydown', e => {
    const tag     = document.activeElement?.tagName ?? '';
    const editing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)
                    || document.activeElement?.isContentEditable;

    // / or Cmd/Ctrl+K → open
    if (!editing && e.key === '/') { e.preventDefault(); open(); return; }
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); open(); return; }

    // Esc → close
    if (e.key === 'Escape' && isOpen) { close(); return; }
  });

  // ── Wire all search trigger buttons ────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-search-open]').forEach(btn => {
      btn.addEventListener('click', e => { e.preventDefault(); open(); });
    });
  });

})();
