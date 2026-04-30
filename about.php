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
  <title>About — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="About the Bulkley Valley Teachers' Union — leadership, meetings, committees, and governance.">
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
          <li><a href="about.php" class="active">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
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
      <h1>About BVTU</h1>
      <p>The Bulkley Valley Teachers' Union represents educators across School District 54 in Houston, Telkwa, and Smithers.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- Leadership -->
      <div class="content-block">
        <h2>Executive Leadership</h2>
        <div class="people-grid">
          <div class="person-card">
            <h4>Cody Lind</h4>
            <p class="role">President</p>
            <p>3772-C 1st Ave, Smithers, BC V0J 2N0</p>
            <p><a href="contact.php">Contact Us</a></p>
          </div>
          <!-- Add additional executive members here -->
        </div>
      </div>

      <!-- Meetings -->
      <div class="content-block">
        <h2>Meetings</h2>
        <p>General meetings are held throughout the school year. Members are encouraged to attend and participate in union business.</p>
        <div class="info-box">
          <p>Meeting dates and agendas will be posted here and communicated directly to members. Check back or watch for email announcements.</p>
        </div>
        <!-- Add upcoming meeting dates as a list when available -->
      </div>

      <!-- Committees -->
      <div class="content-block">
        <h2>Committees</h2>
        <p>BVTU operates several committees that members can join to participate in union governance and advocacy.</p>
        <ul class="resource-list">
          <li><a href="#">Bargaining Committee</a></li>
          <li><a href="#">Health &amp; Safety Committee</a></li>
          <li><a href="#">PRO-D Committee</a></li>
          <li><a href="#">Status of Women Committee</a></li>
          <li><a href="#">Social Justice Committee</a></li>
        </ul>
      </div>

      <!-- Bargaining -->
      <div class="content-block">
        <h2>Bargaining</h2>
        <p>Collective bargaining is conducted at both the provincial level (through BCTF) and locally. Updates on bargaining will be posted here.</p>
        <div class="info-box">
          <p>For the latest provincial bargaining updates, visit the <a href="bctf.php">BCTF page</a> or go directly to <a href="https://bctf.ca" target="_blank" rel="noopener">bctf.ca</a>.</p>
        </div>
      </div>

      <!-- Governance -->
      <div class="content-block">
        <h2>Governance Documents</h2>
        <p>BVTU bylaws and constitutional documents govern how the local operates.</p>
        <div class="doc-list">
          <a href="mailto:lp54@bctf.ca?subject=BVTU Bylaws" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            BVTU Bylaws <span style="font-size:.8rem;color:var(--gray-500);">(request from president)</span>
          </a>
          <a href="mailto:lp54@bctf.ca?subject=BVTU Constitution" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            BVTU Constitution <span style="font-size:.8rem;color:var(--gray-500);">(request from president)</span>
          </a>
        </div>
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
