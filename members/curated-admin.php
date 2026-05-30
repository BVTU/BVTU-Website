<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/curated-db.php';
requireLogin();
curatedEnsureTables();

$member = getMember();
if (!curatedIsCurator($member['email'])) {
    http_response_code(403);
    exit('Access denied — curators only.');
}

$isAdmin  = curatedIsAdmin($member['email']);
$errors   = [];
$success  = '';

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add / edit resource
    if ($action === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $d     = [
            'title'         => trim($_POST['title']        ?? ''),
            'description'   => trim($_POST['description']  ?? ''),
            'url'           => trim($_POST['url']          ?? ''),
            'type'          => $_POST['type']              ?? 'external',
            'grade_band'    => $_POST['grade_band']        ?? 'all',
            'subject'       => $_POST['subject']           ?? '',
            'thumbnail_url' => trim($_POST['thumbnail_url'] ?? ''),
            'sort_order'    => (int)($_POST['sort_order']  ?? 0),
        ];
        if (!$d['title'])   $errors[] = 'Title is required.';
        if (!$d['url'])     $errors[] = 'URL is required.';
        if (!filter_var($d['url'], FILTER_VALIDATE_URL)) $errors[] = 'URL does not appear to be valid.';

        if (!$errors) {
            if ($id) {
                curatedUpdate($id, $d);
                $success = 'Resource updated.';
            } else {
                curatedAdd($d, $member['email'], $member['name'] ?? $member['email']);
                $success = 'Resource added.';
            }
            header('Location: curated-admin.php?success=' . urlencode($success));
            exit;
        }
    }

    // Delete resource
    if ($action === 'delete' && ($rid = (int)($_POST['id'] ?? 0))) {
        curatedDelete($rid);
        header('Location: curated-admin.php?success=Resource+deleted.');
        exit;
    }

    // Add curator (admin only)
    if ($action === 'add_curator' && $isAdmin) {
        $ce = trim($_POST['curator_email'] ?? '');
        $cn = trim($_POST['curator_name']  ?? '');
        if ($ce && filter_var($ce, FILTER_VALIDATE_EMAIL)) {
            curatedAddCurator($ce, $cn, $member['email']);

            // Email the new curator
            $fromEmail   = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'noreply@bvtu.ca';
            $adminName   = $member['name'] ?? $member['email'];
            $curatorName = $cn ?: 'there';
            $host        = $_SERVER['HTTP_HOST'] ?? 'bvtu.ca';
            $protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $adminUrl    = "{$protocol}://{$host}/members/curated-admin.php";
            $publicUrl   = "{$protocol}://{$host}/curated.php";

            $subject = 'BVTU — You\'ve been added as a Curated Resources curator';
            $body    = "Hi {$curatorName},\n\n"
                     . "{$adminName} has given you curator access to the BVTU Curated Resources page.\n\n"
                     . "As a curator, you can add, edit, and remove curated teaching resources "
                     . "that appear on the public Resources page.\n\n"
                     . "Manage resources here (requires your BVTU member login):\n"
                     . "{$adminUrl}\n\n"
                     . "View the public page:\n"
                     . "{$publicUrl}\n\n"
                     . "— Bulkley Valley Teachers' Union";

            $headers  = "From: BVTU Member Portal <{$fromEmail}>\r\n";
            $headers .= "Reply-To: {$fromEmail}\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            mail($ce, $subject, $body, $headers);

            header('Location: curated-admin.php?tab=curators&success=Curator+added+and+notified+by+email.');
            exit;
        }
        $errors[] = 'Valid email required to add a curator.';
    }

    // Remove curator (admin only)
    if ($action === 'remove_curator' && $isAdmin) {
        $ce = trim($_POST['curator_email'] ?? '');
        if ($ce) {
            curatedRemoveCurator($ce);
            header('Location: curated-admin.php?tab=curators&success=Curator+removed.');
            exit;
        }
    }
}

// ── GET: editing an existing resource ─────────────────────────────────────────
$editResource = null;
if (isset($_GET['edit'])) {
    $editResource = curatedGet((int)$_GET['edit']);
}

if (isset($_GET['success'])) $success = htmlspecialchars($_GET['success']);

$tab       = ($_GET['tab'] ?? '') === 'curators' && $isAdmin ? 'curators' : 'resources';
$resources = curatedGetAll();
$curators  = $isAdmin ? curatedGetCurators() : [];

function selOpt(string $val, string $current): string {
    return $val === $current ? ' selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Curated Resources Admin — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    .adm-wrap   { max-width: 960px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
    .adm-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.75rem; }
    .adm-header h1 { font-size: 1.4rem; font-weight: 800; color: var(--primary); margin: 0; }
    .adm-tabs   { display: flex; gap: .4rem; border-bottom: 2px solid var(--border); margin-bottom: 1.75rem; }
    .adm-tab    { padding: .6rem 1.1rem; font-size: .9rem; font-weight: 700; color: var(--gray-500); text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -2px; }
    .adm-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

    .adm-form   { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 2rem; }
    .adm-form h2 { font-size: 1rem; font-weight: 800; color: var(--primary); margin: 0 0 1.1rem; }
    .form-row   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: .3rem; margin-bottom: .9rem; }
    .form-group label { font-size: .83rem; font-weight: 700; color: var(--gray-700); }
    .form-group input, .form-group select, .form-group textarea {
      padding: .55rem .8rem; border: 1.5px solid var(--border); border-radius: 6px;
      font-size: .9rem; font-family: var(--font); color: var(--text);
      transition: border-color .15s;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
      outline: none; border-color: var(--primary);
    }
    .form-group textarea { min-height: 80px; resize: vertical; }
    .form-hint  { font-size: .78rem; color: var(--gray-500); margin-top: .15rem; }

    #fetch-status   { display: none; font-size: .78rem; margin-top: .3rem; }
    #thumb-preview  { display: none; max-height: 72px; max-width: 128px; border-radius: 6px;
                      object-fit: cover; border: 1px solid var(--border); margin-top: .4rem; }

    .adm-table  { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .adm-table th { background: var(--primary); color: white; padding: .6rem .9rem; text-align: left; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; }
    .adm-table td { padding: .65rem .9rem; border-bottom: 1px solid var(--gray-200); vertical-align: middle; }
    .adm-table tr:last-child td { border-bottom: none; }
    .adm-table tr:nth-child(even) td { background: var(--off-white); }
    .adm-table .td-actions { display: flex; gap: .4rem; }

    .btn-sm     { padding: .3rem .75rem; font-size: .8rem; font-weight: 700; border-radius: 5px; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
    .btn-edit   { background: var(--accent); color: var(--primary); border: 1px solid #b8ddc5; }
    .btn-del    { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .btn-del:hover { background: #fee2e2; }

    .alert-success { background: #f0fdf4; border: 1.5px solid #86efac; border-radius: var(--radius-s); padding: .8rem 1.1rem; color: #166534; font-size: .9rem; margin-bottom: 1.25rem; }
    .alert-error   { background: #fef2f2; border: 1.5px solid #fecaca; border-radius: var(--radius-s); padding: .8rem 1.1rem; color: #991b1b; font-size: .9rem; margin-bottom: 1.25rem; }

    .type-badge { display: inline-block; font-size: .72rem; font-weight: 700; padding: .15rem .5rem; border-radius: 100px; }
    .type-ext   { background: #eff6ff; color: #1d4ed8; }
    .type-int   { background: var(--accent); color: var(--primary); }

    @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
  </style>
</head>
<body style="background:var(--off-white);min-height:100vh;">

  <div class="adm-wrap">
    <div class="adm-header">
      <h1>Curated Resources Admin</h1>
      <div style="display:flex;gap:.6rem;">
        <a href="../curated.php" class="btn btn-outline" style="font-size:.85rem;padding:.4rem .9rem;">← View Page</a>
        <?php if ($isAdmin): ?>
          <a href="dashboard.php" class="btn btn-primary" style="font-size:.85rem;padding:.4rem .9rem;">Dashboard</a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert-success"><?= $success ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <!-- Tabs (admin only sees curators tab) -->
    <?php if ($isAdmin): ?>
    <div class="adm-tabs">
      <a href="curated-admin.php" class="adm-tab <?= $tab === 'resources' ? 'active' : '' ?>">Resources</a>
      <a href="curated-admin.php?tab=curators" class="adm-tab <?= $tab === 'curators' ? 'active' : '' ?>">Manage Curators</a>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'resources'): ?>

      <!-- ── Add / Edit form ──────────────────────────────────────────── -->
      <div class="adm-form">
        <h2><?= $editResource ? 'Edit Resource' : 'Add Curated Resource' ?></h2>
        <form method="post">
          <input type="hidden" name="action" value="save">
          <?php if ($editResource): ?>
            <input type="hidden" name="id" value="<?= $editResource['id'] ?>">
          <?php endif; ?>

          <div class="form-row">
            <div class="form-group" style="grid-column:1/-1">
              <label>Title *</label>
              <input type="text" name="title" value="<?= htmlspecialchars($editResource['title'] ?? '') ?>" placeholder="e.g. SD57 Elementary Math Hub" required>
            </div>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" placeholder="Write a short blurb — why is this useful? What will teachers find here?"><?= htmlspecialchars($editResource['description'] ?? '') ?></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>URL *</label>
              <input type="url" name="url" id="curated-url" value="<?= htmlspecialchars($editResource['url'] ?? '') ?>" placeholder="https://…" required>
              <span id="fetch-status"></span>
            </div>
            <div class="form-group">
              <label>Type</label>
              <select name="type">
                <option value="external"<?= selOpt('external', $editResource['type'] ?? 'external') ?>>External website / link</option>
                <option value="internal"<?= selOpt('internal', $editResource['type'] ?? '') ?>>Internal BVTU file</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Grade Band</label>
              <select name="grade_band">
                <option value="all"<?= selOpt('all', $editResource['grade_band'] ?? 'all') ?>>All Grades</option>
                <option value="k3"<?= selOpt('k3', $editResource['grade_band'] ?? '') ?>>Primary K–3</option>
                <option value="47"<?= selOpt('47', $editResource['grade_band'] ?? '') ?>>Intermediate 4–7</option>
                <option value="812"<?= selOpt('812', $editResource['grade_band'] ?? '') ?>>Secondary 8–12</option>
              </select>
            </div>
            <div class="form-group">
              <label>Subject</label>
              <select name="subject">
                <option value="">— Any Subject —</option>
                <?php foreach (CURATED_SUBJECTS as $subj): ?>
                  <option value="<?= htmlspecialchars($subj) ?>"<?= selOpt($subj, $editResource['subject'] ?? '') ?>>
                    <?= htmlspecialchars($subj) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Preview Image <span style="font-weight:400;color:var(--gray-400)">(auto-fetched)</span></label>
              <input type="url" name="thumbnail_url" id="curated-thumb" value="<?= htmlspecialchars($editResource['thumbnail_url'] ?? '') ?>" placeholder="Paste a URL or click Fetch Preview above">
              <img id="thumb-preview" src="<?= htmlspecialchars($editResource['thumbnail_url'] ?? '') ?>" alt="Preview">
              <span class="form-hint">Click "Fetch Preview" to pull automatically, or paste a direct image URL.</span>
            </div>
            <div class="form-group">
              <label>Sort Order <span style="font-weight:400;color:var(--gray-400)">(lower = first)</span></label>
              <input type="number" name="sort_order" value="<?= (int)($editResource['sort_order'] ?? 0) ?>" min="0" max="999">
            </div>
          </div>

          <div style="display:flex;gap:.6rem;margin-top:.5rem;">
            <button type="submit" class="btn btn-primary" style="font-size:.9rem;">
              <?= $editResource ? 'Save Changes' : 'Add Resource' ?>
            </button>
            <?php if ($editResource): ?>
              <a href="curated-admin.php" class="btn btn-outline" style="font-size:.9rem;">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- ── Resource list ────────────────────────────────────────────── -->
      <div class="adm-form">
        <h2>All Curated Resources (<?= count($resources) ?>)</h2>
        <?php if ($resources): ?>
          <table class="adm-table">
            <thead>
              <tr><th>Title</th><th>Band</th><th>Subject</th><th>Type</th><th>Added by</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($resources as $r): ?>
              <tr>
                <td>
                  <a href="<?= htmlspecialchars($r['url']) ?>" target="_blank" rel="noopener" style="color:var(--primary);font-weight:600;">
                    <?= htmlspecialchars($r['title']) ?>
                  </a>
                </td>
                <td><?= htmlspecialchars(CURATED_BANDS[$r['grade_band']] ?? $r['grade_band']) ?></td>
                <td><?= htmlspecialchars($r['subject'] ?: '—') ?></td>
                <td>
                  <span class="type-badge <?= $r['type'] === 'external' ? 'type-ext' : 'type-int' ?>">
                    <?= $r['type'] ?>
                  </span>
                </td>
                <td style="font-size:.82rem;color:var(--gray-500);"><?= htmlspecialchars($r['added_name'] ?: $r['added_by']) ?></td>
                <td>
                  <div class="td-actions">
                    <a href="curated-admin.php?edit=<?= $r['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this resource?');" style="display:inline">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $r['id'] ?>">
                      <button type="submit" class="btn-sm btn-del">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="color:var(--gray-500);font-size:.9rem;">No resources yet — add the first one above.</p>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'curators' && $isAdmin): ?>

      <!-- ── Add curator form ────────────────────────────────────────── -->
      <div class="adm-form">
        <h2>Add Curator</h2>
        <p style="font-size:.88rem;color:var(--gray-600);margin-bottom:1rem;">
          Curators can add, edit, and delete curated resources. They must already have a BVTU member account.
        </p>
        <form method="post">
          <input type="hidden" name="action" value="add_curator">
          <div class="form-row">
            <div class="form-group">
              <label>Teacher Email *</label>
              <input type="email" name="curator_email" placeholder="teacher@sd54.bc.ca" required>
            </div>
            <div class="form-group">
              <label>Name <span style="font-weight:400;color:var(--gray-400)">(for your reference)</span></label>
              <input type="text" name="curator_name" placeholder="Jane Smith">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" style="font-size:.9rem;">Add Curator</button>
        </form>
      </div>

      <!-- ── Curators list ───────────────────────────────────────────── -->
      <div class="adm-form">
        <h2>Current Curators</h2>
        <?php if ($curators): ?>
          <table class="adm-table">
            <thead>
              <tr><th>Email</th><th>Name</th><th>Added by</th><th>Since</th><th></th></tr>
            </thead>
            <tbody>
              <?php foreach ($curators as $c): ?>
              <tr>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td><?= htmlspecialchars($c['name'] ?: '—') ?></td>
                <td style="font-size:.82rem;color:var(--gray-500);"><?= htmlspecialchars($c['added_by']) ?></td>
                <td style="font-size:.82rem;color:var(--gray-500);"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('Remove curator access for <?= htmlspecialchars(addslashes($c['email'])) ?>?');">
                    <input type="hidden" name="action" value="remove_curator">
                    <input type="hidden" name="curator_email" value="<?= htmlspecialchars($c['email']) ?>">
                    <button type="submit" class="btn-sm btn-del">Remove</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="color:var(--gray-500);font-size:.9rem;">No curators yet.</p>
        <?php endif; ?>
      </div>

    <?php endif; ?>
  </div>

  <script src="../js/site.js"></script>
  <script>
  (function () {
    var urlIn    = document.getElementById('curated-url');
    var thumbIn  = document.getElementById('curated-thumb');
    var thumbImg = document.getElementById('thumb-preview');
    var status   = document.getElementById('fetch-status');
    var titleIn  = document.querySelector('input[name="title"]');
    var descIn   = document.querySelector('textarea[name="description"]');

    if (!urlIn) return;

    var lastFetched = '';

    function showThumb(src) {
      if (src && src.match(/^https?:\/\//)) {
        thumbImg.src = src;
        thumbImg.style.display = 'block';
      } else {
        thumbImg.style.display = 'none';
      }
    }

    // Show existing thumbnail on page load (edit mode)
    if (thumbIn.value) showThumb(thumbIn.value);

    // Update preview when thumbnail URL is manually typed
    thumbIn.addEventListener('input', function () {
      showThumb(thumbIn.value.trim());
    });

    function doFetch(url) {
      lastFetched = url;
      status.textContent  = 'Fetching preview…';
      status.style.color  = 'var(--gray-500)';
      status.style.display = 'block';

      fetch('curated-fetch-meta.php?url=' + encodeURIComponent(url))
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) {
            status.textContent = d.error || 'Could not fetch a preview.';
            status.style.color = '#b91c1c';
            return;
          }

          // Only auto-fill fields that are still blank
          if (d.title       && !titleIn.value.trim()) titleIn.value = d.title;
          if (d.description && !descIn.value.trim())  descIn.value  = d.description;

          if (d.thumbnail) {
            thumbIn.value = d.thumbnail;
            showThumb(d.thumbnail);
            status.textContent = 'Preview fetched.';
            status.style.color = '#166534';
          } else {
            status.style.display = 'none';
          }
        })
        .catch(function () {
          status.textContent = 'Could not reach the server for a preview.';
          status.style.color = '#b91c1c';
        });
    }

    // Auto-fetch when the curator leaves the URL field
    urlIn.addEventListener('blur', function () {
      var url = urlIn.value.trim();
      if (!url || url === lastFetched) return;
      if (!url.match(/^https?:\/\/.+/)) return;
      doFetch(url);
    });
  })();
  </script>
</body>
</html>
