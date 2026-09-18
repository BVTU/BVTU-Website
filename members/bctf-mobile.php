<?php
/**
 * bctf-mobile.php — phone capture page, reached by scanning the QR.
 *
 * Token-gated rather than login-gated: the point is to photograph forms without
 * signing in on a phone. The token only permits adding photos to the batch.
 */
require_once __DIR__ . '/bctf-db.php';

$token = trim($_GET['token'] ?? '');
$row   = $token ? bctfValidateUploadToken($token) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BCTF Membership Forms — BVTU</title>
  <link rel="icon" href="../favicon.ico">
  <style>
    * { box-sizing: border-box; }
    body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
           background:#f4f6f8; color:#1f2937; padding:1.25rem; }
    h1 { font-size:1.15rem; margin:0 0 .25rem; color:#1a2e1a; }
    .sub { font-size:.85rem; color:#6b7280; margin-bottom:1.25rem; }
    .shoot { display:block; width:100%; background:#1a6b35; color:#fff; border:none;
             border-radius:12px; padding:1.1rem; font-size:1.05rem; font-weight:700;
             cursor:pointer; margin-bottom:1rem; }
    .shoot:active { background:#14532d; }
    input[type=file] { display:none; }
    .item { background:#fff; border:1px solid #e5e7eb; border-radius:10px;
            padding:.75rem .9rem; margin-bottom:.6rem; display:flex; gap:.7rem; align-items:center; }
    .item img { width:52px; height:52px; object-fit:cover; border-radius:6px; flex-shrink:0; }
    .item .nm { font-weight:700; font-size:.92rem; }
    .item .fn { font-size:.76rem; color:#6b7280; margin-top:.1rem; }
    .item.pending .nm { color:#9ca3af; }
    .warn { font-size:.76rem; color:#b45309; margin-top:.15rem; }
    .namebox { width:100%; margin-top:.4rem; border:1px solid #d1d5db; border-radius:6px;
               padding:.45rem .6rem; font-size:.9rem; font-family:inherit; }
    .done { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534;
            border-radius:10px; padding:1rem; font-size:.9rem; }
    .err  { background:#fef2f2; border:1px solid #fecaca; color:#991b1b;
            border-radius:10px; padding:1rem; font-size:.9rem; }
    .count { font-size:.82rem; color:#6b7280; margin-top:1.25rem; text-align:center; }
  </style>
</head>
<body>

<?php if (!$row): ?>
  <div class="err">
    <strong>This link has expired.</strong><br>
    Open Membership Forms on your computer and scan the QR code again.
  </div>
<?php else: ?>

  <h1>BCTF Membership Forms</h1>
  <div class="sub">Photograph each form. The surname is read automatically — check it before sending.</div>

  <button class="shoot" onclick="document.getElementById('cam').click();">
    &#x1F4F7; Take a photo
  </button>
  <input type="file" id="cam" accept="image/*" capture="environment" onchange="send(this)">

  <div id="list"></div>
  <div class="count" id="count"></div>

  <script>
  var TOKEN = <?= json_encode($token) ?>;
  var n = 0;

  function esc(s) {
    return String(s).replace(/[&<>"]/g, function(c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];
    });
  }

  function send(input) {
    var file = input.files && input.files[0];
    if (!file) return;

    var row = document.createElement('div');
    row.className = 'item pending';
    row.innerHTML = '<img src="' + URL.createObjectURL(file) + '">'
                  + '<div><div class="nm">Reading the form&hellip;</div></div>';
    document.getElementById('list').prepend(row);
    input.value = '';

    var fd = new FormData();
    fd.append('photo', file);
    fd.append('token', TOKEN);

    fetch('bctf-upload.php', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.error) {
          row.className = 'item';
          row.querySelector('.nm').textContent = d.error;
          return;
        }
        n++;
        document.getElementById('count').textContent = n + ' form' + (n === 1 ? '' : 's') + ' added';
        row.className = 'item';
        var body = row.querySelector('div:last-child');
        body.innerHTML =
          '<div class="nm">' + esc(d.last_name || 'Name needed') + '</div>'
          + '<div class="fn">' + esc(d.filename) + '</div>'
          + (d.notice ? '<div class="warn">' + esc(d.notice) + '</div>' : '')
          + '<input class="namebox" placeholder="Surname" value="' + esc(d.last_name || '') + '">';
        var box = body.querySelector('.namebox');
        box.addEventListener('change', function() {
          var fd2 = new FormData();
          fd2.append('token', TOKEN);
          fd2.append('id', d.id);
          fd2.append('last_name', box.value);
          fetch('bctf-rename.php', { method: 'POST', body: fd2 })
            .then(function(r) { return r.json(); })
            .then(function(res) {
              if (res.filename) body.querySelector('.fn').textContent = res.filename;
              body.querySelector('.nm').textContent = box.value || 'Name needed';
            });
        });
      })
      .catch(function() {
        row.className = 'item';
        row.querySelector('.nm').textContent = 'Upload failed — try again.';
      });
  }
  </script>

<?php endif; ?>
</body>
</html>
