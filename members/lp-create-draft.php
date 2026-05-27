<?php
/**
 * lp-create-draft.php — Creates a minimal draft voucher so a QR upload
 * token can be issued before the full form is submitted.
 * Called via fetch() from lp-voucher-new.php when "📱 Phone Upload" is clicked.
 * Returns JSON: { ok, voucher_id, mobile_url }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$member = getMember();
if (!lpCanCreate($member['email'])) {
    http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Access denied']); exit;
}

lpEnsureTables();

// Clean up abandoned drafts older than 24 hours (belt-and-suspenders hygiene)
try {
    getDB()->prepare(
        "DELETE FROM lp_vouchers WHERE name='(draft)' AND status='draft'
         AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    )->execute([]);
} catch (Exception $e) {}

// Create the draft voucher
$db = getDB();
$db->prepare(
    "INSERT INTO lp_vouchers (name, submitted_by, submitted_by_email, year, mileage_rate, status)
     VALUES ('(draft)', ?, ?, ?, ?, 'draft')"
)->execute([$member['name'], $member['email'], lpCurrentYear(), LP_MILEAGE_RATE]);

$voucherId = (int)$db->lastInsertId();

$token     = lpCreateUploadToken($voucherId, $member['email']);
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'new.bvtu.ca';
$mobileUrl = "{$protocol}://{$host}/members/lp-mobile-receipt.php?token={$token}";

echo json_encode(['ok' => true, 'voucher_id' => $voucherId, 'mobile_url' => $mobileUrl]);
