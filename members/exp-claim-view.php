<?php
/**
 * exp-claim-view.php — Read-only detail view for a multi-item expense claim
 *
 * Shows every line item, the combined total, and the single dual-signature
 * approval / e-transfer status for the whole claim.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();
expBatchEnsureTables();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: exp-dashboard.php'); exit; }

$batch = expBatchGet($id);
if (!$batch) { header('Location: exp-dashboard.php'); exit; }

$myEmail        = strtolower(trim($member['email']));
$isOwner        = strtolower($batch['user_email']) === $myEmail;
$isSubmitter    = strtolower(trim($batch['submitted_by_email'] ?? '')) === $myEmail;
$canManage      = $isOwner || $isSubmitter || expCanSubmitOnBehalf($member['email']);
$canReview      = expCanReview($member['email']) || expIsAdmin($member['email']);

if (!$isOwner && !$isSubmitter && !$canReview) {
    header('Location: exp-dashboard.php');
    exit;
}

$notice = $_GET['notice'] ?? '';
$error  = $_GET['error']  ?? '';

// ── Submit for approval (owner / submitter only, draft status) ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if ($batch['status'] === 'draft' && $canManage) {
        try {
            $items = expBatchGetItems($id);
            if (count($items) === 0) {
                $error = 'Add at least one expense item before submitting.';
            } else {
                $total = expBatchTotal($id);
                expBatchSubmit($id);
                $batch = expBatchGet($id);
                expBatchEmailSubmitted($batch, $total, count($items));
                header('Location: exp-claim-view.php?id=' . $id . '&submitted=1');
                exit;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

// ── Delete a draft claim (owner only) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if ($batch['status'] === 'draft' && $isOwner) {
        $db = getDB();
        $db->prepare("DELETE FROM exp_batch_items WHERE batch_id=?")->execute([$id]);
        $db->prepare("DELETE FROM exp_batches WHERE id=?")->execute([$id]);
        header('Location: exp-dashboard.php?deleted=1');
        exit;
    }
}

if (!empty($_GET['submitted'])) {
    $notice = 'Claim submitted — the Treasurer has been notified for the first signature.';
}

$items = expBatchGetItems($id);
$total = expBatchTotal($id);

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference / Workshop',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];

function _expClaimStatusBadge(string $status): string {
    $map = [
        'draft'             => ['#f1f5f9', '#64748b', 'Draft'],
        'pending'           => ['#fffbeb', '#d97706', 'Awaiting Treasurer'],
        'signer1_approved'  => ['#eff6ff', '#1e40af', 'Awaiting Second Signature'],
        'signer2_approved'  => ['#f0fdf4', '#166534', 'Ready to Pay'],
        'paid'              => ['#f0fdf4', '#166534', 'Paid'],
        'rejected'          => ['#fef2f2', '#991b1b', 'Rejected'],
    ];
    $s = $map[$status] ?? ['#f8f9fa', '#555', ucfirst($status)];
    return '<span style="display:inline-block;font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;'
         . 'padding:.25rem .7rem;border-radius:100px;background:' . $s[0] . ';color:' . $s[1] . ';">' . $s[2] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($batch['title'] ?: $batch['ref_code']) ?> — BVTU Expenses</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0 0 .25rem; }
    .portal-header .sub { font-size: .82rem; color: var(--gray-500); }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .action-bar { display: flex; gap: .6rem; flex-wrap: wrap; justify-content: flex-end; }

    .notice-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .8rem 1rem; margin-bottom: 1.25rem; font-size: .88rem; color: #166534; font-weight: 600; }
    .error-box  { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .8rem 1rem; margin-bottom: 1.25rem; font-size: .88rem; color: #991b1b; font-weight: 600; }

    .summary-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: .85rem; margin-bottom: 1.5rem; }
    .sum-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: .9rem 1.1rem; }
    .sum-card .lbl { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-400); margin-bottom: .3rem; }
    .sum-card .val { font-size: 1.15rem; font-weight: 800; color: var(--gray-800); }
    .sum-card.total { background: var(--primary); border-color: var(--primary); }
    .sum-card.total .lbl { color: rgba(255,255,255,.7); }
    .sum-card.total .val { color: #fff; font-size: 1.5rem; }

    .table-wrap { overflow-x: auto; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; margin-bottom: 1.5rem; }
    table.etable { width: 100%; border-collapse: collapse; min-width: 760px; font-size: .85rem; }
    .etable thead th { background: #1a2e1a; color: #fff; padding: .6rem .75rem; text-align: left; font-size: .71rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
    .etable thead th.r { text-align: right; }
    .etable tbody tr { border-bottom: 1px solid var(--gray-100); }
    .etable tbody tr:last-child { border-bottom: none; }
    .etable td { padding: .6rem .75rem; vertical-align: top; }
    .etable td.r { text-align: right; }
    .etable td.bold { font-weight: 800; color: var(--primary); }
    .etable tfoot td { background: #f0fdf4; font-weight: 800; padding: .7rem .75rem; }
    .etable tfoot td.r { text-align: right; color: var(--primary); font-size: .95rem; }

    .receipt-btn { display: inline-flex; align-items: center; gap: .25rem; background: var(--accent); border: 1px solid #b8ddc5; color: var(--primary); font-size: .74rem; font-weight: 700; border-radius: 5px; padding: .25rem .55rem; text-decoration: none; }
    .receipt-btn:hover { background: #b8ddc5; }
    .flag-note { font-size: .74rem; color: #92400e; margin-top: .3rem; }

    .timeline { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.25rem 1.5rem; }
    .timeline h3 { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--primary); margin: 0 0 1rem; }
    .tl-step { display: flex; gap: .85rem; padding: .55rem 0; border-bottom: 1px solid var(--gray-100); align-items: flex-start; }
    .tl-step:last-child { border-bottom: none; }
    .tl-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: .35rem; flex-shrink: 0; }
    .tl-dot.done { background: var(--primary); }
    .tl-dot.pending { background: var(--gray-200); }
    .tl-dot.rejected { background: #dc2626; }
    .tl-label { font-size: .88rem; font-weight: 700; color: var(--gray-800); }
    .tl-meta { font-size: .8rem; color: var(--gray-500); margin-top: .1rem; }
    .tl-note { font-size: .8rem; color: var(--gray-500); margin-top: .25rem; font-style: italic; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="portal-header">
    <div>
      <h1><?= htmlspecialchars($batch['title'] ?: 'Expense Claim') ?></h1>
      <div class="sub">
        Ref <strong><?= htmlspecialchars($batch['ref_code']) ?></strong>
        &middot; <?= htmlspecialchars($batch['user_name']) ?> (<?= htmlspecialchars($batch['user_email']) ?>)
        <?php if (!empty($batch['submitted_by_email']) && strtolower($batch['submitted_by_email']) !== strtolower($batch['user_email'])): ?>
        &middot; submitted by <?= htmlspecialchars($batch['submitted_by_name'] ?: $batch['submitted_by_email']) ?>
        <?php endif; ?>
        &middot; <?= _expClaimStatusBadge($batch['status']) ?>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:.6rem;align-items:flex-end;">
      <div class="action-bar">
        <?php if ($batch['status'] === 'draft' && $canManage): ?>
        <a href="exp-submit.php?id=<?= $id ?>" class="btn btn-outline" style="padding:.5rem .9rem;font-size:.85rem;">&#x270F; Edit Items</a>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Submit this claim for Treasurer review? You can still edit it before they sign.');">
          <input type="hidden" name="action" value="submit">
          <button type="submit" class="btn btn-primary" style="padding:.5rem 1rem;font-size:.85rem;">&#x1F4E4; Submit for Approval</button>
        </form>
        <?php if ($isOwner): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this draft claim and its items? This cannot be undone.');">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn btn-outline" style="padding:.5rem .9rem;font-size:.85rem;color:#dc2626;border-color:#fecaca;">&#x1F5D1; Delete</button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($canReview && in_array($batch['status'], ['pending','signer1_approved','signer2_approved'], true)): ?>
        <a href="exp-claim-review.php" class="btn btn-outline" style="padding:.5rem .9rem;font-size:.85rem;">Review Queue &#x2192;</a>
        <?php endif; ?>
      </div>
      <a class="back-link" href="exp-dashboard.php">&#x2190; My Expenses</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice-box">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="summary-row">
    <div class="sum-card">
      <div class="lbl">Items</div>
      <div class="val"><?= count($items) ?></div>
    </div>
    <div class="sum-card">
      <div class="lbl">Status</div>
      <div class="val" style="font-size:.92rem;"><?= _expClaimStatusBadge($batch['status']) ?></div>
    </div>
    <?php if ($batch['submitted_at']): ?>
    <div class="sum-card">
      <div class="lbl">Submitted</div>
      <div class="val" style="font-size:.95rem;"><?= date('M j, Y', strtotime($batch['submitted_at'])) ?></div>
    </div>
    <?php endif; ?>
    <div class="sum-card total">
      <div class="lbl">Claim Total</div>
      <div class="val">$<?= number_format($total, 2) ?></div>
    </div>
  </div>

  <div class="table-wrap">
    <table class="etable">
      <thead>
        <tr>
          <th>Date</th>
          <th>Category</th>
          <th>Description</th>
          <th>Receipt</th>
          <th class="r">Amount</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$items): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--gray-400);padding:2rem;">No items have been added to this claim yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $it): ?>
        <tr>
          <td style="white-space:nowrap;"><?= $it['expense_date'] ? date('M j, Y', strtotime($it['expense_date'])) : '—' ?></td>
          <td><?= htmlspecialchars($catLabels[$it['category']] ?? ucfirst($it['category'])) ?></td>
          <td>
            <?= nl2br(htmlspecialchars($it['description'] ?? '')) ?>
            <?php $flagText = trim((string)($it['extraction_concerns'] ?? '')) ?: trim((string)($it['extraction_flag'] ?? ''));
                  if ($flagText): ?>
            <div class="flag-note">&#x26A0; <?= htmlspecialchars($flagText) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($it['receipt_path']): ?>
            <a class="receipt-btn" href="exp-receipt.php?f=<?= urlencode($it['receipt_path']) ?>" target="_blank">&#x1F4C4; View</a>
            <?php else: ?>
            <span style="color:var(--gray-300);font-size:.78rem;">No receipt</span>
            <?php endif; ?>
          </td>
          <td class="r bold">$<?= number_format((float)$it['amount'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($items): ?>
      <tfoot>
        <tr>
          <td colspan="4" style="text-transform:uppercase;font-size:.72rem;letter-spacing:.05em;color:var(--gray-500);">Claim Total</td>
          <td class="r">$<?= number_format($total, 2) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <!-- Approval / payment timeline -->
  <div class="timeline">
    <h3>Approval &amp; Payment</h3>

    <div class="tl-step">
      <div class="tl-dot done"></div>
      <div>
        <div class="tl-label">Submitted</div>
        <div class="tl-meta">
          <?php if ($batch['submitted_at']): ?>
            by <?= htmlspecialchars($batch['submitted_by_name'] ?: $batch['user_name']) ?>
            on <?= date('M j, Y \a\t g:ia', strtotime($batch['submitted_at'])) ?>
          <?php else: ?>
            Not yet submitted — this claim is still a draft.
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($batch['status'] === 'rejected'): ?>
    <div class="tl-step">
      <div class="tl-dot rejected"></div>
      <div>
        <div class="tl-label">Rejected</div>
        <div class="tl-meta">
          by <?= htmlspecialchars($batch['rejected_by_name'] ?: '—') ?>
          <?php if ($batch['rejected_at']): ?> on <?= date('M j, Y \a\t g:ia', strtotime($batch['rejected_at'])) ?><?php endif; ?>
        </div>
        <?php if ($batch['rejection_note']): ?><div class="tl-note">&#x201C;<?= htmlspecialchars($batch['rejection_note']) ?>&#x201D;</div><?php endif; ?>
      </div>
    </div>
    <?php else: ?>

    <div class="tl-step">
      <div class="tl-dot <?= $batch['signer1_email'] ? 'done' : 'pending' ?>"></div>
      <div>
        <div class="tl-label">First signature — Treasurer</div>
        <div class="tl-meta">
          <?php if ($batch['signer1_email']): ?>
            Approved by <?= htmlspecialchars($batch['signer1_name']) ?>
            on <?= date('M j, Y \a\t g:ia', strtotime($batch['signer1_at'])) ?>
          <?php elseif ($batch['status'] === 'pending'): ?>
            Awaiting Treasurer review
          <?php else: ?>
            Not yet reached
          <?php endif; ?>
        </div>
        <?php if ($batch['signer1_note']): ?><div class="tl-note">&#x201C;<?= htmlspecialchars($batch['signer1_note']) ?>&#x201D;</div><?php endif; ?>
      </div>
    </div>

    <div class="tl-step">
      <div class="tl-dot <?= $batch['signer2_email'] ? 'done' : 'pending' ?>"></div>
      <div>
        <div class="tl-label">Second signature</div>
        <div class="tl-meta">
          <?php if ($batch['signer2_email']): ?>
            Approved by <?= htmlspecialchars($batch['signer2_name']) ?>
            on <?= date('M j, Y \a\t g:ia', strtotime($batch['signer2_at'])) ?>
          <?php elseif ($batch['status'] === 'signer1_approved'): ?>
            Awaiting second signature
          <?php else: ?>
            Not yet reached
          <?php endif; ?>
        </div>
        <?php if ($batch['signer2_note']): ?><div class="tl-note">&#x201C;<?= htmlspecialchars($batch['signer2_note']) ?>&#x201D;</div><?php endif; ?>
      </div>
    </div>

    <div class="tl-step">
      <div class="tl-dot <?= $batch['status'] === 'paid' ? 'done' : 'pending' ?>"></div>
      <div>
        <div class="tl-label">E-transfer sent</div>
        <div class="tl-meta">
          <?php if ($batch['status'] === 'paid'): ?>
            Sent by <?= htmlspecialchars($batch['paid_by_name']) ?>
            on <?= date('M j, Y \a\t g:ia', strtotime($batch['paid_at'])) ?>
            &mdash; one e-transfer for <strong>$<?= number_format($total, 2) ?></strong> to <?= htmlspecialchars($batch['user_email']) ?>
          <?php elseif ($batch['status'] === 'signer2_approved'): ?>
            Ready — Treasurer will send one e-transfer for $<?= number_format($total, 2) ?>
          <?php else: ?>
            Not yet reached
          <?php endif; ?>
        </div>
        <?php if ($batch['payment_note']): ?><div class="tl-note">&#x201C;<?= htmlspecialchars($batch['payment_note']) ?>&#x201D;</div><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
