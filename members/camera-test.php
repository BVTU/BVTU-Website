<?php
/**
 * camera-test.php — which way of opening a camera does THIS phone accept?
 *
 * Temporary. An Android phone reported that "Take a photo" on the claim form
 * did nothing at all, in both Chrome and Firefox, while the picker beside it
 * worked. The two controls are built identically apart from capture="environment",
 * so the attribute is the suspect — but that is a guess, and guessing remotely
 * has already cost one round trip.
 *
 * This page offers the same thing five ways and reports what each one actually
 * does on the device in front of you. Nothing is uploaded and nothing is saved:
 * every answer stays in the page.
 *
 * Delete it once the claim form is fixed.
 */
require_once __DIR__ . '/auth.php';
requireLogin();
$member = getMember();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Camera test — BVTU</title>
<link rel="icon" href="../favicon.ico">
<style>
  * { box-sizing:border-box; }
  body { margin:0; padding:1.25rem; background:#f4f6f8; color:#1f2937;
         font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
  h1 { font-size:1.15rem; margin:0 0 .25rem; color:#1a2e1a; }
  .sub { font-size:.85rem; color:#6b7280; margin-bottom:1rem; line-height:1.5; }
  .test { background:#fff; border:1px solid #e5e7eb; border-radius:12px;
          padding:1rem; margin-bottom:.9rem; }
  .test h2 { font-size:.9rem; margin:0 0 .2rem; color:#1a2e1a; }
  .test p { font-size:.78rem; color:#6b7280; margin:0 0 .7rem; line-height:1.45; }
  .go { display:block; width:100%; background:#1a6b35; color:#fff; border:none;
        border-radius:10px; padding:.9rem; font-size:.95rem; font-weight:700;
        font-family:inherit; cursor:pointer; }
  .go:active { background:#14532d; }
  .lbl { display:block; position:relative; width:100%; background:#1a6b35; color:#fff;
         border-radius:10px; padding:.9rem; font-size:.95rem; font-weight:700;
         text-align:center; cursor:pointer; }
  .lbl input { position:absolute; inset:0; opacity:0; }
  .hid { display:none; }
  .out { margin-top:.7rem; font-size:.8rem; padding:.6rem .7rem; border-radius:8px;
         background:#f9fafb; border:1px solid #e5e7eb; color:#6b7280;
         word-break:break-word; }
  .out.good { background:#f0fdf4; border-color:#86efac; color:#166534; font-weight:600; }
  .out.bad  { background:#fef2f2; border-color:#fecaca; color:#991b1b; font-weight:600; }
  video { width:100%; border-radius:10px; margin-top:.7rem; background:#000; display:none; }
  .env { font-size:.7rem; color:#9ca3af; line-height:1.5; word-break:break-word;
         background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:.8rem; }
  .copy { width:100%; background:#fff; color:#374151; border:1px solid #d1d5db;
          border-radius:10px; padding:.7rem; font-size:.85rem; font-weight:700;
          font-family:inherit; margin-top:.6rem; cursor:pointer; }
</style>
</head>
<body>

<h1>Camera test</h1>
<div class="sub">
  Five ways of asking for a photo. Tap each one in turn and tell me what each
  says underneath — especially any that do nothing at all.
  <strong>Nothing here is uploaded or saved.</strong>
</div>

<div class="test">
  <h2>1. Button, then camera</h2>
  <p>A plain button that opens a hidden camera input. This is what the claim
     form does, minus the label.</p>
  <button type="button" class="go" onclick="document.getElementById('f1').click()">Take a photo</button>
  <input type="file" id="f1" class="hid" accept="image/*" capture="environment" onchange="report(1,this)">
  <div class="out" id="o1">not tried yet</div>
</div>

<div class="test">
  <h2>2. Label wrapping the input</h2>
  <p>Exactly what the claim form does now, including capture.</p>
  <label class="lbl">Take a photo<input type="file" accept="image/*" capture="environment" onchange="report(2,this)"></label>
  <div class="out" id="o2">not tried yet</div>
</div>

<div class="test">
  <h2>3. capture with no value</h2>
  <p>The same, but asking for "a camera" rather than naming the back one.</p>
  <button type="button" class="go" onclick="document.getElementById('f3').click()">Take a photo</button>
  <input type="file" id="f3" class="hid" accept="image/*" capture onchange="report(3,this)">
  <div class="out" id="o3">not tried yet</div>
</div>

<div class="test">
  <h2>4. No capture at all</h2>
  <p>The plain picker. This is the one that already works — it should send you
     to your photos. It is here as the control.</p>
  <button type="button" class="go" onclick="document.getElementById('f4').click()">Choose a photo</button>
  <input type="file" id="f4" class="hid" accept="image/*" onchange="report(4,this)">
  <div class="out" id="o4">not tried yet</div>
</div>

<div class="test">
  <h2>5. Camera inside the page</h2>
  <p>Asks permission and shows the camera here, with no app in between. If the
     others do nothing and this works, this is the way to build it.</p>
  <button type="button" class="go" onclick="startCam()">Turn the camera on</button>
  <video id="vid" playsinline muted></video>
  <div class="out" id="o5">not tried yet</div>
</div>

<div class="env" id="env"></div>
<button type="button" class="copy" onclick="copyAll()">Copy all results</button>

<script>
function say(n, text, kind) {
  var el = document.getElementById('o' + n);
  el.textContent = text;
  el.className = 'out' + (kind ? ' ' + kind : '');
}

function report(n, input) {
  var f = input.files && input.files[0];
  if (!f) { say(n, 'came back with no file', 'bad'); return; }
  say(n, 'got ' + f.name + ' — ' + Math.round(f.size / 1024) + ' KB, ' + (f.type || 'no type'), 'good');
  input.value = '';   // so the same test can be run twice
}

function startCam() {
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    say(5, 'this browser has no in-page camera at all', 'bad');
    return;
  }
  say(5, 'asking for permission…');
  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(function (stream) {
      var v = document.getElementById('vid');
      v.srcObject = stream;
      v.style.display = 'block';
      v.play();
      say(5, 'the camera is on — you should see it above', 'good');
    })
    .catch(function (err) {
      say(5, 'refused: ' + (err && err.name ? err.name : 'unknown')
            + (err && err.message ? ' — ' + err.message : ''), 'bad');
    });
}

/* What the device says about itself. The user agent is the quickest way to
 * know which Android and which browser build this was. */
document.getElementById('env').textContent =
  'Browser: ' + navigator.userAgent + '\n'
  + 'Touch points: ' + (navigator.maxTouchPoints || 0) + '\n'
  + 'pointer:coarse = ' + (window.matchMedia('(pointer: coarse)').matches)
  + ', any-pointer:coarse = ' + (window.matchMedia('(any-pointer: coarse)').matches) + '\n'
  + 'capture supported: ' + ('capture' in document.createElement('input')) + '\n'
  + 'in-page camera available: ' + !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) + '\n'
  + 'secure context: ' + window.isSecureContext;

function copyAll() {
  var lines = [];
  for (var i = 1; i <= 5; i++) {
    lines.push(i + ': ' + document.getElementById('o' + i).textContent);
  }
  lines.push('');
  lines.push(document.getElementById('env').textContent);
  var text = lines.join('\n');
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text)
      .then(function () { alert('Copied. Paste it into a message.'); })
      .catch(function () { window.prompt('Copy this:', text); });
  } else {
    window.prompt('Copy this:', text);
  }
}
</script>
</body>
</html>
