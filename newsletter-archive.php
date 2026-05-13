<?php
require_once __DIR__ . '/members/auth.php';
if (!isLoggedIn()) {
    header('Location: members/login.php?redirect=../newsletter-archive.php');
    exit;
}
require_once __DIR__ . '/members/newsletter-db.php';

$search  = trim($_GET['q']    ?? '');
$year    = trim($_GET['year'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

// Year filter is applied by narrowing the search to YYYY in the query URL
// We handle it below with a combined approach
$newsletters = nlGetNewsletters($search, $offset, $perPage);
$total       = nlCountNewsletters($search);
$pages       = (int)ceil($total / $perPage);
$years       = nlGetYears();

$loggedIn = true;

function nlFormatDate(string $dateStr): array {
    $ts = strtotime($dateStr);
    return [
        'month' => date('M', $ts),
        'day'   => date('j', $ts),
        'year'  => date('Y', $ts),
        'full'  => date('F j, Y', $ts),
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Newsletter Archive — BVTU</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .nl-wrap { max-width: 820px; margin: 0 auto; }
    /* Search bar */
    .nl-search-form {
      display: flex; gap: .6rem; margin-bottom: 2rem; flex-wrap: wrap;
    }
    .nl-search-form input[type="search"] {
      flex: 1; min-width: 220px;
      border: 1.5px solid var(--gray-200); border-radius: 8px;
      padding: .65rem 1rem; font-size: .95rem; font-family: inherit;
      transition: border-color .2s, box-shadow .2s; background: #fff;
    }
    .nl-search-form input:focus {
      outline: none; border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26,107,53,.1);
    }
    .nl-search-form button {
      padding: .65rem 1.3rem; border-radius: 8px;
      background: var(--primary); color: #fff;
      border: none; font-size: .9rem; font-weight: 600; cursor: pointer;
      transition: background .15s;
    }
    .nl-search-form button:hover { background: var(--primary-dk); }
    .nl-clear-search {
      font-size: .82rem; color: var(--gray-400); align-self: center;
      text-decoration: none; white-space: nowrap;
    }
    .nl-clear-search:hover { color: var(--primary); }
    /* Result meta */
    .nl-results-meta {
      font-size: .82rem; color: var(--gray-400); margin-bottom: 1rem;
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem;
    }
    /* Cards */
    .nl-list { display: flex; flex-direction: column; gap: .8rem; }
    .nl-card {
      display: flex; align-items: stretch; gap: 0;
      background: #fff; border: 1.5px solid var(--gray-200);
      border-radius: 10px; overflow: hidden; text-decoration: none; color: var(--text);
      transition: border-color .18s, box-shadow .18s, transform .15s;
    }
    .nl-card:hover {
      border-color: var(--primary); box-shadow: 0 4px 18px rgba(26,107,53,.09);
      transform: translateY(-2px); text-decoration: none; color: var(--text);
    }
    .nl-date-block {
      flex-shrink: 0; width: 72px; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      background: var(--accent); border-right: 1.5px solid var(--gray-100);
      padding: .9rem .5rem; text-align: center; gap: .05rem;
    }
    .nl-date-month { font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--primary); }
    .nl-date-day   { font-size: 1.6rem; font-weight: 800; line-height: 1; color: var(--primary-dk); }
    .nl-date-year  { font-size: .68rem; color: var(--gray-400); font-weight: 500; }
    .nl-body {
      flex: 1; padding: .9rem 1.15rem; min-width: 0;
      display: flex; flex-direction: column; justify-content: center; gap: .3rem;
    }
    .nl-subject {
      font-size: .98rem; font-weight: 700; color: var(--gray-800);
      margin: 0; line-height: 1.35;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .nl-preview {
      font-size: .83rem; color: var(--gray-500); line-height: 1.5; margin: 0;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .nl-read-btn {
      flex-shrink: 0; align-self: center; margin-right: 1rem;
      padding: .38rem .85rem; border-radius: 6px;
      background: var(--primary); color: #fff;
      font-size: .78rem; font-weight: 600; white-space: nowrap;
      transition: background .15s;
    }
    .nl-card:hover .nl-read-btn { background: var(--primary-dk); }
    /* Empty state */
    .nl-empty {
      text-align: center; padding: 4rem 1.5rem; color: var(--gray-400);
    }
    .nl-empty-icon { font-size: 2.5rem; margin-bottom: .75rem; }
    .nl-empty h3 { color: var(--gray-500); font-size: 1rem; margin: 0 0 .4rem; }
    .nl-empty p  { font-size: .88rem; margin: 0; }
    /* Pagination */
    .nl-pagination {
      display: flex; gap: .4rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap;
    }
    .nl-pagination a, .nl-pagination span {
      padding: .45rem .9rem; border-radius: 7px; font-size: .88rem; font-weight: 600;
      border: 1.5px solid var(--gray-200); background: #fff; color: var(--gray-600);
      text-decoration: none; transition: all .15s;
    }
    .nl-pagination a:hover { border-color: var(--primary); color: var(--primary); background: var(--accent); }
    .nl-pagination span.current { background: var(--primary); color: #fff; border-color: var(--primary); }
    /* Sync nudge banner */
    .nl-sync-nudge {
      background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;
      padding: .75rem 1rem; font-size: .85rem; color: #92400e; margin-bottom: 1.5rem;
    }
    @media (max-width: 540px) {
      .nl-subject { white-space: normal; }
      .nl-read-btn { display: none; }
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
          <li class="has-dropdown"><a href="members.php">Members</a><ul class="dropdown"><li><a href="members.php">Member Resources</a></li><li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li><li><a href="collab-grant.php">Collaboration Grant</a></li></ul></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://bctf.ca/member-services/efap" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li>
          <li><a href="newsletter-archive.php" class="active">Newsletters</a></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li>
          <li><a href="members/dashboard.php" class="btn btn-primary" style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;background:#1a6b35;border-color:#1a6b35;">My Dashboard</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Newsletter Archive</h1>
      <p>All BVTU newsletters, searchable and in one place. Members only.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">
      <div class="nl-wrap">

        <?php if ($total === 0 && $search === ''): ?>
          <div class="nl-sync-nudge">
            📭 No newsletters have been synced yet. Head to the
            <a href="members/newsletter-admin.php" style="font-weight:600;color:#92400e;">admin panel</a>
            to pull newsletters from Mailchimp.
          </div>
        <?php endif; ?>

        <!-- Search -->
        <form class="nl-search-form" method="get" action="newsletter-archive.php">
          <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"
                 placeholder="Search newsletters…" aria-label="Search newsletters">
          <button type="submit">Search</button>
          <?php if ($search): ?>
            <a href="newsletter-archive.php" class="nl-clear-search">✕ Clear</a>
          <?php endif; ?>
        </form>

        <!-- Results meta -->
        <div class="nl-results-meta">
          <span>
            <?php if ($search): ?>
              <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for
              "<strong><?= htmlspecialchars($search) ?></strong>"
            <?php else: ?>
              <?= $total ?> newsletter<?= $total !== 1 ? 's' : '' ?>
            <?php endif; ?>
          </span>
          <?php if ($total > 0): ?>
            <a href="members/newsletter-admin.php" style="font-size:.78rem;color:var(--gray-400);">Sync / Manage</a>
          <?php endif; ?>
        </div>

        <?php if (empty($newsletters)): ?>
          <div class="nl-empty">
            <div class="nl-empty-icon">📭</div>
            <h3><?= $search ? 'No newsletters matched your search.' : 'No newsletters yet.' ?></h3>
            <p><?= $search ? 'Try different keywords.' : 'Sync from Mailchimp to populate the archive.' ?></p>
          </div>
        <?php else: ?>

          <div class="nl-list">
            <?php foreach ($newsletters as $nl): ?>
              <?php
                $d = $nl['send_date'] ? nlFormatDate($nl['send_date']) : null;
                $preview = $nl['preview_text']
                  ? htmlspecialchars($nl['preview_text'])
                  : '';
              ?>
              <a href="newsletter.php?id=<?= $nl['id'] ?>" class="nl-card">
                <div class="nl-date-block">
                  <?php if ($d): ?>
                    <span class="nl-date-month"><?= $d['month'] ?></span>
                    <span class="nl-date-day"><?= $d['day'] ?></span>
                    <span class="nl-date-year"><?= $d['year'] ?></span>
                  <?php else: ?>
                    <span class="nl-date-month" style="font-size:.7rem;color:var(--gray-400);">—</span>
                  <?php endif; ?>
                </div>
                <div class="nl-body">
                  <p class="nl-subject"><?= htmlspecialchars($nl['subject']) ?></p>
                  <?php if ($preview): ?>
                    <p class="nl-preview"><?= $preview ?></p>
                  <?php endif; ?>
                </div>
                <span class="nl-read-btn">Read →</span>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if ($pages > 1): ?>
            <div class="nl-pagination">
              <?php
                $qs = $search ? '&q=' . urlencode($search) : '';
                if ($page > 1) echo "<a href='newsletter-archive.php?page=" . ($page-1) . $qs . "'>← Prev</a>";
                for ($p = 1; $p <= $pages; $p++) {
                    if ($p === $page) echo "<span class='current'>{$p}</span>";
                    elseif (abs($p - $page) <= 2 || $p === 1 || $p === $pages)
                        echo "<a href='newsletter-archive.php?page={$p}{$qs}'>{$p}</a>";
                    elseif (abs($p - $page) === 3) echo "<span>…</span>";
                }
                if ($page < $pages) echo "<a href='newsletter-archive.php?page=" . ($page+1) . $qs . "'>Next →</a>";
              ?>
            </div>
          <?php endif; ?>

        <?php endif; ?>
      </div>
    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <h3>Bulkley Valley Teachers' Union</h3>
        <p>Local of the BC Teachers' Federation</p>
        <p>Representing educators in<br>Houston, Telkwa, and Smithers</p>
      </div>
      <div><h3>Contact</h3><p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p><p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p><p><a href="contact.php">Contact Us</a></p></div>
      <div><h3>Navigate</h3><ul class="footer-nav-list"><li><a href="documents.php">Documents</a></li><li><a href="members.php">Members</a></li><li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li><li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://bctf.ca/member-services/benefits-and-services" target="_blank" rel="noopener">Member Benefits</a></li><li><a href="https://bctf.ca/bargaining" target="_blank" rel="noopener">Bargaining</a></li></ul></li></ul></div>
      <div><h3>Connect</h3><a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a></div>
    </div>
    <div class="footer-bottom"><div class="container"><p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p></div></div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
