<?php
/**
 * lp-export-receipts.php — Bundle all receipts for an LP grant into a ZIP
 *
 * Click-to-download: gathers every uploaded receipt for the given grant,
 * renames each to a readable date/description, and adds a summary.csv
 * so the whole package can be forwarded to the BCTF in one shot.
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

$grantId = (int)($_GET['grant_id'] ?? 0);
if (!$grantId) { http_response_code(400); exit('Invalid grant.'); }

$grantStmt = getDB()->prepare("SELECT * FROM lp_grants WHERE id=?");
$grantStmt->execute([$grantId]);
$grant = $grantStmt->fetch();
if (!$grant) { http_response_code(404); exit('Grant not found.'); }

$expenses = lpGetExpensesByGrant($grantId);

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZIP support is not available on this server.');
}

$safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $grant['name']);
$zipName  = 'BVTU-' . $safeName . '-Receipts-' . $grant['year'] . '.zip';
$tmpZip   = tempnam(sys_get_temp_dir(), 'lpzip');

$zip = new ZipArchive();
$zip->open($tmpZip, ZipArchive::OVERWRITE);

$csvRows   = [];
$csvRows[] = ['Date', 'Voucher', 'Description', 'Travel (km)', 'Travel $', 'Meals $', 'Gifts $', 'Misc $', 'Office $', 'Phone $', 'Total $', 'Receipt File'];

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

    $csvRows[] = [
        $e['expense_date'],
        ($e['voucher_number'] ? '#' . $e['voucher_number'] . ' — ' : '') . $e['voucher_name'],
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
    ];
}

$csvRows[] = [];
$csvRows[] = ['', '', '', '', '', '', '', '', '', 'GRAND TOTAL', number_format($grandTotal, 2), ''];

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
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
unlink($tmpZip);
