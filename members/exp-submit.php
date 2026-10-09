<?php
/**
 * exp-submit.php — Expense claim editor (multi-item)
 *
 * A "claim" groups one or more individual expense items — each with its own
 * date, category, amount, description and receipt — into a single submission.
 * The whole claim goes through ONE Treasurer + second-signer approval and is
 * paid out as ONE e-transfer for the combined total. This mirrors the LP
 * voucher workflow and is meant for members batching several receipts
 * together (e.g. from one trip or conference).
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();
expBatchEnsureTables();
expEnsureOnBehalfColumns();

$errors = [];
$notice = '';

// ── "Submit on behalf of" — President, VP, Treasurer only (signing authority) ──
$canActOnBehalf  = expCanSubmitOnBehalf($member['email']);
$onBehalfMembers = [];
if ($canActOnBehalf) {
    $stmt = getDB()->prepare("SELECT name, email FROM members WHERE email <> ? ORDER BY name");
    $stmt->execute([strtolower(trim($member['email']))]);
    $onBehalfMembers = $stmt->fetchAll();
}

$onBehalfSel = trim($_POST['on_behalf_of'] ?? $_GET['on_behalf_of'] ?? '__self__');

$targetEmail      = $member['email'];
$targetName       = $member['name'];
$submittedByEmail = '';
$submittedByName  = '';

if ($canActOnBehalf && $onBehalfSel !== '' && $onBehalfSel !== '__self__') {
    $obStmt = getDB()->prepare("SELECT name, email FROM members WHERE email=?");
    $obStmt->execute([strtolower(trim($onBehalfSel))]);
    $onBehalfMember = $obStmt->fetch();
    if ($onBehalfMember) {
        $targetEmail      = $onBehalfMember['email'];
        $targetName       = $onBehalfMember['name'];
        $submittedByEmail = $member['email'];
        $submittedByName  = $member['name'];
    } else {
        $onBehalfSel = '__self__';
    }
}

// ── Resolve which draft claim we're editing ────────────────────────────────────
$batchId = (int)($_GET['id'] ?? 0);
$batch   = null;

if ($batchId) {
    $candidate = expBatchGet($batchId);
    if ($candidate
        && $candidate['status'] === 'draft'
        && strtolower($candidate['user_email']) === strtolower($targetEmail)
        && strtolower(trim($candidate['submitted_by_email'] ?? '')) === strtolower(trim($submittedByEmail))
    ) {
        $batch = $candidate;
    }
}
if (!$batch) {
    $batch = expBatchGetOrCreateDraft($targetEmail, $targetName, $submittedByEmail, $submittedByName);
}
$batchId = (int)$batch['id'];

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference / Workshop',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];

// ── Handle save / submit ────────────────────────────────────────────────────────
// Writes the claim's line items and amounts. The page that submits a claim
// for approval already checks; the page that sets the figures should too.
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfCheck();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');

    $itemIds    = $_POST['item_id']        ?? [];
    $dates      = $_POST['expense_date']   ?? [];
    $cats       = $_POST['category']       ?? [];
    $amounts    = $_POST['amount']         ?? [];
    $travelKms  = $_POST['travel_km']      ?? [];
    $descs      = $_POST['description']    ?? [];
    $rPaths     = $_POST['receipt_path']   ?? [];
    $rOrigs     = $_POST['receipt_orig']   ?? [];
    $exVendor   = $_POST['ext_vendor']     ?? [];
    $exDate     = $_POST['ext_date']       ?? [];
    $exAmount   = $_POST['ext_amount']     ?? [];
    $exFlag     = $_POST['ext_flag']       ?? [];
    $exConcerns = $_POST['ext_concerns']   ?? [];
    $noReceipt  = $_POST['no_receipt']     ?? []; // sequential, aligned with description[]/amount[] by row order

    $hasRow = false;
    foreach ($descs as $i => $d) {
        if (trim($d) !== '' || (float)($amounts[$i] ?? 0) > 0) { $hasRow = true; break; }
    }
    if (!$hasRow) {
        $errors[] = 'Please add at least one expense item with a description and amount.';
    }
    foreach ($descs as $i => $d) {
        $amt = (float)($amounts[$i] ?? 0);
        $isRowUsed = (trim($d) !== '' || $amt > 0);
        if (!$isRowUsed) continue;
        if (trim($d) === '') {
            $errors[] = 'Row ' . ($i + 1) . ': please describe the expense.';
        }
        if ($amt <= 0) {
            $errors[] = 'Row ' . ($i + 1) . ': please enter an amount greater than zero.';
        }
        if (empty($rPaths[$i]) && (($noReceipt[$i] ?? '0') !== '1')) {
            $errors[] = 'Row ' . ($i + 1) . ': please upload a receipt, or check "no receipt" for this item.';
        }
    }

    if (!$errors) {
        $db = getDB();
        $db->prepare("UPDATE exp_batches SET title=? WHERE id=?")->execute([$title ?: null, $batchId]);

        $keptIds = array_values(array_filter(array_map('intval', $itemIds)));
        if ($keptIds) {
            $ph = implode(',', array_fill(0, count($keptIds), '?'));
            $db->prepare("DELETE FROM exp_batch_items WHERE batch_id=? AND id NOT IN ($ph)")
               ->execute(array_merge([$batchId], $keptIds));
        } else {
            $db->prepare("DELETE FROM exp_batch_items WHERE batch_id=?")->execute([$batchId]);
        }

        $upd = $db->prepare(
            "UPDATE exp_batch_items SET
                expense_date = ?, category = ?, amount = ?, description = ?,
                receipt_path = ?, receipt_filename = ?,
                extracted_vendor = ?, extracted_date = ?, extracted_amount = ?,
                extraction_flag = ?, extraction_concerns = ?, travel_km = ?, sort_order = ?
             WHERE id = ? AND batch_id = ?"
        );
        $ins = $db->prepare(
            "INSERT INTO exp_batch_items
             (batch_id, expense_date, category, amount, description,
              receipt_path, receipt_filename,
              extracted_vendor, extracted_date, extracted_amount,
              extraction_flag, extraction_concerns, travel_km, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );

        foreach ($descs as $i => $desc) {
            $amt = (float)($amounts[$i] ?? 0);
            if (trim($desc) === '' && $amt <= 0) continue;

            $cat = $cats[$i] ?? 'other';
            if (!in_array($cat, EXP_CATEGORIES, true)) $cat = 'other';

            $params = [
                $dates[$i] ?: null,
                $cat,
                round($amt, 2),
                trim($desc),
                ($rPaths[$i] ?? '') !== '' ? basename(trim($rPaths[$i])) : null,
                ($rOrigs[$i] ?? '') ?: null,
                ($exVendor[$i]   ?? '') ?: null,
                ($exDate[$i]     ?? '') ?: null,
                is_numeric($exAmount[$i] ?? '') ? round((float)$exAmount[$i], 2) : null,
                ($exFlag[$i]     ?? '') ?: null,
                ($exConcerns[$i] ?? '') ?: null,
                is_numeric($travelKms[$i] ?? '') && (float)$travelKms[$i] > 0
                    ? round((float)$travelKms[$i], 1) : null,
                $i,
            ];

            $existingId = (int)($itemIds[$i] ?? 0);
            if ($existingId && in_array($existingId, $keptIds, true)) {
                $upd->execute(array_merge($params, [$existingId, $batchId]));
            } else {
                $ins->execute(array_merge([$batchId], $params));
            }
        }

        if (!empty($_POST['_submit_for_approval'])) {
            $items = expBatchGetItems($batchId);
            if (count($items) > 0) {
                $total = expBatchTotal($batchId);
                expBatchSubmit($batchId);
                $fresh = expBatchGet($batchId);
                expBatchEmailSubmitted($fresh, $total, count($items));
                header('Location: exp-claim-view.php?id=' . $batchId . '&submitted=1');
                exit;
            }
            $errors[] = 'Add at least one expense item before submitting.';
        } else {
            $notice = 'Draft saved — your items are kept here until you submit for review.';
        }
    }
}

$items = expBatchGetItems($batchId);
$total = array_sum(array_map(function ($i) { return (float)$i['amount']; }, $items));
$batch = expBatchGet($batchId);

// Always keep at least one (possibly empty) row for the editor
$rows = $items ?: [[
    'id' => 0, 'expense_date' => date('Y-m-d'), 'category' => '', 'amount' => '',
    'description' => '', 'receipt_path' => '', 'receipt_filename' => '',
    'extracted_vendor' => '', 'extracted_date' => '', 'extracted_amount' => '',
    'extraction_flag' => '', 'extraction_concerns' => '',
]];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Expense Claim — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem 6rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .notice-banner { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .8rem 1rem; margin-bottom: 1.25rem; font-size: .88rem; color: #166534; }
    .error-summary { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .8rem 1rem; margin-bottom: 1.25rem; font-size: .88rem; color: #991b1b; }
    .error-summary ul { margin: .35rem 0 0; padding-left: 1.2rem; }

    .claim-header { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
    .claim-header h2 { font-size: .82rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--primary); margin: 0 0 .9rem; }
    .hfield label { display: block; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); margin-bottom: .3rem; }
    .hfield input, .hfield select { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .55rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .hfield input:focus, .hfield select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .35rem; }

    .toolbar { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; flex-wrap: wrap; }
    .btn-add { background: #fff; color: var(--primary); border: 1.5px solid var(--primary); border-radius: 8px; padding: .55rem 1.1rem; font-size: .88rem; font-weight: 700; cursor: pointer; }
    .btn-add:hover { background: var(--accent); }

    .item-table-wrap { overflow-x: auto; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; margin-bottom: 1.25rem; }
    table.item-table { width: 100%; border-collapse: collapse; min-width: 980px; font-size: .85rem; }
    .item-table thead th { background: #1a2e1a; color: #fff; padding: .6rem .75rem; text-align: left; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
    .item-table thead th.num { text-align: right; }
    .item-table tbody tr { border-bottom: 1px solid var(--gray-100); }
    .item-table tbody tr:last-child { border-bottom: none; }
    .item-table td { padding: .6rem .5rem; vertical-align: top; }
    .item-table tfoot td { padding: .7rem .9rem; background: #f0fdf4; font-weight: 800; font-size: .9rem; }
    .item-table tfoot td.num { text-align: right; color: var(--primary); }
    .cell-input, .cell-select, .cell-textarea {
      border: 1px solid var(--gray-300); border-radius: 6px; padding: .4rem .55rem; font-size: .84rem;
      font-family: inherit; width: 100%; box-sizing: border-box; background: #fff;
    }
    .cell-input:focus, .cell-select:focus, .cell-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(26,107,53,.1); }
    .cell-input.num { text-align: right; }
    .cell-date   { width: 135px; }
    .cell-cat    { width: 150px; }
    .cell-amount { width: 100px; }
    .cell-desc   { min-width: 220px; }
    .cell-textarea { resize: vertical; min-height: 42px; }
    .cell-receipt { width: 170px; }
    /* On a phone there is a camera above the picker; on a desktop there is not,
       and a "Take a photo" button that opens a file dialog would be a lie. */
    .receipt-cam { display: none; }
    .is-touch .receipt-cam { display: block; position: relative; border: 1.5px solid var(--primary);
      background: var(--primary); color: #fff; border-radius: 7px; padding: .55rem;
      text-align: center; cursor: pointer; font-size: .8rem; font-weight: 700;
      margin-bottom: .35rem; }
    .is-touch .receipt-cam input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    /* The picker is an inline label, which is fine inside a narrow table cell
       and looks like an afterthought under a full-width camera button once the
       table stacks into cards. Phones only; the desktop table is untouched. */
    .is-touch .receipt-zone { display: block; }
    /* The QR is for sending a photo from a phone to a desktop. On the phone
       itself it is a picture of the page you are already looking at. */
    .is-touch .btn-phone,
    .is-touch .receipt-phone-btn { display: none; }
    .receipt-zone { border: 1.5px dashed var(--gray-300); border-radius: 7px; padding: .5rem; text-align: center; cursor: pointer; position: relative; font-size: .76rem; color: var(--gray-500); transition: border-color .12s, background .12s; }
    .receipt-zone:hover { border-color: var(--primary); background: var(--accent); }
    .receipt-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .receipt-zone.has-file { border-style: solid; border-color: #86efac; background: #f0fdf4; color: #166534; }
    .receipt-zone .spinner { width: 16px; height: 16px; border: 2px solid var(--gray-200); border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; margin: 0 auto .25rem; display: none; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .no-receipt-mini { display: flex; align-items: center; gap: .35rem; margin-top: .35rem; font-size: .74rem; color: var(--gray-500); }
    .no-receipt-mini input { width: auto; accent-color: var(--primary); }
    .row-flag { font-size: .72rem; color: #92400e; margin-top: .3rem; }
    .btn-row-remove { background: none; border: none; cursor: pointer; color: var(--gray-300); font-size: 1.05rem; padding: .25rem .4rem; border-radius: 4px; transition: color .12s; }
    .btn-row-remove:hover { color: #dc2626; background: #fef2f2; }

    .save-bar { position: sticky; bottom: 1rem; display: flex; gap: .75rem; align-items: center; background: #fff;
                border: 1px solid var(--gray-200); border-radius: 12px; padding: 1rem 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,.1); flex-wrap: wrap; }
    .save-bar .total-display { font-size: 1.2rem; font-weight: 900; color: var(--primary); margin-right: auto; }
    .save-bar .total-display span { display: block; font-size: .72rem; font-weight: 600; color: var(--gray-400); text-transform: uppercase; letter-spacing: .04em; }

    /* ── Phone upload ── */
    .btn-phone { background:#f0fdf4; color:var(--primary); border:1.5px solid #86efac; border-radius:8px; padding:.45rem .85rem; font-size:.85rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; }
    .btn-phone:hover { background:#dcfce7; }
    .qr-panel { display:none; background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1.25rem; }
    .qr-panel.open { display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap; }
    .qr-box { flex-shrink:0; }
    .qr-instructions h3 { font-size:.95rem; font-weight:800; color:var(--primary); margin:0 0 .5rem; }
    .qr-instructions p { font-size:.83rem; color:var(--gray-500); line-height:1.55; margin:0 0 .6rem; max-width:46ch; }
    .qr-url { font-size:.72rem; color:var(--gray-400); word-break:break-all; background:var(--off-white); padding:.4rem .6rem; border-radius:5px; }
    .qr-loading { display:flex; align-items:center; gap:.6rem; font-size:.85rem; color:var(--gray-500); padding:.5rem 0; }
    .qr-spinner { width:18px; height:18px; border:2px solid var(--gray-200); border-top-color:var(--primary); border-radius:50%; animation:spin .7s linear infinite; flex-shrink:0; }
    .qr-target { display:none; background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:.5rem .75rem; margin-top:.6rem; font-size:.82rem; color:#166534; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .qr-target-clear { background:none; border:none; cursor:pointer; color:#9ca3af; font-size:.9rem; margin-left:auto; padding:.1rem .3rem; border-radius:4px; }
    .qr-target-clear:hover { color:#dc2626; background:#fef2f2; }
    .receipt-phone-btn { background:none; border:1px dashed #86efac; border-radius:5px; padding:.2rem .4rem; margin-top:.35rem; cursor:pointer; font-size:.72rem; color:var(--primary); width:100%; }
    .receipt-phone-btn:hover { background:#f0fdf4; border-style:solid; }
    .receipt-phone-btn.targeting { background:#dcfce7; border-style:solid; border-color:var(--primary); font-weight:700; }
    tr.row-flash { animation: rowflash 1.6s ease-out; }
    @keyframes rowflash { 0% { background:#dcfce7; } 100% { background:transparent; } }

    #receiptToast { position: fixed; bottom: 5rem; left: 50%; transform: translateX(-50%) translateY(20px); background: #1a6b35; color: #fff; font-size: .9rem; font-weight: 700; padding: .75rem 1.25rem; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,.2); opacity: 0; transition: opacity .3s, transform .3s; pointer-events: none; z-index: 9999; white-space: nowrap; }
    #receiptToast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

    @media (max-width: 760px) {
      .item-table-wrap { border: none; background: none; }
      table.item-table, table.item-table thead, table.item-table tbody, table.item-table tfoot,
      table.item-table tr, table.item-table th, table.item-table td { display: block; width: 100%; min-width: 0; }
      table.item-table thead { display: none; }
      table.item-table tbody tr { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: .85rem; margin-bottom: .85rem; }
      table.item-table td { padding: .35rem 0; }
      table.item-table td::before { content: attr(data-label); display: block; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-400); margin-bottom: .2rem; }
      .cell-date, .cell-cat, .cell-amount, .cell-receipt { width: 100%; }
      table.item-table tfoot tr { background: #f0fdf4; border-radius: 10px; padding: .85rem; }
    }
  </style>
</head>
<body>
<div class="wrap">

  <div class="portal-header">
    <div>
      <a class="back-link" href="exp-dashboard.php">&#x2190; My Expenses</a>
      <h1 style="margin-top:.3rem;">Submit Expense Claim</h1>
    </div>
    <div style="font-size:.8rem;color:var(--gray-400);">Claim ref: <strong><?= htmlspecialchars($batch['ref_code']) ?></strong></div>
  </div>

  <p style="font-size:.88rem;color:var(--gray-500);max-width:760px;margin:-.75rem 0 1.25rem;">
    Add every receipt for this claim below — for example, all the expenses from one trip or
    conference. The whole claim is reviewed and signed off together, and the Treasurer sends
    <strong>one e-transfer for the combined total</strong> once it's fully approved.
  </p>

  <?php if ($notice): ?>
  <div class="notice-banner">&#x2713; <?= htmlspecialchars($notice) ?></div>
  <?php endif; ?>

  <?php if ($errors): ?>
  <div class="error-summary">
    &#x26A0; Please fix the following before saving:
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form method="POST" id="claimForm">
        <?= csrfField() ?>
    <input type="hidden" name="on_behalf_of" value="<?= htmlspecialchars($onBehalfSel) ?>">

    <div class="claim-header">
      <h2>Claim Details</h2>
      <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
        <div class="hfield" style="flex:2;min-width:240px;">
          <label for="title">What's this claim for? <span style="font-weight:500;text-transform:none;color:var(--gray-400);">(optional)</span></label>
          <input type="text" name="title" id="title" placeholder="e.g. PSA Convention — Vancouver, March 2026"
                 value="<?= htmlspecialchars($_POST['title'] ?? $batch['title'] ?? '') ?>">
          <div class="field-hint">A short label to help the Treasurer and you identify this claim.</div>
        </div>
        <?php if (strtolower($batch['user_email']) !== strtolower($member['email'])): ?>
        <div class="hfield" style="flex:1;min-width:220px;">
          <label>Filing for</label>
          <input type="text" value="<?= htmlspecialchars($batch['user_name'] . ' <' . $batch['user_email'] . '>') ?>" disabled
                 style="background:#f8f9fa;color:var(--gray-500);">
        </div>
        <?php endif; ?>
      </div>

      <?php if ($canActOnBehalf): ?>
      <div class="hfield" style="max-width:380px;margin-top:1rem;">
        <label for="on_behalf_of_select">Who is this claim for?</label>
        <select id="on_behalf_of_select" class="cell-select" onchange="switchOnBehalf(this.value)">
          <option value="__self__" <?= ($onBehalfSel === '__self__') ? 'selected' : '' ?>>
            Myself (<?= htmlspecialchars($member['name']) ?>)
          </option>
          <?php foreach ($onBehalfMembers as $m): ?>
          <option value="<?= htmlspecialchars($m['email']) ?>" <?= (strtolower($onBehalfSel) === strtolower($m['email'])) ? 'selected' : '' ?>>
            <?= htmlspecialchars($m['name']) ?> &mdash; <?= htmlspecialchars($m['email']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="field-hint">
          As an executive with signing authority you can file a claim for another member.
          Switching will start (or resume) a separate draft claim for that member.
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="toolbar">
      <button type="button" class="btn-add" onclick="addRow()">&#x2795; Add expense item</button>
      <button type="button" class="btn-phone" onclick="openPhoneUpload()">&#x1F4F1; Use my phone's camera</button>
      <span style="font-size:.8rem;color:var(--gray-400);">Each item needs its own receipt (or mark "no receipt").</span>
    </div>

    <?php // The phone uploader this opens, and everything behind it, already
          // existed — exp-create-draft.php, exp-mobile-receipt.php,
          // exp-mobile-scan.php, exp-poll-receipt.php and exp-claim-receipt.php
          // were all written and never linked to from anywhere. ?>
    <div class="qr-panel" id="qrPanel">
      <div class="qr-box">
        <img id="qrImg" src="" alt="QR code to open the receipt uploader on your phone"
             style="border-radius:8px;display:none;">
        <div class="qr-loading" id="qrLoading"><div class="qr-spinner"></div> Setting up&hellip;</div>
      </div>
      <div class="qr-instructions" id="qrInstructions" style="display:none;">
        <h3>&#x1F4F1; Photograph receipts with your phone</h3>
        <p>Point your phone's camera at this code. The page that opens goes
           straight to the camera — take a photo of a receipt and it appears in
           your claim here, read and filled in automatically. No email, no AirDrop.</p>
        <div class="qr-url" id="qrUrlText"></div>
        <div class="qr-target" id="qrTarget">
          &#x1F4CC; Next photo &rarr; <strong id="qrTargetLabel"></strong>
          <button type="button" class="qr-target-clear" onclick="clearPhoneTarget()"
                  title="Stop sending to that item">&#x2715;</button>
        </div>
        <p style="margin:.6rem 0 0;font-size:.78rem;color:var(--gray-400);">
          Keep this code to yourself — anyone who scans it can add a receipt to
          this claim. It stops working once you leave the page for a day.
        </p>
      </div>
    </div>

    <div class="item-table-wrap">
      <table class="item-table" id="itemTable">
        <thead>
          <tr>
            <th style="width:36px;"></th>
            <th class="cell-date">Date</th>
            <th class="cell-cat">Category</th>
            <th class="cell-desc">Description</th>
            <th class="cell-km num" title="Kilometres — fills in the amount automatically">km</th>
            <th class="cell-amount num">Amount ($)</th>
            <th class="cell-receipt">Receipt</th>
          </tr>
        </thead>
        <tbody id="itemTbody">
          <?php foreach ($rows as $idx => $row): ?>
          <?= renderItemRow($idx, $row, $catLabels) ?>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4" style="text-align:right;">Total</td>
            <td class="num" id="totalDisplay">$<?= number_format($total, 2) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="save-bar">
      <div class="total-display">
        $<span id="saveBarTotal"><?= number_format($total, 2) ?></span>
        <span>Claim total</span>
      </div>
      <button type="submit" name="_save_only" value="1" class="btn btn-outline" style="padding:.65rem 1.2rem;font-size:.9rem;">
        Save Draft
      </button>
      <button type="submit" name="_submit_for_approval" value="1" class="btn btn-primary" style="padding:.65rem 1.4rem;font-size:.9rem;">
        &#x2713; Submit Claim for Approval
      </button>
    </div>
  </form>

</div>

<div id="receiptToast"></div>

<script src="../js/qrcode.js?v=<?= @filemtime(__DIR__ . '/../js/qrcode.js') ?>"></script>
<script src="../js/qr-img.js?v=<?= @filemtime(__DIR__ . '/../js/qr-img.js') ?>"></script>
<script>
/* ── Is this a phone? ────────────────────────────────────────────────────────
 *
 * A teacher filling in a claim on their phone was shown a QR code — a picture
 * of the page they were already on — and a file picker that, on Android, only
 * offers photos already taken. The camera was unreachable from the one device
 * that had one.
 *
 * (pointer: coarse) is the primary pointer, so a laptop with a touchscreen and
 * a trackpad still reads as a desktop. Both ways of being wrong are safe: a
 * desktop shown the camera button gets an ordinary file dialog, and a phone
 * read as a desktop gets exactly what it gets today.
 */
var IS_TOUCH = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
if (IS_TOUCH) document.documentElement.classList.add('is-touch');

/* The picker is "upload a receipt" on a desktop and "the other option" on a
 * phone, where the camera button sits above it. One constant, because the
 * label is also rewritten when a row is cleared. */
var ZONE_DEFAULT = IS_TOUCH ? '\u{1F5BC}\uFE0F Choose a saved photo or PDF'
                            : '\u{1F4F7} Upload receipt';

var ROW_INDEX = <?= count($rows) ?>;
var EXP_CSRF  = <?= json_encode(csrfToken()) ?>;
var CATEGORIES = <?= json_encode($catLabels) ?>;

function emptyRowHtml(idx) {
  var MILEAGE_RATE = <?= json_encode((float)BVTU_MILEAGE_RATE) ?>;

  // Mileage used to be a separate tool with no approval trail. Entering
  // kilometres here fills in the amount so the claim goes through the same
  // two signatures as everything else.
  function mileageFromKm(el) {
    var row = el.closest('tr');
    var km  = parseFloat(el.value);
    if (!row || isNaN(km) || km <= 0) return;

    var amt = row.querySelector('.cell-amount');
    if (amt) { amt.value = (km * MILEAGE_RATE).toFixed(2); }

    var cat = row.querySelector('.cell-cat');
    if (cat && !cat.value) {
      for (var i = 0; i < cat.options.length; i++) {
        if (cat.options[i].value === 'travel') { cat.selectedIndex = i; break; }
      }
    }
    // Leave a written trail of the calculation for whoever signs it off.
    var desc = row.querySelector('.cell-desc');
    if (desc && !desc.value.trim()) {
      desc.value = 'Mileage — ' + km + ' km @ $' + MILEAGE_RATE.toFixed(2) + '/km';
    }
    if (typeof recalcTotal === 'function') recalcTotal();
  }

  var catOptions = '<option value="">Choose&hellip;</option>';
  for (var key in CATEGORIES) {
    catOptions += '<option value="' + key + '">' + CATEGORIES[key] + '</option>';
  }
  return '' +
    '<tr data-row="' + idx + '">' +
      '<td data-label="Remove"><button type="button" class="btn-row-remove" onclick="removeRow(this)" title="Remove item">&#x2715;</button></td>' +
      '<td data-label="Date"><input type="hidden" name="item_id[]" value="0">' +
        '<input type="date" name="expense_date[]" class="cell-input cell-date" value="' + new Date().toISOString().slice(0,10) + '"></td>' +
      '<td data-label="Category"><select name="category[]" class="cell-select cell-cat">' + catOptions + '</select></td>' +
      '<td data-label="Description"><textarea name="description[]" class="cell-textarea cell-desc" rows="2" placeholder="What was this for?"></textarea></td>' +
      '<td data-label="km"><input type="number" name="travel_km[]" class="cell-input num cell-km" min="0" step="0.1" placeholder="km" title="Kilometres driven — fills in the amount at $' + MILEAGE_RATE.toFixed(2) + '/km" oninput="mileageFromKm(this)"></td>' +
      '<td data-label="Amount ($)"><input type="number" name="amount[]" class="cell-input num cell-amount" min="0.01" step="0.01" placeholder="0.00" oninput="recalcTotal()"></td>' +
      '<td data-label="Receipt">' +
        '<input type="hidden" name="receipt_path[]" class="f-receipt-path">' +
        '<input type="hidden" name="receipt_orig[]" class="f-receipt-orig">' +
        '<input type="hidden" name="ext_vendor[]" class="f-ext-vendor">' +
        '<input type="hidden" name="ext_date[]" class="f-ext-date">' +
        '<input type="hidden" name="ext_amount[]" class="f-ext-amount">' +
        '<input type="hidden" name="ext_flag[]" class="f-ext-flag">' +
        '<input type="hidden" name="ext_concerns[]" class="f-ext-concerns">' +
        '<input type="hidden" name="no_receipt[]" class="f-no-receipt" value="0">' +
        '<label class="receipt-cam">&#x1F4F7; Take a photo<input type="file" accept="image/*" capture="environment" onchange="handleRowFile(this)"></label>' +
        '<label class="receipt-zone">' +
          '<div class="spinner"></div>' +
          '<span class="zone-label"></span>' +
          '<input type="file" accept="image/*,.pdf" onchange="handleRowFile(this)">' +
        '</label>' +
        '<button type="button" class="receipt-phone-btn" onclick="phoneForRow(this)">' +
          '&#x1F4F1; Photograph this one' +
        '</button>' +
        '<div class="row-flag" style="display:none;"></div>' +
        '<label class="no-receipt-mini"><input type="checkbox" onchange="toggleNoReceiptMini(this)"> No receipt for this item</label>' +
      '</td>' +
    '</tr>';
}

function addRow() {
  var tbody = document.getElementById('itemTbody');
  var div = document.createElement('tbody');
  div.innerHTML = emptyRowHtml(ROW_INDEX);
  var tr = div.firstElementChild;
  tbody.appendChild(tr);
  labelRestingZones(tr);
  ROW_INDEX++;
}

/**
 * Put ZONE_DEFAULT on every picker that is not already holding a receipt.
 *
 * Needed in two places: rows built here, and the rows PHP renders on load —
 * which are written with the desktop wording because the server cannot know
 * what is holding the phone.
 */
function labelRestingZones(scope) {
  (scope || document).querySelectorAll('.receipt-zone').forEach(function (zone) {
    if (zone.classList.contains('has-file')) return;
    var label = zone.querySelector('.zone-label');
    if (label) label.textContent = ZONE_DEFAULT;
  });
}

function removeRow(btn) {
  var tbody = document.getElementById('itemTbody');
  var row = btn.closest('tr');
  if (tbody.children.length <= 1) {
    // Clear instead of removing the last row
    row.querySelectorAll('input[type=text], input[type=number], input[type=date], textarea').forEach(function(el){ el.value=''; });
    row.querySelectorAll('input[type=hidden]').forEach(function(el){
      if (el.name === 'item_id[]') return;
      el.value = (el.classList.contains('f-no-receipt')) ? '0' : '';
    });
    row.querySelectorAll('select').forEach(function(el){ el.selectedIndex = 0; });
    var noReceiptCb = row.querySelector('.no-receipt-mini input[type=checkbox]');
    if (noReceiptCb) noReceiptCb.checked = false;
    var zone = row.querySelector('.receipt-zone');
    zone.classList.remove('has-file');
    setReceiptControls(row.querySelector('td[data-label="Receipt"]') || row, true);
    zone.querySelector('.zone-label').textContent = ZONE_DEFAULT;
    row.querySelectorAll('input[type=file]').forEach(function (el) { el.value = ''; });
    row.querySelector('.row-flag').style.display = 'none';
    if (typeof expTargetRow !== 'undefined' && expTargetRow === row) clearPhoneTarget();
    recalcTotal();
    return;
  }
  if (typeof expTargetRow !== 'undefined' && expTargetRow === row) clearPhoneTarget();
  row.remove();
  recalcTotal();
}

function toggleNoReceiptMini(cb) {
  var td   = cb.closest('td');
  var mirror = td.querySelector('.f-no-receipt');
  if (mirror) mirror.value = cb.checked ? '1' : '0';
  setReceiptControls(td, !cb.checked);
}

/**
 * Dim and disable every way of attaching a receipt to this item, or none.
 *
 * Both of them: the camera button sits above the picker, so greying out the
 * picker alone leaves "Take a photo" live on a row marked "no receipt" — and
 * the only confirmation would be printed inside the box that was just dimmed.
 */
function setReceiptControls(td, enabled) {
  td.querySelectorAll('.receipt-zone, .receipt-cam').forEach(function (el) {
    el.style.opacity = enabled ? '1' : '.45';
    el.style.pointerEvents = enabled ? 'auto' : 'none';
  });
}

function recalcTotal() {
  var sum = 0;
  document.querySelectorAll('input[name="amount[]"]').forEach(function(el) {
    var v = parseFloat(el.value);
    if (!isNaN(v)) sum += v;
  });
  var formatted = sum.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  document.getElementById('totalDisplay').textContent = '$' + formatted;
  document.getElementById('saveBarTotal').textContent = formatted;
}

/* ── Phone upload ───────────────────────────────────────────────────────────
 *
 * The desktop file picker cannot reach a phone's camera, so a receipt in
 * somebody's hand used to mean photographing it, emailing it to themselves and
 * then uploading the attachment. This opens a code the phone's camera reads; the
 * page behind it goes straight to the camera, and the photo arrives here.
 *
 * The draft expense created for this is only a mailbox for arriving receipts —
 * it is not the claim, which already exists as a draft batch. It is hidden from
 * the dashboard and swept up after a day.
 */
var EXP_DRAFT_ID = 0,
    expMobileUrl = '',
    qrPanelOpen  = false,
    qrGenerated  = false,
    expPoll      = null,
    expTargetRow = null,   // the <tr> the next photo attaches to, if any
    expSeen      = {};

var expDraftPending = false;

function openPhoneUpload() {
  var panel = document.getElementById('qrPanel');
  panel.classList.add('open');
  qrPanelOpen = true;

  if (EXP_DRAFT_ID) { showQR(); startPolling(); return; }
  if (expDraftPending) return;   // a second click would mint a second mailbox
  expDraftPending = true;

  document.getElementById('qrLoading').style.display = 'flex';
  document.getElementById('qrInstructions').style.display = 'none';

  var fd = new FormData();
  fd.append('csrf_token', EXP_CSRF);
  fetch('exp-create-draft.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      expDraftPending = false;
      if (!d || !d.ok) { qrFailed((d && d.error) || 'Could not start the upload session.'); return; }
      EXP_DRAFT_ID = d.expense_id;
      expMobileUrl = d.mobile_url;
      showQR();
      startPolling();
    })
    .catch(function() { expDraftPending = false; qrFailed('Could not reach the server.'); });
}

/* Every failure path clears the spinner. A spinner left turning says "still
 * working" when nothing is. */
function qrFailed(why) {
  document.getElementById('qrLoading').innerHTML =
    '\u26A0 ' + why + ' <a href="#" onclick="retryPhoneUpload();return false;" ' +
    'style="color:var(--primary);font-weight:700;">Try again</a>';
}

function retryPhoneUpload() {
  document.getElementById('qrLoading').innerHTML =
    '<div class="qr-spinner"></div> Setting up\u2026';
  openPhoneUpload();
}

function showQR() {
  document.getElementById('qrLoading').style.display = 'none';
  document.getElementById('qrInstructions').style.display = 'block';
  document.getElementById('qrUrlText').textContent = expMobileUrl;
  if (!qrGenerated && expMobileUrl) {
    var img = document.getElementById('qrImg');
    // Drawn in the browser: the URL carries an upload token that needs no
    // login, so it is not handed to a QR service to put in its logs.
    if (bvtuQrInto(img, expMobileUrl, 180)) {
      img.style.display = 'block';
      qrGenerated = true;
    } else {
      document.getElementById('qrLoading').style.display = 'flex';
      qrFailed('Could not draw the code. Open the link below on your phone instead.');
      document.getElementById('qrInstructions').style.display = 'block';
    }
  }
}

function phoneForRow(btn) {
  var tr = btn.closest('tr');
  if (!qrPanelOpen) openPhoneUpload();

  expTargetRow = tr;
  var descEl = tr.querySelector('textarea[name="description[]"]');
  var dateEl = tr.querySelector('input[name="expense_date[]"]');
  var desc   = descEl && descEl.value.trim();
  var rows   = Array.prototype.slice.call(document.querySelectorAll('#itemTbody tr'));
  var label  = desc || (dateEl && dateEl.value) || ('Item ' + (rows.indexOf(tr) + 1));

  document.getElementById('qrTargetLabel').textContent = label;
  document.getElementById('qrTarget').style.display = 'flex';

  document.querySelectorAll('.receipt-phone-btn').forEach(function(b) {
    b.classList.remove('targeting');
  });
  btn.classList.add('targeting');
  document.getElementById('qrPanel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function clearPhoneTarget() {
  expTargetRow = null;
  document.getElementById('qrTarget').style.display = 'none';
  document.querySelectorAll('.receipt-phone-btn').forEach(function(b) {
    b.classList.remove('targeting');
  });
}

function startPolling() {
  if (expPoll || !EXP_DRAFT_ID) return;
  pollPhoneReceipt();
  expPoll = setInterval(pollPhoneReceipt, 5000);
}

function pollPhoneReceipt() {
  if (!EXP_DRAFT_ID) return;
  fetch('exp-poll-receipt.php?expense_id=' + EXP_DRAFT_ID)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var rec = d && d.receipt;
      if (!rec || expSeen[rec.id]) return;
      expSeen[rec.id] = true;
      takePhoneReceipt(rec);
    })
    .catch(function() {});
}

/* Receipts land in the targeted row, or in a fresh row when nothing is
 * targeted — so a photo is never silently dropped. */
function takePhoneReceipt(rec) {
  var sd = rec.scan_data || {};
  var data = {
    saved_path:    rec.saved_path,
    original_name: rec.original_name || '',
    vendor:   sd.vendor   || '',
    date:     sd.date     || '',
    amount:   sd.amount   || '',
    category: sd.category || '',
    concerns: sd.concerns || '',
    flag:     sd.flag     || ''
  };

  // A targeted row that has since been removed, or already has a receipt, is
  // not a target any more.
  var tr = expTargetRow;
  if (tr && (!document.body.contains(tr) ||
             (tr.querySelector('.f-receipt-path') || {}).value)) {
    clearPhoneTarget();
    tr = null;
  }
  if (!tr) {
    tr = firstEmptyReceiptRow();
    if (!tr) { addRow(); tr = document.querySelector('#itemTbody tr:last-child'); }
  } else {
    clearPhoneTarget();
  }

  // Pinning a row and then photographing a receipt for it settles the question
  // of whether there is one.
  var noneCb = tr.querySelector('.no-receipt-mini input[type=checkbox]');
  if (noneCb && noneCb.checked) { noneCb.checked = false; toggleNoReceiptMini(noneCb); }

  applyScanToRow(tr.querySelector('td[data-label="Receipt"]'), data);

  tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
  tr.classList.add('row-flash');
  setTimeout(function() { tr.classList.remove('row-flash'); }, 1600);
  showToast('📱 ' + (data.vendor || 'Receipt') + ' added from your phone');

  // Mark it dealt with, or the next poll offers it again.
  var fd = new FormData();
  fd.append('pending_id', rec.id);
  fd.append('csrf_token', EXP_CSRF);
  fetch('exp-claim-receipt.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      // Deliberately not re-offered: the receipt is already in a row, and
      // putting it back invites a second row against the same photo.
      if (!d || !d.ok) {
        alert((d && d.error) ? d.error
          : 'That receipt is attached, but could not be marked as filed. Reload '
            + 'before adding more so it is not attached twice.');
      }
    })
    .catch(function() {
      alert('That receipt is attached, but the server did not confirm it. Reload '
            + 'before adding more so it is not attached twice.');
    });
}

/* A row with no receipt and no "no receipt" tick — somewhere a photo belongs. */
function firstEmptyReceiptRow() {
  var found = null;
  document.querySelectorAll('#itemTbody tr').forEach(function(tr) {
    if (found) return;
    var path = tr.querySelector('.f-receipt-path');
    var none = tr.querySelector('.f-no-receipt');
    if (path && !path.value.trim() && (!none || none.value !== '1')) found = tr;
  });
  return found;
}

/*
 * Put a scanned receipt into a row.
 *
 * Shared by the desktop upload and the phone, so a receipt photographed on a
 * phone lands in exactly the same fields, with the same prefilling and the same
 * flag, as one picked off a hard drive. The two drifting apart is the obvious
 * way for this to go wrong later.
 */
function applyScanToRow(td, data) {
  var zone  = td.querySelector('.receipt-zone');
  var label = zone.querySelector('.zone-label');
  var flag  = td.querySelector('.row-flag');

  td.querySelector('.f-receipt-path').value   = data.saved_path    || '';
  td.querySelector('.f-receipt-orig').value   = data.original_name || '';
  td.querySelector('.f-ext-vendor').value     = data.vendor        || '';
  td.querySelector('.f-ext-date').value       = data.date          || '';
  td.querySelector('.f-ext-amount').value     = data.amount        || '';
  td.querySelector('.f-ext-flag').value       = data.flag          || '';
  td.querySelector('.f-ext-concerns').value   = data.concerns      || '';

  zone.classList.add('has-file');
  label.textContent = '✅ ' + (data.original_name || 'Receipt attached');

  var row = td.closest('tr');
  var amountEl = row.querySelector('input[name="amount[]"]');
  var dateEl   = row.querySelector('input[name="expense_date[]"]');
  var catEl    = row.querySelector('select[name="category[]"]');

  if (data.amount && !amountEl.value) {
    amountEl.value = parseFloat(data.amount).toFixed(2);
    recalcTotal();
  }
  if (data.date && !dateEl.value) dateEl.value = data.date;
  if (data.category && !catEl.value) {
    for (var i = 0; i < catEl.options.length; i++) {
      if (catEl.options[i].value === data.category) { catEl.selectedIndex = i; break; }
    }
  }
  if (data.concerns || data.flag) {
    flag.textContent = '⚠ ' + (data.concerns || 'Flagged for review');
    flag.style.display = 'block';
  } else {
    flag.textContent = '';
    flag.style.display = 'none';
  }
}

function handleRowFile(input) {
  var file = input.files[0];
  if (!file) return;
  var td   = input.closest('td');
  /* Cleared straight away, or "Upload failed — try again" is a dead end:
   * picking the SAME file again fires no change event and nothing happens. */
  input.value = '';
  var zone = td.querySelector('.receipt-zone');
  var spinner = zone.querySelector('.spinner');
  var label   = zone.querySelector('.zone-label');
  var flag    = td.querySelector('.row-flag');

  spinner.style.display = 'block';
  label.textContent = 'Scanning&hellip;';
  flag.style.display = 'none';

  var fd = new FormData();
  fd.append('receipt', file);

  fetch('exp-scan.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      spinner.style.display = 'none';

      if (data.error && !data.saved_path) {
        label.textContent = '⚠ ' + data.error;
        return;
      }

      applyScanToRow(td, data);
      showToast('Receipt scanned — fields auto-filled');
    })
    .catch(function() {
      spinner.style.display = 'none';
      label.textContent = '⚠ Upload failed — try again';
    });
}

function switchOnBehalf(value) {
  var params = new URLSearchParams(window.location.search);
  if (value === '__self__') params.delete('on_behalf_of');
  else params.set('on_behalf_of', value);
  params.delete('id');
  window.location.search = params.toString();
}

var toastTimer = null;
function showToast(msg) {
  var t = document.getElementById('receiptToast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(function() { t.classList.remove('show'); }, 3200);
}

labelRestingZones();
recalcTotal();
</script>
</body>
</html>
<?php
/**
 * Render one editable expense-item row (server-rendered initial state).
 */
function renderItemRow(int $idx, array $row, array $catLabels): string {
    $hasReceipt = !empty($row['receipt_path']);
    $catSel     = $row['category'] ?? '';

    $catOptions = '<option value="">Choose&hellip;</option>';
    foreach ($catLabels as $val => $label) {
        $sel = ($catSel === $val) ? ' selected' : '';
        $catOptions .= '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }

    $zoneClass = $hasReceipt ? ' has-file' : '';
    $zoneLabel = $hasReceipt
        ? '&#x2705; ' . htmlspecialchars($row['receipt_filename'] ?: 'Receipt attached')
        : '&#x1F4F7; Upload receipt';

    $flagText = trim((string)($row['extraction_concerns'] ?? '')) ?: trim((string)($row['extraction_flag'] ?? ''));
    $flagHtml = $flagText
        ? '<div class="row-flag" style="display:block;">&#x26A0; ' . htmlspecialchars($flagText) . '</div>'
        : '<div class="row-flag" style="display:none;"></div>';

    $amountVal = ($row['amount'] !== '' && $row['amount'] !== null) ? number_format((float)$row['amount'], 2, '.', '') : '';

    // An existing item with no receipt must have been explicitly marked "no receipt" to pass validation
    $noReceiptChecked = (!empty($row['id']) && !$hasReceipt);
    $zoneStyle = $noReceiptChecked ? ' style="opacity:.45;pointer-events:none;"' : '';

    return '
    <tr data-row="' . $idx . '">
      <td data-label="Remove"><button type="button" class="btn-row-remove" onclick="removeRow(this)" title="Remove item">&#x2715;</button></td>
      <td data-label="Date">
        <input type="hidden" name="item_id[]" value="' . (int)($row['id'] ?? 0) . '">
        <input type="date" name="expense_date[]" class="cell-input cell-date" value="' . htmlspecialchars($row['expense_date'] ?? '') . '">
      </td>
      <td data-label="Category"><select name="category[]" class="cell-select cell-cat">' . $catOptions . '</select></td>
      <td data-label="Description"><textarea name="description[]" class="cell-textarea cell-desc" rows="2" placeholder="What was this for?">' . htmlspecialchars($row['description'] ?? '') . '</textarea></td>
      <td data-label="km"><input type="number" name="travel_km[]" class="cell-input num cell-km" min="0" step="0.1" placeholder="km" value="' . htmlspecialchars($row['travel_km'] ?? '') . '" oninput="mileageFromKm(this)"></td>
      <td data-label="Amount ($)"><input type="number" name="amount[]" class="cell-input num cell-amount" min="0.01" step="0.01" placeholder="0.00" value="' . htmlspecialchars($amountVal) . '" oninput="recalcTotal()"></td>
      <td data-label="Receipt">
        <input type="hidden" name="receipt_path[]"   class="f-receipt-path"   value="' . htmlspecialchars($row['receipt_path'] ?? '') . '">
        <input type="hidden" name="receipt_orig[]"   class="f-receipt-orig"   value="' . htmlspecialchars($row['receipt_filename'] ?? '') . '">
        <input type="hidden" name="ext_vendor[]"     class="f-ext-vendor"     value="' . htmlspecialchars($row['extracted_vendor'] ?? '') . '">
        <input type="hidden" name="ext_date[]"       class="f-ext-date"       value="' . htmlspecialchars($row['extracted_date'] ?? '') . '">
        <input type="hidden" name="ext_amount[]"     class="f-ext-amount"     value="' . htmlspecialchars($row['extracted_amount'] ?? '') . '">
        <input type="hidden" name="ext_flag[]"       class="f-ext-flag"       value="' . htmlspecialchars($row['extraction_flag'] ?? '') . '">
        <input type="hidden" name="ext_concerns[]"   class="f-ext-concerns"   value="' . htmlspecialchars($row['extraction_concerns'] ?? '') . '">
        <input type="hidden" name="no_receipt[]"     class="f-no-receipt"     value="' . ($noReceiptChecked ? '1' : '0') . '">
        <label class="receipt-cam"' . $zoneStyle . '>&#x1F4F7; Take a photo<input type="file" accept="image/*" capture="environment" onchange="handleRowFile(this)"></label>
        <label class="receipt-zone' . $zoneClass . '"' . $zoneStyle . '>
          <div class="spinner"></div>
          <span class="zone-label">' . $zoneLabel . '</span>
          <input type="file" accept="image/*,.pdf" onchange="handleRowFile(this)">
        </label>
        <button type="button" class="receipt-phone-btn" onclick="phoneForRow(this)">&#x1F4F1; Photograph this one</button>
        ' . $flagHtml . '
        <label class="no-receipt-mini"><input type="checkbox" onchange="toggleNoReceiptMini(this)"' . ($noReceiptChecked ? ' checked' : '') . '> No receipt for this item</label>
      </td>
    </tr>';
}
