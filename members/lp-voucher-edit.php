<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/lp-db.php';
requireLogin();

$member = getMember();
lpEnsureTables();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: lp-dashboard.php'); exit; }

$voucher = lpGetVoucher($id);
if (!$voucher) { header('Location: lp-dashboard.php'); exit; }

$isOwner = $voucher['submitted_by_email'] === $member['email'];
if (!$isOwner && !prodIsExec($member['email'])) { header('Location: lp-dashboard.php'); exit; }

$grants      = lpGetGrants();
$budgetLines = lpGetBudgetLines();
$mileageRate = (float)($voucher['mileage_rate'] ?: LP_MILEAGE_RATE);
$errors      = [];
$saved       = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $voucherName = trim($_POST['voucher_name'] ?? '');
    $voucherNum  = trim($_POST['voucher_number'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    $expIds      = $_POST['exp_id']        ?? [];
    $dates       = $_POST['expense_date']  ?? [];
    $descs       = $_POST['description']   ?? [];
    $travelKms   = $_POST['travel_km']     ?? [];
    $travelAmts  = $_POST['travel_amt']    ?? [];
    $meals       = $_POST['meals']         ?? [];
    $gifts       = $_POST['gifts']         ?? [];
    $misc        = $_POST['misc']          ?? [];
    $office      = $_POST['office']        ?? [];
    $phone       = $_POST['phone']         ?? [];
    $receiptPath = $_POST['receipt_path']  ?? [];
    $receiptOrig = $_POST['receipt_orig']  ?? [];
    $grantIds    = $_POST['grant_id']      ?? [];
    $blIds       = $_POST['budget_line_id']?? [];
    $expNotes    = $_POST['exp_notes']     ?? [];

    if (!$voucherName) $errors[] = 'Please enter a voucher name.';

    $hasRow = false;
    foreach ($descs as $d) { if (trim($d)) { $hasRow = true; break; } }
    if (!$hasRow) $errors[] = 'Please add at least one expense.';

    if (!$errors) {
        $db = getDB();

        // Update voucher header
        $db->prepare("UPDATE lp_vouchers SET voucher_number=?, name=?, notes=? WHERE id=?")
           ->execute([$voucherNum ?: null, $voucherName, $notes ?: null, $id]);

        // Collect IDs being kept
        $keptIds = array_filter(array_map('intval', $expIds));

        // Delete rows that were removed
        if ($keptIds) {
            $placeholders = implode(',', array_fill(0, count($keptIds), '?'));
            $db->prepare("DELETE FROM lp_expenses WHERE voucher_id=? AND id NOT IN ($placeholders)")
               ->execute(array_merge([$id], $keptIds));
        } else {
            $db->prepare("DELETE FROM lp_expenses WHERE voucher_id=?")->execute([$id]);
        }

        $upd = $db->prepare(
            "UPDATE lp_expenses SET expense_date=?, description=?, travel_km=?, travel_amt=?,
             meals=?, gifts=?, misc=?, office=?, phone=?,
             receipt_path=?, receipt_filename=?, grant_id=?, budget_line_id=?, notes=?, sort_order=?
             WHERE id=? AND voucher_id=?"
        );
        $ins = $db->prepare(
            "INSERT INTO lp_expenses
             (voucher_id, expense_date, description, travel_km, travel_amt,
              meals, gifts, misc, office, phone,
              receipt_path, receipt_filename, grant_id, budget_line_id, notes, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );

        foreach ($descs as $i => $desc) {
            $isEmpty = !trim($desc) && !((float)($travelAmts[$i]??0)+(float)($meals[$i]??0)+
                (float)($gifts[$i]??0)+(float)($misc[$i]??0)+
                (float)($office[$i]??0)+(float)($phone[$i]??0));
            if ($isEmpty) continue;

            $km   = (float)($travelKms[$i]  ?? 0);
            $tAmt = (float)($travelAmts[$i] ?? 0);
            if ($km > 0 && $tAmt == 0) $tAmt = round($km * $mileageRate, 2);

            $params = [
                $dates[$i]   ?: null,
                trim($desc),
                $km,
                $tAmt,
                round((float)($meals[$i]  ?? 0), 2),
                round((float)($gifts[$i]  ?? 0), 2),
                round((float)($misc[$i]   ?? 0), 2),
                round((float)($office[$i] ?? 0), 2),
                round((float)($phone[$i]  ?? 0), 2),
                ($receiptPath[$i] ?? '') ?: null,
                ($receiptOrig[$i] ?? '') ?: null,
                ($grantIds[$i] ?? '') ?: null,
                ($blIds[$i]    ?? '') ?: null,
                ($expNotes[$i] ?? '') ?: null,
                $i,
            ];

            $existingId = (int)($expIds[$i] ?? 0);
            if ($existingId && in_array($existingId, $keptIds)) {
                $upd->execute(array_merge($params, [$existingId, $id]));
            } else {
                $ins->execute(array_merge([$id], $params));
            }
        }
        $saved = true;
    }
}

// Reload expenses (fresh after save or on GET)
$expenses = lpGetExpenses($id);
// Reload voucher header in case it was updated
$voucher = lpGetVoucher($id);

$grantsJson      = json_encode(array_values($grants));
$budgetLinesJson = json_encode(array_values($budgetLines));

// Pass existing expenses to JS
$expensesJson = json_encode(array_values($expenses));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Voucher — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .voucher-header { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
    .hfield { flex: 1; min-width: 180px; }
    .hfield label { display: block; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); margin-bottom: .3rem; }
    .hfield input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .5rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .hfield input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .toolbar { display: flex; gap: .6rem; margin-bottom: 1rem; flex-wrap: wrap; align-items: center; }
    .btn-add { background: #fff; color: var(--primary); border: 1.5px solid var(--primary); border-radius: 8px; padding: .55rem 1rem; font-size: .88rem; font-weight: 700; cursor: pointer; }
    .btn-add:hover { background: var(--accent); }
    .mileage-note { font-size: .78rem; color: var(--gray-400); margin-left: auto; }
    .expense-table-wrap { overflow-x: auto; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; margin-bottom: 1.25rem; }
    table.expense-table { width: 100%; border-collapse: collapse; min-width: 1100px; font-size: .83rem; }
    .expense-table thead th { background: #1a2e1a; color: #fff; padding: .6rem .75rem; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
    .expense-table thead th.num { text-align: right; }
    .expense-table tbody tr { border-bottom: 1px solid var(--gray-100); }
    .expense-table tbody tr:last-child { border-bottom: none; }
    .expense-table tbody tr:hover { background: #fafafa; }
    .expense-table td { padding: .5rem .5rem; vertical-align: middle; }
    .expense-table tfoot td { padding: .65rem .75rem; background: #f0fdf4; font-weight: 700; font-size: .82rem; }
    .expense-table tfoot td.num { text-align: right; color: var(--primary); }
    .cell-input { border: 1px solid transparent; border-radius: 5px; padding: .3rem .5rem; font-size: .83rem; font-family: inherit; width: 100%; box-sizing: border-box; background: transparent; transition: border-color .12s, background .12s; }
    .cell-input:hover { border-color: var(--gray-200); background: #fff; }
    .cell-input:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 2px rgba(26,107,53,.1); }
    .cell-input.num { text-align: right; }
    .cell-desc { min-width: 200px; }
    .cell-km { width: 65px; }
    .cell-dollar { width: 82px; }
    .cell-select { width: 100%; border: 1px solid transparent; border-radius: 5px; padding: .3rem .4rem; font-size: .78rem; font-family: inherit; background: transparent; cursor: pointer; }
    .cell-select:hover, .cell-select:focus { border-color: var(--primary); background: #fff; outline: none; }
    .receipt-cell { width: 52px; text-align: center; }
    .receipt-attach-btn { background: none; border: 1px dashed var(--gray-300); border-radius: 5px; width: 34px; height: 28px; cursor: pointer; font-size: .85rem; color: var(--gray-400); display: flex; align-items: center; justify-content: center; margin: auto; transition: border-color .12s, color .12s, background .12s; }
    .receipt-attach-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--accent); }
    .receipt-has-file { width: 34px; height: 28px; border-radius: 5px; background: #dcfce7; border: 1px solid #86efac; display: flex; align-items: center; justify-content: center; margin: auto; cursor: pointer; font-size: .85rem; }
    .receipt-has-file:hover { background: #bbf7d0; }
    .scan-spinner { display: none; width: 18px; height: 18px; border: 2px solid var(--gray-200); border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; margin: auto; }
    @keyframes spin { to { transform: rotate(360deg); } }
    #receiptPreview { position: fixed; z-index: 9998; display: none; background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,.2); padding: 6px; pointer-events: none; }
    #receiptPreview img { max-width: 260px; max-height: 340px; display: block; border-radius: 6px; object-fit: contain; }
    .btn-row-remove { background: none; border: none; cursor: pointer; color: var(--gray-300); font-size: 1rem; padding: .2rem .4rem; border-radius: 4px; transition: color .12s; }
    .btn-row-remove:hover { color: #dc2626; background: #fef2f2; }
    .total-label { font-size: .72rem; font-weight: 800; text-transform: uppercase; color: var(--gray-500); letter-spacing: .05em; }
    .save-bar { position: sticky; bottom: 1rem; display: flex; gap: .75rem; align-items: center; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1rem 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,.1); flex-wrap: wrap; }
    .save-bar .total-display { font-size: 1.2rem; font-weight: 900; color: var(--primary); margin-right: auto; }
    .save-bar .total-display span { font-size: .75rem; font-weight: 600; color: var(--gray-400); }
    .error-list { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .85rem; color: #dc2626; }
    .saved-notice { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .88rem; color: #166534; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="portal-header">
    <h1>Edit Voucher</h1>
    <div style="display:flex;gap:.75rem;align-items:center;">
      <a href="lp-voucher-view.php?id=<?= $id ?>" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">View &amp; Export</a>
      <a class="back-link" href="lp-dashboard.php">← LP Expenses</a>
    </div>
  </div>

  <?php if ($saved): ?>
  <div class="saved-notice">✓ Voucher updated successfully.</div>
  <?php endif; ?>

  <?php if ($errors): ?>
  <div class="error-list"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" id="voucherForm">

  <div class="voucher-header">
    <div class="hfield" style="flex:2;">
      <label>Voucher Name *</label>
      <input type="text" name="voucher_name" value="<?= htmlspecialchars($voucher['name']) ?>">
    </div>
    <div class="hfield" style="flex:0.7;">
      <label>Voucher #</label>
      <input type="text" name="voucher_number" value="<?= htmlspecialchars($voucher['voucher_number'] ?? '') ?>">
    </div>
    <div class="hfield" style="flex:2;">
      <label>Notes</label>
      <input type="text" name="notes" value="<?= htmlspecialchars($voucher['notes'] ?? '') ?>">
    </div>
  </div>

  <div class="toolbar">
    <button type="button" class="btn-add" onclick="addRow()">+ Add Row</button>
    <span class="mileage-note">Mileage: $<?= number_format($mileageRate, 2) ?>/km · attach receipts with 📎</span>
  </div>

  <div class="expense-table-wrap">
    <table class="expense-table" id="expenseTable">
      <thead>
        <tr>
          <th style="width:52px;">Receipt</th>
          <th>Date</th>
          <th>Description</th>
          <th class="num">km</th>
          <th class="num">Travel $</th>
          <th class="num">Meals $</th>
          <th class="num">Gifts $</th>
          <th class="num">Misc $</th>
          <th class="num">Office $</th>
          <th class="num">Phone $</th>
          <th class="num">Total</th>
          <th>BCTF Grant</th>
          <th>Budget Line</th>
          <th style="width:32px;"></th>
        </tr>
      </thead>
      <tbody id="expenseRows"></tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="total-label">Totals</td>
          <td class="num" id="tot_km">—</td>
          <td class="num" id="tot_travel">—</td>
          <td class="num" id="tot_meals">—</td>
          <td class="num" id="tot_gifts">—</td>
          <td class="num" id="tot_misc">—</td>
          <td class="num" id="tot_office">—</td>
          <td class="num" id="tot_phone">—</td>
          <td class="num" id="tot_total" style="font-size:.92rem;">—</td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="save-bar">
    <div class="total-display"><span>Voucher Total</span><br><span id="grandTotal">$0.00</span></div>
    <button type="submit" class="btn btn-primary" style="padding:.65rem 1.5rem;font-size:.95rem;">💾 Save Changes</button>
  </div>

  </form>
</div>

<div id="receiptPreview"><img src="" alt="Receipt preview"></div>

<script>
const GRANTS       = <?= $grantsJson ?>;
const BUDGET_LINES = <?= $budgetLinesJson ?>;
const MILEAGE_RATE = <?= $mileageRate ?>;
const EXISTING     = <?= $expensesJson ?>;
let rowCount = 0;

function buildGrantOptions(selectedId) {
    let html = '<option value="">— No grant —</option>';
    GRANTS.forEach(g => { html += `<option value="${g.id}" ${g.id == selectedId ? 'selected' : ''}>${g.name}</option>`; });
    return html;
}
function buildBLOptions(selectedId) {
    let html = '<option value="">— Select budget line —</option>';
    BUDGET_LINES.forEach(b => { html += `<option value="${b.id}" ${b.id == selectedId ? 'selected' : ''}>${b.name}</option>`; });
    return html;
}

function addRow(data = {}) {
    rowCount++;
    const id = rowCount;
    const tr = document.createElement('tr');
    tr.id = 'row-' + id;
    tr.innerHTML = `
      <td class="receipt-cell">
        <div id="receipt-wrap-${id}">
          <button type="button" class="receipt-attach-btn" id="attach-btn-${id}"
            title="Attach receipt" onclick="triggerRowScan(${id})">📎</button>
          <div class="scan-spinner" id="spinner-${id}"></div>
          <input type="file" id="file-${id}" accept="image/*,.pdf" capture="environment" style="display:none"
            onchange="handleRowScan(this, ${id})">
        </div>
        <input type="hidden" name="exp_id[]"       value="${data.db_id || ''}">
        <input type="hidden" name="receipt_path[]" id="rpath-${id}" value="${escHtml(data.receipt_path || '')}">
        <input type="hidden" name="receipt_orig[]" id="rorig-${id}" value="${escHtml(data.receipt_filename || '')}">
      </td>
      <td><input type="date" name="expense_date[]" class="cell-input" value="${data.expense_date || ''}" style="width:130px;"></td>
      <td><input type="text" name="description[]" class="cell-input cell-desc" placeholder="Description" value="${escHtml(data.description || '')}"></td>
      <td><input type="number" name="travel_km[]" id="km-${id}" class="cell-input num cell-km" placeholder="0" step="0.1" min="0" value="${data.travel_km > 0 ? data.travel_km : ''}" oninput="calcMileage(${id})"></td>
      <td><input type="number" name="travel_amt[]" id="tamt-${id}" class="cell-input num cell-dollar" placeholder="0.00" step="0.01" min="0" value="${data.travel_amt > 0 ? parseFloat(data.travel_amt).toFixed(2) : ''}" oninput="updateRow(${id})"></td>
      <td><input type="number" name="meals[]" class="cell-input num cell-dollar" placeholder="0.00" step="0.01" min="0" value="${data.meals > 0 ? parseFloat(data.meals).toFixed(2) : ''}" oninput="updateRow(${id})"></td>
      <td><input type="number" name="gifts[]" class="cell-input num cell-dollar" placeholder="0.00" step="0.01" min="0" value="${data.gifts > 0 ? parseFloat(data.gifts).toFixed(2) : ''}" oninput="updateRow(${id})"></td>
      <td><input type="number" name="misc[]"  class="cell-input num cell-dollar" placeholder="0.00" step="0.01" min="0" value="${data.misc  > 0 ? parseFloat(data.misc).toFixed(2)  : ''}" oninput="updateRow(${id})"></td>
      <td><input type="number" name="office[]" class="cell-input num cell-dollar" placeholder="0.00" step="0.01" min="0" value="${data.office > 0 ? parseFloat(data.office).toFixed(2) : ''}" oninput="updateRow(${id})"></td>
      <td><input type="number" name="phone[]" class="cell-input num cell-dollar" placeholder="0.00" step="0.01" min="0" value="${data.phone  > 0 ? parseFloat(data.phone).toFixed(2)  : ''}" oninput="updateRow(${id})"></td>
      <td class="num" id="rowtotal-${id}" style="font-weight:700;color:var(--primary);white-space:nowrap;">$0.00</td>
      <td><select name="grant_id[]" class="cell-select" style="min-width:160px;">${buildGrantOptions(data.grant_id || '')}</select></td>
      <td><select name="budget_line_id[]" class="cell-select" style="min-width:180px;">${buildBLOptions(data.budget_line_id || '')}</select></td>
      <td><button type="button" class="btn-row-remove" onclick="removeRow(${id})">×</button></td>
    `;
    document.getElementById('expenseRows').appendChild(tr);

    if (data.receipt_path) {
        showThumb(id, data.receipt_path, null);
    }
    updateRow(id);
    updateTotals();
    return id;
}

function removeRow(id) {
    const row = document.getElementById('row-' + id);
    if (row) row.remove();
    updateTotals();
}

function calcMileage(id) {
    const km = parseFloat(document.getElementById('km-' + id)?.value) || 0;
    const tamtEl = document.getElementById('tamt-' + id);
    if (tamtEl && km > 0) tamtEl.value = (km * MILEAGE_RATE).toFixed(2);
    updateRow(id);
}
function getVal(el) { return parseFloat(el?.value) || 0; }
function updateRow(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;
    let total = 0;
    row.querySelectorAll('[name="travel_amt[]"],[name="meals[]"],[name="gifts[]"],[name="misc[]"],[name="office[]"],[name="phone[]"]')
       .forEach(inp => total += getVal(inp));
    const el = document.getElementById('rowtotal-' + id);
    if (el) el.textContent = '$' + total.toFixed(2);
    updateTotals();
}
function updateTotals() {
    const rows = document.querySelectorAll('#expenseRows tr');
    let km=0, travel=0, meals=0, gifts=0, misc=0, office=0, phone=0;
    rows.forEach(row => {
        km     += getVal(row.querySelector('[name="travel_km[]"]'));
        travel += getVal(row.querySelector('[name="travel_amt[]"]'));
        meals  += getVal(row.querySelector('[name="meals[]"]'));
        gifts  += getVal(row.querySelector('[name="gifts[]"]'));
        misc   += getVal(row.querySelector('[name="misc[]"]'));
        office += getVal(row.querySelector('[name="office[]"]'));
        phone  += getVal(row.querySelector('[name="phone[]"]'));
    });
    const grand = travel+meals+gifts+misc+office+phone;
    document.getElementById('tot_km').textContent     = km     ? km.toFixed(1)+' km' : '—';
    document.getElementById('tot_travel').textContent = travel ? '$'+travel.toFixed(2) : '—';
    document.getElementById('tot_meals').textContent  = meals  ? '$'+meals.toFixed(2)  : '—';
    document.getElementById('tot_gifts').textContent  = gifts  ? '$'+gifts.toFixed(2)  : '—';
    document.getElementById('tot_misc').textContent   = misc   ? '$'+misc.toFixed(2)   : '—';
    document.getElementById('tot_office').textContent = office ? '$'+office.toFixed(2) : '—';
    document.getElementById('tot_phone').textContent  = phone  ? '$'+phone.toFixed(2)  : '—';
    document.getElementById('tot_total').textContent  = grand  ? '$'+grand.toFixed(2)  : '—';
    document.getElementById('grandTotal').textContent = '$' + grand.toFixed(2);
}

function triggerRowScan(rowId) { document.getElementById('file-' + rowId).click(); }
function handleRowScan(input, rowId) {
    if (!input.files[0]) return;
    // Instant local preview
    const reader = new FileReader();
    reader.onload = e => showThumb(rowId, null, e.target.result);
    reader.readAsDataURL(input.files[0]);
    // Upload + AI scan
    const spinner = document.getElementById('spinner-' + rowId);
    if (spinner) spinner.style.display = 'block';
    const fd = new FormData();
    fd.append('receipt', input.files[0]);
    fetch('lp-scan.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (spinner) spinner.style.display = 'none';
            const row = document.getElementById('row-' + rowId);
            if (!row) return;
            if (data.date)        row.querySelector('[name="expense_date[]"]').value = data.date;
            if (data.description) row.querySelector('[name="description[]"]').value  = data.description;
            const amts = {travel_amt: data.travel_amount, meals: data.meals_amount, gifts: data.gifts_amount, misc: data.misc_amount, office: data.office_amount, phone: data.phone_amount};
            for (const [k,v] of Object.entries(amts)) {
                if (v > 0) { const el = row.querySelector(`[name="${k}[]"]`); if(el) el.value = v.toFixed(2); }
            }
            if (data.suggested_grant_id) row.querySelector('[name="grant_id[]"]').value = data.suggested_grant_id;
            if (data.suggested_bl_id)    row.querySelector('[name="budget_line_id[]"]').value = data.suggested_bl_id;
            if (data.saved_path) {
                document.getElementById('rpath-' + rowId).value = data.saved_path;
                document.getElementById('rorig-' + rowId).value = data.original_name || '';
                showThumb(rowId, data.saved_path, null);
            }
            updateRow(rowId);
        })
        .catch(() => { if (spinner) spinner.style.display = 'none'; });
}

function showThumb(rowId, savedPath, dataUrl) {
    const wrap = document.getElementById('receipt-wrap-' + rowId);
    if (!wrap) return;
    const btn = document.getElementById('attach-btn-' + rowId);
    if (btn) btn.remove();
    wrap.querySelector('.receipt-has-file')?.remove();
    const src = dataUrl || ('lp-receipt.php?f=' + encodeURIComponent(savedPath));
    const indicator = document.createElement('div');
    indicator.className = 'receipt-has-file';
    indicator.title = 'Receipt attached — hover to preview, click to change';
    indicator.innerHTML = '📄';
    indicator.dataset.src = src;
    indicator.onclick = () => triggerRowScan(rowId);
    indicator.addEventListener('mouseenter', e => showHoverPreview(e, src));
    indicator.addEventListener('mouseleave', hideHoverPreview);
    indicator.addEventListener('mousemove',  moveHoverPreview);
    wrap.insertBefore(indicator, wrap.querySelector('.scan-spinner'));
}

const hoverPreview = document.getElementById('receiptPreview');
function showHoverPreview(e, src) { hoverPreview.querySelector('img').src = src; hoverPreview.style.display = 'block'; positionPreview(e); }
function hideHoverPreview() { hoverPreview.style.display = 'none'; hoverPreview.querySelector('img').src = ''; }
function moveHoverPreview(e) { positionPreview(e); }
function positionPreview(e) {
    const pad=16, w=272, h=352, vw=window.innerWidth, vh=window.innerHeight;
    let x=e.clientX+pad, y=e.clientY+pad;
    if (x+w>vw) x=e.clientX-w-pad;
    if (y+h>vh) y=e.clientY-h-pad;
    hoverPreview.style.left=x+'px'; hoverPreview.style.top=y+'px';
}
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Load existing expenses, then add blank rows up to 10 minimum
EXISTING.forEach(e => addRow(e));
const minRows = Math.max(0, 10 - EXISTING.length);
for (let i = 0; i < minRows; i++) addRow();
</script>
</body>
</html>
