<?php
/**
 * lp-action.php — POST-only LP voucher workflow handler
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/lp-db.php';

requireLogin();
$member = getMember();
lpEnsureTables();
lpEnsureApprovalColumns();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lp-dashboard.php');
    exit;
}

$action    = $_POST['action']     ?? '';
$voucherId = (int)($_POST['voucher_id'] ?? 0);
$note      = trim($_POST['note'] ?? '');
$redirect  = $_POST['redirect'] ?? 'lp-dashboard.php';

// Whitelist redirect
if (!preg_match('#^lp-[a-z\-]+\.php(\?.*)?$#', $redirect)) {
    $redirect = 'lp-dashboard.php';
}

try {
    if (!$voucherId) throw new RuntimeException('Invalid voucher.');
    $v = lpGetVoucher($voucherId);
    if (!$v) throw new RuntimeException('Voucher not found.');

    // Calculate total for email notifications
    $expenses = lpGetExpenses($voucherId);
    $total = array_sum(array_map('lpRowTotal', $expenses));

    switch ($action) {

        case 'submit':
            if (!lpCanCreate($member['email'])) {
                throw new RuntimeException('Only the Local President can submit vouchers.');
            }
            if (count($expenses) === 0) {
                throw new RuntimeException('Add at least one expense before submitting.');
            }
            lpSubmitVoucher($voucherId);
            lpEmailSubmitted($v, $total);
            $msg = 'Voucher submitted for Treasurer approval. They have been notified.';
            break;

        case 'treasurer_approve':
            if (!lpCanSign1($member['email'])) {
                throw new RuntimeException('Only the BVTU Treasurer can give first approval.');
            }
            lpApproveAsSigner1($voucherId, $member['email'], $member['name'], $note);
            $v = lpGetVoucher($voucherId); // reload
            lpEmailTreasurerApproved($v, $total);
            $msg = 'Voucher approved. VP has been notified for second signature.';
            break;

        case 'vp_approve':
            if (!lpCanSign2($member['email'])) {
                throw new RuntimeException('Only the Vice-President can give second approval.');
            }
            lpApproveAsSigner2($voucherId, $member['email'], $member['name'], $note);
            $v = lpGetVoucher($voucherId); // reload
            lpEmailVPApproved($v, $total);
            $msg = 'Voucher approved. Treasurer notified to issue e-transfer.';
            break;

        case 'reject':
            if (!lpCanSign1($member['email']) && !lpCanSign2($member['email'])) {
                throw new RuntimeException('You do not have permission to reject this voucher.');
            }
            if (!$note) throw new RuntimeException('Please provide a reason for rejection.');
            lpRejectVoucher($voucherId, $member['email'], $member['name'], $note);
            $v = lpGetVoucher($voucherId);
            lpEmailRejected($v);
            $msg = 'Voucher rejected. The Local President has been notified.';
            break;

        case 'mark_paid':
            if (!lpCanSign1($member['email'])) {
                throw new RuntimeException('Only the Treasurer can mark a voucher as paid.');
            }
            lpMarkPaid($voucherId, $member['email'], $member['name'], $note);
            $v = lpGetVoucher($voucherId);
            lpEmailPaid($v, $total);
            $msg = 'Voucher marked as paid. The Local President has been notified.';
            break;

        default:
            throw new RuntimeException('Unknown action.');
    }

    header('Location: ' . $redirect . '?notice=' . urlencode($msg));
    exit;

} catch (RuntimeException $e) {
    header('Location: ' . $redirect . '?error=' . urlencode($e->getMessage()));
    exit;
}
