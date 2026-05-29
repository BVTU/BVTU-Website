<?php
/**
 * exp-poll-receipt.php — AJAX: check for pending phone receipt
 *
 * GET ?expense_id=X
 * Returns JSON: { receipt: {...} | null }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

header('Content-Type: application/json');

requireLogin();
$member = getMember();

$expenseId = (int)($_GET['expense_id'] ?? 0);
if (!$expenseId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing expense_id']);
    exit;
}

$exp = expGet($expenseId);
if (!$exp) {
    http_response_code(404);
    echo json_encode(['error' => 'Expense not found']);
    exit;
}

// Access: owner OR reviewer
$isOwner   = strtolower($exp['user_email']) === strtolower($member['email']);
$canReview = expCanReview($member['email']);

if (!$isOwner && !$canReview) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$receipt = expGetPendingReceipt($expenseId);
echo json_encode(['receipt' => $receipt]);
