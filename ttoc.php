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
  <title>TTOC Resources — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="TTOC resources for SD54 — EI hours calculator, call-out rates, experience credit, sick leave, pro-D entitlements, and benefits information.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>

    /* ── EI Calculator ───────────────────────────────────────────── */
    .ei-calculator {
      background: var(--primary);
      border-radius: var(--radius);
      padding: 2rem;
      color: white;
      margin-bottom: 2.5rem;
    }
    .ei-calculator h2 {
      color: white;
      font-size: 1.25rem;
      margin: 0 0 .35rem;
    }
    .ei-calculator .sub {
      color: rgba(255,255,255,.75);
      font-size: .9rem;
      margin: 0 0 1.5rem;
    }
    .ei-calc-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 1rem;
      align-items: end;
    }
    @media (max-width: 600px) { .ei-calc-grid { grid-template-columns: 1fr; } }
    .ei-calc-field label {
      display: block;
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: rgba(255,255,255,.75);
      margin-bottom: .4rem;
    }
    .ei-calc-field input, .ei-calc-field select {
      width: 100%;
      padding: .75rem 1rem;
      background: rgba(255,255,255,.15);
      border: 1.5px solid rgba(255,255,255,.3);
      border-radius: var(--radius-s);
      color: white;
      font-size: 1rem;
      font-family: var(--font);
    }
    .ei-calc-field input::placeholder { color: rgba(255,255,255,.5); }
    .ei-calc-field input:focus, .ei-calc-field select:focus {
      outline: none;
      border-color: white;
      background: rgba(255,255,255,.2);
    }
    .ei-calc-field select option { background: #1b6b42; color: white; }
    .ei-result {
      background: rgba(255,255,255,.15);
      border: 1.5px solid rgba(255,255,255,.3);
      border-radius: var(--radius-s);
      padding: .75rem 1rem;
      min-height: 50px;
      display: flex;
      align-items: center;
    }
    .ei-result-val {
      font-size: 1.6rem;
      font-weight: 800;
      color: white;
    }
    .ei-result-label {
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: rgba(255,255,255,.75);
      margin-bottom: .4rem;
    }
    .ei-fact-row {
      display: flex;
      gap: 1.5rem;
      margin-top: 1.25rem;
      flex-wrap: wrap;
    }
    .ei-fact {
      background: rgba(255,255,255,.12);
      border-radius: var(--radius-s);
      padding: .65rem 1rem;
      font-size: .87rem;
      color: rgba(255,255,255,.9);
    }
    .ei-fact strong { color: white; }

    /* ── Call-out rates ──────────────────────────────────────────── */
    .callout-cards {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin: 1rem 0;
    }
    @media (max-width: 500px) { .callout-cards { grid-template-columns: 1fr; } }
    .callout-card {
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem;
      text-align: center;
    }
    .callout-card.primary { border-color: var(--primary); background: var(--accent); }
    .callout-pct {
      font-size: 2.2rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }
    .callout-time {
      font-size: .82rem;
      font-weight: 700;
      color: var(--gray-500);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-top: .3rem;
    }
    .callout-desc {
      font-size: .85rem;
      color: var(--gray-600);
      margin-top: .5rem;
    }

    /* ── Section headers ─────────────────────────────────────────── */
    .ttoc-section { margin-bottom: 2.25rem; }
    .ttoc-section-head {
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
    .ttoc-icon {
      width: 34px; height: 34px;
      background: var(--accent);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .ttoc-icon svg {
      width: 17px; height: 17px;
      stroke: var(--primary); fill: none;
      stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    }

    /* ── Experience credit table ─────────────────────────────────── */
    .exp-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .92rem;
      margin: .75rem 0;
    }
    .exp-table th {
      background: var(--primary);
      color: white;
      padding: .6rem 1rem;
      text-align: left;
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .06em;
    }
    .exp-table td {
      padding: .65rem 1rem;
      border-bottom: 1px solid var(--gray-200);
      color: var(--text);
      line-height: 1.5;
    }
    .exp-table tr:last-child td { border-bottom: none; }
    .exp-table tr:nth-child(even) td { background: var(--off-white); }
    .exp-table td strong { color: var(--primary); }

    /* ── Day-21 milestone ────────────────────────────────────────── */
    .milestone {
      background: #f0faf4;
      border-left: 4px solid var(--primary);
      border-radius: 0 var(--radius-s) var(--radius-s) 0;
      padding: 1rem 1.2rem;
      margin: 1rem 0;
    }
    .milestone strong { color: var(--primary); }
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
              <li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li>
              <li><a href="ttoc.php" class="active">TTOC Resources</a></li>
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
      <h1>TTOC Resources</h1>
      <p>Information and entitlements for Teachers Teaching on Call in SD54 — call-out rates, experience credit, sick leave, EI, and more.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- ── EI Hours Calculator ────────────────────────────────── -->
      <div class="ei-calculator">
        <h2>EI Hours Calculator</h2>
        <p class="sub">Teachers and TTOCs receive <strong>9.1 insurable hours per day</strong> worked for EI purposes — confirmed by the BCTF. Use this calculator to find out how many days you need to qualify.</p>

        <div class="ei-calc-grid">
          <div class="ei-calc-field">
            <label>Hours required</label>
            <input type="number" id="ei-hours" placeholder="e.g. 600" min="1" max="2000" value="600">
          </div>
          <div class="ei-calc-field">
            <label>Hours per day</label>
            <select id="ei-hpd">
              <option value="9.1" selected>9.1 hrs/day (SD54 standard)</option>
              <option value="9.1">9.1 hrs/day (confirmed by BCTF)</option>
            </select>
          </div>
          <div class="ei-calc-field">
            <div class="ei-result-label">Days you need to work</div>
            <div class="ei-result">
              <span class="ei-result-val" id="ei-result">66</span>
            </div>
          </div>
        </div>

        <div class="ei-fact-row">
          <div class="ei-fact"><strong>9.1 hours</strong> = 1 teaching day (EI)</div>
          <div class="ei-fact"><strong>600 hours</strong> = standard mat leave threshold → <strong>66 days</strong></div>
          <div class="ei-fact"><strong>420 hours</strong> = new entrant threshold → <strong>47 days</strong></div>
        </div>
      </div>

      <!-- ── Call-Out Rates ─────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.65 3.18 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.54a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
          Call-Out Compensation
        </div>
        <p>When you are called out for only part of the day, your compensation is prorated as follows:</p>
        <div class="callout-cards">
          <div class="callout-card primary">
            <div class="callout-pct">0.60</div>
            <div class="callout-time">Morning Call-Out</div>
            <div class="callout-desc">Called in for the morning only — you receive 60% of your daily rate.</div>
          </div>
          <div class="callout-card">
            <div class="callout-pct">0.40</div>
            <div class="callout-time">Afternoon Call-Out</div>
            <div class="callout-desc">Called in for the afternoon only — you receive 40% of your daily rate.</div>
          </div>
        </div>
      </div>

      <!-- ── Experience Credit ──────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></div>
          Experience Credit
        </div>
        <p>Understanding how your days count toward experience is critical for salary grid placement and seniority. There are two distinct types of experience credit — make sure you know which applies to you.</p>

        <table class="exp-table">
          <thead>
            <tr><th>Credit Type</th><th>Threshold</th><th>Effect</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>TTOC Experience Credit</strong></td>
              <td><strong>170 full-time TTOC days</strong> = 1 year of experience</td>
              <td>Counts toward salary grid placement as a TTOC</td>
            </tr>
            <tr>
              <td><strong>Contract Experience Credit</strong></td>
              <td>Based on contract assignment terms</td>
              <td>Applies when moving to a continuing or long-term position</td>
            </tr>
          </tbody>
        </table>

        <div class="info-box" style="font-size:.9rem;">
          <strong>See LOU No. 11 (page 129)</strong> of the Local Collective Agreement for specific timelines on transferring TTOC days to the experience bank.
        </div>
        <a href="documents/ttoc-experience-credit.pdf" download class="btn btn-outline" style="margin-top:.75rem;display:inline-block;font-size:.9rem;">
          ↓ Download: Understanding TTOC Experience Credit (PDF)
        </a>
      </div>

      <!-- ── Sick Leave ─────────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
          Sick Leave
        </div>
        <p>TTOCs are entitled to <strong>5 sick days</strong> under the Employment Standards Act. Currently, the district applies these days to situations where you are already booked in an assignment and illness prevents you from working that day.</p>

        <div class="milestone">
          <strong>Day 21 Milestone:</strong> Once you have been in the same assignment continuously for <strong>21 days</strong>, you gain access to the standard teacher illness provisions under <strong>Clause G.22</strong> of the collective agreement — the same sick leave entitlements as continuing teachers.
        </div>

        <div class="info-box" style="font-size:.9rem;margin-top:.5rem;">
          A provincial grievance is currently active regarding TTOC sick leave when illness prevents accepting work (as opposed to cancelling a booked assignment). Contact the BVTU president for the latest status.
        </div>
      </div>

      <!-- ── Pro-D Days ─────────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          Professional Development Days
        </div>
        <p>TTOCs are entitled to Pro-D day pay when <strong>both</strong> of the following conditions are met:</p>
        <ul style="font-size:.93rem;line-height:1.9;padding-left:1.3rem;color:var(--text);">
          <li>You have reached <strong>Day 21</strong> in your current continuous assignment, <em>and</em></li>
          <li>The Pro-D day falls within your regular scheduled days in that assignment.</li>
        </ul>
        <p style="font-size:.9rem;color:var(--gray-600);margin-top:.75rem;">The district may consider payment in other circumstances on a case-by-case basis. Contact the SD54 payroll/HR office directly to discuss your situation.</p>
      </div>

      <!-- ── Benefits ───────────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
          Benefits &amp; District Committees
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="detail-two-col">
          <div class="info-box" style="margin:0;">
            <strong>Extended Health &amp; Dental</strong><br>
            TTOCs are eligible to enroll in district Extended Health and Dental plans through Pacific Blue Cross. You pay <strong>100% of the premium</strong> (both the employer and employee portions). Enrollment must meet eligibility requirements each year.
          </div>
          <div class="info-box" style="margin:0;">
            <strong>District Committees</strong><br>
            TTOCs can serve on district committees. Union release time may also be available for union-related work. Contact the BVTU president to learn about current committee opportunities.
          </div>
        </div>
        <style>
          @media (max-width:580px) { .detail-two-col { grid-template-columns: 1fr !important; } }
        </style>
      </div>

      <!-- ── Union Release Time ─────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
          Union Release Time
        </div>
        <p>Release time from the union is available for union-related work, including committee participation and BCTF events. For information on how to enter union release time in Atrieve, see the <a href="atrieve.php">Atrieve Release Time guide</a>.</p>
      </div>

      <!-- ── Key Resource ───────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
          Key Resource
        </div>
        <p>The BCTF publishes <em>The Practice of Teaching: A Handbook for New Teachers and TTOCs</em> — an excellent overview of your rights, responsibilities, and professional practice as a substitute educator in BC.</p>
        <a href="https://www.bctf.ca" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:.5rem;display:inline-block;font-size:.9rem;">Visit BCTF for Resources →</a>
      </div>

      <!-- ── Contact ────────────────────────────────────────────── -->
      <div class="info-box">
        <p><strong>Questions about your TTOC entitlements?</strong> Contact BVTU President Cody Lind at <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a> or by phone at 613-485-3127.</p>
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
  <script>
    // EI Calculator
    function calcEI() {
      const hours = parseFloat(document.getElementById('ei-hours').value) || 0;
      const hpd   = parseFloat(document.getElementById('ei-hpd').value)   || 9.1;
      const days  = hours > 0 ? Math.ceil(hours / hpd) : '—';
      document.getElementById('ei-result').textContent = days;
    }
    document.getElementById('ei-hours').addEventListener('input', calcEI);
    document.getElementById('ei-hpd').addEventListener('change', calcEI);
    calcEI();
  </script>
</body>
</html>
