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

libIncrementDownload($id);

$ext      = strtolower($resource['file_ext']);
$mimeMap  = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';

// Sanitise the download filename
$safeFileName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', $resource['file_name']);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-cache');
readfile($filePath);
exit;
