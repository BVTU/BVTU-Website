<?php
/**
 * collab-grant-admin.php — Collaboration Grant admin review panel
 * Access: executive members only
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/collab-grant-db.php';
requireLogin();

$member = getMember();
if (!prodIsExec($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : cgCurrentYear();
$apps = cgGetApplications($year);

// ── CSV export — must happen before any HTML output ───────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="collab-grant-' . $year . '-' . ($year+1) . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'ID', 'Status', 'Submitted',
        'Applicant Name', 'Email', 'School', 'Position', 'Time in Role',
        'Has Collaborator', 'Collaborator Name', 'Collaborator School', 'Needs Partner Help',
        'Days Requested',
        'Collaboration Description', 'Goals',
        'Admin Notes', 'Reviewed By', 'Reviewed At',
    ]);
    foreach ($apps as $a) {
        fputcsv($out, [
            $a['id'],
            ucfirst($a['status']),
            date('Y-m-d', strtotime($a['submitted_at'])),
            $a['applicant_name'],
            $a['applicant_email'],
            $a['school'],
            $a['position'],
            $a['years_in_role'],
            $a['has_collaborator'] ? 'Yes' : 'No',
            $a['collaborator_name'],
            $a['collaborator_school'],
            $a['needs_partner'] ? 'Yes' : 'No',
            $a['days_requested'],
            $a['collaboration_desc'],
            $a['goals'],
            $a['admin_notes'],
            $a['reviewed_by'],
            $a['reviewed_at'] ? date('Y-m-d', strtotime($a['reviewed_at'])) : '',
        ]);
    }
    fclose($out);
    exit;
}

$notice = '';

// ── Status updates ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['app_id'])) {
    $id     = (int)$_POST['app_id'];
    $action = $_POST['action'];
    $notes  = trim($_POST['admin_notes'] ?? '');

    if (in_array($action, ['approved', 'declined', 'waitlisted', 'pending'], true)) {
        cgUpdateStatus($id, $action, $member['email'], $notes);

        if ($action === 'approved') {
            $app = cgGetApplication($id);
            if ($app) cgSendApprovalEmail($app);
            $notice = 'Application approved — notification email sent to the applicant.';
        } else {
            $notice = 'Status updated to ' . ucfirst($action) . '.';
        }
    }
    // Refresh apps after update
    $apps = cgGetApplications($year);
}

$view = ($_GET['view'] ?? 'review'); // 'review' or 'read'

$statusColour = [
    'pending'    => ['bg' => '#fffbeb', 'border' => '#fde68a', 'text' => '#92400e', 'label' => 'Pending'],
    'approved'   => ['bg' => '#f0f9f3', 'border' => '#b3d9bf', 'text' => '#1a5c2e', 'label' => 'Approved'],
    'declined'   => ['bg' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b', 'label' => 'Declined'],
    'waitlisted' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1e40af', 'label' => 'Waitlisted'],
];

$totalDaysApproved = array_sum(array_column(
    array_filter($apps, fn($a) => $a['status'] === 'approved'),
    'days_requested'
));
$pendingCount = count(array_filter($apps, fn($a) => $a['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Collaboration Grant — Admin Review</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: var(--off-white); }

    .admin-wrap {
      max-width: 1000px;
      margin: 0 auto;
      padding: calc(var(--hdr-h) + 2rem) 1.5rem 3rem;
    }
    .admin-title {
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: .25rem;
    }
    .admin-sub {
      font-size: .9rem;
      color: var(--gray-500);
      margin-bottom: 1.5rem;
    }

    /* ── Top toolbar ── */
    .admin-toolbar {
      display: flex;
      align-items: center;
      gap: .75rem;
      flex-wrap: wrap;
      margin-bottom: 1.75rem;
    }
    .view-toggle {
      display: flex;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      overflow: hidden;
      background: var(--white);
    }
    .view-toggle a {
      padding: .45rem 1rem;
      font-size: .83rem;
      font-weight: 600;
      color: var(--gray-500);
      text-decoration: none;
      border-right: 1px solid var(--border);
      transition: background .15s, color .15s;
    }
    .view-toggle a:last-child { border-right: none; }
    .view-toggle a.active { background: var(--primary); color: #fff; }
    .view-toggle a:not(.active):hover { background: var(--off-white); color: var(--primary); }

    .export-btn {
      display: flex;
      align-items: center;
      gap: .4rem;
      padding: .45rem 1rem;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-size: .83rem;
      font-weight: 600;
      color: var(--gray-600);
      text-decoration: none;
      transition: border-color .15s, color .15s;
    }
    .export-btn:hover { border-color: var(--primary); color: var(--primary); }
    .export-btn svg { width: 14px; height: 14px; }

    /* ── Summary cards ── */
    .summary-row {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 2rem;
    }
    .summary-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: .9rem 1.4rem;
      min-width: 140px;
    }
    .summary-card .val {
      font-size: 1.7rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }
    .summary-card .lbl {
      font-size: .76rem;
      color: var(--gray-500);
      margin-top: .2rem;
    }

    .notice {
      background: #f0f9f3;
      border: 1.5px solid #b3d9bf;
      border-radius: 8px;
      padding: .85rem 1.1rem;
      font-size: .9rem;
      color: #1a5c2e;
      margin-bottom: 1.5rem;
    }

    /* ══ REVIEW VIEW — accordion cards ══════════════════════ */
    .app-list { display: flex; flex-direction: column; gap: 1rem; }

    .app-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .app-card-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.25rem;
      cursor: pointer;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .app-card-head:hover { background: var(--off-white); }
    .app-name { font-weight: 700; color: var(--text); font-size: .97rem; }
    .app-meta { font-size: .82rem; color: var(--gray-500); margin-top: .15rem; }
    .app-status-badge {
      font-size: .75rem;
      font-weight: 700;
      padding: .25rem .7rem;
      border-radius: 20px;
      border: 1px solid;
      white-space: nowrap;
    }
    .app-card-chevron {
      color: var(--gray-400);
      transition: transform .2s;
      flex-shrink: 0;
    }
    .app-card.open .app-card-chevron { transform: rotate(180deg); }

    .app-card-body {
      display: none;
      border-top: 1px solid var(--border);
      padding: 1.25rem;
    }
    .app-card.open .app-card-body { display: block; }

    .app-detail-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
      gap: .75rem 1.25rem;
      margin-bottom: 1.25rem;
    }
    .app-detail-item .dl { color: var(--gray-400); font-size: .74rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .15rem; }
    .app-detail-item .dd { color: var(--text); font-size: .88rem; font-weight: 500; }

    .app-text-label {
      font-size: .74rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--gray-400);
      margin-bottom: .35rem;
    }
    .app-text-block {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: .85rem 1rem;
      font-size: .9rem;
      color: var(--gray-700);
      line-height: 1.65;
      margin-bottom: .85rem;
      white-space: pre-wrap;
    }

    .app-action-form {
      border-top: 1px solid var(--border);
      padding-top: 1.1rem;
      margin-top: 1rem;
      display: flex;
      flex-direction: column;
      gap: .75rem;
    }
    .app-action-form textarea {
      border: 1.5px solid var(--gray-200);
      border-radius: 8px;
      padding: .65rem .9rem;
      font-size: .88rem;
      font-family: inherit;
      width: 100%;
      resize: vertical;
    }
    .app-action-form textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26,107,53,.1);
    }
    .app-action-btns { display: flex; gap: .6rem; flex-wrap: wrap; }
    .app-action-btns .btn {
      padding: .5rem 1.1rem;
      border-radius: 7px;
      font-size: .85rem;
      font-weight: 600;
      cursor: pointer;
      transition: background .15s;
    }
    .btn-approve  { background: var(--primary); color: #fff; border: none; }
    .btn-approve:hover  { background: #155a2a; }
    .btn-waitlist { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .btn-waitlist:hover { background: #dbeafe; }
    .btn-decline  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .btn-decline:hover  { background: #fee2e2; }

    /* ══ READ VIEW — full expanded list ═════════════════════ */
    .read-list { display: flex; flex-direction: column; gap: 2.5rem; }

    .read-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .read-card-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 1.1rem 1.4rem .9rem;
      background: var(--off-white);
      border-bottom: 1px solid var(--border);
      gap: 1rem;
      flex-wrap: wrap;
    }
    .read-card-head h3 {
      font-size: 1rem;
      font-weight: 800;
      color: var(--primary);
      margin: 0 0 .2rem;
    }
    .read-card-head .rc-meta {
      font-size: .82rem;
      color: var(--gray-500);
    }
    .read-card-body { padding: 1.25rem 1.4rem; }

    .read-facts {
      display: flex;
      flex-wrap: wrap;
      gap: .5rem 1.5rem;
      margin-bottom: 1.25rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--gray-100);
    }
    .read-fact { font-size: .85rem; color: var(--gray-600); }
    .read-fact strong { color: var(--text); }

    .read-section-label {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--gray-400);
      margin: 1rem 0 .35rem;
    }
    .read-text {
      font-size: .92rem;
      color: var(--gray-700);
      line-height: 1.7;
      white-space: pre-wrap;
    }

    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--gray-400);
      font-size: .95rem;
    }

    @media print {
      .site-header, .admin-toolbar, .summary-row, .app-action-form { display: none !important; }
      .read-card { break-inside: avoid; }
    }
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
      <nav class="main-nav" id="main-nav">
        <ul>
          <li><a href="dashboard.php">← Dashboard</a></li>
          <li><a href="lp-dashboard.php">LP Dashboard</a></li>
          <li><a href="../collab-grant.php" target="_blank">View grant page ↗</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div class="admin-wrap">

    <div class="admin-title">Collaboration Grant</div>
    <div class="admin-sub"><?= $year ?>–<?= $year + 1 ?> school year · <?= count($apps) ?> application<?= count($apps) !== 1 ? 's' : '' ?></div>

    <!-- Toolbar -->
    <div class="admin-toolbar">
      <div class="view-toggle">
        <a href="?view=review&year=<?= $year ?>" class="<?= $view === 'review' ? 'active' : '' ?>">Review</a>
        <a href="?view=read&year=<?= $year ?>"   class="<?= $view === 'read'   ? 'active' : '' ?>">Read all</a>
      </div>
      <a href="?export=csv&year=<?= $year ?>" class="export-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
      </a>
      <?php if ($view === 'read'): ?>
      <button onclick="window.print()" class="export-btn" style="cursor:pointer;border:1.5px solid var(--border);">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print
      </button>
      <?php endif; ?>
    </div>

    <?php if ($notice): ?>
      <div class="notice"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <!-- Summary -->
    <div class="summary-row">
      <div class="summary-card">
        <div class="val"><?= count($apps) ?></div>
        <div class="lbl">Total applications</div>
      </div>
      <div class="summary-card">
        <div class="val"><?= $pendingCount ?></div>
        <div class="lbl">Awaiting review</div>
      </div>
      <div class="summary-card">
        <div class="val"><?= $totalDaysApproved ?></div>
        <div class="lbl">Release days approved</div>
      </div>
      <div class="summary-card">
        <div class="val"><?= count(array_filter($apps, fn($a) => $a['status'] === 'approved')) ?></div>
        <div class="lbl">Approved</div>
      </div>
    </div>

    <?php if (empty($apps)): ?>
      <div class="empty-state">No applications yet for this school year.</div>

    <?php elseif ($view === 'read'): ?>
      <!-- ══ READ ALL VIEW ════════════════════════════════════════ -->
      <div class="read-list">
        <?php foreach ($apps as $app):
          $sc   = $statusColour[$app['status']] ?? $statusColour['pending'];
          $days = (int)$app['days_requested'];
        ?>
        <div class="read-card">
          <div class="read-card-head">
            <div>
              <h3><?= htmlspecialchars($app['applicant_name']) ?></h3>
              <div class="rc-meta">
                <?= htmlspecialchars($app['school']) ?> · <?= htmlspecialchars($app['position']) ?>
                · submitted <?= date('M j, Y', strtotime($app['submitted_at'])) ?>
              </div>
            </div>
            <span class="app-status-badge" style="background:<?= $sc['bg'] ?>;border-color:<?= $sc['border'] ?>;color:<?= $sc['text'] ?>;">
              <?= $sc['label'] ?>
            </span>
          </div>

          <div class="read-card-body">
            <div class="read-facts">
              <div class="read-fact"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>"><?= htmlspecialchars($app['applicant_email']) ?></a></div>
              <div class="read-fact"><strong>Time in role:</strong> <?= htmlspecialchars($app['years_in_role'] ?: '—') ?></div>
              <div class="read-fact"><strong>Days requested:</strong> <?= $days ?></div>
              <div class="read-fact">
                <strong>Collaborator:</strong>
                <?php if ($app['has_collaborator'] && $app['collaborator_name']): ?>
                  <?= htmlspecialchars($app['collaborator_name']) ?><?= $app['collaborator_school'] ? ' (' . htmlspecialchars($app['collaborator_school']) . ')' : '' ?>
                <?php elseif ($app['needs_partner']): ?>
                  <em>Needs help finding partner</em>
                <?php else: ?>
                  None identified
                <?php endif; ?>
              </div>
            </div>

            <div class="read-section-label">Collaboration description</div>
            <div class="read-text"><?= htmlspecialchars($app['collaboration_desc']) ?></div>

            <div class="read-section-label">Goals</div>
            <div class="read-text"><?= htmlspecialchars($app['goals']) ?></div>

            <?php if ($app['admin_notes']): ?>
              <div class="read-section-label">Admin notes</div>
              <div class="read-text" style="color:var(--gray-500);font-style:italic;"><?= htmlspecialchars($app['admin_notes']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <!-- ══ REVIEW VIEW — accordion ══════════════════════════════ -->
      <div class="app-list">
        <?php foreach ($apps as $app):
          $sc   = $statusColour[$app['status']] ?? $statusColour['pending'];
          $days = (int)$app['days_requested'];
        ?>
        <div class="app-card <?= $app['status'] === 'pending' ? 'open' : '' ?>" id="app-<?= $app['id'] ?>">

          <div class="app-card-head" onclick="toggleCard(<?= $app['id'] ?>)">
            <div style="flex:1;min-width:0;">
              <div class="app-name"><?= htmlspecialchars($app['applicant_name']) ?></div>
              <div class="app-meta">
                <?= htmlspecialchars($app['school']) ?> · <?= htmlspecialchars($app['position']) ?>
                · <?= $days ?> day<?= $days !== 1 ? 's' : '' ?>
                · <?= date('M j', strtotime($app['submitted_at'])) ?>
              </div>
            </div>
            <span class="app-status-badge" style="background:<?= $sc['bg'] ?>;border-color:<?= $sc['border'] ?>;color:<?= $sc['text'] ?>;">
              <?= $sc['label'] ?>
            </span>
            <svg class="app-card-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </div>

          <div class="app-card-body">
            <div class="app-detail-grid">
              <div class="app-detail-item">
                <div class="dl">Email</div>
                <div class="dd"><a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>"><?= htmlspecialchars($app['applicant_email']) ?></a></div>
              </div>
              <div class="app-detail-item">
                <div class="dl">Time in role</div>
                <div class="dd"><?= htmlspecialchars($app['years_in_role'] ?: '—') ?></div>
              </div>
              <div class="app-detail-item">
                <div class="dl">Collaborator</div>
                <div class="dd">
                  <?php if ($app['has_collaborator'] && $app['collaborator_name']): ?>
                    <?= htmlspecialchars($app['collaborator_name']) ?>
                    <?php if ($app['collaborator_school']): ?>
                      <span style="color:var(--gray-400);font-weight:400;"> · <?= htmlspecialchars($app['collaborator_school']) ?></span>
                    <?php endif; ?>
                  <?php elseif ($app['needs_partner']): ?>
                    <em style="color:var(--gray-500);">Needs help finding partner</em>
                  <?php else: ?>
                    <em style="color:var(--gray-500);">None</em>
                  <?php endif; ?>
                </div>
              </div>
              <div class="app-detail-item">
                <div class="dl">Days requested</div>
                <div class="dd"><?= $days ?></div>
              </div>
            </div>

            <div class="app-text-label">Collaboration description</div>
            <div class="app-text-block"><?= htmlspecialchars($app['collaboration_desc']) ?></div>

            <div class="app-text-label">Goals</div>
            <div class="app-text-block"><?= htmlspecialchars($app['goals']) ?></div>

            <?php if ($app['admin_notes']): ?>
              <div class="app-text-label">Admin notes</div>
              <div class="app-text-block" style="font-style:italic;"><?= htmlspecialchars($app['admin_notes']) ?></div>
            <?php endif; ?>

            <form class="app-action-form" method="post">
              <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);">
                Internal notes (optional)
                <textarea name="admin_notes" rows="2" placeholder="Any notes for your records…"><?= htmlspecialchars($app['admin_notes'] ?? '') ?></textarea>
              </label>
              <div class="app-action-btns">
                <button type="submit" name="action" value="approved"   class="btn btn-approve">✓ Approve &amp; notify applicant</button>
                <button type="submit" name="action" value="waitlisted" class="btn btn-waitlist">Waitlist</button>
                <button type="submit" name="action" value="declined"   class="btn btn-decline">Decline</button>
                <?php if ($app['status'] !== 'pending'): ?>
                  <button type="submit" name="action" value="pending" class="btn" style="border:1px solid var(--border);background:#fff;color:var(--gray-500);">Reset to pending</button>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>

  <script src="../js/site.js"></script>
  <script>
    function toggleCard(id) {
      document.getElementById('app-' + id).classList.toggle('open');
    }
  </script>
</body>
</html>
