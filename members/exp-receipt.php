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

// basename() already blocks traversal, but it leaves "..", and it leaves the
// .htaccess this directory is protected by — neither of which has an owning
// row, so both would otherwise reach a reviewer.
if ($path[0] === '.') {
    http_response_code(400);
    exit('Invalid request.');
}

$filePath = EXP_RECEIPTS_DIR . $path;
$access   = expReceiptAccess($path, $member['email']);

// Authorization first, then existence. Answering "is this file here?" before
// the access check would let any logged-in member probe the receipts folder
// and learn which claims exist.
//
// Reviewers can open every receipt in the system anyway, so a file whose owner
// cannot be established is still theirs to read. This is what keeps the next
// table that stores a receipt_path from silently 404ing all of its receipts,
// the way exp_batch_items did and lp_direct_expenses did before it.
if (!$access['owned'] && !expCanReview($member['email'])) {
    http_response_code(403);
    exit('Access denied.');
}

// The old code answered "Receipt not found." whenever the filename matched no
// database row, which reads as "nothing was uploaded" even when the photo is
// sitting safely in exp-receipts/. Said separately now, because a reviewer
// chasing a missing receipt needs to know which of the two it is.
if (!is_file($filePath)) {
    http_response_code(404);
    exit($access['row']
        ? 'This receipt is recorded, but its file is missing from the server. Please ask the member to upload it again.'
        : 'Receipt not found.');
}

$mimeMap = [
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png'  => 'image/png',
    'gif'  => 'image/gif',  'webp' => 'image/webp', 'heic' => 'image/heic',
    'heif' => 'image/heif', 'pdf'  => 'application/pdf',
];
$ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeType = mime_content_type($filePath) ?: ($mimeMap[$ext] ?? 'application/octet-stream');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . addslashes(basename($filePath)) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
readfile($filePath);
