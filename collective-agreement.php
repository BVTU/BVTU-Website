<?php
/**
 * collective-agreement.php
 * Download page for the SD54 Collective Agreement.
 * Full text is indexed in Algolia (166 article records) for AI search —
 * but is NOT displayed here. Members download the PDF.
 */
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

// Auto-detect any PDF in /documents/ with "collective" or "CA" in the name
$pdfFile   = '';
$pdfUrl    = '';
$pdfExists = false;
$docsDir   = __DIR__ . '/documents/';
if (is_dir($docsDir)) {
    foreach (scandir($docsDir) as $f) {
        if (strtolower(substr($f, -4)) === '.pdf' &&
            preg_match('/collective|agreement|\bCA\b/i', $f)) {
            $pdfFile   = $f;
            $pdfUrl    = 'documents/' . $f;
            $pdfExists = true;
            break;
        }
    }
    // Fallback: just grab the first PDF in the folder
    if (!$pdfExists) {
        foreach (scandir($docsDir) as $f) {
            if (strtolower(substr($f, -4)) === '.pdf') {
                $pdfFile = $f; $pdfUrl = 'documents/' . $f; $pdfExists = true;
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Collective Agreement 2022–2025 — BVTU</title>
  <meta name="description" content="Download the SD54 Bulkley Valley Collective Agreement 2022–2025. Covers salary, leaves, working conditions, TTOC rights, and more.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .ca-download-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-l);
      padding: 2.5rem;
      display: flex;
      align-items: flex-start;
      gap: 1.75rem;
      box-shadow: var(--shadow);
      max-width: 680px;
    }
    .ca-download-icon {
      flex-shrink: 0;
      width: 64px;
      height: 64px;
      background: var(--accent);
      border-radius: var(--radius);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
    }
    .ca-download-body h2 { font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: .35rem; }
    .ca-download-body p  { font-size: .93rem; color: var(--gray-500); line-height: 1.6; margin-bottom: 1.25rem; }
    .ca-toc-mini {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.5rem 1.75rem;
      max-width: 680px;
      margin-top: 2.5rem;
    }
    .ca-toc-mini h3 { font-size: .95rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem; }
    .ca-toc-mini ul { display: grid; grid-template-columns: 1fr 1fr; gap: .3rem .5rem; list-style: none; }
    .ca-toc-mini li { font-size: .88rem; color: var(--gray-700); }
    .ca-ai-tip {
      background: var(--accent);
      border-left: 4px solid var(--primary);
      border-radius: 0 var(--radius-s) var(--radius-s) 0;
      padding: .9rem 1.1rem;
      font-size: .9rem;
      color: var(--gray-700);
      max-width: 680px;
      margin-top: 2rem;
      line-height: 1.6;
    }
    @media (max-width: 560px) {
      .ca-download-card { flex-direction: column; gap: 1.1rem; }
      .ca-toc-mini ul   { grid-template-columns: 1fr; }
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
          <li class="has-dropdown">
            <a href="documents.php">Documents</a>
            <ul class="dropdown">
              <li><a href="documents.php">All Documents</a></li>
              <li><a href="collective-agreement.php" class="active">Collective Agreement</a></li>
            </ul>
          </li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="life-insurance.php">Life Insurance</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
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
      <h1>Collective Agreement</h1>
      <p>SD54 Bulkley Valley · Effective July 1, 2022 to June 30, 2025</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="ca-download-card">
        <div class="ca-download-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="32" height="32">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="12" y1="12" x2="12" y2="18"/>
            <polyline points="9 15 12 18 15 15"/>
          </svg>
        </div>
        <div class="ca-download-body">
          <h2>SD54 Collective Agreement 2022–2025</h2>
          <p>The full collective agreement between the BC Teachers' Federation / Bulkley Valley Teachers' Union and the Board of Education, School District No. 54.</p>
          <?php if ($pdfExists): ?>
            <a href="<?= htmlspecialchars($pdfUrl) ?>" class="btn btn-primary" download>
              Download PDF
            </a>
          <?php else: ?>
            <p style="color:var(--gray-500);font-size:.88rem;font-style:italic;">
              PDF upload coming soon. Use Ask AI below to find specific article information in the meantime.
            </p>
          <?php endif; ?>
        </div>
      </div>

      <div class="ca-ai-tip">
        <strong>Tip:</strong> Use the <strong>Search &amp; Ask AI</strong> button (bottom right of your screen) to ask specific questions about the CA —
        like <em>"How many sick days can I port from another district?"</em> or <em>"What is the salary for Category 5 Step 3?"</em>
      </div>

      <div class="ca-toc-mini">
        <h3>What's covered in the agreement</h3>
        <ul>
          <li>Section A — Collective Bargaining Relationship</li>
          <li>Section B — Salary &amp; Economic Benefits</li>
          <li>Section C — Employment Rights</li>
          <li>Section D — Working Conditions</li>
          <li>Section E — School &amp; Teaching Environment</li>
          <li>Section F — Professional Development</li>
          <li>Section G — Leaves of Absence</li>
          <li>Letters of Understanding</li>
          <li>Appendices &amp; Schedules</li>
        </ul>
      </div>

    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-grid container">
      <div>
        <h4>Bulkley Valley Teachers' Union</h4>
        <p>Local of the BC Teachers' Federation<br>School District 54 — Smithers, BC</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <ul class="footer-nav-list">
          <li><a href="about.php">About BVTU</a></li>
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Member Resources</a></li>
          <li><a href="life-insurance.php">Life Insurance</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
          <li><a href="prod.php">PRO-D</a></li>
        </ul>
      </div>
      <div>
        <h4>Resources</h4>
        <ul class="footer-nav-list">
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="members/login.php">Member Login</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Smithers, BC</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
