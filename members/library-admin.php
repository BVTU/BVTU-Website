<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/library-db.php';
requireLogin();

$member = getMember();
if (!libIsAdmin($member['email'])) {
    http_response_code(403);
    exit('Access denied.');
}

// ── Handle actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid    = (int)($_POST['rid'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($aid) {
        if ($action === 'publish')   libUpdateStatus($aid, 'published');
        if ($action === 'unpublish') libUpdateStatus($aid, 'unpublished');
        if ($action === 'delete') {
            libDelete($aid);
        }
    }

    if (!empty($_POST['flag_id'])) {
        libMarkFlagReviewed((int)$_POST['flag_id']);
    }

    header('Location: library-admin.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

// ── CSV export ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $all = libGetResources([], true);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bvtu-library-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Title','Type','Subject','Grades','Uploader','Anonymous','Status','Downloads','Avg Rating','Rating Count','Uploaded']);
    foreach ($all as $r) {
        fputcsv($out, [
            $r['id'], $r['title'], $r['resource_type'], $r['subject'],
            $r['grade_levels'], $r['uploader_name'],
            $r['anonymous'] ? 'Yes' : 'No', $r['status'],
            $r['download_count'], $r['avg_rating'], $r['rating_count'],
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$filterStatus = in_array($_GET['status'] ?? '', ['published','unpublished','all']) ? $_GET['status'] : 'all';
$filterQ      = trim($_GET['q'] ?? '');

$fetchFilters = [];
if ($filterStatus !== 'all') $fetchFilters['status'] = $filterStatus;
if ($filterQ) $fetchFilters['q'] = $filterQ;

$resources = libGetResources($fetchFilters, true);
$flags      = libGetFlags(true); // unreviewed only
$stats      = libStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="../">
  <title>Library Admin — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    /* ── Page frame ──────────────────────────────────────────── */
    .la-hero {
      background: linear-gradient(140deg, var(--primary-dk) 0%, var(--primary) 100%);
      color: var(--white);
      padding: calc(var(--hdr-h) + 1.5rem) 0 1.5rem;
    }
    .la-hero h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: .25rem; }
    .la-hero p  { opacity: .75; font-size: .9rem; }
    .la-hero-row { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }

    /* ── Stats strip ─────────────────────────────────────────── */
    .la-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: .75rem;
      margin-bottom: 1.75rem;
    }
    .la-stat {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem 1.1rem;
      text-align: center;
    }
    .la-stat-num { font-size: 1.75rem; font-weight: 800; color: var(--primary); line-height: 1; margin-bottom: .2rem; }
    .la-stat-label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-400); }

    /* ── Toolbar ─────────────────────────────────────────────── */
    .la-toolbar {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: .6rem;
      margin-bottom: 1.25rem;
    }
    .la-toolbar-search {
      flex: 1;
      min-width: 180px;
      max-width: 320px;
      padding: .5rem .85rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-s);
      font-size: .88rem;
      font-family: inherit;
      color: var(--text);
    }
    .la-toolbar-search:focus { outline: none; border-color: var(--primary); }
    .la-filter-btn {
      padding: .45rem .85rem;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-s);
      background: var(--white);
      font-size: .83rem;
      font-weight: 600;
      color: var(--gray-600);
      cursor: pointer;
      text-decoration: none;
      transition: border-color .15s, color .15s;
    }
    .la-filter-btn:hover, .la-filter-btn.active {
      border-color: var(--primary);
      color: var(--primary);
    }
    .la-export-btn {
      margin-left: auto;
      padding: .45rem .85rem;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: var(--radius-s);
      font-size: .83rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: .35rem;
      transition: background .15s;
    }
    .la-export-btn:hover { background: var(--primary-dk); }
    .la-export-btn svg { width: 14px; height: 14px; }

    /* ── Table ───────────────────────────────────────────────── */
    .la-table-wrap {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      margin-bottom: 2rem;
    }
    .la-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .88rem;
    }
    .la-table thead tr {
      background: var(--gray-50, #f9fafb);
      border-bottom: 2px solid var(--border);
    }
    .la-table th {
      padding: .65rem 1rem;
      text-align: left;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--gray-400);
      white-space: nowrap;
    }
    .la-table td {
      padding: .75rem 1rem;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }
    .la-table tbody tr:last-child td { border-bottom: none; }
    .la-table tbody tr:hover { background: var(--gray-50, #f9fafb); }
    .la-title-cell a { font-weight: 600; color: var(--primary); text-decoration: none; }
    .la-title-cell a:hover { text-decoration: underline; }
    .la-title-cell .la-meta { font-size: .78rem; color: var(--gray-400); margin-top: .15rem; }
    .la-badge {
      display: inline-block;
      padding: .18rem .55rem;
      border-radius: 100px;
      font-size: .7rem;
      font-weight: 700;
      letter-spacing: .03em;
    }
    .la-badge-pub   { background: #dcfce7; color: #166534; }
    .la-badge-unpub { background: #fef3c7; color: #92400e; }
    .la-action-btn {
      font-size: .75rem;
      font-weight: 600;
      padding: .25rem .6rem;
      border-radius: var(--radius-s);
      border: 1.5px solid;
      cursor: pointer;
      background: none;
      transition: background .12s, color .12s;
      white-space: nowrap;
    }
    .la-btn-view    { border-color: var(--primary);  color: var(--primary);  }
    .la-btn-unpub   { border-color: #f59e0b; color: #f59e0b; }
    .la-btn-pub     { border-color: #10b981; color: #10b981; }
    .la-btn-del     { border-color: #ef4444; color: #ef4444; }
    .la-btn-view:hover  { background: var(--primary); color: #fff; }
    .la-btn-unpub:hover { background: #f59e0b; color: #fff; }
    .la-btn-pub:hover   { background: #10b981; color: #fff; }
    .la-btn-del:hover   { background: #ef4444; color: #fff; }
    .la-actions { display: flex; gap: .35rem; align-items: center; }
    .la-stars { color: #f59e0b; font-size: .85rem; }
    .la-stars .e { color: var(--gray-200); }

    /* ── Flags section ───────────────────────────────────────── */
    .la-section-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: .85rem;
      padding-bottom: .4rem;
      border-bottom: 2px solid var(--accent);
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .la-flag-count {
      background: #ef4444;
      color: #fff;
      font-size: .7rem;
      font-weight: 700;
      padding: .1rem .45rem;
      border-radius: 100px;
    }
    .la-flag-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem 1.25rem;
      margin-bottom: .75rem;
      display: flex;
      align-items: flex-start;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .la-flag-card .la-flag-body { flex: 1; min-width: 200px; }
    .la-flag-card .la-flag-title { font-weight: 700; font-size: .9rem; margin-bottom: .2rem; }
    .la-flag-card .la-flag-title a { color: var(--primary); text-decoration: none; }
    .la-flag-card .la-flag-title a:hover { text-decoration: underline; }
    .la-flag-meta { font-size: .78rem; color: var(--gray-400); margin-bottom: .4rem; }
    .la-flag-reason { font-size: .83rem; color: var(--gray-600); font-style: italic; }
    .la-no-flags {
      text-align: center;
      padding: 2rem;
      color: var(--gray-400);
      font-size: .88rem;
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      margin-bottom: 2rem;
    }
    .la-empty {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--gray-400);
      font-size: .9rem;
    }

    @media print {
      .la-toolbar, .la-hero .btn, .la-action-btn { display: none !important; }
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="header-inner container">
      <a href="../index.php" class="logo">
        <img src="../bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <nav class="main-nav">
        <ul>
          <li><a href="../documents.php">Documents</a></li>
          <li><a href="../members.php">Members</a></li>
          <li><a href="../library.php">Resource Library</a></li>
          <li><a href="dashboard.php">Dashboard</a></li>
          <li><a href="logout.php">Sign Out</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div class="la-hero">
    <div class="container">
      <div class="la-hero-row">
        <div>
          <h1>Resource Library — Admin</h1>
          <p><?= $stats['total'] ?> resources · <?= $stats['published'] ?> published · <?= $stats['downloads'] ?> total downloads</p>
        </div>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
          <a href="library-admin.php?export=csv" class="la-export-btn">
            <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
          </a>
          <a href="../library-upload.php" class="btn btn-outline-white" style="font-size:.83rem;">Upload Resource</a>
          <a href="dashboard.php" class="btn btn-outline-white" style="font-size:.83rem;">← Dashboard</a>
        </div>
      </div>
    </div>
  </div>

  <main class="page-content">
    <div class="container">

      <!-- Stats -->
      <div class="la-stats">
        <div class="la-stat">
          <div class="la-stat-num"><?= $stats['total'] ?></div>
          <div class="la-stat-label">Total Resources</div>
        </div>
        <div class="la-stat">
          <div class="la-stat-num"><?= $stats['published'] ?></div>
          <div class="la-stat-label">Published</div>
        </div>
        <div class="la-stat">
          <div class="la-stat-num"><?= $stats['total'] - $stats['published'] ?></div>
          <div class="la-stat-label">Unpublished</div>
        </div>
        <div class="la-stat">
          <div class="la-stat-num"><?= number_format($stats['downloads']) ?></div>
          <div class="la-stat-label">Downloads</div>
        </div>
        <div class="la-stat">
          <div class="la-stat-num"><?= $stats['ratings'] ?></div>
          <div class="la-stat-label">Ratings</div>
        </div>
        <div class="la-stat" style="<?= $stats['flags'] ? 'border-color:#ef4444;' : '' ?>">
          <div class="la-stat-num" style="<?= $stats['flags'] ? 'color:#ef4444;' : '' ?>"><?= $stats['flags'] ?></div>
          <div class="la-stat-label">Open Flags</div>
        </div>
      </div>

      <!-- ── Flagged content ─────────────────────────────────── -->
      <div style="margin-bottom: 2rem;">
        <div class="la-section-title">
          Flagged Content
          <?php if ($flags): ?>
            <span class="la-flag-count"><?= count($flags) ?></span>
          <?php endif; ?>
        </div>

        <?php if (!$flags): ?>
          <div class="la-no-flags">✓ No unreviewed flags</div>
        <?php else: ?>
          <?php foreach ($flags as $flag): ?>
            <div class="la-flag-card">
              <div class="la-flag-body">
                <div class="la-flag-title">
                  <a href="../library-resource.php?id=<?= $flag['resource_id'] ?>">
                    <?= htmlspecialchars($flag['resource_title']) ?>
                  </a>
                  <span style="font-weight:400;color:var(--gray-400);"> — <?= htmlspecialchars($flag['resource_status']) ?></span>
                </div>
                <div class="la-flag-meta">
                  Reported by <?= htmlspecialchars($flag['reporter_email']) ?> · <?= date('M j, Y', strtotime($flag['created_at'])) ?>
                </div>
                <?php if ($flag['reason']): ?>
                  <div class="la-flag-reason">"<?= htmlspecialchars($flag['reason']) ?>"</div>
                <?php endif; ?>
              </div>
              <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
                <a href="../library-resource.php?id=<?= $flag['resource_id'] ?>" class="la-action-btn la-btn-view">View</a>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="flag_id" value="<?= $flag['id'] ?>">
                  <button type="submit" class="la-action-btn la-btn-pub">Mark Reviewed</button>
                </form>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Unpublish this resource?')">
                  <input type="hidden" name="rid"    value="<?= $flag['resource_id'] ?>">
                  <input type="hidden" name="action" value="unpublish">
                  <button type="submit" class="la-action-btn la-btn-unpub">Unpublish</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- ── Resources table ────────────────────────────────── -->
      <div class="la-section-title">All Resources</div>

      <!-- Toolbar -->
      <form method="GET" class="la-toolbar">
        <input type="text" name="q" value="<?= htmlspecialchars($filterQ) ?>"
               class="la-toolbar-search" placeholder="Search by title or description…">
        <?php
        $statusLinks = ['all' => 'All', 'published' => 'Published', 'unpublished' => 'Unpublished'];
        foreach ($statusLinks as $val => $label):
            $href = 'library-admin.php?' . http_build_query(array_merge($_GET, ['status' => $val]));
        ?>
          <a href="<?= $href ?>" class="la-filter-btn <?= $filterStatus === $val ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <button type="submit" class="la-filter-btn" style="border-color:var(--primary);color:var(--primary);">Search</button>
        <a href="library-admin.php?export=csv" class="la-export-btn" style="margin-left:auto;">
          <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export CSV
        </a>
      </form>

      <?php if (!$resources): ?>
        <div class="la-empty">No resources found.</div>
      <?php else: ?>
      <div class="la-table-wrap">
        <table class="la-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Title / Uploader</th>
              <th>Type</th>
              <th>Subject</th>
              <th>Grades</th>
              <th>Status</th>
              <th>DLs</th>
              <th>Rating</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($resources as $r): ?>
            <tr>
              <td style="color:var(--gray-400);font-size:.78rem;"><?= $r['id'] ?></td>
              <td class="la-title-cell">
                <a href="../library-resource.php?id=<?= $r['id'] ?>" target="_blank">
                  <?= htmlspecialchars($r['title']) ?>
                </a>
                <div class="la-meta">
                  <?= $r['anonymous'] ? 'Anonymous' : htmlspecialchars($r['uploader_name']) ?>
                  · <?= strtoupper($r['file_ext']) ?>
                  · <?= libFormatSize($r['file_size']) ?>
                </div>
              </td>
              <td style="font-size:.82rem;"><?= htmlspecialchars($r['resource_type']) ?></td>
              <td style="font-size:.82rem;"><?= htmlspecialchars($r['subject']) ?></td>
              <td style="font-size:.82rem;"><?= htmlspecialchars($r['grade_levels']) ?></td>
              <td>
                <span class="la-badge <?= $r['status'] === 'published' ? 'la-badge-pub' : 'la-badge-unpub' ?>">
                  <?= $r['status'] ?>
                </span>
              </td>
              <td style="font-size:.82rem;text-align:center;"><?= $r['download_count'] ?></td>
              <td style="font-size:.82rem;">
                <?php if ($r['rating_count'] > 0): ?>
                  <span class="la-stars"><?= str_repeat('★', (int)round($r['avg_rating'])) ?><span class="e"><?= str_repeat('★', 5 - (int)round($r['avg_rating'])) ?></span></span>
                  <span style="font-size:.75rem;color:var(--gray-400);"> (<?= $r['rating_count'] ?>)</span>
                <?php else: ?>
                  <span style="color:var(--gray-300);">—</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
              <td>
                <div class="la-actions">
                  <a href="../library-resource.php?id=<?= $r['id'] ?>" class="la-action-btn la-btn-view" target="_blank">View</a>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="rid" value="<?= $r['id'] ?>">
                    <?php if ($r['status'] === 'published'): ?>
                      <input type="hidden" name="action" value="unpublish">
                      <button type="submit" class="la-action-btn la-btn-unpub"
                              onclick="return confirm('Unpublish this resource?')">Unpublish</button>
                    <?php else: ?>
                      <input type="hidden" name="action" value="publish">
                      <button type="submit" class="la-action-btn la-btn-pub">Publish</button>
                    <?php endif; ?>
                  </form>
                  <form method="POST" style="display:inline;"
                        onsubmit="return confirm('Permanently delete this resource and file? This cannot be undone.')">
                    <input type="hidden" name="rid"    value="<?= $r['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="la-action-btn la-btn-del">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    </div><!-- /container -->
  </main>

  <footer class="site-footer">
    <div class="footer-bottom" style="border-top: none;">
      <div class="container">
        <p style="padding: 1.5rem 0; color: rgba(255,255,255,.5);">© 2026 Bulkley Valley Teachers' Union</p>
      </div>
    </div>
  </footer>

  <script src="../js/site.js"></script>
</body>
</html>
