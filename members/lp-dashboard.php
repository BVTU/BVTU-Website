<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/lp-db.php';
requireLogin();

$member = getMember();
lpEnsureTables();

if (!lpCanView($member['email'])) {
    header('Location: dashboard.php'); exit;
}

$canCreate  = lpCanCreate($member['email']);
$vouchers   = lpGetVouchers();
$grantSum   = lpGrantSummary();
$budgetSum  = lpBudgetSummary();

$totalSpent = array_sum(array_column($grantSum, 'spent'));
$totalBudget = array_sum(array_column($grantSum, 'budget'));

// Filter budget lines to only those with activity or budget > 0
$activeBl = array_filter($budgetSum, fn($b) => $b['budget'] > 0 || $b['spent'] > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LP Expenses — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .section-title { font-size: .82rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-500); margin: 0 0 .85rem; }

    /* Hero */
    .hero { background: linear-gradient(135deg, var(--primary-dk) 0%, var(--primary) 100%); border-radius: 14px; padding: 1.75rem 2rem; color: #fff; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }
    .hero-label { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; opacity: .7; margin-bottom: .2rem; }
    .hero-amount { font-size: 2.5rem; font-weight: 900; line-height: 1; }
    .hero-sub { font-size: .8rem; opacity: .65; margin-top: .25rem; }
    .hero-stats { display: flex; gap: 2rem; flex-wrap: wrap; }
    .hero-stat .lbl { font-size: .73rem; opacity: .7; margin-bottom: .1rem; }
    .hero-stat .val { font-size: 1.2rem; font-weight: 800; }

    /* Grant cards */
    .grant-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap: .85rem; margin-bottom: 2rem; }
    .grant-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.15rem; }
    .grant-card .name { font-size: .82rem; font-weight: 700; color: var(--gray-800); margin-bottom: .6rem; line-height: 1.3; }
    .grant-card .bar-wrap { height: 6px; background: var(--gray-100); border-radius: 3px; overflow: hidden; margin-bottom: .5rem; }
    .grant-card .bar { height: 100%; border-radius: 3px; background: var(--primary); }
    .grant-card .bar.warn { background: #f59e0b; }
    .grant-card .bar.over { background: #dc2626; }
    .grant-card .nums { display: flex; justify-content: space-between; font-size: .75rem; }
    .grant-card .spent-lbl { color: var(--gray-500); }
    .grant-card .rem-lbl { font-weight: 700; color: var(--primary); }
    .grant-card .rem-lbl.warn { color: #d97706; }
    .grant-card .rem-lbl.over  { color: #dc2626; }

    /* Voucher list */
    .voucher-list { display: flex; flex-direction: column; gap: .75rem; margin-bottom: 2rem; }
    .voucher-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; text-decoration: none; color: var(--text); transition: border-color .15s; }
    .voucher-card:hover { border-color: var(--primary); }
    .voucher-info .title { font-weight: 700; color: var(--gray-800); font-size: .93rem; }
    .voucher-info .meta  { font-size: .77rem; color: var(--gray-500); margin-top: .15rem; }
    .voucher-right { display: flex; align-items: center; gap: 1rem; flex-shrink: 0; }
    .voucher-total { font-size: 1.1rem; font-weight: 800; color: var(--primary); }
    .status-badge { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .2rem .55rem; border-radius: 100px; }
    .status-draft { background: #f1f5f9; color: #64748b; }
    .status-submitted { background: #f0fdf4; color: #166534; }

    /* Budget table */
    .budget-table { width: 100%; border-collapse: collapse; font-size: .82rem; background: #fff; border-radius: 10px; overflow: hidden; border: 1px solid var(--gray-200); }
    .budget-table thead th { background: #1a2e1a; color: #fff; padding: .55rem .85rem; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .budget-table thead th.r { text-align: right; }
    .budget-table tbody tr { border-bottom: 1px solid var(--gray-100); }
    .budget-table tbody tr:last-child { border-bottom: none; }
    .budget-table tbody tr:hover { background: #fafafa; }
    .budget-table td { padding: .5rem .85rem; }
    .budget-table td.r { text-align: right; }
    .budget-table td.spent { font-weight: 700; color: var(--gray-700); }
    .budget-table td.rem.pos { color: #166534; font-weight: 600; }
    .budget-table td.rem.neg { color: #dc2626; font-weight: 700; }

    .new-voucher-btn { display: inline-flex; align-items: center; gap: .4rem; background: var(--primary); color: #fff; border-radius: 9px; padding: .6rem 1.15rem; font-size: .9rem; font-weight: 700; text-decoration: none; }
    .new-voucher-btn:hover { background: var(--primary-dk); color: #fff; }

    .empty-state { text-align: center; padding: 2.5rem; color: var(--gray-400); background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="portal-header">
    <div>
      <h1>LP Expense Tracker</h1>
      <div style="font-size:.82rem;color:var(--gray-500);">
        <?= date('Y') ?>–<?= date('y', strtotime('+1 year')) ?> school year
      </div>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <?php if ($canCreate): ?>
      <a href="lp-voucher-new.php" class="new-voucher-btn">+ New Voucher</a>
      <?php endif; ?>
      <a class="back-link" href="dashboard.php">← Dashboard</a>
    </div>
  </div>

  <!-- Hero -->
  <div class="hero">
    <div>
      <div class="hero-label">Total BCTF Grant Spending</div>
      <div class="hero-amount">$<?= number_format($totalSpent, 2) ?></div>
      <div class="hero-sub">of $<?= number_format($totalBudget, 2) ?> total grant budget</div>
    </div>
    <div class="hero-stats">
      <div class="hero-stat">
        <div class="lbl">Vouchers</div>
        <div class="val"><?= count($vouchers) ?></div>
      </div>
      <div class="hero-stat">
        <div class="lbl">Remaining</div>
        <div class="val">$<?= number_format($totalBudget - $totalSpent, 2) ?></div>
      </div>
    </div>
  </div>

  <!-- Grant summary -->
  <p class="section-title">BCTF Grants</p>
  <div class="grant-grid">
    <?php foreach ($grantSum as $g):
      $pct   = $g['pct'];
      $class = $pct >= 100 ? 'over' : ($pct >= 80 ? 'warn' : '');
    ?>
    <div class="grant-card">
      <div class="name"><?= htmlspecialchars($g['name']) ?></div>
      <div class="bar-wrap">
        <div class="bar <?= $class ?>" style="width:<?= min(100, $pct) ?>%;"></div>
      </div>
      <div class="nums">
        <span class="spent-lbl">$<?= number_format($g['spent'],2) ?> spent</span>
        <span class="rem-lbl <?= $class ?>">$<?= number_format(max(0,$g['remaining']),2) ?> left</span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Vouchers -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.85rem;flex-wrap:wrap;gap:.5rem;">
    <p class="section-title" style="margin:0;">Expense Vouchers</p>
  </div>

  <?php if (!$vouchers): ?>
  <div class="empty-state">
    No vouchers yet.
    <?php if ($canCreate): ?>
    <br><a href="lp-voucher-new.php" style="color:var(--primary);font-weight:700;">Create your first voucher →</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="voucher-list">
    <?php foreach ($vouchers as $v): ?>
    <a href="lp-voucher-view.php?id=<?= $v['id'] ?>" class="voucher-card">
      <div class="voucher-info">
        <div class="title">
          <?php if ($v['voucher_number']): ?>#<?= htmlspecialchars($v['voucher_number']) ?> — <?php endif; ?>
          <?= htmlspecialchars($v['name']) ?>
        </div>
        <div class="meta">
          <?= htmlspecialchars($v['submitted_by']) ?>
          · <?= $v['expense_count'] ?> expense<?= $v['expense_count'] != 1 ? 's' : '' ?>
          · <?= date('M j, Y', strtotime($v['created_at'])) ?>
        </div>
      </div>
      <div class="voucher-right">
        <span class="voucher-total">$<?= number_format($v['total_amount'], 2) ?></span>
        <span class="status-badge status-<?= $v['status'] ?>"><?= ucfirst($v['status']) ?></span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Budget line summary -->
  <?php if ($activeBl): ?>
  <p class="section-title" style="margin-top:.5rem;">BVTU Budget Lines — Spending to Date</p>
  <table class="budget-table">
    <thead>
      <tr>
        <th>Budget Line</th>
        <th class="r">Budget</th>
        <th class="r">Spent</th>
        <th class="r">Remaining</th>
        <th class="r">% Used</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($activeBl as $b):
        $rem   = $b['budget'] - $b['spent'];
        $remCls = $rem < 0 ? 'neg' : 'pos';
      ?>
      <tr>
        <td><?= htmlspecialchars($b['name']) ?></td>
        <td class="r"><?= $b['budget'] > 0 ? '$'.number_format($b['budget'],2) : '—' ?></td>
        <td class="r spent"><?= $b['spent'] > 0 ? '$'.number_format($b['spent'],2) : '—' ?></td>
        <td class="r rem <?= $remCls ?>"><?= $b['budget'] > 0 ? '$'.number_format($rem,2) : '—' ?></td>
        <td class="r"><?= $b['pct'] > 0 ? $b['pct'].'%' : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

</div>
</body>
</html>
