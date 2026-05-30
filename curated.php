<?php
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/curated-db.php';
curatedEnsureTables();

$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
$isCurator = $loggedIn && curatedIsCurator($member['email']);

$selBand    = isset($_GET['band'])    && isset(CURATED_BANDS[$_GET['band']])           ? $_GET['band']    : '';
$selSubject = isset($_GET['subject']) && in_array($_GET['subject'], CURATED_SUBJECTS, true) ? $_GET['subject'] : '';

$resources = curatedGetAll($selBand ?: null, $selSubject ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Curated Resources — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Hand-picked teaching resources organised by grade band and subject — curated by BVTU educators.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    /* ── Band filter bar ─────────────────────────────────────── */
    .cur-bands {
      display: flex;
      gap: .6rem;
      flex-wrap: wrap;
      margin-bottom: 1.5rem;
    }
    .cur-band-btn {
      padding: .55rem 1.3rem;
      font-size: .92rem;
      font-weight: 700;
      border-radius: 100px;
      border: 2px solid var(--border);
      background: var(--white);
      color: var(--gray-600);
      cursor: pointer;
      text-decoration: none;
      transition: border-color .15s, background .15s, color .15s;
    }
    .cur-band-btn:hover,
    .cur-band-btn.active {
      border-color: var(--primary);
      background: var(--primary);
      color: white;
    }

    /* ── Subject filter pills ────────────────────────────────── */
    .cur-subjects {
      display: flex;
      flex-wrap: wrap;
      gap: .45rem;
      margin-bottom: 2rem;
    }
    .cur-subj-btn {
      padding: .35rem .9rem;
      font-size: .83rem;
      font-weight: 600;
      border-radius: 100px;
      border: 1.5px solid var(--border);
      background: var(--off-white);
      color: var(--gray-600);
      cursor: pointer;
      text-decoration: none;
      transition: border-color .15s, background .15s, color .15s;
    }
    .cur-subj-btn:hover,
    .cur-subj-btn.active {
      border-color: var(--primary);
      background: var(--accent);
      color: var(--primary);
    }

    /* ── Resource grid ───────────────────────────────────────── */
    .cur-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
      gap: 1.4rem;
      margin-bottom: 2rem;
    }

    .cur-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: border-color .15s, box-shadow .15s, transform .12s;
    }
    .cur-card:hover {
      border-color: var(--primary);
      box-shadow: 0 4px 18px rgba(27,107,66,.1);
      transform: translateY(-2px);
    }

    .cur-card-thumb {
      width: 100%;
      height: 140px;
      object-fit: cover;
      border-bottom: 1px solid var(--border);
      display: block;
    }
    .cur-card-thumb-placeholder {
      width: 100%;
      height: 140px;
      background: var(--accent);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .cur-card-thumb-placeholder svg {
      width: 40px; height: 40px;
      stroke: var(--primary); fill: none;
      stroke-width: 1.5; opacity: .5;
    }

    .cur-card-body {
      padding: 1rem 1.1rem 1.1rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .cur-card-meta {
      display: flex;
      gap: .4rem;
      flex-wrap: wrap;
      margin-bottom: .55rem;
    }
    .cur-card-badge {
      font-size: .72rem;
      font-weight: 700;
      padding: .18rem .55rem;
      border-radius: 100px;
      background: var(--accent);
      color: var(--primary);
      border: 1px solid #b8ddc5;
    }
    .cur-card-badge--ext {
      background: #eff6ff;
      color: #1d4ed8;
      border-color: #bfdbfe;
    }
    .cur-card-title {
      font-size: 1rem;
      font-weight: 800;
      color: var(--primary);
      margin: 0 0 .4rem;
      line-height: 1.3;
    }
    .cur-card-desc {
      font-size: .86rem;
      color: var(--gray-600);
      line-height: 1.55;
      margin: 0 0 1rem;
      flex: 1;
    }
    .cur-card-action {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-size: .85rem;
      font-weight: 700;
      padding: .48rem 1.1rem;
      border-radius: 7px;
      text-decoration: none;
      background: var(--primary);
      color: white;
      align-self: flex-start;
      transition: background .15s;
    }
    .cur-card-action:hover { background: #155a2a; }
    .cur-card-action svg {
      width: 14px; height: 14px;
      stroke: currentColor; fill: none; stroke-width: 2.5;
    }
    .cur-card-domain {
      font-size: .75rem;
      color: var(--gray-400);
      margin-top: .55rem;
    }

    /* ── Empty state ─────────────────────────────────────────── */
    .cur-empty {
      text-align: center;
      padding: 3.5rem 1rem;
      color: var(--gray-500);
    }
    .cur-empty svg {
      display: block; margin: 0 auto 1rem;
      color: var(--gray-200);
    }
    .cur-empty h2 { font-size: 1.15rem; color: var(--text); margin-bottom: .4rem; }

    /* ── Curator admin link ───────────────────────────────────── */
    .cur-admin-bar {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 1.25rem;
    }

    @media (max-width: 600px) {
      .cur-grid { grid-template-columns: 1fr; }
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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li></ul></li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown active"><a href="library.php" class="active">Resources</a>
            <ul class="dropdown">
              <li><a href="library.php">Resource Library</a></li>
              <li><a href="curated.php" class="active">Curated Resources</a></li>
            </ul>
          </li>
          <li><a href="newsletter-archive.php">Newsletters</a></li>
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
      <h1>Curated Resources</h1>
      <p>Hand-picked teaching resources — vetted by BVTU educators and organised by grade and subject.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <?php if ($isCurator): ?>
      <div class="cur-admin-bar">
        <a href="members/curated-admin.php" class="btn btn-primary" style="font-size:.87rem;padding:.45rem 1rem;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15" style="vertical-align:middle;margin-right:.3rem"><path d="M12 5v14M5 12h14"/></svg>
          Manage Curated Resources
        </a>
      </div>
      <?php endif; ?>

      <!-- Grade band filter -->
      <div class="cur-bands">
        <a href="curated.php<?= $selSubject ? '?subject='.urlencode($selSubject) : '' ?>"
           class="cur-band-btn <?= !$selBand ? 'active' : '' ?>">All Grades</a>
        <?php foreach (CURATED_BANDS as $key => $label): ?>
          <?php if ($key === 'all') continue; ?>
          <a href="curated.php?band=<?= $key ?><?= $selSubject ? '&subject='.urlencode($selSubject) : '' ?>"
             class="cur-band-btn <?= $selBand === $key ? 'active' : '' ?>">
            <?= htmlspecialchars($label) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Subject pills -->
      <div class="cur-subjects">
        <a href="curated.php<?= $selBand ? '?band='.$selBand : '' ?>"
           class="cur-subj-btn <?= !$selSubject ? 'active' : '' ?>">All Subjects</a>
        <?php foreach (CURATED_SUBJECTS as $subj): ?>
          <a href="curated.php?<?= $selBand ? 'band='.$selBand.'&' : '' ?>subject=<?= urlencode($subj) ?>"
             class="cur-subj-btn <?= $selSubject === $subj ? 'active' : '' ?>">
            <?= htmlspecialchars($subj) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Results -->
      <?php if ($resources): ?>
        <div class="cur-grid">
          <?php foreach ($resources as $r):
            $isExt  = $r['type'] === 'external';
            $domain = $isExt ? parse_url($r['url'], PHP_URL_HOST) : '';
            $domain = $domain ? preg_replace('/^www\./', '', $domain) : '';
            $bandLabel = CURATED_BANDS[$r['grade_band']] ?? $r['grade_band'];
          ?>
          <div class="cur-card">
            <?php if ($r['thumbnail_url']): ?>
              <img src="<?= htmlspecialchars($r['thumbnail_url']) ?>"
                   alt="<?= htmlspecialchars($r['title']) ?>"
                   class="cur-card-thumb">
            <?php else: ?>
              <div class="cur-card-thumb-placeholder">
                <?php if ($isExt): ?>
                  <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                <?php else: ?>
                  <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="cur-card-body">
              <div class="cur-card-meta">
                <span class="cur-card-badge"><?= htmlspecialchars($bandLabel) ?></span>
                <?php if ($r['subject']): ?>
                  <span class="cur-card-badge"><?= htmlspecialchars($r['subject']) ?></span>
                <?php endif; ?>
                <?php if ($isExt): ?>
                  <span class="cur-card-badge cur-card-badge--ext">External Site</span>
                <?php endif; ?>
              </div>
              <h2 class="cur-card-title"><?= htmlspecialchars($r['title']) ?></h2>
              <?php if ($r['description']): ?>
                <p class="cur-card-desc"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
              <?php endif; ?>
              <a href="<?= htmlspecialchars($r['url']) ?>"
                 class="cur-card-action"
                 <?= $isExt ? 'target="_blank" rel="noopener"' : '' ?>>
                <?php if ($isExt): ?>
                  <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 0 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                  Visit Site
                <?php else: ?>
                  <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                  Download
                <?php endif; ?>
              </a>
              <?php if ($domain): ?>
                <div class="cur-card-domain"><?= htmlspecialchars($domain) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <div class="cur-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52">
            <path d="M12 22s-8-4-8-10V5l8-3 8 3v7c0 6-8 10-8 10z"/>
          </svg>
          <h2>No resources here yet</h2>
          <p><?= ($selBand || $selSubject) ? 'Try a different grade or subject filter.' : 'Check back soon — resources are being added.' ?></p>
        </div>
      <?php endif; ?>

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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li><a href="library.php">Resource Library</a></li>
          <li><a href="curated.php">Curated Resources</a></li>
          <li><a href="newsletter-archive.php">Newsletters</a></li>
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
