<?php
/**
 * lp-archive-bundle.php — one school year as a single ZIP: every record, and
 * every receipt image behind it.
 *
 * Everything else in this portal lives in one Hostinger database. That is fine
 * until the account lapses, the database is corrupted, or the next President
 * inherits a login that no longer works. This is the one file that survives
 * those, so it holds the receipts themselves and not just rows that reference
 * them — a spreadsheet of expense totals with no images is not a record the
 * BCTF or an auditor would accept.
 *
 * Read-only: it writes nothing back.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/exp-db.php';
require_once __DIR__ . '/collab-grant-db.php';
require_once __DIR__ . '/xlsx-writer.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: lp-dashboard.php'); exit; }

lpEnsureTables();
expBatchEnsureTables();
cgEnsureTable();

$years = lpYearsWithData();
$w     = isset($_GET['year']) && is_scalar($_GET['year']) ? (int)$_GET['year'] : 0;
$year  = in_array($w, $years, true) ? $w : lpCurrentYear();
$label = lpYearLabel($year);

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('This server has no ZIP support, so the bundle cannot be built. Use the per-section exports on the archive page instead.');
}

// A year with many receipts takes a while to copy; the default 30s is not
// enough and a half-written ZIP is worse than a slow one.
@set_time_limit(300);

$grants   = lpGrantSummary($year);
$lines    = lpBudgetSummary($year);
$vouchers = lpGetVouchers('', '', $year);
$claims   = expBatchGetAll('', $year);
$collab   = cgGetApplications($year);

$tmp = tempnam(sys_get_temp_dir(), 'lpbundle');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);   // tempnam() already created it; do not leave it behind
    http_response_code(500);
    exit('Could not start the bundle. Try again, or use the per-section exports.');
}

/** A CSV as a string, with the same formula guard the downloads use. */
function bundleCsv(array $headers, array $rows): string {
    $fh = fopen('php://temp', 'r+');
    fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($fh, $headers);
    foreach ($rows as $r) fputcsv($fh, array_map('csvSafeText', $r));
    rewind($fh);
    $out = stream_get_contents($fh);
    fclose($fh);
    return $out;
}

$counts = [];
foreach (LP_ARCHIVE_SECTIONS as $key => $file) {
    list($headers, $rows) = archiveSection($key, $grants, $lines, $vouchers, $claims, $collab);
    $counts[$file] = count($rows);
    $zip->addFromString('Data/' . $file . '.csv', bundleCsv($headers, $rows));
}

/**
 * Copy receipts in, recording for each one whether the file was actually there.
 * A missing image is written into the manifest rather than passed over: the
 * point of this file is to be able to prove what was kept and what was not.
 */
$manifest = [['Section', 'Record', 'Description', 'File in bundle', 'Status']];
$used     = [];

function bundleName(array &$used, string $folder, string $base, string $ext): string {
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base);
    $base = trim($base, '-');
    if ($base === '') $base = 'receipt';
    $base = substr($base, 0, 60);
    $name = $folder . '/' . $base . ($ext ? '.' . $ext : '');
    $n = 1;
    while (isset($used[$name])) {
        $name = $folder . '/' . $base . '-' . (++$n) . ($ext ? '.' . $ext : '');
    }
    $used[$name] = true;
    return $name;
}

// President's vouchers
foreach ($vouchers as $v) {
    $ref = $v['voucher_number'] ?: ('voucher-' . $v['id']);
    try {
        $s = getDB()->prepare(
            "SELECT expense_date, description, receipt_path, receipt_filename
             FROM lp_expenses WHERE voucher_id=? ORDER BY sort_order, id"
        );
        $s->execute([(int)$v['id']]);
        $rows = $s->fetchAll();
    } catch (Exception $e) { $rows = []; }

    foreach ($rows as $e) {
        if (empty($e['receipt_path'])) continue;
        $disk = LP_RECEIPTS_DIR . basename($e['receipt_path']);
        $desc = trim((string)$e['description']);
        if (!file_exists($disk)) {
            $manifest[] = ["President's voucher", $ref, $desc, '', 'FILE MISSING ON SERVER'];
            continue;
        }
        $ext  = strtolower(pathinfo($disk, PATHINFO_EXTENSION));
        $name = bundleName($used, 'Receipts/Vouchers/' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $ref),
                           ($e['expense_date'] ?: 'undated') . '_' . $desc, $ext);
        $zip->addFile($disk, $name);
        $manifest[] = ["President's voucher", $ref, $desc, $name, 'included'];
    }
}

// Member claims
foreach ($claims as $b) {
    $ref = $b['ref_code'] ?: ('claim-' . $b['id']);
    try {
        $s = getDB()->prepare(
            "SELECT expense_date, description, receipt_path, receipt_filename
             FROM exp_batch_items WHERE batch_id=? ORDER BY id"
        );
        $s->execute([(int)$b['id']]);
        $rows = $s->fetchAll();
    } catch (Exception $e) { $rows = []; }

    foreach ($rows as $e) {
        if (empty($e['receipt_path'])) continue;
        $disk = EXP_RECEIPTS_DIR . basename($e['receipt_path']);
        $desc = trim((string)($e['description'] ?? ''));
        if (!file_exists($disk)) {
            $manifest[] = ['Member claim', $ref, $desc, '', 'FILE MISSING ON SERVER'];
            continue;
        }
        $ext  = strtolower(pathinfo($disk, PATHINFO_EXTENSION));
        $name = bundleName($used, 'Receipts/Claims/' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $ref),
                           (($e['expense_date'] ?? '') ?: 'undated') . '_' . $desc, $ext);
        $zip->addFile($disk, $name);
        $manifest[] = ['Member claim', $ref, $desc, $name, 'included'];
    }
}

$included = 0; $missing = 0;
foreach (array_slice($manifest, 1) as $m) {
    if ($m[4] === 'included') $included++; else $missing++;
}
$zip->addFromString('Data/receipt-manifest.csv', bundleCsv(array_shift($manifest), $manifest));

$readme = "BVTU — {$label} archive bundle\n"
    . str_repeat('=', 40) . "\n\n"
    . 'Created ' . date('j F Y, g:ia') . ' by ' . $member['email'] . "\n\n"
    . "WHAT THIS IS\n"
    . "A complete copy of one school year: every record, and every receipt image\n"
    . "behind it. It is self-contained on purpose — nothing here needs the BVTU\n"
    . "website, a login, or the database to be readable. Keep a copy somewhere\n"
    . "that is not the web host.\n\n"
    . "CONTENTS\n"
    . "  Data/grants.csv                 BCTF grants: budget, spent, remaining\n"
    . "  Data/budget-lines.csv           Local budget lines: budget, spent, remaining\n"
    . "  Data/vouchers.csv               President's expense vouchers\n"
    . "  Data/member-claims.csv          Member reimbursement claims\n"
    . "  Data/collaboration-grants.csv   Collaboration grant applications\n"
    . "  Data/receipt-manifest.csv       Every receipt, where it is in this file\n"
    . "  Receipts/Vouchers/<voucher>/    Receipt images, by voucher\n"
    . "  Receipts/Claims/<ref>/          Receipt images, by claim\n\n"
    . "COUNTS\n";
foreach ($counts as $f => $n) $readme .= sprintf("  %-30s %d\n", $f . '.csv', $n);
$readme .= sprintf("  %-30s %d\n", 'receipts included', $included);
if ($missing) {
    $readme .= sprintf("  %-30s %d\n", 'receipts MISSING', $missing)
        . "\nSome receipts are recorded in the database but their image files were\n"
        . "not on the server when this bundle was made. They are listed in\n"
        . "Data/receipt-manifest.csv with the status FILE MISSING ON SERVER.\n"
        . "This is reported rather than hidden so the gap is known now.\n";
}
$readme .= "\nFigures are as they stood when this bundle was made, not a frozen\n"
    . "snapshot taken at year end. A voucher approved later will differ.\n";

$zip->addFromString('README.txt', $readme);
$zip->close();

$name = 'bvtu-archive-' . $year . '-' . ($year + 1) . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $name . '"');
// tempnam() created this file at 0 bytes and PHP caches that stat, so
// filesize() can report 0 after it has been written — the browser then
// saves a truncated or empty download.
clearstatcache(true, $tmp);
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);
unlink($tmp);
