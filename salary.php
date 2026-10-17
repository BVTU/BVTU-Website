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
  <title>Salary Grids — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="SD54 salary grids for 2024–2028 — view and download annual teacher salary grids for BVTU members.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .salary-grid-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 1rem;
      margin-top: 1.5rem;
    }
    .salary-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem 1rem 1rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      gap: .5rem;
      transition: box-shadow .15s, border-color .15s;
    }
    .salary-card:hover {
      border-color: var(--primary);
      box-shadow: 0 4px 16px rgba(27,107,66,.1);
    }
    .salary-card-year {
      font-size: 1.55rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1.1;
    }
    .salary-card-sub {
      font-size: .78rem;
      color: var(--gray-500);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .salary-card-icon {
      width: 38px;
      height: 38px;
      background: var(--accent);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: .1rem;
    }
    .salary-card-icon svg {
      width: 20px; height: 20px;
      stroke: var(--primary); fill: none;
      stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
    }
    .salary-card-actions {
      display: flex;
      gap: .4rem;
      margin-top: .4rem;
      flex-wrap: wrap;
      justify-content: center;
    }
    .salary-card-actions a {
      font-size: .8rem;
      padding: .3rem .7rem;
      border-radius: var(--radius-s);
      font-weight: 600;
      text-decoration: none;
      transition: background .15s, color .15s;
    }
    .salary-btn-view {
      background: var(--accent);
      color: var(--primary);
      border: 1.5px solid var(--primary);
    }
    .salary-btn-view:hover { background: var(--primary); color: white; }
    .salary-btn-dl {
      background: var(--primary);
      color: white;
      border: 1.5px solid var(--primary);
    }
    .salary-btn-dl:hover { background: var(--primary-dk); }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>Salary Grids</h1>
      <p>SD54 salary grids sourced from the BCTF database — covering the full 2024–2028 contract period.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="content-block">
        <p>Grids reflect the provincial collective agreement and are updated each year. Source: <a href="https://www.bctf.ca/topics/services-information/collective-agreements-and-salary/view-salary-grids" target="_blank" rel="noopener">BCTF salary grid database</a>.</p>

        <div class="salary-grid-cards">

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2024–25</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2024.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2024.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2025–26</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2025.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2025.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2026–27</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2026.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2026.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2027–28</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2027.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2027.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

          <div class="salary-card">
            <div class="salary-card-icon">
              <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </div>
            <div class="salary-card-year">2028–29</div>
            <div class="salary-card-sub">SD54 Salary Grid</div>
            <div class="salary-card-actions">
              <a href="documents/salary-grids/sd54-salary-grid-2028.pdf" target="_blank" class="salary-btn-view">View</a>
              <a href="documents/salary-grids/sd54-salary-grid-2028.pdf" download class="salary-btn-dl">↓ PDF</a>
            </div>
          </div>

        </div>
      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
