<?php
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/library-db.php';

// Browsing is public — login only required to download/upload/bookmark
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

// Gather filters from GET
$selGrades   = array_filter($_GET['grades'] ?? [], fn($g) => in_array($g, LIB_GRADES));
// Accept both known subjects and any custom subject that exists in the DB
$_rawSubject = trim($_GET['subject'] ?? '');
$selSubject  = (in_array($_rawSubject, LIB_SUBJECTS) || $_rawSubject !== '') ? $_rawSubject : '';
$selType     = in_array($_GET['type'] ?? '', LIB_TYPES) ? $_GET['type'] : '';
$selTag      = trim($_GET['tag'] ?? '');
$selUploader = trim($_GET['uploader'] ?? '');
$q           = trim($_GET['q'] ?? '');
$sort        = in_array($_GET['sort'] ?? '', ['newest','downloads','rating']) ? $_GET['sort'] : 'newest';

$resources = libGetResources([
    'grades'   => $selGrades,
    'subject'  => $selSubject,
    'type'     => $selType,
    'tag'      => $selTag,
    'uploader' => $selUploader,
    'q'        => $q,
    'sort'     => $sort,
]);

$isAdmin    = $loggedIn && libIsAdmin($member['email']);
$hasFilters = $selGrades || $selSubject || $selType || $q || $selTag || $selUploader;

// For uploader filter: get a display name from first result
$uploaderName = '';
if ($selUploader && $resources) {
    $uploaderName = $resources[0]['uploader_name'] ?? '';
}

// Pre-load bookmark state — only for logged-in members
$myBookmarks = [];
if ($loggedIn && $resources) {
    $bmRows = getDB()->prepare(
        "SELECT resource_id FROM library_bookmarks WHERE member_email=?"
    );
    $bmRows->execute([$member['email']]);
    foreach ($bmRows->fetchAll() as $bm) $myBookmarks[$bm['resource_id']] = true;
}

function gradeLabel(string $g): string {
    return $g === 'K' ? 'K' : $g;
}
function buildUrl(array $overrides = []): string {
    $p = array_merge($_GET, $overrides);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== [] && $v !== null);
    return 'library.php?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Resource Library — BVTU</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    /* ── Library layout ──────────────────────────────────────── */
    .lib-layout {
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 2rem;
      align-items: start;
    }

    /* ── Filter sidebar ──────────────────────────────────────── */
    .lib-sidebar {
      position: sticky;
      top: calc(var(--hdr-h) + 1rem);
    }
    .lib-filter-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem;
      margin-bottom: 1rem;
    }
    .lib-filter-title {
      font-size: .75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--gray-400);
      margin-bottom: .75rem;
    }
    .lib-filter-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: .35rem;
    }
    .lib-filter-list li label {
      display: flex;
      align-items: center;
      gap: .5rem;
      font-size: .87rem;
      color: var(--gray-700);
      cursor: pointer;
      padding: .2rem 0;
    }
    .lib-filter-list li label:hover { color: var(--primary); }
    .lib-filter-list input[type="checkbox"],
    .lib-filter-list input[type="radio"] { accent-color: var(--primary); }
    .lib-filter-active { color: var(--primary) !important; font-weight: 600; }

    /* ── Search + sort bar ───────────────────────────────────── */
    .lib-search-bar {
      display: flex;
      gap: .75rem;
      align-items: center;
      margin-bottom: 1.25rem;
      flex-wrap: wrap;
    }
    .lib-search-bar input[type="search"] {
      flex: 1;
      min-width: 180px;
      border: 1.5px solid var(--gray-200);
      border-radius: 8px;
      padding: .6rem .9rem;
      font-size: .93rem;
      font-family: inherit;
      background: #fff;
    }
    .lib-search-bar input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26,107,53,.1);
    }
    .lib-search-bar select {
      border: 1.5px solid var(--gray-200);
      border-radius: 8px;
      padding: .6rem .85rem;
      font-size: .88rem;
      font-family: inherit;
      background: #fff;
      color: var(--gray-700);
    }
    .lib-search-bar select:focus { outline: none; border-color: var(--primary); }
    .lib-count {
      font-size: .85rem;
      color: var(--gray-400);
      margin-bottom: 1rem;
    }

    /* ── Resource cards ──────────────────────────────────────── */
    .lib-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
    }
    .lib-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.2rem 1.25rem 1rem;
      display: flex;
      flex-direction: column;
      text-decoration: none;
      color: var(--text);
      transition: border-color .15s, box-shadow .15s, transform .12s;
    }
    .lib-card:hover {
      border-color: var(--primary);
      box-shadow: 0 4px 16px rgba(27,107,66,.1);
      transform: translateY(-2px);
    }
    .lib-card-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: .5rem;
      margin-bottom: .6rem;
    }
    .lib-card-type {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: var(--primary);
      background: var(--accent);
      border-radius: 4px;
      padding: .15rem .5rem;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .lib-card-title {
      font-size: .97rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.4;
      margin-bottom: .4rem;
    }
    .lib-card-desc {
      font-size: .83rem;
      color: var(--gray-500);
      line-height: 1.55;
      flex: 1;
      /* 2-line clamp */
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      margin-bottom: .75rem;
    }
    .lib-card-grades {
      display: flex;
      flex-wrap: wrap;
      gap: .3rem;
      margin-bottom: .6rem;
    }
    .lib-grade-badge {
      font-size: .72rem;
      font-weight: 700;
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: .1rem .4rem;
      color: var(--gray-600);
      background: var(--off-white);
    }
    .lib-subject-badge {
      font-size: .72rem;
      font-weight: 600;
      border-radius: 4px;
      padding: .1rem .5rem;
      background: #e8f0fb;
      color: #1a3a7a;
    }
    .lib-card-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: .78rem;
      color: var(--gray-400);
      margin-top: .6rem;
      padding-top: .6rem;
      border-top: 1px solid var(--gray-100);
    }
    .lib-card-rating { color: #f59e0b; font-size: .82rem; }
    .lib-card-rating span { color: var(--gray-400); }

    /* ── Empty state ─────────────────────────────────────────── */
    .lib-empty {
      text-align: center;
      padding: 3.5rem 1rem;
      color: var(--gray-400);
    }
    .lib-empty svg { width: 48px; height: 48px; margin-bottom: 1rem; opacity: .4; }
    .lib-empty h3 { color: var(--gray-600); font-size: 1.05rem; margin-bottom: .4rem; }
    .lib-empty p  { font-size: .9rem; }

    /* ── Upload CTA ──────────────────────────────────────────── */
    .lib-upload-cta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
      background: var(--accent);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.1rem 1.4rem;
      margin-bottom: 1.75rem;
    }
    .lib-upload-cta p { margin: 0; font-size: .92rem; color: var(--gray-700); }

    @media (max-width: 800px) {
      .lib-layout { grid-template-columns: 1fr; }
      .lib-sidebar { position: static; }
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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li></ul></li>
          <li class="has-dropdown"><a href="members.php">Members</a><ul class="dropdown"><li><a href="members.php">Member Resources</a></li><li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li><li><a href="collab-grant.php">Collaboration Grant</a></li></ul></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="library.php" class="active">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="/members/dashboard.php" class="btn btn-primary" style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;background:#1a6b35;border-color:#1a6b35;">My Dashboard</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Resource Library</h1>
      <p>Browse and download lesson plans, units, rubrics, and activities shared by BVTU members.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="lib-upload-cta">
        <p>Have a resource to share? Upload it and help your colleagues.</p>
        <a href="library-upload.php" class="btn btn-primary" style="white-space:nowrap;">+ Upload a resource</a>
      </div>

      <div class="lib-layout">

        <!-- Filter sidebar -->
        <aside class="lib-sidebar">
          <form method="get" id="filter-form">
            <?php if ($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
            <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>

            <div class="lib-filter-card">
              <div class="lib-filter-title">Grade</div>
              <ul class="lib-filter-list">
                <?php foreach (LIB_GRADES as $g): ?>
                  <li><label>
                    <input type="checkbox" name="grades[]" value="<?= $g ?>"
                      <?= in_array($g, $selGrades) ? 'checked' : '' ?>
                      onchange="this.form.submit()">
                    <?= $g === 'K' ? 'Kindergarten' : 'Grade ' . $g ?>
                  </label></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <div class="lib-filter-card">
              <div class="lib-filter-title">Subject</div>
              <ul class="lib-filter-list">
                <li><label>
                  <input type="radio" name="subject" value=""
                    <?= !$selSubject ? 'checked' : '' ?> onchange="this.form.submit()"> All subjects
                </label></li>
                <?php foreach (array_filter(LIB_SUBJECTS, fn($s) => $s !== 'Other') as $s): ?>
                  <li><label class="<?= strcasecmp($selSubject, $s) === 0 ? 'lib-filter-active' : '' ?>">
                    <input type="radio" name="subject" value="<?= htmlspecialchars($s) ?>"
                      <?= strcasecmp($selSubject, $s) === 0 ? 'checked' : '' ?> onchange="this.form.submit()">
                    <?= htmlspecialchars($s) ?>
                  </label></li>
                <?php endforeach; ?>
                <?php
                  // Dynamically add any teacher-entered custom subjects from the DB
                  $customSubjects = libGetCustomSubjects();
                  if ($customSubjects): ?>
                  <li style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--gray-300);margin:.5rem 0 .25rem;padding-left:.1rem;">Other</li>
                  <?php foreach ($customSubjects as $cs): ?>
                    <li><label class="<?= strcasecmp($selSubject, $cs) === 0 ? 'lib-filter-active' : '' ?>">
                      <input type="radio" name="subject" value="<?= htmlspecialchars($cs) ?>"
                        <?= strcasecmp($selSubject, $cs) === 0 ? 'checked' : '' ?> onchange="this.form.submit()">
                      <?= htmlspecialchars($cs) ?>
                    </label></li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
            </div>

            <div class="lib-filter-card">
              <div class="lib-filter-title">Type</div>
              <ul class="lib-filter-list">
                <li><label>
                  <input type="radio" name="type" value=""
                    <?= !$selType ? 'checked' : '' ?> onchange="this.form.submit()"> All types
                </label></li>
                <?php foreach (LIB_TYPES as $t): ?>
                  <li><label class="<?= $selType === $t ? 'lib-filter-active' : '' ?>">
                    <input type="radio" name="type" value="<?= $t ?>"
                      <?= $selType === $t ? 'checked' : '' ?> onchange="this.form.submit()">
                    <?= $t ?>
                  </label></li>
                <?php endforeach; ?>
              </ul>
            </div>

            <?php if ($hasFilters): ?>
              <a href="library.php" style="font-size:.82rem;color:var(--gray-400);text-decoration:none;display:block;text-align:center;padding:.25rem;">
                × Clear all filters
              </a>
            <?php endif; ?>
          </form>
        </aside>

        <!-- Results -->
        <div>
          <form method="get" class="lib-search-bar">
            <?php foreach ($selGrades as $g): ?><input type="hidden" name="grades[]" value="<?= htmlspecialchars($g) ?>"><?php endforeach; ?>
            <?php if ($selSubject): ?><input type="hidden" name="subject" value="<?= htmlspecialchars($selSubject) ?>"><?php endif; ?>
            <?php if ($selType):    ?><input type="hidden" name="type"    value="<?= htmlspecialchars($selType) ?>"><?php endif; ?>
            <input type="search" name="q" placeholder="Search titles and descriptions…" value="<?= htmlspecialchars($q) ?>">
            <select name="sort" onchange="this.form.submit()">
              <option value="newest"    <?= $sort === 'newest'    ? 'selected' : '' ?>>Newest first</option>
              <option value="downloads" <?= $sort === 'downloads' ? 'selected' : '' ?>>Most downloaded</option>
              <option value="rating"    <?= $sort === 'rating'    ? 'selected' : '' ?>>Highest rated</option>
            </select>
            <button type="submit" class="btn btn-primary" style="padding:.58rem 1rem;font-size:.88rem;">Search</button>
          </form>

          <!-- Context banners for tag/uploader filters -->
          <?php if ($selUploader && $uploaderName): ?>
            <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:var(--radius-s);padding:.6rem 1rem;margin-bottom:.75rem;font-size:.88rem;color:#166534;display:flex;align-items:center;justify-content:space-between;">
              <span>Resources by <strong><?= htmlspecialchars($uploaderName) ?></strong></span>
              <a href="library.php" style="color:#166534;font-weight:600;font-size:.8rem;">× Clear</a>
            </div>
          <?php elseif ($selTag): ?>
            <div style="background:#e0f2fe;border:1px solid #7dd3fc;border-radius:var(--radius-s);padding:.6rem 1rem;margin-bottom:.75rem;font-size:.88rem;color:#0369a1;display:flex;align-items:center;justify-content:space-between;">
              <span>Tagged: <strong><?= htmlspecialchars($selTag) ?></strong></span>
              <a href="library.php" style="color:#0369a1;font-weight:600;font-size:.8rem;">× Clear</a>
            </div>
          <?php endif; ?>

          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;">
            <p class="lib-count" style="margin:0;">
              <?= count($resources) ?> resource<?= count($resources) !== 1 ? 's' : '' ?>
              <?= $hasFilters || $q ? '— <a href="library.php" style="color:var(--gray-400);font-size:.9em;">clear filters</a>' : '' ?>
            </p>
            <?php if ($loggedIn): ?>
            <a href="library-saved.php" style="font-size:.8rem;color:var(--primary);font-weight:600;text-decoration:none;display:flex;align-items:center;gap:.3rem;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
              Saved
            </a>
            <?php endif; ?>
          </div>

          <?php if (empty($resources)): ?>
            <div class="lib-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <h3>No resources found</h3>
              <p><?= $hasFilters || $q ? 'Try adjusting your filters or search terms.' : 'Be the first to upload a resource!' ?></p>
              <a href="library-upload.php" class="btn btn-primary" style="margin-top:1rem;">Upload a resource</a>
            </div>

          <?php else: ?>
            <div class="lib-grid">
              <?php foreach ($resources as $r):
                $grades      = array_filter(explode(',', $r['grade_levels']));
                $tags        = $r['tags'] ? array_slice(array_map('trim', explode(',', $r['tags'])), 0, 3) : [];
                $rating      = (float)$r['avg_rating'];
                $stars       = $rating > 0 ? str_repeat('★', (int)round($rating)) . str_repeat('☆', 5 - (int)round($rating)) : '';
                $bookmarked  = !empty($myBookmarks[$r['id']]);
              ?>
              <div class="lib-card" style="position:relative;">
                <!-- Bookmark button — members only -->
                <?php if ($loggedIn): ?>
                <button class="lib-bm-btn" data-id="<?= $r['id'] ?>"
                        title="<?= $bookmarked ? 'Remove bookmark' : 'Save for later' ?>"
                        aria-label="<?= $bookmarked ? 'Remove bookmark' : 'Save for later' ?>"
                        style="position:absolute;top:.55rem;right:.55rem;background:none;border:none;cursor:pointer;padding:.3rem;border-radius:50%;transition:background .15s;z-index:2;"
                        onmouseover="this.style.background='rgba(0,0,0,.07)'"
                        onmouseout="this.style.background='none'">
                  <svg width="17" height="17" viewBox="0 0 24 24" fill="<?= $bookmarked ? '#f59e0b' : 'none' ?>"
                       stroke="<?= $bookmarked ? '#f59e0b' : 'var(--gray-300)' ?>" stroke-width="2"
                       stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                  </svg>
                </button>
                <?php endif; ?>

                <a href="library-resource.php?id=<?= $r['id'] ?>" class="lib-card-link" style="display:flex;flex-direction:column;flex:1;color:inherit;text-decoration:none;">
                  <div class="lib-card-top">
                    <div></div>
                    <span class="lib-card-type"><?= htmlspecialchars($r['resource_type']) ?></span>
                  </div>
                  <div class="lib-card-title"><?= htmlspecialchars($r['title']) ?></div>
                  <?php if (!$r['anonymous']): ?>
                    <div style="font-size:.73rem;color:var(--gray-400);padding:0 1rem .25rem;">
                      by <a href="library.php?uploader=<?= urlencode($r['uploader_email']) ?>"
                            style="color:var(--primary);font-weight:600;text-decoration:none;"
                            onclick="event.stopPropagation();"><?= htmlspecialchars($r['uploader_name']) ?></a>
                    </div>
                  <?php endif; ?>
                  <div class="lib-card-desc"><?= htmlspecialchars($r['description']) ?></div>
                  <?php if ($tags): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:.3rem;padding:0 1rem .5rem;">
                      <?php foreach ($tags as $tag): ?>
                        <a href="library.php?tag=<?= urlencode($tag) ?>"
                           style="font-size:.68rem;background:#e0f2fe;color:#0369a1;padding:.15rem .5rem;border-radius:100px;font-weight:600;text-decoration:none;"
                           onclick="event.stopPropagation();"><?= htmlspecialchars($tag) ?></a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <div class="lib-card-grades">
                    <?php foreach ($grades as $g): ?>
                      <span class="lib-grade-badge"><?= gradeLabel($g) ?></span>
                    <?php endforeach; ?>
                    <?php if ($r['subject']): ?>
                      <span class="lib-subject-badge"><?= htmlspecialchars($r['subject']) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="lib-card-meta">
                    <span style="font-size:.73rem;color:var(--gray-400);"><?= date('M Y', strtotime($r['created_at'])) ?></span>
                    <span>
                      <?php if ($stars): ?>
                        <span class="lib-card-rating"><?= $stars ?> <span>(<?= $r['rating_count'] ?>)</span></span>
                      <?php endif; ?>
                      &nbsp;↓ <?= $r['download_count'] ?>
                    </span>
                  </div>
                </a>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div><!-- /.lib-layout -->
    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div><h3>Bulkley Valley Teachers' Union</h3><p>Local of the BC Teachers' Federation</p><p>Representing educators in<br>Houston, Telkwa, and Smithers</p></div>
      <div><h3>Contact</h3><p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p><p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p><p><a href="contact.php">Contact Us</a></p></div>
      <div><h3>Navigate</h3><ul class="footer-nav-list"><li><a href="documents.php">Documents</a></li><li><a href="members.php">Members</a></li><li><a href="library.php">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li><li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li><li><a href="bctf.php">BCTF</a></li></ul></div>
      <div><h3>Connect</h3><a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a></div>
    </div>
    <div class="footer-bottom"><div class="container"><p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p></div></div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
  <script>
    // Bookmark toggle on library cards
    document.querySelectorAll('.lib-bm-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const id  = this.dataset.id;
        const svg = this.querySelector('svg');
        fetch('library-bookmark.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id=' + id,
        })
        .then(r => r.json())
        .then(data => {
          const c = data.bookmarked ? '#f59e0b' : 'var(--gray-300)';
          svg.setAttribute('fill',   data.bookmarked ? '#f59e0b' : 'none');
          svg.setAttribute('stroke', c);
          this.title     = data.bookmarked ? 'Remove bookmark' : 'Save for later';
          this.ariaLabel = this.title;
        });
      });
    });
  </script>
</body>
</html>
