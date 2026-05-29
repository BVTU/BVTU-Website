<?php
/**
 * exp-action.php — POST-only workflow action handler for expense reimbursements
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: exp-dashboard.php');
    exit;
}

$action   = $_POST['action']   ?? '';
$expId    = (int)($_POST['expense_id'] ?? 0);
$note     = trim($_POST['note'] ?? '');
$redirect = $_POST['redirect'] ?? 'exp-treasurer.php';

// Sanitise redirect — only allow relative paths in the members dir
if (!preg_match('/^[a-zA-Z0-9_\-]+\.php(\?[^<>"]*)?$/', $redirect)) {
    $redirect = 'exp-treasurer.php';
}

if (!$expId) {
    header('Location: ' . $redirect . '?error=' . urlencode('Missing expense ID'));
    exit;
}

// Re-fetch expense from DB on every action
$exp = expGet($expId);
if (!$exp) {
    header('Location: ' . $redirect . '?error=' . urlencode('Expense not found'));
    exit;
}

try {

    switch ($action) {

        case 'signer1_approve':
            if (!expIsTreasurer($member['email'])) {
                throw new RuntimeException('Only a Treasurer can approve as Signer 1.');
            }
            expApproveAsSigner1($expId, $member['email'], $member['name'], $note);
            $exp = expGet($expId);
            expEmailSigner1Approved($exp);
            $msg = 'Expense approved as Signer 1 (Treasurer).';
            break;

        case 'signer1_reject':
            if (!expIsTreasurer($member['email'])) {
                throw new RuntimeException('Only a Treasurer can reject at this stage.');
            }
            if (!$note) {
                throw new RuntimeException('A rejection note is required.');
            }
            expReject($expId, $member['email'], $member['name'], $note);
            $exp = expGet($expId);
            expEmailRejected($exp);
            $msg = 'Expense rejected.';
            break;

        case 'signer2_approve':
            if (!expIsEligibleSigner2($member['email'])) {
                throw new RuntimeException('Only a VP, President, or Admin can approve as Signer 2.');
            }
            expApproveAsSigner2($expId, $member['email'], $member['name'], $note);
            $exp = expGet($expId);
            expEmailSigner2Approved($exp);
            $msg = 'Expense approved as Signer 2.';
            break;

        case 'signer2_reject':
            if (!expIsEligibleSigner2($member['email']) && !expIsTreasurer($member['email'])) {
                throw new RuntimeException('You do not have permission to reject at this stage.');
            }
            if (!$note) {
                throw new RuntimeException('A rejection note is required.');
            }
            expReject($expId, $member['email'], $member['name'], $note);
            $exp = expGet($expId);
            expEmailRejected($exp);
            $msg = 'Expense rejected.';
            break;

        case 'mark_paid':
            if (!expIsTreasurer($member['email'])) {
                throw new RuntimeException('Only a Treasurer can mark an expense as paid.');
            }
            expMarkPaid($expId, $member['email'], $member['name'], $note);
            $exp = expGet($expId);
            expEmailPaid($exp);
            $msg = 'Expense marked as paid. Member has been notified.';
            break;

        case 'admin_override':
            if (!expIsAdmin($member['email'])) {
                throw new RuntimeException('Only an admin can override expense status.');
            }
            $newStatus = $_POST['new_status'] ?? '';
            $allowedStatuses = ['pending', 'signer1_approved', 'signer2_approved', 'paid', 'rejected', 'draft'];
            if (!in_array($newStatus, $allowedStatuses)) {
                throw new RuntimeException('Invalid status value.');
            }
            getDB()->prepare(
                "UPDATE exp_expenses SET status=?, rejection_note=? WHERE id=?"
            )->execute([
                $newStatus,
                $note ? '[Admin override] ' . $note : '[Admin override by ' . $member['name'] . ']',
                $expId,
            ]);
            $msg = 'Status overridden to "' . $newStatus . '".';
            break;

        default:
            throw new RuntimeException('Unknown action: ' . htmlspecialchars($action));
    }

} catch (RuntimeException $e) {
    header('Location: ' . $redirect . '?error=' . urlencode($e->getMessage()));
    exit;
}

header('Location: ' . $redirect . '?notice=' . urlencode($msg));
exit;
