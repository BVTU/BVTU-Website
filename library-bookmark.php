<?php
/**
 * library-bookmark.php — AJAX bookmark toggle
 * POST {id: int} → JSON {bookmarked: bool, count: int}
 */
require_once __DIR__ . '/members/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
require_once __DIR__ . '/members/library-db.php';

header('Content-Type: application/json');

$id     = (int)($_POST['id'] ?? 0);
$member = getMember();

if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

$resource = libGetResource($id);
if (!$resource || $resource['status'] !== 'published') {
    http_response_code(404); echo json_encode(['error' => 'Not found']); exit;
}

$bookmarked = libToggleBookmark($id, $member['email']);
echo json_encode(['bookmarked' => $bookmarked]);
exit;
