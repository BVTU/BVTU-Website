<?php
/**
 * exp-treasurer.php — Treasurer review queue and payment dashboard
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

$notice = $_GET['notice'] ?? '';
$error  = $_GET['error']  ?? '';

$pendingExpenses  = expGetAll(['status' => 'pending'],  200, 0);
$readyToPayItems  = expGetAll(['status' => 'signer2_approved'], 200, 0);

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Treasurer Review — BVTU Expenses</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .notice-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box  { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }

    .section-hdr { font-size: 1.05rem; font-weight: 800; color: var(--gray-800); margin: 2rem 0 1rem; display: flex; align-items: center; gap: .6rem; }
    .badge-count { background: var(--primary); color: #fff; font-size: .72rem; font-weight: 800; border-radius: 100px; padding: .15rem .55rem; }

    /* Expense review card */
    .exp-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
    .exp-card-header { display: flex; gap: 1rem; justify-content: space-between; flex-wrap: wrap; margin-bottom: .9rem; align-items: flex-start; }
    .exp-member { font-size: 1rem; font-weight: 800; color: var(--gray-800); }
    .exp-meta   { font-size: .82rem; color: var(--gray-500); margin-top: .15rem; }
    .exp-amount { font-size: 1.4rem; font-weight: 900; color: var(--primary); text-align: right; }
    .exp-amount .ref { font-size: .72rem; font-weight: 700; font-family: monospace; color: var(--gray-400); display: block; }
    .exp-desc { font-size: .9rem; color: var(--gray-700); margin-bottom: .75rem; line-height: 1.5; }

    .receipt-link { font-size: .82rem; color: var(--primary); }
    .receipt-link:hover { text-decoration: underline; }

    .concerns-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: 7px; padding: .6rem .9rem; font-size: .82rem; color: #92400e; margin-bottom: .75rem; }

    /* Action buttons */
    .action-row { display: flex; gap: .75rem; align-items: flex-start; flex-wrap: wrap; margin-top: .75rem; }
    .approve-area, .reject-area { flex: 1; min-width: 200px; }
    .note-input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .5rem .7rem; font-size: .85rem; font-family: inherit; resize: vertical; min-height: 60px; margin-top: .5rem; box-sizing: border-box; }
    .note-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }

    /* Payment instruction card */
    .payment-card { background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1rem; }
    .payment-card h3 { font-size: .85rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #166534; margin: 0 0 .75rem; }
    .etransfer-box { background: #fff; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: .9rem; font-family: monospace; }
    .etransfer-box .row { display: flex; padding: .3rem 0; }
    .etransfer-box .lbl { color: #4ade80; font-size: .82rem; width: 90px; flex-shrink: 0; }
    .etransfer-box .val { color: #14532d; font-weight: 700; font-size: .92rem; }

    .mark-paid-form { display: none; margin-top: .75rem; }
    .mark-paid-form.open { display: block; }
    .empty-section { text-align: center; padding: 2rem; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; color: var(--gray-500); font-size: .9rem; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>Treasurer Review Queue</h1>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <a href="exp-payments.php" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">Payment Records</a>
    </div>
  </div>

  <?php if ($notice): ?>
  <div class="notice-box">&#x2713; <?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ── Pending Review ── -->
  <div class="section-hdr">
    Pending Review
    <?php if ($pendingExpenses): ?>
    <span class="badge-count"><?= count($pendingExpenses) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$pendingExpenses): ?>
  <div class="empty-section">No expenses awaiting review. &#x2713;</div>
  <?php endif; ?>

  <?php foreach ($pendingExpenses as $exp): ?>
  <div class="exp-card">
    <div class="exp-card-header">
      <div>
        <div class="exp-member"><?= htmlspecialchars($exp['user_name']) ?></div>
        <div class="exp-meta">
          <?= htmlspecialchars($exp['user_email']) ?> &middot;
          <?= htmlspecialchars($exp['expense_date']) ?> &middot;
          <?= htmlspecialchars($catLabels[$exp['category']] ?? ucfirst($exp['category'])) ?>
        </div>
      </div>
      <div class="exp-amount">
        $<?= number_format((float)$exp['amount'], 2) ?>
        <span class="ref"><?= htmlspecialchars($exp['ref_code']) ?></span>
      </div>
    </div>

    <div class="exp-desc"><?= nl2br(htmlspecialchars($exp['description'])) ?></div>

    <?php if ($exp['receipt_path']): ?>
    <div style="margin-bottom:.75rem;">
      <a href="exp-receipt.php?f=<?= urlencode($exp['receipt_path']) ?>" target="_blank" class="receipt-link">
        &#x1F4C4; View Receipt
      </a>
      <?php if ($exp['extracted_vendor']): ?>
      <span style="font-size:.78rem;color:var(--gray-400);margin-left:.75rem;">
        Vendor: <?= htmlspecialchars($exp['extracted_vendor']) ?>
        <?php if ($exp['extracted_amount']): ?>
        &middot; $<?= number_format((float)$exp['extracted_amount'], 2) ?>
        <?php endif; ?>
      </span>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div style="font-size:.8rem;color:var(--gray-400);margin-bottom:.75rem;">No receipt provided</div>
    <?php endif; ?>

    <?php if ($exp['extraction_concerns']): ?>
    <div class="concerns-banner">&#x26A0;&#xFE0F; <strong>AI flag:</strong> <?= htmlspecialchars($exp['extraction_concerns']) ?></div>
    <?php endif; ?>

    <div class="action-row">
      <!-- Approve -->
      <div class="approve-area">
        <form method="POST" action="exp-action.php" id="form-approve-<?= $exp['id'] ?>">
          <input type="hidden" name="action"     value="signer1_approve">
          <input type="hidden" name="expense_id" value="<?= (int)$exp['id'] ?>">
          <input type="hidden" name="redirect"   value="exp-treasurer.php">
          <button type="button" class="btn btn-primary" style="padding:.5rem 1rem;font-size:.88rem;"
            onclick="showApproveNote(<?= $exp['id'] ?>)">
            &#x2713; Approve
          </button>
          <div id="approve-note-<?= $exp['id'] ?>" style="display:none;margin-top:.5rem;">
            <textarea class="note-input" name="note" placeholder="Optional note to second signer&hellip;"></textarea>
            <button type="submit" class="btn btn-primary" style="margin-top:.4rem;padding:.45rem .9rem;font-size:.85rem;">
              Confirm Approve
            </button>
          </div>
        </form>
      </div>

      <!-- Reject -->
      <div class="reject-area">
        <form method="POST" action="exp-action.php" id="form-reject-<?= $exp['id'] ?>">
          <input type="hidden" name="action"     value="signer1_reject">
          <input type="hidden" name="expense_id" value="<?= (int)$exp['id'] ?>">
          <input type="hidden" name="redirect"   value="exp-treasurer.php">
          <button type="button" class="btn" style="padding:.5rem 1rem;font-size:.88rem;background:#fef2f2;color:#991b1b;border-color:#fecaca;"
            onclick="showRejectNote(<?= $exp['id'] ?>)">
            &#x2715; Reject
          </button>
          <div id="reject-note-<?= $exp['id'] ?>" style="display:none;margin-top:.5rem;">
            <textarea class="note-input" name="note" placeholder="Reason for rejection (required)&hellip;" required></textarea>
            <button type="submit" class="btn" style="margin-top:.4rem;padding:.45rem .9rem;font-size:.85rem;background:#dc2626;color:#fff;border-color:#dc2626;">
              Confirm Reject
            </button>
          </div>
        </form>
      </div>

      <a href="exp-view.php?id=<?= (int)$exp['id'] ?>" style="font-size:.82rem;color:var(--gray-500);align-self:flex-start;padding-top:.5rem;">Details &#x2192;</a>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- ── Ready to Pay ── -->
  <div class="section-hdr" style="margin-top:2.5rem;">
    Ready to Pay
    <?php if ($readyToPayItems): ?>
    <span class="badge-count"><?= count($readyToPayItems) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$readyToPayItems): ?>
  <div class="empty-section">No expenses awaiting payment. &#x2713;</div>
  <?php endif; ?>

  <?php foreach ($readyToPayItems as $exp): ?>
  <div class="payment-card">
    <h3>&#x1F4B8; Send E-Transfer</h3>
    <div class="etransfer-box">
      <div class="row"><span class="lbl">To:</span><span class="val"><?= htmlspecialchars($exp['user_email']) ?></span></div>
      <div class="row"><span class="lbl">Amount:</span><span class="val">$<?= number_format((float)$exp['amount'], 2) ?></span></div>
      <div class="row"><span class="lbl">Message:</span><span class="val"><?= htmlspecialchars($exp['ref_code']) ?></span></div>
    </div>
    <div style="font-size:.82rem;color:#166534;margin-bottom:.6rem;">
      <strong><?= htmlspecialchars($exp['user_name']) ?></strong> &mdash;
      <?= htmlspecialchars($catLabels[$exp['category']] ?? ucfirst($exp['category'])) ?> &mdash;
      <?= htmlspecialchars($exp['description']) ?>
    </div>
    <?php if ($exp['receipt_path']): ?>
    <div style="margin-bottom:.6rem;">
      <a href="exp-receipt.php?f=<?= urlencode($exp['receipt_path']) ?>" target="_blank" class="receipt-link">
        &#x1F4C4; View Receipt
      </a>
    </div>
    <?php endif; ?>

    <form method="POST" action="exp-action.php">
      <input type="hidden" name="action"     value="mark_paid">
      <input type="hidden" name="expense_id" value="<?= (int)$exp['id'] ?>">
      <input type="hidden" name="redirect"   value="exp-treasurer.php">
      <button type="button" class="btn btn-primary" style="padding:.5rem 1rem;font-size:.88rem;"
        onclick="togglePaidForm(<?= $exp['id'] ?>)">
        Mark as Paid
      </button>
      <div id="paid-form-<?= $exp['id'] ?>" class="mark-paid-form">
        <textarea class="note-input" name="note" placeholder="e-Transfer confirmation # or note&hellip;"></textarea>
        <button type="submit" class="btn btn-primary" style="margin-top:.4rem;padding:.45rem .9rem;font-size:.85rem;">
          &#x2713; Confirm Payment Sent
        </button>
      </div>
    </form>
  </div>
  <?php endforeach; ?>

</div>

<script>
function showApproveNote(id) {
    document.getElementById('approve-note-' + id).style.display = 'block';
}
function showRejectNote(id) {
    document.getElementById('reject-note-' + id).style.display = 'block';
}
function togglePaidForm(id) {
    var f = document.getElementById('paid-form-' + id);
    f.classList.toggle('open');
}
</script>
</body>
</html>
