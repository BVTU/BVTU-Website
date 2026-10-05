<?php
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/forum-db.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

$video    = forumLatest();                 // read-only; null until one is published
$live     = $video !== null;
$chapters = $live ? forumChapters((int)$video['id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>All-Candidates Forum — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Watch the BVTU all-candidates forum for the School District 54 trustee election, with chapters to jump to each candidate and question.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .fv-wrap { margin: 1.4rem 0 .4rem; }
    .fv-frame { position: relative; width: 100%; aspect-ratio: 16 / 9; background: #10241a;
                border-radius: 12px; overflow: hidden; }
    .fv-frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
    .fv-play { position: absolute; inset: 0; width: 100%; height: 100%; display: flex;
               flex-direction: column; align-items: center; justify-content: center; gap: .5rem;
               background: linear-gradient(160deg, #15402a, #0c2318); color: #fff;
               border: 0; cursor: pointer; font-family: inherit; }
    .fv-play:hover .fv-tri { transform: scale(1.08); }
    .fv-tri { width: 0; height: 0; border-left: 26px solid #fff; border-top: 16px solid transparent;
              border-bottom: 16px solid transparent; margin-left: 6px; transition: transform .15s; }
    .fv-label { font-size: 1.05rem; font-weight: 700; }
    .fv-sub { font-size: .8rem; opacity: .75; }
    .fv-note { font-size: .8rem; color: var(--gray-600); line-height: 1.6; margin: .6rem 0 0; }

    .fv-chapters { list-style: none; margin: 0; padding: 0; border: 1px solid var(--border);
                   border-radius: 12px; overflow: hidden; }
    .fv-chapters li + li { border-top: 1px solid var(--border); }
    .fv-chapter { display: flex; gap: .9rem; align-items: baseline; width: 100%; text-align: left;
                  background: #fff; border: 0; padding: .7rem 1rem; cursor: pointer;
                  font-family: inherit; font-size: .92rem; color: var(--gray-800); }
    .fv-chapter:hover { background: var(--accent); color: var(--primary); }
    .fv-at { flex: 0 0 4.6rem; font-variant-numeric: tabular-nums; font-weight: 700;
             color: var(--primary); font-size: .85rem; }
    .fv-more { display: flex; gap: .7rem; flex-wrap: wrap; margin-top: 2rem; }
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
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li><li><a href="trustee-zones.html">Trustee Zone Map</a></li><li><a href="trustee-candidates.php">Trustee Candidate Responses</a></li></ul></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
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
      <h1><?= htmlspecialchars($live && $video['title'] !== '' ? $video['title'] : 'All-Candidates Forum') ?></h1>
      <p>
        <?php if ($live && $video['event_date']): ?>
          Recorded <?= htmlspecialchars(date('j F Y', strtotime($video['event_date']))) ?>.
        <?php endif; ?>
        School District 54 trustee election.
      </p>
    </div>
  </section>

  <main class="container" style="max-width:980px;padding:2.5rem 1.5rem 4rem;">

  <?php if (!$live): ?>
    <div style="border:1px solid var(--border);border-radius:12px;padding:2rem;text-align:center;color:var(--gray-600);">
      <p style="margin:0;font-size:1.05rem;">The recording is not published yet.</p>
      <p style="margin:.5rem 0 0;font-size:.9rem;">
        It will appear here once it has been uploaded. In the meantime you can
        <a href="trustee-candidates.php">read the candidates&rsquo; written responses</a>.
      </p>
    </div>
  <?php else: ?>

    <?php if (trim((string)$video['blurb']) !== ''): ?>
      <p style="font-size:1.02rem;line-height:1.8;color:var(--gray-700);max-width:70ch;">
        <?= nl2br(htmlspecialchars($video['blurb'])) ?>
      </p>
    <?php endif; ?>

    <?php
    /*
     * A placeholder, not the player.
     *
     * privacy.php tells people nothing on this site reports to Google. An
     * embedded YouTube player contacts Google the moment the page loads, which
     * would make that untrue for anyone who merely visited this page. So
     * nothing loads until someone presses play, and the note under the button
     * says what pressing it does. youtube-nocookie is used when it does load.
     */
    ?>
    <div class="fv-wrap">
      <div class="fv-frame" id="fv-frame"
           data-video="<?= htmlspecialchars($video['youtube_id']) ?>">
        <button type="button" class="fv-play" id="fv-play" aria-label="Play the forum recording">
          <span class="fv-tri" aria-hidden="true"></span>
          <span class="fv-label">Play the recording</span>
          <span class="fv-sub">Loads the video from YouTube</span>
        </button>
      </div>
      <p class="fv-note">
        The recording is hosted on YouTube so it plays well on a phone and carries captions.
        Nothing is requested from YouTube until you press play.
        <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($video['youtube_id']) ?>"
           target="_blank" rel="noopener">Open it on YouTube instead</a>.
      </p>
    </div>

    <?php if ($chapters): ?>
    <h2 style="font-size:1.05rem;margin:2.2rem 0 .3rem;">Jump to a part</h2>
    <p style="font-size:.88rem;color:var(--gray-600);margin:0 0 1rem;">
      It runs <?= htmlspecialchars(forumStamp((int)end($chapters)['seconds'])) ?> or more &mdash;
      these jump straight to the part you want.
    </p>
    <ol class="fv-chapters">
      <?php foreach ($chapters as $c): ?>
      <li>
        <button type="button" class="fv-chapter" data-at="<?= (int)$c['seconds'] ?>">
          <span class="fv-at"><?= htmlspecialchars(forumStamp((int)$c['seconds'])) ?></span>
          <span class="fv-what"><?= htmlspecialchars($c['label']) ?></span>
        </button>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <div class="fv-more">
      <a href="trustee-candidates.php" class="btn btn-outline">Read the written responses</a>
      <a href="trustee-zones.html" class="btn btn-outline">Find your voting zone</a>
    </div>

  <?php endif; ?>

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
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li><li><a href="trustee-zones.html">Trustee Zone Map</a></li><li><a href="trustee-candidates.php">Trustee Candidate Responses</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li><a href="prod.php">PRO-D</a></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
        </ul>
      </div>
      <div>
        <h3>Connect</h3>
        <a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation
           · <a href="privacy.php" style="color:inherit;text-decoration:underline;">Privacy</a></p>
      </div>
    </div>
  </footer>

<script>
/* The player is only created when someone asks for it — see the note in the
   markup. Chapter buttons reuse the same player once it exists, and create it
   at the right moment if it does not. */
(function () {
  var frame = document.getElementById('fv-frame');
  if (!frame) return;
  var id = frame.getAttribute('data-video');

  function load(at) {
    var src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id)
            + '?autoplay=1&rel=0&modestbranding=1'
            + (at ? '&start=' + encodeURIComponent(at) : '');
    var existing = frame.querySelector('iframe');
    if (existing) { existing.src = src; return; }
    var f = document.createElement('iframe');
    f.src = src;
    f.title = 'All-candidates forum recording';
    f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture';
    f.setAttribute('allowfullscreen', '');
    var btn = document.getElementById('fv-play');
    if (btn) btn.remove();
    frame.appendChild(f);
  }

  var play = document.getElementById('fv-play');
  if (play) play.addEventListener('click', function () { load(0); });

  document.querySelectorAll('.fv-chapter').forEach(function (b) {
    b.addEventListener('click', function () {
      load(parseInt(b.getAttribute('data-at'), 10) || 0);
      frame.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
})();
</script>
  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
