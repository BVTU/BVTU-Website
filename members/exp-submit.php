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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');

    $itemIds    = $_POST['item_id']        ?? [];
    $dates      = $_POST['expense_date']   ?? [];
    $cats       = $_POST['category']       ?? [];
    $amounts    = $_POST['amount']         ?? [];
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
                extraction_flag = ?, extraction_concerns = ?, sort_order = ?
             WHERE id = ? AND batch_id = ?"
        );
        $ins = $db->prepare(
            "INSERT INTO exp_batch_items
             (batch_id, expense_date, category, amount, description,
              receipt_path, receipt_filename,
              extracted_vendor, extracted_date, extracted_amount,
              extraction_flag, extraction_concerns, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
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
  <link rel="stylesheet" href="../css/style.css">
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
      <span style="font-size:.8rem;color:var(--gray-400);">Each item needs its own receipt (or mark "no receipt").</span>
    </div>

    <div class="item-table-wrap">
      <table class="item-table" id="itemTable">
        <thead>
          <tr>
            <th style="width:36px;"></th>
            <th class="cell-date">Date</th>
            <th class="cell-cat">Category</th>
            <th class="cell-desc">Description</th>
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

<script>
var ROW_INDEX = <?= count($rows) ?>;
var CATEGORIES = <?= json_encode($catLabels) ?>;

function emptyRowHtml(idx) {
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
        '<label class="receipt-zone">' +
          '<div class="spinner"></div>' +
          '<span class="zone-label">&#x1F4F7; Upload receipt</span>' +
          '<input type="file" accept="image/*,.pdf" onchange="handleRowFile(this)">' +
        '</label>' +
        '<div class="row-flag" style="display:none;"></div>' +
        '<label class="no-receipt-mini"><input type="checkbox" onchange="toggleNoReceiptMini(this)"> No receipt for this item</label>' +
      '</td>' +
    '</tr>';
}

function addRow() {
  var tbody = document.getElementById('itemTbody');
  var div = document.createElement('tbody');
  div.innerHTML = emptyRowHtml(ROW_INDEX);
  tbody.appendChild(div.firstElementChild);
  ROW_INDEX++;
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
    zone.style.opacity = '1';
    zone.style.pointerEvents = 'auto';
    zone.querySelector('.zone-label').innerHTML = '&#x1F4F7; Upload receipt';
    row.querySelector('.row-flag').style.display = 'none';
    recalcTotal();
    return;
  }
  row.remove();
  recalcTotal();
}

function toggleNoReceiptMini(cb) {
  var td   = cb.closest('td');
  var zone = td.querySelector('.receipt-zone');
  var mirror = td.querySelector('.f-no-receipt');
  if (mirror) mirror.value = cb.checked ? '1' : '0';
  zone.style.opacity = cb.checked ? '.45' : '1';
  zone.style.pointerEvents = cb.checked ? 'none' : 'auto';
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

function handleRowFile(input) {
  var file = input.files[0];
  if (!file) return;
  var td   = input.closest('td');
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
      }
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
        <label class="receipt-zone' . $zoneClass . '"' . $zoneStyle . '>
          <div class="spinner"></div>
          <span class="zone-label">' . $zoneLabel . '</span>
          <input type="file" accept="image/*,.pdf" onchange="handleRowFile(this)">
        </label>
        ' . $flagHtml . '
        <label class="no-receipt-mini"><input type="checkbox" onchange="toggleNoReceiptMini(this)"' . ($noReceiptChecked ? ' checked' : '') . '> No receipt for this item</label>
      </td>
    </tr>';
}
