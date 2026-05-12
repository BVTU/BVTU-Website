<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
          
          <li class="has-dropdown active">
            <a href="documents.php" class="active">Documents</a>
            <ul class="dropdown">
              <li><a href="documents.php">All Documents</a></li>
              <li><a href="collective-agreement.php">Collective Agreement</a></li>
              <li><a href="lous.php">Letters of Understanding</a></li>
              <li><a href="ca-assistant.php">Contract Assistant</a></li>
              <li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li>
            </ul>
          </li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li><a href="library.php">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li>
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

      <!-- QUICK-ACCESS CARDS -->
      <style>
        .doc-page-grid {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 1rem;
          margin-bottom: 2.5rem;
        }
        @media (max-width: 640px) {
          .doc-page-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 380px) {
          .doc-page-grid { grid-template-columns: 1fr; }
        }
        .doc-page-card {
          display: flex;
          flex-direction: column;
          background: var(--white);
          border: 1.5px solid var(--border);
          border-radius: var(--radius);
          padding: 1.3rem 1.15rem 1.1rem;
          text-decoration: none;
          color: var(--text);
          transition: border-color .15s, box-shadow .15s, transform .12s;
        }
        .doc-page-card:hover {
          border-color: var(--primary);
          box-shadow: 0 4px 18px rgba(27,107,66,.1);
          transform: translateY(-2px);
          color: var(--text);
        }
        .doc-page-card-icon {
          width: 40px; height: 40px;
          background: var(--accent);
          border-radius: 10px;
          display: flex; align-items: center; justify-content: center;
          margin-bottom: .85rem;
          flex-shrink: 0;
        }
        .doc-page-card-icon svg {
          width: 20px; height: 20px;
          stroke: var(--primary); fill: none;
          stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }
        .doc-page-card h3 { font-size: .95rem; font-weight: 800; color: var(--primary); margin: 0 0 .3rem; }
        .doc-page-card p  { font-size: .82rem; color: var(--gray-500); margin: 0; line-height: 1.5; flex: 1; }
        .doc-page-card-arrow { font-size: .8rem; font-weight: 700; color: var(--primary); margin-top: .85rem; }

        /* Calendar cards */
        .cal-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
          gap: 1.5rem;
          margin-bottom: 2rem;
        }
        .cal-card {
          background: var(--white);
          border: 1.5px solid var(--border);
          border-radius: var(--radius);
          overflow: hidden;
        }
        .cal-card-img {
          width: 100%;
          display: block;
          border-bottom: 1px solid var(--border);
        }
        .cal-card-body { padding: 1rem 1.1rem 1.1rem; }
        .cal-card-body h3 { font-size: 1rem; font-weight: 800; color: var(--primary); margin: 0 0 .35rem; }
        .cal-card-body p  { font-size: .85rem; color: var(--gray-500); margin: 0 0 .9rem; }
        .cal-card-actions { display: flex; gap: .6rem; flex-wrap: wrap; }
        .cal-card-actions a {
          display: inline-flex; align-items: center; gap: .35rem;
          font-size: .82rem; font-weight: 700;
          padding: .4rem .85rem; border-radius: 6px;
          text-decoration: none;
        }
        .cal-btn-view {
          background: var(--accent); color: var(--primary);
          border: 1.5px solid var(--primary-light, #b8ddc5);
        }
        .cal-btn-view:hover { background: #d7eee2; }
        .cal-btn-dl {
          background: var(--primary); color: white;
          border: 1.5px solid transparent;
        }
        .cal-btn-dl:hover { background: #155a2a; }
        .cal-btn-dl svg, .cal-btn-view svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.2; flex-shrink: 0; }
      </style>

      <div class="doc-page-grid">

        <a href="collective-agreement.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <h3>Collective Agreement</h3>
          <p>Local and provincial CA — your rights, entitlements, and working conditions.</p>
          <div class="doc-page-card-arrow">Read the CA →</div>
        </a>

        <a href="lous.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </div>
          <h3>Letters of Understanding</h3>
          <p>All signed LOUs, settlements, and arbitration awards between BVTU and SD54.</p>
          <div class="doc-page-card-arrow">Browse LOUs →</div>
        </a>

        <a href="ca-assistant.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z"/></svg>
          </div>
          <h3>Contract Assistant</h3>
          <p>Ask plain-language questions about the CA and get instant answers.</p>
          <div class="doc-page-card-arrow">Ask a question →</div>
        </a>

        <a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank" rel="noopener" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
          </div>
          <h3>Constitution &amp; Bylaws</h3>
          <p>BVTU Constitution and Bylaws, approved May 2026.</p>
          <div class="doc-page-card-arrow">Open PDF →</div>
        </a>

        <a href="calendars.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>School Calendars</h3>
          <p>SD54 school calendars for 2025–26 and 2026–27 — view and download.</p>
          <div class="doc-page-card-arrow">View calendars →</div>
        </a>

        <a href="lous.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <h3>Settlements</h3>
          <p>Local settlements and remedies reached between BVTU and SD54, listed chronologically.</p>
          <div class="doc-page-card-arrow">Browse settlements →</div>
        </a>

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
        <p><a href="contact.php">Contact Us</a></p>
      </div>
      <div>
        <h3>Navigate</h3>
        <ul class="footer-nav-list">
          
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li><a href="library.php">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li>
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
