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

if ($action !== 'add') { header('Location: ' . $back); exit; }

// ── Check the figures before touching the file ───────────────────────────
// Moving the upload first and validating second leaves an orphan on disk every
// time somebody forgets to pick a grant.
$amountIn = round((float)($_POST['amount'] ?? 0), 2);
$payeeIn  = trim((string)($_POST['payee'] ?? ''));
$grantIn  = (int)($_POST['grant_id'] ?? 0);
$lineIn   = (int)($_POST['budget_line_id'] ?? 0);
if ($payeeIn === '' || $amountIn <= 0 || (!$grantIn && !$lineIn)) {
    lpDirectBack($back, 'error',
        'Needs a payee, an amount above zero, and a grant or budget line to count against.');
}

// ── The receipt, when one came with it ────────────────────────────────────
$savedName = null;
$origName  = null;
if (!empty($_FILES['receipt']['name'])) {
    $f = $_FILES['receipt'];
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        lpDirectBack($back, 'error', $f['error'] === UPLOAD_ERR_INI_SIZE
            ? 'That receipt is larger than the server accepts (' . ini_get('upload_max_filesize') . ').'
            : 'The receipt did not upload. Nothing was saved.');
    }
    if ($f['size'] > 15 * 1024 * 1024) {
        lpDirectBack($back, 'error', 'That receipt is over 15 MB.');
    }

    /*
     * The saved extension comes from the file's content, never from the name the
     * browser sent — a real JPEG called "shell.php" would otherwise land as a
     * .php in the web root. Same rule as lp-scan.php.
     */
    $byMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
               'image/gif' => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heif',
               'application/pdf' => 'pdf'];
    $okExt  = ['jpg','jpeg','png','webp','gif','heic','heif','pdf'];
    $origName = basename($f['name']);
    $mime     = @mime_content_type($f['tmp_name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (isset($byMime[$mime]))            $ext = $byMime[$mime];
    elseif (!in_array($ext, $okExt, true)) {
        lpDirectBack($back, 'error', 'That file type is not accepted — use a photo or a PDF.');
    }

    $savedName = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], LP_RECEIPTS_DIR . $savedName)) {
        lpDirectBack($back, 'error', 'The receipt could not be saved. Nothing was recorded.');
    }
}

$id = lpAddDirectExpense([
    'year'           => $year,
    'grant_id'       => $_POST['grant_id']       ?? 0,
    'budget_line_id' => $_POST['budget_line_id'] ?? 0,
    'spent_on'       => $_POST['spent_on']       ?? '',
    'payee'          => $_POST['payee']          ?? '',
    'description'    => $_POST['description']    ?? '',
    'amount'         => $_POST['amount']         ?? 0,
    'cheque_ref'     => $_POST['cheque_ref']     ?? '',
    'receipt_path'     => $savedName,
    'receipt_filename' => $origName,
], $member['email']);

if (!$id) {
    // Validated above, so this is a database failure; do not leave the file behind.
    if ($savedName && is_file(LP_RECEIPTS_DIR . $savedName)) @unlink(LP_RECEIPTS_DIR . $savedName);
    lpDirectBack($back, 'error', 'That could not be saved. The problem has been logged.');
}
lpDirectBack($back, 'notice', 'Recorded — it now counts toward that total.');
