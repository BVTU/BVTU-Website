/*
 * sort-by-date.js — click a Date column heading to order the rows by it.
 *
 * Used by the voucher editor and the voucher viewer. In the editor the rows are
 * physically reordered, so the order is what gets saved: sort_order is written
 * from the row position on submit. In the viewer it only changes what is on
 * screen.
 *
 * Two things the editor needs that a generic table sorter would get wrong. A
 * row split across categories has its breakdown in a second <tr> underneath,
 * which has to travel with it. And a row with no date yet is not "the earliest"
 * — it is unfinished, and belongs at the bottom whichever way the sort runs.
 *
 *   bvtuSortByDate({ body: '#expenseRows', header: '#thDate' });
 */
function bvtuSortByDate(opts) {
    var body   = document.querySelector(opts.body);
    var header = document.querySelector(opts.header);
    if (!body || !header) return;

    var dir = 0;   // 0 none, 1 oldest first, -1 newest first

    /** The date on a row, as a sortable number; 0 when it has none. */
    function dateOf(tr) {
        var input = tr.querySelector('input[type="date"]');
        var raw = input ? input.value : (tr.dataset.sortDate || '');
        if (!raw) {
            // The viewer prints the date rather than holding it in a field.
            var cell = tr.querySelector('[data-sort-date]');
            raw = cell ? cell.getAttribute('data-sort-date') : '';
        }
        if (!raw) return 0;
        var t = Date.parse(raw);
        return isNaN(t) ? 0 : t;
    }

    /** Each row with whatever belongs to it, kept together. */
    function groups() {
        var out = [];
        Array.prototype.forEach.call(body.children, function (tr) {
            if (/^split-/.test(tr.id) && out.length) {
                out[out.length - 1].extra.push(tr);   // a breakdown follows its row
            } else {
                out.push({ row: tr, extra: [] });
            }
        });
        return out;
    }

    function apply() {
        var g = groups();
        g.forEach(function (item, i) { item.i = i; });   // keep ties as they were
        g.sort(function (a, b) {
            var da = dateOf(a.row), db = dateOf(b.row);
            if (!da && !db) return a.i - b.i;
            if (!da) return 1;            // undated rows sink, both directions
            if (!db) return -1;
            if (da === db) return a.i - b.i;
            return dir === -1 ? db - da : da - db;
        });
        var frag = document.createDocumentFragment();
        g.forEach(function (item) {
            frag.appendChild(item.row);
            item.extra.forEach(function (e) { frag.appendChild(e); });
        });
        body.appendChild(frag);
    }

    header.classList.add('sortable');
    header.setAttribute('role', 'button');
    header.setAttribute('tabindex', '0');
    header.setAttribute('aria-sort', 'none');
    if (!header.querySelector('.sort-caret')) {
        var caret = document.createElement('span');
        caret.className = 'sort-caret';
        header.appendChild(caret);
    }

    function toggle() {
        dir = dir === 1 ? -1 : 1;
        apply();
        header.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
        header.classList.toggle('sort-asc',  dir === 1);
        header.classList.toggle('sort-desc', dir === -1);
        if (typeof opts.after === 'function') opts.after(dir);
    }

    header.addEventListener('click', toggle);
    header.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
    });
}
