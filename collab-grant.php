<?php
/**
 * collab-grant.php — BVTU Collaboration Grant
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/collab-grant-db.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

$formSuccess = false;
$formError   = '';
$formData    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cg_submit'])) {
    // Sanitise inputs
    $f = function(string $k): string { return trim($_POST[$k] ?? ''); };
    $formData = [
        'name'              => $f('name'),
        'email'             => $f('email'),
        'school'            => $f('school'),
        'position'          => $f('position'),
        'years_in_role'     => $f('years_in_role'),
        'has_collaborator'  => ($f('has_collaborator') === 'yes'),
        'collaborator_name' => $f('collaborator_name'),
        'collaborator_school'=> $f('collaborator_school'),
        'needs_partner'     => isset($_POST['needs_partner']),
        'collaboration_desc'=> $f('collaboration_desc'),
        'goals'             => $f('goals'),
        'proposed_dates'    => $f('proposed_dates'),
        'days_requested'    => max(1, min(3, (int)$f('days_requested'))),
    ];

    // Validate and sanitise proposed_dates JSON
    $decodedDates = json_decode($formData['proposed_dates'], true);
    if (!is_array($decodedDates) || count($decodedDates) < 1) {
        $formData['proposed_dates'] = null;
        $formData['days_requested'] = 0;
    } else {
        // Keep only valid future weekday dates, max 3
        $today = date('Y-m-d');
        $clean = [];
        foreach (array_slice($decodedDates, 0, 3) as $d) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d >= $today) {
                $dow = (int)date('N', strtotime($d));
                if ($dow <= 5) $clean[] = $d; // weekdays only
            }
        }
        sort($clean);
        $formData['proposed_dates'] = json_encode($clean);
        $formData['days_requested'] = count($clean);
    }

    // Validation
    if (!$formData['name'])              $formError = 'Please enter your name.';
    elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL))
                                         $formError = 'Please enter a valid email address.';
    elseif (!$formData['school'])        $formError = 'Please enter your school.';
    elseif (!$formData['position'])      $formError = 'Please enter your current position.';
    elseif (!$formData['collaboration_desc']) $formError = 'Please describe your collaboration.';
    elseif (!$formData['goals'])         $formError = 'Please describe your goals.';
    elseif ($formData['days_requested'] < 1) $formError = 'Please select at least one preferred date.';

    if (!$formError) {
        try {
            $appId = cgSubmitApplication($formData);
            $formData['id'] = $appId;
            cgSendNewApplicationNotification($formData);
            cgSendSubmissionConfirmation($formData);
            $formSuccess = true;
            $formData = []; // clear form
        } catch (Exception $e) {
            $formError = 'Something went wrong saving your application. Please email lp54@bctf.ca directly.';
        }
    }
}
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
    /* ── At a Glance card ──────────────────────────────────── */
    .grant-eligibility {
      background: var(--accent);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-l);
      padding: 1.4rem 1.6rem;
    }
    .grant-eligibility h3 {
      font-size: .8rem;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: .07em;
      margin: 0 0 1rem;
    }
    .grant-eligibility ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .grant-eligibility li {
      position: relative;
      padding-left: 1rem;
      margin-bottom: .6rem;
      font-size: .88rem;
      color: var(--gray-700);
      line-height: 1.5;
    }
    .grant-eligibility li:last-child { margin-bottom: 0; }
    .grant-eligibility li::before {
      content: '';
      position: absolute;
      left: 0;
      top: .52em;
      width: 5px;
      height: 5px;
      background: var(--primary);
      border-radius: 50%;
    }

    /* ── Bullet list (Who can apply / What's covered) ──────── */
    .grant-bullet-list {
      list-style: none;
      padding: 0;
      margin: 0 0 1.5rem;
    }
    .grant-bullet-list li {
      position: relative;
      padding-left: 1.1rem;
      margin-bottom: .55rem;
      font-size: .93rem;
      color: var(--gray-700);
      line-height: 1.6;
    }
    .grant-bullet-list li:last-child { margin-bottom: 0; }
    .grant-bullet-list li::before {
      content: '';
      position: absolute;
      left: 0;
      top: .55em;
      width: 5px;
      height: 5px;
      background: var(--primary);
      border-radius: 50%;
    }
    .grant-bullet-list li.muted {
      color: var(--gray-500);
      font-style: italic;
    }
    .grant-bullet-list li.muted::before { background: var(--gray-300); }

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

    /* ── Native application form ───────────────────────────── */
    .grant-form {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      max-width: 720px;
    }
    .grant-form-group {
      display: flex;
      flex-direction: column;
      gap: .4rem;
    }
    .grant-form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }
    .grant-form label {
      font-size: .88rem;
      font-weight: 600;
      color: var(--gray-700);
    }
    .grant-form label .req {
      color: var(--primary);
      margin-left: .15rem;
    }
    .grant-form label .hint {
      font-weight: 400;
      color: var(--gray-400);
      font-size: .8rem;
      margin-left: .3rem;
    }
    .grant-form input[type="text"],
    .grant-form input[type="email"],
    .grant-form select,
    .grant-form textarea {
      border: 1.5px solid var(--gray-200);
      border-radius: 8px;
      padding: .65rem .9rem;
      font-size: .93rem;
      font-family: inherit;
      color: var(--gray-800);
      background: #fff;
      width: 100%;
      transition: border-color .2s, box-shadow .2s;
    }
    .grant-form input:focus,
    .grant-form select:focus,
    .grant-form textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26,107,53,.1);
    }
    .grant-form textarea { resize: vertical; }
    .grant-form-section-label {
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--gray-400);
      padding-bottom: .6rem;
      border-bottom: 1px solid var(--gray-100);
      margin-bottom: .25rem;
    }
    .grant-collab-toggle {
      display: flex;
      gap: .75rem;
    }
    .grant-collab-toggle label {
      display: flex;
      align-items: center;
      gap: .4rem;
      font-weight: 500;
      cursor: pointer;
    }
    .grant-collab-fields { display: none; }
    .grant-collab-fields.visible { display: contents; }
    .grant-partner-note { display: none; }
    .grant-partner-note.visible { display: block; }
    .grant-days-options {
      display: flex;
      gap: .75rem;
    }
    .grant-days-options label {
      display: flex;
      align-items: center;
      gap: .4rem;
      font-weight: 500;
      cursor: pointer;
    }
    .grant-form-submit {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .grant-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 8px;
      padding: .85rem 1rem;
      font-size: .9rem;
      color: #991b1b;
    }
    .grant-success {
      background: #f0f9f3;
      border: 1.5px solid #b3d9bf;
      border-radius: 10px;
      padding: 1.5rem 1.75rem;
      max-width: 720px;
    }
    .grant-success h3 {
      color: var(--primary);
      margin: 0 0 .5rem;
      font-size: 1.1rem;
    }
    .grant-success p { margin: 0; color: var(--gray-700); line-height: 1.65; font-size: .93rem; }

    /* ── Date picker calendar ──────────────────────────────── */
    .cg-cal {
      border: 1.5px solid var(--gray-200);
      border-radius: 10px;
      overflow: hidden;
      background: #fff;
      max-width: 420px;
      user-select: none;
    }
    .cg-cal-nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: .75rem 1rem;
      background: var(--off-white);
      border-bottom: 1px solid var(--gray-200);
    }
    .cg-cal-nav button {
      background: none;
      border: 1px solid var(--gray-200);
      border-radius: 6px;
      width: 30px; height: 30px;
      font-size: 1.1rem;
      line-height: 1;
      cursor: pointer;
      color: var(--gray-600);
      display: flex; align-items: center; justify-content: center;
      transition: background .15s, border-color .15s;
    }
    .cg-cal-nav button:hover:not(:disabled) { background: #fff; border-color: var(--primary); color: var(--primary); }
    .cg-cal-nav button:disabled { opacity: .35; cursor: not-allowed; }
    .cg-cal-nav-title { font-size: .92rem; font-weight: 700; color: var(--gray-800); }
    .cg-cal-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
    }
    .cg-cal-dow {
      padding: .5rem 0;
      text-align: center;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--gray-400);
    }
    .cg-cal-dow.weekend { color: var(--gray-300); }
    .cg-cal-day {
      aspect-ratio: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .88rem;
      color: var(--gray-700);
      cursor: pointer;
      border-radius: 6px;
      margin: 2px;
      position: relative;
      transition: background .12s, color .12s;
      font-weight: 500;
    }
    .cg-cal-day:hover:not(.disabled):not(.empty) { background: #e8f5ed; color: var(--primary); }
    .cg-cal-day.today { font-weight: 800; color: var(--primary); }
    .cg-cal-day.today::after {
      content: '';
      position: absolute;
      bottom: 3px; left: 50%;
      transform: translateX(-50%);
      width: 4px; height: 4px;
      border-radius: 50%;
      background: var(--primary);
    }
    .cg-cal-day.disabled, .cg-cal-day.weekend-day {
      color: var(--gray-300);
      cursor: not-allowed;
    }
    .cg-cal-day.empty { cursor: default; }
    .cg-cal-day.selected {
      background: var(--primary);
      color: #fff;
      font-weight: 700;
    }
    .cg-cal-day.selected:hover { background: #155a2a; }
    .cg-cal-day.selected .sel-num {
      position: absolute;
      top: 2px; right: 3px;
      font-size: .55rem;
      font-weight: 800;
      line-height: 1;
      opacity: .85;
    }
    /* Selected dates summary chips */
    .cg-selected-summary {
      display: flex;
      flex-wrap: wrap;
      gap: .45rem;
      min-height: 2rem;
      margin-top: .75rem;
    }
    .cg-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      background: #e8f5ed;
      border: 1px solid #b3d9bf;
      border-radius: 20px;
      padding: .3rem .75rem;
      font-size: .82rem;
      font-weight: 600;
      color: var(--primary);
    }
    .cg-chip button {
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
      line-height: 1;
      color: var(--primary);
      font-size: .9rem;
      display: flex; align-items: center;
      opacity: .6;
      transition: opacity .15s;
    }
    .cg-chip button:hover { opacity: 1; }
    .cg-cal-hint {
      font-size: .78rem;
      color: var(--gray-400);
      margin-top: .4rem;
    }

    /* ── Responsive ────────────────────────────────────────── */
    @media (max-width: 860px) {
      .grant-overview   { grid-template-columns: 1fr; }
      .grant-guidelines { grid-template-columns: 1fr; }
      .grant-form-row   { grid-template-columns: 1fr; }
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
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php" class="active">Collaboration Grant</a></li>
            </ul>
          </li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="library.php">Resource Library</a></li>
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

      <!-- ── Overview ─────────────────────────────────────── -->
      <div class="grant-section">
        <div class="grant-overview">

          <div>
            <h2 class="grant-h2">Who can apply</h2>
            <p style="font-size:.92rem;color:var(--gray-600);margin:0 0 .75rem;">Any BVTU member is welcome to apply. Priority is given to:</p>
            <ul class="grant-bullet-list">
              <li>Teachers in their <strong>first five years</strong> of teaching</li>
              <li>Experienced teachers in a <strong>significantly different position</strong> for the first or second year</li>
              <li>Any member who <strong>self-identifies</strong> as benefiting from mentorship or collaboration</li>
            </ul>

            <h2 class="grant-h2">What's covered</h2>
            <ul class="grant-bullet-list">
              <li>Release time to observe a colleague's classroom practice</li>
              <li>Collaboration with a colleague on teaching and learning</li>
              <li>Voluntary peer mentorship — in person or virtual</li>
              <li class="muted">Not covered: materials, consumables, or technology</li>
            </ul>
          </div>

          <div class="grant-eligibility">
            <h3>At a Glance</h3>
            <ul>
              <li>$40,000 fund held by BVTU</li>
              <li>Open to K–12, district &amp; itinerant staff, and TTOCs</li>
              <li>Up to <strong>3 release days</strong> per member per year</li>
              <li>TTOC costs covered for contract teachers; TTOCs reimbursed at their daily rate</li>
              <li>Once approved, book your absence in Atrieve as <em>"Other BVTU business"</em></li>
            </ul>
          </div>

        </div>
      </div>

      <!-- ── Application Form ──────────────────────────────── -->
      <div class="grant-section" id="apply">
        <h2 class="grant-h2">Apply Now</h2>

        <?php if ($formSuccess): ?>
          <div class="grant-success">
            <h3>✓ Application received — thank you!</h3>
            <p>We've sent a confirmation to your email address. We'll be in touch once your application has been reviewed. If you have any questions in the meantime, reach out at <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a>.</p>
          </div>

        <?php else: ?>

          <?php if ($formError): ?>
            <div class="grant-error" style="margin-bottom:1rem;"><?= htmlspecialchars($formError) ?></div>
          <?php endif; ?>

          <form class="grant-form" method="post" action="#apply" novalidate>

            <div class="grant-form-section-label">Your Information</div>

            <div class="grant-form-row">
              <div class="grant-form-group">
                <label for="cg-name">Full name <span class="req">*</span></label>
                <input type="text" id="cg-name" name="name" required
                  value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                  placeholder="Jane Smith">
              </div>
              <div class="grant-form-group">
                <label for="cg-email">Email address <span class="req">*</span></label>
                <input type="email" id="cg-email" name="email" required
                  value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                  placeholder="you@sd54.bc.ca">
              </div>
            </div>

            <div class="grant-form-row">
              <div class="grant-form-group">
                <label for="cg-school">School <span class="req">*</span></label>
                <input type="text" id="cg-school" name="school" required
                  value="<?= htmlspecialchars($formData['school'] ?? '') ?>"
                  placeholder="e.g. Smithers Secondary">
              </div>
              <div class="grant-form-group">
                <label for="cg-position">Current position / role <span class="req">*</span></label>
                <input type="text" id="cg-position" name="position" required
                  value="<?= htmlspecialchars($formData['position'] ?? '') ?>"
                  placeholder="e.g. Grade 4 teacher">
              </div>
            </div>

            <div class="grant-form-group" style="max-width:320px;">
              <label for="cg-years">How long have you been in this role?</label>
              <select id="cg-years" name="years_in_role">
                <option value="" <?= empty($formData['years_in_role']) ? 'selected' : '' ?>>Select…</option>
                <option value="First year"  <?= ($formData['years_in_role'] ?? '') === 'First year'  ? 'selected' : '' ?>>This is my first year</option>
                <option value="Second year" <?= ($formData['years_in_role'] ?? '') === 'Second year' ? 'selected' : '' ?>>This is my second year</option>
                <option value="3+ years"    <?= ($formData['years_in_role'] ?? '') === '3+ years'    ? 'selected' : '' ?>>Three or more years</option>
              </select>
            </div>

            <div class="grant-form-section-label" style="margin-top:.5rem;">Your Collaboration</div>

            <div class="grant-form-group">
              <label>Do you have a collaborator in mind?</label>
              <div class="grant-collab-toggle">
                <label>
                  <input type="radio" name="has_collaborator" value="yes" id="collab-yes"
                    <?= (($formData['has_collaborator'] ?? false) === true) ? 'checked' : '' ?>>
                  Yes, I have someone in mind
                </label>
                <label>
                  <input type="radio" name="has_collaborator" value="no" id="collab-no"
                    <?= (($formData['has_collaborator'] ?? null) === false) ? 'checked' : '' ?>>
                  Not yet
                </label>
              </div>
            </div>

            <div class="grant-form-row grant-collab-fields" id="collab-fields">
              <div class="grant-form-group">
                <label for="cg-collab-name">Collaborator's name</label>
                <input type="text" id="cg-collab-name" name="collaborator_name"
                  value="<?= htmlspecialchars($formData['collaborator_name'] ?? '') ?>"
                  placeholder="Their full name">
              </div>
              <div class="grant-form-group">
                <label for="cg-collab-school">Collaborator's school</label>
                <input type="text" id="cg-collab-school" name="collaborator_school"
                  value="<?= htmlspecialchars($formData['collaborator_school'] ?? '') ?>"
                  placeholder="e.g. Houston Secondary">
              </div>
            </div>

            <div class="grant-partner-note info-box" id="partner-note" style="margin:0;">
              <p style="margin:0;font-size:.9rem;">No problem — describe what you're looking for below and the BVTU will help connect you with a suitable partner.</p>
              <label style="display:flex;gap:.5rem;align-items:center;margin-top:.6rem;font-size:.9rem;cursor:pointer;">
                <input type="checkbox" name="needs_partner" value="1"
                  <?= !empty($formData['needs_partner']) ? 'checked' : '' ?>>
                Please help me find a collaborator
              </label>
            </div>

            <div class="grant-form-group">
              <label for="cg-desc">Describe your collaboration <span class="req">*</span>
                <span class="hint">What will you and your partner do together?</span>
              </label>
              <textarea id="cg-desc" name="collaboration_desc" rows="4" required
                placeholder="e.g. I'd like to observe a colleague's classroom practice around inquiry-based learning and then debrief together on strategies I can bring back to my class…"><?= htmlspecialchars($formData['collaboration_desc'] ?? '') ?></textarea>
            </div>

            <div class="grant-form-group">
              <label for="cg-goals">Your goals <span class="req">*</span>
                <span class="hint">What do you hope to achieve or learn as a result of this collaboration?</span>
              </label>
              <textarea id="cg-goals" name="goals" rows="4" required
                placeholder="e.g. I hope to strengthen my approach to formative assessment and gain confidence in differentiated instruction by seeing how an experienced colleague structures their lessons…"><?= htmlspecialchars($formData['goals'] ?? '') ?></textarea>
            </div>

            <div class="grant-form-group">
              <label>Preferred release day(s) <span class="req">*</span>
                <span class="hint">Select up to 3 weekdays — click a day to select or deselect it</span>
              </label>
              <div class="cg-cal" id="cg-cal"></div>
              <div class="cg-selected-summary" id="cg-chips"></div>
              <p class="cg-cal-hint" id="cg-hint">No dates selected yet. Select up to 3 preferred days.</p>
              <input type="hidden" name="proposed_dates" id="cg-proposed-dates" value="[]">
              <input type="hidden" name="days_requested"  id="cg-days-count"     value="0">
            </div>

            <div class="grant-form-submit">
              <button type="submit" name="cg_submit" id="cg-submit" class="btn btn-primary" style="padding:.65rem 1.5rem;" disabled>Submit application</button>
              <span style="font-size:.82rem;color:var(--gray-400);">You'll receive a confirmation email right away.</span>
            </div>

          </form>

        <?php endif; ?>
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
              The BVTU reviews all submissions at the Executive Meeting. You'll be notified by email once a decision has been made.
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
          
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Member Resources</a></li>
          <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
        </ul>
      </div>
      <div>
        <h4>Resources</h4>
        <ul class="footer-nav-list">
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="library.php">Resource Library</a></li>
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
  // ── Collaboration Grant date picker ──────────────────────────────────────
  (function () {
    const calEl    = document.getElementById('cg-cal');
    if (!calEl) return; // form not on page (e.g. success state)

    const chipsEl  = document.getElementById('cg-chips');
    const hintEl   = document.getElementById('cg-hint');
    const datesIn  = document.getElementById('cg-proposed-dates');
    const countIn  = document.getElementById('cg-days-count');
    const submitBtn= document.getElementById('cg-submit');

    const MAX = 3;
    const MONTHS = ['January','February','March','April','May','June',
                    'July','August','September','October','November','December'];
    const DAYS  = ['Su','Mo','Tu','We','Th','Fr','Sa'];

    let selected = []; // sorted array of 'YYYY-MM-DD'
    let viewYear, viewMonth;

    // Start on current month; don't allow navigating to past months
    const now = new Date();
    viewYear  = now.getFullYear();
    viewMonth = now.getMonth(); // 0-indexed

    function toDateStr(y, m, d) {
      return y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }

    function todayStr() {
      return toDateStr(now.getFullYear(), now.getMonth(), now.getDate());
    }

    function isWeekend(y, m, d) {
      const dow = new Date(y, m, d).getDay(); // 0=Sun, 6=Sat
      return dow === 0 || dow === 6;
    }

    function isPast(dateStr) {
      return dateStr < todayStr();
    }

    function formatDisplay(dateStr) {
      const [y, m, d] = dateStr.split('-').map(Number);
      const date = new Date(y, m - 1, d);
      return date.toLocaleDateString('en-CA', { weekday: 'short', month: 'short', day: 'numeric' });
    }

    function toggleDate(dateStr) {
      const idx = selected.indexOf(dateStr);
      if (idx >= 0) {
        selected.splice(idx, 1);
      } else {
        if (selected.length >= MAX) return;
        selected.push(dateStr);
        selected.sort();
      }
      syncInputs();
      renderChips();
      renderCalendar(); // refresh day states
    }

    function syncInputs() {
      datesIn.value  = JSON.stringify(selected);
      countIn.value  = selected.length;
      submitBtn.disabled = selected.length === 0;
      if (hintEl) {
        if (selected.length === 0) {
          hintEl.textContent = 'No dates selected yet. Select up to 3 preferred days.';
        } else if (selected.length < MAX) {
          hintEl.textContent = selected.length + ' day' + (selected.length > 1 ? 's' : '') + ' selected. You can add ' + (MAX - selected.length) + ' more.';
        } else {
          hintEl.textContent = '3 days selected (maximum reached).';
        }
      }
    }

    function renderChips() {
      chipsEl.innerHTML = '';
      selected.forEach(function (d) {
        const chip = document.createElement('div');
        chip.className = 'cg-chip';
        chip.innerHTML = formatDisplay(d) +
          '<button type="button" title="Remove" aria-label="Remove ' + formatDisplay(d) + '">×</button>';
        chip.querySelector('button').addEventListener('click', function () { toggleDate(d); });
        chipsEl.appendChild(chip);
      });
    }

    function renderCalendar() {
      // First day of month, number of days
      const firstDow  = new Date(viewYear, viewMonth, 1).getDay(); // 0=Sun
      const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();
      const isPrevDisabled = viewYear === now.getFullYear() && viewMonth === now.getMonth();

      calEl.innerHTML =
        '<div class="cg-cal-nav">' +
          '<button type="button" class="cg-prev" ' + (isPrevDisabled ? 'disabled' : '') + '>‹</button>' +
          '<span class="cg-cal-nav-title">' + MONTHS[viewMonth] + ' ' + viewYear + '</span>' +
          '<button type="button" class="cg-next">›</button>' +
        '</div>' +
        '<div class="cg-cal-grid" id="cg-grid"></div>';

      calEl.querySelector('.cg-prev').addEventListener('click', function () {
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        renderCalendar();
      });
      calEl.querySelector('.cg-next').addEventListener('click', function () {
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        renderCalendar();
      });

      const grid = calEl.querySelector('#cg-grid');

      // Day-of-week headers
      DAYS.forEach(function (d, i) {
        const hdr = document.createElement('div');
        hdr.className = 'cg-cal-dow' + (i === 0 || i === 6 ? ' weekend' : '');
        hdr.textContent = d;
        grid.appendChild(hdr);
      });

      // Empty cells before first day
      for (let i = 0; i < firstDow; i++) {
        const empty = document.createElement('div');
        empty.className = 'cg-cal-day empty';
        grid.appendChild(empty);
      }

      // Day cells
      for (let d = 1; d <= daysInMonth; d++) {
        const dateStr  = toDateStr(viewYear, viewMonth, d);
        const weekend  = isWeekend(viewYear, viewMonth, d);
        const past     = isPast(dateStr);
        const isToday  = dateStr === todayStr();
        const isSel    = selected.indexOf(dateStr) >= 0;
        const selIdx   = selected.indexOf(dateStr);
        const maxed    = selected.length >= MAX && !isSel;

        const cell = document.createElement('div');
        let cls = 'cg-cal-day';
        if (weekend)      cls += ' weekend-day disabled';
        else if (past)    cls += ' disabled';
        else if (isSel)   cls += ' selected';
        if (isToday)      cls += ' today';
        cell.className = cls;

        if (isSel) {
          cell.innerHTML = d + '<span class="sel-num">' + (selIdx + 1) + '</span>';
        } else {
          cell.textContent = d;
        }

        if (!weekend && !past) {
          cell.style.cursor = maxed ? 'not-allowed' : 'pointer';
          if (!maxed) {
            cell.addEventListener('click', function () { toggleDate(dateStr); });
          }
        }

        grid.appendChild(cell);
      }
    }

    renderCalendar();
    syncInputs();
  })();

    // Collaborator toggle
    (function () {
      const yesRadio    = document.getElementById('collab-yes');
      const noRadio     = document.getElementById('collab-no');
      const collabFields = document.getElementById('collab-fields');
      const partnerNote  = document.getElementById('partner-note');
      if (!yesRadio) return;

      function update() {
        const hasCollab = yesRadio.checked;
        collabFields && collabFields.classList.toggle('visible', hasCollab);
        partnerNote  && partnerNote.classList.toggle('visible', !hasCollab && noRadio.checked);
      }
      yesRadio.addEventListener('change', update);
      noRadio.addEventListener('change', update);
      update(); // run on load for back-filled forms
    })();

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
