<?php
/**
 * exp-receipt-print.php — Printable / PDF-saveable expense receipt
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

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference / Workshop',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];

// Check if receipt is an image we can thumbnail
$receiptIsImage = false;
if ($exp['receipt_path']) {
    $ext = strtolower(pathinfo($exp['receipt_path'], PATHINFO_EXTENSION));
    $receiptIsImage = in_array($ext, ['jpg','jpeg','png','webp','gif']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receipt <?= htmlspecialchars($exp['ref_code']) ?> — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; font-family: 'Georgia', serif; }
    .no-print { }

    /* Screen toolbar */
    .toolbar { max-width: 700px; margin: 1.5rem auto .75rem; padding: 0 1.5rem; display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
    .toolbar h1 { font-size: .95rem; font-weight: 700; color: var(--gray-700); margin: 0; flex: 1; }

    /* Receipt card */
    .receipt-wrap { max-width: 700px; margin: 0 auto 3rem; padding: 0 1.5rem; }
    .receipt-card {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 4px;
      padding: 3rem 3.5rem;
      font-size: 13px;
      line-height: 1.5;
      color: #111;
    }

    .org-header { text-align: center; margin-bottom: 2rem; }
    .org-header h1 { font-size: 16px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; margin: 0 0 .25rem; }
    .org-header h2 { font-size: 12px; font-weight: 400; text-transform: uppercase; letter-spacing: .08em; color: #555; margin: 0 0 1.5rem; }
    .divider { border: none; border-top: 2px solid #111; margin: 1.25rem 0; }
    .thin-divider { border: none; border-top: 1px solid #ddd; margin: 1rem 0; }

    .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #555; margin: 1.5rem 0 .5rem; }
    .detail-table { width: 100%; border-collapse: collapse; }
    .detail-table td { padding: .3rem .5rem; vertical-align: top; }
    .detail-table .lbl { width: 160px; font-size: 12px; color: #555; }
    .detail-table .val { font-size: 13px; color: #111; font-weight: 500; }
    .amount-large { font-size: 20px; font-weight: 700; }

    /* Signature lines */
    .sig-row { display: flex; gap: 2rem; margin-top: .75rem; }
    .sig-block { flex: 1; }
    .sig-line { border-bottom: 1px solid #333; height: 24px; margin-bottom: .3rem; position: relative; }
    .sig-name { font-size: 12px; font-weight: 600; position: absolute; bottom: 3px; left: 0; }
    .sig-date { font-size: 11px; color: #555; }

    /* Receipt thumbnail */
    .receipt-thumb { margin: .75rem 0; border: 1px solid #ddd; border-radius: 3px; overflow: hidden; display: inline-block; max-width: 200px; }
    .receipt-thumb img { width: 100%; height: auto; display: block; }

    .footer-note { font-size: 11px; color: #555; text-align: center; margin-top: 1.5rem; line-height: 1.6; }
    .generated { font-size: 10px; color: #999; text-align: center; margin-top: .75rem; }

    /* Print styles */
    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
      .receipt-wrap { margin: 0; padding: 0; max-width: 100%; }
      .receipt-card { border: none; border-radius: 0; padding: 0; box-shadow: none; }
    }

    @page {
      size: letter;
      margin: 2cm;
    }
  </style>
</head>
<body>

  <!-- Screen-only toolbar -->
  <div class="toolbar no-print">
    <h1>Expense Receipt &mdash; <?= htmlspecialchars($exp['ref_code']) ?></h1>
    <button onclick="window.print()" class="btn btn-primary" style="padding:.5rem 1rem;font-size:.88rem;">
      &#x1F5A8; Print / Save as PDF
    </button>
    <a href="exp-view.php?id=<?= (int)$exp['id'] ?>" class="btn btn-outline" style="padding:.5rem 1rem;font-size:.88rem;">
      &#x2190; Back
    </a>
  </div>

  <div class="receipt-wrap">
  <div class="receipt-card">

    <div class="org-header">
      <h1>Bulkley Valley Teachers' Union</h1>
      <h2>Expense Reimbursement Receipt</h2>
    </div>

    <hr class="divider">

    <table class="detail-table">
      <tr>
        <td class="lbl">Reference:</td>
        <td class="val"><strong><?= htmlspecialchars($exp['ref_code']) ?></strong></td>
      </tr>
      <tr>
        <td class="lbl">Date Issued:</td>
        <td class="val"><?= $exp['paid_at'] ? date('F j, Y', strtotime($exp['paid_at'])) : date('F j, Y') ?></td>
      </tr>
    </table>

    <div class="section-title">Payee</div>
    <table class="detail-table">
      <tr>
        <td class="lbl">Name:</td>
        <td class="val"><?= htmlspecialchars($exp['user_name']) ?></td>
      </tr>
      <tr>
        <td class="lbl">Email:</td>
        <td class="val"><?= htmlspecialchars($exp['user_email']) ?></td>
      </tr>
    </table>

    <div class="section-title">Expense Details</div>
    <table class="detail-table">
      <tr>
        <td class="lbl">Date:</td>
        <td class="val"><?= date('F j, Y', strtotime($exp['expense_date'])) ?></td>
      </tr>
      <tr>
        <td class="lbl">Category:</td>
        <td class="val"><?= htmlspecialchars($catLabels[$exp['category']] ?? ucfirst($exp['category'])) ?></td>
      </tr>
      <tr>
        <td class="lbl">Amount:</td>
        <td class="val"><span class="amount-large">$<?= number_format((float)$exp['amount'], 2) ?></span></td>
      </tr>
      <tr>
        <td class="lbl">Description:</td>
        <td class="val"><?= nl2br(htmlspecialchars($exp['description'])) ?></td>
      </tr>
    </table>

    <?php if ($exp['receipt_path']): ?>
    <div class="section-title">Receipt</div>
    <?php if ($receiptIsImage): ?>
    <div class="receipt-thumb">
      <img src="exp-receipt.php?f=<?= urlencode($exp['receipt_path']) ?>" alt="Receipt image">
    </div>
    <?php else: ?>
    <p style="font-size:12px;">
      <a href="exp-receipt.php?f=<?= urlencode($exp['receipt_path']) ?>" style="color:#1a6b35;">
        &#x1F4C4; View attached receipt (PDF)
      </a>
    </p>
    <?php endif; ?>
    <?php if ($exp['extracted_vendor']): ?>
    <table class="detail-table" style="margin-top:.5rem;">
      <tr>
        <td class="lbl">Vendor:</td>
        <td class="val"><?= htmlspecialchars($exp['extracted_vendor']) ?></td>
      </tr>
      <?php if ($exp['extracted_date']): ?>
      <tr>
        <td class="lbl">Receipt Date:</td>
        <td class="val"><?= date('F j, Y', strtotime($exp['extracted_date'])) ?></td>
      </tr>
      <?php endif; ?>
    </table>
    <?php endif; ?>
    <?php endif; ?>

    <hr class="divider">

    <div class="section-title">Authorization</div>

    <p style="font-size:12px;margin:.5rem 0 1rem;">Signer 1 (Treasurer):</p>
    <div class="sig-row">
      <div class="sig-block">
        <div class="sig-line">
          <?php if ($exp['signer1_name']): ?>
          <span class="sig-name"><?= htmlspecialchars($exp['signer1_name']) ?></span>
          <?php endif; ?>
        </div>
        <div class="sig-date">
          <?= $exp['signer1_name'] ? htmlspecialchars($exp['signer1_name']) : '_______________________________' ?>
        </div>
      </div>
      <div class="sig-block">
        <div class="sig-line"></div>
        <div class="sig-date">
          <?= $exp['signer1_at'] ? date('F j, Y', strtotime($exp['signer1_at'])) : '_______________________________' ?>
        </div>
      </div>
    </div>

    <p style="font-size:12px;margin:1.5rem 0 1rem;">Signer 2 (VP / President):</p>
    <div class="sig-row">
      <div class="sig-block">
        <div class="sig-line">
          <?php if ($exp['signer2_name']): ?>
          <span class="sig-name"><?= htmlspecialchars($exp['signer2_name']) ?></span>
          <?php endif; ?>
        </div>
        <div class="sig-date">
          <?= $exp['signer2_name'] ? htmlspecialchars($exp['signer2_name']) : '_______________________________' ?>
        </div>
      </div>
      <div class="sig-block">
        <div class="sig-line"></div>
        <div class="sig-date">
          <?= $exp['signer2_at'] ? date('F j, Y', strtotime($exp['signer2_at'])) : '_______________________________' ?>
        </div>
      </div>
    </div>

    <hr class="divider">

    <div class="section-title">Payment</div>
    <table class="detail-table">
      <tr>
        <td class="lbl">Date Paid:</td>
        <td class="val"><?= $exp['paid_at'] ? date('F j, Y', strtotime($exp['paid_at'])) : 'Pending' ?></td>
      </tr>
      <tr>
        <td class="lbl">Issued by:</td>
        <td class="val"><?= htmlspecialchars($exp['paid_by_name'] ?: 'Pending') ?></td>
      </tr>
      <tr>
        <td class="lbl">Payment ref:</td>
        <td class="val"><?= htmlspecialchars($exp['ref_code']) ?></td>
      </tr>
      <?php if ($exp['payment_note']): ?>
      <tr>
        <td class="lbl">Note:</td>
        <td class="val"><?= htmlspecialchars($exp['payment_note']) ?></td>
      </tr>
      <?php endif; ?>
    </table>

    <hr class="divider">

    <p class="footer-note">
      This receipt confirms that the above expense was reviewed,<br>
      authorized by two signing officers, and payment was issued<br>
      via e-transfer by the Bulkley Valley Teachers' Union.
    </p>

    <p class="generated">Generated: <?= date('F j, Y g:i a T') ?></p>

  </div>
  </div>

</body>
</html>
