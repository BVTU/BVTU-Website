<?php
/**
 * lp-db.php — Local President Expense Tracker database helpers
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/exec-db.php';
// Signer role checks and expAssertNotOwnClaim() live here. Required at the top
// rather than inside lpCanSign1()/lpCanSign2(), because the self-approval guard
// runs before those are called.
require_once __DIR__ . '/exp-db.php';
date_default_timezone_set('America/Vancouver');

define('LP_RECEIPTS_DIR', __DIR__ . '/lp-receipts/');
define('LP_MILEAGE_RATE', BVTU_MILEAGE_RATE); // shared with member claims — see exp-db.php

// School year helper: Sep 1 = new year
function lpCurrentYear(): int {
    $m = (int)date('n');
    return $m >= 9 ? (int)date('Y') : (int)date('Y') - 1;
}

const LP_GRANTS_SEED = [
    ['name' => 'Local Support Grant',              'budget' => 6500.00],
    ['name' => 'Member Outreach Grant',            'budget' => 1500.00],
    ['name' => 'Social Justice Grant',             'budget' => 2000.00],
    ['name' => 'TTOC Appreciation Grant',          'budget' => 1500.00],
    ['name' => 'Political Action Grant',           'budget' => 5700.00],
    ['name' => 'Aboriginal Initiative Grant',      'budget' => 2000.00],
    ['name' => 'Technology Grant',                 'budget' => 5000.00],
    ['name' => 'Climate Action Grant',             'budget' => 3000.00],
    ['name' => 'Local Release Time Grant',         'budget' => 16000.00],
    ['name' => 'SURT Grant',                       'budget' => 13000.00],
];

const LP_BUDGET_LINES_SEED = [
    ['name' => 'Aboriginal Initiative',         'budget' => 2500.00],
    ['name' => 'Advertising',                   'budget' => 0.00],
    ['name' => 'Bank Charges',                  'budget' => 105.00],
    ['name' => 'Bargaining',                    'budget' => 0.00],
    ['name' => 'Bursaries',                     'budget' => 2100.00],
    ['name' => 'Childcare',                     'budget' => 200.00],
    ['name' => 'Donations',                     'budget' => 3000.00],
    ['name' => 'General & Executive Meetings',  'budget' => 225.00],
    ['name' => 'Gifts',                         'budget' => 4000.00],
    ['name' => 'Grievance',                     'budget' => 1000.00],
    ['name' => 'Honorariums',                   'budget' => 500.00],
    ['name' => 'Digital Licences',              'budget' => 1000.00],
    ['name' => 'Meals / Food for Meetings',     'budget' => 3500.00],
    ['name' => 'Member Outreach',               'budget' => 4000.00],
    ['name' => 'Member Strike Support',         'budget' => 6000.00],
    ['name' => 'Miscellaneous',                 'budget' => 1000.00],
    ['name' => 'Office Insurance',              'budget' => 900.00],
    ['name' => 'Office',                        'budget' => 7030.00],
    ['name' => 'Political Action',              'budget' => 6000.00],
    ['name' => 'Professional Development',      'budget' => 9000.00],
    ['name' => 'Professional Services',         'budget' => 1000.00],
    ['name' => 'Rent',                          'budget' => 7000.00],
    ['name' => 'Social Justice',                'budget' => 2500.00],
    ['name' => 'Telephone',                     'budget' => 840.00],
    ['name' => 'Training Workshops (SURT)',     'budget' => 20000.00],
    ['name' => 'Travel - Executive & Member',   'budget' => 3800.00],
    ['name' => 'Travel - Local, President',     'budget' => 1500.00],
    ['name' => 'Travel - Other',                'budget' => 200.00],
    ['name' => 'TTOC Appreciation',             'budget' => 1500.00],
    ['name' => 'TTOC Release Costs',            'budget' => 13000.00],
    ['name' => 'Wages - President',             'budget' => 104230.00],
    ['name' => 'Worksafe Expenses',             'budget' => 270.00],
    ['name' => 'Environmental / Ebike',         'budget' => 3000.00],
];

// ── Table creation ────────────────────────────────────────────────────────────
function lpEnsureTables(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS lp_grants (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        budget     DECIMAL(10,2) DEFAULT 0,
        year       INT NOT NULL,
        active     TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_year (year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS lp_budget_lines (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        budget     DECIMAL(10,2) DEFAULT 0,
        year       INT NOT NULL,
        active     TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_year (year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS lp_vouchers (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        voucher_number      VARCHAR(50),
        name                VARCHAR(255) NOT NULL,
        submitted_by        VARCHAR(255) NOT NULL,
        submitted_by_email  VARCHAR(255) NOT NULL,
        notes               TEXT,
        status              VARCHAR(20) DEFAULT 'draft',
        mileage_rate        DECIMAL(6,4) DEFAULT 0.6100,
        year                INT NOT NULL,
        created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
        submitted_at        DATETIME,
        INDEX idx_email  (submitted_by_email),
        INDEX idx_status (status),
        INDEX idx_year   (year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS lp_expenses (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        voucher_id       INT NOT NULL,
        expense_date     DATE,
        description      TEXT,
        travel_km        DECIMAL(8,2) DEFAULT 0,
        travel_amt       DECIMAL(10,2) DEFAULT 0,
        meals            DECIMAL(10,2) DEFAULT 0,
        gifts            DECIMAL(10,2) DEFAULT 0,
        misc             DECIMAL(10,2) DEFAULT 0,
        office           DECIMAL(10,2) DEFAULT 0,
        phone            DECIMAL(10,2) DEFAULT 0,
        receipt_path     VARCHAR(500),
        receipt_filename VARCHAR(255),
        grant_id         INT DEFAULT NULL,
        budget_line_id   INT DEFAULT NULL,
        notes            TEXT,
        sort_order       INT DEFAULT 0,
        created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_voucher (voucher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed grants if none exist for current year
    $yr = lpCurrentYear();
    $cnt = (int)$db->prepare("SELECT COUNT(*) FROM lp_grants WHERE year=?")->execute([$yr]) ? 0 : 0;
    $s = $db->prepare("SELECT COUNT(*) FROM lp_grants WHERE year=?");
    $s->execute([$yr]);
    if ((int)$s->fetchColumn() === 0) {
        $ins = $db->prepare("INSERT INTO lp_grants (name, budget, year) VALUES (?,?,?)");
        foreach (LP_GRANTS_SEED as $g) $ins->execute([$g['name'], $g['budget'], $yr]);
    }

    // Seed budget lines if none exist for current year
    $s = $db->prepare("SELECT COUNT(*) FROM lp_budget_lines WHERE year=?");
    $s->execute([$yr]);
    if ((int)$s->fetchColumn() === 0) {
        $ins = $db->prepare("INSERT INTO lp_budget_lines (name, budget, year) VALUES (?,?,?)");
        foreach (LP_BUDGET_LINES_SEED as $b) $ins->execute([$b['name'], $b['budget'], $yr]);
    }

    $db->exec("CREATE TABLE IF NOT EXISTS lp_grant_submissions (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        grant_id           INT NOT NULL,
        submitted_at       DATETIME NOT NULL,
        submitted_by_email VARCHAR(255) NOT NULL,
        submitted_by_name  VARCHAR(255) NOT NULL,
        note               VARCHAR(500),
        created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_grant (grant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Mobile upload tokens (no time-based expiry — token is valid until voucher is finalized)
    $db->exec("CREATE TABLE IF NOT EXISTS lp_upload_tokens (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        token       VARCHAR(64) NOT NULL UNIQUE,
        voucher_id  INT NOT NULL,
        created_by  VARCHAR(255) NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token   (token),
        INDEX idx_voucher (voucher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Pending receipts uploaded from phone (not yet assigned to an expense row)
    $db->exec("CREATE TABLE IF NOT EXISTS lp_pending_receipts (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        voucher_id    INT NOT NULL,
        saved_path    VARCHAR(500) NOT NULL,
        original_name VARCHAR(255),
        scan_json     TEXT,
        claimed       TINYINT(1) DEFAULT 0,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_voucher (voucher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Receipts directory
    if (!is_dir(LP_RECEIPTS_DIR)) mkdir(LP_RECEIPTS_DIR, 0750, true);
    $htaccess = LP_RECEIPTS_DIR . '.htaccess';
    if (!file_exists($htaccess)) file_put_contents($htaccess, "Require all denied\n");
}

// ── Mobile upload token helpers ───────────────────────────────────────────────
function lpCreateUploadToken(int $voucherId, string $email): string {
    $token = bin2hex(random_bytes(16));
    $db    = getDB();
    // Delete any existing tokens for this voucher so we don't accumulate stale rows
    $db->prepare("DELETE FROM lp_upload_tokens WHERE voucher_id=?")->execute([$voucherId]);
    $db->prepare("INSERT INTO lp_upload_tokens (token, voucher_id, created_by) VALUES (?,?,?)")
       ->execute([$token, $voucherId, $email]);
    return $token;
}

function lpValidateUploadToken(string $token): ?array {
    $s = getDB()->prepare("SELECT * FROM lp_upload_tokens WHERE token=? LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

// ── Pending receipts helpers ──────────────────────────────────────────────────
function lpAddPendingReceipt(int $voucherId, string $savedPath, string $origName, array $scanData): int {
    $db = getDB();
    $st = $db->prepare("INSERT INTO lp_pending_receipts (voucher_id, saved_path, original_name, scan_json) VALUES (?,?,?,?)");
    $st->execute([$voucherId, $savedPath, $origName, json_encode($scanData)]);
    return (int)$db->lastInsertId();
}

function lpGetPendingReceipts(int $voucherId): array {
    $s = getDB()->prepare(
        "SELECT * FROM lp_pending_receipts WHERE voucher_id=? AND claimed=0 ORDER BY created_at ASC"
    );
    $s->execute([$voucherId]);
    $rows = $s->fetchAll();
    foreach ($rows as &$r) {
        $r['scan_data'] = $r['scan_json'] ? json_decode($r['scan_json'], true) : [];
    }
    return $rows;
}

function lpClaimPendingReceipt(int $id): void {
    getDB()->prepare("UPDATE lp_pending_receipts SET claimed=1 WHERE id=?")->execute([$id]);
}

// ── Access helpers ────────────────────────────────────────────────────────────
// LP = Local President expenses — president only.
function lpCanCreate(string $email): bool {
    return execIsAdmin($email);
}

function lpCanView(string $email): bool {
    return execIsAdmin($email);
}

/** BVTU Treasurer — signer 1 for LP vouchers */
function lpCanSign1(string $email): bool {
    require_once __DIR__ . '/exp-db.php';
    return expIsTreasurer($email);
}

/** VP — signer 2 for LP vouchers (president submits, so VP must be signer 2) */
function lpCanSign2(string $email): bool {
    require_once __DIR__ . '/exp-db.php';
    return expIsVP($email);
}

/** Can review LP vouchers (treasurer or VP) */
function lpCanReview(string $email): bool {
    return lpCanSign1($email) || lpCanSign2($email);
}

/** Ensure approval columns exist on lp_vouchers */
function lpEnsureApprovalColumns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cols = [
        'signer1_email'      => "VARCHAR(255)",
        'signer1_name'       => "VARCHAR(255)",
        'signer1_at'         => "DATETIME",
        'signer1_note'       => "TEXT",
        'signer2_email'      => "VARCHAR(255)",
        'signer2_name'       => "VARCHAR(255)",
        'signer2_at'         => "DATETIME",
        'signer2_note'       => "TEXT",
        'rejected_by_email'  => "VARCHAR(255)",
        'rejected_by_name'   => "VARCHAR(255)",
        'rejected_at'        => "DATETIME",
        'rejection_note'     => "TEXT",
        'paid_at'            => "DATETIME",
        'paid_by_email'      => "VARCHAR(255)",
        'paid_by_name'       => "VARCHAR(255)",
        'payment_note'       => "TEXT",
        'payment_ref'        => "VARCHAR(255)",
        'payment_date'       => "DATE",
    ];
    foreach ($cols as $col => $type) {
        try {
            $exists = getDB()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='lp_vouchers' AND COLUMN_NAME='{$col}'"
            )->fetchColumn();
            if (!$exists) {
                getDB()->exec("ALTER TABLE lp_vouchers ADD COLUMN {$col} {$type}");
            }
        } catch (Exception $e) { /* already exists */ }
    }
}

// ── Data helpers ──────────────────────────────────────────────────────────────
function lpGetGrants(int $year = 0): array {
    if (!$year) $year = lpCurrentYear();
    $s = getDB()->prepare("SELECT * FROM lp_grants WHERE year=? AND active=1 ORDER BY name");
    $s->execute([$year]);
    return $s->fetchAll();
}

function lpGetBudgetLines(int $year = 0): array {
    if (!$year) $year = lpCurrentYear();
    $s = getDB()->prepare("SELECT * FROM lp_budget_lines WHERE year=? AND active=1 ORDER BY name");
    $s->execute([$year]);
    return $s->fetchAll();
}

/**
 * Vouchers in any of the given statuses, newest activity first.
 * Used by the review page's history section, which spans several statuses.
 */
/** Coloured status pill. Shared so every page labels a status identically. */
function lpStatusBadge(string $status): string {
    $map = [
        'draft'              => ['#f1f5f9', '#64748b', 'Draft'],
        'submitted'          => ['#fffbeb', '#d97706', 'Awaiting Treasurer'],
        'treasurer_approved' => ['#eff6ff', '#1e40af', 'Awaiting VP'],
        'vp_approved'        => ['#f0fdf4', '#166534', 'Ready to Pay'],
        'paid'               => ['#f0fdf4', '#166534', 'Paid'],
        'rejected'           => ['#fef2f2', '#991b1b', 'Rejected'],
    ];
    $c = $map[$status] ?? ['#f8f9fa', '#555', ucfirst($status)];
    return '<span style="display:inline-block;font-size:.72rem;font-weight:700;'
         . 'text-transform:uppercase;letter-spacing:.04em;padding:.2rem .6rem;'
         . 'border-radius:100px;background:' . $c[0] . ';color:' . $c[1] . ';">'
         . $c[2] . '</span>';
}

/**
 * Who has signed a voucher and when, as a step list.
 *
 * The voucher pages previously showed a flat "Submitted" for every non-draft
 * status, so an approval was invisible to the submitter. Self-contained inline
 * styles so any page can drop it in.
 */
function lpApprovalTrail(array $v): string {
    $status   = $v['status'] ?? 'draft';
    $rejected = $status === 'rejected';

    $row = function (string $state, string $label, string $detail): string {
        $icon = ['done' => '&#x2713;', 'current' => '&#x23F3;', 'todo' => '&#x25CB;',
                 'stopped' => '&#x2715;'][$state];
        $col  = ['done' => '#166534', 'current' => '#d97706', 'todo' => '#cbd5e1',
                 'stopped' => '#991b1b'][$state];
        $weight = $state === 'todo' ? '500' : '700';
        return '<li style="display:flex;gap:.55rem;align-items:flex-start;padding:.3rem 0;">'
             . '<span style="color:' . $col . ';font-weight:700;line-height:1.35;">' . $icon . '</span>'
             . '<span style="line-height:1.35;">'
             . '<span style="font-weight:' . $weight . ';color:' . ($state === 'todo' ? '#94a3b8' : '#1f2937') . ';">'
             . htmlspecialchars($label) . '</span>'
             . ($detail !== '' ? '<br><span style="font-size:.78rem;color:#64748b;">' . $detail . '</span>' : '')
             . '</span></li>';
    };

    $when = function (?string $ts, string $fmt = 'M j, Y'): string {
        return $ts ? date($fmt, strtotime($ts)) : '';
    };
    $note = function (?string $n): string {
        return !empty($n) ? '<br><em>&ldquo;' . htmlspecialchars($n) . '&rdquo;</em>' : '';
    };

    $out = '<ul style="list-style:none;margin:0;padding:0;">';

    // 1. Submitted
    $out .= $status === 'draft'
        ? $row('todo', 'Not yet submitted', '')
        : $row('done', 'Submitted', htmlspecialchars($v['submitted_by'] ?? '')
               . ($v['submitted_at'] ? ' &middot; ' . $when($v['submitted_at']) : ''));

    if ($rejected) {
        $out .= $row('stopped', 'Rejected',
            htmlspecialchars($v['rejected_by_name'] ?: ($v['rejected_by_email'] ?? ''))
            . ($v['rejected_at'] ? ' &middot; ' . $when($v['rejected_at']) : '')
            . $note($v['rejection_note'] ?? ''));
        return $out . '</ul>';
    }

    // 2. Treasurer (signer 1)
    if (!empty($v['signer1_at'])) {
        $out .= $row('done', 'Approved by Treasurer',
            htmlspecialchars($v['signer1_name'] ?: $v['signer1_email'])
            . ' &middot; ' . $when($v['signer1_at']) . $note($v['signer1_note'] ?? ''));
    } else {
        $out .= $row($status === 'submitted' ? 'current' : 'todo', 'Treasurer approval', '');
    }

    // 3. Vice-President (signer 2)
    if (!empty($v['signer2_at'])) {
        $out .= $row('done', 'Approved by Vice-President',
            htmlspecialchars($v['signer2_name'] ?: $v['signer2_email'])
            . ' &middot; ' . $when($v['signer2_at']) . $note($v['signer2_note'] ?? ''));
    } else {
        $out .= $row($status === 'treasurer_approved' ? 'current' : 'todo', 'Vice-President signature', '');
    }

    // 4. Payment
    if (!empty($v['paid_at'])) {
        $ref = !empty($v['payment_ref']) ? ' &middot; ref ' . htmlspecialchars($v['payment_ref']) : '';
        $out .= $row('done', 'Paid',
            $when($v['payment_date'] ?: $v['paid_at']) . $ref . $note($v['payment_note'] ?? ''));
    } else {
        $out .= $row($status === 'vp_approved' ? 'current' : 'todo', 'E-transfer sent', '');
    }

    return $out . '</ul>';
}

/** Vouchers in a given status. Cheap count for dashboards and the hub. */
function lpCountByStatus(string $status): int {
    $s = getDB()->prepare("SELECT COUNT(*) FROM lp_vouchers WHERE status=?");
    $s->execute([$status]);
    return (int)$s->fetchColumn();
}

function lpGetVouchersByStatuses(array $statuses): array {
    if (!$statuses) return [];
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $s = getDB()->prepare(
        "SELECT v.*,
                COALESCE(SUM(e.travel_amt + e.meals + e.gifts + e.misc + e.office + e.phone), 0) AS total_amount,
                COUNT(e.id) AS expense_count
         FROM lp_vouchers v
         LEFT JOIN lp_expenses e ON e.voucher_id = v.id
         WHERE v.status IN ($in)
         GROUP BY v.id
         ORDER BY COALESCE(v.paid_at, v.rejected_at, v.signer2_at, v.signer1_at, v.submitted_at) DESC"
    );
    $s->execute(array_values($statuses));
    return $s->fetchAll();
}

/**
 * $year: -1, the default, is every year — the behaviour before this parameter
 * existed, so no caller changes meaning by not passing it. A real year scopes
 * the list; the dashboard passes one because it showed every voucher ever
 * submitted and kept growing, last year's mixed in with this year's.
 */
function lpGetVouchers(string $email = '', string $status = '', int $year = -1): array {
    $db = getDB();
    $sql = "SELECT v.*,
                COALESCE(SUM(e.travel_amt + e.meals + e.gifts + e.misc + e.office + e.phone), 0) AS total_amount,
                COUNT(e.id) AS expense_count
            FROM lp_vouchers v
            LEFT JOIN lp_expenses e ON e.voucher_id = v.id";
    $params = [];
    $where  = [];
    if ($year > 0) { $where[] = "v.year=?"; $params[] = $year; }
    if ($email) { $where[] = "v.submitted_by_email=?"; $params[] = $email; }
    if ($status) { $where[] = "v.status=?"; $params[] = $status; }
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " GROUP BY v.id ORDER BY v.created_at DESC";
    $s = $db->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

function lpGetVoucher(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM lp_vouchers WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function lpGetExpenses(int $voucherId): array {
    $s = getDB()->prepare(
        "SELECT e.*, g.name AS grant_name, b.name AS budget_line_name
         FROM lp_expenses e
         LEFT JOIN lp_grants g ON g.id = e.grant_id
         LEFT JOIN lp_budget_lines b ON b.id = e.budget_line_id
         WHERE e.voucher_id=?
         ORDER BY e.sort_order, e.expense_date, e.id"
    );
    $s->execute([$voucherId]);
    return $s->fetchAll();
}

function lpRowTotal(array $expense): float {
    return (float)$expense['travel_amt'] + (float)$expense['meals']
         + (float)$expense['gifts']      + (float)$expense['misc']
         + (float)$expense['office']     + (float)$expense['phone'];
}

function lpGrantSummary(int $year = 0): array {
    if (!$year) $year = lpCurrentYear();
    $grants = lpGetGrants($year);
    $db = getDB();
    foreach ($grants as &$g) {
        $s = $db->prepare(
            "SELECT COALESCE(SUM(e.travel_amt+e.meals+e.gifts+e.misc+e.office+e.phone),0)
             FROM lp_expenses e
             JOIN lp_vouchers v ON v.id = e.voucher_id
             WHERE e.grant_id=?"
        );
        $s->execute([$g['id']]);
        $g['spent']     = (float)$s->fetchColumn();
        $g['remaining'] = $g['budget'] - $g['spent'];
        $g['pct']       = $g['budget'] > 0 ? round($g['spent'] / $g['budget'] * 100) : 0;
    }
    return $grants;
}

/** BVTU Treasurer emails — EC directory first, legacy exp_roles second */
function lpGetTreasurerEmails(): array {
    $emails = [];
    $db     = getDB();

    // Primary: EC directory (exec_roles) — source of truth
    try {
        $s = $db->query("SELECT DISTINCT user_email FROM exec_roles WHERE role='treasurer'");
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $e) {
            $e = strtolower(trim($e));
            if ($e && !in_array($e, $emails)) $emails[] = $e;
        }
    } catch (Exception $ex) {}

    // Legacy: exp_roles (manual assignments, kept for backward compat)
    try {
        $s = $db->query("SELECT DISTINCT user_email FROM exp_roles WHERE role='treasurer'");
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $e) {
            $e = strtolower(trim($e));
            if ($e && !in_array($e, $emails)) $emails[] = $e;
        }
    } catch (Exception $ex) {}

    // No Pro-D admin fallback — LP vouchers go to the BVTU Treasurer only.
    // Callers must handle an empty result and warn the submitter.
    return $emails;
}

/** VP emails (signer 2) — exec_roles uses 'vice_president', exp_roles uses 'vp' */
function lpGetVPEmails(): array {
    $emails = [];
    $db     = getDB();

    try {
        $s = $db->query("SELECT DISTINCT user_email FROM exec_roles WHERE role='vice_president'");
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $e) {
            $e = strtolower(trim($e));
            if ($e && !in_array($e, $emails)) $emails[] = $e;
        }
    } catch (Exception $ex) {}

    try {
        $s = $db->query("SELECT DISTINCT user_email FROM exp_roles WHERE role='vp'");
        foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $e) {
            $e = strtolower(trim($e));
            if ($e && !in_array($e, $emails)) $emails[] = $e;
        }
    } catch (Exception $ex) {}

    return $emails;
}

// ── Workflow transitions ───────────────────────────────────────────────────────

function lpSubmitVoucher(int $id): void {
    $v = lpGetVoucher($id);
    if (!$v) throw new RuntimeException("Voucher #{$id} not found.");
    if ($v['status'] !== 'draft') throw new RuntimeException("Only draft vouchers can be submitted.");
    getDB()->prepare(
        "UPDATE lp_vouchers SET status='submitted', submitted_at=NOW() WHERE id=?"
    )->execute([$id]);
}

function lpApproveAsSigner1(int $id, string $email, string $name, string $note = ''): void {
    $v = lpGetVoucher($id);
    if (!$v) throw new RuntimeException("Voucher not found.");
    if ($v['status'] !== 'submitted') throw new RuntimeException("Voucher must be in 'submitted' state.");
    // The President submits LP vouchers and passes lpCanSign1() through
    // expIsAdmin(), so without this they could sign their own.
    expAssertNotOwnClaim($email, $v['submitted_by_email'] ?? null);
    if (!lpCanSign1($email)) throw new RuntimeException("Only the BVTU Treasurer can give first approval.");
    getDB()->prepare(
        "UPDATE lp_vouchers SET status='treasurer_approved',
         signer1_email=?, signer1_name=?, signer1_at=NOW(), signer1_note=? WHERE id=?"
    )->execute([$email, $name, $note ?: null, $id]);
}

function lpApproveAsSigner2(int $id, string $email, string $name, string $note = ''): void {
    $v = lpGetVoucher($id);
    if (!$v) throw new RuntimeException("Voucher not found.");
    if ($v['status'] !== 'treasurer_approved') throw new RuntimeException("Voucher must have Treasurer approval first.");
    expAssertNotOwnClaim($email, $v['submitted_by_email'] ?? null);
    if (!lpCanSign2($email)) throw new RuntimeException("Only the Vice-President can give second approval.");
    if (!empty($v['signer1_email']) && strtolower(trim($email)) === strtolower(trim($v['signer1_email']))) {
        throw new RuntimeException("Signer 2 cannot be the same person as Signer 1.");
    }
    getDB()->prepare(
        "UPDATE lp_vouchers SET status='vp_approved',
         signer2_email=?, signer2_name=?, signer2_at=NOW(), signer2_note=? WHERE id=?"
    )->execute([$email, $name, $note ?: null, $id]);
}

function lpRejectVoucher(int $id, string $email, string $name, string $note): void {
    $v = lpGetVoucher($id);
    if (!$v) throw new RuntimeException("Voucher not found.");
    if (!in_array($v['status'], ['submitted', 'treasurer_approved'])) {
        throw new RuntimeException("Cannot reject a voucher in its current state.");
    }
    if (!$note) throw new RuntimeException("A rejection reason is required.");
    getDB()->prepare(
        "UPDATE lp_vouchers SET status='rejected',
         rejected_by_email=?, rejected_by_name=?, rejected_at=NOW(), rejection_note=? WHERE id=?"
    )->execute([$email, $name, $note, $id]);
}

function lpMarkPaid(int $id, string $email, string $name, string $note, string $paymentRef = '', string $paymentDate = ''): void {
    $v = lpGetVoucher($id);
    if (!$v) throw new RuntimeException("Voucher not found.");
    if ($v['status'] !== 'vp_approved') throw new RuntimeException("Voucher must have both signatures before marking paid.");
    // lpCanSign1() admits the President via expIsAdmin(); they are the payee on
    // their own voucher, so recording payment on it stays with the Treasurer.
    expAssertNotOwnClaim($email, $v['submitted_by_email'] ?? null);
    if (!lpCanSign1($email)) throw new RuntimeException("Only the Treasurer can mark a voucher as paid.");
    $pd = ($paymentDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) ? $paymentDate : date('Y-m-d');
    getDB()->prepare(
        "UPDATE lp_vouchers SET status='paid',
         paid_at=NOW(), paid_by_email=?, paid_by_name=?, payment_note=?, payment_ref=?, payment_date=? WHERE id=?"
    )->execute([$email, $name, $note ?: null, $paymentRef ?: null, $pd, $id]);
}

// ── Email notifications ───────────────────────────────────────────────────────

function lpNotify(string $to, string $subject, string $body): void {
    require_once __DIR__ . '/smtp.php';
    siteMail($to, $subject, $body, true);
}

function _lpWrap(string $title, string $body): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:0;}
        .wrap{max-width:560px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);}
        .hdr{background:#1a6b35;padding:20px 28px;color:#fff;}
        .hdr h1{margin:0;font-size:18px;font-weight:700;}
        .hdr p{margin:4px 0 0;font-size:13px;opacity:.8;}
        .body{padding:24px 28px;}
        .body p{color:#374151;font-size:14px;line-height:1.6;margin:0 0 12px;}
        .detail-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin:16px 0;}
        .row{display:flex;padding:5px 0;border-bottom:1px solid #f3f4f6;}
        .row:last-child{border-bottom:none;}
        .lbl{color:#6b7280;font-size:13px;width:140px;flex-shrink:0;}
        .val{color:#111827;font-size:13px;font-weight:600;}
        .btn{display:inline-block;background:#1a6b35;color:#fff;padding:10px 22px;border-radius:7px;text-decoration:none;font-size:14px;font-weight:700;margin-top:8px;}
        .ftr{background:#f9fafb;padding:16px 28px;font-size:12px;color:#9ca3af;border-top:1px solid #e5e7eb;}
    </style></head><body><div class="wrap">
    <div class="hdr"><h1>BVTU LP Expense Voucher</h1><p>' . htmlspecialchars($title) . '</p></div>
    <div class="body">' . $body . '</div>
    <div class="ftr">Bulkley Valley Teachers\' Union &mdash; Automated notification. Please do not reply to this email &mdash; this mailbox is not monitored. Contact <a href="mailto:lp54@bctf.ca" style="color:#9ca3af;">lp54@bctf.ca</a> if you have questions.</div>
    </div></body></html>';
}

function _lpVoucherBox(array $v, float $total): string {
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca';
    return '<div class="detail-box">'
         . '<div class="row"><span class="lbl">Voucher</span><span class="val">' . htmlspecialchars($v['name']) . '</span></div>'
         . '<div class="row"><span class="lbl">Number</span><span class="val">' . htmlspecialchars($v['voucher_number'] ?? '—') . '</span></div>'
         . '<div class="row"><span class="lbl">Submitted by</span><span class="val">' . htmlspecialchars($v['submitted_by']) . '</span></div>'
         . '<div class="row"><span class="lbl">Total</span><span class="val">$' . number_format($total, 2) . '</span></div>'
         . '</div>'
         . '<p><a class="btn" href="' . $siteUrl . '/members/approvals.php">Review in Approvals &amp; Payments</a></p>';
}

function lpEmailSubmitted(array $v, float $total): void {
    $body = '<p><strong>' . htmlspecialchars($v['submitted_by']) . '</strong> has submitted an LP expense voucher for Treasurer approval.</p>'
          . _lpVoucherBox($v, $total);
    foreach (lpGetTreasurerEmails() as $email) {
        lpNotify($email, 'LP Voucher for Review — ' . $v['name'], _lpWrap('New LP Voucher Submitted', $body));
    }
}

function lpEmailTreasurerApproved(array $v, float $total): void {
    $body = '<p>The LP expense voucher has been approved by the BVTU Treasurer and now requires the Vice-President\'s signature.</p>'
          . _lpVoucherBox($v, $total);
    foreach (lpGetVPEmails() as $email) {
        lpNotify($email, 'LP Voucher — Second Signature Required — ' . $v['name'], _lpWrap('Second Signature Required', $body));
    }
}

function lpEmailVPApproved(array $v, float $total): void {
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca';
    $body = '<p>The LP expense voucher has received both signatures and is <strong>ready for e-transfer payment</strong>.</p>'
          . _lpVoucherBox($v, $total)
          . '<div class="detail-box" style="background:#f0fdf4;border-color:#bbf7d0;">'
          . '<div class="row"><span class="lbl">Send e-transfer to</span><span class="val">' . htmlspecialchars($v['submitted_by_email']) . '</span></div>'
          . '<div class="row"><span class="lbl">Amount</span><span class="val">$' . number_format($total, 2) . '</span></div>'
          . '</div>'
          . '<p><a class="btn" href="' . $siteUrl . '/members/approvals.php">Open Approvals &amp; Payments</a></p>';
    foreach (lpGetTreasurerEmails() as $email) {
        lpNotify($email, 'LP Voucher Ready for Payment — ' . $v['name'], _lpWrap('Ready for E-Transfer', $body));
    }
}

function lpEmailRejected(array $v): void {
    $body = '<p>Your LP expense voucher has been rejected.</p>'
          . '<div class="detail-box"><div class="row"><span class="lbl">Voucher</span><span class="val">' . htmlspecialchars($v['name']) . '</span></div>'
          . '<div class="row"><span class="lbl">Rejected by</span><span class="val">' . htmlspecialchars($v['rejected_by_name'] ?? '—') . '</span></div>'
          . '<div class="row"><span class="lbl">Reason</span><span class="val">' . htmlspecialchars($v['rejection_note'] ?? '—') . '</span></div>'
          . '</div>';
    if ($v['submitted_by_email']) {
        lpNotify($v['submitted_by_email'], 'LP Voucher Rejected — ' . $v['name'], _lpWrap('Voucher Rejected', $body));
    }
}

function lpEmailPaid(array $v, float $total): void {
    $body = '<p>Your LP expense voucher has been paid via e-transfer.</p>'
          . '<div class="detail-box">'
          . '<div class="row"><span class="lbl">Voucher</span><span class="val">' . htmlspecialchars($v['name']) . '</span></div>'
          . '<div class="row"><span class="lbl">Amount</span><span class="val">$' . number_format($total, 2) . '</span></div>'
          . '<div class="row"><span class="lbl">Paid by</span><span class="val">' . htmlspecialchars($v['paid_by_name'] ?? '—') . '</span></div>'
          . '</div>';
    if ($v['submitted_by_email']) {
        lpNotify($v['submitted_by_email'], 'LP Voucher Paid — ' . $v['name'], _lpWrap('Payment Sent', $body));
    }
}

function lpGetExpensesByGrant(int $grantId): array {
    $s = getDB()->prepare(
        "SELECT e.id, e.expense_date, e.description, e.travel_km, e.travel_amt,
                e.meals, e.gifts, e.misc, e.office, e.phone,
                e.receipt_path, e.receipt_filename, e.created_at,
                v.name AS voucher_name, v.voucher_number, v.id AS voucher_id, v.status AS voucher_status
         FROM lp_expenses e
         JOIN lp_vouchers v ON v.id = e.voucher_id
         WHERE e.grant_id=?
         ORDER BY e.expense_date, e.id"
    );
    $s->execute([$grantId]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

/** Most recent "submitted to BCTF" record for a grant, or null if never submitted */
function lpGetGrantSubmission(int $grantId): ?array {
    $s = getDB()->prepare(
        "SELECT * FROM lp_grant_submissions WHERE grant_id=? ORDER BY submitted_at DESC LIMIT 1"
    );
    $s->execute([$grantId]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Record that a grant's receipts have been submitted to BCTF */
function lpMarkGrantSubmitted(int $grantId, string $email, string $name, string $note = ''): void {
    getDB()->prepare(
        "INSERT INTO lp_grant_submissions (grant_id, submitted_at, submitted_by_email, submitted_by_name, note)
         VALUES (?, NOW(), ?, ?, ?)"
    )->execute([$grantId, $email, $name, $note]);
}

/** Remove the most recent "submitted" record for a grant (undo) */
function lpUnmarkGrantSubmitted(int $grantId): void {
    $s = getDB()->prepare(
        "SELECT id FROM lp_grant_submissions WHERE grant_id=? ORDER BY submitted_at DESC LIMIT 1"
    );
    $s->execute([$grantId]);
    $id = $s->fetchColumn();
    if ($id) {
        getDB()->prepare("DELETE FROM lp_grant_submissions WHERE id=?")->execute([(int)$id]);
    }
}

function lpDeleteVoucher(int $id): void {
    $db = getDB();
    // Delete receipt files from disk first
    $s = $db->prepare("SELECT receipt_path FROM lp_expenses WHERE voucher_id=? AND receipt_path IS NOT NULL");
    $s->execute([$id]);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $path) {
        if ($path && file_exists(LP_RECEIPTS_DIR . basename($path))) {
            @unlink(LP_RECEIPTS_DIR . basename($path));
        }
    }
    $db->prepare("DELETE FROM lp_expenses WHERE voucher_id=?")->execute([$id]);
    $db->prepare("DELETE FROM lp_vouchers WHERE id=?")->execute([$id]);
}

function lpBudgetSummary(int $year = 0): array {
    if (!$year) $year = lpCurrentYear();
    $lines = lpGetBudgetLines($year);
    $db = getDB();
    foreach ($lines as &$l) {
        $s = $db->prepare(
            "SELECT COALESCE(SUM(e.travel_amt+e.meals+e.gifts+e.misc+e.office+e.phone),0)
             FROM lp_expenses e
             JOIN lp_vouchers v ON v.id = e.voucher_id
             WHERE e.budget_line_id=?"
        );
        $s->execute([$l['id']]);
        $l['spent']     = (float)$s->fetchColumn();
        $l['remaining'] = $l['budget'] - $l['spent'];
        $l['pct']       = $l['budget'] > 0 ? round($l['spent'] / $l['budget'] * 100) : 0;
    }
    return $lines;
}

/**
 * Every school year that has anything recorded against it, newest first.
 * Drawn from vouchers, grants and budget lines together, so a year that was
 * set up but never spent against still appears.
 */
function lpYearsWithData(): array {
    $years = [];
    foreach (['SELECT DISTINCT year FROM lp_vouchers',
              'SELECT DISTINCT year FROM lp_grants',
              'SELECT DISTINCT year FROM lp_budget_lines'] as $sql) {
        try {
            foreach (getDB()->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $y) {
                $y = (int)$y;
                if ($y > 0) $years[$y] = true;
            }
        } catch (Exception $e) {}
    }
    $years[lpCurrentYear()] = true;   // always offer the year we are in
    $years = array_keys($years);
    rsort($years);
    return $years;
}

/** "2025–2026", matching how lp-grants-manage.php already writes it. */
function lpYearLabel(int $year): string {
    return $year . '–' . ($year + 1);
}

/**
 * Vouchers from other school years that are still in flight.
 *
 * Scoping the dashboard to one year is right for reporting and wrong for
 * unfinished business: a voucher submitted in August and still unapproved in
 * October belongs to last school year and would otherwise disappear from the
 * President's view entirely.
 */
function lpUnfinishedOtherYears(int $currentYear): array {
    try {
        $s = getDB()->prepare(
            "SELECT year, COUNT(*) AS n FROM lp_vouchers
             WHERE year <> ? AND status NOT IN ('paid','rejected','draft')
             GROUP BY year ORDER BY year DESC"
        );
        $s->execute([$currentYear]);
        return $s->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/* ---------------------------------------------------------------------------
 * Year end
 *
 * Closing a year is a flag and a set of copies. Nothing is moved and nothing is
 * deleted: a rollover that relocates rows is a rollover that can lose them, and
 * you would only find out twelve months later.
 * ------------------------------------------------------------------------ */

function lpYearStatusEnsure(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        getDB()->exec("CREATE TABLE IF NOT EXISTS lp_year_status (
            year       INT PRIMARY KEY,
            closed_at  DATETIME DEFAULT NULL,
            closed_by  VARCHAR(255) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

function lpYearIsClosed(int $year): bool {
    lpYearStatusEnsure();
    try {
        $s = getDB()->prepare("SELECT closed_at FROM lp_year_status WHERE year=?");
        $s->execute([$year]);
        return (bool)$s->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

/** Returns false if the close did not stick — the caller must not claim it did. */
function lpCloseYear(int $year, string $by): bool {
    lpYearStatusEnsure();
    try {
        getDB()->prepare(
            "INSERT INTO lp_year_status (year, closed_at, closed_by) VALUES (?, NOW(), ?)
             ON DUPLICATE KEY UPDATE closed_at=NOW(), closed_by=VALUES(closed_by)"
        )->execute([$year, $by]);
        return true;
    } catch (Exception $e) {
        error_log('lpCloseYear: ' . $e->getMessage());
        return false;
    }
}

/** Closing is reversible on purpose: it is a marker, not a destructive step. */
function lpReopenYear(int $year): bool {
    lpYearStatusEnsure();
    try {
        getDB()->prepare("UPDATE lp_year_status SET closed_at=NULL WHERE year=?")->execute([$year]);
        return true;
    } catch (Exception $e) {
        error_log('lpReopenYear: ' . $e->getMessage());
        return false;
    }
}

/**
 * Carry grants and budget lines from one year into another.
 *
 * Matches on name and updates the amount, rather than refusing when the target
 * year already has rows. It always does: lpEnsureTables() seeds a new year from
 * LP_GRANTS_SEED on the first page load after September, so by the time anyone
 * opens a year-end page the rows exist and a plain "copy if empty" can never
 * run. Existing spending is untouched — only names and budgets are involved.
 *
 * Returns ['grants' => [added, updated], 'lines' => [added, updated], 'error' => string].
 * A swallowed failure here would report "nothing to carry forward" — which reads
 * as success — when in fact nothing could be written at all.
 */
function lpCarryForward(int $from, int $to): array {
    $db  = getDB();
    $out = ['grants' => [0, 0], 'lines' => [0, 0], 'error' => ''];

    foreach ([['lp_grants', 'grants'], ['lp_budget_lines', 'lines']] as $pair) {
        list($table, $key) = $pair;
        try {
            $src = $db->prepare("SELECT name, budget FROM {$table} WHERE year=? AND active=1 ORDER BY name");
            $src->execute([$from]);
            $rows = $src->fetchAll();

            // active is deliberately left alone on update. A line the admin
            // removed from the new year was removed on purpose; carrying
            // budgets forward should not quietly bring it back. New rows take
            // the column default, which is 1.
            $find = $db->prepare("SELECT id FROM {$table} WHERE year=? AND name=? LIMIT 1");
            $upd  = $db->prepare("UPDATE {$table} SET budget=? WHERE id=?");
            $ins  = $db->prepare("INSERT INTO {$table} (name, budget, year) VALUES (?,?,?)");

            foreach ($rows as $r) {
                $find->execute([$to, $r['name']]);
                $id = (int)$find->fetchColumn();
                if ($id) { $upd->execute([$r['budget'], $id]); $out[$key][1]++; }
                else     { $ins->execute([$r['name'], $r['budget'], $to]); $out[$key][0]++; }
            }
        } catch (Exception $e) {
            error_log('lpCarryForward: ' . $e->getMessage());
            $out['error'] = 'Some budgets could not be copied. The problem has been logged.';
        }
    }
    return $out;
}

/** What is still open in a year — the things to settle before closing it. */
function lpYearOutstanding(int $year): array {
    $out = ['vouchers' => [], 'claims' => [], 'collab' => 0];
    try {
        $s = getDB()->prepare(
            "SELECT id, voucher_number, name, status FROM lp_vouchers
             WHERE year=? AND status NOT IN ('paid','rejected','draft')
             ORDER BY created_at"
        );
        $s->execute([$year]);
        $out['vouchers'] = $s->fetchAll();
    } catch (Exception $e) {}

    try {
        require_once __DIR__ . '/exp-db.php';
        foreach (expBatchGetAll('', $year) as $b) {
            if (!in_array($b['status'], ['paid', 'rejected', 'draft'], true)) $out['claims'][] = $b;
        }
    } catch (Exception $e) {}

    try {
        require_once __DIR__ . '/collab-grant-db.php';
        cgEnsureTable();
        $s = getDB()->prepare(
            "SELECT COUNT(*) FROM collab_grant_applications WHERE school_year=? AND status='pending'"
        );
        $s->execute([$year]);
        $out['collab'] = (int)$s->fetchColumn();
    } catch (Exception $e) {}

    return $out;
}

/**
 * Section key => file name stem. Shared by the archive page's per-section
 * downloads and the year-end bundle: with a map in each, a section added later
 * would appear in one and be silently absent from the other.
 */
const LP_ARCHIVE_SECTIONS = [
    'grants'   => 'grants',
    'lines'    => 'budget-lines',
    'vouchers' => 'vouchers',
    'claims'   => 'member-claims',
    'collab'   => 'collaboration-grants',
];

/**
 * One school year's records, section by section, as [headers, rows].
 *
 * Shared by the archive page, its per-section exports and the year-end
 * bundle, so all three describe a year identically. A column cannot appear
 * on screen and go missing from the file someone keeps for seven years.
 */
function archiveSection(string $key, array $grants, array $lines, array $vouchers,
                        array $claims, array $collab): array {
    switch ($key) {
        case 'grants':
            $rows = [];
            foreach ($grants as $g) {
                $rows[] = [$g['name'], number_format((float)$g['budget'], 2, '.', ''),
                           number_format((float)$g['spent'], 2, '.', ''),
                           number_format((float)$g['remaining'], 2, '.', '')];
            }
            return [['Grant', 'Budget', 'Spent', 'Remaining'], $rows];

        case 'lines':
            $rows = [];
            foreach ($lines as $l) {
                $rows[] = [$l['name'], number_format((float)$l['budget'], 2, '.', ''),
                           number_format((float)$l['spent'], 2, '.', ''),
                           number_format((float)$l['remaining'], 2, '.', '')];
            }
            return [['Budget line', 'Budget', 'Spent', 'Remaining'], $rows];

        case 'vouchers':
            $rows = [];
            foreach ($vouchers as $v) {
                $rows[] = [$v['voucher_number'] ?: ('#' . $v['id']), $v['name'],
                           $v['submitted_by'], str_replace('_', ' ', $v['status']),
                           number_format((float)$v['total_amount'], 2, '.', ''),
                           $v['created_at'] ? date('Y-m-d', strtotime($v['created_at'])) : ''];
            }
            return [['Voucher', 'Name', 'Submitted by', 'Status', 'Total', 'Created'], $rows];

        case 'claims':
            $rows = [];
            foreach ($claims as $b) {
                $rows[] = [$b['ref_code'], $b['title'] ?: '', $b['user_name'],
                           str_replace('_', ' ', $b['status']),
                           number_format(expBatchTotal((int)$b['id']), 2, '.', ''),
                           $b['created_at'] ? date('Y-m-d', strtotime($b['created_at'])) : ''];
            }
            return [['Ref', 'Claim', 'Member', 'Status', 'Total', 'Created'], $rows];

        case 'collab':
            $rows = [];
            foreach ($collab as $a) {
                $rows[] = [$a['applicant_name'], $a['applicant_email'], $a['school'],
                           (string)(int)$a['days_requested'], $a['status'],
                           $a['submitted_at'] ? date('Y-m-d', strtotime($a['submitted_at'])) : '',
                           // The follow-through, so a year's record can be
                           // reconciled against the district without going back
                           // into the admin screen one application at a time.
                           !empty($a['atrieve_confirmed']) ? 'Yes' : 'No',
                           $a['invoice_number'] ?? '',
                           // NULL means nobody has costed it; 0.00 is a real
                           // figure and must export as 0.00, not as blank.
                           ($a['release_cost'] ?? null) !== null
                               ? number_format((float)$a['release_cost'], 2, '.', '') : ''];
            }
            return [['Applicant', 'Email', 'School', 'Days', 'Status', 'Submitted',
                     'Atrieve logged', 'Invoice number', 'Release cost'], $rows];
    }
    return [[], []];
}
