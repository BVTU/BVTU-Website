/*
 * receipt-dupes.js — "haven't I already claimed this one?"
 *
 * The server decides whether a receipt has been seen before (lp-dupes.php);
 * this file is only how that answer reaches the person filing it. Two shapes,
 * because the two signals do not deserve the same interruption:
 *
 *   bvtuDupeAsk   identical bytes. Certain, so it stops and waits for an answer
 *                 before the file goes on a row.
 *   bvtuDupeTell  same vendor, day and amount, different bytes. Likely, not
 *                 certain, so it says so and gets out of the way.
 *
 * Deliberately never blocks: two identical coffees on one day is a real thing,
 * and a reminder that refuses the save would be worked around within a week.
 */
(function () {
  'use strict';

  var BAR_ID = 'bvtuDupeBar';

  function bar() {
    var el = document.getElementById(BAR_ID);
    if (el) return el;
    el = document.createElement('div');
    el.id = BAR_ID;
    el.setAttribute('role', 'alert');
    el.style.cssText = [
      'position:fixed', 'left:50%', 'transform:translateX(-50%)',
      'bottom:1.25rem', 'z-index:9999', 'max-width:min(34rem, calc(100vw - 2rem))',
      'background:#fffbeb', 'border:1px solid #f59e0b', 'border-radius:.6rem',
      'box-shadow:0 10px 30px rgba(0,0,0,.18)', 'padding:.85rem 1rem',
      'font-size:.86rem', 'color:#422006', 'display:none'
    ].join(';');
    document.body.appendChild(el);
    return el;
  }

  function hide() { var el = document.getElementById(BAR_ID); if (el) el.style.display = 'none'; }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function btn(label, primary) {
    return '<button type="button" style="' + [
      'border-radius:.4rem', 'padding:.4rem .85rem', 'font-size:.82rem',
      'font-weight:600', 'cursor:pointer',
      primary ? 'background:#b45309;color:#fff;border:1px solid #92400e'
              : 'background:#fff;color:#422006;border:1px solid #d6d3d1'
    ].join(';') + '">' + esc(label) + '</button>';
  }

  /*
   * One bar, so one question at a time.
   *
   * A poll batch is handled in a synchronous loop, so three photographs taken
   * in a row can each raise a question before any is answered. Without a queue
   * the second overwrites the first's buttons, and the first receipt is left
   * unclaimed and already marked as seen — never filed, never offered again,
   * and nothing on screen to say so.
   */
  var queue = [], asking = false;

  function askNext() {
    if (asking || !queue.length) return;
    asking = true;
    var q = queue.shift();
    render(q.dupe, {
      onSkip:    function () { asking = false; if (q.opts.onSkip)    q.opts.onSkip();    askNext(); },
      onProceed: function () { asking = false; if (q.opts.onProceed) q.opts.onProceed(); askNext(); }
    });
  }

  /** Certain: the same file. Ask, and do nothing until answered. */
  window.bvtuDupeAsk = function (dupe, opts) {
    queue.push({ dupe: dupe, opts: opts || {} });
    askNext();
  };

  function render(dupe, opts) {
    var el = bar();
    el.innerHTML =
      '<div style="font-weight:700;margin-bottom:.3rem;">⚠ ' + esc(dupe.headline) + '</div>'
      + '<div style="margin-bottom:.1rem;">It is already on <strong>' + esc(dupe.label) + '</strong>.</div>'
      + '<div style="font-size:.78rem;color:#78716c;margin-bottom:.7rem;">'
      + 'If that is the same expense, skip it. If you meant to claim it twice, you can still attach it.</div>'
      + '<div style="display:flex;gap:.5rem;flex-wrap:wrap;">'
      + btn("Skip it — already claimed", false)
      + btn('Attach it anyway', true)
      + '</div>';
    var buttons = el.querySelectorAll('button');
    buttons[0].onclick = function () { hide(); if (opts.onSkip)    opts.onSkip(); };
    buttons[1].onclick = function () { hide(); if (opts.onProceed) opts.onProceed(); };
    el.style.display = 'block';
    buttons[0].focus();
  };

  /** Likely: says so, marks the row, and lets the receipt through. */
  window.bvtuDupeTell = function (dupe, tr) {
    if (tr) markRow(tr, dupe);
    // A question still waiting for an answer owns the bar; this one only
    // informs, and the row keeps its marker either way.
    if (asking || queue.length) return;
    var el = bar();
    el.innerHTML =
      '<div style="font-weight:700;margin-bottom:.3rem;">\u{1F9FE} ' + esc(dupe.headline) + '</div>'
      + '<div style="margin-bottom:.6rem;">Compare it with <strong>' + esc(dupe.label) + '</strong>'
      + ' before you submit. It has been added either way.</div>'
      + '<div style="display:flex;gap:.5rem;"><span style="flex:1"></span>' + btn('Got it', false) + '</div>';
    el.querySelector('button').onclick = hide;
    el.style.display = 'block';
  };

  /** The marker outlasts the bar, so it is still there at submit time. */
  function markRow(tr, dupe) {
    var wrap = tr.querySelector('.receipt-cell > div') || tr.querySelector('.receipt-cell');
    if (!wrap || wrap.querySelector('.dupe-label')) return;
    var m = document.createElement('div');
    m.className = 'dupe-label';
    m.style.cssText = 'margin-top:.15rem;font-size:.68rem;color:#92400e;cursor:help;';
    m.title = 'Possible duplicate of ' + dupe.label;
    m.textContent = '⚠ possible duplicate';
    wrap.appendChild(m);
  }

  /**
   * Undo an attachment that was declined.
   *
   * The thumbnail goes up the moment a file is chosen, before the server has
   * said whether it has seen it before — which is right, because waiting a few
   * seconds for a spinner with nothing on screen reads as a failure. So saying
   * "skip it" has to put the cell back the way it was, including the paperclip,
   * or the row looks like it holds a receipt that was never stored.
   */
  var RECEIPT_FIELDS = ['rpath-', 'rorig-', 'svend-', 'sdate-', 'stot-'];

  /** What the row held before this upload, so declining can put it back. */
  window.bvtuSnapshotRowReceipt = function (rowId) {
    var snap = {};
    RECEIPT_FIELDS.forEach(function (pre) {
      var el = document.getElementById(pre + rowId);
      snap[pre] = el ? el.value : '';
    });
    var tr = document.getElementById('row-' + rowId);
    snap.billHash = tr ? (tr.dataset.billHash || '') : '';
    return snap;
  };

  window.bvtuRevertRowReceipt = function (rowId, prev) {
    prev = prev || {};
    RECEIPT_FIELDS.forEach(function (pre) {
      var el = document.getElementById(pre + rowId);
      if (el) el.value = prev[pre] || '';
    });
    var tr = document.getElementById('row-' + rowId);
    if (tr) {
      tr.dataset.billHash = prev.billHash || '';
      /* A late thumbnail from the FileReader that was started before the
       * server answered must not put the declined receipt back on screen. */
      tr.dataset.receiptDeclined = '1';
    }
    var wrap = document.getElementById('receipt-wrap-' + rowId);
    if (!wrap) return;
    var gone = wrap.querySelector('.receipt-has-file');
    if (gone) gone.remove();
    gone = wrap.querySelector('.receipt-open-btn');
    if (gone) gone.remove();
    gone = wrap.querySelector('.dupe-label');
    if (gone) gone.remove();

    /* The row had a receipt before this one was offered: put that back rather
     * than the paperclip, or declining a replacement would quietly detach a
     * receipt that is already part of the record. */
    if (prev['rpath-']) {
      if (typeof showThumb === 'function') showThumb(rowId, prev['rpath-'], null);
      return;
    }
    if (!document.getElementById('attach-btn-' + rowId)) {
      var group = wrap.querySelector('.receipt-btn-group') || wrap;
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'receipt-attach-btn';
      b.id = 'attach-btn-' + rowId;
      b.title = 'Attach file';
      b.textContent = '\u{1F4CE}';
      b.onclick = function () { triggerRowScan(rowId); };
      group.insertBefore(b, group.firstChild);
    }
  };

  /**
   * Keep the scan's fingerprint with the row so it is still there after a save
   * and reload. The vendor goes to the server as it was read; the server folds
   * it down, and the page folds it the same way for its own comparisons.
   */
  window.bvtuSetRowScanFields = function (rowId, data) {
    if (!data) return;
    var total = 0;
    ['travel_amount','meals_amount','gifts_amount','misc_amount','office_amount','phone_amount']
      .forEach(function (k) { total += parseFloat(data[k]) || 0; });
    if (total <= 0) total = parseFloat(data.total_amount) || 0;

    var set = function (pre, v) { var el = document.getElementById(pre + rowId); if (el) el.value = v; };
    set('svend-', data.vendor || '');
    set('sdate-', data.date || '');
    set('stot-',  total > 0 ? total.toFixed(2) : '');

    var tr = document.getElementById('row-' + rowId);
    if (!tr) return;
    var fold = (typeof normVendor === 'function')
      ? normVendor(data.vendor)
      : String(data.vendor || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
    tr.dataset.billVendor = fold;
    tr.dataset.billDate   = data.date || '';
    tr.dataset.billAmount = String(total);
    if (data.sha256) tr.dataset.billHash = data.sha256;
  };

  /**
   * The same file twice in one sitting, before either row has been saved — the
   * server has no record of those yet, so the page checks its own rows too.
   */
  window.bvtuDupeLocal = function (sha, exceptRowId) {
    if (!sha) return null;
    var hit = null;
    document.querySelectorAll('#expenseRows tr').forEach(function (tr) {
      if (hit || !tr.id || tr.id === 'row-' + exceptRowId) return;
      if (tr.dataset.billHash === sha) hit = tr;
    });
    if (!hit) return null;
    var desc = (hit.querySelector('[name="description[]"]') || {}).value || '';
    var date = (hit.querySelector('[name="expense_date[]"]') || {}).value || '';
    return {
      kind: 'same_file', certain: true, local: true,
      headline: 'You have already attached this exact file.',
      label: (desc || 'a row on this voucher') + (date ? ' — ' + date : '') + ', just above'
    };
  };
})();
