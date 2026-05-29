<?php
/**
 * exp-claim-receipt.php — AJAX: mark a pending phone receipt as claimed
 *
 * POST pending_id=N
 * Returns JSON: { ok: true }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

header('Content-Type: application/json');

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$pendingId = (int)($_POST['pending_id'] ?? 0);
if (!$pendingId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing pending_id']);
    exit;
}

expClaimPendingReceipt($pendingId);
echo json_encode(['ok' => true]);
