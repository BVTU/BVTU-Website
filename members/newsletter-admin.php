<?php
// ============================================================
//  BVTU Newsletter Archive — Admin Panel
//  Sync from Mailchimp, manage archived newsletters
// ============================================================
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/newsletter-db.php';

$member = getMember();

// Simple admin check — same pattern as library-admin.php
function nlIsAdmin(): bool {
    $m = getMember();
    if (!$m) return false;
    $adminEmail = defined('PROD_ADMIN_EMAIL') ? PROD_ADMIN_EMAIL : 'lp54@bctf.ca';
    return strtolower($m['email']) === strtolower($adminEmail);
}
if (!nlIsAdmin()) {
    http_response_code(403);
    echo '<p style="font-family:sans-serif;padding:2rem;">Access denied — admins only.</p>';
    exit;
}

$syncResult = null;
$deleteMsg  = '';

// ── Actions ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'sync') {
            $syncResult = nlSyncCampaigns(false);
        }
        if ($action === 'sync_force') {
            $syncResult = nlSyncCampaigns(true);
        }
        if ($action === 'delete' && !empty($_POST['nl_id'])) {
            nlDeleteNewsletter((int)$_POST['nl_id']);
            $deleteMsg = 'Newsletter deleted.';
        }
    }
}

$newsletters = nlGetNewsletters('', 0, 500);
$total       = count($newsletters);
$lastSync    = nlGetLastSync();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="../">
  <title>Newsletter Admin — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    .admin-wrap { max-width: 1000px; margin: 0 auto; }
    /* Stat strip */
    .nl-stats {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 1rem; margin-bottom: 2rem;
    }
    .nl-stat {
      background: #fff; border: 1.5px solid var(--gray-200); border-radius: 10px;
      padding: 1.1rem 1.25rem; display: flex; flex-direction: column; gap: .25rem;
    }
    .nl-stat-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-400); }
    .nl-stat-value { font-size: 1.65rem; font-weight: 800; color: var(--primary-dk); line-height: 1.1; }
    .nl-stat-sub   { font-size: .75rem; color: var(--gray-400); }
    /* Sync box */
    .sync-box {
      background: #fff; border: 1.5px solid var(--gray-200); border-radius: 10px;
      padding: 1.4rem 1.6rem; margin-bottom: 2rem;
    }
    .sync-box h2 { font-size: 1rem; font-weight: 700; color: var(--primary); margin: 0 0 .6rem; }
    .sync-box p  { font-size: .88rem; color: var(--gray-600); margin: 0 0 1rem; line-height: 1.6; }
    .sync-actions { display: flex; gap: .75rem; flex-wrap: wrap; }
    .sync-result {
      margin-top: 1rem; padding: .85rem 1rem;
      border-radius: 8px; font-size: .88rem; line-height: 1.6;
    }
    .sync-result.ok  { background: #f0f9f3; border: 1px solid #b3d9bf; color: #1a6b35; }
    .sync-result.err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    /* Delete msg */
    .delete-msg {
      background: #f0f9f3; border: 1px solid #b3d9bf; border-radius: 8px;
      padding: .7rem 1rem; font-size: .88rem; color: #1a6b35; margin-bottom: 1.25rem;
    }
    /* Table */
    .nl-table-wrap { overflow-x: auto; }
    .nl-table {
      width: 100%; border-collapse: collapse; font-size: .875rem;
    }
    .nl-table th {
      background: var(--gray-100); padding: .65rem 1rem; text-align: left;
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .05em; color: var(--gray-500); white-space: nowrap;
      border-bottom: 1.5px solid var(--gray-200);
    }
    .nl-table td {
      padding: .7rem 1rem; border-bottom: 1px solid var(--gray-100);
      vertical-align: middle; color: var(--gray-700);
    }
    .nl-table tr:last-child td { border-bottom: none; }
    .nl-table tr:hover td { background: #fafafa; }
    .nl-subject-cell { font-weight: 600; color: var(--gray-800); max-width: 340px; }
    .nl-subject-cell a { color: inherit; text-decoration: none; }
    .nl-subject-cell a:hover { color: var(--primary); text-decoration: underline; }
    .tbl-action { display: flex; gap: .4rem; flex-wrap: wrap; }
    .btn-sm { padding: .3rem .75rem; font-size: .78rem; border-radius: 5px; }
    .btn-danger { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; cursor: pointer; font-size: .78rem; padding: .3rem .75rem; border-radius: 5px; transition: background .15s; }
    .btn-danger:hover { background: #fee2e2; }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="header-inner container">
      <a href="../index.php" class="logo">
        <img src="../bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <nav class="main-nav">
        <ul>
          <li><a href="../documents.php">Documents</a></li>
          <li><a href="../members.php">Members</a></li>
          <li><a href="../prod.php">PRO-D</a></li>
          <li><a href="../library.php">Resource Library</a></li>
          <li><a href="../newsletter-archive.php">Newsletters</a></li>
          <li><a href="logout.php">Sign Out</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div class="dashboard-hero">
    <div class="container dashboard-hero-inner">
      <div>
        <h1>Newsletter Admin</h1>
        <p>Sync from Mailchimp and manage the archive</p>
      </div>
      <a href="../newsletter-archive.php" class="btn btn-outline-white">View Archive</a>
    </div>
  </div>

  <main class="page-content">
    <div class="container">
      <div class="admin-wrap">

        <!-- Stats strip -->
        <div class="nl-stats">
          <div class="nl-stat">
            <span class="nl-stat-label">Newsletters</span>
            <span class="nl-stat-value"><?= $total ?></span>
            <span class="nl-stat-sub">in archive</span>
          </div>
          <div class="nl-stat">
            <span class="nl-stat-label">Last synced</span>
            <span class="nl-stat-value" style="font-size:1rem;padding-top:.3rem;">
              <?= $lastSync ? date('M j, Y', strtotime($lastSync)) : '—' ?>
            </span>
            <span class="nl-stat-sub"><?= $lastSync ? date('g:i a', strtotime($lastSync)) : 'never' ?></span>
          </div>
        </div>

        <?php if ($deleteMsg): ?>
          <div class="delete-msg">✓ <?= htmlspecialchars($deleteMsg) ?></div>
        <?php endif; ?>

        <!-- Sync box -->
        <div class="sync-box">
          <h2>Sync from Mailchimp</h2>
          <p>
            <strong>Sync new</strong> pulls only campaigns not yet in the archive — fast, safe to run any time.<br>
            <strong>Force re-sync all</strong> re-fetches the HTML content of every campaign — use this if a newsletter looks broken or was edited in Mailchimp after sending.
          </p>
          <form method="post">
            <div class="sync-actions">
              <button type="submit" name="action" value="sync" class="btn btn-primary">
                ↓ Sync new newsletters
              </button>
              <button type="submit" name="action" value="sync_force" class="btn btn-outline"
                      onclick="return confirm('Re-fetch all newsletter content from Mailchimp? This may take a minute.');">
                ↺ Force re-sync all
              </button>
            </div>
          </form>

          <?php if ($syncResult !== null): ?>
            <?php $hasErrors = !empty($syncResult['errors']); ?>
            <div class="sync-result <?= $hasErrors ? 'err' : 'ok' ?>">
              <?php if (!$hasErrors): ?>
                ✓ Sync complete —
                <strong><?= $syncResult['added'] ?></strong> added,
                <strong><?= $syncResult['updated'] ?></strong> updated,
                <strong><?= $syncResult['skipped'] ?></strong> already up-to-date.
              <?php else: ?>
                ⚠ Sync finished with issues —
                <?= $syncResult['added'] ?> added,
                <?= $syncResult['updated'] ?> updated,
                <?= $syncResult['skipped'] ?> skipped.<br>
                <?php foreach ($syncResult['errors'] as $e): ?>
                  <span style="display:block;margin-top:.25rem;">• <?= htmlspecialchars($e) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Newsletter table -->
        <?php if ($total > 0): ?>
          <h2 style="font-size:1rem;font-weight:700;color:var(--primary);margin:0 0 .75rem;">
            Archived newsletters (<?= $total ?>)
          </h2>
          <div class="nl-table-wrap">
            <table class="nl-table">
              <thead>
                <tr>
                  <th>Subject</th>
                  <th>Sent</th>
                  <th>Recipients</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($newsletters as $nl): ?>
                  <tr>
                    <td class="nl-subject-cell">
                      <a href="../newsletter.php?id=<?= $nl['id'] ?>" target="_blank">
                        <?= htmlspecialchars($nl['subject']) ?>
                      </a>
                    </td>
                    <td style="white-space:nowrap;color:var(--gray-500);">
                      <?= $nl['send_date'] ? date('M j, Y', strtotime($nl['send_date'])) : '—' ?>
                    </td>
                    <td style="color:var(--gray-500);">
                      <?= $nl['emails_sent'] ? number_format($nl['emails_sent']) : '—' ?>
                    </td>
                    <td>
                      <div class="tbl-action">
                        <a href="../newsletter.php?id=<?= $nl['id'] ?>" target="_blank"
                           class="btn btn-outline btn-sm">View</a>
                        <?php if ($nl['archive_url']): ?>
                          <a href="<?= htmlspecialchars($nl['archive_url']) ?>" target="_blank" rel="noopener"
                             class="btn btn-outline btn-sm">Mailchimp ↗</a>
                        <?php endif; ?>
                        <form method="post" style="display:inline;"
                              onsubmit="return confirm('Delete this newsletter from the archive?');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="nl_id" value="<?= $nl['id'] ?>">
                          <button type="submit" class="btn-danger">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p style="color:var(--gray-400);font-size:.9rem;">No newsletters in the archive yet — sync from Mailchimp above.</p>
        <?php endif; ?>

      </div>
    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-bottom" style="border-top:none;">
      <div class="container">
        <p style="padding:1.5rem 0;">© 2026 Bulkley Valley Teachers' Union · <a href="logout.php" style="color:rgba(255,255,255,.5)">Sign out</a></p>
      </div>
    </div>
  </footer>

  <script src="../js/site.js"></script>
</body>
</html>
