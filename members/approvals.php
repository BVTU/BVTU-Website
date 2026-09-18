<?php
/**
 * approvals.php — everything a signer needs, in one page.
 *
 * Both queues render here; the two sign in opposite orders:
 *   Member reimbursement — President signs, then Treasurer; either one pays.
 *   President's expenses — Treasurer signs, then VP; Treasurer pays.
 *                          (The President is the claimant and cannot sign.)
 *
 * One list per system rather than a section per stage: each card carries its own
 * status badge and only the buttons that person can actually press, so the stage
 * headings and their empty-state lines are unnecessary.
 *
 * Actions POST to exp-claim-action.php and lp-action.php, which own the workflow
 * rules; this page only renders.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';
require_once __DIR__ . '/lp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();
expBatchEnsureTables();
lpEnsureTables();
lpEnsureApprovalColumns();

$email = $member['email'];

if (!expCanReview($email) && !lpCanReview($email)) {
    header('Location: dashboard.php');
    exit;
}

$notice = $_GET['notice'] ?? '';
$error  = $_GET['error']  ?? '';

// Who may do what
$canSign1    = expIsEligibleSigner1($email);   // President — member claims
$canSign2    = expIsEligibleSigner2($email);   // Treasurer — member claims
$canPayClaim = expCanMarkPaid($email);         // either
$isTreasurer = lpCanSign1($email);             // Treasurer — vouchers
$isVP        = lpCanSign2($email);             // VP — vouchers

// Active items, newest stage first. Ordering the statuses puts anything needing
// this person nearer the top without a heading to say so.
$claimsActive = array_merge(
    expBatchGetAll('pending'),
    expBatchGetAll('signer1_approved'),
    expBatchGetAll('signer2_approved')
);
$claimsDone = array_merge(expBatchGetAll('paid'), expBatchGetAll('rejected'));

$lpActive = lpGetVouchersByStatuses(['submitted', 'treasurer_approved', 'vp_approved']);
$lpDone   = lpGetVouchersByStatuses(['paid', 'rejected']);

/** Does this claim need this viewer right now? Drives the highlight. */
function _claimNeedsMe(array $b, bool $s1, bool $s2, bool $pay): bool {
    if ($b['status'] === 'pending')           return $s1;
    if ($b['status'] === 'signer1_approved')  return $s2;
    if ($b['status'] === 'signer2_approved')  return $pay;
    return false;
}
function _lpNeedsMe(array $v, bool $treas, bool $vp): bool {
    if ($v['status'] === 'submitted')           return $treas;
    if ($v['status'] === 'treasurer_approved')  return $vp;
    if ($v['status'] === 'vp_approved')         return $treas;
    return false;
}

$waiting = 0;
foreach ($claimsActive as $b) if (_claimNeedsMe($b, $canSign1, $canSign2, $canPayClaim)) $waiting++;
foreach ($lpActive   as $v) if (_lpNeedsMe($v, $isTreasurer, $isVP)) $waiting++;

$catLabels = [
    'meals' => 'Meals', 'travel' => 'Travel', 'supplies' => 'Supplies',
    'conference' => 'Conference / Workshop', 'accommodation' => 'Accommodation',
    'other' => 'Other',
];

function _badge(string $status): string {
    $map = [
        'pending'            => ['#fffbeb', '#d97706', 'Awaiting President'],
        'signer1_approved'   => ['#eff6ff', '#1e40af', 'Awaiting Treasurer'],
        'signer2_approved'   => ['#ecfdf5', '#047857', 'Ready to pay'],
        'submitted'          => ['#fffbeb', '#d97706', 'Awaiting Treasurer'],
        'treasurer_approved' => ['#eff6ff', '#1e40af', 'Awaiting VP'],
        'vp_approved'        => ['#ecfdf5', '#047857', 'Ready to pay'],
        'paid'               => ['#f0fdf4', '#166534', 'Paid'],
        'rejected'           => ['#fef2f2', '#991b1b', 'Rejected'],
    ];
    $c = $map[$status] ?? ['#f8f9fa', '#555', ucfirst($status)];
    return '<span class="badge" style="background:' . $c[0] . ';color:' . $c[1] . ';">' . $c[2] . '</span>';
}

function _signedLine(array $r, string $role1, string $role2): string {
    $bits = [];
    if (!empty($r['signer1_at'])) $bits[] = $role1 . ' ' . date('M j', strtotime($r['signer1_at']));
    if (!empty($r['signer2_at'])) $bits[] = $role2 . ' ' . date('M j', strtotime($r['signer2_at']));
    if (!empty($r['paid_at']))    $bits[] = 'paid ' . date('M j', strtotime($r['payment_date'] ?: $r['paid_at']));
    return $bits ? implode(' &middot; ', $bits) : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Approvals &amp; Payments — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 800px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { margin-bottom: 1.5rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: .3rem 0 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice   { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;
                font-size:.88rem;color:#166534;margin-bottom:1rem; }
    .error-box{ background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
                font-size:.88rem;color:#991b1b;margin-bottom:1rem; }
    .all-clear{ background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:.9rem 1.1rem;
                font-size:.9rem;color:#166534;margin-bottom:1.5rem; }

    h2.system { font-size: 1rem; font-weight: 800; color: var(--gray-800);
                margin: 2rem 0 .75rem; padding-bottom: .4rem; border-bottom: 2px solid var(--accent); }
    h2.system .n { font-weight: 600; color: var(--gray-400); font-size: .8rem; margin-left: .4rem; }

    .card { background:#fff;border:1px solid var(--gray-200);border-radius:10px;
            padding:.85rem 1.1rem;margin-bottom:.55rem; }
    .card.mine { border-color: var(--primary); box-shadow: 0 0 0 2px rgba(26,107,53,.08); }
    .card.done { opacity: .7; }
    .card-top { display:flex;align-items:baseline;gap:.6rem;flex-wrap:wrap; }
    .card-name { font-weight:700;color:var(--gray-800);font-size:.93rem; }
    .card-amt  { margin-left:auto;font-weight:800;color:var(--primary);font-size:1rem;white-space:nowrap; }
    .card-meta { font-size:.78rem;color:var(--gray-500);margin-top:.15rem; }
    .badge { display:inline-block;font-size:.66rem;font-weight:800;text-transform:uppercase;
             letter-spacing:.04em;padding:.15rem .5rem;border-radius:100px;white-space:nowrap; }

    .acts { display:flex;gap:.4rem;align-items:center;margin-top:.6rem;flex-wrap:wrap; }
    .acts .spacer { margin-left:auto; }
    .btn-go   { background:var(--primary);color:#fff;border:none;border-radius:6px;
                padding:.35rem .85rem;font-size:.82rem;font-weight:700;cursor:pointer; }
    .btn-go:hover { background:var(--primary-dk); }
    .btn-pay  { background:#166534;color:#fff;border:none;border-radius:6px;
                padding:.35rem .85rem;font-size:.82rem;font-weight:700;cursor:pointer; }
    .link-sm  { font-size:.8rem;color:var(--gray-500);text-decoration:none; }
    .link-sm:hover { color:var(--primary); }

    /* Secondary forms stay folded away until needed */
    details.more { margin-top:.5rem; }
    details.more > summary { font-size:.8rem;color:var(--gray-500);cursor:pointer;
                             list-style:none;display:inline-block; }
    details.more > summary::-webkit-details-marker { display:none; }
    details.more > summary:hover { color:var(--primary); }
    details.more[open] > summary { margin-bottom:.45rem; }
    .form-inline { display:flex;gap:.4rem;flex-wrap:wrap;align-items:center; }
    .form-inline input, .form-inline textarea {
        border:1px solid var(--gray-300);border-radius:6px;padding:.35rem .55rem;
        font-size:.82rem;font-family:inherit; }
    .form-inline textarea { min-height:34px;resize:vertical;flex:1;min-width:180px; }

    details.done-list { margin-top:.6rem; }
    details.done-list > summary { font-size:.82rem;font-weight:700;color:var(--gray-500);
                                  cursor:pointer;padding:.4rem 0; }
    details.done-list > summary:hover { color:var(--primary); }
    .empty { font-size:.85rem;color:var(--gray-400);font-style:italic;padding:.3rem 0 .6rem; }
    .ledger-note { font-size:.8rem;color:var(--gray-400);font-style:italic;margin-top:1.5rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
    <h1>Approvals &amp; Payments</h1>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if ($waiting === 0): ?>
    <div class="all-clear">&#x2713; Nothing needs you right now.</div>
  <?php endif; ?>

  <?php if (expCanReview($email)): ?>
  <h2 class="system">Member Reimbursements<?php if ($claimsActive): ?><span class="n"><?= count($claimsActive) ?> open</span><?php endif; ?></h2>

  <?php if (!$claimsActive): ?><p class="empty">Nothing open.</p><?php endif; ?>

  <?php if (expCanSubmitOnBehalf($email)): ?>
  <!-- The on-behalf form used to be reachable only from "Submit New Expense" on
       the dashboard, which is hidden from the President — they claim through LP
       vouchers. Kept here so a member who cannot file for themselves still can. -->
  <p style="margin:.2rem 0 1rem;">
    <a href="exp-submit.php" class="link-sm" style="font-weight:700;color:var(--primary);">
      + File a claim for a member &rarr;
    </a>
  </p>
  <?php endif; ?>

  <?php foreach ($claimsActive as $b):
    $mine  = _claimNeedsMe($b, $canSign1, $canSign2, $canPayClaim);
    $items = expBatchGetItems($b['id']);
    $total = expBatchTotal($b['id']);
  ?>
  <div class="card <?= $mine ? 'mine' : '' ?>">
    <div class="card-top">
      <span class="card-name"><?= htmlspecialchars($b['title'] ?: $b['ref_code']) ?></span>
      <?= _badge($b['status']) ?>
      <span class="card-amt">$<?= number_format($total, 2) ?></span>
    </div>
    <div class="card-meta">
      <?= htmlspecialchars($b['user_name']) ?> &middot;
      <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> &middot;
      <?= htmlspecialchars($b['ref_code']) ?>
    </div>

    <div class="acts">
      <?php if ($b['status'] === 'pending' && $canSign1): ?>
        <form method="POST" action="exp-claim-action.php" style="display:inline;">
          <input type="hidden" name="action" value="signer1_approve">
          <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="redirect" value="approvals.php">
          <button type="submit" class="btn-go">&#x2713; Approve</button>
        </form>
      <?php elseif ($b['status'] === 'signer1_approved' && $canSign2): ?>
        <form method="POST" action="exp-claim-action.php" style="display:inline;">
          <input type="hidden" name="action" value="signer2_approve">
          <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
          <input type="hidden" name="redirect" value="approvals.php">
          <button type="submit" class="btn-go">&#x2713; Sign</button>
        </form>
      <?php endif; ?>
      <span class="spacer"></span>
      <a class="link-sm" href="exp-claim-view.php?id=<?= (int)$b['id'] ?>">View &rarr;</a>
    </div>

    <?php if ($b['status'] === 'signer2_approved' && $canPayClaim): ?>
    <details class="more">
      <summary>&#x25B8; Record e-transfer</summary>
      <form method="POST" action="exp-claim-action.php" class="form-inline">
        <input type="hidden" name="action" value="mark_paid">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>">
        <input type="text" name="payment_ref" placeholder="Reference">
        <textarea name="note" placeholder="Optional note"></textarea>
        <button type="submit" class="btn-pay">Mark paid</button>
      </form>
    </details>
    <?php endif; ?>

    <?php if ($mine && $b['status'] !== 'signer2_approved'): ?>
    <details class="more">
      <summary>&#x25B8; Reject</summary>
      <form method="POST" action="exp-claim-action.php" class="form-inline">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <textarea name="note" placeholder="Reason (required)" required></textarea>
        <button type="submit" class="btn-go" style="background:#dc2626;">Reject</button>
      </form>
    </details>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if ($claimsDone): ?>
  <details class="done-list">
    <summary>Completed (<?= count($claimsDone) ?>)</summary>
    <?php foreach ($claimsDone as $b): $total = expBatchTotal($b['id']); ?>
    <div class="card done">
      <div class="card-top">
        <span class="card-name"><?= htmlspecialchars($b['title'] ?: $b['ref_code']) ?></span>
        <?= _badge($b['status']) ?>
        <span class="card-amt">$<?= number_format($total, 2) ?></span>
      </div>
      <div class="card-meta">
        <?= htmlspecialchars($b['user_name']) ?>
        <?php $sl = _signedLine($b, 'President', 'Treasurer'); ?>
        <?= $sl ? '&middot; ' . $sl : '' ?>
        &middot; <a class="link-sm" href="exp-claim-view.php?id=<?= (int)$b['id'] ?>">View &rarr;</a>
      </div>
    </div>
    <?php endforeach; ?>
  </details>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (lpCanReview($email)): ?>
  <h2 class="system">President&rsquo;s Expenses<?php if ($lpActive): ?><span class="n"><?= count($lpActive) ?> open</span><?php endif; ?></h2>

  <?php if (!$lpActive): ?><p class="empty">Nothing open.</p><?php endif; ?>

  <?php foreach ($lpActive as $v):
    $mine   = _lpNeedsMe($v, $isTreasurer, $isVP);
    $vTotal = array_sum(array_map('lpRowTotal', lpGetExpenses($v['id'])));
  ?>
  <div class="card <?= $mine ? 'mine' : '' ?>">
    <div class="card-top">
      <span class="card-name"><?= htmlspecialchars($v['name']) ?></span>
      <?= _badge($v['status']) ?>
      <span class="card-amt">$<?= number_format($vTotal, 2) ?></span>
    </div>
    <div class="card-meta">
      <?= htmlspecialchars($v['submitted_by']) ?>
      <?= $v['voucher_number'] ? '&middot; #' . htmlspecialchars($v['voucher_number']) : '' ?>
      &middot; <?= (int)$v['expense_count'] ?> item<?= (int)$v['expense_count'] === 1 ? '' : 's' ?>
    </div>

    <div class="acts">
      <?php if ($v['status'] === 'submitted' && $isTreasurer): ?>
        <form method="POST" action="lp-action.php" style="display:inline;">
          <input type="hidden" name="action" value="treasurer_approve">
          <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
          <input type="hidden" name="redirect" value="approvals.php">
          <button type="submit" class="btn-go">&#x2713; Approve</button>
        </form>
      <?php elseif ($v['status'] === 'treasurer_approved' && $isVP): ?>
        <form method="POST" action="lp-action.php" style="display:inline;">
          <input type="hidden" name="action" value="vp_approve">
          <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
          <input type="hidden" name="redirect" value="approvals.php">
          <button type="submit" class="btn-go">&#x2713; Sign</button>
        </form>
      <?php endif; ?>
      <span class="spacer"></span>
      <a class="link-sm" href="lp-voucher-edit.php?id=<?= (int)$v['id'] ?>">View &rarr;</a>
    </div>

    <?php if ($v['status'] === 'vp_approved' && $isTreasurer): ?>
    <details class="more">
      <summary>&#x25B8; Record e-transfer</summary>
      <form method="POST" action="lp-action.php" class="form-inline">
        <input type="hidden" name="action" value="mark_paid">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>">
        <input type="text" name="payment_ref" placeholder="Reference">
        <textarea name="note" placeholder="Optional note"></textarea>
        <button type="submit" class="btn-pay">Mark paid</button>
      </form>
    </details>
    <?php endif; ?>

    <?php if ($mine && $v['status'] !== 'vp_approved'): ?>
    <details class="more">
      <summary>&#x25B8; Reject</summary>
      <form method="POST" action="lp-action.php" class="form-inline">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <textarea name="note" placeholder="Reason (required)" required></textarea>
        <button type="submit" class="btn-go" style="background:#dc2626;">Reject</button>
      </form>
    </details>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if ($lpDone): ?>
  <details class="done-list">
    <summary>Completed (<?= count($lpDone) ?>)</summary>
    <?php foreach ($lpDone as $v):
      $vTotal = array_sum(array_map('lpRowTotal', lpGetExpenses($v['id'])));
    ?>
    <div class="card done">
      <div class="card-top">
        <span class="card-name"><?= htmlspecialchars($v['name']) ?></span>
        <?= _badge($v['status']) ?>
        <span class="card-amt">$<?= number_format($vTotal, 2) ?></span>
      </div>
      <div class="card-meta">
        <?= htmlspecialchars($v['submitted_by']) ?>
        <?php $sl = _signedLine($v, 'Treasurer', 'VP'); ?>
        <?= $sl ? '&middot; ' . $sl : '' ?>
        &middot; <a class="link-sm" href="lp-voucher-edit.php?id=<?= (int)$v['id'] ?>">View &rarr;</a>
      </div>
    </div>
    <?php endforeach; ?>
  </details>
  <?php endif; ?>
  <?php endif; ?>

  <p class="ledger-note">Payment ledger paused while these tools are reorganised.</p>

</div>
</body>
</html>
