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
  <title>Canada Student Loan Forgiveness — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU teachers qualify for up to $30,000 in Canada Student Loan Forgiveness. Houston, Telkwa, and Smithers postal codes are all eligible. Learn how to apply.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>

    /* ── Good-news banner ────────────────────────────────────────── */
    .lf-good-news {
      background: linear-gradient(135deg, var(--primary) 0%, #1a6b35 100%);
      border-radius: var(--radius);
      padding: 2rem;
      color: white;
      display: flex;
      gap: 1.5rem;
      align-items: center;
      margin-bottom: 2.5rem;
      flex-wrap: wrap;
    }
    .lf-good-news-icon {
      font-size: 3rem;
      line-height: 1;
      flex-shrink: 0;
    }
    .lf-good-news h2 {
      color: white;
      font-size: 1.3rem;
      margin: 0 0 .4rem;
    }
    .lf-good-news p {
      margin: 0;
      color: rgba(255,255,255,.85);
      font-size: .95rem;
      line-height: 1.55;
    }
    .lf-good-news strong { color: white; }

    /* ── Highlight stat cards ─────────────────────────────────────── */
    .lf-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 1rem;
      margin-bottom: 2.5rem;
    }
    .lf-stat {
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem 1rem;
      text-align: center;
    }
    .lf-stat.accent { border-color: var(--primary); background: var(--accent); }
    .lf-stat-val {
      font-size: 2rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }
    .lf-stat-label {
      font-size: .8rem;
      font-weight: 700;
      color: var(--gray-500);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-top: .35rem;
    }

    /* ── Section headers ─────────────────────────────────────────── */
    .lf-section { margin-bottom: 2.25rem; }
    .lf-section-head {
      display: flex;
      align-items: center;
      gap: .75rem;
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: .9rem;
      padding-bottom: .55rem;
      border-bottom: 2px solid var(--accent);
    }
    .lf-icon {
      width: 34px; height: 34px;
      background: var(--accent);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .lf-icon svg {
      width: 17px; height: 17px;
      stroke: var(--primary); fill: none;
      stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    }

    /* ── Year-by-year progress visual ────────────────────────────── */
    .lf-years {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: .75rem;
      margin: 1rem 0;
    }
    @media (max-width: 560px) { .lf-years { grid-template-columns: 1fr 1fr; } }
    .lf-year {
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem .75rem;
      text-align: center;
      position: relative;
    }
    .lf-year-num {
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--gray-500);
      margin-bottom: .3rem;
    }
    .lf-year-amount {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1;
    }
    .lf-year-bar {
      height: 4px;
      background: var(--accent);
      border-radius: 2px;
      margin-top: .65rem;
    }
    .lf-year:nth-child(1) .lf-year-bar { width: 40%; background: var(--primary); opacity: .35; }
    .lf-year:nth-child(2) .lf-year-bar { width: 50%; background: var(--primary); opacity: .5; }
    .lf-year:nth-child(3) .lf-year-bar { width: 62%; background: var(--primary); opacity: .65; }
    .lf-year:nth-child(4) .lf-year-bar { width: 76%; background: var(--primary); opacity: .8; }
    .lf-year:nth-child(5) .lf-year-bar { width: 100%; background: var(--primary); opacity: 1; }
    .lf-year-total {
      font-size: .7rem;
      color: var(--gray-500);
      margin-top: .3rem;
    }

    /* ── Eligibility checklist ───────────────────────────────────── */
    .lf-checklist {
      list-style: none;
      padding: 0;
      margin: .75rem 0;
    }
    .lf-checklist li {
      display: flex;
      gap: .75rem;
      align-items: flex-start;
      padding: .7rem .9rem;
      border-bottom: 1px solid var(--border);
      font-size: .93rem;
    }
    .lf-checklist li:last-child { border-bottom: none; }
    .lf-check-icon {
      width: 22px; height: 22px;
      background: var(--accent);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      margin-top: .1rem;
    }
    .lf-check-icon svg {
      width: 12px; height: 12px;
      stroke: var(--primary); fill: none;
      stroke-width: 3; stroke-linecap: round; stroke-linejoin: round;
    }

    /* ── Community badge row ──────────────────────────────────────── */
    .lf-community-row {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
      margin: 1rem 0;
    }
    .lf-community-badge {
      background: var(--primary);
      color: white;
      border-radius: 8px;
      padding: .6rem 1.1rem;
      font-size: .9rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .lf-community-badge .check { font-size: 1rem; }

    /* ── Step cards ──────────────────────────────────────────────── */
    .lf-steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
      gap: 1rem;
      margin: 1rem 0;
    }
    .lf-step {
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem;
    }
    .lf-step-num {
      width: 32px; height: 32px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      font-size: .85rem;
      font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: .75rem;
    }
    .lf-step h4 { font-size: .95rem; font-weight: 700; color: var(--primary); margin: 0 0 .4rem; }
    .lf-step p  { font-size: .87rem; color: var(--gray-600); margin: 0; }

    /* ── Warning box ──────────────────────────────────────────────── */
    .lf-warning {
      background: #fff7ed;
      border: 2px solid #f59e0b;
      border-radius: var(--radius);
      padding: 1.25rem 1.5rem;
      display: flex;
      gap: 1rem;
      align-items: flex-start;
      margin: 1rem 0;
    }
    .lf-warning-icon { font-size: 1.5rem; flex-shrink: 0; }
    .lf-warning strong { color: #92400e; display: block; margin-bottom: .25rem; }
    .lf-warning p { margin: 0; font-size: .91rem; color: #78350f; }

    /* ── Apply card ───────────────────────────────────────────────── */
    .lf-apply-card {
      background: var(--primary);
      border-radius: var(--radius);
      padding: 1.75rem;
      color: white;
      display: flex;
      gap: 1.5rem;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 1rem;
    }
    .lf-apply-card h3 { color: white; margin: 0 0 .3rem; font-size: 1.05rem; }
    .lf-apply-card p  { color: rgba(255,255,255,.8); margin: 0; font-size: .9rem; }
    .lf-apply-card a.btn-white {
      background: white;
      color: var(--primary);
      border: none;
      border-radius: var(--radius-s);
      padding: .65rem 1.25rem;
      font-size: .9rem;
      font-weight: 700;
      text-decoration: none;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .lf-apply-card a.btn-white:hover { background: var(--accent); }

    /* ── Break-in-service table ───────────────────────────────────── */
    .lf-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .9rem;
      margin: .75rem 0;
    }
    .lf-table th {
      background: var(--primary);
      color: white;
      padding: .6rem 1rem;
      text-align: left;
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .lf-table th:first-child { border-radius: 6px 0 0 0; }
    .lf-table th:last-child  { border-radius: 0 6px 0 0; }
    .lf-table td {
      padding: .65rem 1rem;
      border-bottom: 1px solid var(--border);
      vertical-align: top;
    }
    .lf-table tr:last-child td { border-bottom: none; }
    .lf-table tr:hover td { background: var(--off-white); }

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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
          <li class="has-dropdown">
            <a href="members.php" class="active">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li>
              <li><a href="life-insurance.php">Life Insurance</a></li>
              <li><a href="loan-forgiveness.php" class="active">Student Loan Forgiveness</a></li>
              <li><a href="ttoc.php">TTOC Resources</a></li>
              <li><a href="atrieve.php">Release Time / Atrieve</a></li>
              <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
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
      <div class="breadcrumb">
        <a href="index.php">Home</a> <span>›</span>
        <a href="members.php">Members</a> <span>›</span>
        <span>Student Loan Forgiveness</span>
      </div>
      <h1>Canada Student Loan Forgiveness</h1>
      <p class="hero-sub">Up to $30,000 forgiven over five years — and Bulkley Valley teachers qualify.</p>
    </div>
  </section>

  <main class="main-content">
    <div class="container" style="max-width:820px">

      <!-- ── Good-news banner ──────────────────────────────────────── -->
      <div class="lf-good-news">
        <div class="lf-good-news-icon">🎉</div>
        <div>
          <h2>Yes — our communities qualify!</h2>
          <p>Houston, Telkwa, and Smithers are all eligible communities under the Canada Student Loan Forgiveness program. All BVTU postal codes (V0J) are confirmed eligible. If you have a federal student loan and have been teaching in SD54, <strong>this benefit is for you</strong>.</p>
        </div>
      </div>

      <!-- ── Stat cards ────────────────────────────────────────────── -->
      <div class="lf-stats">
        <div class="lf-stat accent">
          <div class="lf-stat-val">$30,000</div>
          <div class="lf-stat-label">Maximum for teachers</div>
        </div>
        <div class="lf-stat">
          <div class="lf-stat-val">5</div>
          <div class="lf-stat-label">Years to receive it</div>
        </div>
        <div class="lf-stat">
          <div class="lf-stat-val">10</div>
          <div class="lf-stat-label">Months of employment needed</div>
        </div>
        <div class="lf-stat">
          <div class="lf-stat-val">400</div>
          <div class="lf-stat-label">In-person hours required</div>
        </div>
        <div class="lf-stat">
          <div class="lf-stat-val">90</div>
          <div class="lf-stat-label">Days to apply after period ends</div>
        </div>
      </div>

      <!-- ── Our communities ───────────────────────────────────────── -->
      <div class="lf-section">
        <div class="lf-section-head">
          <div class="lf-icon">
            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </div>
          Our Communities — All Eligible
        </div>

        <p>An eligible community is any rural area or population centre with <strong>30,000 or fewer residents</strong> according to Statistics Canada census data. Every school in SD54 qualifies.</p>

        <div class="lf-community-row">
          <div class="lf-community-badge"><span class="check">✓</span> Smithers (V0J 2N_)</div>
          <div class="lf-community-badge"><span class="check">✓</span> Telkwa (V0J 2X_)</div>
          <div class="lf-community-badge"><span class="check">✓</span> Houston (V0J 1Z_)</div>
        </div>

        <p style="font-size:.9rem;color:var(--gray-500);margin-top:.75rem;">You can verify your specific postal code using the <a href="http://tools.canlearn.ca/cslgs-scpse/cln-cln/lfnd-erpm/1-eng.do" target="_blank" rel="noopener">Government of Canada postal code lookup tool</a>. Record the exact community name shown — it must match your Employment Attestation form exactly.</p>
      </div>

      <!-- ── How much you get ──────────────────────────────────────── -->
      <div class="lf-section">
        <div class="lf-section-head">
          <div class="lf-icon">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          How Much Could Be Forgiven
        </div>

        <p>Teachers receive forgiveness on a <strong>growing scale</strong> — the longer you stay, the more you get each year. The full $30,000 is spread over five 12-month periods, which do not have to be consecutive.</p>

        <div class="lf-years">
          <div class="lf-year">
            <div class="lf-year-num">Year 1</div>
            <div class="lf-year-amount">$4,000</div>
            <div class="lf-year-bar"></div>
            <div class="lf-year-total">$4k total</div>
          </div>
          <div class="lf-year">
            <div class="lf-year-num">Year 2</div>
            <div class="lf-year-amount">$5,000</div>
            <div class="lf-year-bar"></div>
            <div class="lf-year-total">$9k total</div>
          </div>
          <div class="lf-year">
            <div class="lf-year-num">Year 3</div>
            <div class="lf-year-amount">$6,000</div>
            <div class="lf-year-bar"></div>
            <div class="lf-year-total">$15k total</div>
          </div>
          <div class="lf-year">
            <div class="lf-year-num">Year 4</div>
            <div class="lf-year-amount">$7,000</div>
            <div class="lf-year-bar"></div>
            <div class="lf-year-total">$22k total</div>
          </div>
          <div class="lf-year">
            <div class="lf-year-num">Year 5</div>
            <div class="lf-year-amount">$8,000</div>
            <div class="lf-year-bar"></div>
            <div class="lf-year-total">$30k total</div>
          </div>
        </div>

        <div class="info-box" style="font-size:.9rem;">
          <p>Forgiveness applies only to the <strong>outstanding federal portion</strong> of your Canada Student Loan. It does not apply to provincial student loans or loans that have been converted to a line of credit or private loan. After each approved period, your monthly payments are automatically reduced to reflect your lower balance.</p>
        </div>
      </div>

      <!-- ── Eligibility ───────────────────────────────────────────── -->
      <div class="lf-section">
        <div class="lf-section-head">
          <div class="lf-icon">
            <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          Eligibility Requirements
        </div>

        <ul class="lf-checklist">
          <li>
            <div class="lf-check-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><strong>Active Canada Student Loan in good standing</strong> — your payments must be up to date (or you are in the 6-month non-repayment period after graduation, or pursuing further studies)</div>
          </li>
          <li>
            <div class="lf-check-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><strong>Teaching K–12 in an eligible community</strong> — all SD54 schools in Houston, Telkwa, and Smithers qualify</div>
          </li>
          <li>
            <div class="lf-check-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><strong>10 consecutive months of employment</strong> within a 12-month loan forgiveness period (the school-year model — you work Sep–Jun, that counts)</div>
          </li>
          <li>
            <div class="lf-check-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><strong>Minimum 400 hours of in-person services</strong> over the forgiveness period</div>
          </li>
          <li>
            <div class="lf-check-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><strong>Valid BC teaching certificate</strong> from the Teacher Regulation Branch</div>
          </li>
        </ul>

        <div class="info-box" style="font-size:.9rem;margin-top:.5rem;">
          <p>Teachers are listed by name in the official eligible professions: <em>"Teacher (Kindergarten to Grade 12)"</em> is explicitly included in the program. Your TRB certification satisfies the licensing requirement.</p>
        </div>
      </div>

      <!-- ── Timing warning ────────────────────────────────────────── -->
      <div class="lf-section">
        <div class="lf-section-head">
          <div class="lf-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          Important Timing — When You Can Apply
        </div>

        <div class="lf-warning">
          <div class="lf-warning-icon">⚠️</div>
          <div>
            <strong>Earliest realistic application: June 2026</strong>
            <p>The program officially launched December 31, 2025. Because it requires 10 consecutive months of prior work, most BVTU teachers won't complete their first qualifying period until around <strong>June 2026</strong>. The CTF/FCE recommends applying as soon as you're eligible, even if you receive a rejection due to the current regulations. If rejected, email <a href="mailto:cslf-erpec@ctffce.ca" style="color:#92400e;">cslf-erpec@ctffce.ca</a> so the CTF/FCE can track the issue and advocate for improvements.</p>
          </div>
        </div>

        <table class="lf-table" style="margin-top:1rem;">
          <thead>
            <tr>
              <th>When</th>
              <th>What's Available</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Now (April 2026)</strong></td>
              <td>Download and prepare your paper application form. Gather employment records.</td>
            </tr>
            <tr>
              <td><strong>Mid-March 2026</strong></td>
              <td>Online applications expected to open. Paper applications being processed from this date.</td>
            </tr>
            <tr>
              <td><strong>June 2026</strong></td>
              <td>Most BVTU teachers complete their first 10-month qualifying period (Sep 2025 – Jun 2026).</td>
            </tr>
            <tr>
              <td><strong>Within 90 days of period end</strong></td>
              <td>Submit your application — don't miss this deadline. Late applications are refused.</td>
            </tr>
          </tbody>
        </table>

        <div class="info-box" style="margin-top:.75rem;font-size:.9rem;">
          <p>Your 12-month loan forgiveness period ends exactly one year after your start date. You select when your period begins — it must be a stretch of 12 months during which you worked in an eligible community. Teachers who work in a school must show 10 consecutive months of employment <em>within</em> that 12-month window.</p>
        </div>
      </div>

      <!-- ── Break in service ──────────────────────────────────────── -->
      <div class="lf-section">
        <div class="lf-section-head">
          <div class="lf-icon">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          Breaks in Service — Still Eligible?
        </div>
        <p>A break of more than one month normally disqualifies a period. However, you <strong>remain eligible</strong> if your break was due to any of the following:</p>

        <table class="lf-table">
          <thead>
            <tr><th>Acceptable Break Reason</th><th>Documentation</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Your own illness, disability, injury, or quarantine</td>
              <td>EI special benefits or private insurance benefits confirm the leave</td>
            </tr>
            <tr>
              <td>Caring for a family member's illness, disability, injury, or quarantine</td>
              <td>EI special benefits or private insurance benefits</td>
            </tr>
            <tr>
              <td>Pregnancy or giving birth</td>
              <td>Maternity EI benefits</td>
            </tr>
            <tr>
              <td>Parental leave for a newborn or newly adopted child</td>
              <td>Parental EI benefits</td>
            </tr>
          </tbody>
        </table>

        <p style="margin-top:.75rem;font-size:.91rem;color:var(--gray-600);">If your break was for one of these reasons, you consent on the application form for the CSFA Program to contact Employment Insurance to verify your leave. If you took an unpaid leave for an acceptable reason, you certify that on the form.</p>
      </div>

      <!-- ── How to apply ──────────────────────────────────────────── -->
      <div class="lf-section">
        <div class="lf-section-head">
          <div class="lf-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
          How to Apply
        </div>

        <div class="lf-steps">
          <div class="lf-step">
            <div class="lf-step-num">1</div>
            <h4>Confirm your postal code</h4>
            <p>Use the <a href="http://tools.canlearn.ca/cslgs-scpse/cln-cln/lfnd-erpm/1-eng.do" target="_blank" rel="noopener">postal code lookup tool</a>. Note the exact community name — it must match your attestation form exactly.</p>
          </div>
          <div class="lf-step">
            <div class="lf-step-num">2</div>
            <h4>Choose your forgiveness period</h4>
            <p>Select 12 consecutive months when you worked in SD54. For school-based teachers: 10 consecutive months within that 12-month window.</p>
          </div>
          <div class="lf-step">
            <div class="lf-step-num">3</div>
            <h4>Complete the application</h4>
            <p>Fill out ESDC form SDE0094 (Sections A–E) and have your employer complete an Employment Attestation. Paper signature required — electronic not accepted.</p>
          </div>
          <div class="lf-step">
            <div class="lf-step-num">4</div>
            <h4>Mail everything together</h4>
            <p>Send your signed application and all Employment Attestation forms <strong>together</strong> to the NSLSC. Applications sent separately are rejected.</p>
          </div>
          <div class="lf-step">
            <div class="lf-step-num">5</div>
            <h4>Apply again each year</h4>
            <p>Forgiveness is not automatic. You must reapply after each 12-month period. Up to 5 periods, not necessarily consecutive.</p>
          </div>
        </div>

        <div class="info-box" style="margin:.75rem 0;font-size:.9rem;">
          <p><strong>Mail your application to:</strong><br>
          National Student Loans Service Centre<br>
          P.O. Box 4030<br>
          Mississauga, ON L5A 4M4</p>
        </div>

        <div class="lf-apply-card">
          <div style="flex:1;min-width:200px;">
            <h3>Application Form (ESDC SDE0094)</h3>
            <p>Download the official Government of Canada application form. Print, complete by hand, and mail with your Employment Attestation.</p>
          </div>
          <a href="documents/student-loan-forgiveness-application.pdf" download class="btn-white">↓ Download Application</a>
        </div>
      </div>

      <!-- ── Questions & more info ─────────────────────────────────── -->
      <div class="lf-section">
        <div class="lf-section-head">
          <div class="lf-icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.65 3.18 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.54a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          Questions &amp; Support
        </div>

        <table class="lf-table">
          <thead><tr><th>Contact</th><th>For</th></tr></thead>
          <tbody>
            <tr>
              <td><strong>National Student Loans Service Centre</strong><br>1-888-815-4514 (toll-free)</td>
              <td>Eligibility questions, application status, your loan balance</td>
            </tr>
            <tr>
              <td><strong>CTF/FCE</strong><br><a href="mailto:cslf-erpec@ctffce.ca">cslf-erpec@ctffce.ca</a></td>
              <td>If your application is rejected — the Canadian Teachers' Federation is tracking regulatory issues and advocating for improvements</td>
            </tr>
            <tr>
              <td><strong>BVTU President</strong><br><a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a></td>
              <td>Questions about how this applies to your specific situation as a BVTU member</td>
            </tr>
          </tbody>
        </table>

        <div style="margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;">
          <a href="https://www.canada.ca/en/services/benefits/education/student-aid/grants-loans/repay/assistance/student-loan-forgiveness.html" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:.9rem;">View on Canada.ca →</a>
          <a href="http://tools.canlearn.ca/cslgs-scpse/cln-cln/lfnd-erpm/1-eng.do" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:.9rem;">Postal Code Lookup Tool →</a>
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
