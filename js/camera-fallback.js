/*
 * camera-fallback.js — when the camera never opens, say so.
 *
 * A phone reported "Take a photo" doing nothing at all, in Chrome and in
 * Firefox both. The diagnosis was the device: every capture= control did
 * nothing, the plain picker worked, and an in-page camera came back
 * NotAllowedError. The camera is blocked for the browser, which no page can
 * change — but a button that silently does nothing is still this page's fault.
 *
 * So: watch any capture= input that is tapped. If the page never goes to the
 * background and no file ever arrives, nothing opened, and the person is told
 * what to do instead rather than tapping a dead button three more times.
 *
 * The signal is coarse on purpose. Opening a camera app backgrounds the page —
 * that is true whether a photo is taken or the person backs out — so the only
 * case that trips this is the one where nothing happened at all.
 */
(function () {
  'use strict';

  /* Long enough that a camera app which is merely slow to come up does not get
   * called broken. Someone tapping a button that does nothing will wait this
   * out; someone whose camera opens never sees it. */
  var WAIT_MS = 4000;
  var BAR_ID  = 'bvtuCameraBar';

  /*
   * Android only, and this is the one place sniffing earns its keep.
   *
   * The failure is an Android one: Chrome asks the system for a camera, the
   * system does not answer, and nothing is said. The test for it is "the page
   * never went to the background", which works there because launching a
   * camera app backgrounds the page.
   *
   * On iOS the camera is a sheet over the page, and the page is not reliably
   * hidden or blurred while it is up — so opening the camera and backing out
   * looks exactly like a camera that never opened. Running this there would
   * tell people with a perfectly good camera that it is switched off, on the
   * platform that already works. A missing warning is a smaller wrong than a
   * false one.
   */
  var ANDROID = /Android/i.test(navigator.userAgent || '');

  function hide() {
    var el = document.getElementById(BAR_ID);
    if (el) el.style.display = 'none';
  }

  function show() {
    var el = document.getElementById(BAR_ID);
    if (!el) {
      el = document.createElement('div');
      el.id = BAR_ID;
      el.setAttribute('role', 'alert');
      el.style.cssText = [
        'position:fixed', 'left:50%', 'transform:translateX(-50%)',
        'bottom:1rem', 'z-index:9999',
        'box-sizing:border-box', 'width:calc(100vw - 1.5rem)', 'max-width:32rem',
        'background:#fffbeb', 'border:1px solid #f59e0b', 'border-radius:.6rem',
        'box-shadow:0 10px 30px rgba(0,0,0,.18)', 'padding:.85rem 1rem',
        'font-size:.86rem', 'line-height:1.5', 'color:#422006'
      ].join(';');
      el.innerHTML =
        '<div style="font-weight:700;margin-bottom:.3rem;">Your phone did not open the camera.</div>'
        + '<div style="margin-bottom:.7rem;">It is usually switched off for this browser in the '
        + 'phone\'s own settings, and a web page cannot turn it back on. Take the photo with your '
        + 'camera app, then use <strong>Choose a saved photo</strong> here.</div>'
        + '<div style="display:flex;"><span style="flex:1"></span>'
        + '<button type="button" style="border-radius:.4rem;padding:.4rem .85rem;font-size:.82rem;'
        + 'font-weight:600;cursor:pointer;background:#fff;color:#422006;border:1px solid #d6d3d1;'
        + 'font-family:inherit;">Got it</button></div>';
      el.querySelector('button').onclick = hide;
      document.body.appendChild(el);
    }
    el.style.display = 'block';
  }

  function watch(input) {
    var answered = false, backgrounded = false;

    function onChange() { answered = true; hide(); }
    function onHidden() { if (document.hidden) backgrounded = true; }
    function onBlur()   { backgrounded = true; }

    input.addEventListener('change', onChange, { once: true });
    document.addEventListener('visibilitychange', onHidden);
    window.addEventListener('blur', onBlur);

    setTimeout(function () {
      document.removeEventListener('visibilitychange', onHidden);
      window.removeEventListener('blur', onBlur);
      // A camera that opened — and a camera that opened and was backed out of —
      // both put this page in the background. Neither is a failure.
      if (!answered && !backgrounded) show();
    }, WAIT_MS);
  }

  /*
   * One delegated listener rather than a hook on every control: these inputs
   * are built in six different places, three of them from JavaScript, and a
   * hook that has to be remembered at each of them will be missed at one.
   * Capture phase, so it still runs if something stops propagation.
   */
  document.addEventListener('click', function (e) {
    if (!ANDROID) return;
    var t = e.target;
    if (!t || t.tagName !== 'INPUT' || t.type !== 'file') return;
    if (!t.hasAttribute('capture')) return;
    watch(t);
  }, true);

  /*
   * Any file arriving at all takes the notice away.
   *
   * It tells people to use "Choose a saved photo" instead, and that control is
   * not a capture one, so without this the notice sat there through the upload
   * and over the top of the screen saying it had worked — still insisting the
   * camera had not opened, after the person had already worked around it.
   */
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (t && t.tagName === 'INPUT' && t.type === 'file') hide();
  }, true);
})();
