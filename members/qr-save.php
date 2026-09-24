<?php
/**
 * qr-save.php — AJAX: remember a QR code that was just made or downloaded.
 * POST content, label, ec, margin, csrf_token
 *
 * Its own endpoint rather than a form post on the page, because saving happens
 * as a side effect of downloading and a full page reload there would be a
 * surprise mid-download. The caller shows what this returns — a save that
 * quietly failed would leave the list looking like it lost someone's code.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/qr-db.php';

header('Content-Type: application/json; charset=utf-8');

// isLoggedIn(), not requireLogin(): the latter redirects to login.php, which a
// fetch follows and resolves as login HTML at status 200 — success, to the caller.
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}
$member = getMember();
if (!execIsAdmin($member['email'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not allowed']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
if (!csrfValid()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Could not verify the request. Reload the page.']);
    exit;
}

$content = trim((string)($_POST['content'] ?? ''));
if ($content === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nothing to save']);
    exit;
}
// The column holds 2000; refuse rather than let MySQL truncate a code silently.
if (mb_strlen($content) > 2000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'That text is too long to save.']);
    exit;
}

$label = trim((string)($_POST['label'] ?? ''));
if (mb_strlen($label) > 255) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'That label is too long (255 characters maximum).']);
    exit;
}

try {
    qrSave($content, $label, (string)($_POST['ec'] ?? 'M'),
           (int)($_POST['margin'] ?? 4), $member['email']);
} catch (\PDOException $e) {
    error_log('qr-save: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save that code.']);
    exit;
}

echo json_encode(['ok' => true]);
