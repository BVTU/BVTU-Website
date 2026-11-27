<?php
/**
 * ttoc-page.php — the frame every TTOC page shares.
 *
 * ttoc.php is the TTOC home page: one card per topic. Each topic is its own
 * short page (ttoc-copiers.php, ttoc-ei.php, …) so a teacher on call can go
 * straight to the one thing they need instead of scrolling one long page.
 *
 * The topic list lives in one place: the 'ttoc' section of bvtuNavSections()
 * in inc/nav.php. It drives the menu, the cards on ttoc.php and Member
 * Resources, so adding a topic page means adding it there and nowhere else.
 *
 *     require_once __DIR__ . '/inc/ttoc-page.php';
 *     ttocPageStart('Sick Leave', 'Meta description.', 'One-line lead.');
 *     … the topic's content …
 *     ttocPageEnd();
 */
require_once __DIR__ . '/../members/auth.php';

/** Head, header, hero and the opening of <main>. $home leaves out the breadcrumb. */
function ttocPageStart(string $title, string $description, string $lead, bool $home = false): void {
    $h    = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $root = dirname(__DIR__);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title><?= $h($title) ?> — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="<?= $h($description) ?>">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime($root . '/css/style.css') ?>">
  <link rel="stylesheet" href="css/ttoc.css?v=<?= @filemtime($root . '/css/ttoc.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php require_once __DIR__ . '/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <?php if (!$home): ?>
      <div class="breadcrumb">
        <a href="index.php">Home</a><span>›</span><a href="ttoc.php">TTOCs</a><span>›</span><?= $h($title) ?>
      </div>
      <?php endif; ?>
      <h1><?= $h($title) ?></h1>
      <p><?= $h($lead) ?></p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">
<?php
}

/** The contact line, footer and scripts. $script is extra inline JS for the page. */
function ttocPageEnd(string $script = ''): void {
    $root = dirname(__DIR__);
    ?>
      <div class="info-box">
        <p><strong>Questions about your TTOC entitlements?</strong> Contact BVTU President Cody Lind at <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a> or by phone at 613-485-3127.</p>
      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js?v=<?= @filemtime($root . '/js/site.js') ?>"></script>
  <script src="js/search.js"></script>
<?php if ($script !== ''): ?>
  <script>
<?= $script ?>
  </script>
<?php endif; ?>
</body>
</html>
<?php
}
