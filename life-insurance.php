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
  <title>Life Insurance — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BCTF-BCSTA Group Life Insurance Plan EB — coverage details, eligibility, beneficiary designation, and claims information for SD54 teachers.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    /* ── Coverage highlight cards ─────────────────────────────────── */
    .coverage-highlights {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin: 1.5rem 0 2rem;
    }
    .coverage-highlight {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.4rem 1.25rem 1.2rem;
      text-align: center;
    }
    .coverage-highlight.primary {
      border-color: var(--primary);
      background: var(--accent);
    }
    .coverage-highlight-value {
      font-size: 2rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }
    .coverage-highlight-label {
      font-size: .82rem;
      font-weight: 600;
      color: var(--gray-500);
      text-transform: uppercase;
      letter-spacing: .05em;
      margin-top: .4rem;
    }

    /* ── Example box ─────────────────────────────────────────────── */
    .example-box {
      background: #f0faf4;
      border-left: 4px solid var(--primary);
      border-radius: 0 var(--radius-s) var(--radius-s) 0;
      padding: 1.1rem 1.25rem;
      margin: 1.25rem 0;
    }
    .example-box strong {
      display: block;
      font-size: .82rem;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--primary);
      margin-bottom: .4rem;
    }
    .example-box p { margin: 0; font-size: .95rem; color: var(--text); line-height: 1.7; }

    /* ── Section icon headers ────────────────────────────────────── */
    .li-section { margin-bottom: 2.5rem; }
    .li-section-header {
      display: flex;
      align-items: center;
      gap: .75rem;
      margin-bottom: .9rem;
      padding-bottom: .6rem;
      border-bottom: 2px solid var(--accent);
    }
    .li-section-icon {
      width: 36px;
      height: 36px;
      background: var(--accent);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .li-section-icon svg {
      width: 18px;
      height: 18px;
      stroke: var(--primary);
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    .li-section-header h2 {
      font-size: 1.2rem;
      font-weight: 800;
      color: var(--primary);
      margin: 0;
    }

    /* ── Two-column detail grid ──────────────────────────────────── */
    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-top: .5rem;
    }
    @media (max-width: 600px) { .detail-grid { grid-template-columns: 1fr; } }
    .detail-card {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: var(--radius-s);
      padding: 1rem 1.1rem;
    }
    .detail-card h4 {
      font-size: .88rem;
      font-weight: 700;
      color: var(--primary);
      margin: 0 0 .35rem;
    }
    .detail-card p, .detail-card ul {
      font-size: .9rem;
      color: var(--gray-700);
      margin: 0;
      line-height: 1.65;
    }
    .detail-card ul { padding-left: 1.15rem; }
    .detail-card ul li { margin-bottom: .2rem; }

    /* ── Timeline list ───────────────────────────────────────────── */
    .timeline-list {
      list-style: none;
      padding: 0;
      margin: .5rem 0 0;
    }
    .timeline-list li {
      display: flex;
      gap: .9rem;
      align-items: flex-start;
      padding: .7rem 0;
      border-bottom: 1px solid var(--gray-200);
      font-size: .93rem;
      color: var(--text);
      line-height: 1.6;
    }
    .timeline-list li:last-child { border-bottom: none; }
    .timeline-dot {
      width: 22px;
      height: 22px;
      background: var(--primary);
      border-radius: 50%;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-top: 2px;
    }
    .timeline-dot svg {
      width: 12px;
      height: 12px;
      stroke: white;
      fill: none;
      stroke-width: 2.5;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    /* ── Contact card ────────────────────────────────────────────── */
    .contact-card {
      background: var(--primary);
      color: white;
      border-radius: var(--radius);
      padding: 1.75rem 2rem;
      display: flex;
      align-items: center;
      gap: 1.5rem;
      flex-wrap: wrap;
    }
    .contact-card-icon {
      width: 52px;
      height: 52px;
      background: rgba(255,255,255,.15);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .contact-card-icon svg {
      width: 26px;
      height: 26px;
      stroke: white;
      fill: none;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    .contact-card h3 { margin: 0 0 .2rem; font-size: 1.1rem; color: white; }
    .contact-card p  { margin: 0; font-size: .93rem; color: rgba(255,255,255,.8); }
    .contact-card a  { color: white; font-weight: 700; }

    /* ── Warning/notice box ──────────────────────────────────────── */
    .notice-box {
      background: #fffbeb;
      border: 1.5px solid #f59e0b;
      border-radius: var(--radius-s);
      padding: 1rem 1.25rem;
      font-size: .9rem;
      color: #78350f;
      line-height: 1.65;
      margin: 1rem 0;
    }
    .notice-box strong { color: #92400e; }
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
              <li><a href="life-insurance.php" class="active">Life Insurance</a></li>
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
      <h1>Group Life Insurance</h1>
      <p>BCTF–BCSTA Group Life Insurance Plan EB — your coverage as an SD54 teacher, explained clearly.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- ── Coverage at a Glance ──────────────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <h2>Your Coverage — Plan EB</h2>
        </div>

        <p>As an SD54 teacher, you are enrolled in <strong>Plan EB</strong> of the BCTF–BCSTA Group Life Insurance Plan, administered by <strong>Manulife</strong>. Your benefit is paid to your named beneficiary in the event of your death — from any cause, with no exclusions.</p>

        <div class="coverage-highlights">
          <div class="coverage-highlight primary">
            <div class="coverage-highlight-value">3×</div>
            <div class="coverage-highlight-label">Annual Salary</div>
          </div>
          <div class="coverage-highlight">
            <div class="coverage-highlight-value">$300K</div>
            <div class="coverage-highlight-label">Maximum Benefit</div>
          </div>
          <div class="coverage-highlight">
            <div class="coverage-highlight-value">Day 1</div>
            <div class="coverage-highlight-label">Coverage Starts</div>
          </div>
          <div class="coverage-highlight">
            <div class="coverage-highlight-value">Any</div>
            <div class="coverage-highlight-label">Cause of Death</div>
          </div>
        </div>

        <p>Your coverage equals <strong>three times your annual earnings</strong>, rounded up to the next higher multiple of $1,000 if not already a multiple of $1,000, with a maximum of $300,000.</p>

        <div class="example-box">
          <strong>Example Calculations</strong>
          <p>
            <strong>$70,500 salary</strong> → 3 × $70,500 = $211,500 → rounded up = <strong>$212,000 coverage</strong><br>
            <strong>$80,000 salary</strong> → 3 × $80,000 = $240,000 → already a multiple → <strong>$240,000 coverage</strong><br>
            <strong>$105,000 salary</strong> → 3 × $105,000 = $315,000 → exceeds cap → <strong>$300,000 coverage</strong>
          </p>
        </div>

        <p>Coverage adjusts automatically when your salary changes (e.g., moving up the salary grid each year).</p>
      </div>

      <!-- ── Eligibility & Enrollment ──────────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h2>Eligibility &amp; Enrollment</h2>
        </div>

        <p>All BCTF members in the SD54 bargaining unit are eligible for Plan EB. Coverage begins on your <strong>first day of employment</strong>, provided you are actively at work.</p>

        <div class="notice-box">
          <strong>Action required within 31 days:</strong> You must complete and return an <em>Application for Group Coverage / Designation of Beneficiary</em> form to your school district plan administrator within 31 days of becoming eligible. Contact your school office or HR to obtain this form.
        </div>
      </div>

      <!-- ── Designating a Beneficiary ─────────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <h2>Designating a Beneficiary</h2>
        </div>

        <p>You must name who will receive your life insurance benefit. Common choices are a spouse, children, or your estate. You may also name a <strong>contingent (backup) beneficiary</strong> who receives the benefit if your primary beneficiary predeceases you.</p>

        <div class="detail-grid">
          <div class="detail-card">
            <h4>Primary Beneficiary</h4>
            <p>The person (or persons) who receive the death benefit. Typically a spouse or common-law partner.</p>
          </div>
          <div class="detail-card">
            <h4>Contingent Beneficiary</h4>
            <p>A backup recipient if your primary beneficiary dies before you. Often children or another family member.</p>
          </div>
          <div class="detail-card">
            <h4>Updating Your Designation</h4>
            <p>Life changes — review your beneficiary after marriage, divorce, birth of a child, or death of a named beneficiary. Contact your school district HR office to update your form.</p>
          </div>
          <div class="detail-card">
            <h4>If No Beneficiary Is Named</h4>
            <p>The benefit is paid to your estate and distributed according to your will or provincial intestacy rules, which can delay payment and involve probate costs.</p>
          </div>
        </div>
      </div>

      <!-- ── Premium Contributions ──────────────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <h2>Your Premiums</h2>
        </div>

        <p>The amount you contribute each month is set out in your <strong>collective agreement with SD54</strong>. Premiums are deducted from your pay during the school year. If you are employed through to June 30, your coverage continues through the summer months without additional deductions.</p>
      </div>

      <!-- ── Coverage Continuity ────────────────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          </div>
          <h2>Coverage Continuity</h2>
        </div>

        <p>Your coverage can continue in several situations where you are not actively working:</p>

        <ul class="timeline-list">
          <li>
            <div class="timeline-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><strong>Approved leave of absence</strong> — Coverage can be maintained for up to <strong>3 years</strong> during an approved leave, provided you are not working more than 20 hours per week.</div>
          </li>
          <li>
            <div class="timeline-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><strong>Transfer between districts</strong> — Coverage from your former district continues to the end of the month. Coverage with your new district begins the following month (or September 1 if the transfer happens mid-summer).</div>
          </li>
          <li>
            <div class="timeline-dot"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><strong>Summer break</strong> — If you are actively employed through June 30, coverage continues automatically through the summer without any additional premiums.</div>
          </li>
        </ul>
      </div>

      <!-- ── Disability: Waiver of Premium ─────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h2>Disability — Waiver of Premium</h2>
        </div>

        <p>If you become <strong>totally disabled before age 65</strong>, you may apply for a <em>Life Waiver of Premium</em>. If approved, your life insurance coverage continues in full — <strong>without you paying any premiums</strong> — for as long as you remain disabled.</p>

        <div class="detail-grid">
          <div class="detail-card">
            <h4>How to Apply</h4>
            <p>Contact Manulife (1-866-318-2727) or your school district HR office to request the Waiver of Premium application package.</p>
          </div>
          <div class="detail-card">
            <h4>Qualification Period</h4>
            <p>There is an initial 6-month waiting period before the waiver takes effect. Continue paying premiums during this period.</p>
          </div>
        </div>
      </div>

      <!-- ── When Coverage Ends ──────────────────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <h2>When Coverage Ends</h2>
        </div>

        <p>Plan EB coverage ends at the <strong>earlier</strong> of the following:</p>

        <ul class="timeline-list">
          <li>
            <div class="timeline-dot"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
            <div><strong>Retirement</strong> — Coverage terminates at the end of the month you retire.</div>
          </li>
          <li>
            <div class="timeline-dot"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
            <div><strong>Age 70</strong> — Coverage terminates at the end of the month you turn 70, regardless of employment status.</div>
          </li>
          <li>
            <div class="timeline-dot"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
            <div><strong>End of employment</strong> — If you leave SD54 for any other reason, coverage ends at the end of that month.</div>
          </li>
        </ul>
      </div>

      <!-- ── Conversion Privilege ───────────────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
          </div>
          <h2>Conversion Privilege</h2>
        </div>

        <p>When your group coverage ends (due to retirement, termination, or leaving the plan), you have the right to convert to an <strong>individual life insurance policy</strong> without a medical exam or health questions.</p>

        <div class="detail-grid">
          <div class="detail-card">
            <h4>Time Limit</h4>
            <p>You must apply within <strong>31 days</strong> of your group coverage ending. After 31 days this right is lost permanently.</p>
          </div>
          <div class="detail-card">
            <h4>Maximum Amount</h4>
            <p>You can convert up to <strong>$200,000</strong> (or your group coverage amount, whichever is less) with no evidence of insurability required.</p>
          </div>
          <div class="detail-card">
            <h4>How to Convert</h4>
            <p>Contact Manulife directly at 1-866-318-2727 to request the conversion application before your 31-day window closes.</p>
          </div>
          <div class="detail-card">
            <h4>Why This Matters</h4>
            <p>This is especially valuable if your health has changed and you could not otherwise qualify for individual coverage. Don't miss the window.</p>
          </div>
        </div>
      </div>

      <!-- ── Optional Additional Coverage ──────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          </div>
          <h2>Optional Additional Coverage</h2>
        </div>

        <p>Want more coverage than Plan EB provides? BCTF members can purchase <strong>optional voluntary life insurance</strong> through the <strong>BCTF Advantage Program</strong>, underwritten by iA Financial Group. This is separate from your group plan and paid entirely by you.</p>

        <div class="info-box">
          <p>Visit <a href="https://www.bctf.ca/topics/services-information/benefits" target="_blank" rel="noopener">bctf.ca/benefits</a> or contact the BCTF directly to learn about optional coverage amounts and rates available to SD54 members.</p>
        </div>
      </div>

      <!-- ── How to Make a Claim ─────────────────────────────────────── -->
      <div class="li-section">
        <div class="li-section-header">
          <div class="li-section-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <h2>How to Make a Claim</h2>
        </div>

        <p>In the event of a death, the beneficiary (or executor of the estate) should contact Manulife as soon as possible to initiate the claims process. Your school district plan administrator can also help guide the family through the required paperwork.</p>

        <div class="contact-card">
          <div class="contact-card-icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.65 3.18 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.54a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          <div>
            <h3>Manulife Group Benefits</h3>
            <p><a href="tel:18663182727">1-866-318-2727</a> &nbsp;·&nbsp; Monday – Friday, 8:00 a.m. – 5:00 p.m. PST</p>
            <p style="margin-top:.4rem;">You can also contact your school district HR/plan administrator for assistance with claim forms.</p>
          </div>
        </div>
      </div>

      <!-- ── Source ─────────────────────────────────────────────────── -->
      <div class="info-box" style="margin-top:1rem;">
        <p>This page summarizes Plan EB of the <a href="https://www.bctf.ca/topics/services-information/benefits/bctf-bcsta-group-life-insurance-plan" target="_blank" rel="noopener">BCTF–BCSTA Group Life Insurance Plan</a>. For full plan details, policy documents, or questions not covered here, contact Manulife or the BCTF directly.</p>
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
