<?php
/**
 * exp-create-draft.php — Creates a minimal draft expense for the phone QR receipt flow
 *
 * POST (login required)
 * Returns JSON: { ok, expense_id, mobile_url }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

header('Content-Type: application/json');

requireLogin();
$member = getMember();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

expEnsureTables();

$db      = getDB();
$refCode = expGenerateRefCode();

$db->prepare(
    "INSERT INTO exp_expenses
     (ref_code, user_email, user_name, expense_date, category, amount, description, status)
     VALUES (?,?,?,CURDATE(),'other',0.00,'(draft)','draft')"
)->execute([$refCode, strtolower(trim($member['email'])), $member['name']]);

$expenseId  = (int)$db->lastInsertId();
$token      = expCreateUploadToken($expenseId, $member['email']);

$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'bvtu.ca';
$mobileUrl = "{$protocol}://{$host}/members/exp-mobile-receipt.php?token={$token}";

echo json_encode([
    'ok'         => true,
    'expense_id' => $expenseId,
    'mobile_url' => $mobileUrl,
]);
