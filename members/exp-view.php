<?php
/**
 * exp-view.php — Single expense detail view
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: exp-dashboard.php');
    exit;
}

$exp = expGet($id);
if (!$exp) {
    header('Location: exp-dashboard.php');
    exit;
}

$isOwner   = strtolower($exp['user_email']) === strtolower($member['email']);
$canReview = expCanReview($member['email']);

if (!$isOwner && !$canReview) {
    header('Location: exp-dashboard.php');
    exit;
}

$submitted = isset($_GET['submitted']);
$notice    = $_GET['notice'] ?? '';

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference / Workshop',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];

// Timeline steps
$step1Done = !in_array($exp['status'], ['pending', 'draft']);
$step2Done = in_array($exp['status'], ['signer2_approved', 'paid']);
$step3Done = $exp['status'] === 'paid';
$rejected  = $exp['status'] === 'rejected';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($exp['ref_code']) ?> — BVTU Expenses</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .notice-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .alert-box  { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }

    .detail-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .detail-card h2 { font-size: .85rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); margin: 0 0 1rem; }
    .detail-row { display: flex; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid var(--gray-100); align-items: flex-start; }
    .detail-row:last-child { border-bottom: none; padding-bottom: 0; }
    .detail-lbl { font-size: .78rem; font-weight: 700; color: var(--gray-500); width: 140px; flex-shrink: 0; padding-top: .05rem; }
    .detail-val { font-size: .92rem; color: var(--gray-800); font-weight: 500; flex: 1; }
    .amount-big { font-size: 1.4rem; font-weight: 900; color: var(--primary); }
    .ref-code   { font-family: monospace; font-size: 1.1rem; font-weight: 700; }

    /* Status badge */
    .status-badge { display: inline-block; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .3rem .8rem; border-radius: 100px; }

    /* Timeline */
    .timeline { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .timeline h2 { font-size: .85rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); margin: 0 0 1.25rem; }
    .tl-steps { display: flex; align-items: flex-start; gap: 0; }
    .tl-step { flex: 1; position: relative; text-align: center; }
    .tl-step .dot { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .85rem; font-weight: 700; margin: 0 auto .5rem; border: 2px solid var(--gray-200); background: #fff; color: var(--gray-400); }
    .tl-step.done .dot { background: var(--primary); border-color: var(--primary); color: #fff; }
    .tl-step.active .dot { border-color: var(--primary); color: var(--primary); background: #fff; }
    .tl-step.rejected-step .dot { background: #dc2626; border-color: #dc2626; color: #fff; }
    .tl-step .label { font-size: .75rem; font-weight: 700; color: var(--gray-500); }
    .tl-step.done .label, .tl-step.active .label { color: var(--primary); }
    .tl-step.rejected-step .label { color: #dc2626; }
    .tl-step .sub { font-size: .68rem; color: var(--gray-400); margin-top: .15rem; }
    .tl-connector { flex: 0 0 30px; height: 2px; background: var(--gray-200); margin-top: 15px; }
    .tl-connector.done { background: var(--primary); }

    /* Payment authorized card */
    .auth-card { background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .auth-card h3 { font-size: 1rem; font-weight: 800; color: #166534; margin: 0 0 1rem; display: flex; align-items: center; gap: .5rem; }
    .auth-card .row { display: flex; gap: 1rem; padding: .45rem 0; border-bottom: 1px solid #bbf7d0; }
    .auth-card .row:last-child { border-bottom: none; }
    .auth-card .lbl { font-size: .78rem; font-weight: 700; color: #4ade80; width: 140px; flex-shrink: 0; }
    .auth-card .val { font-size: .92rem; color: #14532d; font-weight: 600; }

    /* Paid card */
    .paid-card { background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .paid-card h3 { font-size: 1rem; font-weight: 800; color: #166534; margin: 0 0 1rem; }
    .paid-card .row { display: flex; gap: 1rem; padding: .45rem 0; border-bottom: 1px solid #bbf7d0; }
    .paid-card .row:last-child { border-bottom: none; }
    .paid-card .lbl { font-size: .78rem; font-weight: 700; color: #4ade80; width: 140px; flex-shrink: 0; }
    .paid-card .val { font-size: .92rem; color: #14532d; font-weight: 600; }

    /* Rejection card */
    .reject-card { background: #fef2f2; border: 2px solid #fecaca; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .reject-card h3 { font-size: 1rem; font-weight: 800; color: #991b1b; margin: 0 0 .75rem; }
    .reject-card p { font-size: .9rem; color: #7f1d1d; line-height: 1.6; }

    /* Concerns flag */
    .concerns-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: .75rem 1rem; font-size: .82rem; color: #92400e; margin-bottom: 1.25rem; }

    /* Receipt thumbnail */
    .receipt-thumb { border: 1px solid var(--gray-200); border-radius: 8px; overflow: hidden; display: inline-block; max-width: 200px; }
    .receipt-thumb img { width: 100%; height: auto; display: block; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1><span class="ref-code"><?= htmlspecialchars($exp['ref_code']) ?></span></h1>
    <a class="back-link" href="<?= $isOwner ? 'exp-dashboard.php' : 'exp-treasurer.php' ?>">
      &#x2190; <?= $isOwner ? 'My Expenses' : 'Review Queue' ?>
    </a>
  </div>

  <?php if ($submitted): ?>
  <div class="notice-box">&#x2713; Your expense has been submitted and is awaiting review by the Treasurer.</div>
  <?php endif; ?>

  <?php if ($notice): ?>
  <div class="notice-box">&#x2713; <?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <?php if (!empty($exp['extraction_concerns'])): ?>
  <div class="concerns-banner">&#x26A0;&#xFE0F; <strong>AI reviewer flag:</strong> <?= htmlspecialchars($exp['extraction_concerns']) ?></div>
  <?php endif; ?>

  <!-- ── Payment Authorized ── -->
  <?php if ($exp['status'] === 'signer2_approved'): ?>
  <div class="auth-card">
    <h3>&#x1F4B8; Payment Authorized</h3>
    <div class="row"><span class="lbl">Payee email</span><span class="val"><?= htmlspecialchars($exp['user_email']) ?></span></div>
    <div class="row"><span class="lbl">Amount</span><span class="val">$<?= number_format((float)$exp['amount'], 2) ?></span></div>
    <div class="row"><span class="lbl">Reference</span><span class="val"><?= htmlspecialchars($exp['ref_code']) ?></span></div>
    <div class="row"><span class="lbl">Status</span><span class="val">Treasurer will send e-transfer shortly</span></div>
  </div>
  <?php endif; ?>

  <!-- ── Paid ── -->
  <?php if ($exp['status'] === 'paid'): ?>
  <div class="paid-card">
    <h3>&#x2705; Payment Sent</h3>
    <div class="row"><span class="lbl">Date paid</span><span class="val"><?= date('F j, Y', strtotime($exp['paid_at'])) ?></span></div>
    <div class="row"><span class="lbl">Paid by</span><span class="val"><?= htmlspecialchars($exp['paid_by_name']) ?></span></div>
    <div class="row"><span class="lbl">Amount</span><span class="val">$<?= number_format((float)$exp['amount'], 2) ?></span></div>
    <?php if ($exp['payment_note']): ?>
    <div class="row"><span class="lbl">Note</span><span class="val"><?= htmlspecialchars($exp['payment_note']) ?></span></div>
    <?php endif; ?>
    <div style="margin-top:1rem;">
      <a href="exp-receipt-print.php?id=<?= (int)$exp['id'] ?>" class="btn btn-primary" style="padding:.5rem 1rem;font-size:.88rem;" target="_blank">
        &#x1F9FE; View / Print Receipt
      </a>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Rejected ── -->
  <?php if ($rejected): ?>
  <div class="reject-card">
    <h3>&#x274C; Expense Rejected</h3>
    <p><strong>Rejected by:</strong> <?= htmlspecialchars($exp['rejected_by_name']) ?>
       &mdash; <?= htmlspecialchars(date('F j, Y', strtotime($exp['rejected_at']))) ?></p>
    <p><strong>Reason:</strong> <?= htmlspecialchars($exp['rejection_note']) ?></p>
  </div>
  <?php endif; ?>

  <!-- ── Status Timeline ── -->
  <div class="timeline">
    <h2>Status Timeline</h2>
    <div class="tl-steps">
      <div class="tl-step done">
        <div class="dot">&#x2713;</div>
        <div class="label">Submitted</div>
        <div class="sub"><?= date('M j', strtotime($exp['created_at'])) ?></div>
      </div>

      <div class="tl-connector <?= $step1Done ? 'done' : '' ?>"></div>

      <div class="tl-step <?= $rejected ? 'rejected-step' : ($step1Done ? 'done' : 'active') ?>">
        <div class="dot"><?= $rejected ? '&#x2715;' : ($step1Done ? '&#x2713;' : '1') ?></div>
        <div class="label"><?= $rejected ? 'Rejected' : 'Treasurer' ?></div>
        <?php if ($exp['signer1_at']): ?>
        <div class="sub"><?= date('M j', strtotime($exp['signer1_at'])) ?></div>
        <?php elseif ($rejected && $exp['rejected_at']): ?>
        <div class="sub"><?= date('M j', strtotime($exp['rejected_at'])) ?></div>
        <?php endif; ?>
      </div>

      <div class="tl-connector <?= $step2Done ? 'done' : '' ?>"></div>

      <div class="tl-step <?= $step2Done ? 'done' : ($step1Done && !$rejected ? 'active' : '') ?>">
        <div class="dot"><?= $step2Done ? '&#x2713;' : '2' ?></div>
        <div class="label">VP / President</div>
        <?php if ($exp['signer2_at']): ?>
        <div class="sub"><?= date('M j', strtotime($exp['signer2_at'])) ?></div>
        <?php endif; ?>
      </div>

      <div class="tl-connector <?= $step3Done ? 'done' : '' ?>"></div>

      <div class="tl-step <?= $step3Done ? 'done' : ($step2Done ? 'active' : '') ?>">
        <div class="dot"><?= $step3Done ? '&#x2713;' : '$' ?></div>
        <div class="label">Paid</div>
        <?php if ($exp['paid_at']): ?>
        <div class="sub"><?= date('M j', strtotime($exp['paid_at'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Expense Details ── -->
  <div class="detail-card">
    <h2>Expense Details</h2>
    <div class="detail-row">
      <span class="detail-lbl">Reference</span>
      <span class="detail-val ref-code"><?= htmlspecialchars($exp['ref_code']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-lbl">Member</span>
      <span class="detail-val"><?= htmlspecialchars($exp['user_name']) ?> &lt;<?= htmlspecialchars($exp['user_email']) ?>&gt;</span>
    </div>
    <div class="detail-row">
      <span class="detail-lbl">Date</span>
      <span class="detail-val"><?= htmlspecialchars($exp['expense_date']) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-lbl">Category</span>
      <span class="detail-val"><?= htmlspecialchars($catLabels[$exp['category']] ?? ucfirst($exp['category'])) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-lbl">Amount</span>
      <span class="detail-val amount-big">$<?= number_format((float)$exp['amount'], 2) ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-lbl">Description</span>
      <span class="detail-val"><?= nl2br(htmlspecialchars($exp['description'])) ?></span>
    </div>
    <?php if ($exp['receipt_path']): ?>
    <div class="detail-row">
      <span class="detail-lbl">Receipt</span>
      <span class="detail-val">
        <a href="exp-receipt.php?f=<?= urlencode($exp['receipt_path']) ?>" target="_blank" style="color:var(--primary);">
          &#x1F4C4; View Receipt
        </a>
        <?php if ($exp['extracted_vendor']): ?>
        <span style="font-size:.8rem;color:var(--gray-500);margin-left:.75rem;">
          Vendor: <?= htmlspecialchars($exp['extracted_vendor']) ?>
          <?php if ($exp['extracted_amount']): ?>
          &middot; $<?= number_format((float)$exp['extracted_amount'], 2) ?>
          <?php endif; ?>
        </span>
        <?php endif; ?>
      </span>
    </div>
    <?php endif; ?>
    <div class="detail-row">
      <span class="detail-lbl">Submitted</span>
      <span class="detail-val"><?= date('F j, Y g:i a', strtotime($exp['created_at'])) ?></span>
    </div>
  </div>

  <!-- ── Signer notes ── -->
  <?php if ($exp['signer1_name']): ?>
  <div class="detail-card">
    <h2>Approval Notes</h2>
    <?php if ($exp['signer1_name']): ?>
    <div class="detail-row">
      <span class="detail-lbl">Signer 1 (Treasurer)</span>
      <span class="detail-val">
        <?= htmlspecialchars($exp['signer1_name']) ?>
        <?php if ($exp['signer1_at']): ?>
        <span style="font-size:.78rem;color:var(--gray-400);">&mdash; <?= date('M j, Y', strtotime($exp['signer1_at'])) ?></span>
        <?php endif; ?>
        <?php if ($exp['signer1_note']): ?>
        <br><span style="font-size:.82rem;color:var(--gray-600);"><?= htmlspecialchars($exp['signer1_note']) ?></span>
        <?php endif; ?>
      </span>
    </div>
    <?php endif; ?>
    <?php if ($exp['signer2_name']): ?>
    <div class="detail-row">
      <span class="detail-lbl">Signer 2</span>
      <span class="detail-val">
        <?= htmlspecialchars($exp['signer2_name']) ?>
        <?php if ($exp['signer2_at']): ?>
        <span style="font-size:.78rem;color:var(--gray-400);">&mdash; <?= date('M j, Y', strtotime($exp['signer2_at'])) ?></span>
        <?php endif; ?>
        <?php if ($exp['signer2_note']): ?>
        <br><span style="font-size:.82rem;color:var(--gray-600);"><?= htmlspecialchars($exp['signer2_note']) ?></span>
        <?php endif; ?>
      </span>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
