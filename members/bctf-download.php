<?php
/**
 * bctf-download.php — download a sent batch of membership forms as a ZIP.
 *
 * Sending only stamps sent_at, so the images stay on the server; this is the
 * way back to them once a batch has left the pending list. Files are named the
 * same way they were attached to the email.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/bctf-db.php';

requireLogin();
if (!execIsAdmin(getMember()['email'])) { http_response_code(403); exit('Access denied.'); }

$batchKey = trim($_GET['batch'] ?? '');
if ($batchKey === '') { http_response_code(400); exit('No batch specified.'); }

$forms = bctfGetBatch($batchKey);
if (!$forms) { http_response_code(404); exit('That batch was not found.'); }

if (!class_exists('ZipArchive')) { http_response_code(500); exit('ZIP support unavailable on this server.'); }

$names  = bctfAttachmentNames($forms);
$tmpZip = tempnam(sys_get_temp_dir(), 'bctfzip');
$zip    = new ZipArchive();
$zip->open($tmpZip, ZipArchive::OVERWRITE);

$added = 0;
foreach ($forms as $f) {
    $path = BCTF_FORMS_DIR . basename($f['saved_path']);
    if (file_exists($path)) {
        $zip->addFile($path, $names[(int)$f['id']]);
        $added++;
    }
}
$zip->close();

if (!$added) { @unlink($tmpZip); http_response_code(404); exit('The images for that batch are missing from the server.'); }

$stamp = preg_replace('/[^0-9]/', '-', $batchKey);
$zipName = 'BCTF-Membership-Forms-' . $stamp . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
// tempnam() created this file at 0 bytes and PHP caches that stat, so
// filesize() can report 0 after it has been written — the browser then
// saves a truncated or empty download.
clearstatcache(true, $tmpZip);
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
unlink($tmpZip);
