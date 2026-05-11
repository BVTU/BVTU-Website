<?php
require_once __DIR__ . '/members/auth.php';
if (!isLoggedIn()) {
    $here = urlencode('../newsletter.php?id=' . (int)($_GET['id'] ?? 0));
    header("Location: members/login.php?redirect={$here}");
    exit;
}
require_once __DIR__ . '/members/newsletter-db.php';

$id = (int)($_GET['id'] ?? 0);
$nl = $id ? nlGetNewsletter($id) : null;
if (!$nl) {
    http_response_code(404);
    header('Location: newsletter-archive.php');
    exit;
}

$sendDate = $nl['send_date']
    ? date('F j, Y', strtotime($nl['send_date']))
    : null;
$loggedIn = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title><?= htmlspecialchars($nl['subject']) ?> — BVTU Newsletter</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .nl-view-wrap { max-width: 880px; margin: 0 auto; }
    .nl-header-card {
      background: #fff; border: 1.5px solid var(--gray-200);
      border-radius: 12px; padding: 1.4rem 1.75rem;
      margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: .65rem;
    }
    .nl-back {
      font-size: .82rem; color: var(--gray-400); text-decoration: none;
      display: inline-flex; align-items: center; gap: .3rem; margin-bottom: .1rem;
      transition: color .15s;
    }
    .nl-back:hover { color: var(--primary); }
    .nl-view-subject {
      font-size: 1.35rem; font-weight: 800; color: var(--gray-800);
      line-height: 1.3; margin: 0;
    }
    .nl-view-meta {
      display: flex; gap: 1.25rem; flex-wrap: wrap;
      font-size: .82rem; color: var(--gray-400);
    }
    .nl-view-meta span { display: flex; align-items: center; gap: .3rem; }
    .nl-view-actions { display: flex; gap: .65rem; flex-wrap: wrap; }
    /* Newsletter iframe container */
    .nl-frame-wrap {
      background: #f3f4f6; border-radius: 12px; overflow: hidden;
      border: 1.5px solid var(--gray-200);
    }
    .nl-frame-wrap iframe {
      display: block; width: 100%; border: none;
      min-height: 600px;
    }
    @media (max-width: 600px) {
      .nl-header-card { padding: 1rem 1.1rem; }
      .nl-view-subject { font-size: 1.1rem; }
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
          <li><a href="library.php">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="newsletter-archive.php" class="active">Newsletters</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="members/dashboard.php" class="btn btn-primary" style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;background:#1a6b35;border-color:#1a6b35;">My Dashboard</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main class="page-content" style="padding-top:calc(var(--hdr-h) + 1.5rem);">
    <div class="container">
      <div class="nl-view-wrap">

        <div class="nl-header-card">
          <a href="newsletter-archive.php" class="nl-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Newsletter Archive
          </a>
          <h1 class="nl-view-subject"><?= htmlspecialchars($nl['subject']) ?></h1>
          <div class="nl-view-meta">
            <?php if ($sendDate): ?>
              <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <?= $sendDate ?>
              </span>
            <?php endif; ?>
            <?php if ($nl['emails_sent']): ?>
              <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Sent to <?= number_format($nl['emails_sent']) ?> members
              </span>
            <?php endif; ?>
          </div>
          <div class="nl-view-actions">
            <?php if ($nl['archive_url']): ?>
              <a href="<?= htmlspecialchars($nl['archive_url']) ?>" target="_blank" rel="noopener"
                 class="btn btn-outline" style="font-size:.83rem;padding:.4rem 1rem;">
                Open in Mailchimp ↗
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Newsletter rendered in sandboxed iframe -->
        <div class="nl-frame-wrap">
          <iframe id="nl-frame"
                  src="members/newsletter-serve.php?id=<?= $nl['id'] ?>"
                  title="<?= htmlspecialchars($nl['subject']) ?>"
                  sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                  loading="lazy">
          </iframe>
        </div>

      </div>
    </div>
  </main>

  <footer class="site-footer" style="margin-top:3rem;">
    <div class="container footer-grid">
      <div>
        <h3>Bulkley Valley Teachers' Union</h3>
        <p>Local of the BC Teachers' Federation</p>
        <p>Representing educators in<br>Houston, Telkwa, and Smithers</p>
      </div>
      <div><h3>Contact</h3><p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p><p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p><p><a href="contact.php">Contact Us</a></p></div>
      <div><h3>Navigate</h3><ul class="footer-nav-list"><li><a href="documents.php">Documents</a></li><li><a href="members.php">Members</a></li><li><a href="library.php">Resource Library</a></li><li><a href="newsletter-archive.php">Newsletters</a></li><li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li><li><a href="bctf.php">BCTF</a></li></ul></div>
      <div><h3>Connect</h3><a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a></div>
    </div>
    <div class="footer-bottom"><div class="container"><p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p></div></div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
  <script>
    // Auto-resize the iframe to its content height (same-origin, so this works)
    const frame = document.getElementById('nl-frame');
    function resizeFrame() {
      try {
        const h = frame.contentDocument.body.scrollHeight;
        if (h > 100) frame.style.height = h + 'px';
      } catch (e) {}
    }
    frame.addEventListener('load', resizeFrame);
    // Retry once after a short delay in case images load late
    frame.addEventListener('load', () => setTimeout(resizeFrame, 800));
  </script>
</body>
</html>
