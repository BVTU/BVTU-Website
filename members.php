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
  <title>Members — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU member resources — release time, salary grids, certification fees, grants, and TTOC information.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>Member Resources</h1>
      <p>Benefits, salary grids, grants, and resources for BVTU members.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- Quick-access page cards -->
      <style>
        .member-page-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
          gap: 1rem;
          margin-bottom: 2.5rem;
        }
        .member-page-card {
          display: flex;
          flex-direction: column;
          background: var(--white);
          border: 1.5px solid var(--border);
          border-radius: var(--radius);
          padding: 1.4rem 1.25rem 1.2rem;
          text-decoration: none;
          color: var(--text);
          transition: border-color .15s, box-shadow .15s, transform .12s;
        }
        .member-page-card:hover {
          border-color: var(--primary);
          box-shadow: 0 4px 18px rgba(27,107,66,.1);
          transform: translateY(-2px);
          color: var(--text);
        }
        .member-page-card-icon {
          width: 40px; height: 40px;
          background: var(--accent);
          border-radius: 10px;
          display: flex; align-items: center; justify-content: center;
          margin-bottom: .9rem;
          flex-shrink: 0;
        }
        .member-page-card-icon svg {
          width: 20px; height: 20px;
          stroke: var(--primary); fill: none;
          stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }
        .member-page-card h3 {
          font-size: 1rem; font-weight: 800; color: var(--primary);
          margin: 0 0 .35rem;
        }
        .member-page-card p {
          font-size: .85rem; color: var(--gray-500); margin: 0;
          line-height: 1.55; flex: 1;
        }
        .member-page-card-arrow {
          font-size: .82rem; font-weight: 700; color: var(--primary);
          margin-top: .9rem;
        }
      </style>

      <div class="member-page-grid">

        <a href="benefits.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h3>Health &amp; Dental Benefits</h3>
          <p>Pacific Blue Cross coverage — dental, extended health, paramedical, vision, prescription drugs, and how to claim.</p>
          <div class="member-page-card-arrow">View benefits guide →</div>
        </a>

        <a href="life-insurance.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </div>
          <h3>Life Insurance</h3>
          <p>Plan EB coverage — 3× salary up to $300,000, beneficiary designation, disability waiver, and conversion rights.</p>
          <div class="member-page-card-arrow">View coverage details →</div>
        </a>

        <a href="loan-forgiveness.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <h3>Student Loan Forgiveness</h3>
          <p>Up to $30,000 forgiven over 5 years — Houston, Telkwa, and Smithers postal codes all qualify. Learn how to apply.</p>
          <div class="member-page-card-arrow">See if you qualify →</div>
        </a>

        <a href="ttoc.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h3>TTOC Resources</h3>
          <p>Call-out rates, EI hours calculator, experience credit, sick leave entitlements, pro-D access, and benefits for TTOCs.</p>
          <div class="member-page-card-arrow">View TTOC guide →</div>
        </a>

        <a href="atrieve.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>Booking Time Off</h3>
          <p>How to enter union-paid release time in Atrieve — approval process, BCTF and BVTU event codes, and leave entry instructions.</p>
          <div class="member-page-card-arrow">View Atrieve guide →</div>
        </a>

        <a href="remedy-tracker.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          </div>
          <h3>Class Size &amp; Remedy</h3>
          <p>When your class is over size, or over the composition limits, work out what the collective agreement says you are owed.</p>
          <div class="member-page-card-arrow">Work out what you are owed →</div>
        </a>

        <a href="salary.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          </div>
          <h3>Salary Grids</h3>
          <p>SD54 salary grids for 2024–2028 — view and download each year's grid as a PDF.</p>
          <div class="member-page-card-arrow">View salary grids →</div>
        </a>

        <a href="whats-on.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="14"/><line x1="12" y1="14" x2="12" y2="14"/><line x1="16" y1="14" x2="16" y2="14"/></svg>
          </div>
          <h3>What's On</h3>
          <p>Every union event on one calendar &mdash; seminars, meetings and deadlines &mdash; and a feed you can add to your own phone.</p>
          <div class="member-page-card-arrow">See the calendar →</div>
        </a>

        <a href="events.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>Training &amp; Workshops</h3>
          <p>Union rep training and other sessions run by your local &mdash; what is coming up, the handouts, and how to sign up.</p>
          <div class="member-page-card-arrow">See what's on →</div>
        </a>

        <a href="collab-grant.php" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h3>Collaboration Grant</h3>
          <p>Apply for BVTU collaboration grants supporting teacher-led professional development projects.</p>
          <div class="member-page-card-arrow">Apply now →</div>
        </a>

      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
