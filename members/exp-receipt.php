<?php
/**
 * exp-receipt.php — Serve expense receipt images securely (auth-gated)
 *
 * GET ?f=filename.jpg
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();

$member = getMember();
$path   = basename($_GET['f'] ?? '');

if (!$path) {
    http_response_code(400);
    exit('Invalid request.');
}

expEnsureTables();

// Find which expense owns this receipt — check saved expenses first
$s = getDB()->prepare(
    "SELECT user_email FROM exp_expenses WHERE receipt_path = ? LIMIT 1"
);
$s->execute([$path]);
$row = $s->fetch();

// Phone-uploaded receipts live in exp_pending_receipts until claimed
if (!$row) {
    $s2 = getDB()->prepare(
        "SELECT e.user_email
         FROM exp_pending_receipts pr
         JOIN exp_expenses e ON e.id = pr.expense_id
         WHERE pr.saved_path = ? LIMIT 1"
    );
    $s2->execute([$path]);
    $row = $s2->fetch();
}

if (!$row) {
    http_response_code(404);
    exit('Receipt not found.');
}

$isOwner   = strtolower($row['user_email']) === strtolower($member['email']);
$canReview = expCanReview($member['email']);

if (!$isOwner && !$canReview) {
    http_response_code(403);
    exit('Access denied.');
}

$filePath = EXP_RECEIPTS_DIR . $path;
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found on server.');
}

$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . addslashes(basename($filePath)) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
readfile($filePath);
