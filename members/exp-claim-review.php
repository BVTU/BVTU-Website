<?php
/**
 * exp-claim-review.php — Treasurer + second-signer review queue for multi-item expense claims
 *
 * Mirrors lp-review.php: each claim is reviewed and signed ONCE as a whole
 * (not item-by-item), and the Treasurer issues a single e-transfer for the
 * combined total once both signatures are in place.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();
expBatchEnsureTables();

$isTreasurer = expIsTreasurer($member['email']) || expIsAdmin($member['email']);
$isSigner2   = expIsEligibleSigner2($member['email']) || expIsAdmin($member['email']);

if (!$isTreasurer && !$isSigner2) {
    header('Location: exp-dashboard.php');
    exit;
}

$notice = $_GET['notice'] ?? '';
$error  = $_GET['error']  ?? '';

$forTreasurer = $isTreasurer ? expBatchGetAll('pending')          : [];
$forSigner2   = $isSigner2   ? expBatchGetAll('signer1_approved') : [];
$readyToPay   = $isTreasurer ? expBatchGetAll('signer2_approved') : [];

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
        'pending'           => ['#fffbeb', '#d97706', 'Awaiting Treasurer'],
        'signer1_approved'  => ['#eff6ff', '#1e40af', 'Awaiting Second Signature'],
        'signer2_approved'  => ['#f0fdf4', '#166534', 'Ready to Pay'],
        'paid'              => ['#f0fdf4', '#166534', 'Paid'],
        'rejected'          => ['#fef2f2', '#991b1b', 'Rejected'],
    ];
    $s = $map[$status] ?? ['#f8f9fa', '#555', ucfirst($status)];
    return '<span style="display:inline-block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;'
         . 'padding:.2rem .6rem;border-radius:100px;background:' . $s[0] . ';color:' . $s[1] . ';">' . $s[2] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expense Claim Review — BVTU</title>
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
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="exp-dashboard.php">&#x2190; Expense Dashboard</a>
      <h1 style="margin-top:.3rem;">Expense Claim Review</h1>
    </div>
    <?php if ($isTreasurer): ?>
    <a href="exp-payments.php" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">Payment Records</a>
    <?php endif; ?>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

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

  <?php if ($isTreasurer): ?>
  <!-- ── Treasurer: awaiting first approval ── -->
  <div class="sec-head">
    Awaiting Your Approval (Treasurer)
    <?php if ($forTreasurer): ?><span style="background:#fef3c7;color:#d97706;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;"><?= count($forTreasurer) ?></span><?php endif; ?>
  </div>
  <?php if (!$forTreasurer): ?>
    <p class="empty-note">No expense claims awaiting Treasurer approval.</p>
  <?php endif; ?>
  <?php foreach ($forTreasurer as $b):
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
        <input type="hidden" name="redirect" value="exp-claim-review.php">
        <button type="submit" class="btn-approve">&#x2713; Approve</button>
        <div class="note-area"><textarea class="note-input" name="note" placeholder="Optional note for second signer&hellip;"></textarea></div>
      </form>
      <form method="POST" action="exp-claim-action.php" onsubmit="return confirm('Reject this claim?')">
        <input type="hidden" name="action"   value="reject">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="exp-claim-review.php">
        <div>
          <textarea class="note-input" name="note" placeholder="Reason for rejection (required)" required style="width:220px;min-height:60px;"></textarea><br>
          <button type="submit" class="btn-reject" style="margin-top:.35rem;">&#x2715; Reject</button>
        </div>
      </form>
      <a href="exp-claim-view.php?id=<?= (int)$b['id'] ?>" class="detail-link">View claim &#x2192;</a>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- ── Treasurer: ready to pay ── -->
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
        <input type="hidden" name="redirect" value="exp-claim-review.php">
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
  <?php endif; // isTreasurer ?>

  <?php if ($isSigner2): ?>
  <!-- ── Second signer: awaiting signature ── -->
  <div class="sec-head" style="margin-top:2.5rem;">
    Awaiting Your Signature (Second Signer)
    <?php if ($forSigner2): ?><span style="background:#eff6ff;color:#1e40af;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;"><?= count($forSigner2) ?></span><?php endif; ?>
  </div>
  <?php if (!$forSigner2): ?>
    <p class="empty-note">No claims awaiting your signature.</p>
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
        <input type="hidden" name="redirect" value="exp-claim-review.php">
        <button type="submit" class="btn-approve">&#x2713; Approve &amp; Sign</button>
        <div class="note-area"><textarea class="note-input" name="note" placeholder="Optional note&hellip;"></textarea></div>
      </form>
      <form method="POST" action="exp-claim-action.php" onsubmit="return confirm('Reject this claim?')">
        <input type="hidden" name="action"   value="reject">
        <input type="hidden" name="batch_id" value="<?= (int)$b['id'] ?>">
        <input type="hidden" name="redirect" value="exp-claim-review.php">
        <div>
          <textarea class="note-input" name="note" placeholder="Reason for rejection (required)" required style="width:220px;min-height:60px;"></textarea><br>
          <button type="submit" class="btn-reject" style="margin-top:.35rem;">&#x2715; Reject</button>
        </div>
      </form>
      <a href="exp-claim-view.php?id=<?= (int)$b['id'] ?>" class="detail-link">View claim &#x2192;</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; // isSigner2 ?>

</div>
</body>
</html>
