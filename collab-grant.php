<?php
/**
 * collab-grant.php — BVTU Collaboration Grant Application
 * Embeds the Microsoft Forms application; responses populate the BVTU Excel sheet.
 */
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
  <title>Collaboration Grant — BVTU</title>
  <meta name="description" content="Apply for the BVTU Collaboration Grant. Supporting professional learning and collaboration for educators in School District 54.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .grant-layout {
      display: grid;
      grid-template-columns: 1fr 300px;
      gap: 3rem;
      align-items: start;
    }
    .grant-form-wrap {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-l);
      overflow: hidden;
      box-shadow: var(--shadow);
    }
    .grant-form-wrap iframe {
      display: block;
      width: 100%;
      height: 820px;
      border: none;
    }
    .grant-sidebar {
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }
    .grant-info-card {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.5rem 1.75rem;
    }
    .grant-info-card h3 {
      font-size: .95rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: .9rem;
    }
    .grant-info-card p,
    .grant-info-card li {
      font-size: .88rem;
      color: var(--gray-700);
      line-height: 1.65;
    }
    .grant-info-card ul {
      padding-left: 1.1rem;
      margin-top: .4rem;
    }
    .grant-info-card li { margin-bottom: .35rem; }
    .grant-tip {
      background: var(--accent);
      border-left: 4px solid var(--primary);
      border-radius: 0 var(--radius-s) var(--radius-s) 0;
      padding: .85rem 1rem;
      font-size: .87rem;
      color: var(--gray-700);
      line-height: 1.6;
    }
    @media (max-width: 860px) {
      .grant-layout {
        grid-template-columns: 1fr;
      }
      .grant-sidebar { order: -1; }
      .grant-form-wrap iframe { height: 700px; }
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
              <li><a href="collective-agreement.php">Collective Agreement</a></li>
            </ul>
          </li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php" class="active">Collaboration Grant</a></li>
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
      <h1>Collaboration Grant</h1>
      <p>Supporting professional learning and collaboration for BVTU members in SD54.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">
      <div class="grant-layout">

        <!-- Embedded MS Form -->
        <div class="grant-form-wrap">
          <iframe
            src="https://forms.office.com/Pages/ResponsePage.aspx?id=DQSIkWdsW0yxEjajBLZtrQAAAAAAAAAAAANAAV60Yf9UNjBVWU5KTTBGTjYwMVBQVkg2TVQxSzkzNS4u&embed=true"
            title="BVTU Collaboration Grant Application"
            allowfullscreen
            webkitallowfullscreen
            mozallowfullscreen
            msallowfullscreen>
          </iframe>
        </div>

        <!-- Sidebar -->
        <div class="grant-sidebar">

          <div class="grant-info-card">
            <h3>About This Grant</h3>
            <p>The BVTU Collaboration Grant supports members who want to engage in meaningful professional learning with colleagues. Funded by your local union.</p>
          </div>

          <div class="grant-info-card">
            <h3>Before You Apply</h3>
            <ul>
              <li>You must be a BVTU member in good standing</li>
              <li>Collaboration must involve at least two members</li>
              <li>Activities should align with professional growth goals</li>
              <li>Keep receipts — reimbursement requires documentation</li>
            </ul>
          </div>

          <div class="grant-tip">
            <strong>Questions?</strong> Contact the BVTU office or reach out to your PRO-D rep before submitting. We're happy to help you put together a strong application.
            <br><br>
            <a href="contact.php" style="color:var(--primary);font-weight:600;">Contact BVTU →</a>
          </div>

        </div>

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
          <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
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
