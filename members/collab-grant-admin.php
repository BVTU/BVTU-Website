<?php
/**
 * collab-grant-admin.php — Collaboration Grant admin review panel
 * Access: executive members only
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/collab-grant-db.php';
requireLogin();

$member = getMember();

if (!cgIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : cgCurrentYear();
$apps = cgGetApplications($year);

// ── CSV export — must happen before any HTML output ───────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="collab-grant-' . $year . '-' . ($year+1) . '.csv"');
    require_once __DIR__ . '/xlsx-writer.php';   // csvSafeText(), shared guard
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'ID', 'Status', 'Submitted',
        'Applicant Name', 'Email', 'School', 'Position', 'Time in Role',
        'Has Collaborator', 'Collaborator Name', 'Collaborator School', 'Needs Partner Help',
        'Days Requested', 'Days Granted', 'Proposed Dates',
        'Collaboration Description', 'Goals',
        'Admin Notes', 'Reviewed By', 'Reviewed At',
        'Atrieve Logged', 'Atrieve Confirmed', 'Atrieve Confirmed By', 'Invoice Number',
        'Release Cost',
    ]);
    foreach ($apps as $a) {
        $pdArr = json_decode($a['proposed_dates'] ?? '[]', true);
        $pdStr = is_array($pdArr)
            ? implode(', ', array_map(fn($d) => date('D M j Y', strtotime($d)), $pdArr))
            : '';
        fputcsv($out, array_map('csvSafeText', [
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
            ($a['days_approved'] ?? null) !== null ? (int)$a['days_approved'] : '',
            $pdStr,
            $a['collaboration_desc'],
            $a['goals'],
            $a['admin_notes'],
            $a['reviewed_by'],
            $a['reviewed_at'] ? date('Y-m-d', strtotime($a['reviewed_at'])) : '',
            !empty($a['atrieve_confirmed']) ? 'Yes' : 'No',
            !empty($a['atrieve_confirmed_at']) ? date('Y-m-d', strtotime($a['atrieve_confirmed_at'])) : '',
            $a['atrieve_confirmed_by'] ?? '',
            $a['invoice_number'] ?? '',
            ($a['release_cost'] ?? null) !== null
                ? number_format((float)$a['release_cost'], 2, '.', '') : '',
        ]));
    }
    fclose($out);
    exit;
}

// ── Days-per-person export ────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'days') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="collab-days-' . $year . '-' . ($year+1) . '.csv"');
    require_once __DIR__ . '/xlsx-writer.php';   // csvSafeText(), shared guard
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Teacher', 'Email', 'Days Approved', 'Days Pending', 'Days Used',
                   'Days Left', 'Over Limit', 'Took Part As', 'Identified By',
                   'Yearly Cap']);
    foreach (cgDaysLedger($year) as $lp) {
        $roles = array_unique(array_column($lp['apps'], 'role'));
        sort($roles);
        fputcsv($out, array_map('csvSafeText', [
            $lp['name'],
            $lp['email'],
            $lp['approved'],
            $lp['pending'],
            $lp['used'],
            empty($lp['capped']) ? 'n/a' : ($lp['over'] ? 0 : $lp['left']),
            empty($lp['capped']) ? 'n/a' : ($lp['over'] ? 'Yes, by ' . ($lp['used'] - CG_DAY_CAP) : 'No'),
            implode(' + ', $roles),
            $lp['exact'] ? 'Email address' : 'Includes days matched by name — approximate',
            empty($lp['capped']) ? 'Not capped — ' . $lp['exempt_reason'] : 'Applies',
        ]));
    }
    fclose($out);
    exit;
}

$notice = '';

// ── Send someone the link to change their own application ─────────────────
// Separate from the status buttons because it changes nothing about the
// application — it just puts the link in the applicant's hands.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_link') {
    csrfCheck();
    $target = cgGetApplication((int)($_POST['app_id'] ?? 0));
    if (!$target) {
        $notice = 'Could not find that application.';
    } elseif (cgSendEditLink($target, $member['email'])) {
        $notice = 'Edit link sent to ' . $target['applicant_email'] . '.';
    } else {
        $notice = 'The email would not send. Check the mail settings and try again.';
    }
    $apps = cgGetApplications($year);
}

// ── Who the three-day cap applies to ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($_POST['action'] ?? '', ['exempt_on', 'exempt_off'], true)) {
    csrfCheck();
    $person = ['key'   => (string)($_POST['person_key'] ?? ''),
               'email' => (string)($_POST['person_email'] ?? ''),
               'name'  => (string)($_POST['person_name'] ?? '')];
    if (($_POST['action'] ?? '') === 'exempt_off') {
        $notice = cgExemptRemove($person)
            ? 'The three-day limit applies to ' . $person['name'] . ' again, from '
              . $year . '–' . (($year + 1) % 100) . ' on.'
            : 'Nothing changed — no exemption was on record for them.';
    } else {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') $reason = 'Release costs the local nothing';
        // Recorded against the year being viewed and carried forward, so marking
        // someone today does not rewrite a year already closed off.
        $notice = cgExemptAdd($person, $reason, $member['email'], $year)
            ? $person['name'] . ' is not capped from ' . $year . '–' . (($year + 1) % 100)
              . ' on — ' . $reason
            : 'Could not save that. The problem has been logged.';
    }
}

// ── Status updates ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id'])
    && in_array($_POST['action'] ?? '', ['approved', 'declined', 'waitlisted', 'pending'], true)) {
    csrfCheck();
    $id     = (int)$_POST['app_id'];
    $action = $_POST['action'];
    $notes  = trim($_POST['admin_notes'] ?? '');

    // Blank or absent falls back to what was asked for, so the button still works
    // without touching the box.
    $grantDays = null;
    if ($action === 'approved') {
        $raw       = trim((string)($_POST['days_approved'] ?? ''));
        $asked     = cgGetApplication($id);
        $grantDays = $raw === '' ? (int)($asked['days_requested'] ?? 1) : (int)$raw;
        $grantDays = max(1, min(CG_DAY_CAP, $grantDays));
    }
    cgUpdateStatus($id, $action, $member['email'], $notes, $grantDays);

    if ($action === 'approved') {
        $target = cgGetApplication($id);
        if ($target) cgSendApprovalEmail($target);
        $notice = 'Application approved — notification email sent to the applicant.';
    } else {
        $notice = 'Status updated to ' . ucfirst($action) . '.';
    }
    // Refresh apps after update
    $apps = cgGetApplications($year);
}

// ── Follow-through: Atrieve and the district invoice ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fulfilment') {
    csrfCheck();
    $id = (int)($_POST['app_id'] ?? 0);
    // The box is always submitted, so its contents are always applied: a figure
    // records one, an emptied box clears it back to "not recorded".
    $ok = cgSetFulfilment($id, !empty($_POST['atrieve']),
                          (string)($_POST['invoice_number'] ?? ''), $member['email'],
                          (string)($_POST['release_cost'] ?? ''));
    $notice = $ok ? 'Saved.' : 'Could not save. The problem has been logged.';
    $apps   = cgGetApplications($year);
}

$view = ($_GET['view'] ?? 'review'); // 'review' or 'read'

$statusColour = [
    'pending'    => ['bg' => '#fffbeb', 'border' => '#fde68a', 'text' => '#92400e', 'label' => 'Pending'],
    'approved'   => ['bg' => '#f0f9f3', 'border' => '#b3d9bf', 'text' => '#1a5c2e', 'label' => 'Approved'],
    'declined'   => ['bg' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b', 'label' => 'Declined'],
    'waitlisted' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1e40af', 'label' => 'Waitlisted'],
];

$totalDaysApproved = array_sum(array_map('cgEffectiveDays',
    array_filter($apps, fn($a) => $a['status'] === 'approved')));
$pendingCount = count(array_filter($apps, fn($a) => $a['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Collaboration Grant — Admin Review</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
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
          <li><a href="../library.php">Resource Library</a></li>
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
      <a href="?export=days&year=<?= $year ?>" class="export-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Days per person
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
      <?php $rc = cgYearReleaseCost($year); ?>
      <div class="summary-card">
        <?php if (!empty($rc['error'])): ?>
          <div class="val" style="color:#991b1b;font-size:1rem;">unavailable</div>
          <div class="lbl">Release cost could not be worked out</div>
        <?php else: ?>
          <div class="val">$<?= number_format($rc['total'], 2) ?></div>
          <div class="lbl">
            Release cost<?php if ($rc['approved'] > 0): ?>
              &middot; <?= (int)$rc['costed'] ?> of <?= (int)$rc['approved'] ?> costed
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (empty($rc['error']) && $rc['approved'] > $rc['costed']): ?>
    <p style="font-size:.82rem;color:#92400e;background:#fffbeb;border:1px solid #fde68a;
              border-radius:8px;padding:.6rem .9rem;margin:-.4rem 0 1.2rem;line-height:1.7;">
      <?= (int)($rc['approved'] - $rc['costed']) ?>
      approved grant<?= ($rc['approved'] - $rc['costed']) === 1 ? ' has' : 's have' ?>
      no release cost recorded, so the total above is what is known so far, not the year's full cost.
    </p>
    <?php endif; ?>

    <?php // Who has spent what, for the three-day cap and for the FAQ's promise
          // that teachers who haven't used the fund get priority.
          // Only people who have spent a day appear, so there is no "never used"
          // count to take from here — that shows per application instead, where
          // the person being considered is known.
          $ledger = cgDaysLedger($year);
          $anyApprox = (bool)array_filter($ledger, fn($lp) => !$lp['exact']);
    ?>
    <?php if ($ledger): ?>
    <details style="margin-bottom:1.75rem;" <?= $view === 'read' ? 'open' : '' ?>>
      <summary style="cursor:pointer;font-weight:700;color:var(--primary);font-size:.95rem;
                      padding:.6rem 0;">
        Days used by person — <?= count($ledger) ?> <?= count($ledger) === 1 ? 'teacher' : 'teachers' ?>
        this year
      </summary>
      <div style="background:var(--white);border:1.5px solid var(--border);border-radius:10px;
                  padding:1rem;margin-top:.5rem;overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:.86rem;">
          <thead>
            <tr style="text-align:left;color:var(--gray-500);font-size:.78rem;
                       text-transform:uppercase;letter-spacing:.04em;">
              <th style="padding:.4rem .5rem;">Teacher</th>
              <th style="padding:.4rem .5rem;">Approved</th>
              <th style="padding:.4rem .5rem;">Pending</th>
              <th style="padding:.4rem .5rem;">Used</th>
              <th style="padding:.4rem .5rem;">Left</th>
              <th style="padding:.4rem .5rem;">As</th>
              <th style="padding:.4rem .5rem;">Yearly cap</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ledger as $lp): ?>
              <?php
                $roles = array_unique(array_column($lp['apps'], 'role'));
                sort($roles);
              ?>
              <tr style="border-top:1px solid var(--border);">
                <td style="padding:.45rem .5rem;">
                  <strong><?= htmlspecialchars($lp['name']) ?></strong>
                  <?php if ($lp['email']): ?>
                    <span style="color:var(--gray-500);">· <?= htmlspecialchars($lp['email']) ?></span>
                  <?php endif; ?>
                  <?php if (!$lp['exact']): ?>
                    <span style="color:var(--gray-500);">· includes a name match</span>
                  <?php endif; ?>
                </td>
                <td style="padding:.45rem .5rem;"><?= (int)$lp['approved'] ?></td>
                <td style="padding:.45rem .5rem;"><?= (int)$lp['pending'] ?: '—' ?></td>
                <td style="padding:.45rem .5rem;font-weight:700;"><?= (int)$lp['used'] ?></td>
                <td style="padding:.45rem .5rem;<?= $lp['over'] ? 'color:#991b1b;font-weight:700;' : '' ?>">
                  <?php if (empty($lp['capped'])): ?>
                    <span style="color:var(--gray-500);">not capped</span>
                  <?php else: ?>
                    <?= $lp['over'] ? 'over by ' . ($lp['used'] - CG_DAY_CAP) : (int)$lp['left'] ?>
                  <?php endif; ?>
                </td>
                <td style="padding:.45rem .5rem;color:var(--gray-500);">
                  <?= htmlspecialchars(implode(' + ', $roles)) ?>
                </td>
                <td style="padding:.45rem .5rem;">
                  <form method="post" style="display:flex;gap:.3rem;align-items:center;">
                    <?= csrfField() ?>
                    <input type="hidden" name="person_key"   value="<?= htmlspecialchars($lp['key']) ?>">
                    <input type="hidden" name="person_email" value="<?= htmlspecialchars($lp['email']) ?>">
                    <input type="hidden" name="person_name"  value="<?= htmlspecialchars($lp['name']) ?>">
                    <?php if (empty($lp['capped'])): ?>
                      <span style="font-size:.8rem;color:var(--gray-500);"
                            title="<?= htmlspecialchars($lp['exempt_reason']) ?>">
                        <?= htmlspecialchars($lp['exempt_reason']) ?>
                      </span>
                      <button type="submit" name="action" value="exempt_off" class="btn"
                              style="border:1px solid var(--border);background:#fff;color:var(--gray-600);
                                     font-size:.75rem;padding:.2rem .5rem;white-space:nowrap;">
                        Apply the cap
                      </button>
                    <?php else: ?>
                      <input type="text" name="reason" maxlength="200"
                             placeholder="why, e.g. district support teacher"
                             style="width:190px;border:1px solid var(--border);border-radius:6px;
                                    padding:.2rem .4rem;font-size:.78rem;font-family:inherit;">
                      <button type="submit" name="action" value="exempt_on" class="btn"
                              style="border:1px solid var(--border);background:#fff;color:var(--gray-600);
                                     font-size:.75rem;padding:.2rem .5rem;white-space:nowrap;">
                        Not capped
                      </button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p style="font-size:.8rem;color:var(--gray-500);line-height:1.7;margin:.9rem 0 0;">
          Each application spends its days twice — once for the lead and once for the
          collaborator, who books their own absence for the same days.
          <?php if ($anyApprox): ?>
            Rows marked approximate include days from an application where the person
            was named as a collaborator; collaborators are typed names with no email
            address, so those are matched by name and may be wrong.
          <?php endif; ?>
          Anyone who has applied for nothing this year does not appear here at all.
          Mark someone "not capped" when releasing them costs the local nothing — a
          district support teacher, say. Their days still show; the limit stops applying.
        </p>
      </div>
    </details>
    <?php endif; ?>

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
              <?php
                $pdDatesR = [];
                if (!empty($app['proposed_dates'])) {
                    $pdR = json_decode($app['proposed_dates'], true);
                    if (is_array($pdR)) $pdDatesR = $pdR;
                }
              ?>
              <?php if ($pdDatesR): ?>
              <div class="read-fact" style="width:100%;">
                <strong>Proposed dates:</strong>
                <?= implode(', ', array_map(fn($d) => date('D, M j Y', strtotime($d)), $pdDatesR)) ?>
              </div>
              <?php endif; ?>
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
              <?php
                $pdDates = [];
                if (!empty($app['proposed_dates'])) {
                    $pd = json_decode($app['proposed_dates'], true);
                    if (is_array($pd)) $pdDates = $pd;
                }
              ?>
              <?php if ($pdDates): ?>
              <div class="app-detail-item" style="grid-column:1/-1;">
                <div class="dl">Proposed dates</div>
                <div class="dd">
                  <?php foreach ($pdDates as $pd): ?>
                    <span style="display:inline-block;background:#e8f5ed;border:1px solid #b3d9bf;border-radius:20px;padding:.15rem .65rem;font-size:.82rem;font-weight:600;color:#1a5c2e;margin:.1rem .2rem .1rem 0;">
                      <?= date('D, M j Y', strtotime($pd)) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>
            </div>

            <div class="app-text-label">Collaboration description</div>
            <div class="app-text-block"><?= htmlspecialchars($app['collaboration_desc']) ?></div>

            <div class="app-text-label">Goals</div>
            <div class="app-text-block"><?= htmlspecialchars($app['goals']) ?></div>

            <?php if ($app['admin_notes']): ?>
              <div class="app-text-label">Admin notes</div>
              <div class="app-text-block" style="font-style:italic;"><?= htmlspecialchars($app['admin_notes']) ?></div>
            <?php endif; ?>

            <p style="margin:1.25rem 0 0;font-size:.85rem;">
              <a href="../collab-grant-edit.php?id=<?= (int)$app['id'] ?>"
                 style="font-weight:600;">Edit this application</a>
              <span style="color:var(--gray-500);">
                — change the dates, days or details on the applicant's behalf.
                <?php if (!empty($app['edited_at'])): ?>
                  Last changed <?= date('M j, Y', strtotime($app['edited_at'])) ?>
                  by <?= htmlspecialchars($app['edited_by']) ?>.
                <?php endif; ?>
              </span>
            </p>

            <?php // Anyone who applied before the edit page existed has an
                  // application they have never been given a link to. ?>
            <form method="post" style="margin:.5rem 0 0;font-size:.85rem;">
              <?= csrfField() ?>
              <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
              <input type="hidden" name="action" value="resend_link">
              <button type="submit" class="btn"
                      style="border:1px solid var(--border);background:#fff;
                             color:var(--gray-700);font-size:.82rem;padding:.35rem .8rem;">
                <?= !empty($app['link_sent_at']) ? 'Send the edit link again' : 'Email them the edit link' ?>
              </button>
              <span style="color:var(--gray-500);margin-left:.5rem;">
                <?php if (!empty($app['link_sent_at'])): ?>
                  Sent <?= date('M j, Y', strtotime($app['link_sent_at'])) ?><?php
                    ?><?= $app['link_sent_by'] ? ' by ' . htmlspecialchars($app['link_sent_by']) : '' ?>.
                <?php else: ?>
                  Lets <?= htmlspecialchars($app['applicant_name']) ?> change it themselves.
                <?php endif; ?>
              </span>
            </form>

            <form class="app-action-form" method="post">
              <?= csrfField() ?>
              <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);">
                Internal notes (optional)
                <textarea name="admin_notes" rows="2" placeholder="Any notes for your records…"><?= htmlspecialchars($app['admin_notes'] ?? '') ?></textarea>
              </label>
              <?php
                // Both people on an application spend days, so the lead's standing
                // and the collaborator's are both worth seeing before deciding.
                $standings = [['Applicant', cgPersonDays($year, $app['applicant_email'], $app['applicant_name']), $app['applicant_name']]];
                if (trim((string)$app['collaborator_name']) !== '' && !empty($app['has_collaborator'])) {
                    $standings[] = ['Collaborator', cgPersonDays($year, '', $app['collaborator_name']), $app['collaborator_name']];
                }
              ?>
              <div style="background:var(--off-white);border:1px solid var(--border);border-radius:8px;
                          padding:.7rem .85rem;margin:.75rem 0;font-size:.85rem;">
                <?php foreach ($standings as [$role, $p, $who]): ?>
                  <div style="margin-bottom:.25rem;">
                    <strong><?= htmlspecialchars($who) ?></strong>
                    <span style="color:var(--gray-500);">(<?= $role ?>)</span> —
                    <?= htmlspecialchars(cgDaysSentence($p)) ?><?php
                      ?><?= empty($p['capped']) ? '' : ',' ?>
                    <?php if (empty($p['capped'])): ?>
                      <span style="color:var(--gray-500);">
                        (<?= htmlspecialchars($p['exempt_reason']) ?>)</span>
                    <?php elseif ($p['over']): ?>
                      <strong style="color:#991b1b;">over the <?= CG_DAY_CAP ?>-day limit</strong>
                    <?php else: ?>
                      <?= $p['left'] ?> left
                    <?php endif; ?>
                    <?php if (!$p['exact']): ?>
                      <span title="Part of this total comes from an application where the person was named as a collaborator. Collaborators are typed names with no email address, so those days are matched by name and could belong to someone else."
                            style="color:var(--gray-500);">· includes days matched by name</span>
                    <?php endif; ?>
                    <?php if ($p['used'] === 0): ?>
                      <span style="color:#1a5c2e;font-weight:600;">· hasn't used the fund</span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
                <div style="color:var(--gray-500);font-size:.8rem;margin-top:.35rem;">
                  <?php if (in_array($app['status'], ['approved', 'pending'], true)): ?>
                    This application is included in those totals.
                  <?php else: ?>
                    This application is <?= htmlspecialchars($app['status']) ?>, so its days
                    are not counted above.
                  <?php endif; ?>
                </div>
              </div>

              <?php if ($app['status'] === 'approved' && ($app['days_approved'] ?? null) !== null
                        && (int)$app['days_approved'] !== (int)$app['days_requested']): ?>
                <div style="font-size:.84rem;color:#92400e;background:#fffbeb;border:1px solid #fde68a;
                            border-radius:8px;padding:.5rem .7rem;margin-bottom:.6rem;">
                  <?= (int)$app['days_approved'] ?> <?= (int)$app['days_approved'] === 1 ? 'day' : 'days' ?>
                  granted, <?= (int)$app['days_requested'] ?> now requested.
                </div>
              <?php endif; ?>

              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);
                            display:block;margin-bottom:.6rem;max-width:230px;">
                Days to grant
                <input type="number" name="days_approved" min="1" max="<?= CG_DAY_CAP ?>" step="1"
                       value="<?= (int)(($app['days_approved'] ?? null) !== null
                                        ? $app['days_approved'] : $app['days_requested']) ?>"
                       style="width:100%;border:1px solid var(--border);border-radius:7px;
                              padding:.45rem .6rem;font-size:.9rem;font-family:inherit;
                              box-sizing:border-box;margin-top:.25rem;">
                <span style="display:block;font-weight:400;font-size:.78rem;color:var(--gray-500);margin-top:.2rem;">
                  Used when you approve. Applies to both teachers. One application
                  carries at most <?= CG_DAY_CAP ?> days, whoever is on it.
                </span>
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

            <?php // Only once approved: before that there is no absence to log
                  // and no invoice to record, and an empty box invites guessing. ?>
            <?php if ($app['status'] === 'approved'): ?>
            <form class="app-action-form fulfil" method="post" style="margin-top:.6rem;">
              <?= csrfField() ?>
              <input type="hidden" name="app_id" value="<?= $app['id'] ?>">
              <input type="hidden" name="action" value="fulfilment">
              <div style="font-size:.78rem;font-weight:800;text-transform:uppercase;
                          letter-spacing:.05em;color:var(--gray-500);margin-bottom:.5rem;">
                Follow-through
              </div>
              <label style="display:flex;align-items:center;gap:.45rem;font-size:.88rem;
                            color:var(--gray-700);margin-bottom:.6rem;">
                <input type="checkbox" name="atrieve" value="1"
                       <?= !empty($app['atrieve_confirmed']) ? 'checked' : '' ?>>
                Absence logged in Atrieve
              </label>
              <?php if (!empty($app['atrieve_confirmed']) && !empty($app['atrieve_confirmed_at'])): ?>
                <div style="font-size:.78rem;color:var(--gray-500);margin:-.35rem 0 .6rem 1.6rem;">
                  Confirmed <?= date('M j, Y', strtotime($app['atrieve_confirmed_at'])) ?>
                  <?= $app['atrieve_confirmed_by'] ? 'by ' . htmlspecialchars($app['atrieve_confirmed_by']) : '' ?>
                </div>
              <?php endif; ?>
              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);">
                District invoice number
                <input type="text" name="invoice_number" maxlength="100"
                       placeholder="e.g. 45219"
                       value="<?= htmlspecialchars($app['invoice_number'] ?? '') ?>"
                       style="width:100%;border:1px solid var(--border);border-radius:7px;
                              padding:.45rem .6rem;font-size:.9rem;font-family:inherit;
                              box-sizing:border-box;margin-top:.25rem;">
              </label>
              <label style="font-size:.85rem;font-weight:600;color:var(--gray-600);display:block;margin-top:.6rem;">
                Cost of release time
                <input type="number" name="release_cost" step="0.01" min="0"
                       placeholder="not recorded yet"
                       value="<?= ($app['release_cost'] ?? null) !== null
                                  ? number_format((float)$app['release_cost'], 2, '.', '') : '' ?>"
                       style="width:100%;border:1px solid var(--border);border-radius:7px;
                              padding:.45rem .6rem;font-size:.9rem;font-family:inherit;
                              box-sizing:border-box;margin-top:.25rem;">
              </label>
              <div class="app-action-btns" style="margin-top:.6rem;">
                <button type="submit" class="btn" style="border:1px solid var(--border);background:#fff;color:var(--gray-700);">Save follow-through</button>
              </div>
            </form>
            <?php endif; ?>
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
