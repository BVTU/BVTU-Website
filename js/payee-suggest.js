/**
 * payee-suggest.js — "Pay to" offers matching people; picking one fills their
 * e-transfer address.
 *
 * Used by lp-voucher-new.php and lp-voucher-edit.php. Reads people-suggest.php
 * (admin-only, the same people allowed to create a voucher; anyone else gets a
 * 403 and simply no suggestions). Typing a business name still works — the
 * list only appears when somebody on the contact list matches.
 *
 * Only the primary address comes back, and an sd54.bc.ca one is not filled in:
 * union money is not sent to a district inbox. The name is still filled and the
 * address box is left for the person to type.
 */
(function () {
  var nameBox  = document.querySelector('[name="payee_name"]');
  var emailBox = document.querySelector('[name="payee_email"]');
  if (!nameBox || !emailBox) return;

  var wrap = nameBox.parentNode;
  wrap.style.position = 'relative';
  nameBox.setAttribute('autocomplete', 'off');

  var out = document.createElement('div');
  out.className = 'payee-sug';
  out.style.cssText = 'display:none;position:absolute;left:0;right:0;top:100%;z-index:50;'
    + 'background:#fff;border:1px solid #d1d5db;border-radius:8px;margin-top:2px;'
    + 'box-shadow:0 6px 18px rgba(0,0,0,.12);overflow:hidden;';
  wrap.appendChild(out);

  var timer = null, controller = null, items = [], cursor = -1;

  function hide() { out.style.display = 'none'; items = []; cursor = -1; }

  function isDistrict(email) { return /@sd54\.bc\.ca$/i.test(String(email || '').trim()); }

  function pick(p) {
    nameBox.value = p.name;
    if (p.email && !isDistrict(p.email)) {
      emailBox.value = p.email;
    } else {
      emailBox.value = '';
      emailBox.focus();
    }
    hide();
  }

  function draw(list) {
    out.innerHTML = '';
    if (!list.length) { hide(); return; }
    list.forEach(function (p) {
      var a = document.createElement('button');
      a.type = 'button';
      a.style.cssText = 'display:block;width:100%;text-align:left;border:0;background:none;'
        + 'padding:.45rem .7rem;cursor:pointer;font:inherit;';
      var nm = document.createElement('div');
      nm.style.cssText = 'font-weight:600;font-size:.88rem;';
      nm.textContent = p.name;
      var em = document.createElement('div');
      em.style.cssText = 'font-size:.76rem;color:#6b7280;';
      em.textContent = p.email || '';
      a.appendChild(nm); a.appendChild(em);
      // mousedown, not click: click fires after the box loses focus.
      a.addEventListener('mousedown', function (e) { e.preventDefault(); pick(p); });
      a.addEventListener('mouseenter', function () { setCursor(items.indexOf(a)); });
      a._person = p;
      out.appendChild(a);
    });
    items = Array.prototype.slice.call(out.children);
    cursor = -1;
    out.style.display = 'block';
  }

  function setCursor(i) {
    items.forEach(function (el, j) { el.style.background = (j === i) ? '#f0f7ff' : 'none'; });
    cursor = i;
  }

  function look() {
    var q = nameBox.value.trim();
    if (q.length < 2) { hide(); return; }
    if (controller) controller.abort();
    controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    fetch('people-suggest.php?q=' + encodeURIComponent(q),
          controller ? { signal: controller.signal, credentials: 'same-origin' } : { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (d) {
        // Typed past it while the request was out, or the box was ticked off.
        if (nameBox.value.trim() !== q || document.activeElement !== nameBox) return;
        draw(Array.isArray(d) ? d : []);
      })
      .catch(function () { /* aborted, offline or not allowed: no list */ });
  }

  nameBox.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(look, 180);
  });

  nameBox.addEventListener('keydown', function (e) {
    if (out.style.display !== 'block' || !items.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      var i = cursor + (e.key === 'ArrowDown' ? 1 : -1);
      if (i < 0) i = items.length - 1;
      if (i >= items.length) i = 0;
      setCursor(i);
    } else if (e.key === 'Enter' && cursor >= 0) {
      e.preventDefault();          // never submit the voucher from the dropdown
      pick(items[cursor]._person);
    } else if (e.key === 'Escape') {
      hide();
    }
  });

  nameBox.addEventListener('blur', function () { setTimeout(hide, 120); });
})();
