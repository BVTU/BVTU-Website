<?php
/**
 * voucher-receipt.php — serve one receipt to the holder of a review link.
 *
 * The reviewer has no portal account, so lp-receipt.php (which requires a
 * login) cannot serve these. The share token is the credential, and it opens
 * exactly one voucher: a filename that does not belong to that voucher is
 * refused, or the token would read every receipt in the system.
 */
require_once __DIR__ . '/members/db.php';
require_once __DIR__ . '/members/lp-db.php';
require_once __DIR__ . '/members/lp-share.php';

$share = lpShareByToken((string)($_GET['t'] ?? ''));
$state = lpShareState($share);

if (!$state['ok']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This review link is no longer available.');
}

$file = basename((string)($_GET['f'] ?? ''));
if ($file === '' || $file[0] === '.') {
    http_response_code(400);
    exit('Invalid request.');
}

// The stored spelling, not the requested one: MySQL compares strings without
// regard to case or trailing spaces, so "R1.PNG " can match the row while
// naming a different file — or no file — on disk.
$stored = lpShareReceiptName((int)$share['voucher_id'], $file);
if ($stored === null) {
    http_response_code(403);
    exit('That receipt is not part of this voucher.');
}

// Content-Type comes from the extension allowlist, never from sniffing the
// bytes. lp-scan.php will accept a file whose extension and content disagree,
// and a sniffed text/html served inline from our own domain is stored XSS.
$mimeMap = [
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png'  => 'image/png',
    'gif'  => 'image/gif',  'webp' => 'image/webp', 'heic' => 'image/heic',
    'heif' => 'image/heif', 'pdf'  => 'application/pdf',
];
$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
if (!isset($mimeMap[$ext])) {
    http_response_code(403);
    exit('That receipt cannot be shown.');
}

$path = LP_RECEIPTS_DIR . $stored;
if (!is_file($path)) {
    http_response_code(404);
    exit('The receipt file is missing from the server.');
}

header('Content-Type: ' . $mimeMap[$ext]);
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'none\'');
header('Content-Disposition: inline; filename="' . addslashes($stored) . '"');
header('Content-Length: ' . filesize($path));
header('X-Robots-Tag: noindex, nofollow');
// Not cached: withdrawing a link has to stop working straight away, and a
// cached receipt would outlive the revoke by an hour.
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
