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
            <a href="members.php" class="active">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
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
        <p>SD54 salary grids are sourced from the <a href="https://www.bctf.ca/topics/services-information/collective-agreements-and-salary/view-salary-grids" target="_blank" rel="noopener">BCTF salary grid database</a> and reflect the provincial collective agreement. Grids cover the full 2024–2028 contract period.</p>
        <style>
          .salary-grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-top: 1.25rem;
          }
          .salary-card {
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem 1rem 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: .5rem;
            transition: box-shadow .15s, border-color .15s;
          }
          .salary-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 16px rgba(27,107,66,.1);
          }
          .salary-card-year {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1.1;
          }
          .salary-card-sub {
            font-size: .78rem;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
          }
          .salary-card-icon {
            width: 38px;
            height: 38px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: .1rem;
          }
          .salary-card-icon svg {
            width: 20px;
            height: 20px;
            stroke: var(--primary);
            fill: none;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
          }
          .salary-card-actions {
            display: flex;
            gap: .4rem;
            margin-top: .4rem;
            flex-wrap: wrap;
            justify-content: center;
          }
          .salary-card-actions a {
            font-size: .8rem;
            padding: .3rem .7rem;
            border-radius: var(--radius-s);
            font-weight: 600;
            text-decoration: none;
            transition: background .15s, color .15s;
          }
          .salary-btn-view {
            background: var(--accent);
            color: var(--primary);
            border: 1.5px solid var(--primary);
          }
          .salary-btn-view:hover { background: var(--primary); color: white; }
          .salary-btn-dl {
            background: var(--primary);
            color: white;
            border: 1.5px solid var(--primary);
          }
          .salary-btn-dl:hover { background: var(--primary-dk); }
        </style>
        <div class="salary-grid-cards">

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2024–25</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2024.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2024.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2025–26</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2025.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2025.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2026–27</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2026.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2026.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2027–28</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2027.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2027.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2028–29</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2028.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2028.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

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
