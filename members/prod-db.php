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

// ── Table creation ────────────────────────────────────────────────────────────
function prodEnsureTables(): void {
    $db = getDB();

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
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure receipts directory exists and is protected from direct web access
    if (!is_dir(PROD_RECEIPTS_DIR)) {
        mkdir(PROD_RECEIPTS_DIR, 0750, true);
    }
    $htaccess = PROD_RECEIPTS_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }
}

// ── Balance helpers ───────────────────────────────────────────────────────────
function prodGetBalance(string $email): array {
    $db = getDB();

    $s = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM prod_allocations WHERE user_email=?");
    $s->execute([$email]);
    $allocated = (float)$s->fetchColumn();

    $s = $db->prepare("SELECT COALESCE(SUM(amount_claimed),0) FROM prod_claims WHERE user_email=? AND status='approved'");
    $s->execute([$email]);
    $spent = (float)$s->fetchColumn();

    $s = $db->prepare("SELECT COALESCE(SUM(amount_claimed),0) FROM prod_claims WHERE user_email=? AND status='pending'");
    $s->execute([$email]);
    $pending = (float)$s->fetchColumn();

    return [
        'allocated' => $allocated,
        'spent'     => $spent,
        'pending'   => $pending,
        'balance'   => $allocated - $spent,
    ];
}

// Auto-seed a trial allocation so the portal feels real on first visit
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

// ── Role helpers ──────────────────────────────────────────────────────────────
function prodIsAdmin(string $email): bool {
    return defined('PROD_ADMIN_EMAIL') && strtolower(trim($email)) === strtolower(trim(PROD_ADMIN_EMAIL));
}

// ── Pending counts ────────────────────────────────────────────────────────────
function prodPendingClaims(): int {
    $s = getDB()->query("SELECT COUNT(*) FROM prod_claims WHERE status='pending'");
    return (int)$s->fetchColumn();
}

function prodPendingDayRequests(): int {
    $s = getDB()->query("SELECT COUNT(*) FROM prod_day_requests WHERE status='pending'");
    return (int)$s->fetchColumn();
}
