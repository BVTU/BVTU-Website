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

$totalSpent  = array_sum(array_column($grantSum, 'spent'));
$totalBudget = array_sum(array_column($grantSum, 'budget'));

// Filter budget lines to only those with activity or budget > 0
$activeBl = array_filter($budgetSum, fn($b) => $b['budget'] > 0 || $b['spent'] > 0);

// Pre-load transactions for each grant that has spending (for the drill-down modal)
$grantExpenses = [];
foreach ($grantSum as $g) {
    if ($g['spent'] > 0) {
        $grantExpenses[$g['id']] = lpGetExpensesByGrant($g['id']);
    }
}
$grantExpensesJson = json_encode($grantExpenses);
$grantSumJson      = json_encode(array_values($grantSum));
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
    .grant-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.15rem; transition: border-color .15s, box-shadow .15s; }
    .grant-card.has-spend { cursor: pointer; }
    .grant-card.has-spend:hover { border-color: var(--primary); box-shadow: 0 2px 12px rgba(26,107,53,.1); }
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
    .grant-card .view-link { display: block; margin-top: .6rem; font-size: .72rem; font-weight: 700; color: var(--primary); text-align: right; opacity: .7; }
    .grant-card.has-spend:hover .view-link { opacity: 1; }

    /* Grant drill-down modal */
    .gmodal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 8000; align-items: flex-end; justify-content: center; }
    .gmodal-backdrop.open { display: flex; }
    @media (min-width: 640px) { .gmodal-backdrop { align-items: center; } }
    .gmodal { background: #fff; border-radius: 16px 16px 0 0; width: 100%; max-width: 820px; max-height: 88vh; display: flex; flex-direction: column; box-shadow: 0 -4px 40px rgba(0,0,0,.18); }
    @media (min-width: 640px) { .gmodal { border-radius: 16px; max-height: 80vh; } }
    .gmodal-head { padding: 1.1rem 1.4rem .9rem; border-bottom: 1px solid var(--gray-100); display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-shrink: 0; }
    .gmodal-head h2 { font-size: 1.05rem; font-weight: 800; color: var(--gray-800); margin: 0 0 .15rem; }
    .gmodal-head .sub { font-size: .78rem; color: var(--gray-500); }
    .gmodal-close { background: none; border: none; font-size: 1.5rem; line-height: 1; cursor: pointer; color: var(--gray-400); padding: .1rem .3rem; flex-shrink: 0; }
    .gmodal-close:hover { color: var(--gray-700); }
    .gmodal-body { overflow-y: auto; flex: 1; padding: 0; }
    .gmodal-foot { padding: .85rem 1.4rem; border-top: 1px solid var(--gray-100); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; background: #f8fafc; border-radius: 0 0 16px 16px; }
    .gmodal-foot .total-line { font-size: .85rem; color: var(--gray-500); }
    .gmodal-foot .total-amt  { font-size: 1.2rem; font-weight: 900; color: var(--primary); }
    .gtable { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 500px; }
    .gtable thead th { background: #f1f5f9; color: var(--gray-600); padding: .5rem .9rem; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; position: sticky; top: 0; }
    .gtable thead th.r { text-align: right; }
    .gtable tbody tr { border-bottom: 1px solid var(--gray-100); }
    .gtable tbody tr:last-child { border-bottom: none; }
    .gtable tbody tr:hover { background: #f8fafc; }
    .gtable td { padding: .55rem .9rem; vertical-align: middle; }
    .gtable td.r { text-align: right; color: var(--gray-700); }
    .gtable td.bold { font-weight: 700; color: var(--primary); }
    .gtable .voucher-link { color: var(--primary); font-weight: 600; font-size: .78rem; text-decoration: none; white-space: nowrap; }
    .gtable .voucher-link:hover { text-decoration: underline; }
    .gmodal-empty { padding: 2.5rem; text-align: center; color: var(--gray-400); font-size: .88rem; }

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
      $pct      = $g['pct'];
      $class    = $pct >= 100 ? 'over' : ($pct >= 80 ? 'warn' : '');
      $hasSpend = $g['spent'] > 0;
    ?>
    <div class="grant-card <?= $hasSpend ? 'has-spend' : '' ?>"
         <?= $hasSpend ? 'onclick="openGrantModal('.$g['id'].')" title="Click to view transactions"' : '' ?>>
      <div class="name"><?= htmlspecialchars($g['name']) ?></div>
      <div class="bar-wrap">
        <div class="bar <?= $class ?>" style="width:<?= min(100, $pct) ?>%;"></div>
      </div>
      <div class="nums">
        <span class="spent-lbl">$<?= number_format($g['spent'],2) ?> spent</span>
        <span class="rem-lbl <?= $class ?>">$<?= number_format(max(0,$g['remaining']),2) ?> left</span>
      </div>
      <?php if ($hasSpend): ?>
      <span class="view-link">View transactions →</span>
      <?php endif; ?>
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

<!-- Grant drill-down modal -->
<div class="gmodal-backdrop" id="gmodal-backdrop" onclick="closeGrantModal(event)">
  <div class="gmodal" onclick="event.stopPropagation()">
    <div class="gmodal-head">
      <div>
        <h2 id="gmodal-title">Grant</h2>
        <div class="sub" id="gmodal-sub"></div>
      </div>
      <button class="gmodal-close" onclick="closeGrantModal()">&times;</button>
    </div>
    <div class="gmodal-body">
      <div style="overflow-x:auto;">
        <table class="gtable">
          <thead>
            <tr>
              <th>Date</th>
              <th>Voucher</th>
              <th>Description</th>
              <th class="r">km</th>
              <th class="r">Travel $</th>
              <th class="r">Meals $</th>
              <th class="r">Gifts $</th>
              <th class="r">Misc $</th>
              <th class="r">Office $</th>
              <th class="r">Phone $</th>
              <th class="r">Total</th>
            </tr>
          </thead>
          <tbody id="gmodal-rows"></tbody>
        </table>
        <div id="gmodal-empty" class="gmodal-empty" style="display:none;">No submitted expenses for this grant yet.</div>
      </div>
    </div>
    <div class="gmodal-foot">
      <span class="total-line" id="gmodal-count"></span>
      <span class="total-amt" id="gmodal-total"></span>
    </div>
  </div>
</div>

<script>
const GRANT_EXPENSES = <?= $grantExpensesJson ?>;
const GRANT_META     = <?= $grantSumJson ?>;

function fmt2(n) { return '$' + parseFloat(n||0).toFixed(2); }
function fmtDate(d) {
    if (!d) return '—';
    const p = d.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return months[parseInt(p[1])-1] + ' ' + parseInt(p[2]) + ', ' + p[0];
}

function openGrantModal(grantId) {
    const meta = GRANT_META.find(g => g.id == grantId);
    const rows = GRANT_EXPENSES[grantId] || [];

    document.getElementById('gmodal-title').textContent = meta ? meta.name : 'Grant';
    document.getElementById('gmodal-sub').textContent   = meta
        ? '$' + parseFloat(meta.spent).toFixed(2) + ' spent of $' + parseFloat(meta.budget).toFixed(2) + ' budget'
        : '';

    const tbody  = document.getElementById('gmodal-rows');
    const empty  = document.getElementById('gmodal-empty');
    tbody.innerHTML = '';

    if (!rows.length) {
        empty.style.display = 'block';
        document.getElementById('gmodal-count').textContent = '';
        document.getElementById('gmodal-total').textContent = '';
    } else {
        empty.style.display = 'none';
        let grandTotal = 0;
        rows.forEach(e => {
            const rowTotal = (parseFloat(e.travel_amt)||0) + (parseFloat(e.meals)||0)
                           + (parseFloat(e.gifts)||0)     + (parseFloat(e.misc)||0)
                           + (parseFloat(e.office)||0)    + (parseFloat(e.phone)||0);
            grandTotal += rowTotal;

            const vNum = e.voucher_number ? '#' + e.voucher_number + ' — ' : '';
            const vLabel = vNum + e.voucher_name;

            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td style="white-space:nowrap;">' + fmtDate(e.expense_date) + '</td>' +
                '<td><a class="voucher-link" href="lp-voucher-view.php?id=' + e.voucher_id + '" target="_blank">' +
                    escH(vLabel) + ' ↗</a></td>' +
                '<td>' + escH(e.description || '—') + '</td>' +
                '<td class="r">' + (parseFloat(e.travel_km) > 0 ? parseFloat(e.travel_km).toFixed(1) : '') + '</td>' +
                '<td class="r">' + (parseFloat(e.travel_amt) > 0 ? fmt2(e.travel_amt) : '') + '</td>' +
                '<td class="r">' + (parseFloat(e.meals)      > 0 ? fmt2(e.meals)      : '') + '</td>' +
                '<td class="r">' + (parseFloat(e.gifts)      > 0 ? fmt2(e.gifts)      : '') + '</td>' +
                '<td class="r">' + (parseFloat(e.misc)       > 0 ? fmt2(e.misc)       : '') + '</td>' +
                '<td class="r">' + (parseFloat(e.office)     > 0 ? fmt2(e.office)     : '') + '</td>' +
                '<td class="r">' + (parseFloat(e.phone)      > 0 ? fmt2(e.phone)      : '') + '</td>' +
                '<td class="r bold">' + fmt2(rowTotal) + '</td>';
            tbody.appendChild(tr);
        });

        document.getElementById('gmodal-count').textContent = rows.length + ' transaction' + (rows.length !== 1 ? 's' : '');
        document.getElementById('gmodal-total').textContent = fmt2(grandTotal);
    }

    document.getElementById('gmodal-backdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeGrantModal(e) {
    if (e && e.target !== document.getElementById('gmodal-backdrop')) return;
    document.getElementById('gmodal-backdrop').classList.remove('open');
    document.body.style.overflow = '';
}

function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.getElementById('gmodal-backdrop').classList.remove('open'); document.body.style.overflow = ''; } });
</script>
</body>
</html>
