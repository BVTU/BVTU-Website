<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

// One row per calendar, keyed by the year the school year starts in.
// A calendar moves to the archive once its school year is over; nothing is deleted.
$calendars = [
  2025 => ['session' => 188, 'instruction' => 181, 'thumb' => 'cal-2526-thumb.jpg'],
  2026 => ['session' => 187, 'instruction' => 180, 'thumb' => 'cal-2627-thumb.jpg'],
];
$schoolYear = (int)date('n') >= 9 ? (int)date('Y') : (int)date('Y') - 1;
krsort($calendars);
$current = array_filter($calendars, function ($y) use ($schoolYear) { return $y >= $schoolYear; }, ARRAY_FILTER_USE_KEY);
$past    = array_filter($calendars, function ($y) use ($schoolYear) { return $y <  $schoolYear; }, ARRAY_FILTER_USE_KEY);
ksort($current);

function calCard($y, $c) {
  $label = $y . '–' . ($y + 1);
  $pdf   = 'documents/calendars/SD54-School-Calendar-' . $y . '-' . ($y + 1) . '.pdf';
  ?>
        <div class="cal-card">
          <a href="<?= $pdf ?>" target="_blank">
            <img src="images/calendars/<?= htmlspecialchars($c['thumb']) ?>" alt="SD54 <?= $label ?> School Calendar" class="cal-card-img">
          </a>
          <div class="cal-card-body">
            <h2><?= $label ?> School Calendar</h2>
            <p>Days in Session: <?= (int)$c['session'] ?> &nbsp;·&nbsp; Days of Instruction: <?= (int)$c['instruction'] ?></p>
            <div class="cal-card-actions">
              <a href="<?= $pdf ?>" target="_blank" class="cal-btn-view">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                View Full Calendar
              </a>
              <a href="<?= $pdf ?>" download class="cal-btn-dl">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download PDF
              </a>
            </div>
          </div>
        </div>
<?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>School Calendars — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="SD54 school calendars — instructional days, Pro-D days, and key dates, with past years in the archive.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .cal-grid {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 1.75rem;
      margin-bottom: 2rem;
    }
    .cal-grid > .cal-card { flex: 0 1 calc((100% - 1.75rem) / 2); }
    @media (max-width: 680px) { .cal-grid > .cal-card { flex-basis: 100%; } }
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
    .year-archive { margin: 0 0 2rem; }
    .year-archive > summary {
      display: inline-flex; align-items: center; gap: .45rem;
      cursor: pointer; list-style: none;
      font-size: .85rem; font-weight: 700; color: var(--primary);
      padding: .45rem 1rem; border-radius: 7px;
      border: 1.5px solid var(--border); background: var(--white);
      margin-bottom: 1.25rem;
    }
    .year-archive > summary::-webkit-details-marker { display: none; }
    .year-archive > summary::before { content: '▸'; transition: transform .15s; }
    .year-archive[open] > summary::before { transform: rotate(90deg); }
    .year-archive > summary:hover { border-color: var(--primary); }
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
<?php foreach ($current as $y => $c) calCard($y, $c); ?>
      </div>

<?php if ($past): ?>
      <details class="year-archive">
        <summary>Archive (<?= count($past) ?>)</summary>
        <div class="cal-grid">
<?php foreach ($past as $y => $c) calCard($y, $c); ?>
        </div>
      </details>
<?php endif; ?>

      <p style="color:var(--gray-500);font-size:.88rem;">Calendars sourced from <a href="https://www.sd54.bc.ca" target="_blank" rel="noopener">sd54.bc.ca</a>. Click the thumbnail to view the full calendar, or download the PDF.</p>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js?v=<?= @filemtime(__DIR__ . '/js/site.js') ?>"></script>
  <script src="js/search.js"></script>
</body>
</html>
