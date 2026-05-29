<?php
/**
 * exp-admin.php — Admin view: all expenses, aggregate stats, status override
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();

if (!expIsAdmin($member['email'])) {
    header('Location: exp-dashboard.php');
    exit;
}

$notice = $_GET['notice'] ?? '';
$error  = $_GET['error']  ?? '';

// Filters
$statusF     = trim($_GET['status']       ?? '');
$dateFrom    = trim($_GET['date_from']    ?? '');
$dateTo      = trim($_GET['date_to']      ?? '');
$emailSearch = trim($_GET['email_search'] ?? '');

$filters = [];
if ($statusF && $statusF !== 'all') $filters['status']       = $statusF;
if ($dateFrom)                       $filters['date_from']    = $dateFrom;
if ($dateTo)                         $filters['date_to']      = $dateTo;
if ($emailSearch)                    $filters['email_search'] = $emailSearch;

// Exclude drafts unless explicitly requested
if (empty($filters['status'])) {
    // Build the query manually to exclude drafts
}

$expenses = expGetAll($filters, 500, 0);
// Filter out drafts unless status filter is explicitly 'draft'
if ($statusF !== 'draft') {
    $expenses = array_filter($expenses, function($e) { return $e['status'] !== 'draft'; });
    $expenses = array_values($expenses);
}

// Aggregate stats (all time, no filters)
$allExpenses = expGetAll([], 5000, 0);
$stats = [
    'total'            => 0,
    'pending'          => 0,
    'signer1_approved' => 0,
    'signer2_approved' => 0,
    'paid'             => 0,
    'rejected'         => 0,
    'total_paid'       => 0.0,
];
foreach ($allExpenses as $e) {
    if ($e['status'] === 'draft') continue;
    $stats['total']++;
    if (isset($stats[$e['status']])) {
        $stats[$e['status']]++;
    }
    if ($e['status'] === 'paid') {
        $stats['total_paid'] += (float)$e['amount'];
    }
}

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];

$statusBadges = [
    'pending'          => ['label' => 'Awaiting Treasurer',     'color' => '#92400e', 'bg' => '#fffbeb'],
    'signer1_approved' => ['label' => 'Awaiting 2nd Sig.',      'color' => '#1e40af', 'bg' => '#eff6ff'],
    'signer2_approved' => ['label' => 'Payment Authorized',     'color' => '#166534', 'bg' => '#f0fdf4'],
    'paid'             => ['label' => 'Paid',                   'color' => '#166534', 'bg' => '#f0fdf4'],
    'rejected'         => ['label' => 'Rejected',               'color' => '#991b1b', 'bg' => '#fef2f2'],
    'draft'            => ['label' => 'Draft',                  'color' => '#6b7280', 'bg' => '#f3f4f6'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expense Admin — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .notice-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box  { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }

    /* Stats strip */
    .stats-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px,1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .stat-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: .9rem 1rem; text-align: center; }
    .stat-card .num { font-size: 1.5rem; font-weight: 900; color: var(--primary); line-height: 1; }
    .stat-card .lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); margin-top: .2rem; }

    /* Filter bar */
    .filter-bar { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; gap: .75rem; flex-wrap: wrap; align-items: flex-end; }
    .filter-bar .f-field { display: flex; flex-direction: column; gap: .25rem; }
    .filter-bar label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
    .filter-bar input, .filter-bar select { border: 1px solid var(--gray-300); border-radius: 6px; padding: .45rem .65rem; font-size: .85rem; font-family: inherit; }

    /* Table */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .55rem .9rem; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); }
    td { padding: .65rem .9rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .ref-code { font-family: monospace; font-size: .8rem; color: var(--gray-500); }
    .status-badge { display: inline-block; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .2rem .55rem; border-radius: 100px; white-space: nowrap; }
    .empty-row td { text-align: center; color: var(--gray-400); padding: 2.5rem; }

    /* Override modal */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 9000; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: #fff; border-radius: 12px; padding: 1.75rem; width: 100%; max-width: 440px; box-shadow: 0 8px 40px rgba(0,0,0,.2); }
    .modal-box h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1rem; }
    .modal-field { margin-bottom: .9rem; }
    .modal-field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .modal-field select, .modal-field textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .55rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .modal-field textarea { resize: vertical; min-height: 70px; }
    .modal-actions { display: flex; gap: .75rem; margin-top: 1rem; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>Expense Admin</h1>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <a href="exp-manage.php" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">Manage Roles</a>
      <a href="exp-payments.php" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">Payment Records</a>
    </div>
  </div>

  <?php if ($notice): ?>
  <div class="notice-box">&#x2713; <?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- Stats strip -->
  <div class="stats-strip">
    <div class="stat-card"><div class="num"><?= $stats['total'] ?></div><div class="lbl">Total</div></div>
    <div class="stat-card"><div class="num"><?= $stats['pending'] ?></div><div class="lbl">Pending</div></div>
    <div class="stat-card"><div class="num"><?= $stats['signer1_approved'] ?></div><div class="lbl">Needs 2nd Sig</div></div>
    <div class="stat-card"><div class="num"><?= $stats['signer2_approved'] ?></div><div class="lbl">Ready to Pay</div></div>
    <div class="stat-card"><div class="num"><?= $stats['paid'] ?></div><div class="lbl">Paid</div></div>
    <div class="stat-card"><div class="num"><?= $stats['rejected'] ?></div><div class="lbl">Rejected</div></div>
    <div class="stat-card"><div class="num">$<?= number_format($stats['total_paid'], 0) ?></div><div class="lbl">Total Paid</div></div>
  </div>

  <!-- Filter bar -->
  <form method="GET" class="filter-bar">
    <div class="f-field">
      <label>Status</label>
      <select name="status">
        <option value="all" <?= $statusF === '' || $statusF === 'all' ? 'selected' : '' ?>>All statuses</option>
        <option value="pending"          <?= $statusF === 'pending'          ? 'selected' : '' ?>>Pending</option>
        <option value="signer1_approved" <?= $statusF === 'signer1_approved' ? 'selected' : '' ?>>Awaiting 2nd Sig</option>
        <option value="signer2_approved" <?= $statusF === 'signer2_approved' ? 'selected' : '' ?>>Ready to Pay</option>
        <option value="paid"             <?= $statusF === 'paid'             ? 'selected' : '' ?>>Paid</option>
        <option value="rejected"         <?= $statusF === 'rejected'         ? 'selected' : '' ?>>Rejected</option>
        <option value="draft"            <?= $statusF === 'draft'            ? 'selected' : '' ?>>Draft</option>
      </select>
    </div>
    <div class="f-field">
      <label>From</label>
      <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="f-field">
      <label>To</label>
      <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="f-field">
      <label>Member email</label>
      <input type="text" name="email_search" value="<?= htmlspecialchars($emailSearch) ?>" placeholder="Search&hellip;" style="width:160px;">
    </div>
    <div style="display:flex;gap:.5rem;align-items:flex-end;">
      <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem;font-size:.85rem;">Filter</button>
      <a href="exp-admin.php" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">Reset</a>
    </div>
  </form>

  <!-- Expenses table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Ref</th>
          <th>Date</th>
          <th>Member</th>
          <th>Category</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$expenses): ?>
        <tr class="empty-row"><td colspan="7">No expenses found.</td></tr>
        <?php endif; ?>
        <?php foreach ($expenses as $exp):
          $sb = $statusBadges[$exp['status']] ?? ['label' => $exp['status'], 'color' => '#555', 'bg' => '#eee'];
        ?>
        <tr>
          <td><span class="ref-code"><?= htmlspecialchars($exp['ref_code']) ?></span></td>
          <td><?= htmlspecialchars($exp['expense_date']) ?></td>
          <td>
            <strong style="font-size:.85rem;"><?= htmlspecialchars($exp['user_name']) ?></strong>
            <br><span style="font-size:.75rem;color:var(--gray-400);"><?= htmlspecialchars($exp['user_email']) ?></span>
          </td>
          <td><?= htmlspecialchars($catLabels[$exp['category']] ?? ucfirst($exp['category'])) ?></td>
          <td><strong>$<?= number_format((float)$exp['amount'], 2) ?></strong></td>
          <td>
            <span class="status-badge" style="background:<?= $sb['bg'] ?>;color:<?= $sb['color'] ?>;">
              <?= htmlspecialchars($sb['label']) ?>
            </span>
          </td>
          <td>
            <a href="exp-view.php?id=<?= (int)$exp['id'] ?>" style="font-size:.8rem;color:var(--primary);margin-right:.5rem;">View</a>
            <button type="button" class="btn btn-outline" style="padding:.2rem .6rem;font-size:.75rem;"
              onclick="openOverride(<?= (int)$exp['id'] ?>, '<?= htmlspecialchars($exp['ref_code']) ?>', '<?= $exp['status'] ?>')">
              Override
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Override modal -->
<div class="modal-overlay" id="overrideModal">
  <div class="modal-box">
    <h2>Override Status</h2>
    <p style="font-size:.85rem;color:var(--gray-500);margin:0 0 1rem;" id="overrideRef"></p>
    <form method="POST" action="exp-action.php">
      <input type="hidden" name="action"     value="admin_override">
      <input type="hidden" name="expense_id" id="overrideId" value="">
      <input type="hidden" name="redirect"   value="exp-admin.php">
      <div class="modal-field">
        <label>New Status</label>
        <select name="new_status" id="overrideStatus">
          <option value="pending">pending</option>
          <option value="signer1_approved">signer1_approved</option>
          <option value="signer2_approved">signer2_approved</option>
          <option value="paid">paid</option>
          <option value="rejected">rejected</option>
          <option value="draft">draft</option>
        </select>
      </div>
      <div class="modal-field">
        <label>Admin Note</label>
        <textarea name="note" placeholder="Reason for override&hellip;"></textarea>
      </div>
      <div class="modal-actions">
        <button type="submit" class="btn btn-primary" style="padding:.5rem 1rem;font-size:.88rem;">Apply Override</button>
        <button type="button" class="btn btn-outline" style="padding:.5rem 1rem;font-size:.88rem;" onclick="closeOverride()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openOverride(id, ref, currentStatus) {
    document.getElementById('overrideId').value      = id;
    document.getElementById('overrideRef').textContent = ref;
    var sel = document.getElementById('overrideStatus');
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === currentStatus) { sel.selectedIndex = i; break; }
    }
    document.getElementById('overrideModal').classList.add('open');
}
function closeOverride() {
    document.getElementById('overrideModal').classList.remove('open');
}
document.getElementById('overrideModal').addEventListener('click', function(e) {
    if (e.target === this) closeOverride();
});
</script>
</body>
</html>
