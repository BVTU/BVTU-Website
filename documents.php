<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

// Members-only documents — update filenames here as you upload PDFs
$protected_docs = [
    'Internal &amp; Executive' => [
        ['file' => 'executive-meeting-minutes.pdf',    'label' => 'Executive Meeting Minutes'],
        ['file' => 'budget-report.pdf',                'label' => 'BVTU Budget Report'],
        ['file' => 'bargaining-strategy-notes.pdf',    'label' => 'Bargaining Strategy Notes'],
    ],
    'Local Agreement Resources' => [
        ['file' => 'local-agreement-working-copy.pdf', 'label' => 'Local Agreement — Working Copy'],
        ['file' => 'grievance-log.pdf',                'label' => 'Grievance Log'],
    ],
    'Member Information' => [
        ['file' => 'salary-grid-internal.pdf',         'label' => 'Salary Grid (Internal)'],
        ['file' => 'release-time-tracker.pdf',         'label' => 'Release Time Tracker'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Documents — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU documents — collective agreements, settlements, provincial regulations, ethics codes, and professional standards.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .members-locked {
      background: var(--off-white);
      border: 2px dashed var(--border);
      border-radius: var(--radius);
      padding: 2.5rem;
      text-align: center;
      margin-top: 1rem;
    }
    .members-locked .lock-icon {
      width: 48px;
      height: 48px;
      background: var(--accent);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      color: var(--blue);
    }
    .members-locked .lock-icon svg { width: 24px; height: 24px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .members-locked h3 { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: .5rem; }
    .members-locked p { color: var(--gray-500); font-size: .92rem; margin-bottom: 1.25rem; }
    .members-section-title {
      display: flex;
      align-items: center;
      gap: .65rem;
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 1rem;
      padding-bottom: .6rem;
      border-bottom: 2px solid var(--accent);
    }
    .members-badge {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      background: var(--primary);
      color: white;
      padding: .2rem .55rem;
      border-radius: 100px;
    }
    .section-divider {
      border: none;
      border-top: 2px solid var(--gray-200);
      margin: 3rem 0;
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="header-inner container">
      <a href="index.php" class="logo">
        <img src="bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <button class="search-btn" data-search-open aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav">
        <ul>
          <li><a href="about.php">About</a></li>
          <li><a href="documents.php" class="active">Documents</a></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="<?= $loggedIn ? 'members/dashboard.php' : 'members/login.php' ?>"
                class="btn btn-primary"
                style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Documents</h1>
      <p>Collective agreements, regulations, and professional standards — plus members-only resources below.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- PUBLIC DOCUMENTS — visible to everyone -->
      <div class="doc-categories">

        <div class="doc-category">
          <h3>Collective Agreements</h3>
          <div class="doc-list">
            <a href="documents/provincial-collective-agreement.pdf" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Provincial Collective Agreement
            </a>
            <a href="documents/local-collective-agreement.pdf" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Local Collective Agreement (SD54–BVTU)
            </a>
          </div>
        </div>

        <div class="doc-category">
          <h3>Provincial Regulations</h3>
          <div class="doc-list">
            <a href="documents/school-act.pdf" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              School Act
            </a>
            <a href="documents/teachers-act.pdf" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Teachers Act
            </a>
          </div>
        </div>

        <div class="doc-category">
          <h3>Ethics &amp; Professional Standards</h3>
          <div class="doc-list">
            <a href="documents/bctf-code-of-ethics.pdf" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              BCTF Code of Ethics
            </a>
            <a href="documents/standards-for-educators.pdf" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Standards for Educators (TRB)
            </a>
          </div>
        </div>

        <div class="doc-category">
          <h3>BVTU Governance</h3>
          <div class="doc-list">
            <a href="documents/bvtu-bylaws.pdf" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              BVTU Bylaws
            </a>
            <a href="documents/bvtu-constitution.pdf" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              BVTU Constitution
            </a>
          </div>
        </div>

      </div>

      <!-- MEMBERS-ONLY SECTION -->
      <hr class="section-divider">

      <div class="members-section-title">
        Members-Only Documents
        <span class="members-badge">Login Required</span>
      </div>

      <?php if ($loggedIn): ?>

        <!-- Logged in — show protected documents -->
        <p style="color:var(--gray-500);font-size:.92rem;margin-bottom:1.5rem;">
          Welcome, <?= htmlspecialchars($member['name']) ?>. Your members-only documents are below.
        </p>
        <div class="doc-categories">
          <?php foreach ($protected_docs as $category => $docs): ?>
            <div class="doc-category">
              <h3><?= $category ?></h3>
              <div class="doc-list">
                <?php foreach ($docs as $doc): ?>
                  <a href="members/serve-doc.php?file=<?= urlencode($doc['file']) ?>" class="doc-item">
                    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <?= htmlspecialchars($doc['label']) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>

        <!-- Not logged in — show login prompt -->
        <div class="members-locked">
          <div class="lock-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
          <h3>Members-Only Content</h3>
          <p>Log in with your BVTU member account to access internal documents, meeting minutes, and more.</p>
          <a href="members/login.php" class="btn btn-primary">Sign In</a>
          &nbsp;
          <a href="members/register.php" class="btn btn-outline">Create Account</a>
        </div>

      <?php endif; ?>

      <div class="info-box" style="margin-top: 2.5rem;">
        <p>Can't find what you're looking for? Contact the union office at <a href="tel:6134853127">613 485 3127</a>.</p>
      </div>

    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <h3>Bulkley Valley Teachers' Union</h3>
        <p>Local of the BC Teachers' Federation</p>
        <p>Representing educators in<br>Houston, Telkwa, and Smithers</p>
      </div>
      <div>
        <h3>Contact</h3>
        <p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p>
        <p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p>
        <p><a href="tel:6134853127">613 485 3127</a></p>
      </div>
      <div>
        <h3>Navigate</h3>
        <ul class="footer-nav-list">
          <li><a href="about.php">About</a></li>
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Members</a></li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
        </ul>
      </div>
      <div>
        <h3>Connect</h3>
        <a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
