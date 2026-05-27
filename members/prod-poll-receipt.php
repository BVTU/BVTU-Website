<?php
/**
 * prod-poll-receipt.php — Returns the latest unclaimed pending receipt for a request.
 * GET ?request_id=X
 * Returns JSON: { receipt: {id, saved_path, original_name, preview_url, scan_data} | null }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit;
}

$requestId = (int)($_GET['request_id'] ?? 0);
if (!$requestId) {
    http_response_code(400); echo json_encode(['error' => 'Missing request_id']); exit;
}

prodEnsureTables();

$member = getMember();

// Verify the logged-in teacher owns this request (or is exec)
$s = getDB()->prepare("SELECT id, user_email FROM prod_requests WHERE id=? LIMIT 1");
$s->execute([$requestId]);
$req = $s->fetch();

if (!$req) {
    http_response_code(404); echo json_encode(['error' => 'Request not found']); exit;
}

if ($req['user_email'] !== $member['email'] && !prodIsExec($member['email'])) {
    http_response_code(403); echo json_encode(['error' => 'Access denied']); exit;
}

$row = prodGetPendingReceipt($requestId);

if (!$row) {
    echo json_encode(['receipt' => null]);
    exit;
}

echo json_encode([
    'receipt' => [
        'id'            => (int)$row['id'],
        'saved_path'    => $row['saved_path'],
        'original_name' => $row['original_name'],
        'preview_url'   => 'prod-receipt.php?f=' . urlencode($row['saved_path']),
        'scan_data'     => $row['scan_data'],
        'created_at'    => $row['created_at'],
    ],
]);
