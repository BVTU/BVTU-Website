<?php
/**
 * lp-dupes.php — "haven't I already claimed this one?"
 *
 * Receipts get filed in folders by month and photographed off the kitchen
 * table, so the same one reaches a voucher twice: the same JPG dragged in from
 * October's folder in November, or the same paper slip photographed on two
 * different days. Neither is caught by anything else here — the same-bill merge
 * in the voucher editor deliberately looks for the OTHER half of a bill (an
 * itemized receipt and the card slip beside it), which is the opposite case.
 *
 * Two signals, and the difference between them matters more than either:
 *
 *   same_file     identical bytes. A fact, not a guess — the same file, so the
 *                 editor stops and asks before attaching it.
 *   same_receipt  same vendor, same day, same amount to the cent, different
 *                 bytes. Probably the same slip photographed twice, but two
 *                 identical coffees on one day is a real thing that happens, so
 *                 this one only ever says so and lets the claim through.
 *
 * Scope is one school year of the President's vouchers. Member claims and
 * payments recorded outside a voucher are deliberately not searched: this is a
 * reminder, not an audit, and a reminder that reaches across systems would need
 * to be right about far more than bytes.
 */
require_once __DIR__ . '/lp-db.php';

/**
 * The columns the two signals are read from.
 *
 * receipt_sha256 is computed on the server from the file on disk, never taken
 * from the browser — a hash the client supplies proves nothing. The scan_*
 * columns are what the scanner read off the receipt, kept because they are
 * currently thrown away on save: the editor's merge computes exactly this
 * fingerprint in the browser and loses it when the page reloads.
 */
function lpDupeEnsureColumns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cols = [
        'receipt_sha256' => 'VARCHAR(64) DEFAULT NULL',
        'scan_vendor'    => 'VARCHAR(255) DEFAULT NULL',
        'scan_date'      => 'DATE DEFAULT NULL',
        'scan_total'     => 'DECIMAL(10,2) DEFAULT NULL',
    ];
    foreach ($cols as $col => $type) {
        try {
            $exists = getDB()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lp_expenses'
                   AND COLUMN_NAME='{$col}'"
            )->fetchColumn();
            if (!$exists) getDB()->exec("ALTER TABLE lp_expenses ADD COLUMN {$col} {$type}");
        } catch (Exception $e) { error_log('lpDupeEnsureColumns ' . $col . ': ' . $e->getMessage()); }
    }
    // Every lookup is by hash, and a voucher year can hold a few hundred rows.
    try {
        $idx = getDB()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lp_expenses'
               AND INDEX_NAME='idx_receipt_sha'"
        )->fetchColumn();
        if (!$idx) getDB()->exec("CREATE INDEX idx_receipt_sha ON lp_expenses (receipt_sha256)");
    } catch (Exception $e) { error_log('lpDupeEnsureColumns index: ' . $e->getMessage()); }
}

/**
 * The PHP twin of normVendor() in the voucher editor. The two must agree or a
 * fingerprint written by the browser will not match one written here.
 */
function lpDupeNormVendor($v): string {
    $v = strtolower(trim((string)$v));
    $v = preg_replace('/[^a-z0-9]+/', ' ', $v);
    return trim((string)$v);
}

/**
 * The hash to store for a row, which is not quite the same question.
 *
 * null means "no receipt on this row". The empty string means "a receipt is
 * recorded but its file could not be read" — a real state worth keeping, and
 * distinct from not having looked yet, which is what NULL means to the
 * backfill. Writing null in that case would hand the row back to the backfill
 * to re-examine on every single upload, for ever.
 */
function lpDupeHashForSave(?string $savedName): ?string {
    $savedName = trim((string)$savedName);
    if ($savedName === '') return null;
    return lpDupeHashOf($savedName) ?? '';
}

/** The hash of a stored receipt, or null when the file is not there to read. */
function lpDupeHashOf(?string $savedName): ?string {
    $savedName = trim((string)$savedName);
    if ($savedName === '') return null;
    $disk = LP_RECEIPTS_DIR . basename($savedName);
    if (!is_file($disk) || !is_readable($disk)) return null;
    $h = @hash_file('sha256', $disk);
    return $h ?: null;
}

/**
 * Hash the receipts that were uploaded before this feature existed.
 *
 * Without it the first duplicate anyone hits would be compared against a table
 * of nulls and pass silently, which is worse than no check at all because the
 * check appears to be running. Called only from the upload paths, capped, and
 * self-limiting: once a row has a hash it is never looked at again, and a row
 * whose file has gone missing gets the empty string so it stops being retried
 * on every upload forever.
 */
function lpDupeBackfill(int $limit = 300): void {
    lpDupeEnsureColumns();
    try {
        $s = getDB()->prepare(
            "SELECT id, receipt_path FROM lp_expenses
             WHERE receipt_sha256 IS NULL AND receipt_path IS NOT NULL AND receipt_path <> ''
             ORDER BY id DESC LIMIT " . max(1, min(1000, $limit))
        );
        $s->execute();
        $rows = $s->fetchAll();
        if (!$rows) return;
        $upd = getDB()->prepare("UPDATE lp_expenses SET receipt_sha256=? WHERE id=?");
        foreach ($rows as $r) {
            $upd->execute([lpDupeHashOf($r['receipt_path']) ?? '', (int)$r['id']]);
        }
    } catch (Exception $e) { error_log('lpDupeBackfill: ' . $e->getMessage()); }
}

/**
 * "Smithers school visit lunch — 14 Oct 2026, $45.00, on voucher LP-12"
 *
 * Built from the row's own description rather than the scanned vendor name:
 * scan_vendor is stored folded down for matching ("a w"), which is the right
 * shape to compare and the wrong shape to read.
 */
function lpDupeLabel(array $r): string {
    $what = trim((string)($r['description'] ?? ''));
    $rest = [];
    $when = trim((string)($r['expense_date'] ?? '')) ?: trim((string)($r['scan_date'] ?? ''));
    if ($when !== '' && strtotime($when)) $rest[] = date('j M Y', strtotime($when));
    if ((float)($r['amount'] ?? 0) > 0)   $rest[] = '$' . number_format((float)$r['amount'], 2);
    $v = trim((string)($r['voucher_number'] ?? '')) ?: trim((string)($r['voucher_name'] ?? ''));
    if ($v !== '') $rest[] = 'on voucher ' . $v;

    if ($what === '' && !$rest) return "a receipt already on this year's vouchers";
    if ($what === '') return implode(', ', $rest);
    return $rest ? $what . ' — ' . implode(', ', $rest) : $what;
}

/**
 * The one query behind both signals.
 *
 * $excludeExpenseId keeps a row from matching itself: re-uploading the same
 * photo onto the row that already holds it is a correction, not a duplicate.
 */
function lpDupeFind(
    int $year,
    ?string $sha,
    ?string $vendor = null,
    ?string $date = null,
    float $total = 0.0,
    int $excludeExpenseId = 0
): ?array {
    lpDupeEnsureColumns();
    $select =
        "SELECT e.id, e.expense_date, e.description, e.scan_vendor, e.scan_date,
                e.receipt_filename,
                (e.travel_amt + e.meals + e.gifts + e.misc + e.office + e.phone) AS amount,
                v.id AS voucher_id, v.voucher_number, v.name AS voucher_name
         FROM lp_expenses e
         JOIN lp_vouchers v ON v.id = e.voucher_id
         WHERE v.year = ? AND e.id <> ? ";

    try {
        $db = getDB();

        // Identical bytes. Certain, so it is checked first and reported first.
        if ($sha) {
            $s = $db->prepare($select . "AND e.receipt_sha256 = ? ORDER BY e.id LIMIT 1");
            $s->execute([$year, $excludeExpenseId, $sha]);
            if ($hit = $s->fetch()) return lpDupeResult('same_file', $hit);
        }

        // Same vendor, same day, same amount to the cent.
        $nv = lpDupeNormVendor($vendor);
        if ($nv !== '' && $date && strtotime($date) && $total > 0) {
            $s = $db->prepare($select .
                "AND e.scan_vendor = ? AND e.scan_date = ? AND ABS(e.scan_total - ?) < 0.005
                 ORDER BY e.id LIMIT 1");
            $s->execute([$year, $excludeExpenseId, $nv, $date, round($total, 2)]);
            if ($hit = $s->fetch()) return lpDupeResult('same_receipt', $hit);
        }
    } catch (Exception $e) {
        // A reminder that cannot run must not stop a receipt being filed.
        error_log('lpDupeFind: ' . $e->getMessage());
        return null;
    }
    return null;
}

/** Shape one hit for the browser, with its wording decided here. */
function lpDupeResult(string $kind, array $r): array {
    return [
        'kind'        => $kind,
        'certain'     => $kind === 'same_file',
        'expense_id'  => (int)$r['id'],
        'voucher_id'  => (int)$r['voucher_id'],
        'label'       => lpDupeLabel($r),
        'description' => trim((string)($r['description'] ?? '')),
        'filename'    => trim((string)($r['receipt_filename'] ?? '')),
        'headline'    => $kind === 'same_file'
            ? 'You have already attached this exact file.'
            : 'This looks like a receipt already on this year\'s vouchers.',
    ];
}

/**
 * What to write into the scan_* columns for a saved row.
 *
 * Returns [vendor, date, total] with the vendor already normalised, so the
 * editor's hidden fields and this table always hold the same spelling.
 */
function lpDupeScanFields(?string $vendor, ?string $date, $total): array {
    $nv = lpDupeNormVendor($vendor);
    $d  = trim((string)$date);
    if ($d === '' || !strtotime($d)) $d = null;
    $t  = round((float)$total, 2);
    return [$nv !== '' ? $nv : null, $d, $t > 0 ? $t : null];
}
