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
  <title>Health &amp; Dental Benefits — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Pacific Blue Cross health and dental benefits for SD54 teachers — extended health, dental, prescription drugs, vision, paramedical, and how to make a claim.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>

    /* ── Tab navigation ──────────────────────────────────────────── */
    .benefits-tabs {
      display: flex;
      gap: .4rem;
      flex-wrap: wrap;
      margin-bottom: 2rem;
      border-bottom: 2px solid var(--border);
      padding-bottom: 0;
    }
    .benefits-tab {
      padding: .65rem 1.2rem;
      font-size: .9rem;
      font-weight: 700;
      color: var(--gray-500);
      background: transparent;
      border: none;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      cursor: pointer;
      transition: color .15s, border-color .15s;
      font-family: var(--font);
    }
    .benefits-tab:hover { color: var(--primary); }
    .benefits-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

    .benefits-panel { display: none; }
    .benefits-panel.active { display: block; }

    /* ── Summary highlight cards ─────────────────────────────────── */
    .benefit-highlights {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: .85rem;
      margin-bottom: 2rem;
    }
    .benefit-hl {
      background: var(--accent);
      border: 1.5px solid var(--primary);
      border-radius: var(--radius);
      padding: 1.1rem 1rem;
      text-align: center;
    }
    .benefit-hl-val {
      font-size: 1.7rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }
    .benefit-hl-label {
      font-size: .78rem;
      font-weight: 600;
      color: var(--gray-600);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-top: .3rem;
    }

    /* ── Coverage rows ───────────────────────────────────────────── */
    .coverage-table {
      width: 100%;
      border-collapse: collapse;
      margin: 1rem 0 1.5rem;
      font-size: .92rem;
    }
    .coverage-table th {
      background: var(--primary);
      color: white;
      padding: .65rem 1rem;
      text-align: left;
      font-size: .82rem;
      text-transform: uppercase;
      letter-spacing: .06em;
    }
    .coverage-table th:last-child { text-align: right; }
    .coverage-table td {
      padding: .7rem 1rem;
      border-bottom: 1px solid var(--gray-200);
      vertical-align: top;
      line-height: 1.55;
      color: var(--text);
    }
    .coverage-table td:last-child { text-align: right; white-space: nowrap; }
    .coverage-table tr:last-child td { border-bottom: none; }
    .coverage-table tr:nth-child(even) td { background: var(--off-white); }
    .coverage-pct {
      font-weight: 700;
      color: var(--primary);
    }
    .coverage-note {
      font-size: .8rem;
      color: var(--gray-500);
      display: block;
      margin-top: .15rem;
    }

    /* ── Section sub-headers ─────────────────────────────────────── */
    .ben-section { margin-bottom: 2rem; }
    .ben-section-head {
      display: flex;
      align-items: center;
      gap: .7rem;
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: .9rem;
      padding-bottom: .5rem;
      border-bottom: 2px solid var(--accent);
    }
    .ben-section-icon {
      width: 32px;
      height: 32px;
      background: var(--accent);
      border-radius: 7px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .ben-section-icon svg {
      width: 16px; height: 16px;
      stroke: var(--primary); fill: none;
      stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    }

    /* ── Tier badges ─────────────────────────────────────────────── */
    .tier-badge {
      display: inline-block;
      font-size: .75rem;
      font-weight: 700;
      padding: .15rem .55rem;
      border-radius: 100px;
      margin-left: .5rem;
      vertical-align: middle;
    }
    .tier-100 { background: #dcfce7; color: #166534; }
    .tier-80  { background: #dbeafe; color: #1e40af; }
    .tier-eh  { background: #f3e8ff; color: #6b21a8; }

    /* ── Drug list ───────────────────────────────────────────────── */
    .pill-list {
      display: flex;
      flex-wrap: wrap;
      gap: .45rem;
      margin: .75rem 0;
    }
    .pill {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: 100px;
      padding: .3rem .85rem;
      font-size: .82rem;
      color: var(--gray-700);
    }
    .pill.no {
      background: #fef2f2;
      border-color: #fecaca;
      color: #b91c1c;
    }

    /* ── How to claim ────────────────────────────────────────────── */
    .claim-steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin: 1rem 0;
    }
    .claim-step {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.1rem;
      position: relative;
    }
    .claim-step-num {
      width: 28px; height: 28px;
      background: var(--primary);
      color: white;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .82rem; font-weight: 800;
      margin-bottom: .65rem;
    }
    .claim-step h4 { font-size: .9rem; font-weight: 700; color: var(--primary); margin: 0 0 .3rem; }
    .claim-step p  { font-size: .85rem; color: var(--gray-600); margin: 0; line-height: 1.55; }

    /* ── Contact bar ─────────────────────────────────────────────── */
    .contact-bar {
      background: var(--primary);
      border-radius: var(--radius);
      padding: 1.4rem 1.75rem;
      display: flex;
      align-items: center;
      gap: 1.25rem;
      flex-wrap: wrap;
      margin-top: 1.5rem;
    }
    .contact-bar-icon {
      width: 44px; height: 44px;
      background: rgba(255,255,255,.15);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .contact-bar-icon svg { width: 22px; height: 22px; stroke: white; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .contact-bar h3 { margin: 0 0 .15rem; font-size: 1rem; color: white; }
    .contact-bar p  { margin: 0; font-size: .88rem; color: rgba(255,255,255,.8); }
    .contact-bar a  { color: white; font-weight: 700; }

    /* ── Disclaimer ──────────────────────────────────────────────── */
    .disclaimer {
      background: #fffbeb;
      border: 1.5px solid #f59e0b;
      border-radius: var(--radius-s);
      padding: .9rem 1.1rem;
      font-size: .85rem;
      color: #78350f;
      line-height: 1.65;
      margin-bottom: 1.75rem;
    }
    .disclaimer strong { color: #92400e; }

    @media (max-width: 600px) {
      .coverage-table { font-size: .82rem; }
      .coverage-table th, .coverage-table td { padding: .55rem .7rem; }
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
          
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li></ul></li>
          <li class="has-dropdown">
            <a href="members.php" class="active">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php" class="active">Health &amp; Dental Benefits</a></li>
              <li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li>
              <li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
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
      <h1>Health &amp; Dental Benefits</h1>
      <p>Your Pacific Blue Cross coverage as an SD54 teacher — extended health, dental, and prescription drugs, explained clearly.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="disclaimer">
        <strong>Please note:</strong> This page is a plain-language summary of the provincial BC teacher benefit plan through Pacific Blue Cross. Your exact coverage limits may vary slightly. Always verify your specific entitlements by logging in to your <a href="https://service.pac.bluecross.ca/member/login/" target="_blank" rel="noopener">Pacific Blue Cross member account</a> or calling PBC at <strong>1-888-275-4672</strong>.
      </div>

      <!-- ── At-a-Glance Highlights ──────────────────────────────── -->
      <div class="benefit-highlights">
        <div class="benefit-hl">
          <div class="benefit-hl-val">90%</div>
          <div class="benefit-hl-label">Dental — Basic &amp; Preventive</div>
        </div>
        <div class="benefit-hl">
          <div class="benefit-hl-val">70%</div>
          <div class="benefit-hl-label">Major Dental (Crowns, Bridges, Dentures)</div>
        </div>
        <div class="benefit-hl">
          <div class="benefit-hl-val">$1,000</div>
          <div class="benefit-hl-label">Massage / Physio / Chiro per Year</div>
        </div>
        <div class="benefit-hl">
          <div class="benefit-hl-val">$1,500</div>
          <div class="benefit-hl-label">Counselling per Year</div>
        </div>
        <div class="benefit-hl">
          <div class="benefit-hl-val">$650</div>
          <div class="benefit-hl-label">Vision Eyewear / 2 Years</div>
        </div>
        <div class="benefit-hl">
          <div class="benefit-hl-val">100%</div>
          <div class="benefit-hl-label">Out-of-Province Emergency</div>
        </div>
      </div>

      <!-- ── Tabs ───────────────────────────────────────────────── -->
      <div class="benefits-tabs" role="tablist">
        <button class="benefits-tab active" role="tab" data-tab="dental">Dental</button>
        <button class="benefits-tab" role="tab" data-tab="extended">Extended Health</button>
        <button class="benefits-tab" role="tab" data-tab="drugs">Prescription Drugs</button>
        <button class="benefits-tab" role="tab" data-tab="claims">How to Claim</button>
      </div>

      <!-- ══════════════════════════════════════════════════════════
           TAB: DENTAL
      ══════════════════════════════════════════════════════════ -->
      <div class="benefits-panel active" id="tab-dental">

        <p>Your dental plan covers you, your spouse/partner, and dependent children (under 21, or under 25 if a full-time student). Coverage is based on the BC Dental Fee Guide. Claims are usually submitted directly to PBC by your dentist — you only pay the difference, if any.</p>

        <div class="info-box" style="margin-bottom:1.5rem;font-size:.9rem;">
          <strong>Pre-authorization tip:</strong> For crowns, bridges, dentures, implants, orthodontics, or any major procedure — ask your dentist to submit a <em>pre-determination</em> to Pacific Blue Cross <em>before</em> treatment begins. PBC will confirm exactly what they'll cover so there are no surprises.
        </div>

        <!-- Basic & Preventive -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/></svg></div>
            Basic &amp; Preventive Services
            <span class="tier-badge tier-100">90% covered</span>
          </div>
          <table class="coverage-table">
            <thead>
              <tr><th>Service</th><th>Frequency / Limit</th><th>Coverage</th></tr>
            </thead>
            <tbody>
              <tr><td>Complete oral exam</td><td><span class="coverage-note">1 per 36 months</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Recall exam (dentist or hygienist)</td><td><span class="coverage-note">Combined limit: 2 per calendar year</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Specific / emergency exam</td><td><span class="coverage-note">Combined limit: 2 per calendar year</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Scaling (cleaning)</td><td><span class="coverage-note">No stated unit limit</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Polishing</td><td><span class="coverage-note">2 per calendar year</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Fluoride treatment</td><td><span class="coverage-note">2 per calendar year</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Bitewing &amp; periapical x-rays</td><td><span class="coverage-note">Per calendar year · dental fee guide max</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Panoramic x-ray</td><td><span class="coverage-note">1 per 24 months</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Complete series x-rays</td><td><span class="coverage-note">1 per 36 months</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Silver (amalgam) fillings</td><td><span class="coverage-note">1 per tooth per 24 months</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>White (composite) fillings</td><td><span class="coverage-note">1 per tooth per 24 months · molar paid at silver rate</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Prefabricated metal restorations</td><td><span class="coverage-note">1 per tooth per 24 months</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Root canals (all types)</td><td><span class="coverage-note">1 per tooth per 60 months</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Extractions (simple &amp; complex)</td><td><span class="coverage-note">1 per tooth per lifetime · combined limit</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Dental surgery &amp; anaesthesia</td><td><span class="coverage-note">Anaesthesia only on same day as oral/periodontal surgery</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Periodontal (gum) treatment</td><td><span class="coverage-note">Root planing, osseous surgery — 1 per sextant per 5 yrs</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Night guards (bruxing appliances)</td><td><span class="coverage-note">2 per 60 months · lost/stolen not covered</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Space maintainers</td><td><span class="coverage-note">1 per quadrant per calendar year</span></td><td><span class="coverage-pct">90%</span></td></tr>
            </tbody>
          </table>
        </div>

        <!-- Major Restorative -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></div>
            Major Restorative — Crowns, Bridges &amp; Dentures
            <span class="tier-badge tier-80">70% covered</span>
          </div>
          <p style="font-size:.9rem;color:var(--gray-600);margin-bottom:.9rem;">Major restorative services are reimbursed at <strong>70%</strong> of the BC Dental Fee Guide. There is no single annual dollar maximum — limits are per tooth per period as shown. Pre-authorization is strongly recommended.</p>
          <table class="coverage-table">
            <thead>
              <tr><th>Service</th><th>Frequency / Limit</th><th>Coverage</th></tr>
            </thead>
            <tbody>
              <tr><td>Crowns (porcelain, gold, veneer)</td><td><span class="coverage-note">1 per tooth per 60 months · combined limit with bridges/inlays/veneers</span></td><td><span class="coverage-pct">70%</span></td></tr>
              <tr><td>Bridge pontics, abutments &amp; retainers</td><td><span class="coverage-note">1 per tooth per 60 months · combined limit</span></td><td><span class="coverage-pct">70%</span></td></tr>
              <tr><td>Bridge implants</td><td><span class="coverage-note">1 per tooth per 60 months · combined limit</span></td><td><span class="coverage-pct">70%</span></td></tr>
              <tr><td>Inlays &amp; onlays</td><td><span class="coverage-note">1 per tooth per 60 months · combined limit</span></td><td><span class="coverage-pct">70%</span></td></tr>
              <tr><td>Veneers</td><td><span class="coverage-note">1 per tooth per 60 months · combined limit</span></td><td><span class="coverage-pct">70%</span></td></tr>
              <tr><td>Complete dentures (upper &amp; lower)</td><td><span class="coverage-note">1 per person per 60 months · lost/stolen not covered</span></td><td><span class="coverage-pct">70%</span></td></tr>
              <tr><td>Partial dentures</td><td><span class="coverage-note">1 per person per 60 months</span></td><td><span class="coverage-pct">70%</span></td></tr>
            </tbody>
          </table>
        </div>

        <!-- Denture Maintenance -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            Denture Maintenance &amp; Bridge Repairs
            <span class="tier-badge tier-100">90% covered</span>
          </div>
          <table class="coverage-table">
            <thead>
              <tr><th>Service</th><th>Limit</th><th>Coverage</th></tr>
            </thead>
            <tbody>
              <tr><td>Denture repairs &amp; additions</td><td><span class="coverage-note">GP or denturist</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Denture adjustments</td><td><span class="coverage-note">4 per calendar year combined</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Denture rebases &amp; relines</td><td><span class="coverage-note">1 per person per 24 months · upper and lower tracked separately</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Tissue conditioning</td><td><span class="coverage-note">2 per person per 60 months · 2 per arch per 5-year overall cap</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Bridge recementation &amp; repairs</td><td><span class="coverage-note">2 per tooth per day from first eligible claim</span></td><td><span class="coverage-pct">90%</span></td></tr>
              <tr><td>Bridge removal</td><td><span class="coverage-note">2 per tooth per day from first eligible claim</span></td><td><span class="coverage-pct">90%</span></td></tr>
            </tbody>
          </table>
        </div>

        <!-- Orthodontics -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
            Orthodontic Services
            <span class="tier-badge tier-80">75% · $5,000 lifetime max</span>
          </div>
          <p>Orthodontic coverage (braces, retainers, appliances) is reimbursed at <strong>75%</strong> up to a <strong>$5,000 lifetime maximum per person</strong>. A pre-determination must be submitted to PBC before treatment begins. TMJ dysfunction and lost/stolen appliances are not covered.</p>
        </div>

      </div>

      <!-- ══════════════════════════════════════════════════════════
           TAB: EXTENDED HEALTH
      ══════════════════════════════════════════════════════════ -->
      <div class="benefits-panel" id="tab-extended">

        <p>Extended health coverage supplements the BC Medical Services Plan. Your plan covers you, your spouse/partner, and dependent children (under 21, or under 25 if a full-time student).</p>

        <div class="info-box" style="margin-bottom:1.5rem;font-size:.9rem;">
          <strong>How reimbursement works:</strong> After a <strong>$50 annual deductible per family</strong>, the plan pays <strong>80%</strong> of your first $1,000 in eligible expenses per person per calendar year. After that threshold, it pays <strong>100%</strong> of further eligible expenses. Vision, paramedical, and drug benefits all share this same deductible and reimbursement structure.
        </div>

        <!-- Paramedical -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
            Paramedical Services
          </div>
          <p style="font-size:.88rem;color:var(--gray-600);margin-bottom:.9rem;">All paramedical limits are <strong>per person per calendar year</strong>. Practitioners must be registered in BC. If visits exceed what PBC considers reasonable, a doctor's note may be requested.</p>
          <table class="coverage-table">
            <thead>
              <tr><th>Practitioner</th><th>Annual Maximum</th><th>Notes</th></tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Physiotherapist</strong></td>
                <td><span class="coverage-pct">$1,000</span>/person/year</td>
                <td>In-person and online · 80/100% structure</td>
              </tr>
              <tr>
                <td><strong>Registered Massage Therapist</strong></td>
                <td><span class="coverage-pct">$1,000</span>/person/year</td>
                <td>In-person only · 80/100% structure</td>
              </tr>
              <tr>
                <td><strong>Chiropractor</strong></td>
                <td><span class="coverage-pct">$1,000</span>/person/year</td>
                <td>Includes chiro x-rays · $131 initial / $77 subsequent visit · 80/100%</td>
              </tr>
              <tr>
                <td><strong>Acupuncturist</strong></td>
                <td><span class="coverage-pct">$1,000</span>/person/year</td>
                <td>In-person only · $135 initial / $115 subsequent · 80/100%</td>
              </tr>
              <tr>
                <td><strong>Naturopath</strong></td>
                <td><span class="coverage-pct">$1,000</span>/person/year</td>
                <td>Treatments and testing · $257 initial / $189 subsequent · 80/100%<span class="coverage-note">X-rays, appliances, and remedies dispensed by naturopath are <em>not</em> covered</span></td>
              </tr>
              <tr>
                <td><strong>Podiatrist</strong></td>
                <td><span class="coverage-pct">$800</span>/person/year</td>
                <td>In-person only · $180 initial / $105 subsequent · 80/100%<span class="coverage-note">Podiatry x-rays are <em>not</em> covered</span></td>
              </tr>
              <tr>
                <td><strong>Speech Therapist</strong></td>
                <td><span class="coverage-pct">$800</span>/person/year</td>
                <td>In-person and online · $200 initial / $175 subsequent · 80/100%</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Counselling -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
            Mental Health &amp; Counselling Services
            <span class="tier-badge tier-80">$1,500/year combined</span>
          </div>
          <p style="font-size:.88rem;color:var(--gray-600);margin-bottom:.9rem;">All counselling providers below share a <strong>combined $1,500 per person per calendar year</strong> limit. Subject to the 80/100% reimbursement structure and $50 family deductible. Eligible amounts are based on a 60-minute visit — your actual reimbursement depends on visit length.</p>
          <table class="coverage-table">
            <thead>
              <tr><th>Provider Type</th><th>Eligible Amount</th><th>Notes</th></tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Registered Psychologist</strong></td>
                <td><span class="coverage-pct">$300</span>/visit</td>
                <td>In-person or online</td>
              </tr>
              <tr>
                <td><strong>Counsellor / Psychotherapist</strong></td>
                <td><span class="coverage-pct">$170</span>/visit</td>
                <td>In-person or online · Must be registered with BCACC, CPCA, CCPA, ACCT, CRPO, or equivalent<span class="coverage-note">Group counselling is not eligible</span></td>
              </tr>
              <tr>
                <td><strong>Clinical Social Worker</strong></td>
                <td><span class="coverage-pct">$170</span>/visit</td>
                <td>In-person or online</td>
              </tr>
              <tr>
                <td><strong>Online Cognitive Behavioural Therapy (CBT)</strong></td>
                <td>Covered</td>
                <td>Must use a PBC-eligible vendor · Shares $1,500 combined limit<span class="coverage-note">Visit <a href="https://www.pac.bluecross.ca" target="_blank" rel="noopener">pac.bluecross.ca</a> to find eligible CBT vendors</span></td>
              </tr>
            </tbody>
          </table>
          <div class="pill-list" style="margin-top:.75rem;">
            <span class="pill no">Psychoanalyst — not a benefit</span>
            <span class="pill no">Marriage &amp; Family Therapy — not a benefit</span>
            <span class="pill no">Parenting training — not a benefit</span>
            <span class="pill no">Group counselling — not eligible</span>
          </div>
        </div>

        <!-- Vision -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
            Vision Care
            <span class="tier-badge tier-80">80/100% · $50 deductible</span>
          </div>
          <p style="font-size:.88rem;color:var(--gray-600);margin-bottom:.9rem;">Vision benefits are subject to the standard extended health <strong>$50 annual family deductible</strong> and the <strong>80/100% reimbursement structure</strong> (same as paramedical). All eyewear types share a single combined dollar maximum.</p>
          <table class="coverage-table">
            <thead>
              <tr><th>Benefit</th><th>Maximum</th><th>Notes</th></tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Eyewear — combined limit</strong><span class="coverage-note">Includes: complete prescription glasses, frames, prescription lenses, prescription contact lenses, prescription sunglasses, prescription safety glasses/goggles, and vision care repairs</span></td>
                <td><span class="coverage-pct">$650</span>/person</td>
                <td>Per 24-month period from date of first eligible claim · All eyewear types share this combined limit<span class="coverage-note">Non-prescription sunglasses — not a benefit</span></td>
              </tr>
              <tr>
                <td><strong>Eye exam</strong></td>
                <td><span class="coverage-pct">$145</span> eligible/visit</td>
                <td>1 visit per person per 24-month period · Subject to $50 deductible and 80/100% structure</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Hospital & Equipment -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
            Hospital, Equipment &amp; Other Benefits
          </div>
          <table class="coverage-table">
            <thead>
              <tr><th>Benefit</th><th>Maximum</th><th>Notes</th></tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>Private hospital room</strong></td>
                <td>$195/day eligible</td>
                <td>80/100% structure · $50 family deductible</td>
              </tr>
              <tr>
                <td><strong>Semi-private hospital room</strong></td>
                <td>$165/day eligible</td>
                <td>80/100% structure · $50 family deductible</td>
              </tr>
              <tr>
                <td><strong>Local ambulance</strong></td>
                <td>$80/service eligible</td>
                <td>Licensed ambulance to nearest hospital · 80/100% structure</td>
              </tr>
              <tr>
                <td><strong>Hearing aids</strong></td>
                <td><span class="coverage-pct">$3,500</span>/person per 48 months</td>
                <td>Combined limit for aids + repairs · $50 family deductible · 80/100%</td>
              </tr>
              <tr>
                <td><strong>Custom orthotics (one pair)</strong></td>
                <td><span class="coverage-pct">$500</span>/person/year</td>
                <td>Must be custom-made · Doctor's letter required · 80/100%</td>
              </tr>
              <tr>
                <td><strong>Custom orthopedic shoes</strong></td>
                <td><span class="coverage-pct">$500</span>/person/year</td>
                <td>Separate $500 limit from orthotics · Doctor's letter required · 80/100%</td>
              </tr>
              <tr>
                <td><strong>Registered nurse (home care)</strong></td>
                <td>$20,000/person/year</td>
                <td>Doctor's letter required · Not covered in hospital</td>
              </tr>
              <tr>
                <td><strong>Durable medical equipment</strong></td>
                <td>Rental preferred</td>
                <td>Wheelchairs, hospital beds, braces, prosthetics, respiratory equipment · Pre-auth required over $5,000</td>
              </tr>
              <tr>
                <td><strong>Cochlear implant (speech processor &amp; headset)</strong></td>
                <td>$8,000/unit eligible</td>
                <td>80/100% structure</td>
              </tr>
              <tr>
                <td><strong>Prostheses &amp; braces</strong></td>
                <td>Actual cost</td>
                <td>Rigid support braces, artificial limbs/eyes, mastectomy forms</td>
              </tr>
            </tbody>
          </table>
          <div class="pill-list" style="margin-top:.75rem;">
            <span class="pill no">Drug &amp; alcohol rehabilitation — not a benefit</span>
            <span class="pill no">Athletic therapy — not a benefit</span>
            <span class="pill no">Osteopath — not a benefit</span>
            <span class="pill no">Nursing home (residential) care — not a benefit</span>
          </div>
        </div>

        <!-- Travel -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></div>
            Out-of-Province &amp; Out-of-Canada Emergency Coverage
            <span class="tier-badge tier-100">100% covered</span>
          </div>
          <p>Emergency medical expenses incurred while travelling outside your province of residence or outside Canada are reimbursed at <strong>100%</strong>. <strong>Important: if your trip exceeds 30 days, contact PBC before you leave</strong> — trip duration limits may apply.</p>
          <table class="coverage-table">
            <thead>
              <tr><th>Benefit</th><th>Maximum</th></tr>
            </thead>
            <tbody>
              <tr><td>Hospital services &amp; physician/lab/x-ray (out of province)</td><td>100% · no limit</td></tr>
              <tr><td>Hospital services &amp; physician/lab/x-ray (outside Canada)</td><td>100% · no limit</td></tr>
              <tr><td>Air ambulance (attendants as required)</td><td>100%</td></tr>
              <tr><td>Local ambulance during travel emergency</td><td>100%</td></tr>
              <tr><td>Emergency prescription drugs during travel</td><td>100%</td></tr>
              <tr><td>Airfare for family transport (all combined)</td><td>$5,000/family per calendar year</td></tr>
              <tr><td>Convalescence accommodation after hospitalization</td><td>$75/day · Max 5 days per calendar year</td></tr>
              <tr><td>Family accommodation &amp; meals</td><td>$100/day · Max 7 days per calendar year</td></tr>
              <tr><td>Vehicle return</td><td>$500/emergency</td></tr>
              <tr><td>Cremation/repatriation outside Canada</td><td>$1,500 cremation · $5,000 repatriation per lifetime</td></tr>
            </tbody>
          </table>
          <p style="font-size:.85rem;color:var(--gray-600);margin-top:.75rem;"><strong>Medi-Assist</strong> provides 24/7 travel assistance — medical evacuation, locating care, interpreter services, contacting relatives. Call them immediately in an emergency abroad.</p>
        </div>

        <!-- Not Covered -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
            What Is Not Covered
          </div>
          <div class="pill-list">
            <span class="pill no">Vitamins &amp; supplements</span>
            <span class="pill no">Hair loss medications</span>
            <span class="pill no">Erectile dysfunction drugs</span>
            <span class="pill no">Over-the-counter medications</span>
            <span class="pill no">Cosmetic procedures</span>
            <span class="pill no">Preventive vaccines</span>
            <span class="pill no">Athletic therapy</span>
            <span class="pill no">Osteopath treatments</span>
            <span class="pill no">Drug &amp; alcohol rehabilitation</span>
            <span class="pill no">Marriage &amp; family therapy</span>
            <span class="pill no">Naturopath remedies &amp; x-rays</span>
            <span class="pill no">Podiatry x-rays</span>
            <span class="pill no">Elective out-of-province treatment</span>
            <span class="pill no">Ambulance (if not transported to hospital)</span>
          </div>
        </div>

      </div>

      <!-- ══════════════════════════════════════════════════════════
           TAB: PRESCRIPTION DRUGS
      ══════════════════════════════════════════════════════════ -->
      <div class="benefits-panel" id="tab-drugs">

        <p>Prescription drug coverage works through two complementary systems: your <strong>Pacific Blue Cross extended health plan</strong> (for drugs not covered by the province) and <strong>BC PharmaCare</strong> (the provincial drug plan). Together they provide broad coverage for most prescribed medications. Drug claims share the extended health <strong>$50 family deductible</strong> and the 80/100% reimbursement structure.</p>

        <!-- Pay-Direct -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
            Pay-Direct at the Pharmacy
          </div>
          <p>Your Pacific Blue Cross plan includes a <strong>pay-direct drug card</strong>. Present your PBC ID card at any participating pharmacy — your pharmacist submits the claim directly and you only pay your share at the counter. No forms, no waiting for reimbursement.</p>
          <div class="info-box" style="margin:.75rem 0;font-size:.9rem;">
            A written prescription from a licensed physician or dentist is required for all drug claims.
          </div>
        </div>

        <!-- What's Covered -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            Covered Medications
          </div>
          <p>The plan covers drugs and medicines that are not covered by BC PharmaCare and require a written prescription, including:</p>
          <div class="pill-list">
            <span class="pill">Prescription medications (general)</span>
            <span class="pill">Oral contraceptives</span>
            <span class="pill">Fertility drugs</span>
            <span class="pill">Nicotine patches <em style="font-size:.78rem">(max 98/yr)</em></span>
            <span class="pill">Nicotine gum <em style="font-size:.78rem">(max 945 pieces/yr)</em></span>
            <span class="pill">Insulin preparations</span>
            <span class="pill">Vitamin B12 <em style="font-size:.78rem">(pernicious anaemia only)</em></span>
            <span class="pill">Allergy serums <em style="font-size:.78rem">(physician-administered)</em></span>
          </div>
        </div>

        <!-- Not Covered -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
            Not Covered
          </div>
          <div class="pill-list">
            <span class="pill no">Vitamins &amp; mineral supplements</span>
            <span class="pill no">Hair loss drugs (Minoxidil, Propecia)</span>
            <span class="pill no">Erectile dysfunction drugs</span>
            <span class="pill no">Drugs not approved for sale in Canada</span>
            <span class="pill no">Over-the-counter medications</span>
            <span class="pill no">Preventive vaccines / immunizations</span>
            <span class="pill no">General anaesthetics</span>
            <span class="pill no">Food &amp; meal replacements</span>
          </div>
        </div>

        <!-- BC PharmaCare -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
            BC PharmaCare (Provincial Plan)
          </div>
          <p>In addition to your PBC plan, BC PharmaCare provides a provincial prescription drug benefit. The deductible is income-based:</p>
          <table class="coverage-table">
            <thead>
              <tr><th>Net Family Income</th><th>Annual Deductible</th><th>Coverage After Deductible</th></tr>
            </thead>
            <tbody>
              <tr><td>Under $15,000</td><td><strong>$0</strong></td><td><span class="coverage-pct">70%</span> of eligible drug costs</td></tr>
              <tr><td>$15,000 – $30,000</td><td><strong>2% of net income</strong></td><td><span class="coverage-pct">70%</span> of eligible drug costs</td></tr>
              <tr><td>Over $30,000</td><td><strong>3% of net income</strong></td><td><span class="coverage-pct">70%</span> of eligible drug costs</td></tr>
            </tbody>
          </table>
          <p style="font-size:.87rem;color:var(--gray-600);">Any deductible amounts and the remaining 30% not covered by PharmaCare may be claimed under your Pacific Blue Cross extended health plan, providing layered coverage on most prescription costs.</p>
        </div>

      </div>

      <!-- ══════════════════════════════════════════════════════════
           TAB: HOW TO CLAIM
      ══════════════════════════════════════════════════════════ -->
      <div class="benefits-panel" id="tab-claims">

        <!-- Dental claims -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/></svg></div>
            Dental Claims
          </div>
          <div class="claim-steps">
            <div class="claim-step">
              <div class="claim-step-num">1</div>
              <h4>Tell your dentist</h4>
              <p>Let your dentist know you have Pacific Blue Cross coverage. Have your PBC ID card ready.</p>
            </div>
            <div class="claim-step">
              <div class="claim-step-num">2</div>
              <h4>Direct billing</h4>
              <p>Most dentists submit claims directly to PBC. You only pay the portion not covered — typically nothing for basic services.</p>
            </div>
            <div class="claim-step">
              <div class="claim-step-num">3</div>
              <h4>Over $500? Get a Treatment Plan</h4>
              <p>For major work expected to exceed $500, ask your dentist to submit a Treatment Plan first. PBC will confirm coverage before work begins.</p>
            </div>
            <div class="claim-step">
              <div class="claim-step-num">4</div>
              <h4>Submit deadline</h4>
              <p>All dental claims must be submitted within <strong>1 year</strong> of the service date.</p>
            </div>
          </div>
        </div>

        <!-- Drug claims -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
            Prescription Drug Claims
          </div>
          <div class="claim-steps">
            <div class="claim-step">
              <div class="claim-step-num">1</div>
              <h4>Present your PBC card</h4>
              <p>Give the pharmacist your Pacific Blue Cross ID card when filling a prescription. They submit the claim automatically.</p>
            </div>
            <div class="claim-step">
              <div class="claim-step-num">2</div>
              <h4>Pay your share only</h4>
              <p>You pay only the portion not covered by the plan — no upfront payment and reimbursement wait.</p>
            </div>
          </div>
        </div>

        <!-- Extended health claims -->
        <div class="ben-section">
          <div class="ben-section-head">
            <div class="ben-section-icon"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
            Extended Health Claims (Paramedical, Vision, etc.)
          </div>
          <div class="claim-steps">
            <div class="claim-step">
              <div class="claim-step-num">1</div>
              <h4>Pay upfront</h4>
              <p>For most extended health services, you pay the provider directly and keep your original receipt.</p>
            </div>
            <div class="claim-step">
              <div class="claim-step-num">2</div>
              <h4>Submit online</h4>
              <p>Log in to your Pacific Blue Cross member account at <a href="https://service.pac.bluecross.ca/member/login/" target="_blank" rel="noopener">service.pac.bluecross.ca</a> to submit claims and check status online.</p>
            </div>
            <div class="claim-step">
              <div class="claim-step-num">3</div>
              <h4>Or mail your claim</h4>
              <p>Complete a claim form and mail with original receipts to:<br><strong>Pacific Blue Cross, P.O. Box 7000, Vancouver, BC V6B 4E1</strong><br><em>Keep photocopies — originals are not returned.</em></p>
            </div>
            <div class="claim-step">
              <div class="claim-step-num">4</div>
              <h4>Submit deadline</h4>
              <p>All extended health claims must be submitted by <strong>December 31 of the year following</strong> the year the expense was incurred.</p>
            </div>
          </div>
        </div>

        <!-- Contact -->
        <div class="contact-bar">
          <div class="contact-bar-icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.65 3.18 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.54a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          <div>
            <h3>Pacific Blue Cross — Member Services</h3>
            <p>Toll-free: <a href="tel:18882754672">1-888-275-4672</a> &nbsp;·&nbsp; Lower Mainland: <a href="tel:6044192000">604-419-2000</a> &nbsp;·&nbsp; Monday – Friday, 8:00 a.m. – 4:30 p.m. PST</p>
            <p style="margin-top:.3rem;">Member portal: <a href="https://service.pac.bluecross.ca/member/login/" target="_blank" rel="noopener">service.pac.bluecross.ca</a> — view coverage, submit claims, download your ID card</p>
          </div>
        </div>

      </div><!-- end panels -->

      <div class="info-box" style="margin-top:2rem;">
        <p>This summary is based on the provincial BC teacher benefit plan structure. For your exact plan details, log in to <a href="https://service.pac.bluecross.ca/member/login/" target="_blank" rel="noopener">Pacific Blue Cross</a> or contact the BCTF benefits team at <a href="https://www.bctf.ca/topics/services-information/benefits/health-and-dental" target="_blank" rel="noopener">bctf.ca/benefits</a>.</p>
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
  <script>
    // Tab switching
    document.querySelectorAll('.benefits-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.benefits-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.benefits-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
      });
    });
  </script>
</body>
</html>
