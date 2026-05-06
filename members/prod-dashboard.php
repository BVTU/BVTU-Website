<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();
prodSeedTrialAllocation($member['email'], $member['name']);

$bal     = prodGetBalance($member['email']);
$isAdmin = prodIsAdmin($member['email']);

// Pending counts for the member
$db = getDB();
$s  = $db->prepare("SELECT COUNT(*) FROM prod_claims WHERE user_email=? AND status='pending'");
$s->execute([$member['email']]); $myPendingClaims = (int)$s->fetchColumn();

$s = $db->prepare("SELECT COUNT(*) FROM prod_day_requests WHERE user_email=? AND status='pending'");
$s->execute([$member['email']]); $myPendingDays = (int)$s->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pro-D Portal — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.4rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    /* Balance hero */
    .balance-hero { background: linear-gradient(135deg, var(--primary-dk) 0%, var(--primary) 100%); border-radius: 14px; padding: 2rem 2.25rem; color: #fff; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }
    .balance-label { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; opacity: .75; margin-bottom: .3rem; }
    .balance-amount { font-size: 3rem; font-weight: 900; line-height: 1; }
    .balance-sub { font-size: .82rem; opacity: .7; margin-top: .35rem; }
    .balance-stats { display: flex; gap: 2rem; flex-wrap: wrap; }
    .balance-stat { text-align: right; }
    .balance-stat .lbl { font-size: .75rem; opacity: .7; margin-bottom: .15rem; }
    .balance-stat .val { font-size: 1.35rem; font-weight: 800; }

    /* Stat row */
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .stat-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.1rem 1.25rem; }
    .stat-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-400); margin-bottom: .3rem; }
    .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--gray-800); }
    .stat-sub { font-size: .75rem; color: var(--gray-400); margin-top: .2rem; }

    /* Action cards */
    .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 1rem; margin-bottom: 2rem; }
    .action-card { background: #fff; border: 1.5px solid var(--border); border-radius: 12px; padding: 1.5rem 1.4rem 1.3rem; text-decoration: none; color: var(--text); display: flex; flex-direction: column; gap: .5rem; transition: border-color .15s, box-shadow .15s, transform .12s; }
    .action-card:hover { border-color: var(--primary); box-shadow: 0 4px 18px rgba(27,107,66,.1); transform: translateY(-2px); color: var(--text); }
    .action-card-icon { width: 44px; height: 44px; background: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .action-card-icon svg { width: 22px; height: 22px; stroke: var(--primary); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .action-card h3 { font-size: 1rem; font-weight: 800; color: var(--primary); margin: .4rem 0 0; }
    .action-card p { font-size: .83rem; color: var(--gray-500); margin: 0; line-height: 1.5; }
    .action-card .arrow { font-size: .82rem; font-weight: 700; color: var(--primary); margin-top: auto; padding-top: .6rem; }

    /* Badge */
    .badge { display: inline-flex; align-items: center; justify-content: center; background: #dc2626; color: #fff; font-size: .65rem; font-weight: 800; border-radius: 100px; min-width: 18px; height: 18px; padding: 0 5px; margin-left: .4rem; vertical-align: middle; }

    /* Admin section */
    .section-title { font-size: .9rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .06em; margin: 0 0 .85rem; }
    .admin-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; text-decoration: none; color: var(--text); transition: border-color .15s; margin-bottom: .6rem; }
    .admin-card:hover { border-color: var(--primary); color: var(--text); }
    .admin-card-left { display: flex; align-items: center; gap: .85rem; }
    .admin-card-icon { width: 36px; height: 36px; background: #fef3c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
    .admin-card-icon svg { width: 18px; height: 18px; stroke: #d97706; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .admin-card h4 { font-size: .92rem; font-weight: 700; color: var(--gray-800); margin: 0 0 .1rem; }
    .admin-card p { font-size: .78rem; color: var(--gray-500); margin: 0; }

    .trial-notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 9px; padding: .85rem 1.1rem; font-size: .83rem; color: #92400e; margin-bottom: 1.5rem; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <div>
      <h1>Pro-D Portal</h1>
      <div style="font-size:.85rem;color:var(--gray-500);margin-top:.2rem;"><?= htmlspecialchars($member['name']) ?></div>
    </div>
    <a class="back-link" href="dashboard.php">← Back to Dashboard</a>
  </div>

  <div class="trial-notice">
    <strong>Trial mode</strong> — This portal is in setup. Your balance shows a $<?= number_format(PROD_ANNUAL_ALLOCATION, 2) ?> trial allocation. Real opening balances can be entered by the admin before going live.
  </div>

  <!-- Balance hero -->
  <div class="balance-hero">
    <div>
      <div class="balance-label">Available Balance</div>
      <div class="balance-amount">$<?= number_format($bal['balance'], 2) ?></div>
      <div class="balance-sub">Pro-D fund · <?= date('Y') ?> school year</div>
    </div>
    <div class="balance-stats">
      <div class="balance-stat">
        <div class="lbl">Allocated</div>
        <div class="val">$<?= number_format($bal['allocated'], 2) ?></div>
      </div>
      <div class="balance-stat">
        <div class="lbl">Spent</div>
        <div class="val">$<?= number_format($bal['spent'], 2) ?></div>
      </div>
      <div class="balance-stat">
        <div class="lbl">Pending</div>
        <div class="val">$<?= number_format($bal['pending'], 2) ?></div>
      </div>
    </div>
  </div>

  <!-- Stat row -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="stat-label">Carryforward Cap</div>
      <div class="stat-value">$<?= number_format(PROD_CARRYFORWARD_CAP, 2) ?></div>
      <div class="stat-sub">Maximum balance allowed</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Annual Allocation</div>
      <div class="stat-value">$<?= number_format(PROD_ANNUAL_ALLOCATION, 2) ?></div>
      <div class="stat-sub">Per FTE per year (Sep 1)</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">My Pending Claims</div>
      <div class="stat-value"><?= $myPendingClaims ?></div>
      <div class="stat-sub">awaiting review</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">My Day Requests</div>
      <div class="stat-value"><?= $myPendingDays ?></div>
      <div class="stat-sub">awaiting approval</div>
    </div>
  </div>

  <!-- Action cards -->
  <p class="section-title">Quick Actions</p>
  <div class="action-grid">

    <a href="prod-claim-new.php" class="action-card">
      <div class="action-card-icon">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
      </div>
      <h3>Submit a Claim</h3>
      <p>Upload a receipt and submit a financial reimbursement. AI extracts the details for you.</p>
      <div class="arrow">Submit claim →</div>
    </a>

    <a href="prod-claims.php" class="action-card">
      <div class="action-card-icon">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <h3>My Claims</h3>
      <p>View all your submitted financial claims, statuses, and reviewer notes.</p>
      <div class="arrow">View history →</div>
    </a>

    <a href="prod-day-request.php" class="action-card">
      <div class="action-card-icon">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <h3>Request Release Day</h3>
      <p>Request pro-d release days from your school's pool for an upcoming activity.</p>
      <div class="arrow">Request days →</div>
    </a>

  </div>

  <?php
    $showAdminSection = $isAdmin || prodIsTreasurer($member['email']) || prodIsSiteRep($member['email']);
    $memberSchoolId   = prodIsSiteRep($member['email']) ? prodSiteRepSchoolId($member['email']) : null;
  ?>
  <?php if ($showAdminSection): ?>
  <!-- Role-based admin section -->
  <p class="section-title" style="margin-top:1rem;">Review Queue</p>

  <?php if ($isAdmin || prodIsTreasurer($member['email'])): ?>
  <a href="prod-admin.php" class="admin-card">
    <div class="admin-card-left">
      <div class="admin-card-icon">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div>
        <h4>Financial Claims <?php $pc = prodPendingClaims(); if ($pc > 0): ?><span class="badge"><?= $pc ?></span><?php endif; ?></h4>
        <p>Approve or reject pending reimbursement claims</p>
      </div>
    </div>
    <span style="color:var(--gray-400);font-size:1.1rem;">→</span>
  </a>
  <?php endif; ?>

  <?php if ($isAdmin || prodIsSiteRep($member['email'])): ?>
  <a href="prod-admin.php#day-requests" class="admin-card">
    <div class="admin-card-left">
      <div class="admin-card-icon">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div>
        <h4>Day Requests <?php $pd = prodPendingDayRequests($memberSchoolId); if ($pd > 0): ?><span class="badge"><?= $pd ?></span><?php endif; ?></h4>
        <p>Approve or reject pending release day requests<?= $memberSchoolId ? ' from your school' : '' ?></p>
      </div>
    </div>
    <span style="color:var(--gray-400);font-size:1.1rem;">→</span>
  </a>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
  <a href="prod-manage.php" class="admin-card" style="border-color:#e0e7ff;">
    <div class="admin-card-left">
      <div class="admin-card-icon" style="background:#eff6ff;">
        <svg viewBox="0 0 24 24" style="stroke:#1e40af;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      </div>
      <div>
        <h4>Manage Schools &amp; Roles</h4>
        <p>Assign treasurer, site reps, create portal accounts</p>
      </div>
    </div>
    <span style="color:var(--gray-400);font-size:1.1rem;">→</span>
  </a>
  <?php endif; ?>

  <?php endif; // showAdminSection ?>

</div>
</body>
</html>
