<?php
/**
 * treasurer-ledger.php — Unified payment ledger for the Treasurer
 *
 * Pulls paid records from all three expense systems (single-item member
 * expenses, multi-item batch claims, LP vouchers) into one sortable,
 * groupable view so payments can be reconciled against cheques / e-transfers.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';
require_once __DIR__ . '/lp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();
expBatchEnsureTables();
expEnsurePaymentColumns();
lpEnsureTables();
lpEnsureApprovalColumns();

if (!expIsTreasurer($member['email']) && !expIsAdmin($member['email'])) {
    header('Location: exp-dashboard.php');
    exit;
}

// ── Filters ───────────────────────────────────────────────────────────────────
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');
$groupBy  = ($_GET['group'] ?? 'payment') === 'flat' ? 'flat' : 'payment';
$sortBy   = $_GET['sort'] ?? 'payment_date_desc';

// ── Fetch from all three sources ──────────────────────────────────────────────
$db = getDB();
$rows = [];

// Helper to add date filter clauses
function _dateWhere(string $col, string $from, string $to): array {
    $w = []; $p = [];
    if ($from) { $w[] = "{$col} >= ?"; $p[] = $from; }
    if ($to)   { $w[] = "{$col} <= ?"; $p[] = $to;   }
    return [$w, $p];
}

// 1. Single-item member expenses (exp_expenses, status=paid)
{
    $where = ["status = 'paid'"];
    $params = [];
    [$dw, $dp] = _dateWhere('payment_date', $dateFrom, $dateTo);
    $where  = array_merge($where, $dw);
    $params = array_merge($params, $dp);
    $sql = "SELECT id, user_name, user_email, amount, expense_date,
                   payment_date, paid_at, payment_ref, payment_note,
                   receipt_path, description, category
            FROM exp_expenses WHERE " . implode(' AND ', $where);
    $s = $db->prepare($sql);
    $s->execute($params);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'source'       => 'member_expense',
            'source_id'    => $r['id'],
            'member_name'  => $r['user_name'],
            'member_email' => $r['user_email'],
            'amount'       => (float)$r['amount'],
            'expense_date' => $r['expense_date'],
            'payment_date' => $r['payment_date'] ?: substr($r['paid_at'] ?? '', 0, 10),
            'paid_at'      => $r['paid_at'],
            'payment_ref'  => $r['payment_ref'] ?? '',
            'payment_note' => $r['payment_note'] ?? '',
            'description'  => $r['description'],
            'detail_url'   => 'exp-view.php?id=' . $r['id'],
            'receipt_url'  => $r['receipt_path'] ? 'exp-receipt.php?f=' . urlencode($r['receipt_path']) : '',
            'receipt_count'=> $r['receipt_path'] ? 1 : 0,
            'item_count'   => 1,
            'type_label'   => 'Member Expense',
        ];
    }
}

// 2. Multi-item batch claims (exp_batches, status=paid)
{
    $where = ["b.status = 'paid'"];
    $params = [];
    [$dw, $dp] = _dateWhere('b.payment_date', $dateFrom, $dateTo);
    $where  = array_merge($where, $dw);
    $params = array_merge($params, $dp);
    $sql = "SELECT b.id, b.user_name, b.user_email, b.ref_code, b.title,
                   b.payment_date, b.paid_at, b.payment_ref, b.payment_note,
                   COALESCE(SUM(i.travel_amt + i.meals + i.gifts + i.misc + i.office + i.phone), 0) AS total,
                   COUNT(i.id) AS item_count,
                   SUM(CASE WHEN i.receipt_path IS NOT NULL AND i.receipt_path != '' THEN 1 ELSE 0 END) AS receipt_count,
                   MIN(i.expense_date) AS first_date
            FROM exp_batches b
            LEFT JOIN exp_batch_items i ON i.batch_id = b.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY b.id";
    $s = $db->prepare($sql);
    $s->execute($params);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rows[] = [
            'source'       => 'batch_claim',
            'source_id'    => $r['id'],
            'member_name'  => $r['user_name'],
            'member_email' => $r['user_email'],
            'amount'       => (float)$r['total'],
            'expense_date' => $r['first_date'],
            'payment_date' => $r['payment_date'] ?: substr($r['paid_at'] ?? '', 0, 10),
            'paid_at'      => $r['paid_at'],
            'payment_ref'  => $r['payment_ref'] ?? '',
            'payment_note' => $r['payment_note'] ?? '',
            'description'  => $r['title'] ?: $r['ref_code'],
            'detail_url'   => 'exp-claim-view.php?id=' . $r['id'],
            'receipt_url'  => '',
            'receipt_count'=> (int)$r['receipt_count'],
            'item_count'   => (int)$r['item_count'],
            'type_label'   => 'Batch Claim',
        ];
    }
}

// 3. LP vouchers (lp_vouchers, status=paid)
{
    $where = ["v.status = 'paid'"];
    $params = [];
    [$dw, $dp] = _dateWhere('v.payment_date', $dateFrom, $dateTo);
    $where  = array_merge($where, $dw);
    $params = array_merge($params, $dp);
    $sql = "SELECT v.id, v.submitted_by AS member_name, v.submitted_by_email AS member_email,
                   v.name AS title, v.voucher_number,
                   v.payment_date, v.paid_at, v.payment_ref, v.payment_note,
                   COALESCE(SUM(e.travel_amt + e.meals + e.gifts + e.misc + e.office + e.phone), 0) AS total,
                   COUNT(e.id) AS item_count,
                   SUM(CASE WHEN e.receipt_path IS NOT NULL AND e.receipt_path != '' THEN 1 ELSE 0 END) AS receipt_count,
                   MIN(e.expense_date) AS first_date
            FROM lp_vouchers v
            LEFT JOIN lp_expenses e ON e.voucher_id = v.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY v.id";
    $s = $db->prepare($sql);
    $s->execute($params);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $num = $r['voucher_number'] ? ' #' . $r['voucher_number'] : '';
        $rows[] = [
            'source'       => 'lp_voucher',
            'source_id'    => $r['id'],
            'member_name'  => $r['member_name'],
            'member_email' => $r['member_email'],
            'amount'       => (float)$r['total'],
            'expense_date' => $r['first_date'],
            'payment_date' => $r['payment_date'] ?: substr($r['paid_at'] ?? '', 0, 10),
            'paid_at'      => $r['paid_at'],
            'payment_ref'  => $r['payment_ref'] ?? '',
            'payment_note' => $r['payment_note'] ?? '',
            'description'  => $r['title'] . $num,
            'detail_url'   => 'lp-voucher-view.php?id=' . $r['id'],
            'receipt_url'  => '',
            'receipt_count'=> (int)$r['receipt_count'],
            'item_count'   => (int)$r['item_count'],
            'type_label'   => 'LP Voucher',
        ];
    }
}

// ── Sort ──────────────────────────────────────────────────────────────────────
usort($rows, function ($a, $b) use ($sortBy) {
    switch ($sortBy) {
        case 'payment_date_asc':  return strcmp($a['payment_date'] ?? '', $b['payment_date'] ?? '');
        case 'member':            return strcmp($a['member_name'], $b['member_name']);
        case 'amount_desc':       return $b['amount'] <=> $a['amount'];
        case 'amount_asc':        return $a['amount'] <=> $b['amount'];
        default:                  return strcmp($b['payment_date'] ?? '', $a['payment_date'] ?? '');
    }
});

// ── Group by payment ref / date ───────────────────────────────────────────────
function groupRows(array $rows): array {
    $groups = [];
    foreach ($rows as $r) {
        $key = $r['payment_ref']
            ? 'ref:' . $r['payment_ref']
            : 'date:' . ($r['payment_date'] ?? 'unknown');
        $groups[$key]['label']  = $r['payment_ref'] ?: ($r['payment_date'] ? date('F j, Y', strtotime($r['payment_date'])) : 'Unknown date');
        $groups[$key]['is_ref'] = (bool)$r['payment_ref'];
        $groups[$key]['date']   = $r['payment_date'] ?? '';
        $groups[$key]['rows'][] = $r;
        $groups[$key]['total']  = ($groups[$key]['total'] ?? 0) + $r['amount'];
    }
    return array_values($groups);
}

$groups       = $groupBy === 'payment' ? groupRows($rows) : [];
$grandTotal   = array_sum(array_column($rows, 'amount'));
$totalPayments = count($groupBy === 'payment' ? $groups : $rows);

$sourceLabels = [
    'member_expense' => ['label' => 'Member Expense', 'color' => '#1e40af', 'bg' => '#eff6ff'],
    'batch_claim'    => ['label' => 'Batch Claim',    'color' => '#166534', 'bg' => '#f0fdf4'],
    'lp_voucher'     => ['label' => 'LP Voucher',     'color' => '#92400e', 'bg' => '#fffbeb'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Ledger — BVTU Treasurer</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0 0 .2rem; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    /* Hero stats */
    .ledger-hero { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .hero-stat { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: .85rem 1.25rem; flex: 1; min-width: 140px; }
    .hero-stat .lbl { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-500); margin-bottom: .2rem; }
    .hero-stat .val { font-size: 1.5rem; font-weight: 900; color: var(--primary); }

    /* Toolbar */
    .toolbar { display: flex; gap: .75rem; margin-bottom: 1.25rem; flex-wrap: wrap; align-items: flex-end; }
    .toolbar label { font-size: .75rem; font-weight: 700; color: var(--gray-500); display: block; margin-bottom: .2rem; }
    .toolbar input[type=date], .toolbar select { border: 1px solid var(--gray-300); border-radius: 7px; padding: .4rem .6rem; font-size: .83rem; font-family: inherit; }
    .toolbar select { cursor: pointer; }
    .toggle-group { display: flex; border: 1px solid var(--gray-300); border-radius: 7px; overflow: hidden; }
    .toggle-group a { padding: .4rem .85rem; font-size: .82rem; font-weight: 700; text-decoration: none; color: var(--gray-600); background: #fff; }
    .toggle-group a.active { background: var(--primary); color: #fff; }
    .toolbar-apply { background: var(--primary); color: #fff; border: none; border-radius: 7px; padding: .4rem .9rem; font-size: .83rem; font-weight: 700; cursor: pointer; }

    /* Source badge */
    .src-badge { display: inline-block; font-size: .65rem; font-weight: 800; border-radius: 5px; padding: .15rem .4rem; white-space: nowrap; }

    /* Group view */
    .payment-group { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; margin-bottom: 1rem; overflow: hidden; }
    .group-head { display: flex; align-items: center; justify-content: space-between; padding: .75rem 1.1rem; cursor: pointer; user-select: none; gap: 1rem; flex-wrap: wrap; }
    .group-head:hover { background: #f8fafc; }
    .group-ref { font-size: .95rem; font-weight: 800; color: var(--gray-800); }
    .group-ref.is-ref { color: var(--primary); }
    .group-meta { font-size: .78rem; color: var(--gray-500); margin-top: .1rem; }
    .group-right { display: flex; align-items: center; gap: 1.25rem; }
    .group-total { font-size: 1.15rem; font-weight: 900; color: var(--primary); }
    .group-chevron { font-size: .85rem; color: var(--gray-400); transition: transform .15s; }
    .group-chevron.open { transform: rotate(90deg); }
    .group-body { display: none; border-top: 1px solid var(--gray-100); }
    .group-body.open { display: block; }

    /* Expense rows */
    .exp-row { display: flex; align-items: center; gap: .75rem; padding: .65rem 1.1rem; border-bottom: 1px solid var(--gray-50); flex-wrap: wrap; }
    .exp-row:last-child { border-bottom: none; }
    .exp-row:hover { background: #fafafa; }
    .exp-row-left { flex: 1; min-width: 180px; }
    .exp-member { font-size: .88rem; font-weight: 700; color: var(--gray-800); }
    .exp-desc { font-size: .77rem; color: var(--gray-500); margin-top: .1rem; }
    .exp-row-mid { font-size: .78rem; color: var(--gray-500); text-align: center; min-width: 90px; }
    .exp-amount { font-size: 1rem; font-weight: 800; color: var(--primary); text-align: right; min-width: 80px; }
    .exp-actions { display: flex; align-items: center; gap: .6rem; flex-shrink: 0; }
    .exp-actions a { font-size: .78rem; color: var(--primary); text-decoration: none; white-space: nowrap; }
    .exp-actions a:hover { text-decoration: underline; }
    .receipt-icon { font-size: .8rem; background: #f0fdf4; color: #166534; border-radius: 5px; padding: .15rem .45rem; font-weight: 700; white-space: nowrap; }

    /* Flat table */
    .flat-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid var(--gray-200); font-size: .83rem; }
    .flat-table th { background: #1a2e1a; color: #fff; padding: .6rem .9rem; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
    .flat-table th.r { text-align: right; }
    .flat-table th.sortable { cursor: pointer; }
    .flat-table th.sortable:hover { background: #234523; }
    .flat-table td { padding: .55rem .9rem; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .flat-table tr:last-child td { border-bottom: none; }
    .flat-table tr:hover td { background: #fafafa; }
    .flat-table td.r { text-align: right; font-weight: 700; color: var(--primary); }
    .flat-table td.amt { font-weight: 800; color: var(--primary); text-align: right; }
    .flat-table a { color: var(--primary); text-decoration: none; }
    .flat-table a:hover { text-decoration: underline; }

    .empty-state { text-align: center; padding: 3rem; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-400); }
  </style>
</head>
<body>
<div class="wrap">

  <div class="portal-header">
    <div>
      <h1>Payment Ledger</h1>
      <div style="font-size:.82rem;color:var(--gray-500);">All paid expenses across all systems</div>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <a href="exp-treasurer.php" class="back-link">← Expense Review</a>
      <a href="exp-claim-review.php" class="back-link">← Claim Review</a>
      <a href="lp-review.php" class="back-link">← LP Review</a>
    </div>
  </div>

  <!-- Hero stats -->
  <div class="ledger-hero">
    <div class="hero-stat">
      <div class="lbl">Total Paid</div>
      <div class="val">$<?= number_format($grandTotal, 2) ?></div>
    </div>
    <div class="hero-stat">
      <div class="lbl"><?= $groupBy === 'payment' ? 'Payments' : 'Records' ?></div>
      <div class="val"><?= count($groupBy === 'payment' ? $groups : $rows) ?></div>
    </div>
    <div class="hero-stat">
      <div class="lbl">Member Expenses</div>
      <div class="val">$<?= number_format(array_sum(array_column(array_filter($rows, fn($r) => $r['source'] === 'member_expense'), 'amount')), 2) ?></div>
    </div>
    <div class="hero-stat">
      <div class="lbl">Batch Claims</div>
      <div class="val">$<?= number_format(array_sum(array_column(array_filter($rows, fn($r) => $r['source'] === 'batch_claim'), 'amount')), 2) ?></div>
    </div>
    <div class="hero-stat">
      <div class="lbl">LP Vouchers</div>
      <div class="val">$<?= number_format(array_sum(array_column(array_filter($rows, fn($r) => $r['source'] === 'lp_voucher'), 'amount')), 2) ?></div>
    </div>
  </div>

  <!-- Toolbar -->
  <form method="GET" action="treasurer-ledger.php">
    <input type="hidden" name="group" value="<?= htmlspecialchars($groupBy) ?>">
    <input type="hidden" name="sort"  value="<?= htmlspecialchars($sortBy) ?>">
    <div class="toolbar">
      <div>
        <label>From</label>
        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div>
        <label>To</label>
        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div>
        <label>Sort by</label>
        <select name="sort" onchange="this.form.submit()">
          <option value="payment_date_desc" <?= $sortBy === 'payment_date_desc' ? 'selected' : '' ?>>Pay date (newest first)</option>
          <option value="payment_date_asc"  <?= $sortBy === 'payment_date_asc'  ? 'selected' : '' ?>>Pay date (oldest first)</option>
          <option value="member"            <?= $sortBy === 'member'            ? 'selected' : '' ?>>Member name</option>
          <option value="amount_desc"       <?= $sortBy === 'amount_desc'       ? 'selected' : '' ?>>Amount (high to low)</option>
          <option value="amount_asc"        <?= $sortBy === 'amount_asc'        ? 'selected' : '' ?>>Amount (low to high)</option>
        </select>
      </div>
      <div>
        <label>View</label>
        <div class="toggle-group">
          <a href="?group=payment&sort=<?= urlencode($sortBy) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
             class="<?= $groupBy === 'payment' ? 'active' : '' ?>">Group by payment</a>
          <a href="?group=flat&sort=<?= urlencode($sortBy) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"
             class="<?= $groupBy === 'flat' ? 'active' : '' ?>">Flat list</a>
        </div>
      </div>
      <button type="submit" class="toolbar-apply">Apply</button>
    </div>
  </form>

  <?php if (empty($rows)): ?>
  <div class="empty-state">No paid expenses found<?= $dateFrom || $dateTo ? ' for the selected date range' : '' ?>.</div>

  <?php elseif ($groupBy === 'payment'): ?>
  <!-- ── Grouped by cheque / e-transfer ──────────────────────────────────── -->
  <?php foreach ($groups as $gi => $g): ?>
  <div class="payment-group">
    <div class="group-head" onclick="toggleGroup(<?= $gi ?>)">
      <div>
        <div class="group-ref <?= $g['is_ref'] ? 'is-ref' : '' ?>">
          <?= $g['is_ref'] ? '🔖 ' : '📅 ' ?><?= htmlspecialchars($g['label']) ?>
        </div>
        <div class="group-meta">
          <?= count($g['rows']) ?> payment<?= count($g['rows']) != 1 ? 's' : '' ?>
          <?php if ($g['is_ref'] && $g['date']): ?>
          &nbsp;·&nbsp;<?= date('M j, Y', strtotime($g['date'])) ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="group-right">
        <span class="group-total">$<?= number_format($g['total'], 2) ?></span>
        <span class="group-chevron" id="chev-<?= $gi ?>">&#x25B6;</span>
      </div>
    </div>
    <div class="group-body" id="gbody-<?= $gi ?>">
      <?php foreach ($g['rows'] as $r): ?>
      <?php $sl = $sourceLabels[$r['source']]; ?>
      <div class="exp-row">
        <div class="exp-row-left">
          <div class="exp-member">
            <?= htmlspecialchars($r['member_name']) ?>
            <span class="src-badge" style="background:<?= $sl['bg'] ?>;color:<?= $sl['color'] ?>;"><?= $sl['label'] ?></span>
          </div>
          <div class="exp-desc">
            <?= htmlspecialchars($r['description']) ?>
            <?php if ($r['expense_date']): ?>
            &nbsp;·&nbsp;incurred <?= date('M j, Y', strtotime($r['expense_date'])) ?>
            <?php endif; ?>
            <?php if ($r['item_count'] > 1): ?>
            &nbsp;·&nbsp;<?= $r['item_count'] ?> items
            <?php endif; ?>
          </div>
        </div>
        <div class="exp-actions">
          <?php if ($r['receipt_count'] > 0): ?>
          <?php if ($r['receipt_url']): ?>
          <a href="<?= htmlspecialchars($r['receipt_url']) ?>" target="_blank" class="receipt-icon">📄 Receipt</a>
          <?php else: ?>
          <span class="receipt-icon">📄 <?= $r['receipt_count'] ?> receipt<?= $r['receipt_count'] != 1 ? 's' : '' ?></span>
          <?php endif; ?>
          <?php endif; ?>
          <a href="<?= htmlspecialchars($r['detail_url']) ?>" target="_blank">View details ↗</a>
        </div>
        <div class="exp-amount">$<?= number_format($r['amount'], 2) ?></div>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;justify-content:flex-end;padding:.6rem 1.1rem;background:#f8fafc;font-size:.82rem;font-weight:800;color:var(--primary);">
        Subtotal: $<?= number_format($g['total'], 2) ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php else: ?>
  <!-- ── Flat sortable table ─────────────────────────────────────────────── -->
  <table class="flat-table">
    <thead>
      <tr>
        <th>Member</th>
        <th>Type</th>
        <th>Description</th>
        <th>Expense date</th>
        <th>Pay date</th>
        <th>Reference</th>
        <th>Receipts</th>
        <th class="r">Amount</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <?php $sl = $sourceLabels[$r['source']]; ?>
      <tr>
        <td>
          <div style="font-weight:700;"><?= htmlspecialchars($r['member_name']) ?></div>
          <div style="font-size:.73rem;color:var(--gray-500);"><?= htmlspecialchars($r['member_email']) ?></div>
        </td>
        <td><span class="src-badge" style="background:<?= $sl['bg'] ?>;color:<?= $sl['color'] ?>;"><?= $sl['label'] ?></span></td>
        <td><?= htmlspecialchars($r['description']) ?></td>
        <td><?= $r['expense_date'] ? date('M j, Y', strtotime($r['expense_date'])) : '—' ?></td>
        <td><?= $r['payment_date'] ? date('M j, Y', strtotime($r['payment_date'])) : '—' ?></td>
        <td style="font-family:monospace;font-size:.8rem;"><?= htmlspecialchars($r['payment_ref'] ?: '—') ?></td>
        <td>
          <?php if ($r['receipt_count'] > 0): ?>
          <?php if ($r['receipt_url']): ?>
          <a href="<?= htmlspecialchars($r['receipt_url']) ?>" target="_blank" class="receipt-icon">📄 View</a>
          <?php else: ?>
          <span class="receipt-icon">📄 <?= $r['receipt_count'] ?></span>
          <?php endif; ?>
          <?php else: ?>
          <span style="color:var(--gray-300);">—</span>
          <?php endif; ?>
        </td>
        <td class="amt">$<?= number_format($r['amount'], 2) ?></td>
        <td><a href="<?= htmlspecialchars($r['detail_url']) ?>" target="_blank">Details ↗</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="7" style="padding:.6rem .9rem;font-size:.8rem;font-weight:700;color:var(--gray-500);text-align:right;">TOTAL</td>
        <td class="amt" style="padding:.6rem .9rem;">$<?= number_format($grandTotal, 2) ?></td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

</div>

<script>
function toggleGroup(i) {
    const body = document.getElementById('gbody-' + i);
    const chev = document.getElementById('chev-' + i);
    body.classList.toggle('open');
    chev.classList.toggle('open');
}
// Open the first group by default
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('gbody-0')) toggleGroup(0);
});
</script>
</body>
</html>
