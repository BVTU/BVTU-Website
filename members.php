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
  <title>Members — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU member resources — release time, salary grids, certification fees, grants, and TTOC information.">
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
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav">
        <ul>
          <li><a href="about.php">About</a></li>
          <li><a href="documents.php">Documents</a></li>
<li class="has-dropdown">
            <a href="members.php" class="active">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li>
            <button class="search-btn" data-search-open aria-label="Search">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
          </li>
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
      <h1>Member Resources</h1>
      <p>Everything you need as a BVTU member — release time, salary info, grants, and TTOC resources.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- Release Time -->
      <div class="content-block">
        <h2>Union-Paid Release Time</h2>
        <p>BVTU members may be entitled to union-paid release time for union business, including committee work, bargaining, and BCTF events. Contact the president to request release time or learn about your entitlements.</p>
        <div class="doc-list">
          <a href="documents/release-time-policy.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Release Time Policy
          </a>
          <a href="documents/release-time-request-form.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Release Time Request Form
          </a>
        </div>
      </div>

      <!-- Salary Information -->
      <div class="content-block">
        <h2>Salary Information</h2>
        <p>Salary grids are negotiated through the provincial collective agreement. Current grids and allowances are listed below.</p>
        <div class="doc-list">
          <a href="documents/salary-schedule.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Current Salary Schedule
          </a>
          <a href="documents/allowances.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Allowances &amp; Benefits
          </a>
        </div>
      </div>

      <!-- Certification Fees -->
      <div class="content-block">
        <h2>Certification Fees</h2>
        <p>BVTU reimburses members for Teacher Regulation Branch (TRB) certification fees. Submit your receipt to the president for reimbursement.</p>
        <div class="info-box">
          <p>Keep your TRB receipt and submit it to the union president. Reimbursement is available once per renewal cycle.</p>
        </div>
        <div class="doc-list">
          <a href="documents/certification-reimbursement-form.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Certification Fee Reimbursement Form
          </a>
        </div>
      </div>

      <!-- Collaboration Grants -->
      <div class="content-block">
        <h2>Collaboration Grants</h2>
        <p>Grants are available to support collaborative teacher projects and initiatives. Applications are reviewed by the PRO-D committee.</p>
        <div class="doc-list">
          <a href="documents/collaboration-grant-application.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Collaboration Grant Application
          </a>
          <a href="documents/collaboration-grant-guidelines.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Grant Guidelines
          </a>
        </div>
      </div>

      <!-- TTOC Resources -->
      <div class="content-block">
        <h2>TTOC Resources</h2>
        <p>Teacher-on-Call (TTOC / Substitute) members have specific rights and entitlements under the collective agreement. Key resources are listed below.</p>
        <div class="doc-list">
          <a href="documents/ttoc-information.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            TTOC Information Guide
          </a>
          <a href="documents/ttoc-collective-agreement-provisions.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            TTOC Collective Agreement Provisions
          </a>
        </div>
        <ul class="resource-list" style="margin-top: 1rem;">
          <li><a href="https://bctf.ca/your-career/ttoc" target="_blank" rel="noopener">BCTF TTOC Resources</a></li>
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
