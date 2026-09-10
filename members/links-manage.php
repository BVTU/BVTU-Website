<?php
/**
 * links-manage.php — Local President: manage bvtu.ca/go/ short links
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
  <title>Short Links — BVTU</title>
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
    .card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
            padding: 1.5rem; margin-bottom: 1.75rem; }
    .card h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1rem; }
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
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Short Links</h1>
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
  <div class="card">
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
<script>
function toggleEdit(id) {
    var row = document.getElementById('edit-' + id);
    var open = row.classList.toggle('open');
    if (open) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>
</body>
</html>
