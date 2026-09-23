<?php
/**
 * exp-claim-receipt.php — AJAX: mark a pending phone receipt as dealt with.
 * POST pending_id=N, csrf_token
 *
 * It used to check only that somebody was logged in, so any member could post
 * an id and clear receipts out of another member's tray. Its read-side sibling
 * exp-poll-receipt.php already checked owner-or-reviewer; the write side was
 * the weaker half.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

header('Content-Type: application/json');

// isLoggedIn(), not requireLogin(): the latter redirects to login.php, which a
// fetch follows and resolves as HTML at status 200.
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Not logged in']); exit; }
$member = getMember();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
if (!csrfValid()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Could not verify the request. Reload the page.']);
    exit;
}

$pendingId = (int)($_POST['pending_id'] ?? 0);
if (!$pendingId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing pending_id']);
    exit;
}

$pending = expGetPendingReceiptById($pendingId);
if (!$pending) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Receipt not found']);
    exit;
}

// Same test as exp-poll-receipt.php: the claimant, or someone who reviews.
$exp = expGet((int)$pending['expense_id']);
$isOwner = $exp && strtolower(trim($exp['user_email'] ?? '')) === strtolower(trim($member['email']));
if (!$isOwner && !expCanReview($member['email'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not your receipt']);
    exit;
}

expClaimPendingReceipt($pendingId);
echo json_encode(['ok' => true]);
