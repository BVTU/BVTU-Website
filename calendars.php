<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>School Calendars — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="SD54 school calendars for 2025–26 and 2026–27 — instructional days, Pro-D days, and key dates.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .cal-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.75rem;
      margin-bottom: 2rem;
    }
    .cal-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,.05);
    }
    .cal-card-img {
      width: 100%;
      display: block;
      border-bottom: 1px solid var(--border);
    }
    .cal-card-body { padding: 1.1rem 1.25rem 1.25rem; }
    .cal-card-body h2 { font-size: 1.1rem; font-weight: 800; color: var(--primary); margin: 0 0 .3rem; }
    .cal-card-body p  { font-size: .88rem; color: var(--gray-500); margin: 0 0 1rem; }
    .cal-card-actions { display: flex; gap: .65rem; flex-wrap: wrap; }
    .cal-card-actions a {
      display: inline-flex; align-items: center; gap: .4rem;
      font-size: .85rem; font-weight: 700;
      padding: .45rem 1rem; border-radius: 7px;
      text-decoration: none;
    }
    .cal-btn-view {
      background: var(--accent); color: var(--primary);
      border: 1.5px solid #b8ddc5;
    }
    .cal-btn-view:hover { background: #d7eee2; }
    .cal-btn-dl {
      background: var(--primary); color: white;
      border: 1.5px solid transparent;
    }
    .cal-btn-dl:hover { background: #155a2a; }
    .cal-btn-dl svg, .cal-btn-view svg {
      width: 15px; height: 15px;
      stroke: currentColor; fill: none;
      stroke-width: 2.2; flex-shrink: 0;
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
      <h1>School Calendars</h1>
      <p>SD54 (Bulkley Valley) school year calendars — instructional days, Pro-D days, and key dates.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="cal-grid">

        <div class="cal-card">
          <a href="images/calendars/cal-2526.png" target="_blank">
            <img src="images/calendars/cal-2526-thumb.jpg" alt="SD54 2025–2026 School Calendar" class="cal-card-img">
          </a>
          <div class="cal-card-body">
            <h2>2025–2026 School Calendar</h2>
            <p>Days in Session: 188 &nbsp;·&nbsp; Days of Instruction: 181</p>
            <div class="cal-card-actions">
              <a href="images/calendars/cal-2526.png" target="_blank" class="cal-btn-view">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Full Calendar
              </a>
              <a href="documents/calendars/SD54-School-Calendar-2025-2026.pdf" download class="cal-btn-dl">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download PDF
              </a>
            </div>
          </div>
        </div>

        <div class="cal-card">
          <a href="images/calendars/cal-2627.png" target="_blank">
            <img src="images/calendars/cal-2627-thumb.jpg" alt="SD54 2026–2027 School Calendar" class="cal-card-img">
          </a>
          <div class="cal-card-body">
            <h2>2026–2027 School Calendar</h2>
            <p>Days in Session: 187 &nbsp;·&nbsp; Days of Instruction: 180</p>
            <div class="cal-card-actions">
              <a href="images/calendars/cal-2627.png" target="_blank" class="cal-btn-view">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Full Calendar
              </a>
              <a href="documents/calendars/SD54-School-Calendar-2026-2027.pdf" download class="cal-btn-dl">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download PDF
              </a>
            </div>
          </div>
        </div>

      </div>

      <p style="color:var(--gray-500);font-size:.88rem;">Calendars sourced from <a href="https://www.sd54.bc.ca" target="_blank" rel="noopener">sd54.bc.ca</a>. Click the thumbnail to view the full calendar, or download the PDF.</p>

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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li></ul></li>
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
