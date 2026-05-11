<?php
/**
 * lp-db.php — Local President Expense Tracker database helpers
 */
require_once __DIR__ . '/db.php';
date_default_timezone_set('America/Vancouver');

define('LP_RECEIPTS_DIR', __DIR__ . '/lp-receipts/');
define('LP_MILEAGE_RATE', 0.70); // $/km — update annually

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

    // Receipts directory
    if (!is_dir(LP_RECEIPTS_DIR)) mkdir(LP_RECEIPTS_DIR, 0750, true);
    $htaccess = LP_RECEIPTS_DIR . '.htaccess';
    if (!file_exists($htaccess)) file_put_contents($htaccess, "Require all denied\n");
}

// ── Access helpers ────────────────────────────────────────────────────────────
function lpCanCreate(string $email): bool {
    return prodIsExec($email);
}

function lpCanView(string $email): bool {
    return prodIsExec($email) || prodIsTreasurer($email);
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

function lpGetVouchers(string $email = '', string $status = ''): array {
    $db = getDB();
    $sql = "SELECT v.*,
                COALESCE(SUM(e.travel_amt + e.meals + e.gifts + e.misc + e.office + e.phone), 0) AS total_amount,
                COUNT(e.id) AS expense_count
            FROM lp_vouchers v
            LEFT JOIN lp_expenses e ON e.voucher_id = v.id";
    $params = [];
    $where  = [];
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

/** Returns emails of all treasurers; falls back to exec email */
function lpGetTreasurerEmails(): array {
    $emails = [];
    $s = getDB()->query("SELECT user_email FROM prod_roles WHERE role='treasurer'");
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $e) $emails[] = $e;
    if (empty($emails) && defined('PROD_ADMIN_EMAIL')) $emails[] = PROD_ADMIN_EMAIL;
    return array_unique($emails);
}

function lpGetExpensesByGrant(int $grantId): array {
    $s = getDB()->prepare(
        "SELECT e.expense_date, e.description, e.travel_km, e.travel_amt,
                e.meals, e.gifts, e.misc, e.office, e.phone,
                v.name AS voucher_name, v.voucher_number, v.id AS voucher_id, v.status AS voucher_status
         FROM lp_expenses e
         JOIN lp_vouchers v ON v.id = e.voucher_id
         WHERE e.grant_id=?
         ORDER BY e.expense_date, e.id"
    );
    $s->execute([$grantId]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
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
