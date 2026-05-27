<?php
/**
 * prod-receipt.php — Serve Pro-D receipt files securely (auth-gated)
 * Members can only access their own receipts; admins can access all.
 *
 * Supports two modes:
 *   ?id=X [&table=request]  — look up by claim/request row
 *   ?f=filename             — look up by saved filename (used by phone QR polling)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();

// ── Mode 1: serve by saved filename (phone QR pending receipt path) ───────────
if (!empty($_GET['f'])) {
    $filename = basename($_GET['f']);
    $filePath = PROD_RECEIPTS_DIR . $filename;

    if (!file_exists($filePath)) { http_response_code(404); exit('File not found.'); }

    // Access check: must own a prod_request or prod_pending_receipt with this path,
    // or be exec/treasurer/site_rep
    $isPrivileged = prodIsExec($member['email']) || prodIsTreasurer($member['email']) || prodIsSiteRep($member['email']);

    if (!$isPrivileged) {
        // Check prod_requests
        $s = getDB()->prepare("SELECT user_email FROM prod_requests WHERE receipt_path=? LIMIT 1");
        $s->execute([$filename]);
        $row = $s->fetch();

        if (!$row) {
            // Check prod_pending_receipts via request ownership
            $s = getDB()->prepare(
                "SELECT r.user_email FROM prod_pending_receipts p
                 JOIN prod_requests r ON r.id = p.request_id
                 WHERE p.saved_path=? LIMIT 1"
            );
            $s->execute([$filename]);
            $row = $s->fetch();
        }

        if (!$row || $row['user_email'] !== $member['email']) {
            http_response_code(403); exit('Access denied.');
        }
    }

    $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private, max-age=3600');
    readfile($filePath);
    exit;
}

// ── Mode 2: serve by claim/request row ID ─────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); exit('Invalid request.'); }

// Support both prod_claims (legacy) and prod_requests (new two-phase)
$table = ($_GET['table'] ?? '') === 'request' ? 'prod_requests' : 'prod_claims';
$stmt = getDB()->prepare("SELECT user_email, receipt_path, receipt_filename FROM {$table} WHERE id=?");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim || !$claim['receipt_path']) { http_response_code(404); exit('Receipt not found.'); }

// Access control: own records or admin/treasurer/site_rep
$isPrivileged = prodIsAdmin($member['email']) || prodIsTreasurer($member['email']) || prodIsSiteRep($member['email']);
if ($claim['user_email'] !== $member['email'] && !$isPrivileged) {
    http_response_code(403);
    exit('Access denied.');
}

$filePath = PROD_RECEIPTS_DIR . basename($claim['receipt_path']);
if (!file_exists($filePath)) { http_response_code(404); exit('File not found on server.'); }

$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
$filename = $claim['receipt_filename'] ?: basename($filePath);

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . addslashes($filename) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
readfile($filePath);
