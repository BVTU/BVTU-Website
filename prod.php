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
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>Professional Development</h1>
      <p>Policies, funding opportunities, and resources to support your professional growth.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <?php
      // This section's pages, from the same definition as its menu
      // (inc/nav.php), so the two always agree.
      ?>
      <!-- Pro-D Portal card hidden until the portal is ready to launch. -->
      <?php bvtuSectionCards('prod'); ?>
      <ul class="resource-list" style="margin-bottom:2.5rem;">
        <li><a href="https://www.bctf.ca/topics/services-information/professional-development" target="_blank" rel="noopener">BCTF Pro-D resources — provincial policy, funding and learning</a></li>
      </ul>

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

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
