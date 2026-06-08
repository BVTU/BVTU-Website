<?php
/**
 * exp-claim-action.php — POST-only workflow handler for multi-item expense claims
 *
 * Mirrors lp-action.php: one form action per workflow step, whitelisted
 * redirect back to either the review queue or the claim detail page.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();
expBatchEnsureTables();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: exp-dashboard.php');
    exit;
}

$action   = $_POST['action']   ?? '';
$batchId  = (int)($_POST['batch_id'] ?? 0);
$note     = trim($_POST['note'] ?? '');
$redirect = $_POST['redirect'] ?? 'exp-claim-review.php';

// Whitelist redirect targets
if (!preg_match('#^exp-claim-(review|view)\.php(\?.*)?$#', $redirect)) {
    $redirect = 'exp-claim-review.php';
}

try {
    if (!$batchId) throw new RuntimeException('Invalid claim.');
    $b = expBatchGet($batchId);
    if (!$b) throw new RuntimeException('Claim not found.');

    $items = expBatchGetItems($batchId);
    $total = expBatchTotal($batchId);

    switch ($action) {

        case 'submit':
            if (strtolower($b['user_email']) !== strtolower($member['email'])
                && strtolower(trim($b['submitted_by_email'] ?? '')) !== strtolower($member['email'])
                && !expCanSubmitOnBehalf($member['email'])) {
                throw new RuntimeException('You do not have permission to submit this claim.');
            }
            if (count($items) === 0) {
                throw new RuntimeException('Add at least one expense item before submitting.');
            }
            expBatchSubmit($batchId);
            $b = expBatchGet($batchId);
            expBatchEmailSubmitted($b, $total, count($items));
            $msg = 'Claim submitted for Treasurer approval. They have been notified.';
            break;

        case 'signer1_approve':
            if (!expIsTreasurer($member['email'])) {
                throw new RuntimeException('Only the Treasurer can give first approval.');
            }
            expBatchApproveAsSigner1($batchId, $member['email'], $member['name'], $note);
            $b = expBatchGet($batchId);
            expBatchEmailSigner1Approved($b, $total, count($items));
            $msg = 'Claim approved. The second signer has been notified.';
            break;

        case 'signer2_approve':
            if (!expIsEligibleSigner2($member['email'])) {
                throw new RuntimeException('You are not an eligible second signer for this claim.');
            }
            expBatchApproveAsSigner2($batchId, $member['email'], $member['name'], $note);
            $b = expBatchGet($batchId);
            expBatchEmailSigner2Approved($b, $total, count($items));
            $msg = 'Claim approved. The Treasurer has been notified to send the e-transfer.';
            break;

        case 'reject':
            if (!expIsTreasurer($member['email']) && !expIsEligibleSigner2($member['email'])) {
                throw new RuntimeException('You do not have permission to reject this claim.');
            }
            if (!$note) throw new RuntimeException('Please provide a reason for rejection.');
            expBatchReject($batchId, $member['email'], $member['name'], $note);
            $b = expBatchGet($batchId);
            expBatchEmailRejected($b);
            $msg = 'Claim rejected. The member has been notified.';
            break;

        case 'mark_paid':
            if (!expIsTreasurer($member['email'])) {
                throw new RuntimeException('Only the Treasurer can mark a claim as paid.');
            }
            expBatchMarkPaid($batchId, $member['email'], $member['name'], $note);
            $b = expBatchGet($batchId);
            expBatchEmailPaid($b, $total);
            $msg = 'Claim marked as paid. The member has been notified.';
            break;

        default:
            throw new RuntimeException('Unknown action.');
    }

    header('Location: ' . $redirect . (strpos($redirect, '?') === false ? '?' : '&') . 'notice=' . urlencode($msg));
    exit;

} catch (RuntimeException $e) {
    header('Location: ' . $redirect . (strpos($redirect, '?') === false ? '?' : '&') . 'error=' . urlencode($e->getMessage()));
    exit;
}
