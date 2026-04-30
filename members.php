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
      <h1>Member Resources</h1>
      <p>Everything you need as a BVTU member — release time, salary info, grants, and TTOC resources.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- Quick-access page cards -->
      <style>
        .member-page-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
          gap: 1rem;
          margin-bottom: 2.5rem;
        }
        .member-page-card {
          display: flex;
          flex-direction: column;
          background: var(--white);
          border: 1.5px solid var(--border);
          border-radius: var(--radius);
          padding: 1.4rem 1.25rem 1.2rem;
          text-decoration: none;
          color: var(--text);
          transition: border-color .15s, box-shadow .15s, transform .12s;
        }
        .member-page-card:hover {
          border-color: var(--primary);
          box-shadow: 0 4px 18px rgba(27,107,66,.1);
          transform: translateY(-2px);
          color: var(--text);
        }
        .member-page-card-icon {
          width: 40px; height: 40px;
          background: var(--accent);
          border-radius: 10px;
          display: flex; align-items: center; justify-content: center;
          margin-bottom: .9rem;
          flex-shrink: 0;
        }
        .member-page-card-icon svg {
          width: 20px; height: 20px;
          stroke: var(--primary); fill: none;
          stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }
        .member-page-card h3 {
          font-size: 1rem; font-weight: 800; color: var(--primary);
          margin: 0 0 .35rem;
        }
        .member-page-card p {
          font-size: .85rem; color: var(--gray-500); margin: 0;
          line-height: 1.55; flex: 1;
        }
        .member-page-card-arrow {
          font-size: .82rem; font-weight: 700; color: var(--primary);
          margin-top: .9rem;
        }
      </style>

      <div class="member-page-grid">

        <a href="benefits.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h3>Health &amp; Dental Benefits</h3>
          <p>Pacific Blue Cross coverage — dental, extended health, paramedical, vision, prescription drugs, and how to claim.</p>
          <div class="member-page-card-arrow">View benefits guide →</div>
        </a>

        <a href="life-insurance.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <h3>Life Insurance</h3>
          <p>Plan EB coverage — 3× salary up to $300,000, beneficiary designation, disability waiver, and conversion rights.</p>
          <div class="member-page-card-arrow">View coverage details →</div>
        </a>

        <a href="loan-forgiveness.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <h3>Student Loan Forgiveness</h3>
          <p>Up to $30,000 forgiven over 5 years — Houston, Telkwa, and Smithers postal codes all qualify. Learn how to apply.</p>
          <div class="member-page-card-arrow">See if you qualify →</div>
        </a>

        <a href="ttoc.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h3>TTOC Resources</h3>
          <p>Call-out rates, EI hours calculator, experience credit, sick leave entitlements, pro-D access, and benefits for TTOCs.</p>
          <div class="member-page-card-arrow">View TTOC guide →</div>
        </a>

        <a href="atrieve.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>Release Time &amp; Atrieve</h3>
          <p>How to enter union-paid release time in Atrieve — approval process, BCTF and BVTU event codes, and leave entry instructions.</p>
          <div class="member-page-card-arrow">View Atrieve guide →</div>
        </a>

        <a href="remedy-tracker.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <h3>Remedy Tracker</h3>
          <p>Track outstanding remedies and settlements owed to BVTU members under the collective agreement.</p>
          <div class="member-page-card-arrow">Open tracker →</div>
        </a>

        <a href="collab-grant.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h3>Collaboration Grant</h3>
          <p>Apply for BVTU collaboration grants supporting teacher-led professional development projects.</p>
          <div class="member-page-card-arrow">Apply now →</div>
        </a>

      </div>

      <!-- Release Time -->
      <div class="content-block">
        <h2>Union-Paid Release Time</h2>
        <p>BVTU members may be entitled to union-paid release time for union business, including committee work, bargaining, and BCTF events. Contact the president to request release time or learn about your entitlements.</p>
        <p style="margin-top:.5rem;"><a href="atrieve.php">See the Atrieve Release Time guide →</a> for step-by-step instructions on entering union-paid release time and a full list of qualifying events. Contact <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a> to request approval.</p>
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
        <p style="margin-top:.5rem;">Email your receipt to <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a> to request reimbursement. A reimbursement form is available from the president.</p>
      </div>

      <!-- Collaboration Grants -->
      <div class="content-block">
        <h2>Collaboration Grants</h2>
        <p>Grants are available to support collaborative teacher projects and initiatives. Applications are reviewed by the PRO-D committee.</p>
        <div class="doc-list">
          <a href="collab-grant.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Collaboration Grant — Application &amp; Guidelines
          </a>
        </div>
      </div>

      <!-- TTOC Resources -->
      <div class="content-block">
        <h2>TTOC Resources</h2>
        <p>Teacher-on-Call (TTOC / Substitute) members have specific rights and entitlements under the collective agreement. Key resources are listed below.</p>
        <div class="doc-list">
          <a href="ttoc.php" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            TTOC Resources Guide
          </a>
          <a href="documents/ttoc-experience-credit.pdf" class="doc-item" download>
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Understanding TTOC Experience Credit (PDF)
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
