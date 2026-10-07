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

// Inserts a row and mints an upload token, so it is a state change. Reported as
// JSON rather than through csrfCheck()'s plain-text exit, which the caller's
// r.json() would choke on and report as "could not reach the server".
if (!csrfValid()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Could not verify the request. Reload the page.']);
    exit;
}

expEnsureTables();

$db      = getDB();

// Each press of Phone Upload leaves a placeholder behind, and placeholders are
// filtered out of the dashboard, so without this they pile up where nobody can
// see them. Same sweep lp-create-draft.php does.
try {
    $stale = $db->query(
        "SELECT id FROM exp_expenses
         WHERE description='(draft)' AND status='draft'
           AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($stale) {
        $ph = implode(',', array_fill(0, count($stale), '?'));
        // Children first: nothing enforces this for us, and a token that
        // outlives its expense resolves to a bare 404 rather than saying the
        // link has expired.
        $db->prepare("DELETE FROM exp_upload_tokens    WHERE expense_id IN ($ph)")->execute($stale);
        $db->prepare("DELETE FROM exp_pending_receipts WHERE expense_id IN ($ph)")->execute($stale);
        $db->prepare("DELETE FROM exp_expenses        WHERE id IN ($ph)")->execute($stale);
    }
} catch (Exception $e) {}
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
