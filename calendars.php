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
  <title>School Calendars — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="SD54 school calendars for 2025–26 and 2026–27 — instructional days, Pro-D days, and key dates.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .cal-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.75rem;
      margin-bottom: 2rem;
    }
    .cal-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,.05);
    }
    .cal-card-img {
      width: 100%;
      display: block;
      border-bottom: 1px solid var(--border);
    }
    .cal-card-body { padding: 1.1rem 1.25rem 1.25rem; }
    .cal-card-body h2 { font-size: 1.1rem; font-weight: 800; color: var(--primary); margin: 0 0 .3rem; }
    .cal-card-body p  { font-size: .88rem; color: var(--gray-500); margin: 0 0 1rem; }
    .cal-card-actions { display: flex; gap: .65rem; flex-wrap: wrap; }
    .cal-card-actions a {
      display: inline-flex; align-items: center; gap: .4rem;
      font-size: .85rem; font-weight: 700;
      padding: .45rem 1rem; border-radius: 7px;
      text-decoration: none;
    }
    .cal-btn-view {
      background: var(--accent); color: var(--primary);
      border: 1.5px solid #b8ddc5;
    }
    .cal-btn-view:hover { background: #d7eee2; }
    .cal-btn-dl {
      background: var(--primary); color: white;
      border: 1.5px solid transparent;
    }
    .cal-btn-dl:hover { background: #155a2a; }
    .cal-btn-dl svg, .cal-btn-view svg {
      width: 15px; height: 15px;
      stroke: currentColor; fill: none;
      stroke-width: 2.2; flex-shrink: 0;
    }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>School Calendars</h1>
      <p>SD54 (Bulkley Valley) school year calendars — instructional days, Pro-D days, and key dates.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="cal-grid">

        <div class="cal-card">
          <a href="documents/calendars/SD54-School-Calendar-2025-2026.pdf" target="_blank">
            <img src="images/calendars/cal-2526-thumb.jpg" alt="SD54 2025–2026 School Calendar" class="cal-card-img">
          </a>
          <div class="cal-card-body">
            <h2>2025–2026 School Calendar</h2>
            <p>Days in Session: 188 &nbsp;·&nbsp; Days of Instruction: 181</p>
            <div class="cal-card-actions">
              <a href="documents/calendars/SD54-School-Calendar-2025-2026.pdf" target="_blank" class="cal-btn-view">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Full Calendar
              </a>
              <a href="documents/calendars/SD54-School-Calendar-2025-2026.pdf" download class="cal-btn-dl">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download PDF
              </a>
            </div>
          </div>
        </div>

        <div class="cal-card">
          <a href="documents/calendars/SD54-School-Calendar-2026-2027.pdf" target="_blank">
            <img src="images/calendars/cal-2627-thumb.jpg" alt="SD54 2026–2027 School Calendar" class="cal-card-img">
          </a>
          <div class="cal-card-body">
            <h2>2026–2027 School Calendar</h2>
            <p>Days in Session: 187 &nbsp;·&nbsp; Days of Instruction: 180</p>
            <div class="cal-card-actions">
              <a href="documents/calendars/SD54-School-Calendar-2026-2027.pdf" target="_blank" class="cal-btn-view">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Full Calendar
              </a>
              <a href="documents/calendars/SD54-School-Calendar-2026-2027.pdf" download class="cal-btn-dl">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download PDF
              </a>
            </div>
          </div>
        </div>

      </div>

      <p style="color:var(--gray-500);font-size:.88rem;">Calendars sourced from <a href="https://www.sd54.bc.ca" target="_blank" rel="noopener">sd54.bc.ca</a>. Click the thumbnail to view the full calendar, or download the PDF.</p>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
