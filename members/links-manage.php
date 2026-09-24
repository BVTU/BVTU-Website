<?php
/**
 * links-manage.php — Local President: manage bvtu.ca/go/ short links and
 *                    make QR codes from them (or from any URL or text).
 */
require_once 'auth.php';
require_once 'db.php';
require_once 'exec-db.php';
require_once 'links-db.php';

requireLogin();
$member = getMember();

if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

linksEnsureTable();

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $slug  = strtolower(trim($_POST['slug']        ?? ''));
        $dest  = trim($_POST['destination'] ?? '');
        $label = trim($_POST['label']       ?? '');

        if (!$slug || !$dest) {
            $error = 'Slug and destination URL are required.';
        } elseif (!linksValidateSlug($slug)) {
            $error = 'Slug may only contain lowercase letters, numbers, and hyphens.';
        } elseif (!filter_var($dest, FILTER_VALIDATE_URL)) {
            $error = 'Please enter a valid destination URL (include https://).';
        } else {
            try {
                linksCreate($slug, $dest, $label ?: $slug, $member['email']);
                $notice = 'Short link created: bvtu.ca/go/' . htmlspecialchars($slug);
            } catch (\PDOException $e) {
                $error = 'That slug is already in use. Choose a different one.';
            }
        }
    }

    if ($action === 'update') {
        $id    = (int)($_POST['link_id']     ?? 0);
        $slug  = strtolower(trim($_POST['slug']        ?? ''));
        $dest  = trim($_POST['destination'] ?? '');
        $label = trim($_POST['label']       ?? '');

        if (!$id || !$slug || !$dest) {
            $error = 'All fields are required.';
        } elseif (!linksValidateSlug($slug)) {
            $error = 'Slug may only contain lowercase letters, numbers, and hyphens.';
        } elseif (!filter_var($dest, FILTER_VALIDATE_URL)) {
            $error = 'Please enter a valid destination URL (include https://).';
        } else {
            try {
                linksUpdate($id, $slug, $dest, $label ?: $slug);
                $notice = 'Link updated.';
            } catch (\PDOException $e) {
                $error = 'That slug is already in use. Choose a different one.';
            }
        }
    }

    if ($action === 'toggle') {
        $id     = (int)($_POST['link_id']    ?? 0);
        $active = (int)($_POST['set_active'] ?? 1);
        if ($id) { linksSetActive($id, $active); $notice = $active ? 'Link activated.' : 'Link deactivated.'; }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['link_id'] ?? 0);
        if ($id) { linksDelete($id); $notice = 'Link deleted.'; }
    }

    header('Location: links-manage.php' . ($notice ? '?notice=' . urlencode($notice) : ($error ? '?error=' . urlencode($error) : '')));
    exit;
}

$notice = $notice ?: htmlspecialchars($_GET['notice'] ?? '');
$error  = $error  ?: htmlspecialchars($_GET['error']  ?? '');
$links  = linksGetAll();
$totalClicks = array_sum(array_column($links, 'click_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Link Shortener &amp; QR Codes — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between;
                   margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice    { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                 padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
                 padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
                color: var(--gray-400); margin: 2rem 0 .75rem; }
    .pcard { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
            padding: 1.5rem; margin-bottom: 1.75rem; }
    .pcard h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1rem; }
    .field { margin-bottom: .85rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase;
                   letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px;
                   padding: .5rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field-hint { font-size: .74rem; color: var(--gray-400); margin-top: .25rem; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .85rem; }
    @media(max-width:640px) { .field-row { grid-template-columns: 1fr; } }

    /* Stat row */
    .stat-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .stat { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
            padding: .75rem 1.1rem; flex: 1; min-width: 90px; text-align: center; }
    .stat .n { font-size: 1.8rem; font-weight: 800; color: var(--gray-800); line-height: 1; }
    .stat .l { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
               color: var(--gray-400); margin-top: .2rem; }

    /* Table */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    thead tr { background: #1a2e1a; }
    th { padding: .6rem .85rem; text-align: left; font-size: .71rem; font-weight: 700;
         text-transform: uppercase; letter-spacing: .05em; color: #fff; white-space: nowrap; }
    td { padding: .6rem .85rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr.inactive-row td { opacity: .5; }

    .slug-cell { font-family: monospace; font-size: .85rem; font-weight: 700; color: var(--primary); }
    .slug-cell a { color: inherit; text-decoration: none; }
    .slug-cell a:hover { text-decoration: underline; }
    .dest-cell { font-size: .78rem; color: var(--gray-500); max-width: 260px;
                 overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .click-cell { font-weight: 700; color: var(--gray-700); text-align: right; }

    .act-btn { background: none; border: 1px solid var(--gray-200); border-radius: 6px;
               padding: .25rem .55rem; font-size: .75rem; cursor: pointer; color: var(--gray-600); white-space: nowrap; }
    .act-btn:hover { background: var(--accent); border-color: var(--primary); color: var(--primary); }
    .act-btn.danger:hover { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
    .acts { display: flex; gap: .35rem; flex-wrap: wrap; }

    /* Inline edit */
    .edit-row { display: none; background: #f8fafc; }
    .edit-row.open { display: table-row; }
    .edit-row td { padding: .75rem .85rem; border-bottom: 1px solid var(--gray-100); }
    .edit-inner { display: flex; gap: .6rem; align-items: flex-end; flex-wrap: wrap; }
    .edit-inner .ef { display: flex; flex-direction: column; gap: .2rem; }
    .edit-inner .ef label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-400); }
    .edit-inner input { border: 1px solid var(--gray-300); border-radius: 6px; padding: .38rem .55rem; font-size: .86rem; font-family: inherit; }
    .edit-inner input:focus { outline: none; border-color: var(--primary); }
    .slug-prefix { font-size: .82rem; color: var(--gray-400); align-self: center; padding-bottom: .1rem; }

    .empty-row td { text-align: center; color: var(--gray-400); padding: 2.5rem; }
    .badge-inactive { display:inline-block;background:#fee2e2;color:#991b1b;font-size:.68rem;font-weight:700;border-radius:100px;padding:.1rem .45rem;margin-left:.3rem; }

    /* QR code maker */
    .qr-grid { display: grid; grid-template-columns: 1fr 260px; gap: 1.5rem; align-items: start; }
    @media(max-width:720px) { .qr-grid { grid-template-columns: 1fr; } }
    .qr-preview { border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem;
                  background: #fff; text-align: center; }
    .qr-preview canvas { width: 100%; max-width: 220px; height: auto; image-rendering: pixelated; }
    .qr-empty { color: var(--gray-400); font-size: .82rem; padding: 3.2rem 0; }
    .qr-err   { color: #991b1b; font-size: .82rem; padding: 3.2rem .5rem; }
    .qr-dl    { display: flex; gap: .4rem; justify-content: center; margin-top: .85rem; flex-wrap: wrap; }
    /* display:flex outranks the hidden attribute's UA rule, so state it here. */
    .qr-dl[hidden] { display: none; }
    .qr-note  { font-size: .74rem; color: var(--gray-400); margin-top: .6rem; line-height: 1.5; }
    .field select { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px;
                    padding: .5rem .75rem; font-size: .9rem; font-family: inherit;
                    box-sizing: border-box; background: #fff; }
    .field textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px;
                      padding: .5rem .75rem; font-size: .9rem; font-family: inherit;
                      box-sizing: border-box; resize: vertical; min-height: 74px; }
    .field textarea:focus, .field select:focus { outline: none; border-color: var(--primary);
                      box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Link Shortener &amp; QR Codes</h1>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <!-- Stats -->
  <div class="stat-row">
    <div class="stat"><div class="n"><?= count($links) ?></div><div class="l">Total links</div></div>
    <div class="stat"><div class="n"><?= count(array_filter($links, fn($l) => $l['active'])) ?></div><div class="l">Active</div></div>
    <div class="stat"><div class="n"><?= number_format($totalClicks) ?></div><div class="l">Total clicks</div></div>
  </div>

  <!-- Create -->
  <div class="sec-head">New Short Link</div>
  <div class="pcard">
    <h2>Create a link</h2>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="create">
      <div class="field-row">
        <div class="field">
          <label>Slug *</label>
          <input type="text" name="slug" required placeholder="e.g. facebook"
                 pattern="[a-z0-9\-]+" title="Lowercase letters, numbers, and hyphens only">
          <div class="field-hint">bvtu.ca/go/<strong>slug</strong> — lowercase, hyphens OK</div>
        </div>
        <div class="field" style="grid-column: span 2;">
          <label>Destination URL *</label>
          <input type="url" name="destination" required placeholder="https://...">
          <div class="field-hint">Where this link redirects to. Can be any URL — internal or external.</div>
        </div>
      </div>
      <div class="field" style="max-width: 360px;">
        <label>Label <span style="font-weight:400;color:var(--gray-400);">(optional)</span></label>
        <input type="text" name="label" placeholder="e.g. BVTU Facebook Group">
        <div class="field-hint">A friendly name shown in this list — not visible to visitors.</div>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;">
        Create Link
      </button>
    </form>
  </div>

  <!-- QR code maker -->
  <div class="sec-head">QR Code</div>
  <div class="pcard">
    <h2>Make a QR code</h2>
    <div class="qr-grid">
      <div>
        <div class="field">
          <label for="qr-text">Text or URL</label>
          <textarea id="qr-text" placeholder="https://bvtu.ca/go/..."></textarea>
          <div class="field-hint">Any link or plain text. Use <strong>QR</strong> on a row below to
            load that short link &mdash; a short link is the better thing to encode, because you can
            repoint it later without reprinting the code.</div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="qr-size">Image size</label>
            <select id="qr-size">
              <option value="512">Medium &mdash; 512px (web, email)</option>
              <option value="1024" selected>Large &mdash; 1024px (print, posters)</option>
              <option value="2048">Extra large &mdash; 2048px</option>
            </select>
          </div>
          <div class="field">
            <label for="qr-ec">Error correction</label>
            <select id="qr-ec">
              <option value="M" selected>Standard</option>
              <option value="H">High &mdash; survives smudges</option>
            </select>
            <div class="field-hint">Higher correction still scans when the code is damaged or covered.</div>
          </div>
          <div class="field">
            <label for="qr-margin">Quiet zone</label>
            <select id="qr-margin">
              <option value="4" selected>Normal (recommended)</option>
              <option value="2">Tight</option>
            </select>
            <div class="field-hint">The white border scanners need. Do not crop it off.</div>
          </div>
        </div>
      </div>

      <div class="qr-preview">
        <div id="qr-out"><div class="qr-empty">Enter a link to see its QR code.</div></div>
        <div class="qr-dl" id="qr-actions" hidden>
          <button type="button" class="act-btn" onclick="qrDownload('png')">&#x2193; PNG</button>
          <button type="button" class="act-btn" onclick="qrDownload('svg')">&#x2193; SVG</button>
        </div>
        <div class="qr-note">SVG stays sharp at any size &mdash; use it for print.</div>
      </div>
    </div>
  </div>

  <!-- Link list -->
  <div class="sec-head">All Links (<?= count($links) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Short Link</th>
          <th>Label</th>
          <th>Destination</th>
          <th style="text-align:right;">Clicks</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$links): ?>
        <tr class="empty-row"><td colspan="6">No short links yet. Create one above.</td></tr>
        <?php endif; ?>
        <?php foreach ($links as $lnk): ?>
        <tr id="row-<?= $lnk['id'] ?>" class="<?= !$lnk['active'] ? 'inactive-row' : '' ?>">
          <td class="slug-cell">
            <a href="https://bvtu.ca/go/<?= htmlspecialchars($lnk['slug']) ?>" target="_blank">
              bvtu.ca/go/<?= htmlspecialchars($lnk['slug']) ?>
            </a>
            <?php if (!$lnk['active']): ?><span class="badge-inactive">Off</span><?php endif; ?>
          </td>
          <td style="font-size:.82rem;"><?= htmlspecialchars($lnk['label']) ?></td>
          <td class="dest-cell" title="<?= htmlspecialchars($lnk['destination']) ?>">
            <?= htmlspecialchars($lnk['destination']) ?>
          </td>
          <td class="click-cell"><?= number_format($lnk['click_count']) ?></td>
          <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;">
            <?= date('M j, Y', strtotime($lnk['created_at'])) ?>
          </td>
          <td>
            <div class="acts">
              <button class="act-btn" onclick="qrFor('<?= htmlspecialchars($lnk['slug'], ENT_QUOTES) ?>')">&#9632; QR</button>
              <button class="act-btn" onclick="toggleEdit(<?= $lnk['id'] ?>)">✏ Edit</button>
              <?php if ($lnk['active']): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"    value="toggle">
                <input type="hidden" name="link_id"   value="<?= $lnk['id'] ?>">
                <input type="hidden" name="set_active" value="0">
                <button type="submit" class="act-btn">⊘ Disable</button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"    value="toggle">
                <input type="hidden" name="link_id"   value="<?= $lnk['id'] ?>">
                <input type="hidden" name="set_active" value="1">
                <button type="submit" class="act-btn">&#x21BA; Enable</button>
              </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Delete bvtu.ca/go/<?= htmlspecialchars(addslashes($lnk['slug'])) ?>? This cannot be undone.')">
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="link_id" value="<?= $lnk['id'] ?>">
                <button type="submit" class="act-btn danger">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <!-- Inline edit row -->
        <tr id="edit-<?= $lnk['id'] ?>" class="edit-row">
          <td colspan="6">
            <form method="POST" class="edit-inner">
              <input type="hidden" name="action"  value="update">
              <input type="hidden" name="link_id" value="<?= $lnk['id'] ?>">
              <span class="slug-prefix">bvtu.ca/go/</span>
              <div class="ef">
                <label>Slug</label>
                <input type="text" name="slug" value="<?= htmlspecialchars($lnk['slug']) ?>"
                       required pattern="[a-z0-9\-]+" style="width:130px;">
              </div>
              <div class="ef">
                <label>Label</label>
                <input type="text" name="label" value="<?= htmlspecialchars($lnk['label']) ?>" style="width:160px;">
              </div>
              <div class="ef" style="flex:1;min-width:200px;">
                <label>Destination URL</label>
                <input type="url" name="destination" value="<?= htmlspecialchars($lnk['destination']) ?>"
                       required style="width:100%;">
              </div>
              <div style="display:flex;gap:.35rem;align-self:flex-end;">
                <button type="submit" class="btn btn-primary" style="padding:.38rem .85rem;font-size:.82rem;">Save</button>
                <button type="button" class="act-btn" onclick="toggleEdit(<?= $lnk['id'] ?>)">Cancel</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>
<script src="../js/qrcode.js"></script>
<script>
function toggleEdit(id) {
    var row = document.getElementById('edit-' + id);
    var open = row.classList.toggle('open');
    if (open) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ── QR code maker ─────────────────────────────────────────────────────────
   Drawn in the browser from the vendored encoder, so nothing typed here is
   sent anywhere — no third-party QR service sees our links. */
(function () {
    var txt  = document.getElementById('qr-text'),
        out  = document.getElementById('qr-out'),
        acts = document.getElementById('qr-actions'),
        size = document.getElementById('qr-size'),
        ec   = document.getElementById('qr-ec'),
        mrg  = document.getElementById('qr-margin');

    // The encoder defaults to one byte per JS char, which silently mangles any
    // non-ASCII text (an em dash or a curly apostrophe decodes as garbage).
    // URLs are unaffected, but the box also takes plain text.
    if (qrcode.stringToBytesFuncs && qrcode.stringToBytesFuncs['UTF-8']) {
        qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
    }

    var state = { qr: null, name: 'qr-code' };

    function fileName(s) {
        var m = s.match(/\/go\/([a-z0-9\-]+)/i);
        if (m) return 'qr-' + m[1].toLowerCase();
        var t = s.replace(/^https?:\/\//i, '').replace(/[^a-z0-9]+/gi, '-')
                 .replace(/^-+|-+$/g, '').toLowerCase();
        return 'qr-' + (t ? t.slice(0, 40) : 'code');
    }

    function draw() {
        var v = txt.value.trim();
        acts.hidden = true;
        state.qr = null;
        if (!v) {
            out.innerHTML = '<div class="qr-empty">Enter a link to see its QR code.</div>';
            return;
        }
        var qr;
        try {
            qr = qrcode(0, ec.value);   // 0 = smallest version the data fits
            qr.addData(v);
            qr.make();
        } catch (e) {
            out.innerHTML = '<div class="qr-err">That is too long to fit in a QR code. ' +
                            'Shorten it \u2014 a bvtu.ca/go/ link is ideal.</div>';
            return;
        }
        state.qr   = qr;
        state.name = fileName(v);

        var count  = qr.getModuleCount(),
            margin = parseInt(mrg.value, 10),
            total  = count + margin * 2,
            target = parseInt(size.value, 10),
            cell   = Math.max(1, Math.floor(target / total)),
            px     = total * cell;

        var cv = document.createElement('canvas');
        cv.width = cv.height = px;
        var g = cv.getContext('2d');
        g.fillStyle = '#ffffff';
        g.fillRect(0, 0, px, px);
        g.fillStyle = '#000000';
        for (var r = 0; r < count; r++) {
            for (var c = 0; c < count; c++) {
                if (qr.isDark(r, c)) {
                    g.fillRect((c + margin) * cell, (r + margin) * cell, cell, cell);
                }
            }
        }
        out.innerHTML = '';
        out.appendChild(cv);
        acts.hidden = false;
    }

    window.qrDownload = function (kind) {
        if (!state.qr) return;
        var a = document.createElement('a'), url;
        if (kind === 'svg') {
            var svg = state.qr.createSvgTag({ cellSize: 8, margin: 8 * parseInt(mrg.value, 10) });
            url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
            a.href = url;
            a.download = state.name + '.svg';
        } else {
            a.href = out.querySelector('canvas').toDataURL('image/png');
            a.download = state.name + '.png';
        }
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        if (url) setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    };

    window.qrFor = function (slug) {
        txt.value = 'https://bvtu.ca/go/' + slug;
        draw();
        txt.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    txt.addEventListener('input', draw);
    [size, ec, mrg].forEach(function (el) { el.addEventListener('change', draw); });
})();
</script>
</body>
</html>
