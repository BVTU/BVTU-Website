<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/lp-db.php';
requireLogin();

$member = getMember();
lpEnsureTables();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: lp-dashboard.php'); exit; }

$voucher  = lpGetVoucher($id);
if (!$voucher) { header('Location: lp-dashboard.php'); exit; }

$isOwner = $voucher['submitted_by_email'] === $member['email'];
if (!$isOwner && !lpCanView($member['email'])) { header('Location: lp-dashboard.php'); exit; }

$expenses = lpGetExpenses($id);

// Export as CSV
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="voucher-' . ($voucher['voucher_number'] ?: $id) . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['BULKLEY VALLEY TEACHERS\' UNION — Expense Voucher']);
    fputcsv($out, ['Voucher:', $voucher['voucher_number'] ?: '—', 'Name:', $voucher['name']]);
    fputcsv($out, ['Submitted by:', $voucher['submitted_by'], 'Date:', date('Y-m-d', strtotime($voucher['created_at']))]);
    fputcsv($out, []);
    fputcsv($out, ['Date','Description','Travel km','Travel $','Meals $','Gifts $','Misc $','Office $','Phone $','Row Total','BCTF Grant','Budget Line']);
    $totals = array_fill_keys(['km','travel','meals','gifts','misc','office','phone','total'], 0);
    foreach ($expenses as $e) {
        $total = lpRowTotal($e);
        fputcsv($out, [
            $e['expense_date'],
            $e['description'],
            $e['travel_km'] ?: '',
            $e['travel_amt'] ? number_format($e['travel_amt'],2) : '',
            $e['meals']      ? number_format($e['meals'],2)      : '',
            $e['gifts']      ? number_format($e['gifts'],2)      : '',
            $e['misc']       ? number_format($e['misc'],2)       : '',
            $e['office']     ? number_format($e['office'],2)     : '',
            $e['phone']      ? number_format($e['phone'],2)      : '',
            number_format($total, 2),
            $e['grant_name'] ?? '',
            $e['budget_line_name'] ?? '',
        ]);
        $totals['km']     += (float)$e['travel_km'];
        $totals['travel'] += (float)$e['travel_amt'];
        $totals['meals']  += (float)$e['meals'];
        $totals['gifts']  += (float)$e['gifts'];
        $totals['misc']   += (float)$e['misc'];
        $totals['office'] += (float)$e['office'];
        $totals['phone']  += (float)$e['phone'];
        $totals['total']  += $total;
    }
    fputcsv($out, []);
    fputcsv($out, ['TOTALS','',
        number_format($totals['km'],1),
        number_format($totals['travel'],2),
        number_format($totals['meals'],2),
        number_format($totals['gifts'],2),
        number_format($totals['misc'],2),
        number_format($totals['office'],2),
        number_format($totals['phone'],2),
        number_format($totals['total'],2),'','']);
    fclose($out);
    exit;
}

// Compute totals
$totals = array_fill_keys(['km','travel','meals','gifts','misc','office','phone','total'], 0);
$grantSummary = [];
$blSummary    = [];
foreach ($expenses as $e) {
    $rowTotal = lpRowTotal($e);
    $totals['km']     += (float)$e['travel_km'];
    $totals['travel'] += (float)$e['travel_amt'];
    $totals['meals']  += (float)$e['meals'];
    $totals['gifts']  += (float)$e['gifts'];
    $totals['misc']   += (float)$e['misc'];
    $totals['office'] += (float)$e['office'];
    $totals['phone']  += (float)$e['phone'];
    $totals['total']  += $rowTotal;
    if ($e['grant_id']) {
        $k = $e['grant_name'] ?? 'Unknown';
        $grantSummary[$k] = ($grantSummary[$k] ?? 0) + $rowTotal;
    }
    if ($e['budget_line_id']) {
        $k = $e['budget_line_name'] ?? 'Unknown';
        $blSummary[$k] = ($blSummary[$k] ?? 0) + $rowTotal;
    }
}
arsort($grantSummary);
arsort($blSummary);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Voucher <?= htmlspecialchars($voucher['voucher_number'] ?: '#'.$id) ?> — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0 0 .2rem; }
    .portal-header .sub { font-size: .82rem; color: var(--gray-500); }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .action-bar { display: flex; gap: .6rem; flex-wrap: wrap; }

    /* Summary cards */
    .summary-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px,1fr)); gap: .85rem; margin-bottom: 1.5rem; }
    .sum-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: .9rem 1rem; }
    .sum-card .lbl { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-400); margin-bottom: .25rem; }
    .sum-card .val { font-size: 1.15rem; font-weight: 800; color: var(--primary); }
    .sum-card.total { background: var(--primary); border-color: var(--primary); }
    .sum-card.total .lbl { color: rgba(255,255,255,.7); }
    .sum-card.total .val { color: #fff; font-size: 1.5rem; }

    /* Expense table */
    .table-wrap { overflow-x: auto; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; margin-bottom: 1.5rem; }
    table.etable { width: 100%; border-collapse: collapse; min-width: 900px; font-size: .83rem; }
    .etable thead th { background: #1a2e1a; color: #fff; padding: .6rem .75rem; text-align: left; font-size: .71rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
    .etable thead th.r { text-align: right; }
    .etable tbody tr { border-bottom: 1px solid var(--gray-100); }
    .etable tbody tr:last-child { border-bottom: none; }
    .etable tbody tr:hover { background: #fafafa; }
    .etable td { padding: .55rem .75rem; vertical-align: middle; }
    .etable td.r { text-align: right; color: var(--gray-700); }
    .etable td.bold { font-weight: 700; color: var(--primary); }
    .etable tfoot td { background: #f0fdf4; font-weight: 700; padding: .65rem .75rem; }
    .etable tfoot td.r { text-align: right; color: var(--primary); }

    .receipt-btn { display: inline-flex; align-items: center; gap: .25rem; background: var(--accent); border: 1px solid #b8ddc5; color: var(--primary); font-size: .72rem; font-weight: 700; border-radius: 5px; padding: .2rem .5rem; text-decoration: none; cursor: pointer; }
    .receipt-btn:hover { background: #b8ddc5; }
    .tag { display: inline-block; font-size: .7rem; font-weight: 600; border-radius: 4px; padding: .15rem .45rem; }
    .tag-grant { background: #eff6ff; color: #1e40af; }
    .tag-bl    { background: #f5f3ff; color: #6d28d9; }

    /* Breakdown panels */
    .breakdown-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
    @media (max-width: 640px) { .breakdown-grid { grid-template-columns: 1fr; } }
    .breakdown-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.25rem; }
    .breakdown-card h3 { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--primary); margin: 0 0 .85rem; }
    .breakdown-row { display: flex; justify-content: space-between; align-items: center; padding: .35rem 0; border-bottom: 1px solid var(--gray-100); font-size: .83rem; }
    .breakdown-row:last-child { border-bottom: none; }
    .breakdown-row .name { color: var(--gray-700); }
    .breakdown-row .amt  { font-weight: 700; color: var(--primary); }

    /* Print */
    @media print {
      body { background: #fff; font-size: 10pt; }
      .wrap { padding: 0; max-width: 100%; }
      .no-print { display: none !important; }
      .table-wrap, .breakdown-card { border: 1px solid #ccc; box-shadow: none; }
      .etable thead th { background: #1a2e1a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }

    /* Lightbox */
    .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 9999; align-items: center; justify-content: center; }
    .lightbox.open { display: flex; }
    .lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
    .lightbox-close { position: absolute; top: 1rem; right: 1.5rem; color: #fff; font-size: 2rem; cursor: pointer; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="portal-header">
    <div>
      <h1>
        <?php if ($voucher['voucher_number']): ?>Voucher #<?= htmlspecialchars($voucher['voucher_number']) ?> — <?php endif; ?>
        <?= htmlspecialchars($voucher['name']) ?>
      </h1>
      <div class="sub">
        Submitted by <?= htmlspecialchars($voucher['submitted_by']) ?>
        · <?= date('F j, Y', strtotime($voucher['created_at'])) ?>
        <?php if ($voucher['notes']): ?>· <?= htmlspecialchars($voucher['notes']) ?><?php endif; ?>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:.5rem;align-items:flex-end;">
      <div class="action-bar no-print">
        <?php if ($isOwner || prodIsExec($member['email'])): ?>
        <a href="lp-voucher-edit.php?id=<?= $id ?>" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;">✏ Edit</a>
        <?php endif; ?>
        <a href="?id=<?= $id ?>&export=csv" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;">⬇ CSV</a>
        <button onclick="window.print()" class="btn btn-primary" style="padding:.45rem .85rem;font-size:.83rem;">🖨 Print</button>
      </div>
      <a class="back-link no-print" href="lp-dashboard.php">← LP Expenses</a>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="summary-row">
    <?php if ($totals['km'] > 0): ?>
    <div class="sum-card">
      <div class="lbl">Travel km</div>
      <div class="val"><?= number_format($totals['km'], 1) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($totals['travel'] > 0): ?>
    <div class="sum-card"><div class="lbl">Travel $</div><div class="val">$<?= number_format($totals['travel'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['meals'] > 0): ?>
    <div class="sum-card"><div class="lbl">Meals</div><div class="val">$<?= number_format($totals['meals'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['gifts'] > 0): ?>
    <div class="sum-card"><div class="lbl">Gifts</div><div class="val">$<?= number_format($totals['gifts'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['misc'] > 0): ?>
    <div class="sum-card"><div class="lbl">Misc</div><div class="val">$<?= number_format($totals['misc'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['office'] > 0): ?>
    <div class="sum-card"><div class="lbl">Office</div><div class="val">$<?= number_format($totals['office'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['phone'] > 0): ?>
    <div class="sum-card"><div class="lbl">Phone</div><div class="val">$<?= number_format($totals['phone'],2) ?></div></div>
    <?php endif; ?>
    <div class="sum-card total">
      <div class="lbl">Total</div>
      <div class="val">$<?= number_format($totals['total'],2) ?></div>
    </div>
  </div>

  <!-- Expense rows -->
  <div class="table-wrap">
    <table class="etable">
      <thead>
        <tr>
          <th>Receipt</th>
          <th>Date</th>
          <th>Description</th>
          <th class="r">km</th>
          <th class="r">Travel $</th>
          <th class="r">Meals $</th>
          <th class="r">Gifts $</th>
          <th class="r">Misc $</th>
          <th class="r">Office $</th>
          <th class="r">Phone $</th>
          <th class="r">Total</th>
          <th>Grant / Budget Line</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($expenses as $e):
          $rowTotal = lpRowTotal($e);
        ?>
        <tr>
          <td>
            <?php if ($e['receipt_path']): ?>
            <a class="receipt-btn" onclick="openLightbox('lp-receipt.php?f=<?= urlencode($e['receipt_path']) ?>')" href="#">
              📄 View
            </a>
            <?php else: ?>
            <span style="color:var(--gray-300);font-size:.75rem;">—</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap;"><?= $e['expense_date'] ? date('M j, Y', strtotime($e['expense_date'])) : '—' ?></td>
          <td><?= htmlspecialchars($e['description'] ?? '') ?></td>
          <td class="r"><?= $e['travel_km'] > 0 ? number_format($e['travel_km'],1) : '' ?></td>
          <td class="r"><?= $e['travel_amt'] > 0 ? '$'.number_format($e['travel_amt'],2) : '' ?></td>
          <td class="r"><?= $e['meals']      > 0 ? '$'.number_format($e['meals'],2)      : '' ?></td>
          <td class="r"><?= $e['gifts']      > 0 ? '$'.number_format($e['gifts'],2)      : '' ?></td>
          <td class="r"><?= $e['misc']       > 0 ? '$'.number_format($e['misc'],2)        : '' ?></td>
          <td class="r"><?= $e['office']     > 0 ? '$'.number_format($e['office'],2)     : '' ?></td>
          <td class="r"><?= $e['phone']      > 0 ? '$'.number_format($e['phone'],2)       : '' ?></td>
          <td class="r bold">$<?= number_format($rowTotal,2) ?></td>
          <td>
            <?php if ($e['grant_name']): ?>
            <span class="tag tag-grant"><?= htmlspecialchars($e['grant_name']) ?></span>
            <?php endif; ?>
            <?php if ($e['budget_line_name']): ?>
            <br><span class="tag tag-bl"><?= htmlspecialchars($e['budget_line_name']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="font-weight:800;text-transform:uppercase;font-size:.72rem;letter-spacing:.05em;color:var(--gray-500);">Totals</td>
          <td class="r"><?= $totals['km']     > 0 ? number_format($totals['km'],1)     : '' ?></td>
          <td class="r"><?= $totals['travel'] > 0 ? '$'.number_format($totals['travel'],2) : '' ?></td>
          <td class="r"><?= $totals['meals']  > 0 ? '$'.number_format($totals['meals'],2)  : '' ?></td>
          <td class="r"><?= $totals['gifts']  > 0 ? '$'.number_format($totals['gifts'],2)  : '' ?></td>
          <td class="r"><?= $totals['misc']   > 0 ? '$'.number_format($totals['misc'],2)   : '' ?></td>
          <td class="r"><?= $totals['office'] > 0 ? '$'.number_format($totals['office'],2) : '' ?></td>
          <td class="r"><?= $totals['phone']  > 0 ? '$'.number_format($totals['phone'],2)  : '' ?></td>
          <td class="r" style="font-size:.92rem;">$<?= number_format($totals['total'],2) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Breakdown panels -->
  <?php if ($grantSummary || $blSummary): ?>
  <div class="breakdown-grid">
    <?php if ($grantSummary): ?>
    <div class="breakdown-card">
      <h3>By BCTF Grant</h3>
      <?php foreach ($grantSummary as $name => $amt): ?>
      <div class="breakdown-row">
        <span class="name"><?= htmlspecialchars($name) ?></span>
        <span class="amt">$<?= number_format($amt, 2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($blSummary): ?>
    <div class="breakdown-card">
      <h3>By Budget Line</h3>
      <?php foreach ($blSummary as $name => $amt): ?>
      <div class="breakdown-row">
        <span class="name"><?= htmlspecialchars($name) ?></span>
        <span class="amt">$<?= number_format($amt, 2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Signature block for printing -->
  <div style="margin-top:2rem;display:grid;grid-template-columns:1fr 1fr;gap:3rem;padding-top:2rem;border-top:1px solid var(--gray-200);">
    <div>
      <div style="font-size:.75rem;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;">Submitted by</div>
      <div style="font-size:.92rem;font-weight:600;"><?= htmlspecialchars($voucher['submitted_by']) ?></div>
      <div style="margin-top:2.5rem;border-top:1px solid #ccc;padding-top:.3rem;font-size:.75rem;color:var(--gray-400);">Signature &amp; Date</div>
    </div>
    <div>
      <div style="font-size:.75rem;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;">Approved by Treasurer</div>
      <div style="font-size:.92rem;font-weight:600;">&nbsp;</div>
      <div style="margin-top:2.5rem;border-top:1px solid #ccc;padding-top:.3rem;font-size:.75rem;color:var(--gray-400);">Signature &amp; Date</div>
    </div>
  </div>

</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <span class="lightbox-close">×</span>
  <img src="" id="lightboxImg" alt="Receipt">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lightboxImg').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>
