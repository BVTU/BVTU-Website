<?php
/**
 * lp-poll-receipts.php — Returns unclaimed pending receipts for a voucher.
 * GET ?voucher_id=X
 * Returns JSON: { receipts: [...], count: N }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
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

// Build preview URLs and clean up for JS
$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id'            => (int)$r['id'],
        'saved_path'    => $r['saved_path'],
        'original_name' => $r['original_name'],
        'preview_url'   => 'lp-receipt.php?f=' . urlencode($r['saved_path']),
        'scan_data'     => $r['scan_data'],
        'created_at'    => $r['created_at'],
    ];
}

echo json_encode(['receipts' => $out, 'count' => count($out)]);
