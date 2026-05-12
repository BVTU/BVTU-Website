<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Documents — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU documents — collective agreements, settlements, provincial regulations, ethics codes, and professional standards.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .members-locked {
      background: var(--off-white);
      border: 2px dashed var(--border);
      border-radius: var(--radius);
      padding: 2.5rem;
      text-align: center;
      margin-top: 1rem;
    }
    .members-locked .lock-icon {
      width: 48px;
      height: 48px;
      background: var(--accent);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      color: var(--blue);
    }
    .members-locked .lock-icon svg { width: 24px; height: 24px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .members-locked h3 { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: .5rem; }
    .members-locked p { color: var(--gray-500); font-size: .92rem; margin-bottom: 1.25rem; }
    .members-section-title {
      display: flex;
      align-items: center;
      gap: .65rem;
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 1rem;
      padding-bottom: .6rem;
      border-bottom: 2px solid var(--accent);
    }
    .members-badge {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      background: var(--primary);
      color: white;
      padding: .2rem .55rem;
      border-radius: 100px;
    }
    .section-divider {
      border: none;
      border-top: 2px solid var(--gray-200);
      margin: 3rem 0;
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
          
          <li class="has-dropdown active">
            <a href="documents.php" class="active">Documents</a>
            <ul class="dropdown">
              <li><a href="documents.php">All Documents</a></li>
              <li><a href="collective-agreement.php">Collective Agreement</a></li>
              <li><a href="lous.php">Letters of Understanding</a></li>
              <li><a href="ca-assistant.php">Contract Assistant</a></li>
              <li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li>
            </ul>
          </li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li><a href="library.php">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="<?= $loggedIn ? 'members/dashboard.php' : 'members/login.php' ?>"
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
      <h1>Documents</h1>
      <p>Collective agreements, regulations, and professional standards — plus members-only resources below.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- QUICK-ACCESS CARDS -->
      <style>
        .doc-page-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 1rem;
          margin-bottom: 2.5rem;
        }
        .doc-page-card {
          display: flex;
          flex-direction: column;
          background: var(--white);
          border: 1.5px solid var(--border);
          border-radius: var(--radius);
          padding: 1.3rem 1.15rem 1.1rem;
          text-decoration: none;
          color: var(--text);
          transition: border-color .15s, box-shadow .15s, transform .12s;
        }
        .doc-page-card:hover {
          border-color: var(--primary);
          box-shadow: 0 4px 18px rgba(27,107,66,.1);
          transform: translateY(-2px);
          color: var(--text);
        }
        .doc-page-card-icon {
          width: 40px; height: 40px;
          background: var(--accent);
          border-radius: 10px;
          display: flex; align-items: center; justify-content: center;
          margin-bottom: .85rem;
          flex-shrink: 0;
        }
        .doc-page-card-icon svg {
          width: 20px; height: 20px;
          stroke: var(--primary); fill: none;
          stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }
        .doc-page-card h3 { font-size: .95rem; font-weight: 800; color: var(--primary); margin: 0 0 .3rem; }
        .doc-page-card p  { font-size: .82rem; color: var(--gray-500); margin: 0; line-height: 1.5; flex: 1; }
        .doc-page-card-arrow { font-size: .8rem; font-weight: 700; color: var(--primary); margin-top: .85rem; }

        /* Calendar cards */
        .cal-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
          gap: 1.5rem;
          margin-bottom: 2rem;
        }
        .cal-card {
          background: var(--white);
          border: 1.5px solid var(--border);
          border-radius: var(--radius);
          overflow: hidden;
        }
        .cal-card-img {
          width: 100%;
          display: block;
          border-bottom: 1px solid var(--border);
        }
        .cal-card-body { padding: 1rem 1.1rem 1.1rem; }
        .cal-card-body h3 { font-size: 1rem; font-weight: 800; color: var(--primary); margin: 0 0 .35rem; }
        .cal-card-body p  { font-size: .85rem; color: var(--gray-500); margin: 0 0 .9rem; }
        .cal-card-actions { display: flex; gap: .6rem; flex-wrap: wrap; }
        .cal-card-actions a {
          display: inline-flex; align-items: center; gap: .35rem;
          font-size: .82rem; font-weight: 700;
          padding: .4rem .85rem; border-radius: 6px;
          text-decoration: none;
        }
        .cal-btn-view {
          background: var(--accent); color: var(--primary);
          border: 1.5px solid var(--primary-light, #b8ddc5);
        }
        .cal-btn-view:hover { background: #d7eee2; }
        .cal-btn-dl {
          background: var(--primary); color: white;
          border: 1.5px solid transparent;
        }
        .cal-btn-dl:hover { background: #155a2a; }
        .cal-btn-dl svg, .cal-btn-view svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.2; flex-shrink: 0; }
      </style>

      <div class="doc-page-grid">

        <a href="collective-agreement.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <h3>Collective Agreement</h3>
          <p>Local and provincial CA — your rights, entitlements, and working conditions.</p>
          <div class="doc-page-card-arrow">Read the CA →</div>
        </a>

        <a href="lous.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </div>
          <h3>Letters of Understanding</h3>
          <p>All signed LOUs, settlements, and arbitration awards between BVTU and SD54.</p>
          <div class="doc-page-card-arrow">Browse LOUs →</div>
        </a>

        <a href="ca-assistant.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z"/></svg>
          </div>
          <h3>Contract Assistant</h3>
          <p>Ask plain-language questions about the CA and get instant answers.</p>
          <div class="doc-page-card-arrow">Ask a question →</div>
        </a>

        <a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank" rel="noopener" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
          </div>
          <h3>Constitution &amp; Bylaws</h3>
          <p>BVTU Constitution and Bylaws, approved May 2026.</p>
          <div class="doc-page-card-arrow">Open PDF →</div>
        </a>

        <a href="#calendars" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>School Calendars</h3>
          <p>SD54 school calendars for 2025–26 and 2026–27 — view and download.</p>
          <div class="doc-page-card-arrow">View calendars →</div>
        </a>

        <a href="#settlements" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <h3>Settlements</h3>
          <p>Local settlements and remedies reached between BVTU and SD54, listed chronologically.</p>
          <div class="doc-page-card-arrow">Browse settlements →</div>
        </a>

      </div>

      <!-- PUBLIC DOCUMENTS — visible to everyone -->
      <div class="doc-categories">

        <div class="doc-category">
          <h3>Collective Agreements</h3>
          <div class="doc-list">
            <a href="https://bctf.ca/bargaining/provincial-collective-agreement" class="doc-item" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Provincial Collective Agreement
            </a>
            <a href="collective-agreement.php" class="doc-item">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Local Collective Agreement (SD54–BVTU)
            </a>
          </div>
        </div>

        <div class="doc-category">
          <h3>Provincial Regulations</h3>
          <div class="doc-list">
            <a href="https://www.bclaws.gov.bc.ca/civix/document/id/complete/statreg/96412_00" class="doc-item" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              School Act
            </a>
            <a href="https://www.bclaws.gov.bc.ca/civix/document/id/complete/statreg/11019_00" class="doc-item" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Teacher Regulation Branch Act
            </a>
          </div>
        </div>

        <div class="doc-category">
          <h3>Ethics &amp; Professional Standards</h3>
          <div class="doc-list">
            <a href="https://www.bctf.ca/topics/member-services/code-of-ethics" class="doc-item" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              BCTF Code of Ethics
            </a>
            <a href="https://www.bcteacherregulation.ca" class="doc-item" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Standards for Educators (TRB)
            </a>
          </div>
        </div>

        <div class="doc-category">
          <h3>BVTU Governance</h3>
          <div class="doc-list">
            <a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" class="doc-item" target="_blank" rel="noopener">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              BVTU Constitution &amp; Bylaws <span style="font-size:.8rem;color:var(--gray-500);">Approved May 2026</span>
            </a>
          </div>
        </div>

      </div>

      <!-- CONTRACT ASSISTANT CALLOUT -->
      <hr class="section-divider">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:2rem;">
        <a href="ca-assistant.php" style="display:flex;align-items:flex-start;gap:1rem;background:linear-gradient(135deg,#1a6b35,#155a2a);border-radius:10px;padding:1.25rem 1.4rem;text-decoration:none;color:#fff;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px;flex-shrink:0;opacity:.9"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z"/></svg>
          <div>
            <div style="font-weight:700;font-size:.95rem;margin-bottom:.25rem;">Contract Assistant</div>
            <div style="font-size:.82rem;opacity:.85;line-height:1.5;">Ask questions about the CA and LOUs in plain language — get answers instantly.</div>
          </div>
        </a>
        <a href="lous.php" style="display:flex;align-items:flex-start;gap:1rem;background:#f0f7f2;border:1px solid #b8ddc5;border-radius:10px;padding:1.25rem 1.4rem;text-decoration:none;color:var(--gray-800);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px;flex-shrink:0;color:var(--primary);"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <div>
            <div style="font-weight:700;font-size:.95rem;margin-bottom:.25rem;color:var(--primary);">Letters of Understanding</div>
            <div style="font-size:.82rem;color:var(--gray-500);line-height:1.5;">Browse all signed LOUs, settlements, and arbitration awards with full detail.</div>
          </div>
        </a>
      </div>

      <!-- SETTLEMENTS & LETTERS OF UNDERSTANDING -->
      <h2 id="settlements" style="font-size:1.35rem;font-weight:800;color:var(--primary);margin-bottom:.4rem;">Settlements &amp; Letters of Understanding</h2>
      <p style="color:var(--gray-500);font-size:.92rem;margin-bottom:1.5rem;">Local settlements, remedies, and letters of understanding reached between BVTU and SD54, listed chronologically. <a href="lous.php">View detailed descriptions →</a></p>

      <div class="doc-categories">

        <div class="doc-category" style="grid-column: 1 / -1;">
          <h3>2000s</h3>
          <div class="doc-list">
            <a href="documents/settlements/2001-dorsey-award.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2001 — Dorsey Award
            </a>
            <a href="documents/settlements/2004-early-retirement-incentive.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2004 — Early Retirement Incentive
            </a>
          </div>
        </div>

        <div class="doc-category" style="grid-column: 1 / -1;">
          <h3>2010s</h3>
          <div class="doc-list">
            <a href="documents/settlements/2010-sec-preptime.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2010 — Secondary Prep Time (Bev Young)
            </a>
            <a href="documents/settlements/2013-elem-preptime.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2013 — Elementary Prep Time Agreement
            </a>
            <a href="documents/settlements/2014-ttoc-pay-settlement.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2014 — TTOC Pay Settlement
            </a>
            <a href="documents/settlements/2014-ttoc-continuation-settlement.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2014 — TTOC Continuation Settlement
            </a>
            <a href="documents/settlements/2015-part-time-pro-d.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2015 — Part-Time Teachers Pro-D Settlement
            </a>
            <a href="documents/settlements/2017-2018-cupe-emergency-replacements.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2017–18 — CUPE SD54 Emergency Response Replacements
            </a>
            <a href="documents/settlements/2017-ttoc-temp-contract-sick-days.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2017 — TTOC Temp Contract Sick Days
            </a>
            <a href="documents/settlements/2018-jackson-remedy-rollover.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2018 — Jackson Remedy Rollover
            </a>
          </div>
        </div>

        <div class="doc-category" style="grid-column: 1 / -1;">
          <h3>2023–Present</h3>
          <div class="doc-list">
            <a href="documents/settlements/2023-calendar-settlement.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2023 — Calendar Settlement
            </a>
            <a href="documents/settlements/2023-continuing-to-continuing.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2023 — Continuing to Continuing
            </a>
            <a href="documents/settlements/2024-d4-5-elem-prep.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2024 — Article D.4.5 Elementary Prep (Updated)
            </a>
            <a href="documents/settlements/2024-lab-shop-class-size.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2024 — Lab/Shop Class Size Settlement
            </a>
            <a href="documents/settlements/2025-2026-lous-signed.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              2025–26 — Letters of Understanding (Signed)
            </a>
          </div>
        </div>

        <div class="doc-category" style="grid-column: 1 / -1;">
          <h3>Clean Articles</h3>
          <div class="doc-list">
            <a href="documents/settlements/article-c25-part-time-rights.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Article C.25 — Part-Time Teacher Rights
            </a>
            <a href="documents/settlements/article-d22-staff-meetings.pdf" class="doc-item" download>
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
              Article D.22 — Staff Meetings
            </a>
          </div>
        </div>

      </div>

      <!-- SCHOOL CALENDARS -->
      <hr class="section-divider">
      <h2 id="calendars" style="font-size:1.35rem;font-weight:800;color:var(--primary);margin-bottom:.4rem;">School Calendars</h2>
      <p style="color:var(--gray-500);font-size:.92rem;margin-bottom:1.5rem;">SD54 (Bulkley Valley) school year calendars — instructional days, Pro-D days, and key dates.</p>

      <div class="cal-grid">

        <div class="cal-card">
          <img src="images/calendars/cal-2526-thumb.jpg" alt="SD54 2025–2026 School Calendar" class="cal-card-img">
          <div class="cal-card-body">
            <h3>2025–2026 School Calendar</h3>
            <p>Days in Session: 188 &nbsp;·&nbsp; Days of Instruction: 181</p>
            <div class="cal-card-actions">
              <a href="images/calendars/cal-2526.png" target="_blank" class="cal-btn-view">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View
              </a>
              <a href="documents/calendars/SD54-School-Calendar-2025-2026.pdf" download class="cal-btn-dl">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download PDF
              </a>
            </div>
          </div>
        </div>

        <div class="cal-card">
          <img src="images/calendars/cal-2627-thumb.jpg" alt="SD54 2026–2027 School Calendar" class="cal-card-img">
          <div class="cal-card-body">
            <h3>2026–2027 School Calendar</h3>
            <p>Days in Session: 187 &nbsp;·&nbsp; Days of Instruction: 180</p>
            <div class="cal-card-actions">
              <a href="images/calendars/cal-2627.png" target="_blank" class="cal-btn-view">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View
              </a>
              <a href="documents/calendars/SD54-School-Calendar-2026-2027.pdf" download class="cal-btn-dl">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download PDF
              </a>
            </div>
          </div>
        </div>

      </div>

      <div class="info-box" style="margin-top: 2.5rem;">
        <p>Can't find what you're looking for? <a href="contact.php">Contact the union office</a>.</p>
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
          
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li><a href="library.php">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li>
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
