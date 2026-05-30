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
  <title>Letters of Understanding — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="All signed Letters of Understanding, settlement agreements, and arbitration awards between BVTU and School District 54.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .lou-year-group { margin-bottom: 2.5rem; }
    .lou-year-heading {
      font-size: 1rem;
      font-weight: 700;
      color: var(--primary);
      border-bottom: 2px solid var(--primary);
      padding-bottom: .4rem;
      margin-bottom: 1rem;
    }
    .lou-list { display: flex; flex-direction: column; gap: .75rem; }
    .lou-card {
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      background: #fff;
      border: 1px solid var(--gray-200);
      border-radius: 10px;
      padding: 1rem 1.1rem;
      text-decoration: none;
      color: inherit;
      transition: border-color .15s, box-shadow .15s;
    }
    .lou-card:hover {
      border-color: var(--primary);
      box-shadow: 0 2px 8px rgba(26,107,53,.1);
    }
    .lou-icon {
      width: 40px; height: 40px;
      background: #f0f7f2;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: var(--primary);
    }
    .lou-icon svg { width: 20px; height: 20px; }
    .lou-card-body { flex: 1; min-width: 0; }
    .lou-card-title {
      font-size: .93rem;
      font-weight: 600;
      color: var(--gray-800);
      margin-bottom: .2rem;
      line-height: 1.4;
    }
    .lou-card-meta {
      font-size: .78rem;
      color: var(--gray-400);
      display: flex;
      flex-wrap: wrap;
      gap: .5rem;
      align-items: center;
    }
    .lou-type-badge {
      display: inline-block;
      font-size: .7rem;
      font-weight: 600;
      padding: .1rem .45rem;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: .03em;
    }
    .badge-lou { background: #e8f0fb; color: #1a3a7a; }
    .badge-settlement { background: #fff7ed; color: #7c3a00; }
    .badge-arbitration { background: #fef2f2; color: #991b1b; }
    .badge-guidelines { background: #f3f4f6; color: #374151; }
    .badge-proposal { background: #fdf4ff; color: #6b21a8; }
    .lou-articles {
      font-size: .75rem;
      color: var(--gray-500);
      font-family: monospace;
    }
    .ca-assistant-callout {
      background: linear-gradient(135deg, #1a6b35 0%, #155a2a 100%);
      border-radius: 12px;
      padding: 1.5rem 2rem;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 1.5rem;
      margin-bottom: 2.5rem;
    }
    .ca-assistant-callout svg {
      width: 48px; height: 48px;
      opacity: .9;
      flex-shrink: 0;
    }
    .ca-assistant-callout h3 { font-size: 1.1rem; margin: 0 0 .35rem; color: #fff; }
    .ca-assistant-callout p { margin: 0 0 .75rem; font-size: .9rem; opacity: .9; }
    .ca-assistant-callout .btn-white {
      background: #fff;
      color: var(--primary);
      padding: .5rem 1.1rem;
      border-radius: 6px;
      font-weight: 600;
      font-size: .88rem;
      text-decoration: none;
      display: inline-block;
      transition: opacity .15s;
    }
    .ca-assistant-callout .btn-white:hover { opacity: .9; }
    @media (max-width: 600px) {
      .ca-assistant-callout { flex-direction: column; gap: 1rem; text-align: center; }
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
          
          <li class="has-dropdown"><a href="documents.php" class="active">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php" class="active">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li></ul></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
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
      <h1>Letters of Understanding &amp; Settlements</h1>
      <p>All signed agreements between BVTU and School District 54, including settlement agreements and arbitration awards.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="ca-assistant-callout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z"/>
        </svg>
        <div>
          <h3>Questions about your contract?</h3>
          <p>The Contract Assistant can search these LOUs and the full collective agreement to give you plain-language answers.</p>
          <a href="ca-assistant.php" class="btn-white">Try the Contract Assistant →</a>
        </div>
      </div>

      <div class="content-block">
        <p>These documents, together with the <a href="collective-agreement.php">Local Collective Agreement</a>, form the complete contract between BVTU members and School District 54. Letters of Understanding, settlement agreements, and arbitration awards are all legally binding components of the contract.</p>
      </div>

      <!-- 2025 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2025</div>
        <div class="lou-list">
          <a href="documents/settlements/2025-2026-lous-signed.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2025-2026 LOUs — Thursday Early Dismissal · Temporary Small FTE Increases · Pro-D as Alternate Remedy</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-lou">LOU</span>
                <span>June 3, 2025 · Tanya Davidson &amp; Michael McDiarmid</span>
              </div>
            </div>
          </a>
          <a href="documents/settlements/article-c25-part-time-rights.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">Article C.25 Part-Time Teachers' Rights — Employer Proposal (January 2025)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-proposal">Proposal</span>
                <span class="lou-articles">Art. C.25</span>
                <span>Employer proposal for bargaining — not yet agreed</span>
              </div>
            </div>
          </a>
          <a href="documents/settlements/article-d22-staff-meetings.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">Article D.22 Staff Meetings — Current Text &amp; Employer Proposal (January 2025)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-proposal">Proposal</span>
                <span class="lou-articles">Art. D.22</span>
                <span>Employer proposal for bargaining — not yet agreed</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2024 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2024</div>
        <div class="lou-list">
          <a href="documents/settlements/2024-lab-shop-class-size.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2024 Settlement — Labs &amp; Shops Class Size (Science, Culinary, Woodwork, Metalwork, Automotive)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-settlement">Settlement</span>
                <span class="lou-articles">Art. D.1.1</span>
                <span>May 2024 · Arbitrator Marguerite Jackson, KC</span>
              </div>
            </div>
          </a>
          <a href="documents/settlements/2024-d4-5-elem-prep.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2024 LOA — Elementary Preparation Time Scheduling (Renewal)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-lou">LOA</span>
                <span class="lou-articles">Art. D.4.5, D.4.7</span>
                <span>May 14, 2024 · Tanya Davidson &amp; Michael McDiarmid</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2023 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2023</div>
        <div class="lou-list">
          <a href="documents/settlements/2023-continuing-to-continuing.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2023 Arbitration Award — Post &amp; Fill Grievance (Continuing-to-Continuing)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-arbitration">Arbitration</span>
                <span class="lou-articles">Art. E.22.1</span>
                <span>May 9, 2023 · Arbitrator Julie Nichols</span>
              </div>
            </div>
          </a>
          <a href="documents/settlements/2023-calendar-settlement.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2023 Settlement — School Calendar Grievance (Pro-D Day Consultation)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-settlement">Settlement</span>
                <span class="lou-articles">Art. D.28.6, F.22.1</span>
                <span>November 20, 2023 · Arbitrator Cathy Knapp</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2018 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2018</div>
        <div class="lou-list">
          <a href="documents/settlements/2018-jackson-remedy-rollover.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2018 Preliminary Award — Class Size/Composition Remedy (Jackson Remedy Rollover)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-arbitration">Arbitration</span>
                <span class="lou-articles">Art. D.1</span>
                <span>September 17, 2018 · BCTF Grievance 99-2018-0002</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2017 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2017</div>
        <div class="lou-list">
          <a href="documents/settlements/2017-ttoc-temp-contract-sick-days.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2017 Arbitration Award — TTOC &amp; Temporary Contract Sick Days (Carryover)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-arbitration">Arbitration</span>
                <span class="lou-articles">Art. C.26, C.27, G.22</span>
                <span>June 28, 2017 · Arbitrator Christopher Sullivan</span>
              </div>
            </div>
          </a>
          <a href="documents/settlements/2017-2018-cupe-emergency-replacements.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2017-2018 Guidelines — CUPE Emergency Response Replacements for Teacher Absences</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-guidelines">Guidelines</span>
                <span>SEA/EA emergency replacement as non-certified TOC</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2015 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2015</div>
        <div class="lou-list">
          <a href="documents/settlements/2015-part-time-pro-d.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2015 Settlement — Part-Time Teachers and Pro-D Day Attendance</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-settlement">Settlement</span>
                <span class="lou-articles">Art. F.22, B.22.3</span>
                <span>February 17, 2015 · Ilona Weiss &amp; Chris van der Mark</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2014 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2014</div>
        <div class="lou-list">
          <a href="documents/settlements/2014-ttoc-pay-settlement.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2014 Settlement — TTOC Pay Grievance (Block Pay Rules)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-settlement">Settlement</span>
                <span class="lou-articles">Art. B.2.7, C.27</span>
                <span>January 21, 2014 · Arbitrator Chris Sullivan</span>
              </div>
            </div>
          </a>
          <a href="documents/settlements/2014-ttoc-continuation-settlement.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2014 LOA — Continuation of TTOC Pay Settlement (from September 2015)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-lou">LOA</span>
                <span class="lou-articles">Art. B.2.7, C.27</span>
                <span>June 27, 2014 · Karin Bachman &amp; Chris van der Mark</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2013 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2013</div>
        <div class="lou-list">
          <a href="documents/settlements/2013-elem-preptime.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2013 LOA — Elementary Preparation Time Scheduling (Original)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-lou">LOA</span>
                <span class="lou-articles">Art. D.4.5, D.4.7</span>
                <span>February 25, 2013 · Karin Bachman &amp; Chris van der Mark</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2010 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2010</div>
        <div class="lou-list">
          <a href="documents/settlements/2010-sec-preptime.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2010 Letter &amp; LOU — Secondary Preparation Time &amp; Personal Leave (retro to Sept 2005)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-lou">LOU</span>
                <span class="lou-articles">Art. D.4, D.4.7</span>
                <span>April 28, 2010 / May 16, 2006 · Superintendent Beverly Young</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2004 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2004</div>
        <div class="lou-list">
          <a href="documents/settlements/2004-early-retirement-incentive.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2004 LOU — Workforce Adjustment Program (Early Retirement Incentive) <span style="font-size:.75rem;color:var(--gray-400);">— Expired</span></div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-lou">LOU</span>
                <span>May 7, 2004 · Expired June 30, 2004 · Historical reference</span>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- 2001 -->
      <div class="lou-year-group">
        <div class="lou-year-heading">2001</div>
        <div class="lou-list">
          <a href="documents/settlements/2001-dorsey-award.pdf" class="lou-card" target="_blank" rel="noopener">
            <div class="lou-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            <div class="lou-card-body">
              <div class="lou-card-title">2001 Dorsey Arbitration Award — Seniority List (Tiebreakers, TOC Days, Start Dates)</div>
              <div class="lou-card-meta">
                <span class="lou-type-badge badge-arbitration">Arbitration</span>
                <span class="lou-articles">Art. C-7.2, C-7.3</span>
                <span>June 5, 2001 · Arbitrator James E. Dorsey, Q.C.</span>
              </div>
            </div>
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
          
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
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
