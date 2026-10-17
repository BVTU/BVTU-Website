<?php
/**
 * whats-on.php — the BVTU calendar.
 *
 * A month at a time, because that is the shape people already know how to
 * read. Everything on it comes from the events the seminar tools manage, so
 * there is one place an event lives and no second copy to fall out of step.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/event-db.php';

try {
    evEnsureTables();
} catch (\Throwable $e) {
    error_log('whats-on: ' . $e->getMessage());
}

date_default_timezone_set('America/Vancouver');

// Which month we are looking at. Clamped to a few years either side so a
// crafted ?m= cannot walk the page off into year 9999.
$want  = evStr($_GET['m'] ?? '');
$first = DateTime::createFromFormat('Y-m-d H:i:s', ($want !== '' ? $want : date('Y-m')) . '-01 00:00:00');
if (!$first instanceof DateTime) $first = new DateTime(date('Y-m') . '-01');
// Parenthesised: PHP 7.4 deprecates an unparenthesized mix of - and . — a
// notice on the live host that the local 8.0 binary never shows.
$limitLo = new DateTime(((int)date('Y') - 2) . '-01-01');
$limitHi = new DateTime(((int)date('Y') + 2) . '-12-01');
if ($first < $limitLo || $first > $limitHi) $first = new DateTime(date('Y-m') . '-01');
$first->setTime(0, 0, 0);

$last = (clone $first)->modify('last day of this month')->setTime(23, 59, 59);
// Null at the ends of the range, so the arrow disappears rather than
// teleporting the reader back to this month.
$prevD = (clone $first)->modify('-1 month');
$nextD = (clone $first)->modify('+1 month');
$prev  = $prevD >= $limitLo ? $prevD->format('Y-m') : null;
$next  = $nextD <= $limitHi ? $nextD->format('Y-m') : null;

// Everything published, bucketed by the day it starts.
$byDay = [];
$month = [];
foreach (evPublished() as $e) {
    $s = trim((string)$e['starts_at']);
    if ($s === '') continue;
    $t = strtotime($s);
    if ($t === false) continue;

    // An event that began last month and runs into this one belongs to this
    // month too — keying on the start alone made a two-day event straddling
    // the 31st vanish from the month most people would look in.
    $endRaw = trim((string)($e['ends_at'] ?? ''));
    $tEnd   = $endRaw !== '' ? strtotime($endRaw) : false;
    if ($tEnd === false) $tEnd = $t;
    if ($tEnd < $first->getTimestamp() || $t > $last->getTimestamp()) continue;

    // Shown on each day it covers, within this month.
    $cursor = max($t, $first->getTimestamp());
    $stop   = min($tEnd, $last->getTimestamp());
    for ($day = strtotime(date('Y-m-d', $cursor)); $day <= $stop; $day += 86400) {
        $byDay[date('Y-m-d', $day)][] = $e;
    }
    $month[] = $e;
}
// Ascending within a day, so the grid reads in the same order as the list.
foreach ($byDay as $k => $list) {
    usort($list, function ($a, $b) { return strtotime($a['starts_at']) <=> strtotime($b['starts_at']); });
    $byDay[$k] = $list;
}
usort($month, function ($a, $b) { return strtotime($a['starts_at']) <=> strtotime($b['starts_at']); });

// Monday-first, the way a school week reads.
$lead = ((int)$first->format('N')) - 1;
$days = (int)$last->format('j');

$h     = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$today = date('Y-m-d');
$subscribeUrl = (defined('SITE_URL') && SITE_URL ? rtrim(SITE_URL, '/') : 'https://bvtu.ca') . '/calendar.ics.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>What's On — BVTU</title>
  <meta name="description" content="Union events, seminars, meetings and deadlines from the Bulkley Valley Teachers' Union — and a calendar you can subscribe to.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .wo-bar { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1.4rem; }
    .wo-bar h2 { font-size:1.3rem; margin:0; color:var(--primary); flex:1 1 auto; }
    .wo-nav { display:flex; gap:.4rem; }
    .wo-nav a, .wo-today { display:inline-flex; align-items:center; justify-content:center;
                min-width:2.3rem; height:2.3rem; padding:0 .75rem; border:1px solid var(--border);
                border-radius:7px; background:#fff; color:var(--gray-700); text-decoration:none;
                font-size:.88rem; font-weight:600; }
    .wo-nav a:hover { border-color:var(--primary); color:var(--primary); text-decoration:none; }

    /* ── Month grid: desktop ─────────────────────────────────────────── */
    .wo-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px;
               background:var(--border); border:1px solid var(--border); border-radius:10px;
               overflow:hidden; }
    .wo-dow { background:var(--off-white); padding:.55rem .5rem; font-size:.72rem; font-weight:800;
              letter-spacing:.06em; text-transform:uppercase; color:var(--gray-500); text-align:center; }
    .wo-cell { background:#fff; min-height:7.5rem; padding:.45rem .5rem; }
    .wo-cell.pad { background:var(--off-white); }
    .wo-num { font-size:.8rem; font-weight:700; color:var(--gray-500); }
    .wo-cell.is-today .wo-num { background:var(--primary); color:#fff; border-radius:50%;
              width:1.55rem; height:1.55rem; display:inline-flex; align-items:center; justify-content:center; }
    .wo-ev { display:block; margin-top:.3rem; padding:.28rem .4rem; border-radius:5px;
             font-size:.76rem; line-height:1.3; text-decoration:none; color:#fff;
             overflow:hidden; text-overflow:ellipsis; }
    .wo-ev:hover { text-decoration:none; filter:brightness(1.12); }
    .wo-ev .t { display:block; font-weight:700; }
    .wo-ev .w { display:block; opacity:.85; font-size:.7rem; }

    /* ── The same month as a list: phones ─────────────────────────────── */
    .wo-list { display:none; }
    .wo-item { display:flex; gap:.9rem; padding:.9rem 0; border-bottom:1px solid var(--gray-100);
               text-decoration:none; color:inherit; }
    .wo-item:last-child { border-bottom:0; }
    .wo-item:hover { text-decoration:none; }
    .wo-date { flex:0 0 3.1rem; text-align:center; }
    .wo-date .d { font-size:1.25rem; font-weight:800; color:var(--gray-800); line-height:1.1; }
    .wo-date .m { font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em;
                  color:var(--gray-500); }
    .wo-main { flex:1 1 auto; min-width:0; }
    .wo-main .t { font-weight:700; font-size:.97rem; color:var(--gray-800); }
    .wo-main .w { font-size:.83rem; color:var(--gray-500); margin-top:.1rem; }
    .wo-pill { display:inline-block; font-size:.66rem; font-weight:800; text-transform:uppercase;
               letter-spacing:.05em; color:#fff; border-radius:4px; padding:.1rem .38rem;
               margin-top:.3rem; }

    .wo-empty { background:var(--off-white); border:1px solid var(--border); border-radius:10px;
                padding:2rem 1.5rem; color:var(--gray-600); font-size:.95rem; text-align:center; }

    .wo-sub { margin-top:2.5rem; background:var(--accent); border:1px solid #c6e3d2;
              border-radius:10px; padding:1.4rem 1.5rem; }
    .wo-sub h3 { font-size:1.02rem; margin:0 0 .35rem; color:var(--primary-dk); }
    .wo-sub p  { margin:0 0 .9rem; font-size:.9rem; color:var(--gray-700); line-height:1.6; }
    .wo-sub .row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
    .wo-sub input { flex:1 1 18rem; min-width:0; font-size:.8rem; padding:.45rem .6rem;
                    border:1px solid #c6e3d2; border-radius:6px; background:#fff; color:var(--gray-600); }
    .wo-sub button { background:var(--primary); color:#fff; border:0; border-radius:6px;
                     font:inherit; font-size:.85rem; font-weight:700; padding:.48rem 1rem; cursor:pointer; }

    .wo-key { display:flex; gap:.8rem; flex-wrap:wrap; margin-top:1rem; font-size:.78rem; color:var(--gray-600); }
    .wo-key span { display:inline-flex; align-items:center; gap:.35rem; }
    .wo-key i { width:.7rem; height:.7rem; border-radius:3px; display:inline-block; }

    @media (max-width: 820px) {
      .wo-grid { display:none; }
      .wo-list { display:block; }
    }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>What's On</h1>
      <p>Seminars, meetings, deadlines and everything else the union has coming up.
         Tap an event for the details, or add the whole calendar to your phone.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <div class="wo-bar">
        <h2><?= $h($first->format('F Y')) ?></h2>
        <div class="wo-nav">
          <?php if ($prev !== null): ?>
          <a href="?m=<?= $h($prev) ?>" aria-label="Previous month">&larr;</a>
          <?php endif; ?>
          <a href="?m=<?= $h(date('Y-m')) ?>">Today</a>
          <?php if ($next !== null): ?>
          <a href="?m=<?= $h($next) ?>" aria-label="Next month">&rarr;</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$month): ?>
      <div class="wo-empty">
        Nothing on in <?= $h($first->format('F')) ?>.
        <?php if ($next !== null): ?>
        <a href="?m=<?= $h($next) ?>">Look at <?= $h($nextD->format('F')) ?></a>, or
        <?php endif; ?>
        <a href="events.php">see what you can sign up for</a>.
      </div>
      <?php else: ?>

      <!-- Month grid -->
      <div class="wo-grid">
        <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
        <div class="wo-dow"><?= $d ?></div>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < $lead; $i++): ?>
        <div class="wo-cell pad"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $days; $d++):
              $key = $first->format('Y-m-') . str_pad((string)$d, 2, '0', STR_PAD_LEFT); ?>
        <div class="wo-cell<?= $key === $today ? ' is-today' : '' ?>">
          <span class="wo-num"><?= $d ?></span>
          <?php foreach ($byDay[$key] ?? [] as $e): $k = evKind($e); ?>
          <a class="wo-ev" style="background:<?= $h($k['colour']) ?>;"
             href="event.php?s=<?= $h(urlencode($e['slug'])) ?>"
             title="<?= $h($e['title']) ?>">
            <span class="t"><?= $h($e['title']) ?></span>
            <span class="w"><?= $h(evClock(strtotime($e['starts_at']))) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endfor; ?>

        <?php
        // Fill the rest of the last row. Without these the grid's own
        // background shows through where the cells stop, which reads as a
        // stray coloured block rather than an empty end of month.
        $trail = (7 - (($lead + $days) % 7)) % 7;
        for ($i = 0; $i < $trail; $i++): ?>
        <div class="wo-cell pad"></div>
        <?php endfor; ?>
      </div>

      <!-- The same month, as a list, for phones -->
      <div class="wo-list">
        <?php foreach ($month as $e): $k = evKind($e); $t = strtotime($e['starts_at']); ?>
        <a class="wo-item" href="event.php?s=<?= $h(urlencode($e['slug'])) ?>">
          <span class="wo-date">
            <span class="d"><?= $h(date('j', $t)) ?></span>
            <span class="m"><?= $h(date('D', $t)) ?></span>
          </span>
          <span class="wo-main">
            <span class="t"><?= $h($e['title']) ?></span>
            <span class="w">
              <?= $h(evClock($t)) ?><?php if (trim((string)$e['location']) !== ''): ?>
              &middot; <?= $h($e['location']) ?><?php endif; ?>
            </span>
            <span class="wo-pill" style="background:<?= $h($k['colour']) ?>;"><?= $h($k['short']) ?></span>
          </span>
        </a>
        <?php endforeach; ?>
      </div>

      <div class="wo-key">
        <?php foreach (EV_KINDS as $slug => $k): ?>
        <span><i style="background:<?= $h($k['colour']) ?>;"></i><?= $h($k['short']) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="wo-sub">
        <h3>Put the BVTU calendar in your own</h3>
        <p>
          Add this address once and every union event turns up in your calendar from then on
          &mdash; no more emails to remember. Works with Google, Apple, Proton, Outlook and
          anything else that reads a calendar feed.
        </p>
        <div class="row">
          <input type="text" readonly value="<?= $h($subscribeUrl) ?>" id="woFeed"
                 onfocus="this.select()" aria-label="Calendar address">
          <button type="button" onclick="woCopy(this)">Copy</button>
          <a href="calendar.ics.php" class="btn btn-outline" style="font-size:.85rem;padding:.45rem .95rem;">
            Download
          </a>
        </div>
      </div>

    </div>
  </main>

  <script>
  function woCopy(btn) {
    var box = document.getElementById('woFeed');
    var done = function () { btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = 'Copy'; }, 1800); };
    box.select();
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(box.value).then(done, function () {
        try { document.execCommand('copy'); done(); } catch (e) {}
      });
    } else {
      try { document.execCommand('copy'); done(); } catch (e) {}
    }
  }
  </script>
</body>
</html>
