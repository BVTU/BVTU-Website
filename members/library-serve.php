<?php
/**
 * library-serve.php — Auth-gated file download
 * Increments download counter, then streams the file.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/library-db.php';

$id        = (int)($_GET['id']   ?? 0);
$fileId    = (int)($_GET['file'] ?? 0);
$isPreview = isset($_GET['preview']);

// Preview mode is public (no login required) — download mode requires auth
if (!$isPreview) {
    requireLogin();
}

$member   = isLoggedIn() ? getMember() : null;
$isAdmin  = $member ? libIsAdmin($member['email']) : false;
$resource = $id ? libGetResource($id) : null;

// Guests may only preview published resources; unpublished requires admin
if (!$resource || ($resource['status'] !== 'published' && !$isAdmin)) {
    http_response_code(404);
    exit('Resource not found.');
}

if ($fileId) {
    // ── Serve an additional file ──────────────────────────────
    $fileRow = libGetResourceFile($fileId);
    if (!$fileRow || (int)$fileRow['resource_id'] !== $id) {
        http_response_code(404);
        exit('File not found.');
    }
    $filePath     = LIB_UPLOAD_DIR . $fileRow['file_path'];
    $ext          = strtolower($fileRow['file_ext']);
    $safeFileName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $fileRow['file_name']);
    // Download counter is on the resource, not per-file
    if (!$isPreview) libIncrementDownload($id);
} else {
    // ── Serve the primary file ────────────────────────────────
    $filePath     = LIB_UPLOAD_DIR . $resource['file_path'];
    $ext          = strtolower($resource['file_ext']);
    $safeFileName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $resource['file_name']);
    if (!$isPreview) libIncrementDownload($id);
}

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found on server.');
}

$mimeMap  = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// Preview mode: inline display for PDF.js; download mode: force save
$disposition = $isPreview ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $safeFileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-cache');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '*'));
readfile($filePath);
exit;
