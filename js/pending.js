/**
 * pending.js — say that something is happening.
 *
 * Several things this site does take a few seconds: sending a notification,
 * reading the whole Mailchimp audience, pushing a contact. The page sat there
 * looking untouched while they ran, and the only sign was the browser's own
 * tab spinner — which is the easiest thing on the screen to miss. People
 * pressed the button again, which on a signing or a payment form is not a
 * harmless thing to do.
 *
 * So: the button that was pressed says what it is doing, and the form refuses
 * a second press until the page navigates away.
 *
 * Two rules worth keeping if this is ever edited:
 *
 *   1. The button is NEVER disabled. A disabled submit button is left out of
 *      the POST, and half the forms here carry their action in the button's
 *      own name and value — `<button name="action" value="sync">`. Disabling
 *      it would send the form without knowing what it was for.
 *   2. Nothing happens for the first fraction of a second, so an ordinary
 *      fast post does not flash a spinner on its way out.
 */
(function () {
  'use strict';

  var DELAY = 180;          // ms before saying anything at all
  var DEFAULT_WORD = 'Working…';

  function wordFor(btn, form) {
    return (btn && btn.getAttribute('data-busy'))
        || form.getAttribute('data-busy')
        || DEFAULT_WORD;
  }

  function markBusy(form, btn) {
    form.classList.add('is-pending');
    form.setAttribute('aria-busy', 'true');
    if (!btn) return;
    // Kept so going back to this page can put the label right again.
    if (btn.dataset.pendingLabel === undefined) {
      btn.dataset.pendingLabel = btn.innerHTML;
    }
    var word = wordFor(btn, form);
    btn.classList.add('is-busy');
    btn.innerHTML = '<span class="pending-spin" aria-hidden="true"></span>'
                  + '<span>' + word.replace(/</g, '&lt;') + '</span>';
  }

  function clearBusy(form) {
    form.classList.remove('is-pending');
    form.removeAttribute('aria-busy');
    delete form.dataset.pending;
    [].slice.call(form.querySelectorAll('.is-busy')).forEach(function (btn) {
      if (btn.dataset.pendingLabel !== undefined) btn.innerHTML = btn.dataset.pendingLabel;
      btn.classList.remove('is-busy');
    });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || form.nodeName !== 'FORM') return;
    // Somebody else is handling this one — an AJAX form, or a confirm() the
    // reader just cancelled. Either way the page is not going anywhere.
    if (e.defaultPrevented) return;
    if (form.hasAttribute('data-nopending')) return;

    if (form.dataset.pending === '1') { e.preventDefault(); return; }
    form.dataset.pending = '1';

    // `type` is optional on a <button> in a form — it defaults to submit, and
    // plenty of buttons here are written that way. e.submitter gets it right
    // on its own; the selector is only the fallback, so it must know too.
    var btn = e.submitter
           || form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');

    window.setTimeout(function () {
      // Still here, so the request really is taking a moment.
      if (form.dataset.pending === '1') markBusy(form, btn);
    }, DELAY);
  });

  /* Coming back with the back button hands you the page exactly as it was —
   * including a button still saying "Sending…" for a send that finished long
   * ago, on a form that now refuses to submit.
   *
   * This looks for the flag, not the spinner. A post that got away inside the
   * DELAY above is flagged but was never marked up, and matching on the class
   * would walk straight past it and leave that form refusing every submit for
   * as long as the page stayed open. Clearing the flag also defuses the
   * pending timer, which checks it before marking anything busy. */
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    [].slice.call(document.querySelectorAll('form[data-pending]')).forEach(clearBusy);
  });
})();
