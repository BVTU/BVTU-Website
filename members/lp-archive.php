<?php
/**
 * lp-archive.php — one school year, read only, with exports.
 *
 * Reads the same rows the live pages read; there is no separate archive store.
 * Grants, budget lines, vouchers, claims and collaboration grants each keep
 * their own year, so a past year is simply a query, not a copy — which is why
 * nothing has to be moved at year end and nothing can be lost by moving it.
 *
 * This page never writes.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/exp-db.php';
require_once __DIR__ . '/collab-grant-db.php';
require_once __DIR__ . '/xlsx-writer.php';

requireLogin();
$member = getMember();
if (!lpCanView($member['email'])) { header('Location: dashboard.php'); exit; }

sendPrivateHeaders();
lpEnsureTables();
expBatchEnsureTables();
cgEnsureTable();

$years = lpYearsWithData();
$w     = isset($_GET['year']) && is_scalar($_GET['year']) ? (int)$_GET['year'] : 0;
$year  = in_array($w, $years, true) ? $w : lpCurrentYear();
$label = lpYearLabel($year);

$grants   = lpGrantSummary($year);
$lines    = lpBudgetSummary($year);
$vouchers = lpGetVouchers('', '', $year);
$claims   = expBatchGetAll('', $year);
$collab   = cgGetApplications($year);

$exportable = LP_ARCHIVE_SECTIONS;

$export = isset($_GET['export']) && is_scalar($_GET['export']) ? (string)$_GET['export'] : '';
if (isset($exportable[$export])) {
    list($headers, $rows) = archiveSection($export, $grants, $lines, $vouchers, $claims, $collab);
    $format = (isset($_GET['format']) && $_GET['format'] === 'xlsx') ? 'xlsx' : 'csv';
    $file   = 'bvtu-' . $exportable[$export] . '-' . $year . '-' . ($year + 1);

    if ($format === 'xlsx') {
        $tmp = tempnam(sys_get_temp_dir(), 'lparch');
        if (xlsxWrite($tmp, $headers, $rows, ucfirst($export))) {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $file . '.xlsx"');
                        // tempnam() created this file at 0 bytes and PHP caches that stat, so
            // filesize() can report 0 after it has been written — the browser then
            // saves a truncated or empty download.
            clearstatcache(true, $tmp);
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp);
            unlink($tmp);
            exit;
        }
        if (file_exists($tmp)) unlink($tmp);
        // Fall through to CSV rather than sending a broken spreadsheet.
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));   // BOM so Excel reads UTF-8
    fputcsv($out, $headers);
    foreach ($rows as $r) fputcsv($out, array_map('csvSafeText', $r));
    fclose($out);
    exit;
}

$totalBudget = array_sum(array_column($grants, 'budget'));
$totalSpent  = array_sum(array_column($grants, 'spent'));
$claimTotal  = 0.0;
foreach ($claims as $b) $claimTotal += expBatchTotal((int)$b['id']);
$voucherTotal = array_sum(array_map(function ($v) { return (float)$v['total_amount']; }, $vouchers));
$isClosed     = lpYearIsClosed($year);

function qsv(string $s): string { return htmlspecialchars($s); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Archive <?= qsv($label) ?> — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:1080px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 1rem; }
    .yearbar { display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;
               background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:.55rem .9rem; }
    .yearbar select { border:1px solid var(--gray-300);border-radius:7px;padding:.35rem .6rem;
                      font-size:.88rem;font-family:inherit; }
    .tag { display:inline-block;padding:.12rem .5rem;border-radius:100px;font-size:.72rem;font-weight:700; }
    .tag.closed { background:#f1f5f9;color:#475569; }
    .tiles { display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1.2rem; }
    .tile { flex:1;min-width:150px;background:#fff;border:1px solid var(--gray-200);
            border-radius:10px;padding:.85rem 1rem; }
    .tile .n { font-size:1.35rem;font-weight:800;color:var(--gray-800);line-height:1.1; }
    .tile .l { font-size:.76rem;color:var(--gray-500);margin-top:.2rem; }
    .pcard { background:#fff;border:1px solid var(--gray-200);border-radius:12px;
             padding:1.1rem 1.25rem;margin-bottom:1rem; }
    .pcard .head { display:flex;justify-content:space-between;align-items:center;
                   gap:.6rem;flex-wrap:wrap;margin-bottom:.7rem; }
    .pcard h2 { font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;
                color:var(--gray-500);margin:0; }
    .dl a { font-size:.76rem;color:var(--gray-600);text-decoration:none;border:1px solid var(--gray-200);
            border-radius:6px;padding:.2rem .55rem;margin-left:.3rem; }
    .dl a:hover { border-color:var(--primary);color:var(--primary); }
    table { width:100%;border-collapse:collapse;font-size:.85rem; }
    th { text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.04em;
         color:var(--gray-500);font-weight:700;padding:.35rem .5rem;border-bottom:1px solid var(--gray-200); }
    td { padding:.35rem .5rem;border-bottom:1px solid var(--gray-100);color:var(--gray-700); }
    tr:last-child td { border-bottom:none; }
    td.num, th.num { text-align:right;white-space:nowrap; }
    .empty { font-size:.85rem;color:var(--gray-400);font-style:italic;margin:0; }
  </style>
</head>
<body>
<div class="wrap">

  <a class="back-link" href="lp-dashboard.php">&#x2190; Expense Tracker</a>
  <h1>Archive &mdash; <?= qsv($label) ?></h1>

  <form method="GET" class="yearbar">
    <label for="y" style="font-size:.72rem;font-weight:800;text-transform:uppercase;
                          letter-spacing:.05em;color:var(--gray-500);">School year</label>
    <select name="year" id="y" onchange="this.form.submit()">
      <?php foreach ($years as $yy): ?>
      <option value="<?= (int)$yy ?>" <?= $yy === $year ? 'selected' : '' ?>>
        <?= qsv(lpYearLabel($yy)) ?><?= $yy === lpCurrentYear() ? ' (current)' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button type="submit">Show</button></noscript>
    <?php if ($isClosed): ?><span class="tag closed">Closed</span><?php endif; ?>
    <span style="font-size:.8rem;color:var(--gray-500);">Read only &mdash; figures are as they stand now.</span>
    <a href="lp-archive-bundle.php?year=<?= (int)$year ?>" style="margin-left:auto;font-size:.8rem;
       font-weight:700;color:#fff;background:var(--primary);border:1px solid var(--primary);
       border-radius:7px;padding:.35rem .8rem;text-decoration:none;">&#8681; Download whole year (ZIP)</a>
  </form>

  <p style="font-size:.82rem;color:var(--gray-600);line-height:1.7;margin:-.4rem 0 1.1rem;">
    The ZIP holds every record above <em>and every receipt image behind it</em>, readable
    without this website. Everything else lives only in the portal's database &mdash; keep
    a copy of this somewhere that is not the web host.
  </p>

  <div class="tiles">
    <div class="tile"><div class="n">$<?= number_format($totalSpent, 2) ?></div>
      <div class="l">Grant spending of $<?= number_format($totalBudget, 2) ?></div></div>
    <div class="tile"><div class="n"><?= count($vouchers) ?></div>
      <div class="l">President's vouchers &middot; $<?= number_format($voucherTotal, 2) ?></div></div>
    <div class="tile"><div class="n"><?= count($claims) ?></div>
      <div class="l">Member claims &middot; $<?= number_format($claimTotal, 2) ?></div></div>
    <div class="tile"><div class="n"><?= count($collab) ?></div>
      <div class="l">Collaboration grants</div></div>
  </div>

  <?php
    $sections = [
        'grants'   => 'BCTF grants',
        'lines'    => 'Budget lines',
        'vouchers' => "President's vouchers",
        'claims'   => 'Member claims',
        'collab'   => 'Collaboration grants',
    ];
    foreach ($sections as $key => $title):
        list($headers, $rows) = archiveSection($key, $grants, $lines, $vouchers, $claims, $collab);
  ?>
  <div class="pcard">
    <div class="head">
      <h2><?= qsv($title) ?> (<?= count($rows) ?>)</h2>
      <?php if ($rows): ?>
      <span class="dl">
        <a href="?year=<?= (int)$year ?>&amp;export=<?= qsv($key) ?>&amp;format=csv">CSV</a>
        <a href="?year=<?= (int)$year ?>&amp;export=<?= qsv($key) ?>&amp;format=xlsx">Excel</a>
      </span>
      <?php endif; ?>
    </div>
    <?php if (!$rows): ?>
      <p class="empty">Nothing recorded for <?= qsv($label) ?>.</p>
    <?php else: ?>
    <table>
      <thead><tr>
        <?php foreach ($headers as $i => $h): ?>
        <th class="<?= $i > 0 && in_array($h, ['Budget','Spent','Remaining','Total','Days','Release cost'], true) ? 'num' : '' ?>">
          <?= qsv($h) ?></th>
        <?php endforeach; ?>
      </tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($r as $i => $cell): ?>
          <td class="<?= in_array($headers[$i] ?? '', ['Budget','Spent','Remaining','Total','Days','Release cost'], true) ? 'num' : '' ?>">
            <?= qsv((string)$cell) ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

</div>
</body>
</html>
