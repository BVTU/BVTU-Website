<?php
require_once 'auth.php';
requireLogin();
$member  = getMember();
$welcome = isset($_GET['welcome']);

// Protected documents — add filenames and labels here as you upload PDFs
$documents = [
    'Internal & Executive' => [
        ['file' => 'executive-meeting-minutes.pdf',   'label' => 'Executive Meeting Minutes'],
        ['file' => 'bargaining-strategy-notes.pdf',   'label' => 'Bargaining Strategy Notes'],
        ['file' => 'budget-report.pdf',               'label' => 'BVTU Budget Report'],
    ],
    'Collective Agreement Resources' => [
        ['file' => 'local-agreement-working-copy.pdf','label' => 'Local Agreement — Working Copy'],
        ['file' => 'grievance-log.pdf',               'label' => 'Grievance Log'],
    ],
    'Member Information' => [
        ['file' => 'salary-grid-internal.pdf',        'label' => 'Salary Grid (Internal)'],
        ['file' => 'release-time-tracker.pdf',        'label' => 'Release Time Tracker'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Dashboard — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    .dashboard-hero {
      background: linear-gradient(140deg, var(--primary-dk) 0%, var(--primary) 100%);
      color: var(--white);
      padding: 2.5rem 1.5rem;
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
      <a href="../index.html" class="logo">
        <img src="../bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <nav class="main-nav">
        <ul>
          <li><a href="../about.html">About</a></li>
          <li><a href="../documents.html">Documents</a></li>
          <li><a href="../members.html">Members</a></li>
          <li><a href="../prod.html">PRO-D</a></li>
          <li><a href="../health-safety.html">Health &amp; Safety</a></li>
          <li><a href="../bctf.html">BCTF</a></li>
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

      <?php foreach ($documents as $category => $docs): ?>
        <div class="doc-section">
          <h2><?= htmlspecialchars($category) ?></h2>
          <div class="doc-list">
            <?php foreach ($docs as $doc): ?>
              <a href="serve-doc.php?file=<?= urlencode($doc['file']) ?>" class="doc-item">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <?= htmlspecialchars($doc['label']) ?>
                <span class="lock-badge">Members only</span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-bottom" style="border-top: none;">
      <div class="container">
        <p style="padding: 1.5rem 0;">© 2026 Bulkley Valley Teachers' Union · <a href="logout.php" style="color:rgba(255,255,255,.5)">Sign out</a></p>
      </div>
    </div>
  </footer>

</body>
</html>
