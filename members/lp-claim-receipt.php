<?php
/**
 * lp-claim-receipt.php — mark a pending receipt as dealt with, either because
 * it has been attached to a row or because it was dismissed.
 * POST: pending_id=N, csrf_token
 *
 * It used to check only that SOMEBODY was logged in, so any member could post
 * an id and clear receipts out of the President's tray — the receipts stayed on
 * disk, but they vanished from the only screen that offers them.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/prod-db.php';   // prodIsExec(), matching the editor's gate

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok' => false]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$member = getMember();
if (!csrfValid()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Could not verify the request. Reload the page.']);
    exit;
}

$id = (int)($_POST['pending_id'] ?? 0);
if (!$id) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'No receipt given.']); exit; }

// Whose voucher is it? Only its owner, or someone who can act on LP vouchers,
// may clear a receipt from the tray.
$pending = lpGetPendingReceiptById($id);
if (!$pending) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Receipt not found.']); exit; }

$voucher = lpGetVoucher((int)$pending['voucher_id']);
$isOwner = $voucher
        && strtolower(trim($voucher['submitted_by_email'] ?? '')) === strtolower(trim($member['email']));

// Mirrors lp-voucher-edit.php's gate exactly — owner, a Pro-D exec, or a
// signer reviewing it, plus the President via lpCanView. A narrower gate
// here means a reviewer's row clears on screen while the receipt
// stays unclaimed and returns on the next reload.
if (!$isOwner && !lpCanView($member['email']) && !prodIsExec($member['email'])
             && !lpCanReview($member['email'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not your receipt.']);
    exit;
}

lpClaimPendingReceipt($id);
echo json_encode(['ok' => true]);
