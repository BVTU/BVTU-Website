<?php
/**
 * onedrive-mobile.php — phone capture, reached by scanning the QR.
 * Pick a folder by drilling down from the root, then photograph into it.
 */
require_once __DIR__ . '/onedrive-db.php';

$token = trim($_GET['token'] ?? '');
$row   = $token ? odValidateUploadToken($token) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Photo to OneDrive — BVTU</title>
  <link rel="icon" href="../favicon.ico">
  <style>
    * { box-sizing:border-box; }
    body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
           background:#f4f6f8; color:#1f2937; padding:1.25rem; }
    h1 { font-size:1.15rem; margin:0 0 .25rem; color:#1a2e1a; }
    .sub { font-size:.85rem; color:#6b7280; margin-bottom:1rem; }
    .crumbs { font-size:.8rem; color:#6b7280; margin-bottom:.5rem; word-break:break-word; }
    .crumbs a { color:#1a6b35; font-weight:700; text-decoration:none; }
    .folder { display:flex; align-items:center; gap:.6rem; background:#fff; border:1px solid #e5e7eb;
              border-radius:10px; padding:.8rem .9rem; margin-bottom:.45rem; cursor:pointer; }
    .folder:active { background:#f0fdf4; }
    .folder .ic { font-size:1.1rem; }
    .folder .nm { font-weight:600; font-size:.92rem; flex:1; }
    .folder .ct { font-size:.75rem; color:#9ca3af; }
    .here { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px;
            padding:.8rem .9rem; margin:.75rem 0; font-size:.86rem; color:#166534; }
    .shoot { display:block; width:100%; background:#1a6b35; color:#fff; border:none;
             border-radius:12px; padding:1.1rem; font-size:1.05rem; font-weight:700;
             cursor:pointer; margin:.5rem 0 1rem; }
    .shoot:active { background:#14532d; }
    .shoot[disabled] { background:#9ca3af; }
    input[type=file] { display:none; }
    .cap { width:100%; border:1px solid #d1d5db; border-radius:8px; padding:.6rem .7rem;
           font-size:.92rem; font-family:inherit; margin-bottom:.6rem; }
    .item { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:.7rem .85rem;
            margin-bottom:.5rem; font-size:.85rem; display:flex; gap:.6rem; align-items:center; }
    .item img { width:44px;height:44px;object-fit:cover;border-radius:6px; }
    .item .ok { color:#166534; font-weight:700; }
    .item .bad { color:#991b1b; font-weight:700; }
    .err { background:#fef2f2;border:1px solid #fecaca;color:#991b1b;
           border-radius:10px;padding:1rem;font-size:.9rem; }
  </style>
</head>
<body>

<?php if (!$row): ?>
  <div class="err"><strong>This link has expired.</strong><br>
    Open OneDrive Photos on your computer and scan the QR code again.</div>
<?php else: ?>

  <h1>Photo to OneDrive</h1>
  <div class="sub">Choose a folder, then photograph into it.</div>

  <div class="crumbs" id="crumbs"></div>
  <div id="folders"></div>

  <div class="here" id="here"></div>

  <input class="cap" id="caption" placeholder="Name for the photo (optional)">
  <button class="shoot" onclick="document.getElementById('cam').click();">&#x1F4F7; Take a photo</button>
  <input type="file" id="cam" accept="image/*" capture="environment" onchange="send(this)">

  <div id="log"></div>

  <script>
  var TOKEN = <?= json_encode($token) ?>;
  var stack = [{ id: 'root', name: 'OneDrive' }];

  function esc(s){return String(s).replace(/[&<>"]/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

  function here(){ return stack[stack.length-1]; }
  function path(){ return stack.map(function(s){return s.name;}).join(' / '); }

  function render(folders) {
    document.getElementById('crumbs').innerHTML =
      stack.map(function(s,i){
        return i === stack.length-1
          ? '<strong>' + esc(s.name) + '</strong>'
          : '<a href="#" onclick="up(' + i + ');return false;">' + esc(s.name) + '</a>';
      }).join(' / ');

    document.getElementById('here').innerHTML =
      '&#x2713; Photos will go to <strong>' + esc(path()) + '</strong>';

    var box = document.getElementById('folders');
    box.innerHTML = '';
    if (!folders.length) {
      box.innerHTML = '<div style="font-size:.82rem;color:#9ca3af;padding:.3rem 0 .6rem;">No sub-folders here.</div>';
      return;
    }
    folders.forEach(function(f) {
      var d = document.createElement('div');
      d.className = 'folder';
      d.innerHTML = '<span class="ic">&#x1F4C1;</span><span class="nm">' + esc(f.name) + '</span>'
                  + '<span class="ct">' + (f.count || 0) + '</span>';
      d.onclick = function(){ stack.push({id:f.id, name:f.name}); load(); };
      box.appendChild(d);
    });
  }

  function up(i){ stack = stack.slice(0, i+1); load(); }

  function load() {
    document.getElementById('folders').innerHTML =
      '<div style="font-size:.82rem;color:#9ca3af;">Loading&hellip;</div>';
    fetch('onedrive-folders.php?token=' + encodeURIComponent(TOKEN) + '&id=' + encodeURIComponent(here().id))
      .then(function(r){return r.json();})
      .then(function(d){
        if (d.error) {
          document.getElementById('folders').innerHTML =
            '<div class="err">' + esc(d.error) + '</div>';
          return;
        }
        render(d.folders || []);
      });
  }

  function send(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    var dest = here(), destPath = path();
    var cap  = document.getElementById('caption').value;

    var row = document.createElement('div');
    row.className = 'item';
    row.innerHTML = '<img src="' + URL.createObjectURL(file) + '"><div>Uploading&hellip;</div>';
    document.getElementById('log').prepend(row);
    input.value = '';

    var fd = new FormData();
    fd.append('photo', file);
    fd.append('token', TOKEN);
    fd.append('folder_id', dest.id);
    fd.append('folder_path', destPath);
    fd.append('label', cap);

    fetch('onedrive-push.php', { method:'POST', body: fd })
      .then(function(r){return r.json();})
      .then(function(d){
        row.querySelector('div').innerHTML = d.error
          ? '<span class="bad">' + esc(d.error) + '</span>'
          : '<span class="ok">&#x2713; ' + esc(d.name) + '</span><br>'
            + '<span style="color:#6b7280;font-size:.78rem;">' + esc(d.folder) + '</span>';
        document.getElementById('caption').value = '';
      })
      .catch(function(){
        row.querySelector('div').innerHTML = '<span class="bad">Upload failed — try again.</span>';
      });
  }

  load();
  </script>

<?php endif; ?>
</body>
</html>
