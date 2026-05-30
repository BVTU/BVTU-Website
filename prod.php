<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>PRO-D — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU professional development — policies, committee information, and training opportunities.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
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
          
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li></ul></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php" class="active">PRO-D</a></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
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
      <h1>Professional Development</h1>
      <p>Policies, funding opportunities, and resources to support your professional growth.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- QUICK-ACCESS CARDS -->
      <div class="member-page-grid">
        <style>
          .member-page-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
          .member-page-card { display: flex; flex-direction: column; background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 1.4rem 1.25rem 1.2rem; text-decoration: none; color: var(--text); transition: border-color .15s, box-shadow .15s, transform .12s; }
          .member-page-card:hover { border-color: var(--primary); box-shadow: 0 4px 18px rgba(27,107,66,.1); transform: translateY(-2px); color: var(--text); }
          .member-page-card-icon { width: 40px; height: 40px; background: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: .9rem; flex-shrink: 0; }
          .member-page-card-icon svg { width: 20px; height: 20px; stroke: var(--primary); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
          .member-page-card h3 { font-size: 1rem; font-weight: 800; color: var(--primary); margin: 0 0 .35rem; }
          .member-page-card p { font-size: .85rem; color: var(--gray-500); margin: 0; line-height: 1.55; flex: 1; }
          .member-page-card-arrow { font-size: .82rem; font-weight: 700; color: var(--primary); margin-top: .9rem; }
        </style>

        <a href="members/prod-dashboard.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          </div>
          <h3>Pro-D Portal</h3>
          <p>Apply for Pro-D funding, track your applications, and manage professional development records.</p>
          <div class="member-page-card-arrow">Open portal →</div>
        </a>

        <a href="collab-grant.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h3>Collaboration Grant</h3>
          <p>Apply for BVTU grants supporting teacher-led collaborative professional development projects.</p>
          <div class="member-page-card-arrow">Apply now →</div>
        </a>

        <a href="https://bctf.ca/pro-d" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
          </div>
          <h3>BCTF PRO-D Resources</h3>
          <p>Provincial PRO-D policies, funding opportunities, and professional learning resources.</p>
          <div class="member-page-card-arrow">Visit BCTF →</div>
        </a>

        <a href="https://www.sd54.bc.ca" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>PRO-D Day Schedule</h3>
          <p>Upcoming PRO-D days and school calendar information from SD54.</p>
          <div class="member-page-card-arrow">View SD54 site →</div>
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
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
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
