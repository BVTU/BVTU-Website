<?php
/**
 * lp-grant-action.php — POST-only handler for grant-level "submitted to BCTF" tracking
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';

requireLogin();
$member = getMember();
lpEnsureTables();

if (!lpCanView($member['email'])) {
    header('Location: lp-dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lp-dashboard.php');
    exit;
}

// Records that a grant's receipts went to the BCTF — the rest of this workflow
// now checks, and leaving one handler out is how a sweep ends up half done.
csrfCheck();

$action  = $_POST['action']   ?? '';
$grantId = (int)($_POST['grant_id'] ?? 0);
$note    = trim($_POST['note'] ?? '');

/* Ticking an item in or out of a grant's total, from the grant's list. Answers
 * JSON: the list stays open and reloads onto itself. The grant tools' own gate
 * (execIsAdmin), not lpCanView's, which also admits the Treasurer read-only. */
if ($action === 'set_counted') {
    header('Content-Type: application/json');
    try {
        if (!execIsAdmin($member['email'])) throw new RuntimeException('Access denied.');
        if (!$grantId) throw new RuntimeException('Invalid grant.');
        lpSetGrantCounted((string)($_POST['expense'] ?? ''), $grantId, ($_POST['counted'] ?? '') === '1');
        echo json_encode(['ok' => true]);
    } catch (RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

try {
    if (!$grantId) throw new RuntimeException('Invalid grant.');

    switch ($action) {

        case 'mark_submitted':
            lpMarkGrantSubmitted($grantId, $member['email'], $member['name'], $note);
            $msg = 'Grant marked as submitted to BCTF.';
            break;

        case 'unmark_submitted':
            lpUnmarkGrantSubmitted($grantId);
            $msg = 'Submission record removed.';
            break;

        default:
            throw new RuntimeException('Unknown action.');
    }

    header('Location: lp-dashboard.php?notice=' . urlencode($msg));
    exit;

} catch (RuntimeException $e) {
    header('Location: lp-dashboard.php?error=' . urlencode($e->getMessage()));
    exit;
}
