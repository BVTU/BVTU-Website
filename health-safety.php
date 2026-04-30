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
  <title>Health &amp; Safety — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU health and safety resources — workplace committees, WorkSafe forms, employee assistance, and mental health support.">
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
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php" class="active">Health &amp; Safety</a></li>
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
      <h1>Health &amp; Safety</h1>
      <p>Your workplace safety matters. Resources, forms, and support for BVTU members.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="content-block">
        <h2>Workplace Safety Committee</h2>
        <p>The joint Occupational Health &amp; Safety committee works with School District 54 to identify and address workplace hazards. Members can raise safety concerns through their school rep or the union president.</p>
        <div class="info-box">
          <p>Have a safety concern? Contact the union president or your school's OH&amp;S representative. All concerns are taken seriously and followed up promptly.</p>
        </div>
      </div>

      <div class="content-block">
        <h2>Violence in the Workplace</h2>
        <p>Teachers are entitled to a safe working environment. If you experience or witness violence in the workplace, you have the right to report it and receive support.</p>
        <div class="doc-list">
          <a href="documents/violence-incident-report.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Violence Incident Report Form
          </a>
          <a href="documents/violence-prevention-policy.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Violence Prevention Policy (SD54)
          </a>
        </div>
      </div>

      <div class="content-block">
        <h2>WorkSafe BC</h2>
        <p>Work-related injuries must be reported to WorkSafe BC. Forms and guidance are available below. Contact the union if you need support navigating a WorkSafe claim.</p>
        <div class="doc-list">
          <a href="documents/worksafe-claim-form.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            WorkSafe Claim Form (Worker)
          </a>
          <a href="documents/worksafe-injury-guide.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Guide to WorkSafe Claims for Teachers
          </a>
        </div>
        <ul class="resource-list" style="margin-top:1rem;">
          <li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC Website</a></li>
        </ul>
      </div>

      <div class="content-block">
        <h2>Employee &amp; Family Assistance Program</h2>
        <p>The Employee and Family Assistance Program (EFAP) provides free, confidential counselling and support services for teachers and their immediate family members.</p>
        <div class="info-box">
          <p>EFAP services are available 24/7. Access is confidential — your employer is not informed. Contact details are in your benefits package or ask the union president.</p>
        </div>
        <div class="doc-list">
          <a href="documents/efap-guide.pdf" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            EFAP Guide for Employees
          </a>
        </div>
      </div>

      <div class="content-block">
        <h2>Mental Health Resources</h2>
        <p>Teaching is demanding work. BVTU is committed to supporting member mental health and well-being.</p>
        <ul class="resource-list">
          <li><a href="https://bctf.ca/member-services/health-and-wellness" target="_blank" rel="noopener">BCTF Health &amp; Wellness Resources</a></li>
          <li><a href="https://www.crisiscentre.bc.ca" target="_blank" rel="noopener">BC Crisis Centre (1-800-SUICIDE)</a></li>
          <li><a href="https://www.bouncebackbc.ca" target="_blank" rel="noopener">BounceBack BC — Free Mental Health Program</a></li>
          <li><a href="https://here2talk.ca" target="_blank" rel="noopener">Here2Talk — Counselling for Educators</a></li>
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
