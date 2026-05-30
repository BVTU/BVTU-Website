<?php
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/library-db.php';

// Browsing is public — login required only to download / rate / bookmark
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
$isAdmin  = $loggedIn && libIsAdmin($member['email']);

$id       = (int)($_GET['id'] ?? 0);
$resource = $id ? libGetResource($id) : null;

if (!$resource || ($resource['status'] !== 'published' && !$isAdmin)) {
    http_response_code(404);
    $notFound = true;
}

// Handle rating POST — members only
$ratingError   = '';
$ratingSuccess = false;
if ($loggedIn && !isset($notFound) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lib_rate'])) {
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $ratingError = 'Please choose a star rating from 1–5.';
    } else {
        libAddRating($id, $member['email'], $member['name'], $rating, $comment);
        libNotifyRating($resource, $member['name'], $rating, $comment);
        $ratingSuccess = true;
        $resource = libGetResource($id);
    }
}

// Handle flag POST — members only
$flagDone = false;
if ($loggedIn && !isset($notFound) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lib_flag'])) {
    $reason = trim($_POST['flag_reason'] ?? '');
    libAddFlag($id, $member['email'], $reason);
    $flagDone = true;
}

// Handle admin status change
if (!isset($notFound) && $isAdmin && isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'unpublish') libUpdateStatus($id, 'unpublished');
    if ($action === 'publish')   libUpdateStatus($id, 'published');
    if ($action === 'delete') {
        libDelete($id);
        header('Location: library.php');
        exit;
    }
    header('Location: library-resource.php?id=' . $id);
    exit;
}

// Owner delete — POST only, separate from the admin GET action to avoid accidental triggers
$isOwner = $loggedIn && !isset($notFound) && $member['email'] === $resource['uploader_email'];
if ($isOwner && !isset($notFound) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'owner_delete') {
    libDelete($id);
    header('Location: library.php?deleted=1');
    exit;
}

$ratings     = isset($notFound) ? [] : libGetRatings($id);
$myRating    = ($loggedIn && !isset($notFound)) ? libGetMemberRating($id, $member['email']) : null;
$grades      = isset($notFound) ? [] : ($resource['grade_levels'] ? explode(',', $resource['grade_levels']) : []);
$tags        = isset($notFound) ? [] : ($resource['tags'] ? array_map('trim', explode(',', $resource['tags'])) : []);
$bookmarked  = ($loggedIn && !isset($notFound)) ? libIsBookmarked($id, $member['email']) : false;
$extraFiles  = isset($notFound) ? [] : libGetResourceFiles($id);
$downloadUrl = 'members/library-serve.php?id=' . $id;
$previewUrl  = 'members/library-serve.php?id=' . $id . '&preview=1';
$loginUrl    = 'members/login.php?redirect=' . urlencode('../library-resource.php?id=' . $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title><?= isset($notFound) ? 'Not Found' : htmlspecialchars($resource['title']) ?> — BVTU Library</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    /* ── Resource detail layout ──────────────────────────────── */
    .res-layout {
      display: grid;
      grid-template-columns: 1fr 280px;
      gap: 2rem;
      align-items: start;
    }
    @media (max-width: 800px) {
      .res-layout { grid-template-columns: 1fr; }
    }

    /* ── Main card ───────────────────────────────────────────── */
    .res-main-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
    }
    .res-hero {
      background: linear-gradient(140deg, var(--primary-dk) 0%, var(--primary) 100%);
      padding: 2rem 2rem 1.5rem;
      color: var(--white);
    }
    .res-hero-back {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      color: rgba(255,255,255,.7);
      font-size: .82rem;
      font-weight: 500;
      text-decoration: none;
      margin-bottom: 1rem;
      transition: color .15s;
    }
    .res-hero-back:hover { color: #fff; }
    .res-hero-back svg { width: 14px; height: 14px; }
    .res-type-badge {
      display: inline-block;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      background: rgba(255,255,255,.2);
      color: #fff;
      padding: .2rem .6rem;
      border-radius: 100px;
      margin-bottom: .65rem;
    }
    .res-hero h1 {
      font-size: 1.55rem;
      font-weight: 800;
      margin: 0 0 .6rem;
      line-height: 1.3;
    }
    .res-meta-row {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: .5rem;
      font-size: .83rem;
      color: rgba(255,255,255,.8);
    }
    .res-meta-row .sep { opacity: .4; }
    .res-body { padding: 1.75rem 2rem; }
    .res-section { margin-bottom: 1.75rem; }
    .res-section:last-child { margin-bottom: 0; }
    .res-section-title {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: var(--gray-400);
      margin-bottom: .6rem;
    }
    .res-description {
      font-size: .95rem;
      line-height: 1.7;
      color: var(--gray-700);
      white-space: pre-wrap;
    }
    .res-tag-row {
      display: flex;
      flex-wrap: wrap;
      gap: .4rem;
    }
    .res-tag {
      font-size: .78rem;
      font-weight: 600;
      padding: .25rem .65rem;
      border-radius: 100px;
      background: var(--gray-100);
      color: var(--gray-600);
    }
    .res-tag.grade { background: #e0f2fe; color: #0369a1; }
    .res-tag.subject { background: #f0fdf4; color: #166534; }
    .res-info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .75rem;
    }
    .res-info-item {
      background: var(--gray-100);
      border-radius: var(--radius-s);
      padding: .6rem .85rem;
    }
    .res-info-label {
      font-size: .7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--gray-400);
      margin-bottom: .2rem;
    }
    .res-info-value { font-size: .88rem; color: var(--text); }

    /* ── Sidebar ─────────────────────────────────────────────── */
    .res-sidebar { display: flex; flex-direction: column; gap: 1rem; }
    .res-side-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem;
    }
    .res-side-title {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: var(--gray-400);
      margin-bottom: .85rem;
    }
    .download-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      width: 100%;
      padding: .85rem;
      background: var(--primary);
      color: var(--white);
      border-radius: var(--radius-s);
      font-weight: 700;
      font-size: .95rem;
      text-decoration: none;
      transition: background .15s, transform .1s;
    }
    .download-btn:hover { background: var(--primary-dk); transform: translateY(-1px); }
    .download-btn svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .res-stat-row {
      display: flex;
      justify-content: space-between;
      font-size: .83rem;
      color: var(--gray-500);
      margin-top: .75rem;
    }
    .res-stat-row span { font-weight: 600; color: var(--text); }
    .res-stars-display {
      font-size: 1.25rem;
      color: var(--accent-gold, #f59e0b);
      letter-spacing: .05em;
    }
    .res-stars-display .empty { color: var(--gray-200); }

    /* ── Download progress toast ─────────────────────────────── */
    #dl-toast {
      position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(120%);
      background: #1e293b; color: #fff; border-radius: 12px;
      padding: .9rem 1.25rem; min-width: 280px; max-width: 420px; width: calc(100vw - 2rem);
      box-shadow: 0 8px 32px rgba(0,0,0,.35);
      transition: transform .3s cubic-bezier(.34,1.56,.64,1);
      z-index: 9999; pointer-events: none;
    }
    #dl-toast.visible { transform: translateX(-50%) translateY(0); }
    .dl-toast-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: .55rem; }
    .dl-toast-name { font-size: .82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .dl-toast-pct  { font-size: .82rem; font-weight: 700; color: #94a3b8; white-space: nowrap; flex-shrink: 0; }
    .dl-track {
      height: 5px; background: rgba(255,255,255,.15); border-radius: 100px; overflow: hidden;
    }
    .dl-fill {
      height: 100%; background: #22c55e; border-radius: 100px;
      width: 0%; transition: width .15s ease;
    }
    .dl-toast-status { font-size: .75rem; color: #94a3b8; margin-top: .4rem; }

    /* ── Rating form ─────────────────────────────────────────── */
    .star-picker {
      display: flex;
      gap: .2rem;
      margin-bottom: .85rem;
    }
    .star-picker input[type=radio] { display: none; }
    .star-picker label {
      font-size: 1.6rem;
      cursor: pointer;
      color: var(--gray-200);
      transition: color .1s;
      line-height: 1;
    }
    .star-picker:has(label:nth-child(2):hover) label:nth-child(1),
    .star-picker:has(label:nth-child(2):hover) label:nth-child(2) { color: #f59e0b; }
    /* Highlight logic — hover fills up to hovered star */
    .star-picker label:hover,
    .star-picker label:hover ~ label { color: var(--gray-200) !important; }
    .star-picker:hover label { color: #f59e0b; }
    .star-picker label:hover ~ label { color: var(--gray-200) !important; }
    /* Selected state */
    .star-picker input[type=radio]:checked ~ label { color: var(--gray-200); }
    .star-picker input[type=radio]:checked + label,
    .star-picker input[type=radio]:checked + label ~ label:not(:has(~ input:checked)):first-of-type { color: #f59e0b; }

    /* Simpler approach: reverse-order trick */
    .star-row {
      display: flex;
      flex-direction: row-reverse;
      justify-content: flex-end;
      gap: .15rem;
      margin-bottom: .85rem;
    }
    .star-row input { display: none; }
    .star-row label {
      font-size: 1.75rem;
      cursor: pointer;
      color: var(--gray-200);
      transition: color .12s;
      line-height: 1;
    }
    .star-row label:hover,
    .star-row label:hover ~ label { color: #f59e0b; }
    .star-row input:checked ~ label { color: #f59e0b; }

    .rating-comment {
      width: 100%;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-s);
      padding: .6rem .75rem;
      font-size: .88rem;
      font-family: inherit;
      color: var(--text);
      resize: vertical;
      min-height: 70px;
      margin-bottom: .75rem;
      box-sizing: border-box;
    }
    .rating-comment:focus { outline: none; border-color: var(--primary); }

    /* ── Ratings list ────────────────────────────────────────── */
    .ratings-list { display: flex; flex-direction: column; gap: 1rem; }
    .rating-item {
      background: var(--gray-50, #f9fafb);
      border: 1px solid var(--border);
      border-radius: var(--radius-s);
      padding: .85rem 1rem;
    }
    .rating-item-header {
      display: flex;
      align-items: center;
      gap: .5rem;
      margin-bottom: .35rem;
      flex-wrap: wrap;
    }
    .rating-item-name { font-weight: 600; font-size: .88rem; }
    .rating-item-stars { color: #f59e0b; font-size: .95rem; letter-spacing: .04em; }
    .rating-item-stars .e { color: var(--gray-200); }
    .rating-item-date { font-size: .75rem; color: var(--gray-400); margin-left: auto; }
    .rating-item-comment { font-size: .88rem; color: var(--gray-600); line-height: 1.6; }

    /* ── Flag modal ──────────────────────────────────────────── */
    .flag-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.45);
      z-index: 200;
      align-items: center;
      justify-content: center;
    }
    .flag-modal-overlay.open { display: flex; }
    .flag-modal {
      background: var(--white);
      border-radius: var(--radius);
      padding: 1.75rem;
      max-width: 420px;
      width: calc(100% - 2rem);
      box-shadow: 0 8px 32px rgba(0,0,0,.18);
    }
    .flag-modal h3 { margin: 0 0 .5rem; font-size: 1rem; }
    .flag-modal p { font-size: .88rem; color: var(--gray-500); margin-bottom: 1rem; }
    .flag-modal textarea {
      width: 100%;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-s);
      padding: .6rem .75rem;
      font-size: .88rem;
      font-family: inherit;
      resize: vertical;
      min-height: 80px;
      margin-bottom: .85rem;
      box-sizing: border-box;
    }
    .flag-modal textarea:focus { outline: none; border-color: var(--primary); }
    .flag-modal-footer { display: flex; justify-content: flex-end; gap: .6rem; }

    /* ── Admin bar ───────────────────────────────────────────── */
    .admin-bar {
      background: #fef3c7;
      border-bottom: 2px solid #fbbf24;
      padding: .6rem 0;
    }
    .admin-bar-inner {
      display: flex;
      align-items: center;
      gap: .75rem;
      flex-wrap: wrap;
    }
    .admin-bar-label { font-size: .78rem; font-weight: 700; color: #92400e; }
    .admin-bar a, .admin-bar button {
      font-size: .78rem;
      padding: .3rem .75rem;
      border-radius: var(--radius-s);
      font-weight: 600;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: opacity .15s;
    }
    .admin-bar a:hover, .admin-bar button:hover { opacity: .8; }
    .admin-btn-unpub { background: #f59e0b; color: #fff; }
    .admin-btn-pub   { background: #10b981; color: #fff; }
    .admin-btn-del   { background: #ef4444; color: #fff; }

    /* ── PDF preview ─────────────────────────────────────────── */
    @keyframes spin { to { transform: rotate(360deg); } }
    #pdf-canvas {
      max-width: 100%;
      border-radius: 3px;
      box-shadow: 0 2px 16px rgba(0,0,0,.45);
      display: block;
      margin: 0 auto;
      transition: opacity .15s;
    }
    #pdf-canvas.rendering { opacity: .5; }
    .pdf-nav-btn {
      display: flex; align-items: center; gap: .35rem;
      background: rgba(255,255,255,.12); color: rgba(255,255,255,.85);
      border: 1px solid rgba(255,255,255,.2); border-radius: 6px;
      padding: .45rem .9rem; font-size: .82rem; font-weight: 600;
      cursor: pointer; transition: background .15s;
    }
    .pdf-nav-btn:hover:not(:disabled) { background: rgba(255,255,255,.22); }
    .pdf-nav-btn:disabled { opacity: .3; cursor: default; }
    .pdf-nav-btn.locked {
      background: rgba(245,158,11,.15); border-color: rgba(245,158,11,.4);
      color: #fbbf24;
    }
    .pdf-nav-btn.locked:hover { background: rgba(245,158,11,.25); }

    /* ── Not found ───────────────────────────────────────────── */
    .not-found-wrap {
      text-align: center;
      padding: 5rem 1rem;
    }
    .not-found-wrap h2 { font-size: 1.5rem; margin-bottom: .5rem; }
    .not-found-wrap p { color: var(--gray-500); margin-bottom: 1.5rem; }

    /* ── Alerts ──────────────────────────────────────────────── */
    .alert-success {
      background: #f0fdf4;
      border: 1px solid #86efac;
      color: #166534;
      border-radius: var(--radius-s);
      padding: .75rem 1rem;
      font-size: .88rem;
      font-weight: 500;
      margin-bottom: 1rem;
    }
    .alert-error {
      background: #fef2f2;
      border: 1px solid #fca5a5;
      color: #991b1b;
      border-radius: var(--radius-s);
      padding: .75rem 1rem;
      font-size: .88rem;
      font-weight: 500;
      margin-bottom: 1rem;
    }
    .status-badge {
      display: inline-block;
      padding: .2rem .65rem;
      border-radius: 100px;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .status-unpublished { background: #fef3c7; color: #92400e; }
  </style>
</head>
<body>

  <?php if ($isAdmin && !isset($notFound)): ?>
  <div class="admin-bar">
    <div class="container admin-bar-inner">
      <span class="admin-bar-label">Admin</span>
      <?php if ($resource['status'] === 'published'): ?>
        <a href="library-resource.php?id=<?= $id ?>&action=unpublish" class="admin-btn-unpub"
           onclick="return confirm('Unpublish this resource?')">Unpublish</a>
      <?php else: ?>
        <a href="library-resource.php?id=<?= $id ?>&action=publish" class="admin-btn-pub">Re-publish</a>
      <?php endif; ?>
      <a href="library-resource.php?id=<?= $id ?>&action=delete" class="admin-btn-del"
         onclick="return confirm('Permanently delete this resource and file?')">Delete</a>
      <a href="members/library-admin.php" style="background:var(--gray-100);color:var(--text);">← Admin panel</a>
    </div>
  </div>
  <?php endif; ?>

  <header class="site-header">
    <div class="header-inner container">
      <a href="index.php" class="logo">
        <img src="bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <nav class="main-nav">
        <ul>
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Members</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li><a href="library.php" class="active">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="members/logout.php">Sign Out</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div class="page-hero" style="padding-top: calc(var(--hdr-h) + 1rem); padding-bottom: 1rem;">
    <!-- intentionally minimal — hero is in the card -->
  </div>

  <main class="page-content">
    <div class="container">

    <?php if (isset($notFound)): ?>
      <div class="not-found-wrap">
        <h2>Resource Not Found</h2>
        <p>This resource may have been removed or isn't available.</p>
        <a href="library.php" class="btn btn-primary">Browse the Library</a>
      </div>

    <?php else: ?>

      <div class="res-layout">

        <!-- ── Main column ──────────────────────────────────── -->
        <div>
          <div class="res-main-card">

            <!-- Hero -->
            <div class="res-hero">
              <a href="library.php" class="res-hero-back">
                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Back to Library
              </a>
              <?php if ($resource['status'] !== 'published'): ?>
                <div style="margin-bottom:.5rem;">
                  <span class="status-badge status-unpublished">Unpublished</span>
                </div>
              <?php endif; ?>
              <div class="res-type-badge"><?= htmlspecialchars($resource['resource_type']) ?></div>
              <h1><?= htmlspecialchars($resource['title']) ?></h1>
              <div class="res-meta-row">
                <span>
                  <?php if ($resource['anonymous']): ?>
                    Anonymous Member
                  <?php else: ?>
                    <a href="library.php?uploader=<?= urlencode($resource['uploader_email']) ?>"
                       style="color:rgba(255,255,255,.9);font-weight:600;text-decoration:none;"
                       onmouseover="this.style.textDecoration='underline'"
                       onmouseout="this.style.textDecoration='none'">
                      <?= htmlspecialchars($resource['uploader_name']) ?>
                    </a>
                  <?php endif; ?>
                </span>
                <span class="sep">·</span>
                <span><?= date('M j, Y', strtotime($resource['created_at'])) ?></span>
                <?php if ($resource['rating_count'] > 0): ?>
                  <span class="sep">·</span>
                  <span><?= number_format($resource['avg_rating'], 1) ?> ★ (<?= $resource['rating_count'] ?> rating<?= $resource['rating_count'] !== 1 ? 's' : '' ?>)</span>
                <?php endif; ?>
                <span class="sep">·</span>
                <span><?= $resource['download_count'] ?> download<?= $resource['download_count'] !== 1 ? 's' : '' ?></span>
              </div>
            </div>

            <!-- PDF Preview -->
            <?php if (!isset($notFound) && $resource['file_ext'] === 'pdf'): ?>
            <div id="pdf-preview-wrap" style="background:#525659;border-bottom:1.5px solid var(--border);">

              <!-- Header bar -->
              <div style="display:flex;align-items:center;justify-content:space-between;padding:.6rem 1rem;background:rgba(0,0,0,.25);flex-wrap:wrap;gap:.5rem;">
                <span id="pdf-page-info" style="font-size:.75rem;color:rgba(255,255,255,.7);font-weight:500;">Loading preview…</span>
                <div style="display:flex;align-items:center;gap:.6rem;">
                  <?php if ($isOwner || $isAdmin): ?>
                  <button id="use-preview-btn" onclick="captureCanvasAsThumb(false)"
                          style="display:none;font-size:.72rem;font-weight:600;color:rgba(255,255,255,.8);background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);border-radius:5px;padding:.2rem .65rem;cursor:pointer;transition:background .15s;"
                          onmouseover="this.style.background='rgba(255,255,255,.22)'"
                          onmouseout="this.style.background='rgba(255,255,255,.12)'">
                    🖼 Use as thumbnail
                  </button>
                  <?php endif; ?>
                  <span style="font-size:.72rem;color:rgba(255,255,255,.5);">PDF preview</span>
                </div>
              </div>

              <!-- Single page canvas -->
              <div style="padding:1rem 1.25rem;text-align:center;">
                <div id="pdf-loading" style="padding:3rem 1rem;color:rgba(255,255,255,.5);font-size:.88rem;">
                  <div style="font-size:1.5rem;margin-bottom:.5rem;animation:spin 1s linear infinite;display:inline-block;">⟳</div><br>
                  Loading preview…
                </div>
                <canvas id="pdf-canvas" style="display:none;"></canvas>
                <div id="pdf-error" style="display:none;padding:2rem 1rem;color:rgba(255,255,255,.5);font-size:.85rem;">
                  Preview unavailable — <a href="<?= $downloadUrl ?>" style="color:rgba(255,255,255,.8);font-weight:600;">download the file</a> to view it.
                </div>
              </div>

              <!-- Navigation bar -->
              <div id="pdf-nav" style="display:none;align-items:center;justify-content:center;gap:.75rem;padding:.65rem 1rem .85rem;flex-wrap:wrap;">
                <button id="pdf-prev" class="pdf-nav-btn" disabled>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                  Previous
                </button>
                <span id="pdf-nav-label" style="font-size:.78rem;color:rgba(255,255,255,.6);min-width:120px;text-align:center;"></span>
                <button id="pdf-next" class="pdf-nav-btn">
                  Next
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
              </div>

              <!-- Download CTA — shown when user hits the preview limit -->
              <div id="pdf-locked" style="display:none;text-align:center;padding:.25rem 1rem .9rem;">
                <div style="background:rgba(0,0,0,.25);border-radius:8px;padding:.85rem 1.25rem;display:inline-block;max-width:380px;">
                  <p id="pdf-locked-msg" style="font-size:.83rem;color:rgba(255,255,255,.75);margin:0 0 .65rem;"></p>
                  <?php if ($loggedIn): ?>
                  <a href="<?= $downloadUrl ?>" class="download-btn"
                     data-download data-filename="<?= htmlspecialchars($resource['file_name']) ?>"
                     style="display:inline-flex;width:auto;padding:.5rem 1.25rem;font-size:.85rem;">
                    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download full PDF
                  </a>
                  <?php else: ?>
                  <a href="<?= $loginUrl ?>" style="display:inline-block;background:var(--primary);color:#fff;font-weight:700;font-size:.85rem;padding:.5rem 1.25rem;border-radius:var(--radius-s);text-decoration:none;">
                    Log in to download
                  </a>
                  <?php endif; ?>
                </div>
              </div>

            </div>
            <?php elseif (!isset($notFound) && in_array($resource['file_ext'], ['docx','pptx'])): ?>
            <div style="background:#f3f4f6;border-bottom:1.5px solid var(--border);padding:2rem;text-align:center;">
              <?php $icon = $resource['file_ext'] === 'pptx' ? '🟧' : '🟦'; ?>
              <div style="font-size:2.5rem;margin-bottom:.5rem;"><?= $icon ?></div>
              <div style="font-size:.88rem;font-weight:700;color:var(--text);margin-bottom:.25rem;"><?= strtoupper($resource['file_ext']) ?> Document</div>
              <div style="font-size:.78rem;color:var(--gray-400);margin-bottom:.85rem;">Preview not available for this file type</div>
              <a href="<?= $downloadUrl ?>" class="download-btn" style="display:inline-flex;width:auto;padding:.55rem 1.25rem;font-size:.85rem;"
                 data-download data-filename="<?= htmlspecialchars($resource['file_name']) ?>">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download to view
              </a>
            </div>
            <?php endif; ?>

            <!-- Body -->
            <div class="res-body">

              <!-- Description -->
              <div class="res-section">
                <div class="res-section-title">Description</div>
                <div class="res-description"><?= htmlspecialchars($resource['description']) ?></div>
              </div>

              <!-- Tags -->
              <div class="res-section">
                <div class="res-section-title">Grade Levels &amp; Subject</div>
                <div class="res-tag-row">
                  <?php foreach ($grades as $g): ?>
                    <span class="res-tag grade">Grade <?= htmlspecialchars($g) ?></span>
                  <?php endforeach; ?>
                  <?php if ($resource['subject']): ?>
                    <span class="res-tag subject"><?= htmlspecialchars($resource['subject']) ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Keywords/tags -->
              <?php if ($tags): ?>
              <div class="res-section">
                <div class="res-section-title">Tags</div>
                <div class="res-tag-row">
                  <?php foreach ($tags as $tag): ?>
                    <a href="library.php?tag=<?= urlencode($tag) ?>"
                       style="font-size:.78rem;font-weight:600;padding:.25rem .65rem;border-radius:100px;background:#e0f2fe;color:#0369a1;text-decoration:none;transition:background .12s;"
                       onmouseover="this.style.background='#bae6fd'"
                       onmouseout="this.style.background='#e0f2fe'"><?= htmlspecialchars($tag) ?></a>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endif; ?>

              <!-- Optional fields -->
              <?php $hasOptional = $resource['bc_curriculum'] || $resource['time_required'] || $resource['materials']; ?>
              <?php if ($hasOptional): ?>
              <div class="res-section">
                <div class="res-section-title">Additional Details</div>
                <div class="res-info-grid">
                  <?php if ($resource['time_required']): ?>
                  <div class="res-info-item">
                    <div class="res-info-label">Time Required</div>
                    <div class="res-info-value"><?= htmlspecialchars($resource['time_required']) ?></div>
                  </div>
                  <?php endif; ?>
                  <?php if ($resource['bc_curriculum']): ?>
                  <div class="res-info-item" style="grid-column: 1 / -1;">
                    <div class="res-info-label">BC Curriculum Connection</div>
                    <div class="res-info-value"><?= nl2br(htmlspecialchars($resource['bc_curriculum'])) ?></div>
                  </div>
                  <?php endif; ?>
                  <?php if ($resource['materials']): ?>
                  <div class="res-info-item" style="grid-column: 1 / -1;">
                    <div class="res-info-label">Materials Needed</div>
                    <div class="res-info-value"><?= nl2br(htmlspecialchars($resource['materials'])) ?></div>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

            </div><!-- /res-body -->
          </div><!-- /res-main-card -->

          <!-- ── Ratings section ────────────────────────────── -->
          <div style="margin-top: 1.5rem;">

            <?php if ($ratingSuccess): ?>
              <div class="alert-success">Your rating has been submitted — thank you!</div>
            <?php endif; ?>

            <?php if ($ratingError): ?>
              <div class="alert-error"><?= htmlspecialchars($ratingError) ?></div>
            <?php endif; ?>

            <?php if ($flagDone): ?>
              <div class="alert-success">Thank you — this resource has been flagged for admin review.</div>
            <?php endif; ?>

            <!-- Rate this resource -->
            <div class="res-main-card" style="margin-bottom: 1rem;">
              <div class="res-body">
                <?php if (!$loggedIn): ?>
                  <div class="res-section-title">Rate This Resource</div>
                  <p style="font-size:.88rem;color:var(--gray-500);margin:0 0 .75rem;">
                    <a href="<?= $loginUrl ?>" style="color:var(--primary);font-weight:600;">Log in</a>
                    to rate and review this resource.
                  </p>
                <?php elseif ($myRating && !$ratingSuccess): ?>
                  <div class="res-section-title">Your Rating</div>
                  <p style="font-size:.88rem;color:var(--gray-600);margin:0 0 .75rem;">
                    You rated this
                    <?= str_repeat('★', $myRating['rating']) ?><?= str_repeat('☆', 5-$myRating['rating']) ?>
                    <?php if ($myRating['comment']): ?>
                      — "<?= htmlspecialchars($myRating['comment']) ?>"
                    <?php endif; ?>
                  </p>
                  <details style="font-size:.83rem;">
                    <summary style="cursor:pointer;color:var(--primary);font-weight:600;">Update your rating</summary>
                    <div style="padding-top:.85rem;">
                      <?= renderRatingForm($id, $myRating) ?>
                    </div>
                  </details>
                <?php else: ?>
                  <div class="res-section-title">Rate This Resource</div>
                  <?= renderRatingForm($id, null) ?>
                <?php endif; ?>
              </div>
            </div>

            <!-- Ratings list -->
            <?php if ($ratings): ?>
            <div class="res-main-card">
              <div class="res-body">
                <div class="res-section-title"><?= count($ratings) ?> Rating<?= count($ratings) !== 1 ? 's' : '' ?></div>
                <div class="ratings-list">
                  <?php foreach ($ratings as $r): ?>
                    <div class="rating-item">
                      <div class="rating-item-header">
                        <span class="rating-item-name"><?= htmlspecialchars($r['rater_name']) ?></span>
                        <span class="rating-item-stars">
                          <?= str_repeat('★', (int)$r['rating']) ?><span class="e"><?= str_repeat('★', 5 - (int)$r['rating']) ?></span>
                        </span>
                        <span class="rating-item-date"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                      </div>
                      <?php if ($r['comment']): ?>
                        <div class="rating-item-comment"><?= htmlspecialchars($r['comment']) ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

          </div><!-- /ratings section -->
        </div><!-- /main column -->

        <!-- ── Sidebar ───────────────────────────────────────── -->
        <div class="res-sidebar">

          <!-- Download card -->
          <div class="res-side-card">
            <div class="res-side-title">
              Download<?= count($extraFiles) ? ' (' . (1 + count($extraFiles)) . ' files)' : '' ?>
            </div>

            <?php
              function resFileIcon(string $ext): string {
                $e = strtolower($ext);
                if ($e === 'pdf')  return '📕';
                if ($e === 'docx') return '📘';
                if ($e === 'pptx') return '📙';
                return '📄';
              }
            ?>

            <?php if (!$loggedIn): ?>
            <!-- Guest: prompt to log in -->
            <a href="<?= $loginUrl ?>" class="download-btn" style="background:var(--accent);color:var(--primary);border:1.5px solid var(--border);">
              <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Log in to download
            </a>
            <?php else: ?>
            <!-- Primary file -->
            <a href="<?= $downloadUrl ?>" class="download-btn"
               data-download data-filename="<?= htmlspecialchars($resource['file_name']) ?>">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              <?php if (count($extraFiles)): ?>
                <span style="flex:1;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= resFileIcon($resource['file_ext']) ?>
                  <?= htmlspecialchars($resource['file_name']) ?>
                  <span style="font-size:.72rem;font-weight:400;opacity:.7;"><?= libFormatSize($resource['file_size']) ?></span>
                </span>
              <?php else: ?>
                Download <?= strtoupper($resource['file_ext']) ?>
              <?php endif; ?>
            </a>

            <!-- Additional files -->
            <?php foreach ($extraFiles as $ef): ?>
              <a href="members/library-serve.php?id=<?= $id ?>&file=<?= $ef['id'] ?>"
                 class="download-btn" style="margin-top:.4rem;background:var(--accent);color:var(--primary);border:1.5px solid var(--border);"
                 data-download data-filename="<?= htmlspecialchars($ef['file_name']) ?>">
                <svg viewBox="0 0 24 24" stroke="var(--primary)" fill="none"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <span style="flex:1;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= resFileIcon($ef['file_ext']) ?>
                  <?= htmlspecialchars($ef['file_name']) ?>
                  <span style="font-size:.72rem;font-weight:400;opacity:.7;"><?= libFormatSize($ef['file_size']) ?></span>
                </span>
              </a>
            <?php endforeach; ?>

            <?php endif; // end logged-in download block ?>

            <!-- Bookmark toggle — members only -->
            <?php if ($loggedIn): ?>
            <button id="bm-btn" data-id="<?= $id ?>" data-state="<?= $bookmarked ? '1' : '0' ?>"
                    style="display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;margin-top:.6rem;padding:.55rem;background:none;border:1.5px solid var(--border);border-radius:var(--radius-s);font-size:.83rem;font-weight:600;color:var(--gray-600);cursor:pointer;transition:border-color .15s,color .15s;">
              <svg id="bm-icon" width="15" height="15" viewBox="0 0 24 24"
                   fill="<?= $bookmarked ? '#f59e0b' : 'none' ?>"
                   stroke="<?= $bookmarked ? '#f59e0b' : 'currentColor' ?>"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
              <span id="bm-label"><?= $bookmarked ? 'Saved' : 'Save for later' ?></span>
            </button>
            <?php endif; ?>

            <?php if ($isOwner || $isAdmin): ?>
            <a href="library-edit.php?id=<?= $id ?>"
               style="display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;margin-top:.5rem;padding:.55rem;background:none;border:1.5px solid var(--border);border-radius:var(--radius-s);font-size:.83rem;font-weight:600;color:var(--gray-600);text-decoration:none;transition:border-color .15s,color .15s;"
               onmouseover="this.style.borderColor='var(--primary)';this.style.color='var(--primary)'"
               onmouseout="this.style.borderColor='';this.style.color=''">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
              Edit this resource
            </a>

            <?php if ($isOwner): ?>
            <form method="POST" style="width:100%;margin-top:.4rem;"
                  onsubmit="return confirm('Permanently delete this resource and all its files? This cannot be undone.')">
              <input type="hidden" name="action" value="owner_delete">
              <button type="submit"
                      style="display:flex;align-items:center;justify-content:center;gap:.4rem;width:100%;padding:.55rem;background:none;border:1.5px solid var(--border);border-radius:var(--radius-s);font-size:.83rem;font-weight:600;color:var(--gray-600);cursor:pointer;transition:border-color .15s,color .15s;"
                      onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'"
                      onmouseout="this.style.borderColor='';this.style.color=''">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                  <path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                </svg>
                Remove this resource
              </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            <div class="res-stat-row">
              <div>Size</div>
              <span><?php
                $totalSize = $resource['file_size'];
                foreach ($extraFiles as $ef) $totalSize += $ef['file_size'];
                echo count($extraFiles)
                  ? libFormatSize($totalSize) . ' total'
                  : libFormatSize($resource['file_size']);
              ?></span>
            </div>
            <div class="res-stat-row">
              <div>Downloads</div>
              <span><?= $resource['download_count'] ?></span>
            </div>
            <?php if ($resource['rating_count'] > 0): ?>
            <div class="res-stat-row">
              <div>Rating</div>
              <span><?= number_format($resource['avg_rating'], 1) ?> / 5</span>
            </div>
            <?php endif; ?>
          </div>

          <!-- Resource info card -->
          <div class="res-side-card">
            <div class="side-thumb-wrap" style="<?= empty($resource['thumbnail_path']) ? 'display:none;' : '' ?>margin:-1.25rem -1.25rem .95rem;border-radius:calc(var(--radius) - 1.5px) calc(var(--radius) - 1.5px) 0 0;overflow:hidden;height:140px;">
              <img id="side-thumb-img"
                   src="<?= !empty($resource['thumbnail_path']) ? htmlspecialchars(LIB_THUMB_URL . basename($resource['thumbnail_path'])) : '' ?>"
                   alt="" style="width:100%;height:100%;object-fit:cover;display:block;">
            </div>
            <div class="res-side-title">Details</div>
            <div style="display:flex;flex-direction:column;gap:.55rem;">
              <div class="res-stat-row" style="margin-top:0;">
                <div>Type</div>
                <span><?= htmlspecialchars($resource['resource_type']) ?></span>
              </div>
              <div class="res-stat-row" style="margin-top:0;">
                <div>Subject</div>
                <span><?= htmlspecialchars($resource['subject']) ?></span>
              </div>
              <div class="res-stat-row" style="margin-top:0;">
                <div>Grade<?= count($grades) !== 1 ? 's' : '' ?></div>
                <span><?= implode(', ', array_map('htmlspecialchars', $grades)) ?></span>
              </div>
              <div class="res-stat-row" style="margin-top:0;">
                <div>Uploaded</div>
                <span><?= date('M Y', strtotime($resource['created_at'])) ?></span>
              </div>
            </div>
          </div>

          <!-- Flag card -->
          <div class="res-side-card" style="border-color: var(--gray-200);">
            <div class="res-side-title">Issue?</div>
            <p style="font-size:.82rem;color:var(--gray-500);margin:0 0 .75rem;line-height:1.5;">
              If this resource contains an error or inappropriate content, let us know.
            </p>
            <button onclick="document.getElementById('flagModal').classList.add('open')"
                    style="background:none;border:1.5px solid var(--border);border-radius:var(--radius-s);padding:.45rem .85rem;font-size:.82rem;font-weight:600;color:var(--gray-600);cursor:pointer;width:100%;transition:border-color .15s,color .15s;"
                    onmouseover="this.style.borderColor='#ef4444';this.style.color='#ef4444';"
                    onmouseout="this.style.borderColor='';this.style.color='';">
              Flag this resource
            </button>
          </div>

          <!-- Upload another -->
          <div class="res-side-card" style="background: linear-gradient(140deg, var(--primary-dk), var(--primary)); border: none;">
            <div style="color:rgba(255,255,255,.7);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.5rem;">Share Your Work</div>
            <p style="color:rgba(255,255,255,.8);font-size:.83rem;margin:0 0 .85rem;line-height:1.5;">Have a great lesson or resource? Share it with your colleagues.</p>
            <a href="library-upload.php" style="display:block;text-align:center;background:rgba(255,255,255,.2);color:#fff;border-radius:var(--radius-s);padding:.55rem;font-size:.85rem;font-weight:700;text-decoration:none;transition:background .15s;"
               onmouseover="this.style.background='rgba(255,255,255,.3)'"
               onmouseout="this.style.background='rgba(255,255,255,.2)'">
              Upload a Resource
            </a>
          </div>

        </div><!-- /sidebar -->
      </div><!-- /res-layout -->

    <?php endif; ?>

    </div><!-- /container -->
  </main>

  <!-- Download progress toast -->
  <div id="dl-toast" role="status" aria-live="polite">
    <div class="dl-toast-row">
      <span class="dl-toast-name" id="dl-toast-name">Preparing download…</span>
      <span class="dl-toast-pct"  id="dl-toast-pct">0%</span>
    </div>
    <div class="dl-track"><div class="dl-fill" id="dl-fill"></div></div>
    <div class="dl-toast-status" id="dl-toast-status">Starting…</div>
  </div>

  <!-- Flag modal -->
  <?php if (!isset($notFound)): ?>
  <div class="flag-modal-overlay" id="flagModal">
    <div class="flag-modal">
      <h3>Flag This Resource</h3>
      <p>Describe the issue — the admin team will review it.</p>
      <form method="POST">
        <input type="hidden" name="lib_flag" value="1">
        <textarea name="flag_reason" placeholder="e.g. Contains incorrect information, inappropriate content…"></textarea>
        <div class="flag-modal-footer">
          <button type="button" onclick="document.getElementById('flagModal').classList.remove('open')"
                  style="background:var(--gray-100);border:none;border-radius:var(--radius-s);padding:.5rem 1rem;cursor:pointer;font-size:.88rem;font-weight:600;">Cancel</button>
          <button type="submit"
                  style="background:#ef4444;color:#fff;border:none;border-radius:var(--radius-s);padding:.5rem 1rem;cursor:pointer;font-size:.88rem;font-weight:600;">Submit Flag</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <footer class="site-footer">
    <div class="footer-bottom" style="border-top: none;">
      <div class="container">
        <p style="padding: 1.5rem 0; color: rgba(255,255,255,.5);">© 2026 Bulkley Valley Teachers' Union</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>

  <?php if (!isset($notFound) && $resource['file_ext'] === 'pdf'): ?>
  <!-- PDF.js paginated preview -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const pdfUrl      = '<?= $previewUrl ?>';
    const MAX_PREVIEW = <?= max(1, min(10, (int)($resource['preview_pages'] ?? 3))) ?>;
    const canvas      = document.getElementById('pdf-canvas');
    const ctx         = canvas.getContext('2d');
    const loadingEl   = document.getElementById('pdf-loading');
    const errEl       = document.getElementById('pdf-error');
    const navEl       = document.getElementById('pdf-nav');
    const prevBtn     = document.getElementById('pdf-prev');
    const nextBtn     = document.getElementById('pdf-next');
    const navLabel    = document.getElementById('pdf-nav-label');
    const pageInfoEl  = document.getElementById('pdf-page-info');
    const lockedEl    = document.getElementById('pdf-locked');
    const lockedMsg   = document.getElementById('pdf-locked-msg');

    let pdfDoc      = null;
    let currentPage = 1;
    let totalPages  = 0;
    let rendering   = false;

    <?php if ($isOwner || $isAdmin): ?>
    const RESOURCE_ID   = <?= $id ?>;
    const HAS_THUMBNAIL = <?= empty($resource['thumbnail_path']) ? 'false' : 'true' ?>;

    function captureCanvasAsThumb(silent) {
      try {
        const dataUrl = canvas.toDataURL('image/jpeg', 0.88);
        const fd = new FormData();
        fd.append('id',    RESOURCE_ID);
        fd.append('image', dataUrl);
        fetch('library-thumb-capture.php', { method:'POST', credentials:'same-origin', body:fd })
          .then(r => r.json())
          .then(data => {
            if (data.ok) {
              const sideThumb = document.getElementById('side-thumb-img');
              if (sideThumb) {
                sideThumb.src = data.path + '?t=' + Date.now();
                sideThumb.closest('.side-thumb-wrap').style.display = 'block';
              }
              if (!silent) {
                const btn = document.getElementById('use-preview-btn');
                if (btn) { btn.textContent = '✓ Saved'; btn.disabled = true; }
              }
            }
          }).catch(() => {});
      } catch(e) {}
    }
    <?php endif; ?>

    function renderPage(num) {
      if (rendering) return;
      rendering = true;
      canvas.classList.add('rendering');
      lockedEl.style.display = 'none';

      pdfDoc.getPage(num).then(page => {
        const wrap     = document.getElementById('pdf-preview-wrap');
        const maxWidth = Math.min(wrap.clientWidth - 40, 800);
        const vp0      = page.getViewport({ scale: 1 });
        const scale    = Math.min(1.8, maxWidth / vp0.width);
        const vp       = page.getViewport({ scale });

        canvas.width  = vp.width;
        canvas.height = vp.height;

        return page.render({ canvasContext: ctx, viewport: vp }).promise;
      }).then(() => {
        rendering = false;
        canvas.classList.remove('rendering');
        updateUI();

        // First page ready
        if (num === 1) {
          loadingEl.style.display = 'none';
          canvas.style.display    = 'block';
          navEl.style.display     = totalPages > 1 ? 'flex' : 'none';
          <?php if ($isOwner || $isAdmin): ?>
          document.getElementById('use-preview-btn').style.display = 'inline-flex';
          if (!HAS_THUMBNAIL) captureCanvasAsThumb(true);
          <?php endif; ?>
        }
      }).catch(() => {
        rendering = false;
        loadingEl.style.display = 'none';
        errEl.style.display     = 'block';
      });
    }

    function updateUI() {
      const previewLimit = Math.min(MAX_PREVIEW, totalPages);
      const atLimit      = currentPage >= previewLimit;
      const hasMore      = totalPages > MAX_PREVIEW;

      // Header info
      pageInfoEl.textContent = hasMore
        ? 'Preview — page ' + currentPage + ' of ' + previewLimit + ' (' + totalPages + ' total)'
        : 'Page ' + currentPage + ' of ' + totalPages;

      // Nav label
      navLabel.textContent = 'Page ' + currentPage + ' of ' + previewLimit +
        (hasMore ? ' preview' : '');

      // Prev button
      prevBtn.disabled = currentPage <= 1;

      // Next button — locked style when at preview limit with more pages
      if (atLimit && hasMore) {
        nextBtn.classList.add('locked');
        nextBtn.innerHTML =
          'More <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
      } else {
        nextBtn.classList.remove('locked');
        nextBtn.innerHTML =
          'Next <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>';
        nextBtn.disabled = currentPage >= previewLimit;
      }

      // Show download CTA when at limit
      if (atLimit && hasMore) {
        const remaining = totalPages - previewLimit;
        lockedMsg.textContent = 'This document has ' + remaining + ' more page' +
          (remaining > 1 ? 's' : '') + ' — download to read the full resource.';
        lockedEl.style.display = 'block';
      }
    }

    prevBtn.addEventListener('click', () => {
      if (currentPage > 1) { currentPage--; renderPage(currentPage); }
    });

    nextBtn.addEventListener('click', () => {
      const previewLimit = Math.min(MAX_PREVIEW, totalPages);
      if (currentPage < previewLimit) {
        currentPage++;
        renderPage(currentPage);
      }
      // At limit: CTA is already visible via updateUI — button does nothing more
    });

    pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
      pdfDoc     = pdf;
      totalPages = pdf.numPages;
      renderPage(1);
    }).catch(() => {
      loadingEl.style.display = 'none';
      errEl.style.display     = 'block';
    });
  </script>
  <?php endif; ?>

  <script>
    // Close flag modal on overlay click
    document.getElementById('flagModal')?.addEventListener('click', function(e) {
      if (e.target === this) this.classList.remove('open');
    });

    // ── Download progress bar ─────────────────────────────────
    (function() {
      const toast      = document.getElementById('dl-toast');
      const toastName  = document.getElementById('dl-toast-name');
      const toastPct   = document.getElementById('dl-toast-pct');
      const fill       = document.getElementById('dl-fill');
      const statusEl   = document.getElementById('dl-toast-status');
      let hideTimer    = null;

      function showToast(filename) {
        clearTimeout(hideTimer);
        toastName.textContent  = filename;
        toastPct.textContent   = '0%';
        fill.style.width       = '0%';
        statusEl.textContent   = 'Starting…';
        toast.classList.add('visible');
      }
      function setProgress(pct) {
        const p = Math.round(Math.min(100, Math.max(0, pct)));
        fill.style.width     = p + '%';
        toastPct.textContent = p + '%';
        statusEl.textContent = p < 100 ? 'Downloading…' : 'Finishing…';
      }
      function doneToast() {
        fill.style.width       = '100%';
        toastPct.textContent   = '100%';
        statusEl.textContent   = '✓ Download complete';
        hideTimer = setTimeout(() => toast.classList.remove('visible'), 2500);
      }
      function errorToast() {
        statusEl.textContent = '✗ Download failed — please try again';
        fill.style.background = '#ef4444';
        hideTimer = setTimeout(() => {
          toast.classList.remove('visible');
          fill.style.background = '';
        }, 3500);
      }

      document.querySelectorAll('a[data-download]').forEach(link => {
        link.addEventListener('click', async function(e) {
          e.preventDefault();
          const url      = this.href;
          const filename = this.dataset.filename || 'file';
          showToast(filename);

          try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('HTTP ' + response.status);

            const total  = parseInt(response.headers.get('Content-Length') || '0', 10);
            const reader = response.body.getReader();
            const chunks = [];
            let received = 0;

            while (true) {
              const { done, value } = await reader.read();
              if (done) break;
              chunks.push(value);
              received += value.length;
              setProgress(total ? received / total * 100 : -1);
            }

            // If no Content-Length, animate indeterminate then snap to 100
            if (!total) setProgress(100);

            const blob   = new Blob(chunks);
            const dlUrl  = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href     = dlUrl;
            anchor.download = filename;
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
            setTimeout(() => URL.revokeObjectURL(dlUrl), 10000);

            doneToast();
          } catch (err) {
            errorToast();
          }
        });
      });
    })();

    // Bookmark toggle
    const bmBtn = document.getElementById('bm-btn');
    if (bmBtn) {
      bmBtn.addEventListener('click', function() {
        fetch('library-bookmark.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id=' + this.dataset.id,
        })
        .then(r => r.json())
        .then(data => {
          const icon  = document.getElementById('bm-icon');
          const label = document.getElementById('bm-label');
          const c     = data.bookmarked ? '#f59e0b' : 'currentColor';
          icon.setAttribute('fill',   data.bookmarked ? '#f59e0b' : 'none');
          icon.setAttribute('stroke', c);
          label.textContent = data.bookmarked ? 'Saved' : 'Save for later';
          bmBtn.style.borderColor = data.bookmarked ? '#f59e0b' : '';
          bmBtn.style.color       = data.bookmarked ? '#f59e0b' : '';
        });
      });
    }
  </script>
</body>
</html>
<?php
// ── Helper: render rating form ───────────────────────────────────────────────
function renderRatingForm(int $id, ?array $existing): string {
    $existingRating  = $existing['rating']  ?? 0;
    $existingComment = $existing['comment'] ?? '';
    ob_start();
    ?>
    <form method="POST">
      <input type="hidden" name="lib_rate" value="1">
      <div class="star-row">
        <?php for ($s = 5; $s >= 1; $s--): ?>
          <input type="radio" name="rating" id="star<?= $s ?>_<?= $id ?>" value="<?= $s ?>"
                 <?= $existingRating === $s ? 'checked' : '' ?>>
          <label for="star<?= $s ?>_<?= $id ?>" title="<?= $s ?> star<?= $s > 1 ? 's' : '' ?>">★</label>
        <?php endfor; ?>
      </div>
      <textarea name="comment" class="rating-comment"
                placeholder="Leave an optional comment…"><?= htmlspecialchars($existingComment) ?></textarea>
      <button type="submit" class="btn btn-primary" style="font-size:.88rem;padding:.5rem 1.25rem;">
        <?= $existing ? 'Update Rating' : 'Submit Rating' ?>
      </button>
    </form>
    <?php
    return ob_get_clean();
}
?>
