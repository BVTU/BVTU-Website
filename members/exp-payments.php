<?php
/**
 * exp-payments.php — Payment records dashboard (Treasurer / Admin only)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();

if (!expIsTreasurer($member['email']) && !expIsAdmin($member['email'])) {
    header('Location: exp-dashboard.php');
    exit;
}

// Filters
$dateFrom    = trim($_GET['date_from']   ?? '');
$dateTo      = trim($_GET['date_to']     ?? '');
$categoryF   = trim($_GET['category']   ?? '');
$emailSearch = trim($_GET['email_search'] ?? '');

$filters = ['status' => 'paid'];
if ($dateFrom)    $filters['date_from']    = $dateFrom;
if ($dateTo)      $filters['date_to']      = $dateTo;
if ($categoryF)   $filters['category']     = $categoryF;
if ($emailSearch) $filters['email_search'] = $emailSearch;

$payments = expGetAll($filters, 500, 0);

// Aggregate totals
$totalFiltered = array_sum(array_column($payments, 'amount'));

// YTD total
$ytdFilters = ['status' => 'paid', 'date_from' => date('Y-01-01')];
$ytdPayments = expGetAll($ytdFilters, 2000, 0);
$totalYTD    = array_sum(array_column($ytdPayments, 'amount'));

// All-time total
$allFilters  = ['status' => 'paid'];
$allPayments = expGetAll($allFilters, 2000, 0);
$totalAll    = array_sum(array_column($allPayments, 'amount'));

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];

// Build CSV export URL with current filters
$exportParams = http_build_query([
    'date_from'    => $dateFrom,
    'date_to'      => $dateTo,
    'category'     => $categoryF,
    'email_search' => $emailSearch,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Records — BVTU Expenses</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    /* Summary strip */
    .summary-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px,1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .summary-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.25rem; text-align: center; }
    .summary-card .num { font-size: 1.5rem; font-weight: 900; color: var(--primary); line-height: 1; margin-bottom: .2rem; }
    .summary-card .lbl { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); }

    /* Filter bar */
    .filter-bar { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; gap: .75rem; flex-wrap: wrap; align-items: flex-end; }
    .filter-bar .f-field { display: flex; flex-direction: column; gap: .25rem; }
    .filter-bar label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
    .filter-bar input, .filter-bar select { border: 1px solid var(--gray-300); border-radius: 6px; padding: .45rem .65rem; font-size: .85rem; font-family: inherit; }
    .filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: var(--primary); }
    .filter-actions { display: flex; gap: .5rem; align-items: flex-end; }

    /* Table */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem; }
    .table-toolbar { display: flex; align-items: center; justify-content: space-between; padding: .9rem 1.25rem; border-bottom: 1px solid var(--gray-200); flex-wrap: wrap; gap: .75rem; }
    .table-toolbar h2 { font-size: .95rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .55rem .9rem; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); }
    td { padding: .65rem .9rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tfoot td { font-weight: 700; background: #f8f9fa; border-top: 2px solid var(--gray-200); }
    .ref-code { font-family: monospace; font-size: .8rem; color: var(--gray-500); }
    .empty-row td { text-align: center; color: var(--gray-400); padding: 2.5rem; }

    /* Print styles */
    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
      .portal-wrap { max-width: 100%; padding: 0; }
      .table-wrap { border: none; border-radius: 0; box-shadow: none; }
      .print-header { display: block !important; }
    }
    .print-header { display: none; margin-bottom: 1.5rem; }
    .print-header h1 { font-size: 1.2rem; margin: 0 0 .25rem; }
    .print-header p { font-size: .85rem; color: #555; margin: 0; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header no-print">
    <h1>Payment Records</h1>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
      <a class="back-link" href="exp-treasurer.php">&#x2190; Review Queue</a>
      <button onclick="window.print()" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">&#x1F5A8; Print</button>
    </div>
  </div>

  <!-- Print-only header -->
  <div class="print-header">
    <h1>BVTU Expense Payment Records</h1>
    <p>Bulkley Valley Teachers' Union</p>
    <?php if ($dateFrom || $dateTo): ?>
    <p>Period: <?= $dateFrom ?: 'All time' ?> to <?= $dateTo ?: 'present' ?></p>
    <?php endif; ?>
    <p>Generated: <?= date('F j, Y g:i a') ?></p>
  </div>

  <!-- Summary strip -->
  <div class="summary-strip">
    <div class="summary-card">
      <div class="num">$<?= number_format($totalYTD, 2) ?></div>
      <div class="lbl">Total Paid YTD</div>
    </div>
    <div class="summary-card">
      <div class="num">$<?= number_format($totalAll, 2) ?></div>
      <div class="lbl">All-Time Total</div>
    </div>
    <div class="summary-card">
      <div class="num"><?= count($allPayments) ?></div>
      <div class="lbl">Total Claims</div>
    </div>
  </div>

  <!-- Filter bar -->
  <form method="GET" class="filter-bar no-print">
    <div class="f-field">
      <label>From</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="f-field">
      <label>To</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="f-field">
      <label>Category</label>
      <select name="category">
        <option value="">All categories</option>
        <?php foreach ($catLabels as $val => $label): ?>
        <option value="<?= $val ?>" <?= $categoryF === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="f-field">
      <label>Member email</label>
      <input type="text" name="email_search" value="<?= htmlspecialchars($emailSearch) ?>" placeholder="Search email&hellip;" style="width:180px;">
    </div>
    <div class="filter-actions">
      <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem;font-size:.85rem;">Filter</button>
      <a href="exp-payments.php" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">Reset</a>
      <a href="exp-payments-export.php?<?= htmlspecialchars($exportParams) ?>" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">Export CSV</a>
    </div>
  </form>

  <!-- Payments table -->
  <div class="table-wrap">
    <div class="table-toolbar no-print">
      <h2>Payments (<?= count($payments) ?> records &mdash; $<?= number_format($totalFiltered, 2) ?>)</h2>
    </div>
    <table>
      <thead>
        <tr>
          <th>Ref</th>
          <th>Date Paid</th>
          <th>Member</th>
          <th>Email</th>
          <th>Category</th>
          <th>Amount</th>
          <th>Signer 1 (Treasurer)</th>
          <th>Signer 2</th>
          <th class="no-print">Receipt</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$payments): ?>
        <tr class="empty-row"><td colspan="9">No payment records found.</td></tr>
        <?php endif; ?>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><span class="ref-code"><?= htmlspecialchars($p['ref_code']) ?></span></td>
          <td><?= $p['paid_at'] ? date('Y-m-d', strtotime($p['paid_at'])) : '—' ?></td>
          <td><?= htmlspecialchars($p['user_name']) ?></td>
          <td style="font-size:.78rem;color:var(--gray-500);"><?= htmlspecialchars($p['user_email']) ?></td>
          <td><?= htmlspecialchars($catLabels[$p['category']] ?? ucfirst($p['category'])) ?></td>
          <td><strong>$<?= number_format((float)$p['amount'], 2) ?></strong></td>
          <td style="font-size:.78rem;">
            <?= htmlspecialchars($p['signer1_name'] ?: '—') ?>
            <?php if ($p['signer1_at']): ?>
            <span style="color:var(--gray-400);">&mdash; <?= date('Y-m-d', strtotime($p['signer1_at'])) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;">
            <?= htmlspecialchars($p['signer2_name'] ?: '—') ?>
            <?php if ($p['signer2_at']): ?>
            <span style="color:var(--gray-400);">&mdash; <?= date('Y-m-d', strtotime($p['signer2_at'])) ?></span>
            <?php endif; ?>
          </td>
          <td class="no-print">
            <?php if ($p['receipt_path']): ?>
            <a href="exp-receipt-print.php?id=<?= (int)$p['id'] ?>" target="_blank"
               style="font-size:.8rem;color:var(--primary);">&#x1F4C4; View</a>
            <?php else: ?>
            <span style="font-size:.78rem;color:var(--gray-400);">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($payments): ?>
      <tfoot>
        <tr>
          <td colspan="5" style="text-align:right;color:var(--gray-500);font-size:.82rem;">
            Total (<?= count($payments) ?> records)
          </td>
          <td colspan="4"><strong>$<?= number_format($totalFiltered, 2) ?></strong></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

</div>
</body>
</html>
