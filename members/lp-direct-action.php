<?php
/**
 * lp-direct-action.php — record or remove a direct expense against a grant.
 *
 * A direct expense is money the union paid straight out — a cheque to a speaker,
 * a venue deposit — that never appears on a President's voucher but still spends
 * a grant. Without it the grant total read low and the BCTF claim went in short.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';

requireLogin();
$member = getMember();
lpEnsureTables();
lpDirectEnsure();

// Same read gate as the page it posts from: President or Treasurer. Both handle
// the cheques this records.
if (!lpCanRecordDirect($member['email'])) { header('Location: dashboard.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: lp-dashboard.php'); exit; }

/*
 * A body over post_max_size arrives empty — no $_POST, no $_FILES — so the CSRF
 * check would fail first and report "could not be verified", which is both wrong
 * and unfixable from the user's side.
 */
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    header('Location: lp-dashboard.php?error=' . urlencode(
        'That receipt was too big for the server (its limit is '
        . ini_get('post_max_size') . '). Nothing was saved.'));
    exit;
}
csrfCheck();

$year   = (int)($_POST['year'] ?? lpCurrentYear());
$back   = 'lp-dashboard.php?year=' . $year;
$action = $_POST['action'] ?? '';

if ($year !== lpCurrentYear() || lpYearIsClosed($year)) {
    header('Location: ' . $back . '&error=' . urlencode(
        'That school year is closed. Direct payments can only be recorded against the open year.'));
    exit;
}

function lpDirectBack(string $back, string $key, string $msg): void {
    header('Location: ' . $back . '&' . $key . '=' . urlencode($msg));
    exit;
}

if ($action === 'delete') {
    $row = lpGetDirectExpense((int)($_POST['id'] ?? 0));
    if ($row && (int)$row['year'] !== $year) {
        lpDirectBack($back, 'error', 'That entry belongs to another school year.');
    }
    if ($row && lpDeleteDirectExpense((int)$row['id'])) {
        // The file stays on disk: it may be the only copy of that receipt, and
        // a mis-click should not destroy it.
        lpDirectBack($back, 'notice', 'Removed "' . $row['payee'] . '" from the grant total.');
    }
    lpDirectBack($back, 'error', 'That entry could not be removed.');
}

/*
 * Adding is retired. Everything now goes through the expense portal, so money
 * paid to somebody outside the union is a voucher with two signatures rather
 * than a line typed in afterwards. Refused here and not merely hidden on the
 * page: a bookmarked form, a stale tab or a back-button resubmit would
 * otherwise keep writing rows nobody signed for.
 *
 * Removing still works. The rows already recorded are part of the year's
 * figures, and clearing a mistaken one has to stay possible.
 */
if ($action === 'add') {
    lpDirectBack($back, 'error',
        'Logging a payment afterwards has been retired — everything goes through the '
      . 'expense portal now. Start a voucher and tick "Pay a vendor instead of me", '
      . 'and it gets two signatures before the money moves.');
}

// Nothing else reaches here: 'delete' and 'add' both exit above.
header('Location: ' . $back);
exit;
