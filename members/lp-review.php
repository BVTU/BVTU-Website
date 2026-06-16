<?php
/**
 * lp-review.php — Treasurer + VP review queue for LP expense vouchers
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/lp-db.php';

requireLogin();
$member = getMember();
lpEnsureTables();
lpEnsureApprovalColumns();

if (!lpCanReview($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$isTreasurer = lpCanSign1($member['email']);
$isVP        = lpCanSign2($member['email']);

$notice = htmlspecialchars($_GET['notice'] ?? '');
$error  = htmlspecialchars($_GET['error']  ?? '');

// Treasurer sees: submitted vouchers
$forTreasurer = $isTreasurer ? lpGetVouchers('', 'submitted') : [];
// VP sees: treasurer_approved vouchers
$forVP        = $isVP        ? lpGetVouchers('', 'treasurer_approved') : [];
// Treasurer sees: vp_approved (ready to pay)
$readyToPay   = $isTreasurer ? lpGetVouchers('', 'vp_approved') : [];

function _lpStatusBadge(string $status): string {
    $map = [
        'draft'               => ['#f1f5f9','#64748b','Draft'],
        'submitted'           => ['#fffbeb','#d97706','Awaiting Treasurer'],
        'treasurer_approved'  => ['#eff6ff','#1e40af','Awaiting VP'],
        'vp_approved'         => ['#f0fdf4','#166534','Ready to Pay'],
        'paid'                => ['#f0fdf4','#166534','Paid'],
        'rejected'            => ['#fef2f2','#991b1b','Rejected'],
    ];
    $s = $map[$status] ?? ['#f8f9fa','#555', ucfirst($status)];
    return '<span style="display:inline-block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:.2rem .6rem;border-radius:100px;background:' . $s[0] . ';color:' . $s[1] . ';">' . $s[2] . '</span>';
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
  <title>LP Voucher Review — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between;
                   margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice   { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box{ background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase;
                letter-spacing: .08em; color: var(--gray-400); margin: 2rem 0 .75rem; }
    .empty-note { font-size: .88rem; color: var(--gray-400); font-style: italic; padding: .5rem 0 1rem; }
    .voucher-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
                    padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
    .voucher-top { display: flex; align-items: flex-start; justify-content: space-between;
                   gap: 1rem; flex-wrap: wrap; margin-bottom: .85rem; }
    .voucher-name { font-size: 1rem; font-weight: 800; color: var(--gray-800); }
    .voucher-meta { font-size: .82rem; color: var(--gray-400); margin-top: .2rem; }
    .voucher-total { font-size: 1.15rem; font-weight: 800; color: var(--primary); text-align: right; }
    .voucher-total span { display: block; font-size: .72rem; font-weight: 600;
                          color: var(--gray-400); text-transform: uppercase; letter-spacing: .04em; }
    .action-row { display: flex; gap: .6rem; align-items: flex-start; flex-wrap: wrap; margin-top: .85rem; }
    .note-area { width: 100%; margin-top: .4rem; }
    textarea.note-input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px;
                          padding: .5rem .75rem; font-size: .85rem; font-family: inherit;
                          resize: vertical; min-height: 60px; box-sizing: border-box; }
    .btn-approve { background: var(--primary); color: #fff; border: none; border-radius: 7px;
                   padding: .5rem 1.1rem; font-size: .88rem; font-weight: 700; cursor: pointer; }
    .btn-approve:hover { background: var(--primary-dk); }
    .btn-reject  { background: none; border: 1px solid #fecaca; color: #dc2626; border-radius: 7px;
                   padding: .5rem 1rem; font-size: .88rem; font-weight: 600; cursor: pointer; }
    .btn-reject:hover { background: #fef2f2; }
    .btn-paid    { background: #166534; color: #fff; border: none; border-radius: 7px;
                   padding: .5rem 1.1rem; font-size: .88rem; font-weight: 700; cursor: pointer; }
    .btn-paid:hover { background: #14532d; }
    .detail-link { font-size: .82rem; color: var(--gray-500); align-self: center; }
    .detail-link:hover { color: var(--primary); }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="lp-dashboard.php">&#x2190; LP Dashboard</a>
      <h1 style="margin-top:.3rem;">LP Voucher Review</h1>
    </div>
    <a href="treasurer-ledger.php" style="background:#1a2e1a;color:#fff;border:none;border-radius:8px;padding:.5rem 1rem;font-size:.85rem;font-weight:700;text-decoration:none;">📒 Unified Ledger</a>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <?php
  // ── Treasurer: awaiting first approval ──────────────────────────────────────
  if ($isTreasurer):
  ?>
  <div class="sec-head">
    Awaiting Your Approval (Treasurer)
    <?php if ($forTreasurer): ?>
    <span style="background:#fef3c7;color:#d97706;font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem;margin-left:.4rem;"><?= count($forTreasurer) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$forTreasurer): ?>
    <p class="empty-note">No vouchers awaiting Treasurer approval.</p>
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
    <?= _lpStatusBadge($v['status']) ?>
    <div class="action-row">
      <form method="POST" action="lp-action.php">
        <input type="hidden" name="action"     value="treasurer_approve">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="lp-review.php">
        <button type="submit" class="btn-approve">&#x2713; Approve</button>
        <div class="note-area">
          <textarea class="note-input" name="note" placeholder="Optional note for VP&hellip;"></textarea>
        </div>
      </form>
      <form method="POST" action="lp-action.php" onsubmit="return confirm('Reject this voucher?')">
        <input type="hidden" name="action"     value="reject">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="lp-review.php">
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
    <?= _lpStatusBadge($v['status']) ?>
    <div class="action-row" style="margin-top:.85rem;">
      <form method="POST" action="lp-action.php">
        <input type="hidden" name="action"     value="mark_paid">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="lp-review.php">
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

  <?php endif; // isTreasurer ?>

  <?php
  // ── VP: awaiting second approval ────────────────────────────────────────────
  if ($isVP):
  ?>
  <div class="sec-head">
    Awaiting Your Signature (Vice-President)
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
    <?= _lpStatusBadge($v['status']) ?>
    <div class="action-row">
      <form method="POST" action="lp-action.php">
        <input type="hidden" name="action"     value="vp_approve">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="lp-review.php">
        <button type="submit" class="btn-approve">&#x2713; Approve &amp; Sign</button>
        <div class="note-area">
          <textarea class="note-input" name="note" placeholder="Optional note&hellip;"></textarea>
        </div>
      </form>
      <form method="POST" action="lp-action.php" onsubmit="return confirm('Reject this voucher?')">
        <input type="hidden" name="action"     value="reject">
        <input type="hidden" name="voucher_id" value="<?= (int)$v['id'] ?>">
        <input type="hidden" name="redirect"   value="lp-review.php">
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

</div>
</body>
</html>
