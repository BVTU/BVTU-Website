<?php
/**
 * news-updates.php — the News & Updates section page.
 *
 * The subject directory from bvtuNavSections() in inc/nav.php, then
 * the election archive: the 2026 trustee election pages lost their only
 * routes when the homepage promotion took itself down on 18 October, and this
 * is their permanent home. The pages themselves are unchanged.
 */
require_once __DIR__ . '/members/auth.php';           // so the nav can tell you are signed in
require_once __DIR__ . '/members/forum-state.php';    // a small file, no database — as on index.php
require_once __DIR__ . '/inc/nav.php';

$fs        = forumState();
$forumDate = ($fs && $fs['event_date'] !== '' && strtotime($fs['event_date']))
    ? date('j F Y', strtotime($fs['event_date'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>News &amp; Updates — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU events and meetings, newsletters, the union and its governance, and the 2026 trustee election archive.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>News &amp; Updates</h1>
      <p><?= bvtuNavSections()['news']['intro'] ?></p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <?php bvtuTopicDirectory(['news']); ?>

      <div class="section-group" id="election-2026">
        <h2 class="section-group-title">Archive: 2026 school trustee election</h2>
        <p class="section-more" style="margin-top:0;">General voting day was Saturday 17 October 2026.</p>
        <ul class="archive-list">
          <li>
            <a href="forum.php">All-candidates forum recording</a>
            <?php if ($forumDate !== ''): ?><span class="archive-date">Recorded <?= htmlspecialchars($forumDate) ?></span><?php endif; ?>
          </li>
          <li>
            <a href="trustee-candidates.php">Candidate survey responses</a>
            <span class="archive-date">Published as submitted</span>
          </li>
          <li>
            <a href="documents/BVTU-Trustee-Candidate-Responses-2026-09-28.pdf" target="_blank" rel="noopener">Candidate responses (PDF)<span class="sr-only"> (opens in a new tab)</span></a>
            <span class="archive-date">28 September 2026</span>
          </li>
          <li>
            <a href="trustee-zones.php">Trustee zone map</a>
            <span class="archive-date">Zones as used for the 2026 election</span>
          </li>
        </ul>
      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
