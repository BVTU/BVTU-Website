<?php
/**
 * bctf-image.php — serve a captured form thumbnail to the President only.
 * The upload directory is denied by .htaccess; this is the only way in.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/bctf-db.php';

requireLogin();
if (!execIsAdmin(getMember()['email'])) { http_response_code(403); exit('Access denied.'); }

$s = getDB()->prepare("SELECT saved_path FROM bctf_forms WHERE id=?");
$s->execute([(int)($_GET['id'] ?? 0)]);
$row = $s->fetch();
if (!$row) { http_response_code(404); exit('Not found.'); }

$path = BCTF_FORMS_DIR . basename($row['saved_path']);
if (!file_exists($path)) { http_response_code(404); exit('File missing.'); }

$mimeMap = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png',
            'webp'=>'image/webp','heic'=>'image/heic'];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline');
readfile($path);
