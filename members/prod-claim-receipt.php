<?php
/**
 * prod-claim-receipt.php — AJAX: mark a pending phone receipt as dealt with.
 * POST pending_id=N, csrf_token
 *
 * It used to check only that somebody was logged in, so any member could post
 * an id and clear receipts out of another member's Pro-D tray. Same hole as the
 * expense and LP twins; closed the same way.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';

header('Content-Type: application/json');

// isLoggedIn() rather than requireLogin(): the latter redirects to login.php,
// which a fetch follows and resolves as login HTML at status 200 — a failure
// the caller cannot distinguish from success.
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Not logged in']); exit; }
prodEnsureTables();
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

$pending = prodGetPendingReceiptById($pendingId);
if (!$pending) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Receipt not found']);
    exit;
}

// Mirrors prod-poll-receipt.php's gate exactly: the claimant, or a Pro-D exec.
// Fetched inline because prod-db.php has no by-id request lookup and adding one
// for a single caller is more surface than the check needs.
$q = getDB()->prepare("SELECT user_email FROM prod_requests WHERE id=? LIMIT 1");
$q->execute([(int)$pending['request_id']]);
$req = $q->fetch();
$isOwner = $req && strtolower(trim($req['user_email'] ?? '')) === strtolower(trim($member['email']));
if (!$isOwner && !prodIsExec($member['email'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not your receipt']);
    exit;
}

prodClaimPendingReceipt($pendingId);
echo json_encode(['ok' => true]);
