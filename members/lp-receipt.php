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

/*
 * Receipts after the first on a line.
 *
 * A line can carry the itemised bill and the card slip; the first is in
 * receipt_path and the rest are JSON in receipt_extra. Matching only the
 * first meant every other receipt 404'd — from the chooser, from the
 * Treasurer's "View 2", from the printed pack.
 *
 * LIKE finds the candidate rows and the decoded list decides, so a filename
 * that merely appears inside another row's JSON cannot authorise itself.
 */
if (!$row) {
    try {
        $s1b = getDB()->prepare(
            "SELECT e.receipt_extra, v.submitted_by_email FROM lp_expenses e
             JOIN lp_vouchers v ON v.id = e.voucher_id
             WHERE e.receipt_extra LIKE ?"
        );
        $s1b->execute(['%' . $path . '%']);
        foreach ($s1b->fetchAll() as $cand) {
            foreach (lpRowReceipts(['receipt_extra' => $cand['receipt_extra']]) as $rc) {
                if (basename($rc['path']) === $path) {
                    $row = ['submitted_by_email' => $cand['submitted_by_email']];
                    break 2;
                }
            }
        }
    } catch (\Throwable $e) {
        // A server without the column simply has no extras to find.
        error_log('lp-receipt extras: ' . $e->getMessage());
    }
}

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

// Payments made outside a voucher keep their receipts in the same folder but
// their own table, and this served 404 for every one of them.
if (!$row) {
    $s3 = getDB()->prepare(
        "SELECT created_by AS submitted_by_email FROM lp_direct_expenses
         WHERE receipt_path = ? LIMIT 1"
    );
    $s3->execute([$path]);
    $row = $s3->fetch();
}

if (!$row) { http_response_code(404); exit('Receipt not found.'); }

$isOwner      = $row['submitted_by_email'] === $member['email'];
// lpCanView() now covers President and Treasurer. The VP reaches the voucher
// itself through lp-voucher-edit.php (gated on lpCanReview), so without this
// they could read the line items but got 403 on every receipt to be signed.
$isPrivileged = lpCanView($member['email']) || lpCanReview($member['email']);

if (!$isOwner && !$isPrivileged) { http_response_code(403); exit('Access denied.'); }

$filePath = LP_RECEIPTS_DIR . $path;
if (!file_exists($filePath)) { http_response_code(404); exit('File not found on server.'); }

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
