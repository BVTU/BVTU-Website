<?php
/**
 * lp-poll-receipts.php — Returns unclaimed pending receipts for a voucher.
 * GET ?voucher_id=X
 * Returns JSON: { receipts: [...], count: N }
 *
 * Each receipt carries a 'duplicate' block when it matches something already
 * on this year's vouchers — decided here rather than on the phone, because the
 * desktop is where the receipt is filed and where the answer is of any use.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/lp-dupes.php';
require_once __DIR__ . '/prod-db.php';   // prodIsExec(), matching the editor's gate

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }

$voucherId = (int)($_GET['voucher_id'] ?? 0);
if (!$voucherId) { http_response_code(400); echo json_encode(['error' => 'Missing voucher_id']); exit; }

lpEnsureTables();

$member  = getMember();
$voucher = lpGetVoucher($voucherId);
if (!$voucher) { http_response_code(404); echo json_encode(['error' => 'Voucher not found']); exit; }

$isOwner = strtolower(trim($voucher['submitted_by_email'] ?? '')) === strtolower(trim($member['email']));
// Mirrors lp-voucher-edit.php's gate exactly — owner, a Pro-D exec, or a
// signer reviewing it, plus the President via lpCanView. A narrower gate
// here means a reviewer's tray silently never fills.
if (!$isOwner && !lpCanView($member['email']) && !prodIsExec($member['email'])
             && !lpCanReview($member['email'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not your voucher']);
    exit;
}

$rows = lpGetPendingReceipts($voucherId);

$year = (int)($voucher['year'] ?? lpCurrentYear());

/* The gate above is deliberately wide — a Pro-D exec or a reviewer must see
 * this voucher's tray fill. The duplicate reminder is narrower: it names OTHER
 * vouchers' descriptions, amounts and numbers, which is more than seeing this
 * one. Same test lp-scan.php applies, for the same reason. */
$mayCompare = lpCanCreate($member['email']) || lpCanView($member['email']);
if ($rows && $mayCompare) lpDupeBackfill();

// Build preview URLs and clean up for JS
$out = [];
foreach ($rows as $r) {
    $sd    = is_array($r['scan_data'] ?? null) ? $r['scan_data'] : [];
    $total = 0.0;
    foreach (['travel_amount','meals_amount','gifts_amount','misc_amount','office_amount','phone_amount'] as $k) {
        $total += (float)($sd[$k] ?? 0);
    }
    if ($total <= 0) $total = (float)($sd['total_amount'] ?? 0);

    $hash = lpDupeHashOf($r['saved_path']);

    $out[] = [
        'id'            => (int)$r['id'],
        'saved_path'    => $r['saved_path'],
        'original_name' => $r['original_name'],
        'preview_url'   => 'lp-receipt.php?f=' . urlencode($r['saved_path']),
        'scan_data'     => $r['scan_data'],
        'created_at'    => $r['created_at'],
        // Carried so the page can also spot the same photo sent twice from the
        // phone, which is on no voucher yet and so on no query's radar.
        'sha256'        => $hash,
        'duplicate'     => $mayCompare
            ? lpDupeFind($year, $hash, $sd['vendor'] ?? null, $sd['date'] ?? null, $total)
            : null,
    ];
}

echo json_encode(['receipts' => $out, 'count' => count($out)]);
