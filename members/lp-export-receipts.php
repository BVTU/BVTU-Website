<?php
/**
 * lp-export-receipts.php — Bundle LP receipts into a ZIP
 *
 * Two modes:
 *   ?grant_id=N    every receipt filed against a BCTF grant, for forwarding
 *                  the whole package to the BCTF in one shot. President only.
 *   ?voucher_id=N  every receipt attached to one voucher, so a signer can read
 *                  them offline. Owner, President, Treasurer or VP.
 *
 * Either way each file is renamed to a readable date/description and a
 * summary.csv is included.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
requireLogin();

$member = getMember();
lpEnsureTables();

$voucherId = (int)($_GET['voucher_id'] ?? 0);
$grantId   = (int)($_GET['grant_id']   ?? 0);

if ($voucherId) {
    // Per-voucher: whoever may review the voucher may bundle its receipts.
    // Mirrors lp-receipt.php — a signer can't approve what they can't read.
    $voucher = lpGetVoucher($voucherId);
    if (!$voucher) { http_response_code(404); exit('Voucher not found.'); }

    $isOwner = $voucher['submitted_by_email'] === $member['email'];
    if (!$isOwner && !lpCanView($member['email']) && !lpCanReview($member['email'])) {
        http_response_code(403);
        exit('Access denied.');
    }

    $expenses = lpGetExpenses($voucherId);
    $label    = ($voucher['voucher_number'] ? $voucher['voucher_number'] . '-' : '')
              . $voucher['name'];
    $zipStem  = 'Voucher-' . $label;

    // One voucher, so naming the voucher on every row is noise — the grant and
    // budget line the signer is actually checking against are more useful.
    $contextHeaders = ['Grant', 'Budget Line'];
    $contextCells   = function (array $e): array {
        return [$e['grant_name'] ?? '', $e['budget_line_name'] ?? ''];
    };

} elseif ($grantId) {
    if (!lpCanView($member['email'])) {
        header('Location: lp-dashboard.php');
        exit;
    }

    $grantStmt = getDB()->prepare("SELECT * FROM lp_grants WHERE id=?");
    $grantStmt->execute([$grantId]);
    $grant = $grantStmt->fetch();
    if (!$grant) { http_response_code(404); exit('Grant not found.'); }

    $expenses = lpGetExpensesByGrant($grantId);
    $zipStem  = $grant['name'] . '-Receipts-' . $grant['year'];

    $contextHeaders = ['Voucher'];
    $contextCells   = function (array $e): array {
        return [($e['voucher_number'] ? '#' . $e['voucher_number'] . ' — ' : '')
                . ($e['voucher_name'] ?? '')];
    };

} else {
    http_response_code(400);
    exit('Specify a voucher or a grant.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZIP support is not available on this server.');
}

$safeName = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $zipStem), '-');
$zipName  = 'BVTU-' . ($safeName !== '' ? $safeName : 'Receipts') . '.zip';
$tmpZip   = tempnam(sys_get_temp_dir(), 'lpzip');

$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::OVERWRITE);

$csvRows   = [];
$csvRows[] = array_merge(
    ['Date'], $contextHeaders,
    ['Description', 'Travel (km)', 'Travel $', 'Meals $', 'Gifts $', 'Misc $',
     'Office $', 'Phone $', 'Total $', 'Receipt File']
);

$usedNames = [];
$grandTotal = 0;

foreach ($expenses as $e) {
    $rowTotal = (float)$e['travel_amt'] + (float)$e['meals'] + (float)$e['gifts']
              + (float)$e['misc'] + (float)$e['office'] + (float)$e['phone'];
    $grandTotal += $rowTotal;

    $receiptLabel = '';

    if (!empty($e['receipt_path'])) {
        $diskFile = LP_RECEIPTS_DIR . basename($e['receipt_path']);
        if (file_exists($diskFile)) {
            $ext = strtolower(pathinfo($diskFile, PATHINFO_EXTENSION));
            $descSlug = preg_replace('/[^A-Za-z0-9]+/', '-', trim((string)$e['description']));
            $descSlug = trim($descSlug, '-');
            if ($descSlug === '') $descSlug = 'receipt';
            $descSlug = substr($descSlug, 0, 40);

            $base = ($e['expense_date'] ?: 'undated') . '_' . $descSlug;
            $entryName = $base . '.' . $ext;

            // Avoid collisions if two expenses share the same date/description
            $n = 1;
            while (isset($usedNames[$entryName])) {
                $entryName = $base . '-' . (++$n) . '.' . $ext;
            }
            $usedNames[$entryName] = true;

            $zip->addFile($diskFile, 'Receipts/' . $entryName);
            $receiptLabel = $entryName;
        } else {
            $receiptLabel = '(file missing)';
        }
    } else {
        $receiptLabel = '(no receipt)';
    }

    $csvRows[] = array_merge(
        [$e['expense_date']],
        $contextCells($e),
        [
        $e['description'],
        $e['travel_km'] > 0 ? $e['travel_km'] : '',
        $e['travel_amt'] > 0 ? number_format((float)$e['travel_amt'], 2) : '',
        $e['meals']      > 0 ? number_format((float)$e['meals'], 2)      : '',
        $e['gifts']      > 0 ? number_format((float)$e['gifts'], 2)      : '',
        $e['misc']       > 0 ? number_format((float)$e['misc'], 2)       : '',
        $e['office']     > 0 ? number_format((float)$e['office'], 2)     : '',
        $e['phone']      > 0 ? number_format((float)$e['phone'], 2)      : '',
        number_format($rowTotal, 2),
        $receiptLabel,
        ]
    );
}

$csvRows[] = [];
$totalRow = array_fill(0, count($csvRows[0]), '');
$totalRow[count($totalRow) - 3] = 'GRAND TOTAL';
$totalRow[count($totalRow) - 2] = number_format($grandTotal, 2);
$csvRows[] = $totalRow;

$csvHandle = fopen('php://temp', 'w+');
foreach ($csvRows as $row) {
    fputcsv($csvHandle, $row);
}
rewind($csvHandle);
$csvContent = stream_get_contents($csvHandle);
fclose($csvHandle);

$zip->addFromString('summary.csv', $csvContent);
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
// tempnam() created this file at 0 bytes and PHP caches that stat, so
// filesize() can report 0 after it has been written — the browser then
// saves a truncated or empty download.
clearstatcache(true, $tmpZip);
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
unlink($tmpZip);
