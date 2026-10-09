<?php
/**
 * onedrive-mobile.php — phone capture, reached by scanning the QR.
 * Pick a folder by drilling down from the root, then photograph into it.
 */
require_once __DIR__ . '/auth.php';          // reqStr()
require_once __DIR__ . '/onedrive-db.php';

$token = reqStr('token');
$row   = $token ? odValidateUploadToken($token) : null;

// Shared links are handed to someone who cannot open the admin page, so "scan
// the QR again" is no use to them — they need to be told to ask for a new link.
$shareLabel = $row && ($row['kind'] ?? 'self') === 'share' ? trim((string)$row['label']) : '';
// From the database's seconds-remaining, the same clock that let this page open.
$shareUntil = $row && isset($row['secs_left']) && $row['secs_left'] !== null
            ? time() + (int)$row['secs_left'] : 0;

// Browsing and creating are not the same permission — see odTokenMayCreate().
// The server decides this again on the POST; this only keeps the button off a
// screen where pressing it could not work.
$mayCreate = odTokenMayCreate($row);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload to OneDrive — BVTU</title>
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
    .newfolder { display:flex; align-items:center; gap:.6rem; background:#fff;
                 border:1px dashed #bbf7d0; border-radius:10px; padding:.8rem .9rem;
                 margin-bottom:.45rem; cursor:pointer; color:#166534; font-weight:600;
                 font-size:.92rem; width:100%; text-align:left; font-family:inherit; }
    .newfolder:active { background:#f0fdf4; }
    .nfform { background:#fff; border:1px solid #bbf7d0; border-radius:10px;
              padding:.8rem .9rem; margin-bottom:.45rem; }
    .nfform input { width:100%; border:1px solid #d1d5db; border-radius:8px;
                    padding:.6rem .7rem; font-size:.95rem; font-family:inherit; }
    .nfform .row { display:flex; gap:.5rem; margin-top:.6rem; }
    .nfform button { flex:1; border-radius:8px; padding:.6rem; font-size:.88rem;
                     font-weight:700; font-family:inherit; cursor:pointer; }
    .nfform .go { background:#1a6b35; color:#fff; border:none; }
    .nfform .no { background:#fff; color:#374151; border:1px solid #d1d5db; }
    .nferr { color:#991b1b; font-size:.82rem; margin-top:.5rem; }
    .here { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px;
            padding:.8rem .9rem; margin:.75rem 0; font-size:.86rem; color:#166534; }
    .shoot { display:block; width:100%; background:#1a6b35; color:#fff; border:none;
             border-radius:12px; padding:1.1rem; font-size:1.05rem; font-weight:700;
             cursor:pointer; margin:.5rem 0 1rem; }
    .shoot:active { background:#14532d; }
    .shoot[disabled] { background:#9ca3af; }
    .pick { display:block; width:100%; background:#f0fdf4; color:#1a6b35;
            border:2px solid #86efac; border-radius:12px; padding:.9rem;
            font-size:.95rem; font-weight:700; font-family:inherit;
            cursor:pointer; margin:0 0 1rem; }
    .pick:active { background:#dcfce7; }
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
  <div class="err"><strong>This link no longer works.</strong><br>
    It may have run out, or been turned off. If someone shared it with you, ask
    them for a new one. If it is your own, open OneDrive Doc Upload on your
    computer and scan the QR code again.</div>
<?php else: ?>

  <h1>Upload to OneDrive</h1>
  <div class="sub">Choose a folder, then photograph a document into it.</div>
  <?php if ($shareLabel !== ''): ?>
    <div class="sub" style="background:#eef6f0;border:1px solid #c7e3d1;border-radius:8px;
                            padding:.55rem .7rem;margin:-.5rem 0 1rem;color:#14532d;">
      Shared with <strong><?= htmlspecialchars($shareLabel) ?></strong><?php
        if ($shareUntil): ?> &middot; works until
        <strong><?= date('g:ia \o\n D j M', $shareUntil) ?></strong><?php endif; ?>.
      Uploads are recorded under that name.
    </div>
  <?php endif; ?>

  <div class="crumbs" id="crumbs"></div>
  <div id="newfolder"></div>
  <div id="folders"></div>

  <div class="here" id="here"></div>

  <input class="cap" id="caption" placeholder="Name for the file (optional)">
  <button class="shoot" onclick="document.getElementById('cam').click();">&#x1F4F7; Take a photo</button>
  <button class="pick" onclick="document.getElementById('saved').click();">&#x1F5BC;&#xFE0F; Choose a saved photo</button>
  <!--
    Two inputs, not one.

    A single accept="image/*" input is a sheet with "Take Photo" on iOS, but on
    Android it hands you straight to the photo picker with no way to reach the
    camera. capture="environment" fixes that and breaks the other half: it
    forces the camera and removes the ability to pick a photo already taken.
    So the choice is made with two buttons instead of left to the browser.
  -->
  <input type="file" id="cam" accept="image/*" capture="environment" onchange="send(this)">
  <input type="file" id="saved" accept="image/*" onchange="send(this)">

  <div id="log"></div>

  <script src="../js/camera-fallback.js?v=<?= @filemtime(__DIR__ . '/../js/camera-fallback.js') ?>"></script>
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
      '&#x2713; Uploads will go to <strong>' + esc(path()) + '</strong>';

    newFolderButton();   // its label names the folder you are standing in

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

  /* ── Making a folder ──────────────────────────────────────────────────────
   *
   * Without this the only way to file into a folder that does not exist yet is
   * to stop, find a computer, make it in OneDrive, come back and reload.
   *
   * A new folder is stepped into straight away: you asked for it in order to
   * put something in it, and leaving you on the level above to find and tap it
   * is a step that exists only because it was easier to write.
   */
  var MAY_CREATE = <?= json_encode($mayCreate) ?>;

  function newFolderButton() {
    var box = document.getElementById('newfolder');
    if (!MAY_CREATE) { box.innerHTML = ''; return; }
    box.innerHTML = '';
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'newfolder';
    b.innerHTML = '<span class="ic">&#x2795;</span><span>New folder in '
                + esc(here().name) + '</span>';
    b.onclick = newFolderForm;
    box.appendChild(b);
  }

  function newFolderForm() {
    var box = document.getElementById('newfolder');
    box.innerHTML =
      '<div class="nfform">'
      + '<input id="nfname" placeholder="Folder name" autocomplete="off" '
      +   'autocapitalize="words" enterkeyhint="done">'
      + '<div class="nferr" id="nferr" style="display:none;"></div>'
      + '<div class="row">'
      +   '<button type="button" class="no" id="nfcancel">Cancel</button>'
      +   '<button type="button" class="go" id="nfgo">Create</button>'
      + '</div></div>';
    var input = document.getElementById('nfname');
    document.getElementById('nfcancel').onclick = newFolderButton;
    document.getElementById('nfgo').onclick     = createFolder;
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); createFolder(); }
    });
    input.focus();
  }

  function nfError(msg, existing) {
    var el = document.getElementById('nferr');
    if (!el) return;
    el.textContent = msg;
    el.style.display = 'block';
    // The folder is already there, so offer the thing that was wanted.
    if (existing && existing.id) {
      var a = document.createElement('div');
      a.style.cssText = 'margin-top:.4rem;';
      var open = document.createElement('button');
      open.type = 'button';
      open.className = 'go';
      open.style.cssText = 'border-radius:8px;padding:.5rem .9rem;font-size:.85rem;'
                         + 'font-weight:700;border:none;background:#1a6b35;color:#fff;';
      open.textContent = 'Open ' + existing.name;
      open.onclick = function () {
        stack.push({ id: existing.id, name: existing.name });
        load();
      };
      a.appendChild(open);
      el.appendChild(a);
    }
  }

  function createFolder() {
    var input = document.getElementById('nfname');
    var go    = document.getElementById('nfgo');
    if (!input) return;
    var name = input.value.trim();
    if (!name) { nfError('Give the folder a name.'); return; }

    go.disabled = true;
    go.textContent = 'Creating\u2026';
    var fd = new FormData();
    fd.append('action', 'create');
    fd.append('token', TOKEN);
    fd.append('parent', here().id);
    fd.append('name', name);

    fetch('onedrive-folders.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) {
          go.disabled = false;
          go.textContent = 'Create';
          nfError((d && (d.error || d.message)) || 'That did not work. Try again.',
                  d && d.existing);
          return;
        }
        /* The form goes now, not when the listing comes back.
         *
         * Re-enabling the button first left a form that looked untouched for
         * as long as the next request took, and a second tap on a slow signal
         * posted the same name again — this time into the folder just made,
         * giving Receipts/Receipts. */
        document.getElementById('newfolder').innerHTML = '';
        stack.push({ id: d.folder.id, name: d.folder.name });
        load();
      })
      .catch(function () {
        go.disabled = false;
        go.textContent = 'Create';
        nfError('No answer from the server. Check your signal and try again.');
      });
  }

  function load() {
    document.getElementById('folders').innerHTML =
      '<div style="font-size:.82rem;color:#9ca3af;">Loading&hellip;</div>';
    fetch('onedrive-folders.php?token=' + encodeURIComponent(TOKEN) + '&id=' + encodeURIComponent(here().id))
      .then(function(r){return r.json();})
      .then(function(d){
        if (d.error) {
          document.getElementById('folders').innerHTML =
            '<div class="err">' + esc(d.error) + '</div>';
          // Its label names a folder we failed to open; leaving it would
          // invite making a folder somewhere other than it says.
          document.getElementById('newfolder').innerHTML = '';
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
