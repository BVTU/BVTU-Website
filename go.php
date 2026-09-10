<?php
/**
 * go.php — Short link redirect handler
 * Accessed via /go/{slug} (rewritten by .htaccess)
 */
require_once __DIR__ . '/members/links-db.php';

linksEnsureTable();

$slug = trim($_GET['slug'] ?? '');

if (!$slug) {
    header('Location: /');
    exit;
}

$link = linksGetBySlug($slug);

if (!$link) {
    // No match — redirect to home with a fragment so the 404 is silent
    http_response_code(404);
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Link Not Found — BVTU</title>
  <link rel="stylesheet" href="/css/style.css">
  <link rel="icon" href="/favicon.ico">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh;
           background:#f4f6f8; font-family:var(--font,sans-serif); }
    .box { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:2.5rem 2rem;
           max-width:420px; text-align:center; }
    h1 { font-size:1.2rem; font-weight:800; color:#1a2e1a; margin-bottom:.5rem; }
    p { font-size:.9rem; color:#64748b; line-height:1.6; }
    a { color:#1a6b35; font-weight:600; }
  </style>
</head>
<body>
  <div class="box">
    <h1>Link not found</h1>
    <p>The short link <strong>/go/<?= htmlspecialchars($slug) ?></strong> doesn't exist or has been deactivated.</p>
    <p style="margin-top:1rem;"><a href="/">Return to bvtu.ca</a></p>
  </div>
</body>
</html>
<?php
    exit;
}

linksIncrementClick($link['id']);

// Redirect — use 302 so browsers don't cache in case the destination changes
header('Location: ' . $link['destination'], true, 302);
exit;
