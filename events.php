<?php
/**
 * events.php — the SURTs and seminars a member can sign up for, and the ones
 * that have already run.
 *
 * Public. Registration itself lives on event.php; this is the way in for
 * somebody who has not been handed a link.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/event-db.php';

// A public page: a database that cannot be reached should show an empty
// listing, not a 500. evPublished() and evDocuments() already degrade that
// way, and this line would have defeated both.
try {
    evEnsureTables();
} catch (\Throwable $e) {
    error_log('events.php: ' . $e->getMessage());
}

$all      = evPublished();
$upcoming = [];
$past     = [];
foreach ($all as $e) {
    if (evIsPast($e)) { $past[] = $e; } else { $upcoming[] = $e; }
}
// Soonest first for what is ahead; most recent first for what is behind.
usort($upcoming, function ($a, $b) {
    $x = $a['starts_at'] ? strtotime($a['starts_at']) : PHP_INT_MAX;
    $y = $b['starts_at'] ? strtotime($b['starts_at']) : PHP_INT_MAX;
    return $x <=> $y;
});

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>SURTs &amp; Seminars — BVTU</title>
  <meta name="description" content="Union rep training and seminars run by the Bulkley Valley Teachers' Union — what is coming up, and how to sign up.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .ev-group { margin-bottom: 3rem; }
    .ev-group:last-child { margin-bottom: 0; }
    .ev-group h2 { font-size: 1.25rem; margin: 0 0 .3rem; color: var(--primary); }
    .ev-group .sub { font-size: .9rem; color: var(--gray-600); margin: 0 0 1.4rem; }

    .ev-cards { list-style: none; margin: 0; padding: 0;
                display: grid; grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr)); gap: 1.1rem; }
    .ev-cards a { display: flex; flex-direction: column; gap: .3rem; height: 100%;
                  background: #fff; border: 1px solid var(--border); border-radius: 10px;
                  padding: 1.2rem 1.3rem; text-decoration: none; color: inherit;
                  transition: border-color .15s ease, transform .15s ease; }
    .ev-cards a:hover { border-color: var(--primary); transform: translateY(-2px); text-decoration: none; }
    .ev-when  { font-size: .74rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
                color: var(--primary); }
    .ev-name  { font-size: 1.08rem; font-weight: 700; color: var(--gray-800); line-height: 1.35; }
    .ev-sum   { font-size: .89rem; color: var(--gray-600); line-height: 1.55; }
    .ev-where { font-size: .82rem; color: var(--gray-500); }
    .ev-tags  { margin-top: .5rem; display: flex; gap: .4rem; flex-wrap: wrap; }
    .ev-tag   { font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase;
                border-radius: 4px; padding: .14rem .42rem; }
    .ev-tag.open   { background: var(--accent); color: var(--primary); }
    .ev-tag.shut   { background: var(--gray-100); color: var(--gray-500); }
    .ev-tag.docs   { background: #fff6e6; color: #8a6d3b; }
    .ev-go    { margin-top: auto; padding-top: .7rem; font-size: .83rem; font-weight: 700; color: var(--primary); }

    .ev-past a { background: var(--off-white); }
    .ev-none { background: var(--off-white); border: 1px solid var(--border); border-radius: 10px;
               padding: 2rem 1.5rem; color: var(--gray-600); font-size: .95rem; }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>SURTs &amp; Seminars</h1>
      <p>Union rep training and other sessions run by your local. Open to BVTU members —
         no portal account needed to sign up.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="ev-group">
        <h2>Coming up</h2>
        <p class="sub">Click through for the details, the handouts and the sign-up form.</p>

        <?php if (!$upcoming): ?>
        <div class="ev-none">
          Nothing scheduled at the moment. When something is booked it will appear here and on
          the <a href="index.php">home page</a> &mdash; and if you have registered for anything
          before, you will get an email.
        </div>
        <?php else: ?>
        <ul class="ev-cards">
          <?php foreach ($upcoming as $e):
                $docs = count(evDocuments((int)$e['id']));
                $open = evRegistrationState($e); ?>
          <li>
            <a href="event.php?s=<?= $h(urlencode($e['slug'])) ?>">
              <span class="ev-when"><?= $h(evWhen($e)) ?></span>
              <span class="ev-name"><?= $h($e['title']) ?></span>
              <?php if (trim((string)$e['summary']) !== ''): ?>
              <span class="ev-sum"><?= $h($e['summary']) ?></span>
              <?php endif; ?>
              <?php if (trim((string)$e['location']) !== ''): ?>
              <span class="ev-where"><?= $h($e['location']) ?></span>
              <?php endif; ?>
              <span class="ev-tags">
                <span class="ev-tag <?= $open['open'] ? 'open' : 'shut' ?>">
                  <?= $open['open'] ? 'Registration open' : 'Registration closed' ?>
                </span>
                <?php if ($docs): ?>
                <span class="ev-tag docs"><?= $docs ?> handout<?= $docs === 1 ? '' : 's' ?></span>
                <?php endif; ?>
              </span>
              <span class="ev-go"><?= $open['open'] ? 'Details &amp; sign up' : 'See the details' ?> &rarr;</span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <?php if ($past): ?>
      <div class="ev-group ev-past">
        <h2>Already run</h2>
        <p class="sub">Kept here for the record &mdash; the handouts are still on each page.</p>
        <ul class="ev-cards">
          <?php foreach (array_slice($past, 0, 12) as $e):
                $docs = count(evDocuments((int)$e['id'])); ?>
          <li>
            <a href="event.php?s=<?= $h(urlencode($e['slug'])) ?>">
              <span class="ev-when"><?= $h(evWhen($e)) ?></span>
              <span class="ev-name"><?= $h($e['title']) ?></span>
              <?php if ($docs): ?>
              <span class="ev-tags">
                <span class="ev-tag docs"><?= $docs ?> handout<?= $docs === 1 ? '' : 's' ?></span>
              </span>
              <?php endif; ?>
              <span class="ev-go">See the details &rarr;</span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

    </div>
  </main>

</body>
</html>
