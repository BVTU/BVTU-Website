<?php
/**
 * exp-dashboard.php — Member's personal expense portal home
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();

$expenses = expGetByMember($member['email']);

// Summary counts
$counts = ['pending' => 0, 'review' => 0, 'paid' => 0, 'rejected' => 0];
$totalPaid = 0.0;
foreach ($expenses as $exp) {
    if ($exp['status'] === 'draft') continue;
    if (in_array($exp['status'], ['pending', 'signer1_approved', 'signer2_approved'])) {
        if ($exp['status'] === 'pending') {
            $counts['pending']++;
        } else {
            $counts['review']++;
        }
    } elseif ($exp['status'] === 'paid') {
        $counts['paid']++;
        $totalPaid += (float)$exp['amount'];
    } elseif ($exp['status'] === 'rejected') {
        $counts['rejected']++;
    }
}

$statusLabels = [
    'draft'            => ['label' => 'Draft',                   'color' => '#6b7280', 'bg' => '#f3f4f6'],
    'pending'          => ['label' => 'Awaiting Treasurer',      'color' => '#92400e', 'bg' => '#fffbeb'],
    'signer1_approved' => ['label' => 'Awaiting 2nd Signature',  'color' => '#1e40af', 'bg' => '#eff6ff'],
    'signer2_approved' => ['label' => 'Payment Authorized',      'color' => '#166534', 'bg' => '#f0fdf4'],
    'paid'             => ['label' => 'Paid',                    'color' => '#166534', 'bg' => '#f0fdf4'],
    'rejected'         => ['label' => 'Rejected',                'color' => '#991b1b', 'bg' => '#fef2f2'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Expenses — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    /* Summary strip */
    .summary-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px,1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .summary-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.1rem 1.25rem; text-align: center; }
    .summary-card .num { font-size: 1.6rem; font-weight: 900; color: var(--primary); line-height: 1; margin-bottom: .2rem; }
    .summary-card .lbl { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); }

    /* Expenses table */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; }
    .table-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; border-bottom: 1px solid var(--gray-200); flex-wrap: wrap; gap: .75rem; }
    .table-toolbar h2 { font-size: .95rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .6rem 1rem; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); }
    td { padding: .75rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .ref-code { font-size: .78rem; font-weight: 700; font-family: monospace; color: var(--gray-500); }
    .desc-cell { max-width: 220px; }
    .desc-trunc { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; display: block; }
    .status-badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .22rem .65rem; border-radius: 100px; white-space: nowrap; }
    .action-links a { font-size: .8rem; color: var(--primary); text-decoration: none; margin-right: .6rem; }
    .action-links a:hover { text-decoration: underline; }

    /* Payment authorized highlight */
    .row-authorized td { background: #f0fdf4; }
    .row-authorized:last-child td { border-bottom: none; }

    /* Paid flash */
    .payment-info { font-size: .78rem; color: #166534; margin-top: .2rem; }

    /* Empty state */
    .empty-state { text-align: center; padding: 3.5rem 2rem; }
    .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }
    .empty-state h3 { font-size: 1.05rem; font-weight: 800; color: var(--gray-700); margin: 0 0 .5rem; }
    .empty-state p { font-size: .9rem; color: var(--gray-500); margin: 0 0 1.25rem; }

    @media (max-width: 700px) {
      .col-cat, .col-status { display: none; }
    }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>My Expenses</h1>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
      <a href="dashboard.php" class="back-link" style="display:flex;align-items:center;">&#x2190; Dashboard</a>
      <a href="exp-submit.php" class="btn btn-primary" style="padding:.5rem 1rem;font-size:.9rem;">+ Submit New Expense</a>
    </div>
  </div>

  <!-- Summary strip -->
  <div class="summary-strip">
    <div class="summary-card">
      <div class="num"><?= $counts['pending'] ?></div>
      <div class="lbl">Pending Review</div>
    </div>
    <div class="summary-card">
      <div class="num"><?= $counts['review'] ?></div>
      <div class="lbl">Under Review</div>
    </div>
    <div class="summary-card">
      <div class="num"><?= $counts['paid'] ?></div>
      <div class="lbl">Paid</div>
    </div>
    <div class="summary-card">
      <div class="num"><?= $counts['rejected'] ?></div>
      <div class="lbl">Rejected</div>
    </div>
    <div class="summary-card">
      <div class="num">$<?= number_format($totalPaid, 2) ?></div>
      <div class="lbl">Total Received</div>
    </div>
  </div>

  <!-- Expenses table -->
  <?php
  $visibleExpenses = array_filter($expenses, function($e) { return $e['status'] !== 'draft'; });
  ?>

  <?php if (empty($visibleExpenses)): ?>
  <div class="table-wrap">
    <div class="empty-state">
      <div class="icon">&#x1F4B8;</div>
      <h3>No expense submissions yet</h3>
      <p>Submit your first expense and the Treasurer will review it.</p>
      <a href="exp-submit.php" class="btn btn-primary" style="padding:.6rem 1.25rem;font-size:.9rem;">Submit Your First Expense</a>
    </div>
  </div>

  <?php else: ?>
  <div class="table-wrap">
    <div class="table-toolbar">
      <h2>Expense History</h2>
    </div>
    <table>
      <thead>
        <tr>
          <th>Ref</th>
          <th>Date</th>
          <th class="col-cat">Category</th>
          <th>Amount</th>
          <th class="desc-cell">Description</th>
          <th class="col-status">Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($visibleExpenses as $exp):
          $sl    = $statusLabels[$exp['status']] ?? ['label' => $exp['status'], 'color' => '#555', 'bg' => '#eee'];
          $isAuth = $exp['status'] === 'signer2_approved';
        ?>
        <tr<?= $isAuth ? ' class="row-authorized"' : '' ?>>
          <td><span class="ref-code"><?= htmlspecialchars($exp['ref_code']) ?></span></td>
          <td><?= htmlspecialchars($exp['expense_date']) ?></td>
          <td class="col-cat"><?= ucfirst(htmlspecialchars($exp['category'])) ?></td>
          <td><strong>$<?= number_format((float)$exp['amount'], 2) ?></strong></td>
          <td class="desc-cell">
            <span class="desc-trunc" title="<?= htmlspecialchars($exp['description']) ?>">
              <?= htmlspecialchars($exp['description']) ?>
            </span>
          </td>
          <td class="col-status">
            <span class="status-badge" style="background:<?= $sl['bg'] ?>;color:<?= $sl['color'] ?>;">
              <?= htmlspecialchars($sl['label']) ?>
            </span>
            <?php if ($isAuth): ?>
            <div class="payment-info">&#x1F4B8; E-transfer coming</div>
            <?php endif; ?>
          </td>
          <td class="action-links">
            <a href="exp-view.php?id=<?= (int)$exp['id'] ?>">View</a>
            <?php if ($exp['status'] === 'paid'): ?>
            <a href="exp-receipt-print.php?id=<?= (int)$exp['id'] ?>">&#x1F9FE; Receipt</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
