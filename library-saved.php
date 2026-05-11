<?php
require_once __DIR__ . '/members/auth.php';
if (!isLoggedIn()) {
    header('Location: members/login.php?redirect=../library-saved.php');
    exit;
}
require_once __DIR__ . '/members/library-db.php';

$member    = getMember();
$bookmarks = libGetBookmarks($member['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title>Saved Resources — BVTU Library</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .saved-hero {
      background: linear-gradient(140deg, var(--primary-dk) 0%, var(--primary) 100%);
      color: var(--white);
      padding: calc(var(--hdr-h) + 1.5rem) 0 1.5rem;
    }
    .saved-hero h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: .25rem; }
    .saved-hero p  { opacity: .75; font-size: .9rem; }

    /* ── Resource grid (shared style with library.php) ───────── */
    .lib-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1.1rem;
    }
    .lib-card {
      background: var(--white);
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: box-shadow .2s, transform .2s;
      position: relative;
    }
    .lib-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.1); transform: translateY(-2px); }
    .lib-card-body  { padding: 1rem 1.1rem; flex: 1; display: flex; flex-direction: column; }
    .lib-card-type  { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--primary); margin-bottom: .35rem; }
    .lib-card-title { font-size: .97rem; font-weight: 700; color: var(--text); margin-bottom: .4rem; line-height: 1.35; }
    .lib-card-title a { color: inherit; text-decoration: none; }
    .lib-card-title a:hover { color: var(--primary); }
    .lib-card-desc  { font-size: .82rem; color: var(--gray-500); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; flex: 1; margin-bottom: .65rem; }
    .lib-card-tags  { display: flex; flex-wrap: wrap; gap: .3rem; margin-bottom: .6rem; }
    .lib-card-tag   { font-size: .7rem; background: #e0f2fe; color: #0369a1; padding: .15rem .5rem; border-radius: 100px; font-weight: 600; }
    .lib-card-footer { display: flex; align-items: center; justify-content: space-between; padding: .6rem 1.1rem; border-top: 1px solid var(--border); font-size: .75rem; color: var(--gray-400); gap: .5rem; }
    .lib-card-footer-left { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; min-width: 0; }
    .lib-grade { font-size: .68rem; font-weight: 700; background: #f0fdf4; color: #166534; padding: .18rem .45rem; border-radius: 100px; }
    .lib-subject { font-size: .68rem; font-weight: 700; background: var(--gray-100); color: var(--gray-500); padding: .18rem .45rem; border-radius: 100px; }
    .lib-stars { color: #f59e0b; font-size: .8rem; }
    .lib-card-uploader { font-size: .73rem; color: var(--gray-400); margin-bottom: .4rem; }
    .lib-card-uploader a { color: var(--primary); text-decoration: none; font-weight: 600; }
    .lib-card-uploader a:hover { text-decoration: underline; }

    /* Bookmark button on card */
    .bm-btn {
      position: absolute;
      top: .6rem;
      right: .6rem;
      background: none;
      border: none;
      cursor: pointer;
      padding: .3rem;
      border-radius: 50%;
      transition: background .15s;
      line-height: 1;
    }
    .bm-btn:hover { background: rgba(0,0,0,.06); }
    .bm-btn svg { width: 18px; height: 18px; }

    .empty-state {
      text-align: center;
      padding: 5rem 1rem;
      color: var(--gray-400);
    }
    .empty-state h2 { font-size: 1.2rem; margin-bottom: .5rem; color: var(--text); }
    .empty-state p  { margin-bottom: 1.5rem; font-size: .9rem; }
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
      <nav class="main-nav">
        <ul>
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Members</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="library.php" class="active">Resource Library</a></li>
          <li><a href="members/logout.php">Sign Out</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div class="saved-hero">
    <div class="container">
      <h1>Saved Resources</h1>
      <p><?= count($bookmarks) ?> saved resource<?= count($bookmarks) !== 1 ? 's' : '' ?></p>
    </div>
  </div>

  <main class="page-content">
    <div class="container">

      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;flex-wrap:wrap;">
        <a href="library.php" style="font-size:.85rem;color:var(--primary);font-weight:600;text-decoration:none;">← Browse all resources</a>
        <a href="library-upload.php" style="margin-left:auto;font-size:.83rem;" class="btn btn-primary">Upload a Resource</a>
      </div>

      <?php if (!$bookmarks): ?>
        <div class="empty-state">
          <h2>No saved resources yet</h2>
          <p>Tap the bookmark icon on any resource to save it here for later.</p>
          <a href="library.php" class="btn btn-primary">Browse the Library</a>
        </div>
      <?php else: ?>
        <div class="lib-grid">
          <?php foreach ($bookmarks as $r):
            $grades = $r['grade_levels'] ? explode(',', $r['grade_levels']) : [];
            $tags   = $r['tags'] ? array_slice(explode(',', $r['tags']), 0, 3) : [];
          ?>
          <div class="lib-card" data-id="<?= $r['id'] ?>">
            <button class="bm-btn bm-active" data-id="<?= $r['id'] ?>" title="Remove bookmark" aria-label="Remove bookmark">
              <svg viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
            </button>
            <div class="lib-card-body">
              <div class="lib-card-type"><?= htmlspecialchars($r['resource_type']) ?></div>
              <div class="lib-card-title">
                <a href="library-resource.php?id=<?= $r['id'] ?>"><?= htmlspecialchars($r['title']) ?></a>
              </div>
              <?php if (!$r['anonymous']): ?>
                <div class="lib-card-uploader">
                  by <a href="library.php?uploader=<?= urlencode($r['uploader_email']) ?>"><?= htmlspecialchars($r['uploader_name']) ?></a>
                </div>
              <?php endif; ?>
              <div class="lib-card-desc"><?= htmlspecialchars($r['description']) ?></div>
              <?php if ($tags): ?>
                <div class="lib-card-tags">
                  <?php foreach ($tags as $t): ?>
                    <a href="library.php?tag=<?= urlencode(trim($t)) ?>" class="lib-card-tag"><?= htmlspecialchars(trim($t)) ?></a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="lib-card-footer">
              <div class="lib-card-footer-left">
                <?php foreach (array_slice($grades, 0, 3) as $g): ?>
                  <span class="lib-grade">Gr.<?= htmlspecialchars($g) ?></span>
                <?php endforeach; ?>
                <?php if ($r['subject']): ?>
                  <span class="lib-subject"><?= htmlspecialchars($r['subject']) ?></span>
                <?php endif; ?>
              </div>
              <?php if ($r['rating_count'] > 0): ?>
                <span class="lib-stars" title="<?= number_format($r['avg_rating'], 1) ?> / 5">
                  <?= str_repeat('★', (int)round($r['avg_rating'])) ?><?= str_repeat('☆', 5 - (int)round($r['avg_rating'])) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-bottom" style="border-top: none;">
      <div class="container">
        <p style="padding: 1.5rem 0; color: rgba(255,255,255,.5);">© 2026 Bulkley Valley Teachers' Union</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script>
    // Bookmark toggle — removes card from view on unbookmark
    document.querySelectorAll('.bm-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const id   = this.dataset.id;
        const card = this.closest('.lib-card');
        fetch('library-bookmark.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id=' + id,
        })
        .then(r => r.json())
        .then(data => {
          if (!data.bookmarked) {
            card.style.transition = 'opacity .3s, transform .3s';
            card.style.opacity    = '0';
            card.style.transform  = 'scale(.95)';
            setTimeout(() => card.remove(), 310);
          }
        });
      });
    });
  </script>
</body>
</html>
