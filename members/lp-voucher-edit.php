<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/exec-db.php';
requireLogin();

$member = getMember();
lpEnsureTables();
lpEnsureApprovalColumns();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: lp-dashboard.php'); exit; }

$voucher = lpGetVoucher($id);
if (!$voucher) { header('Location: lp-dashboard.php'); exit; }

$isOwner    = $voucher['submitted_by_email'] === $member['email'];
$isAdmin    = execIsAdmin($member['email']);
$isReviewer = lpCanReview($member['email']);
$isTreasurer = lpCanSign1($member['email']);   // signer 1; labels the read-only banner
if (!$isOwner && !prodIsExec($member['email']) && !$isReviewer) {
    header('Location: lp-dashboard.php');
    exit;
}

$grants      = lpGetGrants();
$budgetLines = lpGetBudgetLines();
$mileageRate = (float)($voucher['mileage_rate'] ?: LP_MILEAGE_RATE);
$errors      = [];
$saved       = false;

// Reviewers (treasurer/VP) see the voucher read-only — they approve via approvals.php
$readOnly = $isReviewer && !$isOwner;

// The voucher form saves expenses and can submit for approval, which
// emails the Treasurer. Same transition the view page protects.
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfCheck();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $readOnly) {
    header('Location: approvals.php');
    exit;
}

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

        // Submit for approval (saves first, then submits)
        if (!empty($_POST['_submit_for_approval'])) {
            lpEnsureApprovalColumns();
            $freshExpenses = lpGetExpenses($id);
            if (count($freshExpenses) > 0) {
                $freshVoucher = lpGetVoucher($id);
                $total = array_sum(array_map('lpRowTotal', $freshExpenses));
                lpSubmitVoucher($id);
                lpEmailSubmitted($freshVoucher, $total);
                header('Location: lp-voucher-edit.php?id=' . $id . '&submitted=1');
                exit;
            }
        }
    }
}

// Reload expenses (fresh after save or on GET)
$expenses = lpGetExpenses($id);
$receiptCount = count(array_filter($expenses, function ($e) {
    return !empty($e['receipt_path']);
}));
// Reload voucher header in case it was updated
$voucher = lpGetVoucher($id);

$grantsJson      = json_encode(array_values($grants));
$budgetLinesJson = json_encode(array_values($budgetLines));

// Pass existing expenses to JS
$expensesJson = json_encode(array_values($expenses));

// Generate a mobile upload token for QR code
$uploadToken   = lpCreateUploadToken($id, $member['email']);
$protocol      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host          = $_SERVER['HTTP_HOST'] ?? 'bvtu.ca';
$mobileUrl     = "{$protocol}://{$host}/members/lp-mobile-receipt.php?token={$uploadToken}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Voucher — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .approval-card { background:#fff; border:1px solid var(--gray-200); border-radius:12px;
                     padding:1rem 1.25rem; margin-bottom:1.25rem; max-width:520px; }
    .approval-head { font-size:.72rem; font-weight:800; text-transform:uppercase;
                     letter-spacing:.06em; color:var(--gray-400); margin-bottom:.5rem; }
    .wrap { max-width: 1500px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
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
    /* No overflow here on wide screens: any scroll container would become the
       scrollport and break the sticky header below. Table fits under 1500px
       wrap, so horizontal scroll is only needed on narrower viewports. */
    /*
     * Scrolls at every width, not only below 1200px. The table needs 1571px and
     * .wrap is capped at 1500, so it never fitted on any screen: above 1200 the
     * Budget Line column and the row-delete button simply hung outside the card
     * with no scrollbar to reach them, dragging the whole page sideways instead.
     */
    .expense-table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; margin-bottom: 1.25rem; overflow-x: auto; }
    /*
     * A horizontal scrollport is also a vertical one, which stops the sticky
     * header sticking to the page. Above 1440px the trimmed table fits with room
     * to spare and needs no scrolling, so the header is given back there.
     */
    @media (min-width: 1441px) { .expense-table-wrap { overflow-x: visible; } }
    table.expense-table { width: 100%; border-collapse: collapse; min-width: 1100px; font-size: .83rem; }
    .expense-table thead th { position: sticky; top: 0; z-index: 3; background: #1a2e1a; color: #fff; padding: .6rem .75rem; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,.18); }
    .expense-table thead th:first-child { border-top-left-radius: 12px; }
    .expense-table thead th:last-child  { border-top-right-radius: 12px; }
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
    .receipt-cell { width: 68px; text-align: center; }
    .receipt-attach-btn { background: none; border: 1px dashed var(--gray-300); border-radius: 5px; width: 28px; height: 28px; cursor: pointer; font-size: .82rem; color: var(--gray-400); display: flex; align-items: center; justify-content: center; transition: border-color .12s, color .12s, background .12s; }
    .receipt-attach-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--accent); }
    .receipt-has-file { width: 28px; height: 28px; border-radius: 5px; background: #dcfce7; border: 1px solid #86efac; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: .82rem; }
    .receipt-has-file:hover { background: #bbf7d0; }
    .receipt-open-btn { display: block; font-size: .6rem; font-weight: 700; color: var(--primary); text-decoration: none; text-align: center; line-height: 1.3; margin-top: .1rem; white-space: nowrap; }
    .receipt-open-btn:hover { text-decoration: underline; color: var(--primary-dk); }
    .receipt-phone-btn { background: none; border: 1px dashed #86efac; border-radius: 5px; width: 28px; height: 28px; cursor: pointer; font-size: .78rem; color: var(--primary); display: flex; align-items: center; justify-content: center; transition: border-color .12s, background .12s; }
    .receipt-phone-btn:hover { background: #f0fdf4; border-style: solid; }
    .receipt-phone-btn.targeting { background: #dcfce7; border-style: solid; border-color: var(--primary); }
    .receipt-btn-group { display: flex; gap: 3px; align-items: center; justify-content: center; }
    .qr-target { display:none; background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; padding:.5rem .75rem; margin-top:.6rem; font-size:.82rem; color:#166534; align-items:center; gap:.5rem; flex-wrap:wrap; }
    .qr-target strong { font-weight:800; }
    .qr-target-clear { background:none; border:none; cursor:pointer; color:#9ca3af; font-size:.9rem; margin-left:auto; padding:.1rem .3rem; border-radius:4px; }
    .qr-target-clear:hover { color:#dc2626; background:#fef2f2; }
    .scan-spinner { display: none; width: 18px; height: 18px; border: 2px solid var(--gray-200); border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; margin: auto; }
    @keyframes spin { to { transform: rotate(360deg); } }
    #receiptPreview { position: fixed; z-index: 9998; display: none; background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,.2); padding: 6px; pointer-events: none; }
    #receiptPreview img { max-width: 260px; max-height: 340px; display: block; border-radius: 6px; object-fit: contain; }
    .btn-row-remove { background: none; border: none; cursor: pointer; color: var(--gray-300); font-size: 1rem; padding: .2rem .4rem; border-radius: 4px; transition: color .12s; }
    .btn-row-remove:hover { color: #dc2626; background: #fef2f2; }
    /* Drag-and-drop */
    .drop-zone { border: 2px dashed var(--gray-300); border-radius: 10px; padding: .75rem 1rem; text-align: center; color: var(--gray-400); font-size: .84rem; font-weight: 600; margin-bottom: .75rem; transition: border-color .15s, background .15s, color .15s; user-select: none; }
    .drop-zone.drag-active { border-color: #86efac; background: #f0fdf4; color: var(--primary); }
    .drop-zone.drag-over   { border-color: var(--primary); background: #dcfce7; color: var(--primary); }
    .receipt-wrap.drag-over { outline: 2px solid var(--primary); outline-offset: 2px; border-radius: 6px; background: #dcfce7; }

    .total-label { font-size: .72rem; font-weight: 800; text-transform: uppercase; color: var(--gray-500); letter-spacing: .05em; }
    .save-bar { position: sticky; bottom: 1rem; display: flex; gap: .75rem; align-items: center; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1rem 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,.1); flex-wrap: wrap; }
    .save-bar .total-display { font-size: 1.2rem; font-weight: 900; color: var(--primary); margin-right: auto; }
    .save-bar .total-display span { font-size: .75rem; font-weight: 600; color: var(--gray-400); }
    .error-list { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .85rem; color: #dc2626; }
    .saved-notice { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .88rem; color: #166534; }
    /* ── Receipt toast notification ── */
    #receiptToast { position:fixed; bottom:5rem; left:50%; transform:translateX(-50%) translateY(20px); background:#1a6b35; color:#fff; font-size:.9rem; font-weight:700; padding:.75rem 1.25rem; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.2); opacity:0; transition:opacity .3s, transform .3s; pointer-events:none; z-index:9999; white-space:nowrap; }
    #receiptToast.show { opacity:1; transform:translateX(-50%) translateY(0); }
    @keyframes rowFlash { 0%,100%{background:transparent} 30%{background:#dcfce7} }
    .row-flash { animation: rowFlash 1.6s ease; }

    /* ── Category, amount and the split breakdown ───────────────── */
    .cell-cat { min-width: 128px; }
    .cell-cat.needs-category { border-color: #dc2626; background: #fef2f2; }
    .cell-amount.is-split { background: var(--gray-100); cursor: not-allowed; }

    .split-row > td { background: #f8fafc; padding: .6rem .75rem; }
    .split-wrap { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .split-lead { font-size: .78rem; font-weight: 700; color: var(--gray-600); }
    .split-field { display: flex; flex-direction: column; font-size: .7rem; font-weight: 700;
                   text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); gap: .15rem; }
    .split-input { width: 92px; border: 1px solid var(--gray-300); border-radius: 5px;
                   padding: .25rem .4rem; font-size: .82rem; font-family: inherit; }
    .split-close { margin-left: auto; background: none; border: none; font: inherit; font-size: .78rem;
                   color: var(--primary); font-weight: 600; cursor: pointer; text-decoration: underline; }

    /* ── Per-category totals, once six table columns ────────────── */
    .cat-totals { display: flex; flex-wrap: wrap; gap: .5rem; margin: -.75rem 0 1.25rem; }
    .cat-total { background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
                 padding: .35rem .7rem; font-size: .84rem; font-weight: 700; color: var(--gray-800); }
    .cat-total-label { font-size: .68rem; font-weight: 800; text-transform: uppercase;
                       letter-spacing: .05em; color: var(--gray-500); margin-right: .35rem; }
    .cat-total-grand { background: var(--primary); color: #fff; border-color: var(--primary); }
    .cat-total-grand .cat-total-label { color: rgba(255,255,255,.75); }
    .cat-total-empty { font-size: .83rem; color: var(--gray-400); }

    /*
     * ── One card per row on a narrow screen ─────────────────────
     * Below 900px even the trimmed table scrolls, and scrolling a form
     * sideways to reach the field you are filling in is the worst of both.
     */
    @media (max-width: 900px) {
      .expense-table-wrap { border: none; background: none; overflow-x: visible; }
      table.expense-table, table.expense-table tbody, table.expense-table tfoot,
      table.expense-table tr, table.expense-table td { display: block; width: 100%; min-width: 0; }
      table.expense-table thead { display: none; }
      table.expense-table tbody tr { background: #fff; border: 1px solid var(--gray-200);
        border-radius: 10px; padding: .85rem; margin-bottom: .85rem; }
      table.expense-table td { padding: .3rem 0; text-align: left !important; }
      table.expense-table td[data-label]::before {
        content: attr(data-label);
        display: block; font-size: .68rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: .05em; color: var(--gray-500); margin-bottom: .15rem;
      }
      .cell-input, .cell-select, .cell-cat { width: 100% !important; }
      input[type=date].cell-input { width: 100% !important; }
      .cell-km, .cell-dollar { width: 100% !important; }
      /* Right-aligned figures make sense in a column; in a card they drift away
         from the label they belong to. */
      .cell-input.num, table.expense-table td.num { text-align: left !important; }
      .btn-row-remove { width: auto; padding: .3rem .7rem; font-size: .95rem; }
      table.expense-table tfoot tr { background: #fff; border: 1px solid var(--gray-200);
        border-radius: 10px; padding: .85rem; }
      /* A sibling row becomes its own card, which would float free of the row
         it belongs to; the accent edge and the pulled-up margin keep them read
         as one thing. */
      table.expense-table tbody tr.split-row { margin-top: -.6rem; border-top: none;
        border-left: 3px solid var(--primary); border-radius: 0 0 10px 10px; }
      .split-row > td { background: none; padding: .3rem 0; }
      .split-wrap { gap: .5rem; }
      .split-field { flex: 1 1 28%; }
      .split-input { width: 100%; }
      .split-close { margin-left: 0; flex-basis: 100%; text-align: left; }
    }

    /* ── Phone upload QR panel ── */
    .qr-panel { display:none; background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1.25rem; }
    .qr-panel.open { display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap; }
    .qr-box { flex-shrink:0; }
    #qrCanvas { border-radius:8px; }
    .qr-instructions { flex:1; min-width:200px; }
    .qr-instructions h3 { font-size:.95rem; font-weight:800; color:var(--primary); margin:0 0 .5rem; }
    .qr-instructions p  { font-size:.83rem; color:var(--gray-500); line-height:1.5; margin-bottom:.6rem; }
    .qr-url { font-size:.72rem; color:var(--gray-400); word-break:break-all; background:var(--off-white); padding:.4rem .6rem; border-radius:5px; }
    .qr-expiry { font-size:.73rem; color:#92400e; margin-top:.5rem; }
    .btn-phone { background:#f0fdf4; color:var(--primary); border:1.5px solid #86efac; border-radius:8px; padding:.5rem .9rem; font-size:.85rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.4rem; }
    .btn-phone:hover { background:#dcfce7; }
    .btn-wide { background:#fff; color:var(--gray-500); border:1.5px solid var(--gray-300); border-radius:8px; padding:.5rem .9rem; font-size:.85rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.4rem; margin-left:auto; }
    .btn-wide:hover { background:#f9fafb; }

    /* ── Pending receipts tray ── */
    .pending-tray { display:none; background:#f0fdf4; border:1.5px solid #86efac; border-radius:12px; padding:1rem 1.25rem; margin-bottom:1.25rem; }
    .pending-tray.has-items { display:block; }
    .pending-tray-header { display:flex; align-items:center; gap:.6rem; margin-bottom:.85rem; }
    .pending-tray-header h3 { font-size:.9rem; font-weight:800; color:var(--primary); margin:0; }
    .pending-badge { background:var(--primary); color:#fff; font-size:.7rem; font-weight:800; border-radius:100px; padding:.1rem .5rem; }
    .pending-tray-scroll { display:flex; gap:.75rem; flex-wrap:wrap; }
    .pending-card { background:#fff; border:1px solid #bbf7d0; border-radius:10px; padding:.75rem; width:180px; flex-shrink:0; }
    .pending-card img { width:100%; height:90px; object-fit:cover; border-radius:6px; margin-bottom:.5rem; cursor:pointer; }
    .pending-card .pdf-thumb { width:100%; height:90px; background:#f9fafb; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:2rem; margin-bottom:.5rem; }
    .pending-card .p-desc { font-size:.78rem; font-weight:700; color:#1a2e1a; margin-bottom:.15rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .pending-card .p-amt  { font-size:.8rem; color:var(--primary); font-weight:800; margin-bottom:.5rem; }
    .pending-card .p-flag { font-size:.7rem; color:#92400e; margin-bottom:.4rem; }
    .btn-claim { width:100%; background:var(--primary); color:#fff; border:none; border-radius:6px; padding:.4rem; font-size:.78rem; font-weight:700; cursor:pointer; margin-bottom:.3rem; }
    .btn-claim:hover { background:var(--primary-dk); }
    .attach-select { width:100%; border:1px solid #86efac; border-radius:6px; padding:.32rem .4rem; font-size:.74rem; font-family:inherit; background:#f0fdf4; color:var(--primary); font-weight:600; cursor:pointer; }
    .attach-select:focus { outline:none; border-color:var(--primary); }
  </style>
</head>
<body>
<div class="wrap">

  <div class="portal-header">
    <h1>Edit Voucher</h1>
    <div style="display:flex;gap:.75rem;align-items:center;">
      <?php if ($receiptCount): ?>
      <a href="lp-export-receipts.php?voucher_id=<?= $id ?>" class="btn btn-outline"
         style="padding:.45rem .9rem;font-size:.85rem;"
         title="Download every receipt on this voucher as a ZIP, with a summary.csv">
        &#x2B07; All Receipts (<?= $receiptCount ?>)
      </a>
      <?php endif; ?>
      <?php if (!$readOnly): ?>
      <!-- lp-voucher-view.php is President-only, so this would just bounce a reviewer -->
      <a href="lp-voucher-view.php?id=<?= $id ?>" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">View &amp; Export</a>
      <?php endif; ?>
      <!-- Reviewers can't open lp-dashboard.php either — send them to their queue -->
      <a class="back-link" href="<?= $readOnly ? 'approvals.php' : 'lp-dashboard.php' ?>">
        ← <?= $readOnly ? 'Review queue' : 'Expenses &amp; Grants' ?>
      </a>
    </div>
  </div>

  <?php if ($readOnly): ?>
  <div class="saved-notice" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;">
    &#x1F441; Reviewing as <?= $isTreasurer ? 'Treasurer' : 'Vice-President' ?> — read only.
    <a href="approvals.php" style="color:#1e40af;font-weight:700;margin-left:.5rem;">&#x2190; Back to review queue</a>
  </div>
  <?php endif; ?>
  <?php if ($voucher['status'] !== 'draft'): ?>
  <div class="approval-card">
    <div class="approval-head">Approval Progress</div>
    <?= lpApprovalTrail($voucher) ?>
  </div>
  <?php endif; ?>

  <?php if ($saved): ?>
  <div class="saved-notice">&#x2713; Voucher updated successfully.</div>
  <?php endif; ?>
  <?php if (isset($_GET['submitted'])): ?>
  <div class="saved-notice" style="background:#fffbeb;border-color:#fde68a;color:#92400e;">
    &#x2713; Voucher submitted for approval. The Treasurer has been notified.
  </div>
  <?php endif; ?>

  <?php if ($errors): ?>
  <div class="error-list"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST" id="voucherForm">
    <?= csrfField() ?>

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
    <button type="button" class="btn-phone" onclick="toggleQR()">📱 Phone Upload</button>
    <span class="mileage-note">Mileage: $<?= number_format($mileageRate, 2) ?>/km · attach receipts with 📎</span>
  </div>

  <!-- Global drop zone -->
  <div id="globalDropZone" class="drop-zone">
    ⬇ Drop receipt files here — each file is added as a new expense row automatically
  </div>

  <!-- QR code panel -->
  <div class="qr-panel" id="qrPanel">
    <div class="qr-box">
      <img id="qrImg" src="" width="180" height="180" style="border-radius:8px;display:block;" alt="QR code">
    </div>
    <div class="qr-instructions">
      <h3>📱 Upload receipts from your phone</h3>
      <p>Scan this code with your phone's camera. Take a photo of any receipt and it will appear here automatically — no AirDrop needed.</p>
      <p>Or copy the link and text it to yourself:</p>
      <div class="qr-url" id="qrUrlText"><?= htmlspecialchars($mobileUrl) ?></div>
      <div class="qr-target" id="qrTarget">
        📌 Next receipt → <strong id="qrTargetLabel"></strong>
        <button class="qr-target-clear" onclick="clearPhoneTarget()" title="Clear target">✕</button>
      </div>
    </div>
  </div>

  <!-- Pending receipts tray (shown when phone receipts arrive) -->
  <div class="pending-tray" id="pendingTray">
    <div class="pending-tray-header">
      <h3>📥 Receipts from your phone</h3>
      <span class="pending-badge" id="pendingBadge">0</span>
    </div>
    <div class="pending-tray-scroll" id="pendingCards"></div>
  </div>

  <div class="expense-table-wrap">
    <table class="expense-table" id="expenseTable">
      <thead>
        <tr>
          <th style="width:52px;">Receipt</th>
          <th>Date</th>
          <th>Description</th>
          <th class="num">km</th>
          <th>Category</th>
          <th class="num">Amount</th>
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
          <td></td>
          <td class="num" id="tot_total" style="font-size:.92rem;">—</td>
          <td colspan="3"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Per-category totals, which used to be six columns in the table footer. -->
  <div class="cat-totals" id="catTotals"><span class="cat-total-empty">No amounts entered yet.</span></div>

  <div class="save-bar">
    <div class="total-display"><span>Voucher Total</span><br><span id="grandTotal">$0.00</span></div>
    <?php if ($readOnly): ?>
    <a href="approvals.php" class="btn btn-primary" style="padding:.65rem 1.5rem;font-size:.95rem;">&#x2190; Back to Approvals</a>
    <?php elseif ($voucher['status'] === 'draft' || (($isOwner || $isAdmin) && !in_array($voucher['status'], ['paid']))): ?>
    <button type="submit" class="btn btn-primary" style="padding:.65rem 1.5rem;font-size:.95rem;">💾 Save Changes</button>
    <?php if ($voucher['status'] === 'draft' && count($expenses) > 0): ?>
    <button type="submit" name="_submit_for_approval" value="1"
            class="btn btn-primary" style="padding:.65rem 1.5rem;font-size:.95rem;background:#166534;"
            onclick="return confirm('Save and submit this voucher for Treasurer approval? You will not be able to edit it after submission.')">
      ✅ Submit for Approval
    </button>
    <?php endif; ?>
    <?php if ($voucher['status'] !== 'draft'): ?>
    <span style="font-size:.88rem;color:var(--gray-500);font-style:italic;">
      <?php
        $statusLabels = [
          'submitted'          => '⏳ Awaiting Treasurer approval',
          'treasurer_approved' => '⏳ Awaiting VP signature',
          'vp_approved'        => '✅ Approved — awaiting payment',
          'rejected'           => '❌ Rejected',
        ];
        echo $statusLabels[$voucher['status']] ?? ucfirst($voucher['status']);
      ?>
    </span>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  </form>
</div>

<div id="receiptPreview">
  <img src="" alt="Receipt preview">
  <div id="hoverPdfMsg" style="display:none;width:200px;padding:1rem 1.25rem;text-align:center;">
    <div style="font-size:2.5rem;line-height:1;">📄</div>
    <div style="font-size:.82rem;font-weight:700;color:#374151;margin-top:.5rem;">PDF Receipt</div>
    <div style="font-size:.74rem;color:#9ca3af;margin-top:.25rem;">Click to change file</div>
  </div>
</div>

<script src="../js/qrcode.js?v=<?= @filemtime(__DIR__ . '/../js/qrcode.js') ?>"></script>
<script src="../js/qr-img.js?v=<?= @filemtime(__DIR__ . '/../js/qr-img.js') ?>"></script>
<script>
  const LP_CSRF = <?= json_encode(csrfToken()) ?>;

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
      <td class="receipt-cell" data-label="Receipt">
        <div id="receipt-wrap-${id}">
          <div class="receipt-btn-group">
            <button type="button" class="receipt-attach-btn" id="attach-btn-${id}"
              title="Attach file" onclick="triggerRowScan(${id})">📎</button>
            <button type="button" class="receipt-phone-btn" id="phone-btn-${id}"
              title="Upload from phone" onclick="phoneForRow(${id})">📱</button>
          </div>
          <div class="scan-spinner" id="spinner-${id}"></div>
          <input type="file" id="file-${id}" accept="image/*,.pdf" capture="environment" style="display:none"
            onchange="handleRowScan(this, ${id})">
        </div>
        <input type="hidden" name="exp_id[]"       value="${data.db_id || ''}">
        <input type="hidden" name="receipt_path[]" id="rpath-${id}" value="${escHtml(data.receipt_path || '')}">
        <input type="hidden" name="receipt_orig[]" id="rorig-${id}" value="${escHtml(data.receipt_filename || '')}">
      </td>
      <td data-label="Date"><input type="date" name="expense_date[]" class="cell-input" value="${data.expense_date || ''}" style="width:130px;"></td>
      <td data-label="Description"><input type="text" name="description[]" class="cell-input cell-desc" placeholder="Description" value="${escHtml(data.description || '')}"></td>
      <td data-label="Travel km"><input type="number" name="travel_km[]" id="km-${id}" class="cell-input num cell-km" placeholder="0" step="0.1" min="0" value="${data.travel_km > 0 ? data.travel_km : ''}" oninput="calcMileage(${id})"></td>
      <td data-label="Category">
        <select name="cat_sel[]" class="cell-select cell-cat" id="cat-${id}" onchange="catChanged(${id})">
          ${buildCatOptions(data)}
        </select>
        ${hiddenAmounts(id, data)}
      </td>
      <td data-label="Amount"><input type="number" class="cell-input num cell-dollar cell-amount"
           id="amt-${id}" placeholder="0.00" step="0.01" min="0" value="${rowAmount(data)}"
           oninput="amountChanged(${id})"></td>
      <td data-label="BCTF Grant"><select name="grant_id[]" class="cell-select" style="min-width:160px;">${buildGrantOptions(data.grant_id || '')}</select></td>
      <td data-label="Budget Line"><select name="budget_line_id[]" class="cell-select" style="min-width:180px;">${buildBLOptions(data.budget_line_id || '')}</select></td>
      <td><button type="button" class="btn-row-remove" onclick="removeRow(${id})">×</button></td>
    `;
    document.getElementById('expenseRows').appendChild(tr);
    // Rows that arrive split across categories open their breakdown, or the
    // figures would be real, posted and invisible.
    if (document.getElementById('cat-' + id) &&
        document.getElementById('cat-' + id).value === '__split') openSplit(id);

    if (data.receipt_path) {
        showThumb(id, data.receipt_path, null);
    }
    enableRowDrop(id);
    updateRow(id);
    updateTotals();
    return id;
}

function removeRow(id) {
    const row = document.getElementById('row-' + id);
    if (row) row.remove();
    closeSplit(id);        // its breakdown is a sibling row, not a child
    updateTotals();
}

function calcMileage(id) {
    const km = parseFloat(document.getElementById('km-' + id)?.value) || 0;
    const tamtEl = document.getElementById('tamt-' + id);
    if (tamtEl && km > 0) {
        tamtEl.value = (km * MILEAGE_RATE).toFixed(2);
        // Kilometres can only be travel, so the row says so rather than leaving
        // the category unset and the amount looking like it came from nowhere.
        const cat = document.getElementById('cat-' + id);
        const amt = document.getElementById('amt-' + id);
        if (cat && cat.value !== '__split') {
            const other = cat.value && cat.value !== 'travel_amt' &&
                          (parseFloat(amt && amt.value) || 0) > 0;
            if (other) {
                // Mileage plus something else is exactly what a split is for;
                // overwriting the category here would lose the other amount.
                cat.value = '__split';
                openSplit(id);
            } else {
                cat.value = 'travel_amt';
                if (amt) amt.value = tamtEl.value;
                cat.classList.remove('needs-category');
            }
        } else if (cat && cat.value === '__split') {
            const box = splitRowEl(id) &&
                        splitRowEl(id).querySelector('[data-cat="travel_amt"]');
            if (box) { box.value = tamtEl.value; splitChanged(id); }
        }
    }
    updateRow(id);
}
function getVal(el) { return parseFloat(el?.value) || 0; }
/* ── Category + amount ───────────────────────────────────────────────────────
 *
 * The voucher used to carry a column per category — Travel, Meals, Gifts, Misc,
 * Office, Phone — six boxes a row, five of them empty. They took 588px of a
 * table that only had about 1450px to live in, which is why Budget Line hung off
 * the right-hand edge at every screen size.
 *
 * One select and one amount box now stand in front of those six fields, which
 * are still posted under exactly the same names, so the database, the printed
 * voucher and the year-end reports are untouched. A row that genuinely splits
 * across categories — a trip with mileage and a meal — keeps all six, shown in a
 * breakdown under the row.
 */
const LP_CATS = [
    ['travel_amt', 'Travel'],
    ['meals',      'Meals'],
    ['gifts',      'Gifts'],
    ['misc',       'Misc'],
    ['office',     'Office'],
    ['phone',      'Phone']
];

/* The two pages name their row data differently — travel_amount here, travel_amt
 * there — so read whichever is present rather than keeping two copies of this. */
function catValue(data, key) {
    const alt = { travel_amt: 'travel_amount', meals: 'meals_amount', gifts: 'gifts_amount',
                  misc: 'misc_amount', office: 'office_amount', phone: 'phone_amount' }[key];
    const v = (data && data[key] !== undefined && data[key] !== '') ? data[key]
            : (data && alt && data[alt] !== undefined ? data[alt] : 0);
    return parseFloat(v) || 0;
}

function catsUsed(data) {
    return LP_CATS.map(c => c[0]).filter(k => catValue(data, k) > 0);
}

function rowAmount(data) {
    const used = catsUsed(data);
    if (used.length !== 1) return '';            // nothing yet, or a split row
    return catValue(data, used[0]).toFixed(2);
}

function buildCatOptions(data) {
    const used = catsUsed(data);
    const sel  = used.length === 1 ? used[0] : (used.length > 1 ? '__split' : '');
    let html = '<option value=""' + (sel === '' ? ' selected' : '') + '>Choose…</option>';
    LP_CATS.forEach(([k, label]) => {
        html += '<option value="' + k + '"' + (sel === k ? ' selected' : '') + '>' + label + '</option>';
    });
    html += '<option value="__split"' + (sel === '__split' ? ' selected' : '') + '>Split across categories…</option>';
    return html;
}

function hiddenAmounts(id, data) {
    return LP_CATS.map(([k]) => {
        const extra = k === 'travel_amt' ? ' id="tamt-' + id + '"' : '';
        const v = catValue(data, k);
        return '<input type="hidden" name="' + k + '[]"' + extra +
               ' class="hid-' + k + '" value="' + (v > 0 ? v.toFixed(2) : '') + '">';
    }).join('');
}

/*
 * Bring the Category select and Amount box back in line with the six hidden
 * fields.
 *
 * Needed because receipt scanning writes straight into those fields. Once they
 * stopped being visible columns, a scanned amount became money nobody could see:
 * it was in the form, it would have saved, and the first touch of the Category
 * select would have wiped it. Anything that writes a hidden amount calls this.
 */
function syncRowFromHidden(id) {
    const row = document.getElementById('row-' + id);
    const cat = document.getElementById('cat-' + id);
    const amt = document.getElementById('amt-' + id);
    if (!row || !cat || !amt) return;
    const filled = LP_CATS.map(([k]) => k).filter(k => {
        const h = hiddenFor(id, k);
        return h && (parseFloat(h.value) || 0) > 0;
    });
    if (filled.length > 1) {
        cat.value = '__split';
        openSplit(id);
    } else if (filled.length === 1) {
        closeSplit(id);
        cat.value = filled[0];
        amt.value = parseFloat(hiddenFor(id, filled[0]).value).toFixed(2);
        cat.classList.remove('needs-category');
    }
    updateRow(id);
}

function hiddenFor(id, key) {
    const row = document.getElementById('row-' + id);
    return row ? row.querySelector('.hid-' + key) : null;
}

/* The visible amount goes to the chosen category and nowhere else, so switching
 * category moves the money rather than leaving a copy behind. */
function amountChanged(id) {
    const cat = document.getElementById('cat-' + id);
    const amt = document.getElementById('amt-' + id);
    if (!cat || !amt) return;
    if (cat.value === '__split') return;         // the breakdown owns the figures
    LP_CATS.forEach(([k]) => { const h = hiddenFor(id, k); if (h) h.value = ''; });
    if (cat.value) {
        const h = hiddenFor(id, cat.value);
        if (h) h.value = amt.value;
    }
    cat.classList.toggle('needs-category', !cat.value && (parseFloat(amt.value) || 0) > 0);
    updateRow(id);
}

function catChanged(id) {
    const cat = document.getElementById('cat-' + id);
    if (!cat) return;
    if (cat.value === '__split') { openSplit(id); return; }

    // Leaving a split would drop every category but one, so it asks first and
    // puts the select back if the answer is no.
    if (splitRowEl(id)) {
        const filled = LP_CATS.map(([k]) => k).filter(k => {
            const h = hiddenFor(id, k);
            return h && (parseFloat(h.value) || 0) > 0;
        });
        if (filled.length > 1 &&
            !confirm('This row is split across ' + filled.length + ' categories. ' +
                     'Moving it to one category clears the others. Continue?')) {
            cat.value = '__split';
            return;
        }
        LP_CATS.forEach(([k]) => { const h = hiddenFor(id, k); if (h) h.value = ''; });
        const amt0 = document.getElementById('amt-' + id);
        if (amt0) amt0.value = '';
    }
    closeSplit(id);

    // Kilometres mean travel. The server fills travel_amt from travel_km
    // whenever the posted travel amount is zero, so a row left carrying km
    // under another category would save the mileage *as well* — the voucher
    // would total more than the screen ever showed.
    if (cat.value && cat.value !== 'travel_amt') {
        const kmEl = document.getElementById('km-' + id);
        if (kmEl && (parseFloat(kmEl.value) || 0) > 0) {
            kmEl.value = '';
            const h = hiddenFor(id, 'travel_amt');
            if (h) h.value = '';
        }
    }
    amountChanged(id);
}

/* ── Split rows ──────────────────────────────────────────────────────────── */

function splitRowEl(id) { return document.getElementById('split-' + id); }

function openSplit(id) {
    const row = document.getElementById('row-' + id);
    if (!row || splitRowEl(id)) return;
    const amtEl = document.getElementById('amt-' + id);
    if (amtEl) { amtEl.readOnly = true; amtEl.classList.add('is-split'); }

    const tr = document.createElement('tr');
    tr.id = 'split-' + id;
    tr.className = 'split-row';
    const td = document.createElement('td');
    td.colSpan = row.children.length;
    td.innerHTML = '<div class="split-wrap"><span class="split-lead">Split this row:</span>' +
        LP_CATS.map(([k, label]) =>
            '<label class="split-field">' + label +
            '<input type="number" step="0.01" min="0" class="split-input" data-cat="' + k + '"' +
            ' oninput="splitChanged(' + id + ')"></label>').join('') +
        '<button type="button" class="split-close" onclick="cancelSplit(' + id + ')">Use one category instead</button>' +
        '</div>';
    tr.appendChild(td);
    row.after(tr);

    // Seed the boxes from what the row already holds.
    tr.querySelectorAll('.split-input').forEach(inp => {
        const h = hiddenFor(id, inp.dataset.cat);
        if (h) inp.value = h.value;
    });
    splitChanged(id);
}

function splitChanged(id) {
    const tr = splitRowEl(id);
    if (!tr) return;
    let total = 0;
    tr.querySelectorAll('.split-input').forEach(inp => {
        const h = hiddenFor(id, inp.dataset.cat);
        if (h) h.value = inp.value;
        total += parseFloat(inp.value) || 0;
    });
    // Left alone when the breakdown is still empty, so an amount typed before a
    // category was chosen stays on screen instead of vanishing. A row in that
    // state is caught by rowsMissingCategory() rather than saved as nothing.
    const amtEl = document.getElementById('amt-' + id);
    if (amtEl && total) amtEl.value = total.toFixed(2);
    updateRow(id);
}

function closeSplit(id) {
    const tr = splitRowEl(id);
    if (tr) tr.remove();
    const amtEl = document.getElementById('amt-' + id);
    if (amtEl) { amtEl.readOnly = false; amtEl.classList.remove('is-split'); }
}

/* Collapsing a split back to one category would silently drop the other figures,
 * so it says what it is about to do first. */
function cancelSplit(id) {
    const filled = LP_CATS.map(([k]) => k).filter(k => {
        const h = hiddenFor(id, k);
        return h && (parseFloat(h.value) || 0) > 0;
    });
    if (filled.length > 1 &&
        !confirm('This row is split across ' + filled.length + ' categories. ' +
                 'Going back to one category clears the others. Continue?')) return;
    LP_CATS.forEach(([k]) => { const h = hiddenFor(id, k); if (h && k !== filled[0]) h.value = ''; });
    const cat = document.getElementById('cat-' + id);
    if (cat) cat.value = filled[0] || '';
    closeSplit(id);
    const amtEl = document.getElementById('amt-' + id);
    const keep  = filled[0] ? hiddenFor(id, filled[0]) : null;
    if (amtEl) amtEl.value = keep ? keep.value : '';
    amountChanged(id);
}

/* A row with money but no category would post six zeroes and be dropped on the
 * floor by the server loop, so it is caught here instead. */
function rowsMissingCategory() {
    return Array.from(document.querySelectorAll('#expenseRows tr[id^="row-"]')).filter(row => {
        const id  = row.id.replace('row-', '');
        const cat = document.getElementById('cat-' + id);
        const amt = document.getElementById('amt-' + id);
        if (!cat || !amt) return false;
        const shown = parseFloat(amt.value) || 0;
        if (shown <= 0) return false;
        if (!cat.value) return true;
        if (cat.value !== '__split') return false;
        // A split whose boxes add to nothing is an amount with nowhere to go.
        const allocated = LP_CATS.reduce((sum, [k]) => {
            const h = hiddenFor(id, k);
            return sum + (h ? (parseFloat(h.value) || 0) : 0);
        }, 0);
        return allocated <= 0;
    });
}

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
    document.getElementById('tot_km').textContent    = km    ? km.toFixed(1) + ' km' : '—';
    document.getElementById('tot_total').textContent = grand ? '$' + grand.toFixed(2) : '—';
    document.getElementById('grandTotal').textContent = '$' + grand.toFixed(2);

    // The per-category totals left the table with their columns, but they are
    // what the Treasurer reads off, so they appear under it — and only the
    // categories this voucher actually used, rather than six dashes.
    const strip = document.getElementById('catTotals');
    if (strip) {
        const parts = [['Travel', travel], ['Meals', meals], ['Gifts', gifts],
                       ['Misc', misc], ['Office', office], ['Phone', phone]]
            .filter(([, v]) => v > 0)
            .map(([label, v]) => '<span class="cat-total"><span class="cat-total-label">' +
                 label + '</span> $' + v.toFixed(2) + '</span>');
        strip.innerHTML = parts.length
            ? parts.join('') + '<span class="cat-total cat-total-grand">' +
              '<span class="cat-total-label">Total</span> $' + grand.toFixed(2) + '</span>'
            : '<span class="cat-total-empty">No amounts entered yet.</span>';
    }
}

function isPdfSrc(src) {
    return src === '__pdf__' || /\.pdf/i.test(src || '') || (src && src.startsWith('data:application/pdf'));
}

// ── Drag-and-drop ─────────────────────────────────────────────────────────────
function enableRowDrop(rowId) {
    const wrap = document.getElementById('receipt-wrap-' + rowId);
    if (!wrap) return;
    wrap.addEventListener('dragover', e => {
        e.preventDefault(); e.stopPropagation();
        wrap.classList.add('drag-over');
    });
    wrap.addEventListener('dragleave', e => {
        if (!wrap.contains(e.relatedTarget)) wrap.classList.remove('drag-over');
    });
    wrap.addEventListener('drop', e => {
        e.preventDefault(); e.stopPropagation();
        wrap.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (!file) return;
        showLocalPreview(rowId, file);
        uploadAndScan(file, rowId);
    });
}

(function initGlobalDrop() {
    const dz = document.getElementById('globalDropZone');
    let counter = 0;

    document.addEventListener('dragenter', e => {
        if (![...e.dataTransfer.types].includes('Files')) return;
        counter++;
        dz.classList.add('drag-active');
    });
    document.addEventListener('dragleave', () => {
        if (--counter <= 0) { counter = 0; dz.classList.remove('drag-active'); }
    });
    document.addEventListener('dragover', e => e.preventDefault());

    dz.addEventListener('dragover', e => {
        e.preventDefault(); e.stopPropagation();
        dz.classList.add('drag-over');
    });
    dz.addEventListener('dragleave', e => {
        if (!dz.contains(e.relatedTarget)) dz.classList.remove('drag-over');
    });
    dz.addEventListener('drop', e => {
        e.preventDefault(); e.stopPropagation();
        counter = 0;
        dz.classList.remove('drag-active', 'drag-over');

        const files = [...e.dataTransfer.files].filter(
            f => f.type.startsWith('image/') || f.type === 'application/pdf'
        );
        if (!files.length) return;

        files.forEach(file => {
            let rowId = null;
            for (const hidden of document.querySelectorAll('[name="receipt_path[]"]')) {
                if (!hidden.value) { rowId = hidden.id.replace('rpath-', ''); break; }
            }
            if (!rowId) rowId = addRow();
            showLocalPreview(rowId, file);
            uploadAndScan(file, rowId);
        });
    });
})();

function triggerRowScan(rowId) { document.getElementById('file-' + rowId).click(); }
function handleRowScan(input, rowId) {
    if (!input.files[0]) return;
    // Instant local preview
    if (input.files[0].type === 'application/pdf') {
        showThumb(rowId, null, '__pdf__');
    } else {
        const reader = new FileReader();
        reader.onload = e => showThumb(rowId, null, e.target.result);
        reader.readAsDataURL(input.files[0]);
    }
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
            syncRowFromHidden(rowId);
        })
        .catch(() => { if (spinner) spinner.style.display = 'none'; });
}

function showThumb(rowId, savedPath, dataUrl) {
    const wrap = document.getElementById('receipt-wrap-' + rowId);
    if (!wrap) return;
    const btn = document.getElementById('attach-btn-' + rowId);
    if (btn) btn.remove();
    wrap.querySelector('.receipt-has-file')?.remove();
    wrap.querySelector('.receipt-open-btn')?.remove();
    const pdf = isPdfSrc(dataUrl) || isPdfSrc(savedPath);
    const src = (dataUrl && dataUrl !== '__pdf__') ? dataUrl : ('lp-receipt.php?f=' + encodeURIComponent(savedPath || ''));
    const hoverSrc = pdf ? '__pdf__' : src;
    const indicator = document.createElement('div');
    indicator.className = 'receipt-has-file';
    indicator.title = 'Click to change file';
    indicator.innerHTML = '📄';
    indicator.dataset.src = src;
    indicator.onclick = () => triggerRowScan(rowId);
    indicator.addEventListener('mouseenter', e => showHoverPreview(e, hoverSrc));
    indicator.addEventListener('mouseleave', hideHoverPreview);
    indicator.addEventListener('mousemove',  moveHoverPreview);
    wrap.insertBefore(indicator, wrap.querySelector('.scan-spinner'));
    if (savedPath) {
        const openBtn = document.createElement('a');
        openBtn.href = 'lp-receipt.php?f=' + encodeURIComponent(savedPath);
        openBtn.target = '_blank';
        openBtn.title = 'Open receipt in new tab';
        openBtn.className = 'receipt-open-btn';
        openBtn.textContent = '↗ open';
        openBtn.addEventListener('click', e => e.stopPropagation());
        wrap.insertBefore(openBtn, wrap.querySelector('.scan-spinner'));
    }
}

const hoverPreview = document.getElementById('receiptPreview');
function showHoverPreview(e, src) {
    const pdf = isPdfSrc(src);
    const img    = hoverPreview.querySelector('img');
    const pdfMsg = document.getElementById('hoverPdfMsg');
    if (pdf) {
        img.style.display = 'none'; img.src = '';
        if (pdfMsg) pdfMsg.style.display = 'block';
    } else {
        if (pdfMsg) pdfMsg.style.display = 'none';
        img.style.display = 'block'; img.src = src;
    }
    hoverPreview.style.display = 'block';
    positionPreview(e);
}
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

// Fill row fields from scan data — only overwrites empty fields
function fillRowFromScan(rowId, sd) {
    if (!sd) return;
    var tr = document.getElementById('row-' + rowId);
    if (!tr) return;
    var dateEl = tr.querySelector('[name="expense_date[]"]');
    if (dateEl && !dateEl.value && sd.date) dateEl.value = sd.date;
    var descEl = tr.querySelector('[name="description[]"]');
    if (descEl && !descEl.value && sd.description) descEl.value = sd.description;
    var amts = {'travel_amt[]': sd.travel_amount, 'meals[]': sd.meals_amount,
                'gifts[]': sd.gifts_amount, 'misc[]': sd.misc_amount,
                'office[]': sd.office_amount, 'phone[]': sd.phone_amount};
    Object.keys(amts).forEach(function(name) {
        var v = parseFloat(amts[name]);
        if (v > 0) {
            var el = tr.querySelector('[name="' + name + '"]');
            if (el && !parseFloat(el.value)) el.value = v.toFixed(2);
        }
    });
    if (sd.suggested_grant_id) {
        var gEl = tr.querySelector('[name="grant_id[]"]');
        if (gEl && !gEl.value) gEl.value = sd.suggested_grant_id;
    }
    if (sd.suggested_bl_id) {
        var bEl = tr.querySelector('[name="budget_line_id[]"]');
        if (bEl && !bEl.value) bEl.value = sd.suggested_bl_id;
    }
    syncRowFromHidden(rowId);
}

// Load existing expenses, then add blank rows up to 10 minimum
EXISTING.forEach(e => addRow(e));
const minRows = Math.max(0, 10 - EXISTING.length);
for (let i = 0; i < minRows; i++) addRow();

// ── QR code panel ─────────────────────────────────────────────────────────────
const VOUCHER_ID  = <?= $id ?>;
const MOBILE_URL  = <?= json_encode($mobileUrl) ?>;
let qrGenerated   = false;
let qrPanelOpen   = false;
let targetRowId   = null;

function toggleQR() {
    const panel = document.getElementById('qrPanel');
    qrPanelOpen = !qrPanelOpen;
    panel.classList.toggle('open', qrPanelOpen);
    if (qrPanelOpen && !qrGenerated) {
        generateQR();
    }
}

function phoneForRow(rowId) {
    // Open QR panel if not already open
    if (!qrPanelOpen) {
        qrPanelOpen = true;
        const panel = document.getElementById('qrPanel');
        panel.classList.add('open');
        if (!qrGenerated) generateQR();
    }
    // Set target row
    targetRowId = rowId;
    const tr      = document.getElementById('row-' + rowId);
    const descEl  = tr ? tr.querySelector('[name="description[]"]') : null;
    const label   = (descEl && descEl.value.trim()) ? descEl.value.trim() : 'Row ' + rowId;
    document.getElementById('qrTargetLabel').textContent = label;
    document.getElementById('qrTarget').style.display   = 'flex';
    // Highlight active button, clear others
    document.querySelectorAll('.receipt-phone-btn').forEach(function(b) { b.classList.remove('targeting'); });
    const btn = document.getElementById('phone-btn-' + rowId);
    if (btn) btn.classList.add('targeting');
    // Scroll QR panel into view
    document.getElementById('qrPanel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function clearPhoneTarget() {
    targetRowId = null;
    document.getElementById('qrTarget').style.display = 'none';
    document.querySelectorAll('.receipt-phone-btn').forEach(function(b) { b.classList.remove('targeting'); });
}

function generateQR() {
    var img = document.getElementById('qrImg');
    if (bvtuQrInto(img, MOBILE_URL, 180)) {
        qrGenerated = true;
    } else {
        img.style.display = 'none';   // the link is printed below regardless
    }
}


// ── Pending receipts polling ──────────────────────────────────────────────────
const seenReceiptIds = {};
const receiptStore   = {}; // keyed by pending receipt id

function pollPending() {
    fetch('lp-poll-receipts.php?voucher_id=' + VOUCHER_ID)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.receipts || d.receipts.length === 0) return;
            var newOnes = d.receipts.filter(function(r) { return !seenReceiptIds[r.id]; });
            if (newOnes.length === 0) return;
            var tray = document.getElementById('pendingTray');
            tray.classList.add('has-items');
            document.getElementById('pendingBadge').textContent = d.receipts.length;
            newOnes.forEach(function(receipt) {
                seenReceiptIds[receipt.id] = true;
                receiptStore[receipt.id]   = receipt;
                addPendingCard(receipt);
            });
        })
        .catch(function() {});
}

function addPendingCard(receipt) {
    receiptStore[receipt.id] = receipt;

    // If a row is targeted, auto-attach directly — no tray card needed
    if (targetRowId) {
        var tid    = targetRowId;
        clearPhoneTarget();
        var rpathEl = document.getElementById('rpath-' + tid);
        var rorigEl = document.getElementById('rorig-' + tid);
        if (rpathEl) rpathEl.value = receipt.saved_path;
        if (rorigEl) rorigEl.value = receipt.original_name || '';
        showThumb(tid, receipt.saved_path, null);
        fillRowFromScan(tid, receipt.scan_data || {});
        var fd = new FormData();
        fd.append('pending_id', receipt.id);
        fd.append('csrf_token', LP_CSRF);
        fetch('lp-claim-receipt.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                // Say so rather than leaving the tray looking cleared when the
                // server refused — the receipt is still there on reload.
                if (!d || !d.ok) {
                    // Deliberately NOT re-offered: the receipt is already in a
                    // row, so putting the card back invites a second row against
                    // the same file. Say what happened and let them reload.
                    alert((d && d.error) ? d.error
                        : 'That receipt was attached here but could not be marked as filed. '
                          + 'Reload before adding more so it is not attached twice.');
                }
            })
            .catch(function () {
                alert('That receipt could not be filed — the server did not respond. '
                      + 'Reload before adding more.');
            });
        var tr = document.getElementById('row-' + tid);
        if (tr) {
            tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            tr.classList.add('row-flash');
            setTimeout(function() { tr.classList.remove('row-flash'); }, 1600);
        }
        var sd2 = receipt.scan_data || {};
        var label = (sd2.description) ? sd2.description : 'receipt';
        showToast('✅ ' + label + ' attached to row');
        return;
    }

    // No target — show in pending tray
    var sd      = receipt.scan_data || {};
    var desc    = sd.description || receipt.original_name || 'Receipt';
    var isPdf   = /\.pdf$/i.test(receipt.saved_path || '');
    var thumbHtml = isPdf
        ? '<div class="pdf-thumb">📄</div>'
        : '<img src="' + escHtml(receipt.preview_url) + '" alt="Receipt">';
    var amount = null;
    ['travel_amount','meals_amount','gifts_amount','misc_amount','office_amount','phone_amount','total_amount'].forEach(function(k) {
        if (!amount && sd[k] && parseFloat(sd[k]) > 0) amount = parseFloat(sd[k]);
    });

    var html = '<div class="pending-card" id="pc-' + receipt.id + '">'
        + thumbHtml
        + '<div class="p-desc" title="' + escHtml(desc) + '">' + escHtml(desc) + '</div>'
        + (amount ? '<div class="p-amt">$' + amount.toFixed(2) + '</div>' : '')
        + (sd.concerns ? '<div class="p-flag">⚠️ ' + escHtml(sd.concerns) + '</div>' : '')
        + '<button class="btn-claim" onclick="claimReceipt(' + receipt.id + ')">+ New Row</button>'
        + '<select class="attach-select" id="as-' + receipt.id + '"'
        +   ' onclick="rebuildAttachOptions(' + receipt.id + ')"'
        +   ' onchange="attachToRow(' + receipt.id + ', this)">'
        + '<option value="">📎 Attach to existing row…</option>'
        + '</select>'
        + '</div>';

    document.getElementById('pendingCards').insertAdjacentHTML('beforeend', html);

    // Scroll the tray into view and toast
    var tray = document.getElementById('pendingTray');
    tray.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    showToast('📱 Receipt arrived — tap "+ New Row" or attach to a row');
}

// Populate the "Attach to row" dropdown with rows that have no receipt yet
function rebuildAttachOptions(pendingId) {
    var sel = document.getElementById('as-' + pendingId);
    if (!sel) return;
    while (sel.options.length > 1) sel.remove(1);
    var found = 0;
    document.querySelectorAll('#expenseRows tr').forEach(function(tr) {
        var rowId  = tr.id ? tr.id.replace('row-', '') : '';
        if (!rowId) return;
        var rpath  = document.getElementById('rpath-' + rowId);
        if (!rpath || rpath.value.trim()) return; // already has receipt
        var descEl = tr.querySelector('[name="description[]"]');
        var dateEl = tr.querySelector('[name="expense_date[]"]');
        var desc   = descEl ? descEl.value.trim() : '';
        var date   = dateEl ? dateEl.value : '';
        if (!desc && !date) return; // skip blank rows
        var label  = (date ? date + ' — ' : '') + (desc || '(no description)');
        var opt    = document.createElement('option');
        opt.value  = rowId;
        opt.textContent = label;
        sel.appendChild(opt);
        found++;
    });
    if (!found) {
        var opt = document.createElement('option');
        opt.disabled = true;
        opt.textContent = 'No rows without a receipt';
        sel.appendChild(opt);
    }
}

// Snap a pending receipt onto an existing row (persisted when form is saved)
function attachToRow(pendingId, selectEl) {
    var rowId = selectEl.value;
    if (!rowId) return;
    selectEl.value = '';

    var receipt = receiptStore[pendingId];
    if (!receipt) return;

    var rpathEl = document.getElementById('rpath-' + rowId);
    var rorigEl = document.getElementById('rorig-' + rowId);
    if (rpathEl) rpathEl.value = receipt.saved_path;
    if (rorigEl) rorigEl.value = receipt.original_name || '';

    showThumb(rowId, receipt.saved_path, null);
    fillRowFromScan(rowId, receipt.scan_data || {});

    var tr = document.getElementById('row-' + rowId);
    if (tr) {
        tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        tr.classList.add('row-flash');
        setTimeout(function() { tr.classList.remove('row-flash'); }, 1600);
    }
    var sd2 = (receipt.scan_data || {});
    showToast('✅ ' + (sd2.description || 'Receipt') + ' attached to row');

    dismissPendingCard(pendingId);
}

// Add a brand-new row pre-filled with scan data
function claimReceipt(pendingId) {
    var receipt = receiptStore[pendingId];
    if (!receipt) return;
    var sd = receipt.scan_data || {};

    var rowId = addRow({
        date:               sd.date || '',
        description:        sd.description || '',
        travel_amount:      sd.travel_amount  || '',
        meals_amount:       sd.meals_amount   || '',
        gifts_amount:       sd.gifts_amount   || '',
        misc_amount:        sd.misc_amount    || '',
        office_amount:      sd.office_amount  || '',
        phone_amount:       sd.phone_amount   || '',
        suggested_grant_id: sd.suggested_grant_id || '',
        suggested_bl_id:    sd.suggested_bl_id    || '',
        saved_path:         receipt.saved_path,
        original_name:      receipt.original_name,
        concerns:           sd.concerns || '',
        flag:               sd.flag || '',
    });
    var newRow = document.getElementById('row-' + rowId);
    if (newRow) newRow.scrollIntoView({ behavior: 'smooth', block: 'center' });

    dismissPendingCard(pendingId);
}

function dismissPendingCard(pendingId) {
    var fd = new FormData();
    fd.append('pending_id', pendingId);
    fd.append('csrf_token', LP_CSRF);
    fetch('lp-claim-receipt.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                // Say so rather than leaving the tray looking cleared when the
                // server refused — the receipt is still there on reload.
                if (!d || !d.ok) {
                    // Not re-offered. Both callers of this have already attached
                    // the receipt to a row, so putting the card back would let a
                    // second click create a duplicate line for the same file.
                    alert((d && d.error) ? d.error
                        : 'That receipt was attached but could not be marked as filed. '
                          + 'Reload before adding more so it is not attached twice.');
                }
            })
            .catch(function () {
                alert('That receipt could not be filed — the server did not respond. '
                      + 'Reload before adding more.');
            });

    var card = document.getElementById('pc-' + pendingId);
    if (card) card.remove();
    var remaining = document.getElementById('pendingCards').children.length;
    if (remaining === 0) {
        document.getElementById('pendingTray').classList.remove('has-items');
    } else {
        document.getElementById('pendingBadge').textContent = remaining;
    }
}

// Poll every 5 seconds
pollPending();
setInterval(pollPending, 5000);

// ── Toast notification ────────────────────────────────────────────────────────
var toastTimer = null;
function showToast(msg) {
    var t = document.getElementById('receiptToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function() { t.classList.remove('show'); }, 4000);
}

    /* A row with an amount and no category would post six zeroes, and the server
     * loop skips rows that total nothing — the money would vanish on save. */
    document.getElementById('voucherForm').addEventListener('submit', function (ev) {
      const bad = rowsMissingCategory();
      if (!bad.length) return;
      ev.preventDefault();
      bad.forEach(row => {
        const cat = document.getElementById('cat-' + row.id.replace('row-', ''));
        if (cat) cat.classList.add('needs-category');
      });
      bad[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      alert(bad.length === 1
        ? 'One row has an amount but no category. Pick a category so the money lands somewhere.'
        : bad.length + ' rows have an amount but no category. Pick a category for each so the money lands somewhere.');
    });
</script>
<div id="receiptToast"></div>
</body>
</html>
