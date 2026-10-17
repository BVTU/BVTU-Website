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
  <title>About — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="About the Bulkley Valley Teachers' Union — leadership, meetings, committees, and governance.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>About BVTU</h1>
      <p>The Bulkley Valley Teachers' Union represents educators across School District 54 in Houston, Telkwa, and Smithers.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- Leadership -->
      <div class="content-block">
        <h2>Executive Leadership</h2>
        <div class="people-grid">
          <div class="person-card">
            <h4>Cody Lind</h4>
            <p class="role">President</p>
            <p>3772-C 1st Ave, Smithers, BC V0J 2N0</p>
            <p><a href="contact.php">Contact Us</a></p>
          </div>
          <!-- Add additional executive members here -->
        </div>
      </div>

      <!-- Meetings -->
      <div class="content-block">
        <h2>Meetings</h2>
        <p>General meetings are held throughout the school year. Members are encouraged to attend and participate in union business.</p>
        <div class="info-box">
          <p>Meeting dates and agendas will be posted here and communicated directly to members. Check back or watch for email announcements.</p>
        </div>
        <!-- Add upcoming meeting dates as a list when available -->
      </div>

      <!-- Committees -->
      <div class="content-block">
        <h2>Committees</h2>
        <p>BVTU operates several committees that members can join to participate in union governance and advocacy.</p>
        <ul class="resource-list">
          <li><a href="#">Bargaining Committee</a></li>
          <li><a href="#">Health &amp; Safety Committee</a></li>
          <li><a href="#">PRO-D Committee</a></li>
          <li><a href="#">Status of Women Committee</a></li>
          <li><a href="#">Social Justice Committee</a></li>
        </ul>
      </div>

      <!-- Bargaining -->
      <div class="content-block">
        <h2>Bargaining</h2>
        <p>Collective bargaining is conducted at both the provincial level (through BCTF) and locally. Updates on bargaining will be posted here.</p>
        <div class="info-box">
          <p>For the latest provincial bargaining updates, visit the <a href="bctf.php">BCTF page</a> or go directly to <a href="https://bctf.ca" target="_blank" rel="noopener">bctf.ca</a>.</p>
        </div>
      </div>

      <!-- Governance -->
      <div class="content-block">
        <h2>Governance Documents</h2>
        <p>BVTU bylaws and constitutional documents govern how the local operates.</p>
        <div class="doc-list">
          <a href="mailto:lp54@bctf.ca?subject=BVTU Bylaws" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            BVTU Bylaws <span style="font-size:.8rem;color:var(--gray-500);">(request from president)</span>
          </a>
          <a href="mailto:lp54@bctf.ca?subject=BVTU Constitution" class="doc-item">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            BVTU Constitution <span style="font-size:.8rem;color:var(--gray-500);">(request from president)</span>
          </a>
        </div>
      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
