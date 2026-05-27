<?php
/**
 * lp-claim-receipt.php — Mark a pending receipt as claimed (assigned to a row).
 * POST: pending_id=N
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok' => false]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }

$id = (int)($_POST['pending_id'] ?? 0);
if ($id) lpClaimPendingReceipt($id);

echo json_encode(['ok' => true]);
