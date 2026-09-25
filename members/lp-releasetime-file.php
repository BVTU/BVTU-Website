<?php
/**
 * lp-releasetime-file.php — Serve a release time invoice, auth-gated.
 *
 * The files live outside the web root's reach (LP_RT_DIR carries a deny rule),
 * so this is the only way in. Same gate as the tool itself: president only.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/lp-releasetime-db.php';
requireLogin();

$member = getMember();
if (!lpCanView($member['email'])) { http_response_code(403); exit('Access denied.'); }

$path = basename($_GET['f'] ?? '');
if ($path === '') { http_response_code(400); exit('Invalid request.'); }

// Only serve a file some invoice actually claims, so a guessed name gets
// nothing even from someone who may read the rest of the tool.
lpRtEnsureTables();
$s = getDB()->prepare("SELECT original_name FROM lp_rt_invoices WHERE file_path=? LIMIT 1");
$s->execute([$path]);
$row = $s->fetch();
if (!$row) { http_response_code(404); exit('File not found.'); }

$full = LP_RT_DIR . $path;
if (!is_file($full)) { http_response_code(404); exit('File not found.'); }

$mime = function_exists('mime_content_type') ? mime_content_type($full) : 'application/octet-stream';
$safe = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
// Anything else is downloaded rather than rendered — an unexpected type should
// not be handed to the browser to interpret.
$inline = in_array($mime, $safe, true);

clearstatcache(true, $full);
header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
header('Content-Length: ' . filesize($full));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
       . '; filename="' . str_replace('"', '', $row['original_name'] ?: $path) . '"');
header('X-Content-Type-Options: nosniff');
readfile($full);
