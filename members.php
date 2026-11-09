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
  <title>Member Resources — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BVTU member resources — your contract and benefits, professional development, teaching resources, and union news.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <style>
    .members-section-head { font-size: 1.15rem; font-weight: 800; margin: 0 0 .75rem; }
    .members-section-head a { color: var(--primary); text-decoration: none; }
    .members-section-head a:hover { text-decoration: underline; }
  </style>

  <section class="page-hero">
    <div class="container">
      <h1>Member Resources</h1>
      <p>Everything for members, by section.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <?php
      /*
       * The four sections and their pages, from the same definition as the
       * menu (inc/nav.php). Every card this page used to carry is in one of
       * them; Member Resources stays as the broad starting point people have
       * bookmarked, not as a fifth menu.
       */
      foreach (bvtuNavSections() as $key => $sec): ?>
      <section class="section-group" aria-labelledby="mr-<?= $key ?>">
        <h2 class="members-section-head" id="mr-<?= $key ?>"><a href="<?= htmlspecialchars($sec['url']) ?>"><?= $sec['label'] ?></a></h2>
        <?php bvtuTopicDirectory([$key], ['heading' => 'h3', 'skipFlatHeading' => true]); ?>
      </section>
      <?php endforeach; ?>

      <p class="section-more">
        Claims, applications and your account are in
        <a href="<?= $loggedIn ? 'members/dashboard.php' : 'members/login.php' ?>">My Dashboard</a><?= $loggedIn ? '' : ' (union login)' ?>.
        Every PDF is in <a href="documents.php">Documents &amp; Forms</a>.
      </p>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
