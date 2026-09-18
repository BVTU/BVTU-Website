<?php
/**
 * approvals.php — everything a signer needs, in one page.
 *
 * Member reimbursements and the President's expenses used to live on separate
 * pages reachable only sideways from each other. Both queues now render here,
 * so a signer sees their whole workload without navigating between systems.
 *
 * The two sign in opposite orders, which is why the sections differ:
 *   Member reimbursement — President signs, then Treasurer; either one pays.
 *   President's expenses — Treasurer signs, then VP; Treasurer pays.
 *                          (The President is the claimant and cannot sign.)
 *
 * Actions still POST to exp-claim-action.php and lp-action.php, which own the
 * workflow rules; this page only renders and links.
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

// ── Member reimbursements ────────────────────────────────────────────────────
$canSign1 = expIsEligibleSigner1($email);   // President
$canSign2 = expIsEligibleSigner2($email);   // Treasurer
$canPay   = expCanMarkPaid($email);         // either

$forSigner1 = $canSign1 ? expBatchGetAll('pending')          : [];
$forSigner2 = $canSign2 ? expBatchGetAll('signer1_approved') : [];
$readyToPay = $canPay   ? expBatchGetAll('signer2_approved') : [];

// ── President's expenses ─────────────────────────────────────────────────────
$isTreasurer = lpCanSign1($email);
$isVP        = lpCanSign2($email);

$forTreasurer  = $isTreasurer ? lpGetVouchers('', 'submitted')          : [];
$forVP         = $isVP        ? lpGetVouchers('', 'treasurer_approved') : [];
$lpReadyToPay  = $isTreasurer ? lpGetVouchers('', 'vp_approved')        : [];

$waiting = count($forSigner1) + count($forSigner2) + count($readyToPay)
         + count($forTreasurer) + count($forVP) + count($lpReadyToPay);

// Signed-off items from both systems, newest first, for reference only.
$claimHistory = array_merge(expBatchGetAll('paid'), expBatchGetAll('rejected'));
$lpActionable = [];
if ($isTreasurer) $lpActionable[] = 'vp_approved';
if ($isVP)        $lpActionable[] = 'treasurer_approved';
$lpHistory = lpGetVouchersByStatuses(
    array_values(array_diff(['treasurer_approved','vp_approved','paid','rejected'], $lpActionable))
);

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference / Workshop',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];

function _expClaimBadge(string $status): string {
    $map = [
        'pending'           => ['#fffbeb', '#d97706', 'Awaiting President'],
        'signer1_approved'  => ['#eff6ff', '#1e40af', 'Awaiting Treasurer'],
        'signer2_approved'  => ['#f0fdf4', '#166534', 'Ready to Pay'],
        'paid'              => ['#f0fdf4', '#166534', 'Paid'],
        'rejected'          => ['#fef2f2', '#991b1b', 'Rejected'],
    ];
    $s = $map[$status] ?? ['#f8f9fa', '#555', ucfirst($status)];
    return '<span style="display:inline-block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;'
         . 'padding:.2rem .6rem;border-radius:100px;background:' . $s[0] . ';color:' . $s[1] . ';">' . $s[2] . '</span>';
}

function _lpExpenseTotal(int $voucherId): float {
    $expenses = lpGetExpenses($voucherId);
    return array_sum(array_map('lpRowTotal', $expenses));
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
    .wrap { max-width: 980px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice   { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box{ background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--gray-400); margin: 2rem 0 .75rem; display: flex; align-items: center; gap: .5rem; }
    .empty-note { font-size: .88rem; color: var(--gray-400); font-style: italic; padding: .5rem 0 1rem; }

    .claim-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
    .claim-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: .6rem; }
    .claim-name { font-size: 1rem; font-weight: 800; color: var(--gray-800); }
    .claim-meta { font-size: .82rem; color: var(--gray-400); margin-top: .2rem; }
    .claim-total { font-size: 1.15rem; font-weight: 800; color: var(--primary); text-align: right; }
    .claim-total span { display: block; font-size: .72rem; font-weight: 600; color: var(--gray-400); text-transform: uppercase; letter-spacing: .04em; }
    .item-list { font-size: .82rem; color: var(--gray-500); margin: .6rem 0; padding: .6rem .8rem; background: #f8f9fa; border-radius: 7px; }
    .item-list .it { display: flex; justify-content: space-between; padding: .15rem 0; }
    .item-list .it.more { color: var(--gray-400); font-style: italic; }

    .action-row { display: flex; gap: .6rem; align-items: flex-start; flex-wrap: wrap; margin-top: .85rem; }
    .note-area { width: 100%; margin-top: .4rem; }
    textarea.note-input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .5rem .75rem; font-size: .85rem; font-family: inherit; resize: vertical; min-height: 60px; box-sizing: border-box; }
    .btn-approve { background: var(--primary); color: #fff; border: none; border-radius: 7px; padding: .5rem 1.1rem; font-size: .88rem; font-weight: 700; cursor: pointer; }
    .btn-approve:hover { background: var(--primary-dk); }
    .btn-reject  { background: none; border: 1px solid #fecaca; color: #dc2626; border-radius: 7px; padding: .5rem 1rem; font-size: .88rem; font-weight: 600; cursor: pointer; }
    .btn-reject:hover { background: #fef2f2; }
    .btn-paid    { background: #166534; color: #fff; border: none; border-radius: 7px; padding: .5rem 1.1rem; font-size: .88rem; font-weight: 700; cursor: pointer; }
    .btn-paid:hover { background: #14532d; }
    .detail-link { font-size: .82rem; color: var(--gray-500); align-self: center; }
    .detail-link:hover { color: var(--primary); }
    .etransfer-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; margin: .6rem 0; font-family: monospace; font-size: .85rem; color: #14532d; }
    .etransfer-box .row { display: flex; gap: .5rem; }
    .etransfer-box .lbl { color: #4ade80; width: 70px; flex-shrink: 0; }
  
    /* Card styles unique to the voucher queue */
    .voucher-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
                    padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
    .voucher-top { display: flex; align-items: flex-start; justify-content: space-between;
                   gap: 1rem; flex-wrap: wrap; margin-bottom: .85rem; }
    .voucher-name { font-size: 1rem; font-weight: 800; color: var(--gray-800); }
    .voucher-meta { font-size: .82rem; color: var(--gray-400); margin-top: .2rem; }
    .voucher-total { font-size: 1.15rem; font-weight: 800; color: var(--primary); text-align: right; }
    .voucher-total span { display: block; font-size: .72rem; font-weight: 600;
                          color: var(--gray-400); text-transform: uppercase; letter-spacing: .04em; }
    .system-head { font-size: .95rem; font-weight: 800; color: var(--gray-800);
                   margin: 2.5rem 0 .25rem; padding-bottom: .4rem;
                   border-bottom: 2px solid var(--accent); }
    .system-sub  { font-size: .8rem; color: var(--gray-500); margin-bottom: .5rem; }
    .all-clear   { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
                   padding: 1rem 1.25rem; font-size: .9rem; color: #166534; margin-bottom: 1rem; }
    .muted-note  { font-size: .8rem; color: var(--gray-400); font-style: italic; }
    .ref-card    { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
                   padding: .9rem 1.1rem; margin-top: .6rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Approvals &amp; Payments</h1>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if ($waiting === 0): ?>
    <div class="all-clear">&#x2713; Nothing needs your signature right now.</div>
  <?php endif; ?>

  <?php if (expCanReview($email)): ?>
  <div class="system-head">Member Reimbursements</div>
  <div class="system-sub">
    Claims from members. The President approves, the Treasurer signs, either one pays.
  </div>

  <?php
  function _expClaimItemPreview(array $items, array $catLabels): string {
      if (!$items) return '';
      $html = '<div class="item-list">';
      $shown = array_slice($items, 0, 4);
      foreach ($shown as $it) {
          $label = $catLabels[$it['category']] ?? ucfirst($it['category']);
          $html .= '<div class="it"><span>' . htmlspecialchars(($it['expense_date'] ? date('M j', strtotime($it['expense_date'])) . ' — ' : '') . $label . ': ' . $it['description']) . '</span>'
                 . '<span>$' . number_format((float)$it['amount'], 2) . '</span></div>';
      }
      if (count($items) > count($shown)) {
          $html .= '<div class="it more">+ ' . (count($items) - count($shown)) . ' more item' . ((count($items) - count($shown)) === 1 ? '' : 's') . '&hellip;</div>';
      }
      $html .= '</div>';
      return $html;
  }
  ?>

  <?php if ($canSign1): ?>
  <!-- ── Step 1: President authorises ── -->
  <div class="sec-head">
    Awaiting President's Approval
    <?php if ($forSigner1): ?><span style="background:#fef3c7;color:#d97706;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;"><?= count($forSigner1) ?></span><?php endif; ?>
  </div>
  <?php if (!$forSigner1): ?>
    <p class="empty-note">No claims awaiting the President's approval.</p>
  <?php endif; ?>
  <?php foreach ($forSigner1 as $b):
    $items = expBatchGetItems((int)$b['id']);
    $total = array_sum(array_map(function($i){ return (float)$i['amount']; }, $items));
  ?>
  <div class="claim-card">
    <div class="claim-top">
      <div>
        <div class="claim-name"><?= htmlspecialchars($b['title'] ?: $b['user_name']) ?></div>
        <div class="claim-meta">
          <?= htmlspecialchars($b['user_name']) ?> &middot; <?= htmlspecialchars($b['ref_code']) ?>
          &middot; <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>
          &middot; submitted <?= $b['submitted_at'] ? date('M j, Y', strtotime($b['submitted_at'])) : '—' ?>
          <?php if (!empty($b['submitted_by_email']) && strtolower($b['submitted_by_email']) !== strtolower($b['user_email'])): ?>
          &middot; <span style="color:var(--gray-400);">submitted by <?= htmlspecialchars($b['submitted_by_name'] ?: $b['submitted_by_email']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="claim-total">$<?= number_format($total, 2) ?><span>Claim total</span></div>
    </div>
    <?= _expClaimBadge($b['status']) ?>
    <?= _expClaimItemPreview($items, $catLabels) ?>
    <div class="action-row">
      <form method="POST" action="exp-claim-action.php">
        <input type="hidden" name="action"   value="signer1_approve">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <button type="submit" class="btn-approve">&#x2713; Approve</button>
        <div class="note-area"><textarea class="note-input" name="note" placeholder="Optional note for second signer&hellip;"></textarea></div>
      </form>
      <form method="POST" action="exp-claim-action.php" onsubmit="return confirm('Reject this claim?')">
        <input type="hidden" name="action"   value="reject">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <div>
          <textarea class="note-input" name="note" placeholder="Reason for rejection (required)" required style="width:220px;min-height:60px;"></textarea><br>
          <button type="submit" class="btn-reject" style="margin-top:.35rem;">&#x2715; Reject</button>
        </div>
      </form>
      <a href="exp-claim-view.php?id=<?= (int)$b['id'] ?>" class="detail-link">View claim &#x2192;</a>
      <?php if (expIsAdmin($member['email'])): ?>
      <form method="POST" action="exp-claim-action.php"
            onsubmit="return confirm('Re-send the Treasurer notification email for this claim?')">
        <input type="hidden" name="action"   value="resend_to_treasurer">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <button type="submit" style="background:none;border:1px solid var(--gray-200);border-radius:6px;padding:.3rem .65rem;font-size:.78rem;color:var(--gray-500);cursor:pointer;">&#x21BA; Resend notification</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; ?>

  <?php if ($canSign2): ?>
  <!-- ── Step 2: Treasurer signs ── -->
  <div class="sec-head" style="margin-top:2.5rem;">
    Awaiting Treasurer's Signature
    <?php if ($forSigner2): ?><span style="background:#eff6ff;color:#1e40af;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;"><?= count($forSigner2) ?></span><?php endif; ?>
  </div>
  <?php if (!$forSigner2): ?>
    <p class="empty-note">No claims awaiting the Treasurer's signature.</p>
  <?php endif; ?>
  <?php foreach ($forSigner2 as $b):
    $items = expBatchGetItems((int)$b['id']);
    $total = array_sum(array_map(function($i){ return (float)$i['amount']; }, $items));
  ?>
  <div class="claim-card">
    <div class="claim-top">
      <div>
        <div class="claim-name"><?= htmlspecialchars($b['title'] ?: $b['user_name']) ?></div>
        <div class="claim-meta">
          <?= htmlspecialchars($b['user_name']) ?> &middot; <?= htmlspecialchars($b['ref_code']) ?>
          &middot; <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>
          &middot; Treasurer approved by <strong><?= htmlspecialchars($b['signer1_name'] ?: '—') ?></strong>
          on <?= $b['signer1_at'] ? date('M j, Y', strtotime($b['signer1_at'])) : '—' ?>
        </div>
      </div>
      <div class="claim-total">$<?= number_format($total, 2) ?><span>Claim total</span></div>
    </div>
    <?= _expClaimBadge($b['status']) ?>
    <?php if ($b['signer1_note']): ?>
    <div class="claim-meta" style="font-style:italic;margin-top:.4rem;">&#x201C;<?= htmlspecialchars($b['signer1_note']) ?>&#x201D; &mdash; <?= htmlspecialchars($b['signer1_name']) ?></div>
    <?php endif; ?>
    <?= _expClaimItemPreview($items, $catLabels) ?>
    <div class="action-row">
      <form method="POST" action="exp-claim-action.php">
        <input type="hidden" name="action"   value="signer2_approve">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <button type="submit" class="btn-approve">&#x2713; Approve &amp; Sign</button>
        <div class="note-area"><textarea class="note-input" name="note" placeholder="Optional note&hellip;"></textarea></div>
      </form>
      <form method="POST" action="exp-claim-action.php" onsubmit="return confirm('Reject this claim?')">
        <input type="hidden" name="action"   value="reject">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <div>
          <textarea class="note-input" name="note" placeholder="Reason for rejection (required)" required style="width:220px;min-height:60px;"></textarea><br>
          <button type="submit" class="btn-reject" style="margin-top:.35rem;">&#x2715; Reject</button>
        </div>
      </form>
      <a href="exp-claim-view.php?id=<?= (int)$b['id'] ?>" class="detail-link">View claim &#x2192;</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($canPay): ?>
  <!-- ── Step 3: e-transfer ── -->
  <div class="sec-head" style="margin-top:2.5rem;">
    Ready for E-Transfer
    <?php if ($readyToPay): ?><span style="background:#f0fdf4;color:#166534;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;"><?= count($readyToPay) ?></span><?php endif; ?>
  </div>
  <?php if (!$readyToPay): ?>
    <p class="empty-note">No claims ready for payment.</p>
  <?php endif; ?>
  <?php foreach ($readyToPay as $b):
    $items = expBatchGetItems((int)$b['id']);
    $total = array_sum(array_map(function($i){ return (float)$i['amount']; }, $items));
  ?>
  <div class="claim-card">
    <div class="claim-top">
      <div>
        <div class="claim-name"><?= htmlspecialchars($b['title'] ?: $b['user_name']) ?></div>
        <div class="claim-meta">
          <?= htmlspecialchars($b['user_name']) ?> &middot; <?= htmlspecialchars($b['ref_code']) ?>
          &middot; Both signatures complete &middot; <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?>
        </div>
      </div>
      <div class="claim-total">$<?= number_format($total, 2) ?><span>E-transfer amount</span></div>
    </div>
    <?= _expClaimBadge($b['status']) ?>
    <div class="etransfer-box">
      <div class="row"><span class="lbl">To:</span><span><?= htmlspecialchars($b['user_email']) ?></span></div>
      <div class="row"><span class="lbl">Amount:</span><span>$<?= number_format($total, 2) ?></span></div>
      <div class="row"><span class="lbl">Message:</span><span><?= htmlspecialchars($b['ref_code']) ?></span></div>
    </div>
    <div class="action-row" style="margin-top:.4rem;">
      <form method="POST" action="exp-claim-action.php">
        <input type="hidden" name="action"   value="mark_paid">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="approvals.php">
        <div>
          <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.4rem;">
            <div>
              <label style="font-size:.75rem;color:#6b7280;display:block;margin-bottom:.2rem;">Payment date</label>
              <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" style="border:1px solid #d1d5db;border-radius:6px;padding:.35rem .5rem;font-size:.83rem;">
            </div>
            <div style="flex:1;min-width:160px;">
              <label style="font-size:.75rem;color:#6b7280;display:block;margin-bottom:.2rem;">Cheque # or e-transfer ref</label>
              <input type="text" name="payment_ref" placeholder="e.g. Cheque #1042 or ET-abc123" style="width:100%;border:1px solid #d1d5db;border-radius:6px;padding:.35rem .5rem;font-size:.83rem;box-sizing:border-box;">
            </div>
          </div>
          <textarea class="note-input" name="note" placeholder="Optional note&hellip;" style="width:280px;min-height:40px;"></textarea><br>
          <button type="submit" class="btn-paid" style="margin-top:.35rem;">&#x2713; Mark as Paid &mdash; Single E-Transfer Sent</button>
        </div>
      </form>
      <a href="exp-claim-view.php?id=<?= (int)$b['id'] ?>" class="detail-link">View claim &#x2192;</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  
  <?php endif; ?>

  <?php if (lpCanReview($email)): ?>
  <div class="system-head">President&rsquo;s Expenses</div>
  <div class="system-sub">
    The President&rsquo;s own expenses. The Treasurer approves, the Vice-President signs,
    the Treasurer pays &mdash; the President cannot sign their own.
  </div>

  <?php
  // ── Treasurer: awaiting first approval ──────────────────────────────────────
  if ($isTreasurer):
  ?>
  <div class="sec-head">
    Awaiting Treasurer&rsquo;s Approval
    <?php if ($forTreasurer): ?>
    <span style="background:#fef3c7;color:#d97706;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;margin-left:.4rem;"><?= count($forTreasurer) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$forTreasurer): ?>
    <p class="empty-note">No expenses awaiting the Treasurer&rsquo;s approval.</p>
  <?php endif; ?>

  <?php foreach ($forTreasurer as $v):
    $total = _lpExpenseTotal($v['id']);
  ?>
  <div class="voucher-card">
    <div class="voucher-top">
      <div>
        <div class="voucher-name"><?= htmlspecialchars($v['name']) ?></div>
        <div class="voucher-meta">
          <?= htmlspecialchars($v['submitted_by']) ?> &middot;
          Submitted <?= $v['submitted_at'] ? date('M j, Y', strtotime($v['submitted_at'])) : '—' ?>
          <?php if ($v['voucher_number']): ?>&middot; #<?= htmlspecialchars($v['voucher_number']) ?><?php endif; ?>
        </div>
      </div>
      <div class="voucher-total">
        $<?= number_format($total, 2) ?>
        <span>Total</span>
      </div>
    </div>
    <?= lpStatusBadge($v['status']) ?>
    <div class="action-row">
      <form method="POST" action="lp-action.php">
        <input type="hidden" name="action"     value="treasurer_approve">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="approvals.php">
        <button type="submit" class="btn-approve">&#x2713; Approve</button>
        <div class="note-area">
          <textarea class="note-input" name="note" placeholder="Optional note for VP&hellip;"></textarea>
        </div>
      </form>
      <form method="POST" action="lp-action.php" onsubmit="return confirm('Reject this voucher?')">
        <input type="hidden" name="action"     value="reject">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="approvals.php">
        <div>
          <textarea class="note-input" name="note" placeholder="Reason for rejection (required)" required
                    style="width:220px;min-height:60px;"></textarea>
          <br>
          <button type="submit" class="btn-reject" style="margin-top:.35rem;">&#x2715; Reject</button>
        </div>
      </form>
      <a href="lp-voucher-edit.php?id=<?= (int)$v['id'] ?>" class="detail-link">View voucher &#x2192;</a>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; // isTreasurer ?>

  <?php
  // ── VP: awaiting second approval ────────────────────────────────────────────
  if ($isVP):
  ?>
  <div class="sec-head">
    Awaiting Vice-President&rsquo;s Signature
    <?php if ($forVP): ?>
    <span style="background:#eff6ff;color:#1e40af;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;margin-left:.4rem;"><?= count($forVP) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$forVP): ?>
    <p class="empty-note">No vouchers awaiting VP signature.</p>
  <?php endif; ?>

  <?php foreach ($forVP as $v):
    $total = _lpExpenseTotal($v['id']);
  ?>
  <div class="voucher-card">
    <div class="voucher-top">
      <div>
        <div class="voucher-name"><?= htmlspecialchars($v['name']) ?></div>
        <div class="voucher-meta">
          Treasurer approved by <strong><?= htmlspecialchars($v['signer1_name'] ?? '—') ?></strong>
          on <?= $v['signer1_at'] ? date('M j, Y', strtotime($v['signer1_at'])) : '—' ?>
        </div>
      </div>
      <div class="voucher-total">
        $<?= number_format($total, 2) ?>
        <span>Total</span>
      </div>
    </div>
    <?= lpStatusBadge($v['status']) ?>
    <div class="action-row">
      <form method="POST" action="lp-action.php">
        <input type="hidden" name="action"     value="vp_approve">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="approvals.php">
        <button type="submit" class="btn-approve">&#x2713; Approve &amp; Sign</button>
        <div class="note-area">
          <textarea class="note-input" name="note" placeholder="Optional note&hellip;"></textarea>
        </div>
      </form>
      <form method="POST" action="lp-action.php" onsubmit="return confirm('Reject this voucher?')">
        <input type="hidden" name="action"     value="reject">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="approvals.php">
        <div>
          <textarea class="note-input" name="note" placeholder="Reason for rejection (required)" required
                    style="width:220px;min-height:60px;"></textarea>
          <br>
          <button type="submit" class="btn-reject" style="margin-top:.35rem;">&#x2715; Reject</button>
        </div>
      </form>
      <a href="lp-voucher-edit.php?id=<?= (int)$v['id'] ?>" class="detail-link">View voucher &#x2192;</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; // isVP ?>

  <?php if ($isTreasurer): ?>
  <!-- ── Treasurer: ready to pay ─────────────────────────────────────────────── -->
  <div class="sec-head">
    Ready for E-Transfer
    <?php if ($readyToPay): ?>
    <span style="background:#f0fdf4;color:#166534;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;margin-left:.4rem;"><?= count($readyToPay) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$readyToPay): ?>
    <p class="empty-note">No vouchers ready for payment.</p>
  <?php endif; ?>

  <?php foreach ($readyToPay as $v):
    $total = _lpExpenseTotal($v['id']);
  ?>
  <div class="voucher-card">
    <div class="voucher-top">
      <div>
        <div class="voucher-name"><?= htmlspecialchars($v['name']) ?></div>
        <div class="voucher-meta">
          Both signatures complete &middot; Send e-transfer to
          <strong><?= htmlspecialchars($v['submitted_by_email']) ?></strong>
        </div>
      </div>
      <div class="voucher-total">
        $<?= number_format($total, 2) ?>
        <span>E-transfer amount</span>
      </div>
    </div>
    <?= lpStatusBadge($v['status']) ?>
    <div class="action-row" style="margin-top:.85rem;">
      <form method="POST" action="lp-action.php">
        <input type="hidden" name="action"     value="mark_paid">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="approvals.php">
        <div>
          <div style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:.4rem;">
            <div>
              <label style="font-size:.75rem;color:#6b7280;display:block;margin-bottom:.2rem;">Payment date</label>
              <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" style="border:1px solid #d1d5db;border-radius:6px;padding:.35rem .5rem;font-size:.83rem;">
            </div>
            <div style="flex:1;min-width:160px;">
              <label style="font-size:.75rem;color:#6b7280;display:block;margin-bottom:.2rem;">Cheque # or e-transfer ref</label>
              <input type="text" name="payment_ref" placeholder="e.g. Cheque #1042 or ET-abc123" style="width:100%;border:1px solid #d1d5db;border-radius:6px;padding:.35rem .5rem;font-size:.83rem;box-sizing:border-box;">
            </div>
          </div>
          <textarea class="note-input" name="note" placeholder="Optional note&hellip;" style="width:280px;min-height:40px;"></textarea>
          <br>
          <button type="submit" class="btn-paid" style="margin-top:.35rem;">
            &#x2713; Mark as Paid
          </button>
        </div>
      </form>
      <a href="lp-voucher-edit.php?id=<?= (int)$v['id'] ?>" class="detail-link">View voucher &#x2192;</a>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; ?>

  
  <?php endif; ?>

  <div class="system-head">Payment History</div>
  <div class="ref-card">
    <div style="font-weight:700;color:var(--gray-800);font-size:.93rem;">Payment Ledger</div>
    <div style="font-size:.8rem;color:var(--gray-500);margin-top:.15rem;">
      Every paid claim and voucher in one list, for reconciling against e-transfers.
    </div>
    <span class="muted-note">Paused while these tools are reorganised.</span>
  </div>

</div>
</body>
</html>
