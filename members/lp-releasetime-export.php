<?php
/**
 * lp-releasetime-export.php — the package that goes to the BCTF.
 *
 * ?year=N produces a ZIP holding:
 *   reimbursement.csv    the BCTF table, one row per invoice, plus totals
 *   reimbursement.html   the same laid out for printing or pasting into the form
 *   Invoice-<n>.<ext>    every invoice file, named by its number
 *
 * The BCTF's form wants one row per invoice — invoice number, dates released,
 * name of released member, number of release days, cost of release — and rule
 * (g) asks for a short report of activities alongside it. Both are built here
 * from the log rather than retyped.
 *
 * President only, the same gate as the rest of the tool.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/lp-releasetime-db.php';
require_once __DIR__ . '/xlsx-writer.php';   // csvSafeText()
requireLogin();

$member = getMember();
if (!lpCanView($member['email'])) { http_response_code(403); exit('Access denied.'); }

$year = (int)($_GET['year'] ?? 0);
if ($year < 2000 || $year > 2100) $year = lpCurrentYear();

lpRtEnsureTables();
$yr       = lpRtYear($year);
$invoices = lpRtInvoices($year);
$totals   = lpRtTotals($year);
$label    = lpYearLabel($year);

if (!$invoices) {
    http_response_code(400);
    exit('Nothing to export for ' . htmlspecialchars($label) . ' yet — add an invoice first.');
}

/**
 * A cost that was never recorded must not leave here as "0.00".
 *
 * This package is what the BCTF reimburses against. A blank tells them the
 * figure is still coming; a zero tells them the release was free, and they
 * would be right to pay nothing for it.
 */
function rtCost($v): string { return $v === null ? '' : number_format((float)$v, 2); }

$rows       = [];
$dayTotal   = 0.0;
$costTotal  = 0.0;
$noCost     = [];
$mixed      = [];   // invoices covering days BVTU decided not to claim

foreach ($invoices as $inv) {
    $num = $inv['invoice_number'] !== '' ? $inv['invoice_number'] : ('#' . (int)$inv['id']);
    $dayTotal += (float)$inv['days'];
    if ($inv['total_cost'] === null) $noCost[] = $num;
    else                             $costTotal += (float)$inv['total_cost'];

    /*
     * The day count already leaves out days marked not-claimed, but the cost
     * cannot: the district bills one figure for the whole invoice and there is
     * no per-day rate to subtract. Pro-rating would be inventing a number to
     * send the BCTF. So the cost is reported as invoiced and the mismatch is
     * stated, loudly, for a human to settle before submitting.
     */
    $skipped = 0.0;
    foreach ($inv['entries'] as $en) if ((int)$en['excluded']) $skipped += (float)$en['days'];
    if ($skipped > 0) $mixed[] = ['num' => $num, 'days' => $skipped];
    $rows[] = [
        'num'   => $num,
        'dates' => $inv['dates_text'],
        'names' => $inv['names_text'],
        'days'  => lpRtDays((float)$inv['days']),
        'cost'  => rtCost($inv['total_cost']),
        'file'  => $inv['file_path'],
        'orig'  => $inv['original_name'],
    ];
}

// ── CSV ──────────────────────────────────────────────────────────────────────
$csv = [['Invoice number', 'Dates released', 'Name of released member',
         'Number of release days', 'Cost of release ($)']];
foreach ($rows as $r) {
    $csv[] = [csvSafeText($r['num']), csvSafeText($r['dates']), csvSafeText($r['names']),
              $r['days'], $r['cost']];
}
$csv[] = ['', '', 'TOTAL', lpRtDays($dayTotal), number_format($costTotal, 2)];
if ($noCost) {
    $csv[] = [];
    $csv[] = ['', '', csvSafeText('Cost not yet recorded for invoice(s): ' . implode(', ', $noCost)
                                  . ' - the total above excludes them.'), '', ''];
}
if ($mixed) {
    $bits = [];
    foreach ($mixed as $m) $bits[] = $m['num'] . ' (' . lpRtDays($m['days']) . ' day(s))';
    $csv[] = [];
    $csv[] = ['', '', csvSafeText('CHECK BEFORE SENDING - these invoices also cover release days '
        . 'BVTU is not claiming: ' . implode(', ', $bits) . '. The day counts exclude them but the '
        . 'costs are as invoiced by the district and still include them.'), '', ''];
}

$h = fopen('php://temp', 'w+');
foreach ($csv as $row) fputcsv($h, $row);
rewind($h);
$csvOut = stream_get_contents($h);
fclose($h);

// ── printable page ───────────────────────────────────────────────────────────
$e = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$fte = $yr['fte'] !== null ? rtrim(rtrim((string)$yr['fte'], '0'), '.') : '—';

ob_start(); ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<title>Release Time reimbursement — <?= $e($label) ?></title>
<style>
  body{font-family:Georgia,serif;color:#111;max-width:7.5in;margin:0 auto;padding:.5in;font-size:11pt}
  h1{font-size:15pt;margin:0 0 .15in} h2{font-size:11pt;margin:.3in 0 .1in;text-transform:uppercase;letter-spacing:.04em}
  .meta{font-size:10pt;color:#333;margin-bottom:.25in;line-height:1.6}
  table{width:100%;border-collapse:collapse;font-size:10pt}
  th,td{border:1px solid #999;padding:5px 7px;text-align:left;vertical-align:top}
  th{background:#eee} td.n{text-align:right;white-space:nowrap}
  tr.total td{font-weight:bold;background:#f4f4f4}
  .warn{border:1px solid #b45309;background:#fffbeb;padding:8px 10px;font-size:10pt;margin:.2in 0}
  .report{white-space:pre-wrap;line-height:1.6;font-size:10.5pt}
  @media print{ body{padding:0} }
</style></head><body>
<h1>Local Support Grant &mdash; Release Time</h1>
<div class="meta">
  <strong>Local association:</strong> Bulkley Valley Teachers Union &nbsp; <strong>#:</strong> 54<br>
  <strong>School year:</strong> <?= $e($label) ?><br>
  <strong>FTE as of September 30:</strong> <?= $e($fte) ?> &nbsp;
  <strong>Release days allowed:</strong> <?= (int)$yr['day_cap'] ?><br>
  <strong>Total release days claimed:</strong> <?= $e(lpRtDays($dayTotal)) ?> &nbsp;
  <strong>Total grant request:</strong> $<?= $e(number_format($costTotal, 2)) ?>
</div>

<?php if ($noCost): ?>
<div class="warn">
  Cost is not yet recorded for invoice<?= count($noCost) === 1 ? '' : 's' ?>
  <?= $e(implode(', ', $noCost)) ?>. Those rows are blank below and the total excludes them.
</div>
<?php endif; ?>

<?php if ($mixed): ?>
<div class="warn">
  <strong>Check before sending.</strong>
  <?php $bits = []; foreach ($mixed as $m) $bits[] = $m['num'] . ' (' . lpRtDays($m['days']) . ' day' . ($m['days'] == 1 ? '' : 's') . ')'; ?>
  These invoices also cover release days the BVTU is not claiming &mdash;
  <?= $e(implode(', ', $bits)) ?>. The day counts below leave those days out, but the costs are
  as the district invoiced them and still include them. There is no per-day rate to subtract,
  so adjust the figure yourself rather than sending it as it stands.
</div>
<?php endif; ?>

<h2>Submitting for reimbursement</h2>
<table>
  <thead><tr>
    <th>Invoice number</th><th>Dates released</th><th>Name of released member</th>
    <th>Number of release days</th><th>Cost of release ($)</th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= $e($r['num']) ?></td>
      <td><?= $e($r['dates']) ?></td>
      <td><?= $e($r['names']) ?></td>
      <td class="n"><?= $e($r['days']) ?></td>
      <td class="n"><?= $r['cost'] === '' ? '<em>not recorded</em>' : '$' . $e($r['cost']) ?></td>
    </tr>
  <?php endforeach; ?>
    <tr class="total">
      <td colspan="3">Total grant request</td>
      <td class="n"><?= $e(lpRtDays($dayTotal)) ?> days</td>
      <td class="n">$<?= $e(number_format($costTotal, 2)) ?></td>
    </tr>
  </tbody>
</table>

<h2>Summary report</h2>
<?php if (trim((string)($yr['report'] ?? '')) !== ''): ?>
  <div class="report"><?= $e($yr['report'] ?? '') ?></div>
<?php else: ?>
  <p><em>No summary report written yet. The BCTF asks for a short account of the activities the
  released members carried out; add it on the Release Time page before sending.</em></p>
<?php endif; ?>
</body></html>
<?php
$html = ob_get_clean();

// ── ZIP ──────────────────────────────────────────────────────────────────────
if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('The server is missing PHP\'s zip extension, so the package cannot be built. '
       . 'The figures are all on the Release Time page in the meantime.');
}
$tmp = tempnam(sys_get_temp_dir(), 'rtzip');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    http_response_code(500);
    exit('Could not start the zip file. Try again; if it keeps failing, tell Claude.');
}
$zip->addFromString('reimbursement.csv',  $csvOut);
$zip->addFromString('reimbursement.html', $html);

$used = [];
foreach ($rows as $r) {
    if ($r['file'] === '') continue;
    $disk = LP_RT_DIR . basename($r['file']);
    if (!is_file($disk)) continue;
    $ext  = strtolower(pathinfo($r['file'], PATHINFO_EXTENSION)) ?: 'pdf';
    $stem = 'Invoice-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $r['num']);
    // Two invoices can carry the same number; suffix rather than overwrite one
    // with the other, which would send the BCTF the wrong document.
    $name = $stem . '.' . $ext;
    $i = 2;
    while (isset($used[$name])) { $name = $stem . '-' . $i . '.' . $ext; $i++; }
    $used[$name] = true;
    $zip->addFile($disk, $name);
}
$zip->close();

// lpYearLabel() uses an en dash, which has no business in a Content-Disposition
// header — browsers save the file under a mangled name. Reduce to plain ASCII.
$zipName = 'BVTU-ReleaseTime-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $label) . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
// tempnam() made this file at 0 bytes and PHP caches that stat, so filesize()
// can still report 0 after the zip is written — the browser then saves an
// empty download.
clearstatcache(true, $tmp);
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
unlink($tmp);
