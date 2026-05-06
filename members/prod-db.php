<?php
/**
 * prod-db.php — Pro-D portal database helpers
 * Include this in every Pro-D portal page.
 */
require_once __DIR__ . '/db.php';
date_default_timezone_set('America/Vancouver');

define('PROD_ANNUAL_ALLOCATION', 240.00);
define('PROD_CARRYFORWARD_CAP',  720.00);
define('PROD_RECEIPTS_DIR', __DIR__ . '/prod-receipts/');
define('PROD_CATEGORIES', ['conference', 'course', 'materials', 'travel', 'other']);

const PROD_SCHOOLS_DEFAULT = [
    'Smithers Secondary',
    'Walnut Park',
    'Muheim',
    'Silverthorne',
    'Twain Sullivan',
    'Telkwa',
    'Houston Secondary',
    'Learner Support Centre',
];

// ── Table creation ────────────────────────────────────────────────────────────
function prodEnsureTables(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS prod_schools (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(255) NOT NULL,
        fte_count  DECIMAL(6,1) DEFAULT 1.0,
        active     TINYINT(1)   DEFAULT 1,
        created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS prod_roles (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_email  VARCHAR(255) NOT NULL,
        user_name   VARCHAR(255),
        role        VARCHAR(20)  NOT NULL,
        school_id   INT          DEFAULT NULL,
        assigned_by VARCHAR(255),
        created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_email_role (user_email, role),
        INDEX idx_email (user_email),
        INDEX idx_role  (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS prod_allocations (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_email   VARCHAR(255) NOT NULL,
        user_name    VARCHAR(255) NOT NULL,
        year         INT NOT NULL,
        amount       DECIMAL(10,2) NOT NULL,
        source       VARCHAR(50)  DEFAULT 'manual',
        note         TEXT,
        created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (user_email),
        INDEX idx_year  (year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS prod_claims (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        user_email          VARCHAR(255)   NOT NULL,
        user_name           VARCHAR(255)   NOT NULL,
        expense_date        DATE           NOT NULL,
        category            VARCHAR(50)    NOT NULL,
        amount_claimed      DECIMAL(10,2)  NOT NULL,
        description         TEXT,
        receipt_path        VARCHAR(500),
        receipt_filename    VARCHAR(255),
        extracted_vendor    VARCHAR(255),
        extracted_date      DATE,
        extracted_amount    DECIMAL(10,2),
        extraction_flag     VARCHAR(50),
        extraction_concerns TEXT,
        status              VARCHAR(20)    DEFAULT 'pending',
        reviewer_note       TEXT,
        reviewed_by         VARCHAR(255),
        reviewed_at         DATETIME,
        created_at          DATETIME       DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email  (user_email),
        INDEX idx_status (status),
        INDEX idx_date   (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS prod_day_requests (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        user_email           VARCHAR(255)  NOT NULL,
        user_name            VARCHAR(255)  NOT NULL,
        school               VARCHAR(255),
        school_id            INT           DEFAULT NULL,
        request_dates        TEXT          NOT NULL,
        num_days             DECIMAL(4,1)  NOT NULL,
        activity_description TEXT          NOT NULL,
        toc_needed           TINYINT(1)    DEFAULT 0,
        status               VARCHAR(20)   DEFAULT 'pending',
        reviewer_note        TEXT,
        reviewed_by          VARCHAR(255),
        reviewed_at          DATETIME,
        created_at           DATETIME      DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email  (user_email),
        INDEX idx_status (status),
        INDEX idx_school (school_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed schools if none exist
    $count = (int)$db->query("SELECT COUNT(*) FROM prod_schools")->fetchColumn();
    if ($count === 0) {
        $ins = $db->prepare("INSERT INTO prod_schools (name) VALUES (?)");
        foreach (PROD_SCHOOLS_DEFAULT as $school) {
            $ins->execute([$school]);
        }
    }

    // Receipts directory
    if (!is_dir(PROD_RECEIPTS_DIR)) mkdir(PROD_RECEIPTS_DIR, 0750, true);
    $htaccess = PROD_RECEIPTS_DIR . '.htaccess';
    if (!file_exists($htaccess)) file_put_contents($htaccess, "Require all denied\n");
}

// ── Role helpers ──────────────────────────────────────────────────────────────

/** Returns array of roles for an email, e.g. ['exec','teacher'] */
function prodGetRoles(string $email): array {
    // PROD_ADMIN_EMAIL always gets exec regardless of DB
    $roles = [];
    if (defined('PROD_ADMIN_EMAIL') && strtolower(trim($email)) === strtolower(trim(PROD_ADMIN_EMAIL))) {
        $roles[] = 'exec';
    }
    $s = getDB()->prepare("SELECT role FROM prod_roles WHERE user_email=?");
    $s->execute([strtolower(trim($email))]);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $r) {
        if (!in_array($r, $roles)) $roles[] = $r;
    }
    return $roles;
}

function prodHasRole(string $email, string $role): bool {
    return in_array($role, prodGetRoles($email));
}

function prodIsExec(string $email): bool      { return prodHasRole($email, 'exec'); }
function prodIsTreasurer(string $email): bool { return prodHasRole($email, 'treasurer') || prodIsExec($email); }
function prodIsSiteRep(string $email): bool   { return prodHasRole($email, 'site_rep'); }

/** Returns the school_id assigned to a site rep, or null */
function prodSiteRepSchoolId(string $email): ?int {
    $s = getDB()->prepare("SELECT school_id FROM prod_roles WHERE user_email=? AND role='site_rep'");
    $s->execute([strtolower(trim($email))]);
    $id = $s->fetchColumn();
    return $id !== false ? (int)$id : null;
}

/** Returns school name for a site rep */
function prodSiteRepSchoolName(string $email): ?string {
    $id = prodSiteRepSchoolId($email);
    if (!$id) return null;
    $s = getDB()->prepare("SELECT name FROM prod_schools WHERE id=?");
    $s->execute([$id]);
    return $s->fetchColumn() ?: null;
}

/** Legacy alias kept for existing pages */
function prodIsAdmin(string $email): bool { return prodIsExec($email); }

// ── Balance helpers ───────────────────────────────────────────────────────────
function prodGetBalance(string $email): array {
    $db = getDB();

    $s = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM prod_allocations WHERE user_email=?");
    $s->execute([$email]); $allocated = (float)$s->fetchColumn();

    $s = $db->prepare("SELECT COALESCE(SUM(amount_claimed),0) FROM prod_claims WHERE user_email=? AND status='approved'");
    $s->execute([$email]); $spent = (float)$s->fetchColumn();

    $s = $db->prepare("SELECT COALESCE(SUM(amount_claimed),0) FROM prod_claims WHERE user_email=? AND status='pending'");
    $s->execute([$email]); $pending = (float)$s->fetchColumn();

    return [
        'allocated' => $allocated,
        'spent'     => $spent,
        'pending'   => $pending,
        'balance'   => $allocated - $spent,
    ];
}

function prodSeedTrialAllocation(string $email, string $name): void {
    $db = getDB();
    $s  = $db->prepare("SELECT COUNT(*) FROM prod_allocations WHERE user_email=?");
    $s->execute([$email]);
    if ((int)$s->fetchColumn() === 0) {
        $db->prepare("INSERT INTO prod_allocations (user_email, user_name, year, amount, source, note)
                      VALUES (?, ?, ?, ?, 'trial', 'Auto-seeded trial allocation — replace with real opening balance')")
           ->execute([$email, $name, (int)date('Y'), PROD_ANNUAL_ALLOCATION]);
    }
}

// ── Pending counts ────────────────────────────────────────────────────────────
function prodPendingClaims(): int {
    return (int)getDB()->query("SELECT COUNT(*) FROM prod_claims WHERE status='pending'")->fetchColumn();
}

function prodPendingDayRequests(?int $schoolId = null): int {
    if ($schoolId) {
        $s = getDB()->prepare("SELECT COUNT(*) FROM prod_day_requests WHERE status='pending' AND school_id=?");
        $s->execute([$schoolId]);
        return (int)$s->fetchColumn();
    }
    return (int)getDB()->query("SELECT COUNT(*) FROM prod_day_requests WHERE status='pending'")->fetchColumn();
}

// ── School helpers ────────────────────────────────────────────────────────────
function prodGetSchools(bool $activeOnly = true): array {
    $sql = "SELECT * FROM prod_schools" . ($activeOnly ? " WHERE active=1" : "") . " ORDER BY name";
    return getDB()->query($sql)->fetchAll();
}
