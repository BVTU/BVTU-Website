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

        <a href="https://www.bctf.ca/topics/services-information/professional-development" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
          </div>
          <h3>BCTF PRO-D Resources</h3>
          <p>Provincial PRO-D policies, funding opportunities, and professional learning resources.</p>
          <div class="member-page-card-arrow">Visit BCTF →</div>
        </a>

        <a href="https://bvsd54.sharepoint.com/:u:/r/sites/SD54ProDCalendar/SitePages/Home.aspx?csf=1&web=1&e=noZNN1" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>PRO-D Day Schedule</h3>
          <p>District 54 Pro-D calendar on SharePoint. Requires your district login to access.</p>
          <div class="member-page-card-arrow">Open SD54 calendar →</div>
        </a>

      </div>

      <!-- OVERARCHING PRINCIPLES -->
      <style>
        .policy-section { margin-bottom: 3rem; }
        .policy-section h2 { font-size: 1.2rem; font-weight: 800; color: var(--primary); margin-bottom: .35rem; }
        .policy-section .policy-subtitle { font-size: .88rem; color: var(--gray-500); margin-bottom: 1.5rem; font-style: italic; }
        .policy-item { display: flex; gap: 1rem; margin-bottom: 1rem; }
        .policy-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--primary); flex-shrink: 0; margin-top: .55rem; }
        .policy-item p { font-size: .93rem; color: var(--gray-700); line-height: 1.65; margin: 0; }
        .policy-item p strong { color: var(--gray-800); }
        .policy-divider { border: none; border-top: 2px solid var(--accent); margin: 2rem 0; }
        .policy-appendix { background: #f8f9fa; border: 1px solid var(--border); border-radius: var(--radius); padding: 1.25rem 1.5rem; }
        .policy-appendix h3 { font-size: .95rem; font-weight: 800; color: var(--gray-700); margin: 0 0 .75rem; text-transform: uppercase; letter-spacing: .04em; font-size: .78rem; }
        .policy-appendix ul { padding-left: 1.25rem; margin: 0; }
        .policy-appendix ul li { font-size: .9rem; color: var(--gray-600); line-height: 1.6; margin-bottom: .3rem; }
      </style>

      <div class="policy-section">
        <h2>District 54 Pro-D Committee &mdash; Overarching Policy</h2>
        <p class="policy-subtitle">Ratified policy statement governing the BVTU Professional Development Committee</p>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>The BVTU Professional Development Committee shall consist of <strong>10 members</strong>: the elected PD representative from each school/job location (Silverthorne, Twain Sullivan, Houston Secondary, Telkwa Elementary, District Learning Centre, Muheim Elementary, Walnut Park Elementary, &amp; Smithers Secondary), plus the PD Treasurer and PD Chairperson.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>The committee&rsquo;s role is to look for opportunities to encourage and provide professional development for its members and to ensure <strong>equitable management of funds</strong>. This may include working with SD54, with the understanding that attendance is strictly by choice &mdash; it cannot be required.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>Each job location <strong>must submit a ratified School Professional Development Policy</strong> before the end of October each year. This plan must include the school&rsquo;s cycle, funds allocation, and which year in the cycle the school is at. Each cycle runs September through end of June. Summer approvals apply to the following cycle.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p><strong>Amount eligibility is based on percentage of full-time.</strong> A teacher in a 0.5 position is eligible for 0.5 of the funding. Teachers employed at multiple schools have funding split by percentage between locations.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p><strong>Honorarium:</strong> When a BVTU member presents a half-day or more workshop on a Pro-D day, a gift of no more than $50 (non-monetary) will be given in recognition of their contribution.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p><strong>Extra funds:</strong> Once a teacher has used their Pro-D allotment, there is no recourse to further funds from the BVTU Pro-D committee or school committees.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>Only <strong>pre-approved expenses listed on the PD approval form</strong> will be paid.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p><strong>Eligible coverage includes:</strong> Registration fees (excluding organization membership fees unless included in registration &mdash; confirmation of registration and amount paid required, reimbursed after the event); transportation including gas, flights, cancellation insurance, parking, taxi, and car rental (receipts required &mdash; but not mileage). A reimbursement of $150.00 per leave request may be claimed for use of personal Rewards Miles; taxes, insurance, and airport fees reimbursed at dollar value.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>Any requests for <strong>more than 2 TTOC days</strong> from the Pro-D allotment must be decided by the district Pro-D committee. TTOC days do not carry over to the following year.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>Schools will <strong>roll over their year-end balance</strong> (credit or deficit) from one year to the next.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>A <strong>brief write-up</strong> about the conference, speaker, or topic must be submitted with receipts to receive reimbursement. Teachers are encouraged to provide a brief report at the staff meeting following attendance at any conference for which Pro-D funds were used.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p><strong>University credit courses, programs, and courses required for job eligibility</strong> will not be funded due to tax implications. If a course increases employability or wage potential, it will not be funded. Summer conferences may be applied for based on availability of Pro-D funds.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>Pro-D funds are a <strong>shared pool</strong> among teachers on staff. Portions cannot be transferred between teachers or schools. TTOCs may apply to the District Committee for up to $250 in funding.</p>
        </div>

        <div class="policy-item">
          <div class="policy-dot"></div>
          <p>The committee will <strong>adjudicate any disputed requests</strong>. A legitimate request may be refused if a school&rsquo;s funds are low and the teacher has regularly accessed funds &mdash; this ensures equitable access for all staff.</p>
        </div>

        <hr class="policy-divider">

        <div class="policy-appendix">
          <h3>Appendix &mdash; Examples of Ineligible Expenses</h3>
          <ul>
            <li>Any university credit course or program</li>
            <li>Level B training (a specific course required for job eligibility)</li>
            <li>Certificate-level courses that increase employability</li>
            <li>Classroom materials, supplies, or lesson plans (e.g. TeachersPayTeachers)</li>
          </ul>
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
