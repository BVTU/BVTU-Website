<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['ok' => false]); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false]); exit; }
$id = (int)($_POST['pending_id'] ?? 0);
if ($id) prodClaimPendingReceipt($id);
echo json_encode(['ok' => true]);
