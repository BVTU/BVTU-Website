<?php
require_once 'auth.php';
require_once 'exp-db.php';
require_once 'exec-db.php';
requireLogin();
$member  = getMember();
$welcome = isset($_GET['welcome']);
expEnsureTables();
execEnsureTables();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="../">
  <title>Member Dashboard — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    .dashboard-hero {
      background: linear-gradient(140deg, var(--primary-dk) 0%, var(--primary) 100%);
      color: var(--white);
      padding: calc(var(--hdr-h) + 2.5rem) 1.5rem 2.5rem;
    }
    .dashboard-hero-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
    .dashboard-hero h1 { font-size: 1.5rem; font-weight: 800; margin-bottom: .25rem; }
    .dashboard-hero p { opacity: .78; font-size: .92rem; }
    .welcome-banner {
      background: #dcfce7;
      border: 1px solid #86efac;
      color: #166534;
      border-radius: var(--radius-s);
      padding: .85rem 1.1rem;
      margin-bottom: 1.75rem;
      font-size: .93rem;
      font-weight: 500;
    }
    .doc-section { margin-bottom: 2.5rem; }
    .doc-section h2 { font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: .75rem; padding-bottom: .5rem; border-bottom: 2px solid var(--accent); }
    .doc-list { display: flex; flex-direction: column; gap: .4rem; }
    .doc-item {
      display: flex;
      align-items: center;
      gap: .85rem;
      padding: .85rem 1.1rem;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-s);
      color: var(--text);
      font-size: .93rem;
      font-weight: 500;
      transition: background .15s, border-color .15s, transform .15s;
    }
    .doc-item:hover { background: var(--accent); border-color: var(--blue); transform: translateX(4px); text-decoration: none; color: var(--primary); }
    .doc-item svg { width: 20px; height: 20px; flex-shrink: 0; color: var(--blue); fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .lock-badge { margin-left: auto; font-size: .75rem; font-weight: 600; color: var(--gray-500); background: var(--gray-100); padding: .2rem .55rem; border-radius: 100px; }
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
          <li><a href="../health-safety.php">Health &amp; Safety</a></li>
          <li><a href="../bctf.php">BCTF</a></li>
          <li><a href="../library.php">Resource Library</a></li>
          <li><a href="logout.php">Sign Out</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div class="dashboard-hero">
    <div class="container dashboard-hero-inner">
      <div>
        <h1>Welcome, <?= htmlspecialchars($member['name']) ?></h1>
        <p>Members-only documents and resources</p>
      </div>
      <a href="logout.php" class="btn btn-outline-white">Sign Out</a>
    </div>
  </div>

  <main class="page-content">
    <div class="container">

      <?php if ($welcome): ?>
        <div class="welcome-banner">
          Your account has been created successfully. Welcome to the BVTU members portal.
        </div>
      <?php endif; ?>

      <div class="doc-section" style="margin-bottom:1.5rem;">
        <h2>Pro-D Portal</h2>
        <div class="doc-list">
          <a href="prod-dashboard.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Professional Development Portal
          </a>
          <a href="prod-admin.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Pro-D Review Queue
          </a>
          <a href="prod-manage.php" class="doc-item">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Pro-D Schools &amp; Roles
          </a>
        </div>
      </div>

      <div class="doc-section" style="margin-bottom:1.5rem;">
        <h2>LP Expense Tracker</h2>
        <div class="doc-list">
          <a href="lp-dashboard.php" class="doc-item">
            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            Expense Dashboard &amp; Grant Summary
          </a>
          <a href="lp-voucher-new.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            New Expense Voucher
          </a>
        </div>
      </div>

      <div class="doc-section" style="margin-bottom:1.5rem;">
        <h2>Newsletter Archive</h2>
        <div class="doc-list">
          <a href="../newsletter-archive.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            Browse &amp; Search Newsletters
          </a>
          <a href="newsletter-admin.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Sync from Mailchimp (Admin)
          </a>
        </div>
      </div>

      <div class="doc-section" style="margin-bottom:1.5rem;">
        <h2>Resource Library</h2>
        <div class="doc-list">
          <a href="../library.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            Browse &amp; Download Resources
          </a>
          <a href="../library-upload.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Upload a Resource
          </a>
          <a href="library-admin.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Library Admin Panel
          </a>
        </div>
      </div>

      <div class="doc-section" style="margin-bottom:1.5rem;">
        <h2>Collaboration Grant</h2>
        <div class="doc-list">
          <a href="collab-grant-admin.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Review Applications &amp; Export CSV
          </a>
        </div>
      </div>

      <div class="doc-section" style="margin-bottom:1.5rem;">
        <h2>EC Mileage</h2>
        <div class="doc-list">
          <a href="../mileage.php" class="doc-item">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
            Submit EC Mileage Claim
          </a>
          <?php if (execIsEC($member['email'])): ?>
          <a href="mileage-admin.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            EC Mileage Claims — Admin View
          </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="doc-section" style="margin-bottom:1.5rem;">
        <h2>Expense Reimbursement</h2>
        <div class="doc-list">
          <a href="exp-dashboard.php" class="doc-item">
            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            My Expense Submissions
          </a>
          <a href="exp-submit.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            Submit New Expense
          </a>
          <?php if (expIsTreasurer($member['email']) || expIsAdmin($member['email'])): ?>
          <a href="exp-treasurer.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            Expense Review Queue
            <?php $pendingN = expPendingForTreasurer(); if ($pendingN > 0): ?>
            <span class="lock-badge"><?= $pendingN ?> pending</span>
            <?php endif; ?>
          </a>
          <?php endif; ?>
          <?php if (expIsEligibleSigner2($member['email']) || expIsAdmin($member['email'])): ?>
          <a href="exp-signer2.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Second Signature Queue
            <?php $signer2N = expPendingForSigner2(); if ($signer2N > 0): ?>
            <span class="lock-badge"><?= $signer2N ?> pending</span>
            <?php endif; ?>
          </a>
          <?php endif; ?>
          <?php if (expIsTreasurer($member['email']) || expIsAdmin($member['email'])): ?>
          <a href="exp-payments.php" class="doc-item">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Payment Records
          </a>
          <?php endif; ?>
          <?php if (expIsAdmin($member['email'])): ?>
          <a href="exp-manage.php" class="doc-item">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Expense Portal Admin
          </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="doc-section" style="margin-bottom:1.5rem;">
        <h2>Administration</h2>
        <div class="doc-list">
          <a href="roles-overview.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Executive &amp; Roles Directory
          </a>
          <a href="token-usage.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Claude API Token Usage
          </a>
        </div>
      </div>

    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-bottom" style="border-top: none;">
      <div class="container">
        <p style="padding: 1.5rem 0;">© 2026 Bulkley Valley Teachers' Union · <a href="logout.php" style="color:rgba(255,255,255,.5)">Sign out</a></p>
      </div>
    </div>
  </footer>

  <script src="../js/site.js"></script>
  <script src="../js/search.js"></script>
</body>
</html>
