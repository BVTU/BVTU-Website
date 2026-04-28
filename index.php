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
  <title>Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Bulkley Valley Teachers' Union — Local of the BC Teachers' Federation, representing educators in Houston, Telkwa, and Smithers.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <!-- Header -->
  <header class="site-header hero-mode">
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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
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
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Hero -->
  <section class="hero">
    <h1>Supporting Educators<br>in the Bulkley Valley</h1>
    <p>Your local union representing teachers in Houston, Telkwa, and Smithers — SD54, Local of the BCTF.</p>
    <div style="display:flex;gap:.85rem;justify-content:center;flex-wrap:wrap;">
      <a href="members.php" class="btn btn-primary">Member Resources</a>
      <a href="about.php" class="btn btn-outline-white">About BVTU</a>
    </div>
  </section>

  <!-- Quick Access Cards -->
  <section class="cards-section">
    <div class="container">
      <h2 class="section-title">What are you looking for?</h2>
      <p class="section-sub">Resources, documents, and information for BVTU members.</p>
      <div class="cards">

        <a href="about.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h3>About BVTU</h3>
          <p>Leadership, meeting schedules, committees, and governance documents.</p>
          <span class="card-arrow">Learn more →</span>
        </a>

        <a href="documents.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          </div>
          <h3>Documents</h3>
          <p>Collective agreements, settlements, provincial regulations, and more.</p>
          <span class="card-arrow">View documents →</span>
        </a>

        <a href="members.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <h3>Members</h3>
          <p>Release time, salary grids, certification fees, grants, and TTOC resources.</p>
          <span class="card-arrow">Member info →</span>
        </a>

        <a href="prod.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
          </div>
          <h3>PRO-D</h3>
          <p>Professional development policies, committee info, and training opportunities.</p>
          <span class="card-arrow">PRO-D info →</span>
        </a>

        <a href="health-safety.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h3>Health &amp; Safety</h3>
          <p>Workplace safety committees, WorkSafe forms, and employee assistance programs.</p>
          <span class="card-arrow">H&amp;S resources →</span>
        </a>

        <a href="bctf.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          </div>
          <h3>BCTF</h3>
          <p>Provincial collective agreements, member discounts, and bargaining updates.</p>
          <span class="card-arrow">BCTF info →</span>
        </a>

      </div>
    </div>
  </section>

  <!-- Quick External Links -->
  <section class="quick-links">
    <div class="container">
      <h2>Quick Links</h2>
      <div class="quick-links-row">
        <a href="https://bctf.ca" target="_blank" rel="noopener">BCTF</a>
        <a href="https://www.sd54.bc.ca" target="_blank" rel="noopener">School District 54</a>
        <a href="https://www.teacherspension.ca" target="_blank" rel="noopener">Teachers' Pension Plan</a>
        <a href="https://www2.gov.bc.ca/gov/content/education-training/k-12" target="_blank" rel="noopener">Ministry of Education</a>
        <a href="https://www.bcteacherregulation.ca" target="_blank" rel="noopener">Teacher Certification</a>
        <a href="https://www.tqs.ca" target="_blank" rel="noopener">Teacher Qualification Services</a>
      </div>
    </div>
  </section>

  <!-- Footer -->
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
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
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
