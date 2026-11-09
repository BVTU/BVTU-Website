<?php
/**
 * contract-benefits.php — the Contract & Benefits section page.
 *
 * The subject directory comes from bvtuNavSections() in inc/nav.php, the same
 * list as the menu, so this page cannot drift from it. Public: nothing here needs a login.
 */
require_once __DIR__ . '/members/auth.php';   // so the nav can tell you are signed in
require_once __DIR__ . '/inc/nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Contract &amp; Benefits — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Your collective agreement, LOUs, pay, health and dental benefits, leave, TTOC guidance and health and safety at work.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>Contract &amp; Benefits</h1>
      <p><?= bvtuNavSections()['contract']['intro'] ?></p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <?php bvtuTopicDirectory(['contract']); ?>

      <div class="section-group">
        <h2 class="section-group-title">Outside help</h2>
        <?php // The external links the old Health & Safety and BCTF menus carried. Each
              // also sits on its own topic page; these returned 200 on 6 Nov 2026. ?>
        <ul class="topic-support-links">
          <li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafeBC — claims and workplace injury</a></li>
          <li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">Counselling &amp; support — free and confidential (LifeWorks)</a></li>
          <li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">BCTF Advantage member discounts</a></li>
        </ul>
      </div>

      <p class="section-more">Not sure where your question fits? <a href="contact.php">Contact the union</a>.</p>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js?v=<?= @filemtime(__DIR__ . '/js/site.js') ?>"></script>
  <script src="js/search.js"></script>
</body>
</html>
