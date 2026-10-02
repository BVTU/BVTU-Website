<?php
/**
 * analytics.php — what the public site is being used for.
 *
 * President only. Reads site_views and site_clicks, which hold no IP, no
 * user-agent, no member identity, and nothing at all from the members area —
 * see analytics-db.php for what is collected and why.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/analytics-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) $days = 30;

anEnsureTables();
$totals  = anTotals($days);
$daily   = anDaily($days);
$pages   = anTopPages($days);
$refs    = anReferrers($days);
$devices = anDevices($days);
$oldest  = anOldestView();

$docs = anTopClicks($days, 'doc');
$outs = anTopClicks($days, 'out');
$gos  = anTopClicks($days, 'go');
$ints = anTopClicks($days, 'int');

$maxDay = 0;
foreach ($daily as $d) $maxDay = max($maxDay, (int)$d['views']);
$devTotal = array_sum($devices);
$refTotal = 0;
foreach ($refs as $r) $refTotal += $r['total'];

$REF_LABEL = ['direct' => 'Typed or bookmarked', 'search' => 'Search engines',
              'social' => 'Social media', 'internal' => 'Another BVTU page',
              'other' => 'Other websites'];

function anPct(int $n, int $total): float { return $total > 0 ? ($n / $total) * 100 : 0.0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Site Analytics — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1040px; margin: 0 auto; padding: 2rem 1.5rem 5rem; }
    .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:1rem; }
    .page-header h1 { font-size:1.25rem; font-weight:800; color:var(--gray-800); margin:.3rem 0 0; }
    .back-link { font-size:.85rem; color:var(--primary); text-decoration:none; }
    .back-link:hover { text-decoration:underline; }
    .range a { font-size:.8rem; font-weight:700; color:var(--gray-600); text-decoration:none;
               border:1px solid var(--gray-200); border-radius:6px; padding:.3rem .75rem; margin-left:.3rem; }
    .range a.on { background:var(--primary); border-color:var(--primary); color:#fff; }
    .pcard { background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:1.4rem; margin-bottom:1.4rem; }
    .pcard h2 { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--gray-500); margin:0 0 1rem; }
    .stat-row { display:flex; gap:.8rem; margin-bottom:1.4rem; flex-wrap:wrap; }
    .stat { background:#fff; border:1px solid var(--gray-200); border-radius:10px; padding:.85rem 1.1rem; flex:1; min-width:130px; }
    .stat .n { font-size:1.75rem; font-weight:800; color:var(--gray-800); line-height:1; }
    .stat .l { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--gray-400); margin-top:.35rem; }
    .stat .s { font-size:.74rem; color:var(--gray-500); margin-top:.25rem; }
    .bars { display:flex; align-items:flex-end; gap:2px; height:120px; }
    .bars div { flex:1; background:var(--primary); border-radius:2px 2px 0 0; min-height:2px; opacity:.85; }
    .bars div:hover { opacity:1; }
    .bars-x { display:flex; justify-content:space-between; font-size:.7rem; color:var(--gray-400); margin-top:.4rem; }
    table { width:100%; border-collapse:collapse; font-size:.85rem; }
    th { text-align:left; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
         color:var(--gray-400); padding:.3rem .5rem .45rem 0; border-bottom:1px solid var(--gray-200); }
    td { padding:.42rem .5rem .42rem 0; border-bottom:1px solid var(--gray-100); color:var(--gray-700); }
    tr:last-child td { border-bottom:none; }
    td.n { text-align:right; font-weight:700; white-space:nowrap; }
    .meter { background:var(--gray-100); border-radius:100px; height:7px; overflow:hidden; min-width:70px; }
    .meter span { display:block; height:100%; background:var(--primary); border-radius:100px; }
    .two { display:grid; grid-template-columns:1fr 1fr; gap:1.4rem; }
    @media(max-width:800px){ .two { grid-template-columns:1fr; } }
    .empty { color:var(--gray-400); font-size:.85rem; padding:1.2rem 0; text-align:center; }
    .note { font-size:.76rem; color:var(--gray-500); line-height:1.6; }
    code { font-size:.82rem; color:var(--gray-700); word-break:break-all; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1>Site Analytics</h1>
    </div>
    <div class="range">
      <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $lbl): ?>
      <a href="?days=<?= $d ?>" class="<?= $d === $days ? 'on' : '' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="stat-row">
    <div class="stat">
      <div class="n"><?= number_format((int)$totals['views']) ?></div>
      <div class="l">Page views</div>
    </div>
    <div class="stat">
      <div class="n"><?= number_format((int)$totals['visitors']) ?></div>
      <div class="l">Daily visitors</div>
      <div class="s">added up &mdash; a regular reader counts once each day</div>
    </div>
    <div class="stat">
      <div class="n"><?= $totals['busiest_hour'] === null ? '—'
            : date('ga', mktime((int)$totals['busiest_hour'], 0)) ?></div>
      <div class="l">Busiest hour</div>
    </div>
    <div class="stat">
      <div class="n"><?= count($daily) ?></div>
      <div class="l">Days with traffic</div>
      <div class="s">of <?= $days ?></div>
    </div>
  </div>

  <?php if (!$totals['views']): ?>
  <div class="pcard">
    <h2>Nothing recorded yet</h2>
    <p class="note">
      No page views in the last <?= $days ?> days. Collection started when this was first
      deployed, so there is nothing from before then &mdash; and a visit only counts once the
      page has finished loading, from a browser that has not asked not to be tracked.
    </p>
  </div>
  <?php else: ?>

  <div class="pcard">
    <h2>Views per day</h2>
    <div class="bars">
      <?php foreach ($daily as $d): $h = $maxDay ? max(2, round(((int)$d['views'] / $maxDay) * 120)) : 2; ?>
      <div style="height:<?= $h ?>px"
           title="<?= htmlspecialchars(date('D j M', strtotime($d['day']))) ?> — <?= (int)$d['views'] ?> views, <?= (int)$d['visitors'] ?> people"></div>
      <?php endforeach; ?>
    </div>
    <div class="bars-x">
      <span><?= $daily ? htmlspecialchars(date('j M', strtotime($daily[0]['day']))) : '' ?></span>
      <span><?= $daily ? htmlspecialchars(date('j M', strtotime($daily[count($daily)-1]['day']))) : '' ?></span>
    </div>
    <?php if ($oldest && strtotime($oldest) > strtotime('-' . ($days - 1) . ' days')): ?>
    <p class="note" style="margin-top:.8rem;">
      Nothing is held from before <?= htmlspecialchars(date('j F Y', strtotime($oldest))) ?>, so
      the earlier part of this range is empty rather than quiet. That is either where collection
      started or where the <?= AN_RETAIN_DAYS ?>-day cutoff now falls &mdash; this page cannot
      tell which, because the records that would say are the ones that get deleted.
    </p>
    <?php endif; ?>
  </div>

  <div class="pcard">
    <h2>Most-read pages</h2>
    <table>
      <thead><tr><th>Page</th><th style="width:90px;"></th><th style="text-align:right;">Views</th><th style="text-align:right;">Daily visitors</th></tr></thead>
      <tbody>
        <?php $topViews = $pages ? (int)$pages[0]['views'] : 0; ?>
        <?php foreach ($pages as $p): ?>
        <tr>
          <td><code><?= htmlspecialchars($p['path']) ?></code></td>
          <td><div class="meter"><span style="width:<?= $topViews ? round(((int)$p['views']/$topViews)*100) : 0 ?>%"></span></div></td>
          <td class="n"><?= number_format((int)$p['views']) ?></td>
          <td class="n"><?= number_format((int)$p['visitors']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="two">
    <div class="pcard">
      <h2>How people arrived</h2>
      <table>
        <tbody>
        <?php foreach ($refs as $kind => $r): ?>
        <tr>
          <td>
            <?= htmlspecialchars($REF_LABEL[$kind] ?? $kind) ?>
            <?php if ($r['hosts']): arsort($r['hosts']); ?>
              <div class="note"><?= htmlspecialchars(implode(', ', array_slice(array_keys($r['hosts']), 0, 3))) ?></div>
            <?php endif; ?>
          </td>
          <td class="n"><?= number_format($r['total']) ?></td>
          <td class="n" style="color:var(--gray-400);font-weight:600;"><?= number_format(anPct($r['total'], $refTotal), 0) ?>%</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pcard">
      <h2>Phone, tablet or computer</h2>
      <table>
        <tbody>
        <?php foreach ($devices as $dev => $n): ?>
        <tr>
          <td style="text-transform:capitalize;"><?= htmlspecialchars($dev) ?></td>
          <td style="width:90px;"><div class="meter"><span style="width:<?= round(anPct($n, $devTotal)) ?>%"></span></div></td>
          <td class="n"><?= number_format($n) ?></td>
          <td class="n" style="color:var(--gray-400);font-weight:600;"><?= number_format(anPct($n, $devTotal), 0) ?>%</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
  $clickSets = [
      ['Documents opened',      $docs, 'Which PDFs and forms people actually download.'],
      ['Links off the site',    $outs, 'Where people go when they leave.'],
      ['Short links used',      $gos,  'bvtu.ca/go/ links followed from a page on the site.'],
      ['Moving around the site', $ints, 'Which internal links get used.'],
  ];
  ?>
  <div class="two">
    <?php foreach ($clickSets as list($title, $rows, $blurb)): ?>
    <div class="pcard">
      <h2><?= htmlspecialchars($title) ?></h2>
      <?php if (!$rows): ?>
        <p class="empty">No clicks recorded yet.</p>
      <?php else: ?>
      <table>
        <tbody>
        <?php $top = (int)$rows[0]['n']; foreach ($rows as $r): ?>
        <tr>
          <td><code><?= htmlspecialchars($r['target']) ?></code></td>
          <td style="width:70px;"><div class="meter"><span style="width:<?= $top ? round(((int)$r['n']/$top)*100) : 0 ?>%"></span></div></td>
          <td class="n"><?= number_format((int)$r['n']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
      <p class="note" style="margin-top:.7rem;"><?= htmlspecialchars($blurb) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>

  <div class="pcard">
    <h2>What this does and does not hold</h2>
    <p class="note">
      No IP addresses, no browser fingerprints, no names. The members area is not counted at
      all &mdash; nothing here reflects what a signed-in member reads. People are counted with a
      code that is regenerated every day, so the same person on Monday and Tuesday cannot be
      matched up &mdash; which is also why a visitor count over several days adds up the daily
      figures rather than counting individuals. Visitors who ask not to be tracked are not
      counted at all.
      Detail is kept for <?= AN_RETAIN_DAYS ?> days, after which only daily page totals remain.
      <a href="../privacy.php">The public privacy note</a> says the same in plainer words.
    </p>
  </div>

</div>
</body>
</html>
