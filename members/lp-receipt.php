<?php
/**
 * lp-receipt.php — Serve LP expense receipt images securely (auth-gated)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
requireLogin();

$member = getMember();
$path   = basename($_GET['f'] ?? '');

if (!$path) { http_response_code(400); exit('Invalid request.'); }

// Find which voucher owns this receipt — check saved expenses first, then pending tray
$s = getDB()->prepare(
    "SELECT v.submitted_by_email FROM lp_expenses e
     JOIN lp_vouchers v ON v.id = e.voucher_id
     WHERE e.receipt_path = ? LIMIT 1"
);
$s->execute([$path]);
$row = $s->fetch();

// Phone-uploaded receipts live in lp_pending_receipts until claimed
if (!$row) {
    $s2 = getDB()->prepare(
        "SELECT v.submitted_by_email FROM lp_pending_receipts pr
         JOIN lp_vouchers v ON v.id = pr.voucher_id
         WHERE pr.saved_path = ? LIMIT 1"
    );
    $s2->execute([$path]);
    $row = $s2->fetch();
}

if (!$row) { http_response_code(404); exit('Receipt not found.'); }

$isOwner      = $row['submitted_by_email'] === $member['email'];
$isPrivileged = lpCanView($member['email']);

if (!$isOwner && !$isPrivileged) { http_response_code(403); exit('Access denied.'); }

$filePath = LP_RECEIPTS_DIR . $path;
if (!file_exists($filePath)) { http_response_code(404); exit('File not found on server.'); }

$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . addslashes(basename($filePath)) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
readfile($filePath);
