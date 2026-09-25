<?php
/**
 * lp-releasetime-db.php — BCTF Local Support Grant: Release Time.
 *
 * Replaces the "RELEASE TIME GRANT Who When" Word doc.
 *
 * The shape of this comes straight from the two BCTF forms. You log release a
 * day at a time — date, who, how many days, why — but the BCTF reimbursement
 * table wants one row per INVOICE, and a district invoice rarely lines up with
 * one entry: invoice 556 in 2025-26 covered three people across two dates,
 * invoice 604 covered one person across three dates in two months. Hand-rolling
 * the log into that table is where the totals go wrong.
 *
 * So entries stay a day-at-a-time log, invoices are their own record, and the
 * link between them generates the BCTF table. Nothing is retyped.
 *
 * Cost sits on the invoice, not the entry, because that is how the district
 * bills: 556 was 3 days for $1,362.25, which is no per-day rate at all. A
 * per-day figure is derived for display and never entered.
 *
 * President only, via lpCanView().
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lp-db.php';

/** Where invoice PDFs live. Shares the LP receipts directory and its .htaccess. */
if (!defined('LP_RT_DIR')) define('LP_RT_DIR', LP_RECEIPTS_DIR);

/**
 * Members' Guide 10.J.18-3.a: the annual day cap by local FTE.
 * Ordered low to high; the first tier the FTE fits is the one that applies.
 *
 * Checked against the form at every boundary: 175→40, 176→60, 510→60, 511→80,
 * 999→80, 1000→100, 1499→100, 1500→120, 2999→120, 3001→140. BVTU is 103 FTE,
 * so 40.
 *
 * The form leaves two things undefined and this makes a choice about both:
 * a fraction between two bands (175.5) takes the higher band, and exactly
 * 3000 — which falls between "1,500–2,999" and "over 3,000" — takes 140. The
 * cap is editable on the page precisely so a BCTF ruling wins over this guess.
 */
const LP_RT_TIERS = [
    [175,  40], [510,  60], [999,  80], [1499, 100], [2999, 120], [PHP_INT_MAX, 140],
];

function lpRtCapForFte(float $fte): int {
    foreach (LP_RT_TIERS as $t) { if ($fte <= $t[0]) return $t[1]; }
    return 140;
}

/**
 * The school year a date falls in — September starts a new one, matching
 * lpCurrentYear(). Used to stop a mistyped year being filed under, and
 * counted against, the wrong year's cap.
 */
function lpRtYearOfDate(string $date): ?int {
    $t = strtotime($date);
    if ($t === false) return null;
    $m = (int)date('n', $t);
    return $m >= 9 ? (int)date('Y', $t) : (int)date('Y', $t) - 1;
}

function lpRtEnsureTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    // FTE decides the cap, so it is recorded per year rather than assumed.
    // day_cap is stored rather than always derived: if the BCTF ever rules a
    // different number for us, it is an edit here, not a code change.
    $db->exec("CREATE TABLE IF NOT EXISTS lp_rt_years (
        year        INT PRIMARY KEY,
        fte         DECIMAL(7,2) DEFAULT NULL,
        day_cap     INT NOT NULL DEFAULT 40,
        report      TEXT,
        updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // total_cost is NULLABLE on purpose: an invoice logged before its dollar
    // figure is known must read as "not recorded yet", not as $0.00.
    $db->exec("CREATE TABLE IF NOT EXISTS lp_rt_invoices (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        year           INT NOT NULL,
        invoice_number VARCHAR(100) NOT NULL DEFAULT '',
        invoice_date   DATE DEFAULT NULL,
        total_cost     DECIMAL(10,2) DEFAULT NULL,
        file_path      VARCHAR(255) NOT NULL DEFAULT '',
        original_name  VARCHAR(255) NOT NULL DEFAULT '',
        notes          TEXT,
        created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_by     VARCHAR(255) NOT NULL DEFAULT '',
        INDEX idx_year (year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // days is DECIMAL(3,1): half days are real — 2025-26 invoice 590 was 0.5.
    // excluded/excluded_reason record a deliberate decision not to claim, so a
    // year later it is clear the day was considered rather than forgotten.
    $db->exec("CREATE TABLE IF NOT EXISTS lp_rt_entries (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        year            INT NOT NULL,
        release_date    DATE NOT NULL,
        member_name     VARCHAR(255) NOT NULL,
        days            DECIMAL(3,1) NOT NULL DEFAULT 1.0,
        reason          VARCHAR(500) NOT NULL DEFAULT '',
        excluded        TINYINT(1) NOT NULL DEFAULT 0,
        excluded_reason VARCHAR(255) NOT NULL DEFAULT '',
        invoice_id      INT DEFAULT NULL,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_by      VARCHAR(255) NOT NULL DEFAULT '',
        INDEX idx_year (year),
        INDEX idx_invoice (invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** The year row, created on first use so the page never has a missing record. */
function lpRtYear(int $year): array {
    lpRtEnsureTables();
    $db = getDB();
    $s  = $db->prepare("SELECT * FROM lp_rt_years WHERE year=?");
    $s->execute([$year]);
    $row = $s->fetch();
    if (!$row) {
        $db->prepare("INSERT INTO lp_rt_years (year, day_cap) VALUES (?,40)")->execute([$year]);
        $s->execute([$year]);
        $row = $s->fetch();
    }
    return $row ?: ['year' => $year, 'fte' => null, 'day_cap' => 40, 'report' => ''];
}

/**
 * A cap of 0 is never what anyone meant — an emptied box would otherwise save
 * as zero and the page would read "of 0 allowed". Out-of-range keeps whatever
 * is already stored.
 */
function lpRtSaveYear(int $year, $fte, int $cap, string $report): void {
    lpRtEnsureTables();
    $cur = lpRtYear($year);
    if ($cap < 1 || $cap > 200) $cap = (int)$cur['day_cap'];
    getDB()->prepare("UPDATE lp_rt_years SET fte=?, day_cap=?, report=? WHERE year=?")
           ->execute([($fte === '' || $fte === null) ? null : (float)$fte,
                      $cap, $report, $year]);
}

/** Every year this tool knows about — entries or settings alone is enough. */
function lpRtYears(): array {
    lpRtEnsureTables();
    $rows = getDB()->query(
        "SELECT year FROM lp_rt_entries UNION SELECT year FROM lp_rt_years ORDER BY year DESC"
    )->fetchAll();
    return array_map(function ($r) { return (int)$r['year']; }, $rows);
}

// ── Entries ──────────────────────────────────────────────────────────────────

function lpRtEntries(int $year): array {
    lpRtEnsureTables();
    $s = getDB()->prepare(
        "SELECT e.*, i.invoice_number, i.invoice_date, i.total_cost
           FROM lp_rt_entries e
           LEFT JOIN lp_rt_invoices i ON i.id = e.invoice_id
          WHERE e.year = ?
          ORDER BY e.release_date, e.member_name, e.id"
    );
    $s->execute([$year]);
    return $s->fetchAll();
}

function lpRtEntry(int $id): ?array {
    lpRtEnsureTables();
    $s = getDB()->prepare("SELECT * FROM lp_rt_entries WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function lpRtAddEntry(int $year, string $date, string $name, float $days,
                      string $reason, string $by): int {
    lpRtEnsureTables();
    getDB()->prepare(
        "INSERT INTO lp_rt_entries (year, release_date, member_name, days, reason, created_by)
         VALUES (?,?,?,?,?,?)"
    )->execute([$year, $date, mb_substr(trim($name), 0, 255), $days,
                mb_substr(trim($reason), 0, 500), $by]);
    return (int)getDB()->lastInsertId();
}

function lpRtUpdateEntry(int $id, string $date, string $name, float $days, string $reason): void {
    lpRtEnsureTables();
    getDB()->prepare(
        "UPDATE lp_rt_entries SET release_date=?, member_name=?, days=?, reason=? WHERE id=?"
    )->execute([$date, mb_substr(trim($name), 0, 255), $days,
                mb_substr(trim($reason), 0, 500), $id]);
}

/**
 * Mark an entry as not being claimed, with the reason.
 *
 * The grant excludes contractor and office-staff salaries, the ongoing cost of
 * regularly released officers, and anything another BCTF grant already covers.
 * This records the decision rather than enforcing it — the call is the
 * president's, and a tool that silently dropped a day would be worse than one
 * that asks.
 */
function lpRtSetExcluded(int $id, bool $excluded, string $reason): void {
    lpRtEnsureTables();
    getDB()->prepare("UPDATE lp_rt_entries SET excluded=?, excluded_reason=? WHERE id=?")
           ->execute([$excluded ? 1 : 0, $excluded ? mb_substr(trim($reason), 0, 255) : '', $id]);
}

function lpRtDeleteEntry(int $id): void {
    lpRtEnsureTables();
    getDB()->prepare("DELETE FROM lp_rt_entries WHERE id=?")->execute([$id]);
}

// ── Totals ───────────────────────────────────────────────────────────────────

/**
 * The numbers the page and the BCTF form both need.
 *
 * 'claimable' excludes the days deliberately marked not-claimable; 'logged'
 * counts everything. Both are shown, because the difference is the point: it
 * is the record of what was considered and set aside.
 */
function lpRtTotals(int $year): array {
    $entries = lpRtEntries($year);
    $out = ['logged' => 0.0, 'claimable' => 0.0, 'invoiced' => 0.0,
            'unbilled' => 0.0, 'cost' => 0.0, 'cost_missing' => 0, 'entries' => count($entries)];
    $seenInvoice = [];
    foreach ($entries as $e) {
        $d = (float)$e['days'];
        $out['logged'] += $d;
        if ((int)$e['excluded']) continue;
        $out['claimable'] += $d;
        if ($e['invoice_id']) {
            $out['invoiced'] += $d;
            $iid = (int)$e['invoice_id'];
            // Cost belongs to the invoice, so count each invoice once no matter
            // how many entries hang off it.
            if (!isset($seenInvoice[$iid])) {
                $seenInvoice[$iid] = true;
                if ($e['total_cost'] === null) $out['cost_missing']++;
                else $out['cost'] += (float)$e['total_cost'];
            }
        } else {
            $out['unbilled'] += $d;
        }
    }
    return $out;
}
