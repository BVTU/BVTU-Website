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

$action  = $_POST['action']   ?? '';
$grantId = (int)($_POST['grant_id'] ?? 0);
$note    = trim($_POST['note'] ?? '');

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
