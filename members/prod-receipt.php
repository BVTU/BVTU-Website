<?php
/**
 * prod-receipt.php — Serve receipt files securely (auth-gated)
 * Members can only access their own receipts; admins can access all.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
$id     = (int)($_GET['id'] ?? 0);

if ($id <= 0) { http_response_code(400); exit('Invalid request.'); }

$stmt = getDB()->prepare("SELECT user_email, receipt_path, receipt_filename FROM prod_claims WHERE id=?");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim || !$claim['receipt_path']) { http_response_code(404); exit('Receipt not found.'); }

// Access control: own claims or admin
if ($claim['user_email'] !== $member['email'] && !prodIsAdmin($member['email'])) {
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
