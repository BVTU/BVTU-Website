<?php
require_once __DIR__ . '/members/auth.php';   // so the nav can tell you are signed in
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Documents &amp; Forms — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Every document the BVTU publishes — collective agreements, LOUs and settlements, salary grids, claim forms, leave policies, school calendars and governance.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .members-locked {
      background: var(--off-white);
      border: 2px dashed var(--border);
      border-radius: var(--radius);
      padding: 2.5rem;
      text-align: center;
      margin-top: 1rem;
    }
    .members-locked .lock-icon {
      width: 48px;
      height: 48px;
      background: var(--accent);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      color: var(--blue);
    }
    .members-locked .lock-icon svg { width: 24px; height: 24px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .members-locked h3 { font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: .5rem; }
    .members-locked p { color: var(--gray-500); font-size: .92rem; margin-bottom: 1.25rem; }
    .members-section-title {
      display: flex;
      align-items: center;
      gap: .65rem;
      font-size: 1.35rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 1rem;
      padding-bottom: .6rem;
      border-bottom: 2px solid var(--accent);
    }
    .members-badge {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      background: var(--primary);
      color: white;
      padding: .2rem .55rem;
      border-radius: 100px;
    }
    .section-divider {
      border: none;
      border-top: 2px solid var(--gray-200);
      margin: 3rem 0;
    }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>Documents &amp; Forms</h1>
      <p>Every document we publish, by topic. No login needed.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- QUICK-ACCESS CARDS -->
      <style>
        .doc-page-grid {
          display: grid;
          grid-template-columns: repeat(3, 1fr);
          gap: 1rem;
          margin-bottom: 2.5rem;
        }
        @media (max-width: 640px) {
          .doc-page-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 380px) {
          .doc-page-grid { grid-template-columns: 1fr; }
        }
        .doc-page-card {
          display: flex;
          flex-direction: column;
          background: var(--white);
          border: 1.5px solid var(--border);
          border-radius: var(--radius);
          padding: 1.3rem 1.15rem 1.1rem;
          text-decoration: none;
          color: var(--text);
          transition: border-color .15s, box-shadow .15s, transform .12s;
        }
        .doc-page-card:hover {
          border-color: var(--primary);
          box-shadow: 0 4px 18px rgba(27,107,66,.1);
          transform: translateY(-2px);
          color: var(--text);
        }
        .doc-page-card-icon {
          width: 40px; height: 40px;
          background: var(--accent);
          border-radius: 10px;
          display: flex; align-items: center; justify-content: center;
          margin-bottom: .85rem;
          flex-shrink: 0;
        }
        .doc-page-card-icon svg {
          width: 20px; height: 20px;
          stroke: var(--primary); fill: none;
          stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        }
        .doc-page-card h3 { font-size: .95rem; font-weight: 800; color: var(--primary); margin: 0 0 .3rem; }
        .doc-page-card p  { font-size: .82rem; color: var(--gray-500); margin: 0; line-height: 1.5; flex: 1; }
        .doc-page-card-arrow { font-size: .8rem; font-weight: 700; color: var(--primary); margin-top: .85rem; }

        /* Calendar cards */
        .cal-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
          gap: 1.5rem;
          margin-bottom: 2rem;
        }
        .cal-card {
          background: var(--white);
          border: 1.5px solid var(--border);
          border-radius: var(--radius);
          overflow: hidden;
        }
        .cal-card-img {
          width: 100%;
          display: block;
          border-bottom: 1px solid var(--border);
        }
        .cal-card-body { padding: 1rem 1.1rem 1.1rem; }
        .cal-card-body h3 { font-size: 1rem; font-weight: 800; color: var(--primary); margin: 0 0 .35rem; }
        .cal-card-body p  { font-size: .85rem; color: var(--gray-500); margin: 0 0 .9rem; }
        .cal-card-actions { display: flex; gap: .6rem; flex-wrap: wrap; }
        .cal-card-actions a {
          display: inline-flex; align-items: center; gap: .35rem;
          font-size: .82rem; font-weight: 700;
          padding: .4rem .85rem; border-radius: 6px;
          text-decoration: none;
        }
        .cal-btn-view {
          background: var(--accent); color: var(--primary);
          border: 1.5px solid var(--primary-light, #b8ddc5);
        }
        .cal-btn-view:hover { background: #d7eee2; }
        .cal-btn-dl {
          background: var(--primary); color: white;
          border: 1.5px solid transparent;
        }
        .cal-btn-dl:hover { background: #155a2a; }
        .cal-btn-dl svg, .cal-btn-view svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.2; flex-shrink: 0; }
      </style>

      <div class="doc-page-grid">

        <a href="collective-agreement.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <h3>Collective Agreement</h3>
          <p>Local and provincial CA — your rights, entitlements, and working conditions.</p>
          <div class="doc-page-card-arrow">Read the CA →</div>
        </a>

        <a href="lous.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          </div>
          <h3>LOUs &amp; Settlements</h3>
          <p>Signed LOUs, settlements, arbitration awards and proposals between BVTU and SD54.</p>
          <div class="doc-page-card-arrow">Browse LOUs &amp; settlements →</div>
        </a>

        <a href="ca-assistant.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 3v-3z"/></svg>
          </div>
          <h3>Contract Assistant</h3>
          <p>Ask plain-language questions about the CA and get instant answers.</p>
          <div class="doc-page-card-arrow">Ask a question →</div>
        </a>

        <a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank" rel="noopener" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
          </div>
          <h3>Constitution &amp; Bylaws</h3>
          <p>BVTU Constitution and Bylaws, approved May 2026.</p>
          <div class="doc-page-card-arrow">Open PDF →</div>
        </a>

        <a href="calendars.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <h3>School Calendars</h3>
          <p>SD54 school calendars for 2025–26 and 2026–27 — view and download.</p>
          <div class="doc-page-card-arrow">View calendars →</div>
        </a>

        <a href="deferred-salary.php" class="doc-page-card">
          <div class="doc-page-card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <h3>Deferred Salary Leave Plan</h3>
          <p>Article G.37 and its schedules — defer part of your pay to fund a year off. Plan, form and deadlines.</p>
          <div class="doc-page-card-arrow">How it works →</div>
        </a>

      </div>

      <?php
      /*
       * The index: every PDF under documents/, one row each, from the explicit
       * list in inc/documents-index.php. A listed file that is not on disk is
       * skipped rather than shown as a dead link.
       */
      require_once __DIR__ . '/inc/documents-index.php';
      $docRows = [];
      foreach (docIndexRecords() as $r) {
          if (is_file(__DIR__ . '/' . $r['file'])) $docRows[$r['topic']][] = $r;
      }
      $hd = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
      ?>
      <style>
        .doc-index { margin-bottom: 2.25rem; }
        .doc-index h2 { font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
                        color: var(--gray-500); margin: 0 0 .6rem; }
        .doc-index ul { list-style: none; margin: 0; padding: 0; border: 1px solid var(--border);
                        border-radius: var(--radius-s); background: var(--white); }
        .doc-index li { display: flex; flex-wrap: wrap; align-items: baseline; gap: .15rem 1rem;
                        padding: .65rem 1rem; border-top: 1px solid var(--border); }
        .doc-index li:first-child { border-top: 0; }
        .doc-index-title { flex: 1 1 22rem; font-weight: 600; }
        .doc-index-meta  { font-size: .8rem; color: var(--gray-500); }
        .doc-index-guide { font-size: .8rem; white-space: nowrap; }
        .doc-index-status { font-weight: 700; color: var(--gray-600); }
      </style>
      <?php foreach (docIndexTopics() as $topic => $heading): ?>
        <?php if (empty($docRows[$topic])) continue; ?>
      <section class="doc-index" aria-labelledby="docs-<?= $hd($topic) ?>">
        <h2 id="docs-<?= $hd($topic) ?>"><?= $heading ?></h2>
        <ul>
          <?php foreach ($docRows[$topic] as $r): ?>
          <li>
            <a class="doc-index-title" href="<?= $hd($r['file']) ?>" target="_blank" rel="noopener"><?= $hd($r['title']) ?><span class="sr-only"> (PDF, opens in a new tab)</span></a>
            <span class="doc-index-meta">
              <?= $hd($r['type']) ?><?= $r['date'] !== '' ? ' · ' . $hd($r['date']) : '' ?>
              <?php if (!empty($r['status'])): ?> · <span class="doc-index-status"><?= $hd($r['status']) ?></span><?php endif; ?>
            </span>
            <a class="doc-index-guide" href="<?= $hd($r['guide']) ?>"><?= $hd(docIndexGuideLabel($r['guide'])) ?></a>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
      <?php endforeach; ?>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
