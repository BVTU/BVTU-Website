<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/lp-share.php';
require_once __DIR__ . '/lp-checks.php';
requireLogin();

$member = getMember();
lpEnsureTables();
lpEnsureApprovalColumns();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: lp-dashboard.php'); exit; }

$voucher  = lpGetVoucher($id);
if (!$voucher) { header('Location: lp-dashboard.php'); exit; }

$isOwner = $voucher['submitted_by_email'] === $member['email'];
if (!$isOwner && !lpCanView($member['email'])) { header('Location: lp-dashboard.php'); exit; }

$expenses = lpGetExpenses($id);
$notice   = null;

// This page's own handlers (submit, delete, resend) act on POST. They now carry
// tokens, so check them here — a token that is emitted and never verified is
// worse than none, because it looks like the page is protected.
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfCheck();

// ── Submit to treasurer ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if ($voucher['status'] === 'draft' && ($isOwner || prodIsExec($member['email']))) {
        getDB()->prepare("UPDATE lp_vouchers SET status='submitted', submitted_at=NOW() WHERE id=?")
               ->execute([$id]);
        $voucher = lpGetVoucher($id); // reload

        // Send email to treasurer(s)
        $treasurerEmails = lpGetTreasurerEmails();
        $vNum    = $voucher['voucher_number'] ? '#'.$voucher['voucher_number'].' — ' : '';
        $subject = "LP Expense Voucher submitted for review: {$vNum}{$voucher['name']}";
        $viewUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/members/lp-voucher-view.php?id=' . $id;

        // Expense summary for email
        $expCount = count($expenses);
        $total    = array_sum(array_map('lpRowTotal', $expenses));
        $body  = "A new expense voucher has been submitted for your review.\n\n";
        $body .= "Submitted by: {$voucher['submitted_by']}\n";
        if (lpIsVendorPayment($voucher)) {
            $_pt = lpPayTo($voucher);
            $body .= "Pay to: {$_pt['name']}" . ($_pt['email'] !== '' ? " <{$_pt['email']}>" : '') . "\n";
        }
        $body .= "Voucher: {$vNum}{$voucher['name']}\n";
        $body .= "Expenses: {$expCount} line items\n";
        $body .= "Total: $" . number_format($total, 2) . "\n";
        if ($voucher['notes']) $body .= "Notes: {$voucher['notes']}\n";
        $body .= "\nView and approve online:\n{$viewUrl}\n\n";
        $body .= "— BVTU Members Portal";

        require_once __DIR__ . '/smtp.php';
        foreach ($treasurerEmails as $to) {
            siteMail($to, $subject, $body);
        }

        $notice = $treasurerEmails
            ? 'Voucher submitted — the treasurer has been notified by email.'
            : 'Voucher submitted, but no Treasurer is assigned so no email was sent. '
            . 'Assign a Treasurer in the Roles &amp; Directory, then use “Resend to Treasurer”.';
    }
}

// ── Delete voucher ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $isAdmin     = execIsAdmin($member['email']);
    $canDelete   = ($voucher['status'] === 'draft' && $isOwner)
                || ($isAdmin && $voucher['status'] !== 'paid');
    if ($canDelete) {
        lpDeleteVoucher($id);
        header('Location: lp-dashboard.php?deleted=1');
        exit;
    }
}

// ── Resend to treasurer ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_to_treasurer') {
    $isAdmin = execIsAdmin($member['email']);
    if ($isAdmin && $voucher['status'] === 'submitted') {
        $treasurerEmails = lpGetTreasurerEmails();
        $vNum    = $voucher['voucher_number'] ? '#'.$voucher['voucher_number'].' — ' : '';
        $subject = "LP Expense Voucher submitted for review: {$vNum}{$voucher['name']}";
        $viewUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/members/lp-voucher-view.php?id=' . $id;
        $expCount = count($expenses);
        $total    = array_sum(array_map('lpRowTotal', $expenses));
        $body  = "A new expense voucher has been submitted for your review.\n\n";
        $body .= "Submitted by: {$voucher['submitted_by']}\n";
        if (lpIsVendorPayment($voucher)) {
            $_pt = lpPayTo($voucher);
            $body .= "Pay to: {$_pt['name']}" . ($_pt['email'] !== '' ? " <{$_pt['email']}>" : '') . "\n";
        }
        $body .= "Voucher: {$vNum}{$voucher['name']}\n";
        $body .= "Expenses: {$expCount} line items\n";
        $body .= "Total: $" . number_format($total, 2) . "\n";
        if ($voucher['notes']) $body .= "Notes: {$voucher['notes']}\n";
        $body .= "\nView and approve online:\n{$viewUrl}\n\n";
        $body .= "— BVTU Members Portal";
        require_once __DIR__ . '/smtp.php';
        foreach ($treasurerEmails as $to) {
            siteMail($to, $subject, $body);
        }
        $notice = $treasurerEmails
            ? 'Treasurer notification re-sent to ' . implode(', ', $treasurerEmails) . '.'
            : 'No Treasurer is assigned — nothing was sent. Assign one in the Roles &amp; Directory first.';
    }
}

// ── Share for review ──────────────────────────────────────────────────────────
// Only the people who can already see this voucher may hand it to someone else.
$mayShare  = $isOwner || lpCanView($member['email']) || lpCanReview($member['email']);
$newShare  = null;   // just created, so the dialog can open on it

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'share-create' && $mayShare) {
    $who   = trim($_POST['shared_with'] ?? '');
    $token = lpShareCreate($id, $who, $member['email']);
    $newShare = $token;

    // Sending it from here saves copying a long URL into a mail client by hand,
    // which is where links get truncated.
    if ($who !== '' && filter_var($who, FILTER_VALIDATE_EMAIL) && ($_POST['send'] ?? '') === '1') {
        require_once __DIR__ . '/smtp.php';
        $url  = lpShareUrl($token);
        $body = $member['name'] . " has shared an expense voucher with you for review.\n\n"
              . "Voucher: " . $voucher['name'] . "\n\n"
              . "Open it here — no account or password needed:\n" . $url . "\n\n"
              . "The page is read-only. You can read every line and its receipt, and save\n"
              . "a PDF copy from the button at the top. The link stops working in "
              . LP_SHARE_DAYS . " days.\n\n"
              . "— Bulkley Valley Teachers' Union";
        siteMail($who, 'For your review: ' . $voucher['name'], $body);
        $notice = 'Link created and emailed to ' . $who . '.';
    } elseif (($_POST['send'] ?? '') === '1' && $who !== '') {
        // Asked for it to be sent, but the address could not be used.
        $notice = 'Link created, but "' . $who . '" is not an email address we could '
                . 'send to — copy the link from the Share box instead.';
    } else {
        $notice = 'Link created. Copy it from the Share box below.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'share-revoke' && $mayShare) {
    lpShareRevoke((int)($_POST['share_id'] ?? 0), $id);
    $notice = 'Access removed. That link no longer opens.';
}

$shares = $mayShare ? lpSharesFor($id) : [];

// A last look before it goes for signature. Advice, never a gate — see
// lp-checks.php for why.
$review = lpReviewVoucher($voucher, $expenses);

// Export as CSV
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="voucher-' . ($voucher['voucher_number'] ?: $id) . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['BULKLEY VALLEY TEACHERS\' UNION — Expense Voucher']);
    fputcsv($out, ['Voucher:', $voucher['voucher_number'] ?: '—', 'Name:', $voucher['name']]);
    fputcsv($out, ['Submitted by:', $voucher['submitted_by'], 'Date:', date('Y-m-d', strtotime($voucher['created_at']))]);
    fputcsv($out, []);
    fputcsv($out, ['Date','Description','Travel km','Travel $','Meals $','Gifts $','Misc $','Office $','Phone $','Row Total','BCTF Grant','Budget Line']);
    $totals = array_fill_keys(['km','travel','meals','gifts','misc','office','phone','total'], 0);
    foreach ($expenses as $e) {
        $total = lpRowTotal($e);
        fputcsv($out, [
            $e['expense_date'],
            $e['description'],
            $e['travel_km'] ?: '',
            $e['travel_amt'] ? number_format($e['travel_amt'],2) : '',
            $e['meals']      ? number_format($e['meals'],2)      : '',
            $e['gifts']      ? number_format($e['gifts'],2)      : '',
            $e['misc']       ? number_format($e['misc'],2)       : '',
            $e['office']     ? number_format($e['office'],2)     : '',
            $e['phone']      ? number_format($e['phone'],2)      : '',
            number_format($total, 2),
            $e['grant_name'] ?? '',
            $e['budget_line_name'] ?? '',
        ]);
        $totals['km']     += (float)$e['travel_km'];
        $totals['travel'] += (float)$e['travel_amt'];
        $totals['meals']  += (float)$e['meals'];
        $totals['gifts']  += (float)$e['gifts'];
        $totals['misc']   += (float)$e['misc'];
        $totals['office'] += (float)$e['office'];
        $totals['phone']  += (float)$e['phone'];
        $totals['total']  += $total;
    }
    fputcsv($out, []);
    fputcsv($out, ['TOTALS','',
        number_format($totals['km'],1),
        number_format($totals['travel'],2),
        number_format($totals['meals'],2),
        number_format($totals['gifts'],2),
        number_format($totals['misc'],2),
        number_format($totals['office'],2),
        number_format($totals['phone'],2),
        number_format($totals['total'],2),'','']);
    fclose($out);
    exit;
}

// Compute totals
$totals = array_fill_keys(['km','travel','meals','gifts','misc','office','phone','total'], 0);
$grantSummary = [];
$blSummary    = [];
foreach ($expenses as $e) {
    $rowTotal = lpRowTotal($e);
    $totals['km']     += (float)$e['travel_km'];
    $totals['travel'] += (float)$e['travel_amt'];
    $totals['meals']  += (float)$e['meals'];
    $totals['gifts']  += (float)$e['gifts'];
    $totals['misc']   += (float)$e['misc'];
    $totals['office'] += (float)$e['office'];
    $totals['phone']  += (float)$e['phone'];
    $totals['total']  += $rowTotal;
    if ($e['grant_id']) {
        $k = $e['grant_name'] ?? 'Unknown';
        $grantSummary[$k] = ($grantSummary[$k] ?? 0) + $rowTotal;
    }
    if ($e['budget_line_id']) {
        $k = $e['budget_line_name'] ?? 'Unknown';
        $blSummary[$k] = ($blSummary[$k] ?? 0) + $rowTotal;
    }
}
arsort($grantSummary);
arsort($blSummary);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Voucher <?= htmlspecialchars($voucher['voucher_number'] ?: '#'.$id) ?> — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    .approval-card { background:#fff; border:1px solid var(--gray-200); border-radius:12px;
                     padding:1rem 1.25rem; margin-bottom:1.25rem; max-width:520px; }
    .approval-head { font-size:.72rem; font-weight:800; text-transform:uppercase;
                     letter-spacing:.06em; color:var(--gray-400); margin-bottom:.5rem; }
    body { background: #f4f6f8; }
    .wrap { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0 0 .2rem; }
    .portal-header .sub { font-size: .82rem; color: var(--gray-500); }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .action-bar { display: flex; gap: .6rem; flex-wrap: wrap; }

    /* Summary cards */
    .summary-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px,1fr)); gap: .85rem; margin-bottom: 1.5rem; }
    .sum-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: .9rem 1rem; }
    .sum-card .lbl { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-400); margin-bottom: .25rem; }
    .sum-card .val { font-size: 1.15rem; font-weight: 800; color: var(--primary); }
    .sum-card.total { background: var(--primary); border-color: var(--primary); }
    .sum-card.total .lbl { color: rgba(255,255,255,.7); }
    .sum-card.total .val { color: #fff; font-size: 1.5rem; }

    /* Expense table */
    /* No overflow here on wide screens: a scroll container would become the
       scrollport and break the sticky header below. 900px table fits inside the
       1200px wrap, so horizontal scroll is only needed on narrower viewports. */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; margin-bottom: 1.5rem; }
    @media (max-width: 980px) { .table-wrap { overflow-x: auto; } }
    /* Sortable Date heading */
    th.sortable { cursor: pointer; user-select: none; }
    th.sortable:hover { text-decoration: underline; }
    th.sortable:focus-visible { outline: 2px solid #fff; outline-offset: -2px; }
    .sort-caret { display: inline-block; width: 0; height: 0; margin-left: .35rem;
      vertical-align: middle; border-left: 4px solid transparent;
      border-right: 4px solid transparent; border-top: 5px solid currentColor; opacity: .35; }
    th.sort-asc  .sort-caret { border-top: none; border-bottom: 5px solid currentColor; opacity: 1; }
    th.sort-desc .sort-caret { opacity: 1; }

    table.etable { width: 100%; border-collapse: collapse; min-width: 900px; font-size: .83rem; }
    .etable thead th { position: sticky; top: 0; z-index: 3; background: #1a2e1a; color: #fff; padding: .6rem .75rem; text-align: left; font-size: .71rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,.18); }
    .etable thead th:first-child { border-top-left-radius: 12px; }
    .etable thead th:last-child  { border-top-right-radius: 12px; }
    .etable thead th.r { text-align: right; }
    .etable tbody tr { border-bottom: 1px solid var(--gray-100); }
    .etable tbody tr:last-child { border-bottom: none; }
    .etable tbody tr:hover { background: #fafafa; }
    .etable td { padding: .55rem .75rem; vertical-align: middle; }
    .etable td.r { text-align: right; color: var(--gray-700); }
    .etable td.bold { font-weight: 700; color: var(--primary); }
    .etable tfoot td { background: #f0fdf4; font-weight: 700; padding: .65rem .75rem; }
    .etable tfoot td.r { text-align: right; color: var(--primary); }

    .receipt-btn { display: inline-flex; align-items: center; gap: .25rem; background: var(--accent); border: 1px solid #b8ddc5; color: var(--primary); font-size: .72rem; font-weight: 700; border-radius: 5px; padding: .2rem .5rem; text-decoration: none; cursor: pointer; }
    .receipt-btn:hover { background: #b8ddc5; }
    .tag { display: inline-block; font-size: .7rem; font-weight: 600; border-radius: 4px; padding: .15rem .45rem; }
    .tag-grant { background: #eff6ff; color: #1e40af; }
    .tag-bl    { background: #f5f3ff; color: #6d28d9; }

    /* Breakdown panels */
    .breakdown-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
    @media (max-width: 640px) { .breakdown-grid { grid-template-columns: 1fr; } }
    .breakdown-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.25rem; }
    .breakdown-card h3 { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--primary); margin: 0 0 .85rem; }
    .breakdown-row { display: flex; justify-content: space-between; align-items: center; padding: .35rem 0; border-bottom: 1px solid var(--gray-100); font-size: .83rem; }
    .breakdown-row:last-child { border-bottom: none; }
    .breakdown-row .name { color: var(--gray-700); }
    .breakdown-row .amt  { font-weight: 700; color: var(--primary); }

    /* Print */
    @media print {
      body { background: #fff; font-size: 10pt; }
      .wrap { padding: 0; max-width: 100%; }
      .no-print { display: none !important; }
      .table-wrap, .breakdown-card { border: 1px solid #ccc; box-shadow: none; }
      .etable thead th { position: static; box-shadow: none; background: #1a2e1a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }

    /* Lightbox */
    .lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 9999; align-items: center; justify-content: center; }
    .lightbox.open { display: flex; }
    .lightbox img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }
    .lightbox iframe { width: 88vw; height: 90vh; border: none; border-radius: 8px; background: #fff; }
    .lightbox-close { position: absolute; top: 1rem; right: 1.5rem; color: #fff; font-size: 2rem; cursor: pointer; line-height: 1; }
    .lightbox-opennew { position: absolute; top: 1rem; right: 4rem; color: rgba(255,255,255,.7); font-size: .85rem; text-decoration: none; }
    .lightbox-opennew:hover { color: #fff; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="portal-header">
    <div>
      <h1>
        <?php if ($voucher['voucher_number']): ?>Voucher #<?= htmlspecialchars($voucher['voucher_number']) ?> — <?php endif; ?>
        <?= htmlspecialchars($voucher['name']) ?>
      </h1>
      <div class="sub">
        Submitted by <?= htmlspecialchars($voucher['submitted_by']) ?>
        <?php // Said plainly on the voucher itself: whoever reads it later needs
              // to know the submitter was authorising a spend, not claiming. ?>
        <?php if (lpIsVendorPayment($voucher)): $pt = lpPayTo($voucher); ?>
        · <strong>Pay to <?= htmlspecialchars($pt['name']) ?></strong>
        <?php if ($pt['email'] !== ''): ?>(<?= htmlspecialchars($pt['email']) ?>)<?php endif; ?>
        <?php endif; ?>
        · <?= date('F j, Y', strtotime($voucher['created_at'])) ?>
        <?php if ($voucher['notes']): ?>· <?= htmlspecialchars($voucher['notes']) ?><?php endif; ?>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;gap:.5rem;align-items:flex-end;">
      <div class="action-bar no-print">
        <?php if ($isOwner || prodIsExec($member['email'])): ?>
          <?php if ($voucher['status'] === 'draft'): ?>
          <a href="lp-voucher-edit.php?id=<?= $id ?>" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;">✏ Edit</a>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Submit this voucher to the treasurer for review? You can still edit it after.')">
        <?= csrfField() ?>
            <input type="hidden" name="action" value="submit">
            <button type="submit" class="btn btn-primary" style="padding:.45rem .85rem;font-size:.83rem;background:#166534;">📤 Submit to Treasurer</button>
          </form>
          <?php if ($isOwner): ?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this draft voucher and all its expenses? This cannot be undone.')">
        <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;color:#dc2626;border-color:#dc2626;">🗑 Delete</button>
          </form>
          <?php endif; ?>
          <?php else: ?>
          <span style="padding:.45rem .5rem;"><?= lpStatusBadge($voucher['status']) ?></span>
          <a href="lp-voucher-edit.php?id=<?= $id ?>" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;">✏ Edit</a>
          <?php if (execIsAdmin($member['email']) && $voucher['status'] !== 'paid'): ?>
          <?php if ($voucher['status'] === 'submitted'): ?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Re-send the Treasurer notification email for this voucher?')">
        <?= csrfField() ?>
            <input type="hidden" name="action" value="resend_to_treasurer">
            <button type="submit" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;">&#x21BA; Resend to Treasurer</button>
          </form>
          <?php endif; ?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently delete this voucher and all its expenses? This cannot be undone.')">
        <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;color:#dc2626;border-color:#dc2626;">🗑 Delete</button>
          </form>
          <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
<?php if ($mayShare): ?>
        <button type="button" onclick="shareOpen()" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;">&#128279; Share</button>
        <?php endif; ?>
        <a href="?id=<?= $id ?>&export=csv" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;">⬇ CSV</a>
        <button onclick="window.print()" class="btn btn-outline" style="padding:.45rem .85rem;font-size:.83rem;">🖨 Print</button>
      </div>
      <a class="back-link no-print" href="lp-dashboard.php">← Expenses &amp; Grants</a>
    </div>

  </div>

      <?php if ($review['findings'] && in_array($voucher['status'], ['draft', 'rejected'], true)
            && ($isOwner || prodIsExec($member['email']))): ?>
  <div class="rev-card no-print">
    <div class="rev-head">
      <span class="rev-ico" aria-hidden="true">&#9906;</span>
      <div>
        <h2>Before you submit</h2>
        <p>
          <?php
            $bits = [];
            if ($review['missing']) $bits[] = $review['missing'] . ' thing' . ($review['missing'] === 1 ? '' : 's') . ' missing';
            if ($review['check'])   $bits[] = $review['check']   . ' worth checking';
            echo htmlspecialchars(implode(' · ', $bits));
          ?>.
          None of it stops you submitting — plenty of these turn out to be fine.
        </p>
      </div>
      <button type="button" class="rev-toggle" onclick="revToggle(this)" aria-expanded="true">Hide</button>
    </div>

    <ul class="rev-list" id="revList">
      <?php foreach ($review['findings'] as $f): ?>
      <li class="rev-item <?= $f['level'] === 'missing' ? 'is-missing' : 'is-check' ?>">
        <span class="rev-line">
          Line <?= (int)$f['line'] ?>
          <?php if (!empty($f['where'])): ?><em><?= htmlspecialchars($f['where']) ?></em><?php endif; ?>
        </span>
        <div>
          <span class="rev-what"><?= htmlspecialchars($f['what']) ?></span>
          <span class="rev-detail"><?= htmlspecialchars($f['detail']) ?></span>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($voucher['status'] === 'draft'): ?>
    <div class="rev-foot">
      <a href="lp-voucher-edit.php?id=<?= $id ?>" class="btn btn-outline" style="padding:.4rem .9rem;font-size:.83rem;">Fix these</a>
    </div>
    <?php endif; ?>
  </div>
  <style>
    .rev-card { background:#fffdf7; border:1px solid #ecd9a8; border-radius:10px;
                padding:1.1rem 1.25rem; margin-bottom:1.25rem; }
    .rev-head { display:flex; gap:.85rem; align-items:flex-start; }
    .rev-ico  { font-size:1.15rem; color:#9a6b00; line-height:1.3; }
    .rev-head h2 { font-size:.98rem; margin:0 0 .2rem; color:#6b4a00; }
    .rev-head p  { margin:0; font-size:.83rem; color:#7a6333; line-height:1.5; }
    .rev-toggle { margin-left:auto; background:none; border:0; color:#7a6333;
                  font:inherit; font-size:.8rem; text-decoration:underline; cursor:pointer; }
    .rev-list { list-style:none; margin:1rem 0 0; padding:0; }
    .rev-list.hid { display:none; }
    .rev-item { display:flex; gap:.7rem; align-items:baseline; padding:.5rem 0;
                border-top:1px solid #f0e3c4; font-size:.86rem; line-height:1.5; }
    .rev-line { flex:0 0 9.5rem; font-weight:700; font-size:.76rem; color:#8a6d3b; }
    .rev-line em { display:block; font-weight:400; font-style:normal; color:#9a8862; }
    @media (max-width:640px) { .rev-item { display:block; } .rev-line { margin-bottom:.15rem; } }
    .rev-what { font-weight:700; color:#5c4a1a; }
    .rev-item.is-missing .rev-what { color:#9c3a1e; }
    .rev-detail { color:#6f6246; }
    .rev-foot { margin-top:.9rem; }
  </style>
  <script>
    function revToggle(btn) {
      var list = document.getElementById('revList');
      var hid  = list.classList.toggle('hid');
      btn.textContent = hid ? 'Show' : 'Hide';
      btn.setAttribute('aria-expanded', hid ? 'false' : 'true');
    }
  </script>
  <?php endif; ?>

  <?php if ($voucher['status'] !== 'draft'): ?>
  <!-- Approval progress. Previously the page showed a flat "Submitted" for every
       non-draft status, so a Treasurer sign-off was invisible to the submitter. -->
  <div class="approval-card">
    <div class="approval-head">Approval Progress</div>
    <?= lpApprovalTrail($voucher) ?>
  </div>
  <?php endif; ?>

  <?php if ($notice): ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.88rem;color:#166534;font-weight:600;">
    ✓ <?= htmlspecialchars($notice) ?>
  </div>
  <?php endif; ?>

  <!-- Summary cards -->
  <div class="summary-row">
    <?php if ($totals['km'] > 0): ?>
    <div class="sum-card">
      <div class="lbl">Travel km</div>
      <div class="val"><?= number_format($totals['km'], 1) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($totals['travel'] > 0): ?>
    <div class="sum-card"><div class="lbl">Travel $</div><div class="val">$<?= number_format($totals['travel'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['meals'] > 0): ?>
    <div class="sum-card"><div class="lbl">Meals</div><div class="val">$<?= number_format($totals['meals'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['gifts'] > 0): ?>
    <div class="sum-card"><div class="lbl">Gifts</div><div class="val">$<?= number_format($totals['gifts'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['misc'] > 0): ?>
    <div class="sum-card"><div class="lbl">Misc</div><div class="val">$<?= number_format($totals['misc'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['office'] > 0): ?>
    <div class="sum-card"><div class="lbl">Office</div><div class="val">$<?= number_format($totals['office'],2) ?></div></div>
    <?php endif; ?>
    <?php if ($totals['phone'] > 0): ?>
    <div class="sum-card"><div class="lbl">Phone</div><div class="val">$<?= number_format($totals['phone'],2) ?></div></div>
    <?php endif; ?>
    <div class="sum-card total">
      <div class="lbl">Total</div>
      <div class="val">$<?= number_format($totals['total'],2) ?></div>
    </div>
  </div>

  <!-- Expense rows -->
  <div class="table-wrap">
    <table class="etable">
      <thead>
        <tr>
          <th>Receipt</th>
          <th id="thDate" title="Sort the rows by date">Date</th>
          <th>Description</th>
          <th class="r">km</th>
          <th class="r">Travel $</th>
          <th class="r">Meals $</th>
          <th class="r">Gifts $</th>
          <th class="r">Misc $</th>
          <th class="r">Office $</th>
          <th class="r">Phone $</th>
          <th class="r">Total</th>
          <th>Grant / Budget Line</th>
        </tr>
      </thead>
      <tbody id="expenseRows">
        <?php foreach ($expenses as $e):
          $rowTotal = lpRowTotal($e);
        ?>
        <tr>
          <td>
            <?php if ($e['receipt_path']): ?>
            <a class="receipt-btn" href="lp-receipt.php?f=<?= urlencode($e['receipt_path']) ?>" target="_blank">
              📄 View
            </a>
            <?php else: ?>
            <span style="color:var(--gray-300);font-size:.75rem;">—</span>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap;" data-sort-date="<?= htmlspecialchars($e['expense_date'] ?? '') ?>"><?= $e['expense_date'] ? date('M j, Y', strtotime($e['expense_date'])) : '—' ?></td>
          <td><?= htmlspecialchars($e['description'] ?? '') ?></td>
          <td class="r"><?= $e['travel_km'] > 0 ? number_format($e['travel_km'],1) : '' ?></td>
          <td class="r"><?= $e['travel_amt'] > 0 ? '$'.number_format($e['travel_amt'],2) : '' ?></td>
          <td class="r"><?= $e['meals']      > 0 ? '$'.number_format($e['meals'],2)      : '' ?></td>
          <td class="r"><?= $e['gifts']      > 0 ? '$'.number_format($e['gifts'],2)      : '' ?></td>
          <td class="r"><?= $e['misc']       > 0 ? '$'.number_format($e['misc'],2)        : '' ?></td>
          <td class="r"><?= $e['office']     > 0 ? '$'.number_format($e['office'],2)     : '' ?></td>
          <td class="r"><?= $e['phone']      > 0 ? '$'.number_format($e['phone'],2)       : '' ?></td>
          <td class="r bold">$<?= number_format($rowTotal,2) ?></td>
          <td>
            <?php if ($e['grant_name']): ?>
            <span class="tag tag-grant"><?= htmlspecialchars($e['grant_name']) ?></span>
            <?php endif; ?>
            <?php if ($e['budget_line_name']): ?>
            <br><span class="tag tag-bl"><?= htmlspecialchars($e['budget_line_name']) ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="font-weight:800;text-transform:uppercase;font-size:.72rem;letter-spacing:.05em;color:var(--gray-500);">Totals</td>
          <td class="r"><?= $totals['km']     > 0 ? number_format($totals['km'],1)     : '' ?></td>
          <td class="r"><?= $totals['travel'] > 0 ? '$'.number_format($totals['travel'],2) : '' ?></td>
          <td class="r"><?= $totals['meals']  > 0 ? '$'.number_format($totals['meals'],2)  : '' ?></td>
          <td class="r"><?= $totals['gifts']  > 0 ? '$'.number_format($totals['gifts'],2)  : '' ?></td>
          <td class="r"><?= $totals['misc']   > 0 ? '$'.number_format($totals['misc'],2)   : '' ?></td>
          <td class="r"><?= $totals['office'] > 0 ? '$'.number_format($totals['office'],2) : '' ?></td>
          <td class="r"><?= $totals['phone']  > 0 ? '$'.number_format($totals['phone'],2)  : '' ?></td>
          <td class="r" style="font-size:.92rem;">$<?= number_format($totals['total'],2) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Breakdown panels -->
  <?php if ($grantSummary || $blSummary): ?>
  <div class="breakdown-grid">
    <?php if ($grantSummary): ?>
    <div class="breakdown-card">
      <h3>By BCTF Grant</h3>
      <?php foreach ($grantSummary as $name => $amt): ?>
      <div class="breakdown-row">
        <span class="name"><?= htmlspecialchars($name) ?></span>
        <span class="amt">$<?= number_format($amt, 2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($blSummary): ?>
    <div class="breakdown-card">
      <h3>By Budget Line</h3>
      <?php foreach ($blSummary as $name => $amt): ?>
      <div class="breakdown-row">
        <span class="name"><?= htmlspecialchars($name) ?></span>
        <span class="amt">$<?= number_format($amt, 2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Signature block for printing -->
  <div style="margin-top:2rem;display:grid;grid-template-columns:1fr 1fr;gap:3rem;padding-top:2rem;border-top:1px solid var(--gray-200);">
    <div>
      <div style="font-size:.75rem;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;">Submitted by</div>
      <div style="font-size:.92rem;font-weight:600;"><?= htmlspecialchars($voucher['submitted_by']) ?></div>
      <div style="margin-top:2.5rem;border-top:1px solid #ccc;padding-top:.3rem;font-size:.75rem;color:var(--gray-400);">Signature &amp; Date</div>
    </div>
    <div>
      <div style="font-size:.75rem;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;">Approved by Treasurer</div>
      <div style="font-size:.92rem;font-weight:600;">
        <?php if (!empty($voucher['signer1_at'])): ?>
          <?= htmlspecialchars($voucher['signer1_name'] ?: $voucher['signer1_email']) ?>
          <span style="font-weight:400;color:var(--gray-500);">
            &middot; <?= date('M j, Y', strtotime($voucher['signer1_at'])) ?>
          </span>
        <?php else: ?>&nbsp;<?php endif; ?>
      </div>
      <div style="margin-top:2.5rem;border-top:1px solid #ccc;padding-top:.3rem;font-size:.75rem;color:var(--gray-400);">Signature &amp; Date</div>
    </div>
  </div>

</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <span class="lightbox-close">×</span>
  <a id="lightbox-opennew" class="lightbox-opennew no-print" href="#" target="_blank" onclick="event.stopPropagation()">⬡ Open in new tab</a>
  <img src="" id="lightboxImg" alt="Receipt">
  <iframe id="lightboxFrame" src="" style="display:none;"></iframe>
</div>

<script>
function openLightbox(src) {
    const isPdf = /\.pdf/i.test(src);
    const img   = document.getElementById('lightboxImg');
    const frame = document.getElementById('lightboxFrame');
    const openNew = document.getElementById('lightbox-opennew');
    if (isPdf) {
        img.style.display = 'none'; img.src = '';
        frame.src = src; frame.style.display = 'block';
    } else {
        frame.style.display = 'none'; frame.src = '';
        img.src = src; img.style.display = 'block';
    }
    if (openNew) openNew.href = src;
    document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.getElementById('lightboxImg').src = '';
    document.getElementById('lightboxFrame').src = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>
<script src="../js/sort-by-date.js?v=<?= @filemtime(__DIR__ . '/../js/sort-by-date.js') ?>"></script>
<script>bvtuSortByDate({ body: '#expenseRows', header: '#thDate' });</script>

<?php if ($mayShare):
      $liveShares = array_values(array_filter($shares, function ($r) {
          return empty($r['revoked_at'])
              && (empty($r['expires_at']) || strtotime($r['expires_at']) >= time());
      })); ?>
<div class="share-veil" id="shareVeil" onclick="if(event.target===this)shareClose()">
  <div class="share-box" role="dialog" aria-modal="true" aria-labelledby="shareTitle">
    <div class="share-head">
      <h2 id="shareTitle">Share &ldquo;<?= htmlspecialchars($voucher['name']) ?>&rdquo;</h2>
      <button type="button" class="share-x" onclick="shareClose()" aria-label="Close">&times;</button>
    </div>

    <form method="POST" class="share-add">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="share-create">
      <input type="email" name="shared_with" placeholder="Email address" aria-label="Email address">
      <label class="share-send"><input type="checkbox" name="send" value="1" checked> Email them the link</label>
      <button type="submit" class="btn btn-primary" style="font-size:.85rem;padding:.45rem 1rem;">Share</button>
    </form>

    <div class="share-gen">
      <div class="share-gen-ico">&#128279;</div>
      <div style="flex:1 1 auto;min-width:0;">
        <div style="font-weight:700;font-size:.9rem;">Anyone with the link can view</div>
        <div style="font-size:.8rem;color:var(--gray-500);">
          No account needed. They can read every line and receipt, and save a PDF.
          Links stop working after <?= LP_SHARE_DAYS ?> days.
        </div>
      </div>
    </div>

    <?php if ($liveShares): ?>
    <div class="share-list">
      <div class="share-list-h">People with access</div>
      <?php foreach ($liveShares as $sh): ?>
      <div class="share-row">
        <div style="flex:1 1 auto;min-width:0;">
          <div class="share-who"><?= htmlspecialchars($sh['shared_with'] ?: 'Anyone with this link') ?></div>
          <div class="share-when">
            <?php if ($sh['last_viewed_at']): ?>
              Opened <?= htmlspecialchars(date('M j', strtotime($sh['last_viewed_at']))) ?>
              <?= (int)$sh['view_count'] > 1 ? '· ' . (int)$sh['view_count'] . ' times' : '' ?>
            <?php else: ?>
              Not opened yet
            <?php endif; ?>
            · expires <?= htmlspecialchars(date('M j, Y', strtotime($sh['expires_at']))) ?>
          </div>
        </div>
        <div class="share-act">
          <input type="text" class="share-url" readonly value="<?= htmlspecialchars(lpShareUrl($sh['token'])) ?>"
                 onfocus="this.select()" aria-label="Review link">
          <button type="button" class="share-copy" onclick="shareCopy(this)">Copy</button>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Remove access? That link will stop working immediately.');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="share-revoke">
            <input type="hidden" name="share_id" value="<?= (int)$sh['id'] ?>">
            <button type="submit" class="share-rm" title="Remove access">Remove</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="share-foot">
      <span style="font-size:.78rem;color:var(--gray-500);">
        Viewing only &mdash; nobody with a link can edit, approve or sign.
      </span>
      <button type="button" class="btn btn-outline" style="font-size:.85rem;padding:.4rem .95rem;" onclick="shareClose()">Done</button>
    </div>
  </div>
</div>

<style>
  .share-veil { display:none; position:fixed; inset:0; background:rgba(20,24,20,.45); z-index:900;
                align-items:flex-start; justify-content:center; padding:4vh 1rem; overflow:auto; }
  .share-veil.open { display:flex; }
  .share-box { background:#fff; border-radius:12px; width:100%; max-width:34rem;
               box-shadow:0 12px 40px rgba(0,0,0,.25); padding:1.4rem 1.5rem 1.1rem; }
  .share-head { display:flex; align-items:flex-start; gap:1rem; margin-bottom:1.1rem; }
  .share-head h2 { font-size:1.08rem; margin:0; flex:1 1 auto; color:var(--primary); }
  .share-x { background:none; border:0; font-size:1.5rem; line-height:1; color:var(--gray-400);
             cursor:pointer; padding:0 .2rem; }
  .share-add { display:flex; gap:.55rem; align-items:center; flex-wrap:wrap; margin-bottom:1.1rem; }
  .share-add input[type=email] { flex:1 1 12rem; padding:.55rem .7rem; border:1px solid var(--border);
                                 border-radius:7px; font:inherit; font-size:.9rem; }
  .share-send { font-size:.8rem; color:var(--gray-600); display:flex; align-items:center; gap:.3rem; }
  .share-gen { display:flex; gap:.8rem; align-items:flex-start; background:var(--off-white);
               border-radius:8px; padding:.8rem .9rem; margin-bottom:1.1rem; }
  .share-gen-ico { font-size:1.1rem; }
  .share-list-h { font-size:.74rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em;
                  color:var(--gray-500); margin-bottom:.5rem; }
  .share-row { padding:.7rem 0; border-top:1px solid var(--gray-100); }
  .share-act { display:flex; gap:.45rem; align-items:center; margin-top:.4rem; }
  .share-who  { font-weight:700; font-size:.9rem; }
  .share-when { font-size:.76rem; color:var(--gray-500); }
  .share-url  { flex:1 1 auto; min-width:0; font-size:.74rem; padding:.35rem .5rem;
                border:1px solid var(--border); border-radius:5px; color:var(--gray-600); background:var(--off-white); }
  .share-copy { background:var(--primary); color:#fff; border:0; border-radius:5px;
                font-size:.78rem; font-weight:700; padding:.38rem .7rem; cursor:pointer; }
  .share-rm   { background:none; border:0; color:#b3261e; font-size:.78rem; cursor:pointer;
                text-decoration:underline; padding:.38rem .2rem; }
  .share-foot { display:flex; justify-content:space-between; align-items:center; gap:1rem;
                flex-wrap:wrap; border-top:1px solid var(--gray-100); margin-top:1rem; padding-top:.9rem; }
</style>

<script>
  function shareOpen()  { document.getElementById('shareVeil').classList.add('open'); }
  function shareClose() { document.getElementById('shareVeil').classList.remove('open'); }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') shareClose();
  });
  function shareCopy(btn) {
    var box = btn.parentNode.querySelector('.share-url');
    var done = function () { btn.textContent = 'Copied'; setTimeout(function(){ btn.textContent = 'Copy'; }, 1800); };
    // Clipboard access needs a secure context and can still be refused; the
    // select-and-copy fallback works everywhere and leaves the link selected
    // so it can be copied by hand if even that fails.
    box.select();
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(box.value).then(done, function () {
        try { document.execCommand('copy'); done(); } catch (err) {}
      });
    } else {
      try { document.execCommand('copy'); done(); } catch (err) {}
    }
  }
  <?php if ($newShare): ?>shareOpen();<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>
