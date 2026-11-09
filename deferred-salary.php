<?php
/**
 * deferred-salary.php — Deferred Salary Leave Plan (Article G.37 / Schedule A)
 *
 * A summary of the plan with both source documents attached. The summary is a
 * way in, not the authority: every figure and date here comes from Schedule A,
 * and the page says so where it matters, because a teacher deciding to defer a
 * third of their salary for six years should be reading the agreement itself.
 */
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
  <title>Deferred Salary Leave Plan — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Take a year off and pay for it yourself, spread over several years. How the SD54 Deferred Salary Leave Plan works, what the deadlines are, and the forms to apply.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    /* Page-local only. Prefixed so nothing here can reach the shared .card
       rules the member pages share. */
    .dsp-lede {
      background: linear-gradient(135deg, var(--primary) 0%, #1a6b35 100%);
      border-radius: var(--radius);
      padding: 2rem;
      color: #fff;
      margin-bottom: 2.5rem;
    }
    .dsp-lede h2 { color:#fff; font-size:1.3rem; margin:0 0 .5rem; }
    .dsp-lede p  { margin:0; opacity:.95; line-height:1.65; }

    .dsp-section { margin-bottom: 2.5rem; }
    .dsp-section-head {
      display:flex; align-items:center; gap:.65rem;
      font-size:1.15rem; font-weight:700; color:var(--primary);
      margin-bottom:1rem; padding-bottom:.6rem;
      border-bottom:2px solid var(--border);
    }
    .dsp-icon svg {
      width:22px; height:22px; stroke:currentColor; fill:none;
      stroke-width:2; stroke-linecap:round; stroke-linejoin:round; display:block;
    }

    .dsp-steps { list-style:none; margin:0; padding:0; counter-reset:dsp; }
    .dsp-steps li {
      position:relative; padding:0 0 1.15rem 2.6rem; counter-increment:dsp;
      line-height:1.6;
    }
    .dsp-steps li::before {
      content: counter(dsp);
      position:absolute; left:0; top:0;
      width:1.75rem; height:1.75rem; border-radius:50%;
      background:var(--primary); color:#fff;
      display:flex; align-items:center; justify-content:center;
      font-size:.82rem; font-weight:700;
    }
    .dsp-steps li:last-child { padding-bottom:0; }

    .dsp-table { width:100%; border-collapse:collapse; font-size:.93rem; }
    .dsp-table th, .dsp-table td {
      text-align:left; padding:.6rem .7rem; border-bottom:1px solid var(--border);
      vertical-align:top;
    }
    .dsp-table th { color:var(--gray-500); font-weight:700; font-size:.82rem;
                    text-transform:uppercase; letter-spacing:.04em; }
    .dsp-table td:first-child { white-space:nowrap; font-weight:600; }

    .dsp-note {
      background:#fffbeb; border:1.5px solid #fcd34d; border-radius:10px;
      padding:1rem 1.15rem; font-size:.92rem; line-height:1.6;
    }

    .dsp-files { display:grid; gap:1rem; }
    .dsp-file {
      display:flex; gap:1rem; align-items:center; flex-wrap:wrap;
      border:1.5px solid var(--border); border-radius:10px;
      padding:1.1rem 1.25rem; background:var(--white);
    }
    .dsp-file-body { flex:1 1 16rem; min-width:0; }
    .dsp-file h3 { margin:0 0 .25rem; font-size:1rem; }
    .dsp-file p  { margin:0; font-size:.9rem; color:var(--gray-500); line-height:1.55; }
    .dsp-file-acts { display:flex; gap:.5rem; flex-wrap:wrap; }

    @media (max-width: 560px) {
      .dsp-lede { padding:1.4rem; }
      .dsp-table td:first-child { white-space:normal; }
    }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <div class="breadcrumb">
        <a href="index.php">Home</a> <span>&rsaquo;</span>
        <a href="members.php">Members</a> <span>&rsaquo;</span>
        <span>Deferred Salary Leave Plan</span>
      </div>
      <h1>Deferred Salary Leave Plan</h1>
      <p class="hero-sub">Take a year off, and pay for it yourself out of salary set aside over the years before.</p>
    </div>
  </section>

  <?php // page-content, not main-content: the latter has no rule in style.css,
        // so the first block sits flush against the hero. This is the shared
        // 3.5rem the other member pages breathe with. ?>
  <main class="page-content">
    <div class="container" style="max-width:820px">

      <div class="dsp-lede">
        <h2>What it is</h2>
        <p>You agree to be paid less than your full salary for up to six school years.
           The Board holds back the difference, invests it, and pays it to you during a
           year off. It is your own money, deferred &mdash; not a grant and not a loan.
           The plan is set up under Article G.37 of the Collective Agreement, and the
           detail lives in Schedule&nbsp;A, attached at the bottom of this page.</p>
      </div>

      <!-- ── How it works ───────────────────────────────────────────── -->
      <div class="dsp-section">
        <div class="dsp-section-head">
          <span class="dsp-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
          How it works
        </div>
        <ol class="dsp-steps">
          <li><strong>You apply by 31 March</strong> of the year before your deferrals start,
              using the Memorandum of Agreement (Schedule&nbsp;B) sent to the Superintendent
              of Schools.</li>
          <li><strong>The Board decides by 15 May.</strong> Approval rests with the Board
              alone; if they say no, they must tell you why.</li>
          <li><strong>You defer part of your salary</strong> &mdash; a percentage you choose,
              up to a third of your pay in any one year, for up to six school years. The
              Board holds the money and invests it.</li>
          <li><strong>You take your year off,</strong> and the deferred money is paid back to
              you in instalments during the leave, no more often than monthly.</li>
          <li><strong>You return to teaching</strong> at the end of the leave, to a position
              assigned under the agreement in force at the time.</li>
        </ol>
      </div>

      <!-- ── The numbers and dates ──────────────────────────────────── -->
      <div class="dsp-section">
        <div class="dsp-section-head">
          <span class="dsp-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
          The numbers and dates
        </div>
        <table class="dsp-table">
          <tbody>
            <tr><td>Apply by</td>
                <td>31 March of the year preceding the school year your deferrals begin</td></tr>
            <tr><td>Decision by</td>
                <td>15 May, or another date agreed between the Board and the Union</td></tr>
            <tr><td>Most you can defer</td>
                <td>33&#8531;% of your current compensation in any one year</td></tr>
            <tr><td>How long you can defer</td>
                <td>Up to six school years</td></tr>
            <tr><td>Length of the leave</td>
                <td>Twelve months, or not less than six months where the plan permits</td></tr>
            <tr><td>Latest start of leave</td>
                <td>No later than six years after your deferrals began</td></tr>
            <tr><td>Interest</td>
                <td>Paid to you each 31 December, into your account at the institution the
                    Committee has chosen. Any interest still owing is paid when the leave ends.</td></tr>
            <tr><td>Annual statement</td>
                <td>From the Secretary-Treasurer by 31 July each year, showing what is being
                    held for you and the interest earned</td></tr>
            <tr><td>Administration costs</td>
                <td>Borne by the Board</td></tr>
          </tbody>
        </table>
      </div>

      <!-- ── Changing your mind ─────────────────────────────────────── -->
      <div class="dsp-section">
        <div class="dsp-section-head">
          <span class="dsp-icon"><svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg></span>
          If your plans change
        </div>
        <table class="dsp-table">
          <tbody>
            <tr><td>Postponing the leave</td>
                <td>You may put it off for one year with the Committee's consent, asked for
                    at least six months before the leave was due to start.</td></tr>
            <tr><td>The Board postponing it</td>
                <td>If no suitable replacement can be found, the Board may defer your leave
                    by one school year. You can then stay in the plan or withdraw and be paid
                    out in a lump sum within sixty days.</td></tr>
            <tr><td>Pausing deferrals</td>
                <td>You can suspend participation from the following 1 September. Money
                    already held stays held and keeps earning interest until you take the
                    leave or withdraw. You can rejoin the same way.</td></tr>
            <tr><td>Withdrawing</td>
                <td>With the Committee's consent, any time before 31 May in the year the
                    leave was to be taken. You are paid out within sixty days.</td></tr>
            <tr><td>Leaving the district</td>
                <td>If you stop working for the Board you must withdraw, and are paid out
                    within sixty days.</td></tr>
            <tr><td>Death</td>
                <td>The amount held is paid to the estate within thirty days of the Board
                    being notified, subject to the usual clearances.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- ── While you are away ─────────────────────────────────────── -->
      <div class="dsp-section">
        <div class="dsp-section-head">
          <span class="dsp-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
          Benefits and seniority
        </div>
        <table class="dsp-table">
          <tbody>
            <tr><td>Fringe benefits</td>
                <td>As set out in the agreement in force at the time. Where you have to pay
                    the cost of a benefit during the leave, the Board can pay it for you on
                    request and deduct it from what it pays you.</td></tr>
            <tr><td>Sick leave credits</td>
                <td>As set out in the Collective Agreement</td></tr>
            <tr><td>Pension</td>
                <td>The Board makes the superannuation deductions required under the
                    Pension (Teachers) Act</td></tr>
            <tr><td>Seniority</td>
                <td>Governed by a separate agreement between the Board and the Union</td></tr>
          </tbody>
        </table>
      </div>

      <!-- ── The documents ──────────────────────────────────────────── -->
      <div class="dsp-section">
        <div class="dsp-section-head">
          <span class="dsp-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
          The documents
        </div>

        <div class="dsp-files">
          <div class="dsp-file">
            <div class="dsp-file-body">
              <h3>Schedule A &mdash; the plan itself</h3>
              <p>The full policy: definitions, how application and approval work, how the
                 money is held and paid, withdrawal, benefits and how the plan can be
                 amended. This is the authority &mdash; everything above is a summary of it.</p>
            </div>
            <div class="dsp-file-acts">
              <a href="documents/deferred-salary-leave-plan-policy.pdf" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:.88rem;">View</a>
              <a href="documents/deferred-salary-leave-plan-policy.pdf" download class="btn btn-primary" style="font-size:.88rem;">&darr; PDF</a>
            </div>
          </div>

          <div class="dsp-file">
            <div class="dsp-file-body">
              <h3>Schedule B &mdash; the form you fill in</h3>
              <p>The Memorandum of Agreement. Sets your enrolment date, the year you intend
                 to take leave, and the percentage held back each year. Goes to the
                 Superintendent of Schools by 31 March.</p>
            </div>
            <div class="dsp-file-acts">
              <a href="documents/deferred-salary-leave-plan-agreement-schedule-b.pdf" target="_blank" rel="noopener" class="btn btn-outline" style="font-size:.88rem;">View</a>
              <a href="documents/deferred-salary-leave-plan-agreement-schedule-b.pdf" download class="btn btn-primary" style="font-size:.88rem;">&darr; PDF</a>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Before you commit ──────────────────────────────────────── -->
      <div class="dsp-section">
        <div class="dsp-note">
          <strong>Worth knowing before you sign.</strong>
          The plan is registered as a &ldquo;prescribed plan or arrangement&rdquo; under the
          <em>Income Tax Act</em>, which is what lets the deferred salary be taxed when you
          receive it rather than when it is earned. That status depends on the six-year
          rule being kept, so the dates are not flexible in the way an ordinary leave is.
          Deferring a third of your pay for several years is a significant financial
          decision, and the plan document is written in legal language for a reason &mdash;
          read Schedule&nbsp;A in full, and talk to the President before you commit.
        </div>
      </div>

      <!-- ── Questions ──────────────────────────────────────────────── -->
      <div class="dsp-section">
        <div class="dsp-section-head">
          <span class="dsp-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
          Questions
        </div>
        <table class="dsp-table">
          <thead><tr><th>Contact</th><th>For</th></tr></thead>
          <tbody>
            <tr><td><strong>BVTU President</strong><br><a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a></td>
                <td>Whether the plan is the right fit for you, and anything about how the
                    Collective Agreement applies</td></tr>
            <tr><td><strong>SD54 Superintendent's office</strong><br>250&nbsp;877-6820</td>
                <td>Where to send Schedule B, and the status of an application</td></tr>
            <tr><td><strong>SD54 Secretary-Treasurer</strong><br>250&nbsp;877-6820</td>
                <td>Your annual statement, the amount held, and interest</td></tr>
          </tbody>
        </table>

        <div style="margin-top:1.25rem;">
          <a href="collective-agreement.php" class="btn btn-outline" style="font-size:.9rem;">Read Article G.37 in the Collective Agreement &rarr;</a>
        </div>
      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js?v=<?= @filemtime(__DIR__ . '/js/site.js') ?>"></script>
  <script src="js/search.js"></script>
</body>
</html>
