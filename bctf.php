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
  <title>BCTF — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BC Teachers' Federation resources — provincial agreements, member discounts, and bargaining updates.">
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
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php" class="active">BCTF</a></li>
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
      <h1>BC Teachers' Federation</h1>
      <p>BVTU is a proud local of the BCTF — the provincial union representing all BC public school teachers.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="content-block">
        <h2>About the BCTF</h2>
        <p>The BC Teachers' Federation is the union representing over 48,000 public school teachers in British Columbia. BVTU operates as a local within the BCTF, and all BVTU members are automatically BCTF members.</p>
        <ul class="resource-list">
          <li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li>
          <li><a href="https://bctf.ca/about-bctf" target="_blank" rel="noopener">About the BCTF</a></li>
        </ul>
      </div>

      <div class="content-block">
        <h2>Provincial Collective Agreement</h2>
        <p>The provincial collective agreement sets out the terms and conditions of employment for all BC public school teachers. It is negotiated between the BCTF and the BC Public School Employers' Association (BCPSEA).</p>
        <div class="doc-list">
          <a href="documents/provincial-collective-agreement.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Provincial Collective Agreement (Current)
          </a>
        </div>
        <ul class="resource-list" style="margin-top:1rem;">
          <li><a href="https://bctf.ca/your-collective-agreement" target="_blank" rel="noopener">BCTF — Your Collective Agreement</a></li>
        </ul>
      </div>

      <div class="content-block">
        <h2>Bargaining Updates</h2>
        <p>Bargaining between the BCTF and BCPSEA takes place on a regular cycle. Stay informed on the latest updates through the BCTF website.</p>
        <div class="info-box">
          <p>For the most current bargaining news, visit <a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">bctf.ca/bargaining</a> or watch for communications from BVTU.</p>
        </div>
      </div>

      <div class="content-block">
        <h2>Member Discount Programs</h2>
        <p>BCTF members have access to a wide range of discounts on insurance, travel, and services through the BCTF Member Benefits program.</p>
        <ul class="resource-list">
          <li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">BCTF Member Benefits &amp; Services</a></li>
          <li><a href="https://bctf.ca/member-services/insurance" target="_blank" rel="noopener">BCTF Insurance Programs</a></li>
          <li><a href="https://bctf.ca/member-services/travel" target="_blank" rel="noopener">BCTF Travel Discounts</a></li>
        </ul>
      </div>

      <div class="content-block">
        <h2>Useful BCTF Links</h2>
        <ul class="resource-list">
          <li><a href="https://bctf.ca/your-career" target="_blank" rel="noopener">Your Career — New Teacher Resources</a></li>
          <li><a href="https://bctf.ca/social-justice" target="_blank" rel="noopener">Social Justice &amp; Equity</a></li>
          <li><a href="https://bctf.ca/pro-d" target="_blank" rel="noopener">Professional Development</a></li>
          <li><a href="https://bctf.ca/news" target="_blank" rel="noopener">BCTF News</a></li>
          <li><a href="https://bctf.ca/contact" target="_blank" rel="noopener">Contact the BCTF</a></li>
        </ul>
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
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
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
