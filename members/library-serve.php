<?php
/**
 * library-serve.php — Auth-gated file download
 * Increments download counter, then streams the file.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/library-db.php';
requireLogin();

$id       = (int)($_GET['id'] ?? 0);
$member   = getMember();
$resource = $id ? libGetResource($id) : null;

// Admins can download unpublished resources; members cannot
if (!$resource || ($resource['status'] !== 'published' && !libIsAdmin($member['email']))) {
    http_response_code(404);
    exit('Resource not found.');
}

$filePath = LIB_UPLOAD_DIR . $resource['file_path'];
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found on server.');
}

$isPreview = isset($_GET['preview']);

// Only count as a download when actually downloading (not previewing)
if (!$isPreview) {
    libIncrementDownload($id);
}

$ext      = strtolower($resource['file_ext']);
$mimeMap  = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// Sanitise the download filename
$safeFileName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $resource['file_name']);

// Preview mode: inline display for PDF.js; download mode: force save
$disposition = $isPreview ? 'inline' : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $safeFileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-cache');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : '*'));
readfile($filePath);
exit;
