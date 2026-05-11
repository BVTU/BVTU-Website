<?php
// ============================================================
//  newsletter-serve.php — Serves raw newsletter HTML inside
//  the iframe on newsletter.php. Auth-gated; members only.
// ============================================================
require_once __DIR__ . '/auth.php';
if (!isLoggedIn()) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;color:#666;">
    <p>Please <a href="login.php">log in</a> to view this newsletter.</p></body></html>';
    exit;
}
require_once __DIR__ . '/newsletter-db.php';

$id = (int)($_GET['id'] ?? 0);
$nl = $id ? nlGetNewsletter($id) : null;

if (!$nl || empty($nl['html_content'])) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;color:#666;">
    <p>Newsletter not found.</p></body></html>';
    exit;
}

// Output the stored Mailchimp HTML directly
// Content-Type is text/html; charset from the stored document is preserved
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: private, max-age=3600');

echo $nl['html_content'];
