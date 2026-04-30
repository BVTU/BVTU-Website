<?php
/**
 * collab-grant.php — BVTU Collaboration Grant
 * Primer, FAQ, and embedded Microsoft Forms application.
 */
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
  <title>Collaboration Grant — BVTU</title>
  <meta name="description" content="The BVTU Collaboration Grant supports mentorship and professional collaboration for SD54 educators. Up to 3 release days per year — apply online.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    /* ── Page-level layout ─────────────────────────────────── */
    .grant-section { margin-bottom: 3.5rem; }
    .grant-section:last-child { margin-bottom: 0; }

    .grant-lead {
      font-size: 1.05rem;
      color: var(--gray-700);
      line-height: 1.75;
      max-width: 780px;
    }

    /* ── Overview grid: intro + eligibility card ───────────── */
    .grant-overview {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 2.5rem;
      align-items: start;
    }
    .grant-eligibility {
      background: var(--accent);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-l);
      padding: 1.75rem;
    }
    .grant-eligibility h3 {
      font-size: .95rem;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-bottom: 1rem;
    }
    .grant-eligibility ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: .6rem;
    }
    .grant-eligibility li {
      display: flex;
      gap: .6rem;
      align-items: flex-start;
      font-size: .9rem;
      color: var(--gray-700);
      line-height: 1.55;
    }
    .grant-eligibility li::before {
      content: '';
      flex-shrink: 0;
      width: 7px;
      height: 7px;
      background: var(--primary);
      border-radius: 50%;
      margin-top: .45rem;
    }

    /* ── Section heading style ─────────────────────────────── */
    .grant-h2 {
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 1.1rem;
      padding-bottom: .55rem;
      border-bottom: 2px solid var(--accent);
    }

    /* ── Guidelines two-col ────────────────────────────────── */
    .grant-guidelines {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.25rem;
    }
    .grant-guideline-card {
      background: var(--off-white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.3rem 1.5rem;
    }
    .grant-guideline-card h4 {
      font-size: .88rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: .65rem;
    }
    .grant-guideline-card ul {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: .45rem;
    }
    .grant-guideline-card li {
      font-size: .87rem;
      color: var(--gray-700);
      line-height: 1.55;
      padding-left: 1rem;
      position: relative;
    }
    .grant-guideline-card li::before {
      content: '–';
      position: absolute;
      left: 0;
      color: var(--primary);
      font-weight: 700;
    }

    /* ── Application steps ─────────────────────────────────── */
    .grant-steps {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      max-width: 780px;
    }
    .grant-step {
      display: flex;
      gap: 1.1rem;
      align-items: flex-start;
    }
    .grant-step-num {
      flex-shrink: 0;
      width: 32px;
      height: 32px;
      background: var(--primary);
      color: var(--white);
      border-radius: 50%;
      font-size: .85rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-top: .1rem;
    }
    .grant-step-body {
      font-size: .93rem;
      color: var(--gray-700);
      line-height: 1.65;
    }
    .grant-step-body strong { color: var(--text); }

    /* ── FAQ accordion ─────────────────────────────────────── */
    .faq-list {
      max-width: 820px;
      display: flex;
      flex-direction: column;
      gap: .5rem;
    }
    .faq-item {
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      background: var(--white);
    }
    .faq-question {
      width: 100%;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.25rem;
      background: none;
      border: none;
      cursor: pointer;
      font-family: var(--font);
      font-size: .93rem;
      font-weight: 600;
      color: var(--text);
      text-align: left;
      line-height: 1.45;
      transition: background .15s;
    }
    .faq-question:hover { background: var(--off-white); }
    .faq-question[aria-expanded="true"] { color: var(--primary); }
    .faq-icon {
      flex-shrink: 0;
      width: 20px;
      height: 20px;
      color: var(--primary);
      transition: transform .22s ease;
    }
    .faq-question[aria-expanded="true"] .faq-icon { transform: rotate(45deg); }
    .faq-answer {
      display: none;
      padding: 0 1.25rem 1.1rem;
      font-size: .9rem;
      color: var(--gray-700);
      line-height: 1.7;
    }
    .faq-answer.open { display: block; }

    /* ── Apply section ─────────────────────────────────────── */
    .grant-apply-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .grant-apply-header h2 { margin-bottom: 0; border-bottom: none; padding-bottom: 0; }
    .grant-apply-note {
      font-size: .88rem;
      color: var(--gray-500);
    }
    .grant-form-wrap {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-l);
      overflow: hidden;
      box-shadow: var(--shadow);
    }
    .grant-form-wrap iframe {
      display: block;
      width: 100%;
      height: 840px;
      border: none;
    }

    /* ── Responsive ────────────────────────────────────────── */
    @media (max-width: 860px) {
      .grant-overview   { grid-template-columns: 1fr; }
      .grant-guidelines { grid-template-columns: 1fr; }
      .grant-form-wrap iframe { height: 700px; }
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
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown">
            <a href="documents.php">Documents</a>
            <ul class="dropdown">
              <li><a href="documents.php">All Documents</a></li>
              <li><a href="collective-agreement.php">Collective Agreement</a></li>
            </ul>
          </li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="life-insurance.php">Life Insurance</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php" class="active">Collaboration Grant</a></li>
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
      <h1>Collaboration Grant</h1>
      <p>Mentorship and professional collaboration — funded by your local union, available to all BVTU members.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- ── Background & Purpose ──────────────────────────── -->
      <div class="grant-section">
        <div class="grant-overview">
          <div>
            <p class="grant-lead" style="margin-bottom:1.25rem;">
              As part of the 2019–2022 provincial collective bargaining process, a fund was established to provide collaboration support through local unions. This opportunity is completely voluntary and is distinct from anything SD54 may offer independently.
            </p>
            <h2 class="grant-h2">Who This Is For</h2>
            <p style="font-size:.93rem;color:var(--gray-700);line-height:1.7;max-width:640px;">
              The grant is designed to support teachers at key transition points in their careers — but any member who self-identifies as a candidate is welcome to apply:
            </p>
            <ul style="list-style:none;margin-top:1rem;display:flex;flex-direction:column;gap:.6rem;max-width:640px;">
              <li style="display:flex;gap:.75rem;align-items:flex-start;font-size:.93rem;color:var(--gray-700);line-height:1.55;">
                <span style="flex-shrink:0;width:8px;height:8px;background:var(--primary);border-radius:50%;margin-top:.45rem;"></span>
                Teachers in the <strong>first five years</strong> of their career
              </li>
              <li style="display:flex;gap:.75rem;align-items:flex-start;font-size:.93rem;color:var(--gray-700);line-height:1.55;">
                <span style="flex-shrink:0;width:8px;height:8px;background:var(--primary);border-radius:50%;margin-top:.45rem;"></span>
                Experienced teachers in their <strong>first or second year in a significantly different position</strong>
              </li>
              <li style="display:flex;gap:.75rem;align-items:flex-start;font-size:.93rem;color:var(--gray-700);line-height:1.55;">
                <span style="flex-shrink:0;width:8px;height:8px;background:var(--primary);border-radius:50%;margin-top:.45rem;"></span>
                Teachers who <strong>self-identify</strong> as candidates for collaboration or mentorship support
              </li>
            </ul>
          </div>

          <div class="grant-eligibility">
            <h3>At a Glance</h3>
            <ul>
              <li>$40,000 fund held by BVTU</li>
              <li>Available to K–12, district &amp; itinerant staff, and TTOCs</li>
              <li>Up to <strong>3 release days</strong> per member per year</li>
              <li>Funds cover TTOC release time only — not materials or technology</li>
              <li>Applications reviewed monthly by the BVTU Mentorship Sub-Committee</li>
              <li>Approved by the first week of each month; notifications by the 15th</li>
            </ul>
          </div>
        </div>
      </div>

      <!-- ── Guidelines ────────────────────────────────────── -->
      <div class="grant-section">
        <h2 class="grant-h2">Fund Guidelines</h2>
        <div class="grant-guidelines">

          <div class="grant-guideline-card">
            <h4>Eligible Activities</h4>
            <ul>
              <li>Release time to observe another teacher's practice</li>
              <li>Collaboration with a colleague on teaching and learning</li>
              <li>Voluntary peer mentorship arrangements</li>
            </ul>
          </div>

          <div class="grant-guideline-card">
            <h4>How Funds Are Used</h4>
            <ul>
              <li>Funds cover TTOC release costs for contract teachers</li>
              <li>TTOC applicants are reimbursed at their daily TTOC rate</li>
              <li>No funding for consumables, resources, or technology</li>
            </ul>
          </div>

          <div class="grant-guideline-card">
            <h4>Priorities &amp; Limits</h4>
            <ul>
              <li>Priority given to early-career and newly-repositioned teachers</li>
              <li>Committee aims for broad availability — more members, fewer days each</li>
              <li>Annual limit of 3 release days per member</li>
              <li>Self-identified candidates are accepted; early-career teachers given priority</li>
            </ul>
          </div>

          <div class="grant-guideline-card">
            <h4>Booking Absences</h4>
            <ul>
              <li>Once approved, book absences in Atrieve using <em>"Other BVTU business"</em></li>
              <li>Release days are charged to the District Pro-D account set up for this purpose</li>
              <li>Do not book before receiving written approval</li>
            </ul>
          </div>

        </div>
      </div>

      <!-- ── Application Process ───────────────────────────── -->
      <div class="grant-section">
        <h2 class="grant-h2">Application Process</h2>
        <div class="grant-steps">

          <div class="grant-step">
            <div class="grant-step-num">1</div>
            <div class="grant-step-body">
              <strong>Complete the application form</strong> below on this page. Describe your collaboration goals, your partner (if identified), and any other relevant details.
            </div>
          </div>

          <div class="grant-step">
            <div class="grant-step-num">2</div>
            <div class="grant-step-body">
              <strong>Applications are reviewed at the monthly BVTU Executive Meeting</strong> — usually the first week of every month. You'll be notified of the outcome by the 15th.
            </div>
          </div>

          <div class="grant-step">
            <div class="grant-step-num">3</div>
            <div class="grant-step-body">
              <strong>Once approved, book your release time</strong> in Atrieve using the <em>"Other BVTU business"</em> code.
            </div>
          </div>

          <div class="grant-step">
            <div class="grant-step-num">4</div>
            <div class="grant-step-body">
              <strong>If you've already identified a mentor/collaborator</strong> and they've agreed, proceed as planned. <strong>If you need help finding a partner</strong>, the BVTU will assist — reach out to the local president or your colleagues.
            </div>
          </div>

        </div>
      </div>

      <!-- ── FAQ ──────────────────────────────────────────── -->
      <div class="grant-section">
        <h2 class="grant-h2">Frequently Asked Questions</h2>
        <div class="faq-list" id="faq-list">

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              What is the process for finding a collaboration teacher?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              The process is flexible and up to you. Find a teacher you'd like to collaborate with, have a conversation, and determine if you're both interested in working together on the grant.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              What if I want to apply but can't find a collaboration partner?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              No problem. Simply fill out the application form and include details about your needs and areas of focus. The BVTU is available to assist in connecting you with a suitable collaboration partner.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              Can I work with a retired teacher?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              Yes — you can collaborate with a retired teacher as long as they hold an active teaching certificate.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              Do both teachers need to submit individual applications?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              No. Only one application is needed. The lead teacher (the one initiating the collaboration) submits the form and includes the other teacher as a collaborator.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              How long does it take to hear back after applying?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              The BVTU reviews all submissions at the monthly Executive Meeting and notifies applicants by the 15th of each month.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              Can I reapply every year?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              Yes. Each year you are eligible for up to 3 days of release time. Teachers who have not yet accessed the fund will be given priority.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              How are the collaboration days scheduled?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              The 3 collaboration days are flexible and can be scheduled based on both teachers' availability.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              What are the expectations for the collaboration?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              The collaboration should focus on enhancing teaching practices, improving student learning outcomes, or developing new instructional strategies. Both teachers should actively participate and work toward any agreed-upon goals.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              Are there funding restrictions — can I use it for materials or technology?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              The grant covers the cost of release time only (up to 3 days). Funding for materials, resources, or technology is not included.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              Can the grant be used for virtual or remote collaborations?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              Yes. Virtual or remote collaborations are allowed. As long as you and your partner are working together to achieve the project goals, the format — in-person or virtual — can be flexible.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              Can I apply if I already have funding from another source?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              Yes. You can still apply for the collaboration grant even if you have other funding. The grant is specifically for teacher collaboration and mentorship, so there's no conflict with other funding sources.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              How do I report on the outcomes of the collaboration?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              After completing your collaboration, you'll be asked to submit a brief summary outlining the outcomes, insights gained, and impact on your teaching. Details will be provided by the BVTU upon completion.
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question" aria-expanded="false">
              I have more questions — what do I do?
              <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <div class="faq-answer">
              Reach out to the BVTU directly — we're happy to help. Use the contact form or speak to the local president.
              <br><br>
              <a href="contact.php" style="color:var(--primary);font-weight:600;">Contact BVTU →</a>
            </div>
          </div>

        </div>
      </div>

      <!-- ── Application Form ──────────────────────────────── -->
      <div class="grant-section" id="apply">
        <div class="grant-apply-header">
          <h2 class="grant-h2" style="margin-bottom:0;border-bottom:none;padding-bottom:0;">Apply Now</h2>
          <p class="grant-apply-note">Applications reviewed monthly · notifications by the 15th</p>
        </div>
        <div class="grant-form-wrap">
          <iframe
            src="https://forms.office.com/Pages/ResponsePage.aspx?id=DQSIkWdsW0yxEjajBLZtrQAAAAAAAAAAAANAAV60Yf9UNjBVWU5KTTBGTjYwMVBQVkg2TVQxSzkzNS4u&embed=true"
            title="BVTU Collaboration Grant Application"
            allowfullscreen
            webkitallowfullscreen
            mozallowfullscreen
            msallowfullscreen>
          </iframe>
        </div>
      </div>

    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-grid container">
      <div>
        <h4>Bulkley Valley Teachers' Union</h4>
        <p>Local of the BC Teachers' Federation<br>School District 54 — Smithers, BC</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <ul class="footer-nav-list">
          <li><a href="about.php">About BVTU</a></li>
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Member Resources</a></li>
          <li><a href="life-insurance.php">Life Insurance</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
          <li><a href="prod.php">PRO-D</a></li>
        </ul>
      </div>
      <div>
        <h4>Resources</h4>
        <ul class="footer-nav-list">
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="members/login.php">Member Login</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Smithers, BC</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
  <script>
    // FAQ accordion
    document.querySelectorAll('.faq-question').forEach(btn => {
      btn.addEventListener('click', () => {
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        const answer   = btn.nextElementSibling;

        // Collapse all others
        document.querySelectorAll('.faq-question').forEach(b => {
          b.setAttribute('aria-expanded', 'false');
          b.nextElementSibling.classList.remove('open');
        });

        // Toggle this one
        if (!expanded) {
          btn.setAttribute('aria-expanded', 'true');
          answer.classList.add('open');
        }
      });
    });
  </script>
</body>
</html>
