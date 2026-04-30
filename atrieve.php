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
  <title>Release Time &amp; Atrieve Entries — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="How to enter union-paid release time in Atrieve for SD54 teachers — BCTF and BVTU events, codes, approval process.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>

    /* ── Warning banner ──────────────────────────────────────────── */
    .atrieve-warning {
      background: #fff7ed;
      border: 2px solid #f59e0b;
      border-radius: var(--radius);
      padding: 1.25rem 1.5rem;
      display: flex;
      gap: 1rem;
      align-items: flex-start;
      margin-bottom: 2rem;
    }
    .atrieve-warning-icon {
      font-size: 1.6rem;
      line-height: 1;
      flex-shrink: 0;
    }
    .atrieve-warning strong { color: #92400e; }
    .atrieve-warning p { margin: .25rem 0 0; font-size: .92rem; color: #78350f; }

    /* ── Step cards ──────────────────────────────────────────────── */
    .steps-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin: 1rem 0 1.5rem;
    }
    .step-card {
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem;
      position: relative;
    }
    .step-num {
      width: 32px; height: 32px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      font-size: .85rem;
      font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: .75rem;
    }
    .step-card h4 {
      font-size: .95rem;
      font-weight: 700;
      color: var(--primary);
      margin: 0 0 .4rem;
    }
    .step-card p { font-size: .87rem; color: var(--gray-600); margin: 0; }

    /* ── Section headers ─────────────────────────────────────────── */
    .atrieve-section { margin-bottom: 2.25rem; }
    .atrieve-section-head {
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
    .atrieve-icon {
      width: 34px; height: 34px;
      background: var(--accent);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .atrieve-icon svg {
      width: 17px; height: 17px;
      stroke: var(--primary); fill: none;
      stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    }

    /* ── Event tables ────────────────────────────────────────────── */
    .event-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .9rem;
      margin: .75rem 0;
    }
    .event-table th {
      background: var(--primary);
      color: white;
      padding: .6rem 1rem;
      text-align: left;
      font-size: .8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .event-table th:first-child { border-radius: 6px 0 0 0; }
    .event-table th:last-child  { border-radius: 0 6px 0 0; }
    .event-table td {
      padding: .65rem 1rem;
      border-bottom: 1px solid var(--border);
      vertical-align: top;
    }
    .event-table tr:last-child td { border-bottom: none; }
    .event-table tr:hover td { background: var(--off-white); }
    .event-table .freq-badge {
      display: inline-block;
      background: var(--accent);
      color: var(--primary);
      font-size: .75rem;
      font-weight: 700;
      border-radius: 4px;
      padding: .15rem .5rem;
      white-space: nowrap;
    }
    .event-table .month-note {
      font-size: .8rem;
      color: var(--gray-500);
      margin-top: .2rem;
    }

    /* ── Code pill ───────────────────────────────────────────────── */
    .code-pill {
      display: inline-block;
      font-family: monospace;
      background: var(--off-white);
      border: 1.5px solid var(--border);
      border-radius: 4px;
      padding: .1rem .45rem;
      font-size: .85rem;
      color: var(--primary);
      font-weight: 700;
    }

    /* ── Contact card ────────────────────────────────────────────── */
    .contact-card-atrieve {
      background: var(--primary);
      color: white;
      border-radius: var(--radius);
      padding: 1.5rem;
      display: flex;
      gap: 1.25rem;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 1.5rem;
    }
    .contact-card-atrieve .cc-icon {
      width: 48px; height: 48px;
      background: rgba(255,255,255,.15);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .contact-card-atrieve .cc-icon svg {
      width: 24px; height: 24px;
      stroke: white; fill: none;
      stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    }
    .contact-card-atrieve h3 { margin: 0 0 .25rem; color: white; font-size: 1rem; }
    .contact-card-atrieve p  { margin: 0; color: rgba(255,255,255,.8); font-size: .9rem; }
    .contact-card-atrieve a  { color: white; text-decoration: underline; }

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
              <li><a href="ttoc.php">TTOC Resources</a></li>
              <li><a href="atrieve.php" class="active">Release Time / Atrieve</a></li>
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
        <span>Release Time &amp; Atrieve</span>
      </div>
      <h1>Release Time &amp; Atrieve Entries</h1>
      <p class="hero-sub">How to enter union-paid release time in SD54's Atrieve system for BCTF and BVTU events.</p>
    </div>
  </section>

  <main class="main-content">
    <div class="container" style="max-width:800px">

      <!-- ── Critical warning ──────────────────────────────────────── -->
      <div class="atrieve-warning">
        <div class="atrieve-warning-icon">⚠️</div>
        <div>
          <strong>Get approval BEFORE entering leave in Atrieve</strong>
          <p>You must have a <strong>signed approval form from the BVTU President</strong> before entering any union-paid release time in Atrieve. Email the president first at <a href="mailto:lp54@bctf.ca" style="color:#92400e;font-weight:600;">lp54@bctf.ca</a> — the president tracks all union releases centrally to ensure accuracy and avoid over-claiming.</p>
        </div>
      </div>

      <!-- ── How it works ──────────────────────────────────────────── -->
      <div class="atrieve-section">
        <div class="atrieve-section-head">
          <div class="atrieve-icon">
            <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          How to Enter Release Time
        </div>

        <div class="steps-grid">
          <div class="step-card">
            <div class="step-num">1</div>
            <h4>Request Approval</h4>
            <p>Email <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a> describing the event, date(s), and number of days needed.</p>
          </div>
          <div class="step-card">
            <div class="step-num">2</div>
            <h4>Receive Signed Form</h4>
            <p>Wait for a signed approval form from the BVTU President confirming the release is authorized.</p>
          </div>
          <div class="step-card">
            <div class="step-num">3</div>
            <h4>Enter in Atrieve</h4>
            <p>Log in to Atrieve and enter the leave using the correct leave code for the event type.</p>
          </div>
          <div class="step-card">
            <div class="step-num">4</div>
            <h4>Keep Documentation</h4>
            <p>Retain your signed approval form. HR may request it as verification of the authorized release.</p>
          </div>
        </div>

        <div class="info-box">
          <p><strong>What is Atrieve?</strong> Atrieve is SD54's employee self-service system used to record leave, review pay stubs, and manage time-off requests. Union-paid release time is a specific leave category that must be entered with the correct leave code so that the union — not the district — is billed for your absence.</p>
        </div>
      </div>

      <!-- ── BCTF events ────────────────────────────────────────────── -->
      <div class="atrieve-section">
        <div class="atrieve-section-head">
          <div class="atrieve-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          </div>
          BCTF Business — Provincial Events
        </div>
        <p style="margin-bottom:1rem;color:var(--gray-600);font-size:.92rem;">These events are organized and funded by the BC Teachers' Federation. Use the <strong>BCTF Business</strong> leave code when entering these in Atrieve.</p>

        <table class="event-table">
          <thead>
            <tr>
              <th>Event</th>
              <th>Frequency</th>
              <th>Typical Timing</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Zone Meetings</strong></td>
              <td><span class="freq-badge">2× per year</span></td>
              <td>Fall &amp; Spring</td>
            </tr>
            <tr>
              <td><strong>Representative Assemblies (RA)</strong></td>
              <td><span class="freq-badge">3× per year</span></td>
              <td>
                October, January, May
                <div class="month-note">Three-day events in Vancouver</div>
              </td>
            </tr>
            <tr>
              <td><strong>Annual General Meeting (AGM)</strong></td>
              <td><span class="freq-badge">Yearly</span></td>
              <td>Spring (typically May/June)</td>
            </tr>
            <tr>
              <td><strong>Bargaining Training</strong></td>
              <td><span class="freq-badge">2× per year</span></td>
              <td>As scheduled by BCTF</td>
            </tr>
            <tr>
              <td><strong>Summer Conference</strong></td>
              <td><span class="freq-badge">Yearly</span></td>
              <td>August</td>
            </tr>
            <tr>
              <td><strong>Federation Leadership Institute (FLI)</strong></td>
              <td><span class="freq-badge">Yearly</span></td>
              <td>Winter (typically February)</td>
            </tr>
            <tr>
              <td><strong>Women's Institute</strong></td>
              <td><span class="freq-badge">Yearly</span></td>
              <td>February</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── BVTU events ────────────────────────────────────────────── -->
      <div class="atrieve-section">
        <div class="atrieve-section-head">
          <div class="atrieve-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          BVTU Business — Local Events
        </div>
        <p style="margin-bottom:1rem;color:var(--gray-600);font-size:.92rem;">These events are organized by BVTU locally. Use the <strong>BVTU Business</strong> leave code when entering these in Atrieve.</p>

        <table class="event-table">
          <thead>
            <tr>
              <th>Event / Activity</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>SURT Events</strong><br><span style="font-size:.82rem;color:var(--gray-500)">School Union Rep Team</span></td>
              <td>School-level union representative activities and meetings</td>
            </tr>
            <tr>
              <td><strong>Executive Committee Visits</strong></td>
              <td>BVTU executive members visiting schools on union business</td>
            </tr>
            <tr>
              <td><strong>Local Office Work Days</strong></td>
              <td>Authorized work days at the BVTU local office for union administration</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── Leave codes ────────────────────────────────────────────── -->
      <div class="atrieve-section">
        <div class="atrieve-section-head">
          <div class="atrieve-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          Atrieve Leave Codes
        </div>

        <p>When entering your leave in Atrieve, select the appropriate leave type from the dropdown. Common codes used for union release time:</p>

        <table class="event-table" style="margin-top:.75rem;">
          <thead>
            <tr>
              <th>Leave Type</th>
              <th>Used For</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="code-pill">BCTF Business</span></td>
              <td>All provincial BCTF events — RA, AGM, institutes, zone meetings, bargaining training</td>
            </tr>
            <tr>
              <td><span class="code-pill">BVTU Business</span></td>
              <td>All BVTU local events — SURT, exec visits, local office work days</td>
            </tr>
          </tbody>
        </table>

        <div class="info-box" style="margin-top:1rem;">
          <p>⚠️ <strong>Do not use a personal sick day or professional development code</strong> for union release time. Using the wrong code means the district absorbs the cost instead of the union — which can cause accounting errors and may affect your leave balance.</p>
        </div>
      </div>

      <!-- ── Who to contact ─────────────────────────────────────────── -->
      <div class="atrieve-section">
        <div class="atrieve-section-head">
          <div class="atrieve-icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.65 3.18 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.54a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          Questions &amp; Approval Requests
        </div>

        <div class="contact-card-atrieve">
          <div class="cc-icon">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div>
            <h3>Cody Lind — BVTU President</h3>
            <p>Email to request approval for release time: <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a></p>
            <p style="margin-top:.35rem;">All union release time requests must go through the president. The president tracks all releases centrally to ensure the union budget is managed correctly and members are not over-claiming.</p>
          </div>
        </div>
      </div>

      <!-- ── Related link ───────────────────────────────────────────── -->
      <div style="text-align:center;padding:1.5rem 0 .5rem;">
        <p style="color:var(--gray-600);font-size:.92rem;margin-bottom:.75rem;">Looking for information about TTOC entitlements, call-out rates, or the EI hours calculator?</p>
        <a href="ttoc.php" class="btn btn-outline" style="font-size:.9rem;">← Back to TTOC Resources</a>
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
