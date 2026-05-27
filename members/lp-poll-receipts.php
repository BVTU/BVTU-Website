<?php
/**
 * lp-poll-receipts.php — Returns unclaimed pending receipts for a voucher.
 * GET ?voucher_id=X
 * Returns JSON: { receipts: [...], count: N }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }

$voucherId = (int)($_GET['voucher_id'] ?? 0);
if (!$voucherId) { http_response_code(400); echo json_encode(['error' => 'Missing voucher_id']); exit; }

lpEnsureTables();

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
