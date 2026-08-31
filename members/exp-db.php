<?php
/**
 * exp-db.php — Expense Reimbursement Portal database helpers
 * Include this in every Expense portal page.
 */
require_once __DIR__ . '/db.php';
date_default_timezone_set('America/Vancouver');

define('EXP_RECEIPTS_DIR', __DIR__ . '/exp-receipts/');
define('EXP_CATEGORIES', ['meals', 'travel', 'supplies', 'conference', 'accommodation', 'other']);

// ── Table creation ─────────────────────────────────────────────────────────────
function expEnsureTables(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS exp_roles (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_email  VARCHAR(255) NOT NULL,
        user_name   VARCHAR(255),
        role        VARCHAR(20)  NOT NULL,
        assigned_by VARCHAR(255),
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_email_role (user_email, role),
        INDEX idx_email (user_email),
        INDEX idx_role  (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS exp_expenses (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        ref_code            VARCHAR(20)   NOT NULL UNIQUE,
        user_email          VARCHAR(255)  NOT NULL,
        user_name           VARCHAR(255)  NOT NULL,
        expense_date        DATE          NOT NULL,
        category            VARCHAR(50)   NOT NULL,
        amount              DECIMAL(10,2) NOT NULL,
        description         TEXT          NOT NULL,
        receipt_path        VARCHAR(500),
        receipt_filename    VARCHAR(255),
        extracted_vendor    VARCHAR(255),
        extracted_date      DATE,
        extracted_amount    DECIMAL(10,2),
        extraction_flag     VARCHAR(50),
        extraction_concerns TEXT,
        status              VARCHAR(30)   DEFAULT 'pending',
        signer1_email       VARCHAR(255),
        signer1_name        VARCHAR(255),
        signer1_at          DATETIME,
        signer1_note        TEXT,
        signer2_email       VARCHAR(255),
        signer2_name        VARCHAR(255),
        signer2_at          DATETIME,
        signer2_note        TEXT,
        rejected_by_email   VARCHAR(255),
        rejected_by_name    VARCHAR(255),
        rejected_at         DATETIME,
        rejection_note      TEXT,
        paid_at             DATETIME,
        paid_by_email       VARCHAR(255),
        paid_by_name        VARCHAR(255),
        payment_note        TEXT,
        created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user   (user_email),
        INDEX idx_status (status),
        INDEX idx_date   (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS exp_upload_tokens (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        token      VARCHAR(64)  NOT NULL UNIQUE,
        expense_id INT          NOT NULL,
        created_by VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token   (token),
        INDEX idx_expense (expense_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS exp_pending_receipts (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        expense_id    INT          NOT NULL,
        saved_path    VARCHAR(500) NOT NULL,
        original_name VARCHAR(255),
        scan_json     TEXT,
        claimed       TINYINT(1)   DEFAULT 0,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_expense (expense_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Receipts directory + .htaccess block
    if (!is_dir(EXP_RECEIPTS_DIR)) {
        mkdir(EXP_RECEIPTS_DIR, 0750, true);
    }
    $htaccess = EXP_RECEIPTS_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Require all denied\n");
    }

    expEnsureOnBehalfColumns();
}

// ── Payment tracking columns (payment_ref, payment_date) ───────────────────────
function expEnsurePaymentColumns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $tables = ['exp_expenses', 'exp_batches'];
    $cols   = [
        'payment_ref'  => "VARCHAR(255)",
        'payment_date' => "DATE",
    ];
    foreach ($tables as $table) {
        foreach ($cols as $col => $type) {
            try {
                $exists = getDB()->query(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$table}' AND COLUMN_NAME='{$col}'"
                )->fetchColumn();
                if (!$exists) {
                    getDB()->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$type}");
                }
            } catch (Exception $e) { /* already exists */ }
        }
    }
}

// ── "Submitted on behalf of" columns ───────────────────────────────────────────
function expEnsureOnBehalfColumns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $cols = [
        'submitted_by_email' => "VARCHAR(255)",
        'submitted_by_name'  => "VARCHAR(255)",
    ];
    foreach ($cols as $col => $type) {
        try {
            $exists = getDB()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='exp_expenses' AND COLUMN_NAME='{$col}'"
            )->fetchColumn();
            if (!$exists) {
                getDB()->exec("ALTER TABLE exp_expenses ADD COLUMN {$col} {$type}");
            }
        } catch (Exception $e) { /* already exists */ }
    }
}

// Can this person submit an expense on behalf of another member?
// Limited to those with signing authority: President, VP, Treasurer.
function expCanSubmitOnBehalf(string $email): bool {
    return expIsPresident($email) || expIsVP($email) || expIsTreasurer($email);
}

// ── Multi-item Expense Claims ("batches") ──────────────────────────────────────
// A batch groups several individual expense items (each with its own date,
// category, amount and receipt) into ONE submission. The whole batch goes
// through a single Treasurer + second-signer approval and a single e-transfer,
// rather than signing and paying out each receipt separately. This is the
// preferred way for members to submit several receipts from the same trip
// or activity (mirrors the LP voucher workflow).

function expBatchEnsureTables(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS exp_batches (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        ref_code            VARCHAR(20)   NOT NULL UNIQUE,
        title               VARCHAR(255),
        user_email          VARCHAR(255)  NOT NULL,
        user_name           VARCHAR(255)  NOT NULL,
        submitted_by_email  VARCHAR(255),
        submitted_by_name   VARCHAR(255),
        status              VARCHAR(30)   DEFAULT 'draft',
        signer1_email       VARCHAR(255),
        signer1_name        VARCHAR(255),
        signer1_at          DATETIME,
        signer1_note        TEXT,
        signer2_email       VARCHAR(255),
        signer2_name        VARCHAR(255),
        signer2_at          DATETIME,
        signer2_note        TEXT,
        rejected_by_email   VARCHAR(255),
        rejected_by_name    VARCHAR(255),
        rejected_at         DATETIME,
        rejection_note      TEXT,
        paid_at             DATETIME,
        paid_by_email       VARCHAR(255),
        paid_by_name        VARCHAR(255),
        payment_note        TEXT,
        submitted_at        DATETIME,
        created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user   (user_email),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS exp_batch_items (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        batch_id            INT           NOT NULL,
        expense_date        DATE,
        category            VARCHAR(50)   DEFAULT 'other',
        amount              DECIMAL(10,2) NOT NULL DEFAULT 0,
        description         TEXT,
        receipt_path        VARCHAR(500),
        receipt_filename    VARCHAR(255),
        extracted_vendor    VARCHAR(255),
        extracted_date      DATE,
        extracted_amount    DECIMAL(10,2),
        extraction_flag     VARCHAR(50),
        extraction_concerns TEXT,
        sort_order          INT DEFAULT 0,
        created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_batch (batch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    expEnsureTables(); // shares EXP_RECEIPTS_DIR with single-item expenses
}

function expBatchGenerateRefCode(): string {
    $year = date('Y');
    $s = getDB()->prepare("SELECT COUNT(*) FROM exp_batches WHERE ref_code LIKE ?");
    $s->execute(["BAT-{$year}-%"]);
    $n = (int)$s->fetchColumn() + 1;
    return 'BAT-' . $year . '-' . str_pad($n, 4, '0', STR_PAD_LEFT);
}

function expBatchGet(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM exp_batches WHERE id=? LIMIT 1");
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ?: null;
}

function expBatchGetItems(int $batchId): array {
    $s = getDB()->prepare("SELECT * FROM exp_batch_items WHERE batch_id=? ORDER BY sort_order, id");
    $s->execute([$batchId]);
    return $s->fetchAll();
}

function expBatchTotal(int $batchId): float {
    $s = getDB()->prepare("SELECT COALESCE(SUM(amount),0) FROM exp_batch_items WHERE batch_id=?");
    $s->execute([$batchId]);
    return (float)$s->fetchColumn();
}

/**
 * Get the member's existing draft batch, or create a new one.
 * $targetEmail/$targetName: who the batch is FOR (may differ from the
 * logged-in member when an exec submits on someone else's behalf).
 */
function expBatchGetOrCreateDraft(string $targetEmail, string $targetName, string $submittedByEmail = '', string $submittedByName = ''): array {
    $targetEmail = strtolower(trim($targetEmail));
    $db = getDB();

    $where  = "user_email = ? AND status = 'draft'";
    $params = [$targetEmail];
    if ($submittedByEmail) {
        $where   .= " AND submitted_by_email = ?";
        $params[] = strtolower(trim($submittedByEmail));
    } else {
        $where .= " AND (submitted_by_email IS NULL OR submitted_by_email = '')";
    }

    $s = $db->prepare("SELECT * FROM exp_batches WHERE {$where} ORDER BY id DESC LIMIT 1");
    $s->execute($params);
    $batch = $s->fetch();
    if ($batch) return $batch;

    $refCode = expBatchGenerateRefCode();
    $db->prepare(
        "INSERT INTO exp_batches (ref_code, user_email, user_name, submitted_by_email, submitted_by_name, status)
         VALUES (?,?,?,?,?,'draft')"
    )->execute([
        $refCode,
        $targetEmail,
        $targetName,
        $submittedByEmail ? strtolower(trim($submittedByEmail)) : null,
        $submittedByName  ?: null,
    ]);

    return expBatchGet((int)$db->lastInsertId());
}

function expBatchGetByMember(string $email): array {
    $s = getDB()->prepare("SELECT * FROM exp_batches WHERE user_email=? ORDER BY created_at DESC");
    $s->execute([strtolower(trim($email))]);
    return $s->fetchAll();
}

function expBatchGetAll(string $status = ''): array {
    if ($status) {
        $s = getDB()->prepare("SELECT * FROM exp_batches WHERE status=? ORDER BY submitted_at ASC, created_at ASC");
        $s->execute([$status]);
    } else {
        $s = getDB()->query("SELECT * FROM exp_batches ORDER BY created_at DESC");
    }
    return $s->fetchAll();
}

// ── Batch workflow transitions (mirrors single-item exp workflow) ─────────────

function expBatchSubmit(int $id): void {
    $b = expBatchGet($id);
    if (!$b) throw new RuntimeException("Claim #{$id} not found.");
    if ($b['status'] !== 'draft') throw new RuntimeException("Only draft claims can be submitted.");
    if (!expBatchGetItems($id)) throw new RuntimeException("Add at least one expense item before submitting.");

    getDB()->prepare(
        "UPDATE exp_batches SET status='pending', submitted_at=NOW() WHERE id=?"
    )->execute([$id]);
}

function expBatchApproveAsSigner1(int $id, string $email, string $name, string $note = ''): void {
    $b = expBatchGet($id);
    if (!$b) throw new RuntimeException("Claim #{$id} not found.");
    if ($b['status'] !== 'pending') {
        throw new RuntimeException("Cannot approve as Signer 1: claim is not awaiting Treasurer approval (current: {$b['status']}).");
    }
    if (!expIsTreasurer($email)) {
        throw new RuntimeException("Only a Treasurer can approve as Signer 1.");
    }
    getDB()->prepare(
        "UPDATE exp_batches SET status='signer1_approved',
            signer1_email=?, signer1_name=?, signer1_at=NOW(), signer1_note=?
         WHERE id=?"
    )->execute([$email, $name, $note ?: null, $id]);
}

function expBatchReject(int $id, string $email, string $name, string $note): void {
    $b = expBatchGet($id);
    if (!$b) throw new RuntimeException("Claim #{$id} not found.");
    if (!in_array($b['status'], ['pending', 'signer1_approved'])) {
        throw new RuntimeException("Cannot reject: claim is not in a rejectable state (current: {$b['status']}).");
    }
    if (!expIsTreasurer($email) && !expIsEligibleSigner2($email)) {
        throw new RuntimeException("You do not have permission to reject this claim.");
    }
    if (!$note) throw new RuntimeException("A rejection note is required.");

    getDB()->prepare(
        "UPDATE exp_batches SET status='rejected',
            rejected_by_email=?, rejected_by_name=?, rejected_at=NOW(), rejection_note=?
         WHERE id=?"
    )->execute([$email, $name, $note, $id]);
}

function expBatchApproveAsSigner2(int $id, string $email, string $name, string $note = ''): void {
    $b = expBatchGet($id);
    if (!$b) throw new RuntimeException("Claim #{$id} not found.");
    if ($b['status'] !== 'signer1_approved') {
        throw new RuntimeException("Cannot approve as Signer 2: claim must have Treasurer approval first (current: {$b['status']}).");
    }
    if (!expIsEligibleSigner2($email)) {
        throw new RuntimeException("Only a VP, President, or Admin can approve as Signer 2.");
    }
    if ($email === $b['signer1_email']) {
        throw new RuntimeException("Signer 2 cannot be the same person as Signer 1.");
    }
    getDB()->prepare(
        "UPDATE exp_batches SET status='signer2_approved',
            signer2_email=?, signer2_name=?, signer2_at=NOW(), signer2_note=?
         WHERE id=?"
    )->execute([$email, $name, $note ?: null, $id]);
}

function expBatchMarkPaid(int $id, string $email, string $name, string $paymentNote, string $paymentRef = '', string $paymentDate = ''): void {
    $b = expBatchGet($id);
    if (!$b) throw new RuntimeException("Claim #{$id} not found.");
    if ($b['status'] !== 'signer2_approved') {
        throw new RuntimeException("Cannot mark as paid: claim must have both signatures first (current: {$b['status']}).");
    }
    if (!expIsTreasurer($email)) {
        throw new RuntimeException("Only a Treasurer can mark a claim as paid.");
    }
    $pd = ($paymentDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) ? $paymentDate : date('Y-m-d');
    getDB()->prepare(
        "UPDATE exp_batches SET status='paid',
            paid_at=NOW(), paid_by_email=?, paid_by_name=?, payment_note=?, payment_ref=?, payment_date=?
         WHERE id=?"
    )->execute([$email, $name, $paymentNote ?: null, $paymentRef ?: null, $pd, $id]);
}

// ── Batch email notifications ──────────────────────────────────────────────────

function _expBatchDetailBox(array $b, float $total, int $itemCount): string {
    $html  = '<div class="detail-box">';
    $html .= '<div class="row"><span class="lbl">Reference</span><span class="val">' . htmlspecialchars($b['ref_code']) . '</span></div>';
    $html .= '<div class="row"><span class="lbl">Member</span><span class="val">' . htmlspecialchars($b['user_name']) . '</span></div>';
    $html .= '<div class="row"><span class="lbl">Email</span><span class="val">' . htmlspecialchars($b['user_email']) . '</span></div>';
    if (!empty($b['submitted_by_email']) && strtolower($b['submitted_by_email']) !== strtolower($b['user_email'])) {
        $html .= '<div class="row"><span class="lbl">Submitted by</span><span class="val">' . htmlspecialchars($b['submitted_by_name'] ?: $b['submitted_by_email']) . ' (on behalf of member)</span></div>';
    }
    if (!empty($b['title'])) {
        $html .= '<div class="row"><span class="lbl">Description</span><span class="val">' . htmlspecialchars($b['title']) . '</span></div>';
    }
    $html .= '<div class="row"><span class="lbl">Items</span><span class="val">' . $itemCount . ' receipt' . ($itemCount === 1 ? '' : 's') . '</span></div>';
    $html .= '<div class="row"><span class="lbl">Total</span><span class="val">$' . number_format($total, 2) . '</span></div>';
    $html .= '</div>';
    return $html;
}

function expBatchEmailSubmitted(array $b, float $total, int $itemCount): void {
    $onBehalf = !empty($b['submitted_by_email'])
             && strtolower($b['submitted_by_email']) !== strtolower($b['user_email']);

    $intro = $onBehalf
        ? '<p>An expense claim was submitted on your behalf by <strong>' . htmlspecialchars($b['submitted_by_name'] ?: $b['submitted_by_email']) . '</strong> and is awaiting review by the BVTU Treasurer.</p>'
        : '<p>Your expense claim has been submitted and is awaiting review by the BVTU Treasurer.</p>';
    $body = $intro
          . _expBatchDetailBox($b, $total, $itemCount)
          . '<p>You will receive an email when it has been reviewed.</p>'
          . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-claim-view.php?id=' . (int)$b['id'] . '">View Your Claim</a></p>';
    expNotify(
        $b['user_email'],
        'Expense Claim Submitted — ' . $b['ref_code'],
        _expHtmlWrap('Expense Claim Submitted', $body)
    );

    $submitterLine = $onBehalf
        ? '<p><strong>' . htmlspecialchars($b['submitted_by_name'] ?: $b['submitted_by_email']) . '</strong> has submitted a new expense claim on behalf of <strong>' . htmlspecialchars($b['user_name']) . '</strong> (' . $itemCount . ' item' . ($itemCount === 1 ? '' : 's') . ', $' . number_format($total, 2) . ') for your review.</p>'
        : '<p><strong>' . htmlspecialchars($b['user_name']) . '</strong> has submitted a new expense claim (' . $itemCount . ' item' . ($itemCount === 1 ? '' : 's') . ', $' . number_format($total, 2) . ') for your review.</p>';
    $treasurerBody = $submitterLine
                   . _expBatchDetailBox($b, $total, $itemCount)
                   . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-claim-review.php">Review in Expense Portal</a></p>';
    foreach (expGetTreasurerEmails() as $email) {
        expNotify(
            $email,
            'New Expense Claim for Review — ' . $b['ref_code'],
            _expHtmlWrap('New Expense Claim Submitted', $treasurerBody)
        );
    }

}

function expBatchEmailSigner1Approved(array $b, float $total, int $itemCount): void {
    $body = '<p>The following expense claim has been approved by the BVTU Treasurer (<strong>' . htmlspecialchars($b['signer1_name']) . '</strong>) and now requires a second signature.</p>'
          . _expBatchDetailBox($b, $total, $itemCount)
          . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-claim-review.php">Review &amp; Sign in Expense Portal</a></p>';
    foreach (expGetSigner2Emails() as $email) {
        expNotify(
            $email,
            'Second Signature Required — ' . $b['ref_code'],
            _expHtmlWrap('Second Signature Required', $body)
        );
    }
}

function expBatchEmailSigner2Approved(array $b, float $total, int $itemCount): void {
    $memberBody = '<p>Great news! Your expense claim has been authorized by two signing officers.</p>'
                . _expBatchDetailBox($b, $total, $itemCount)
                . '<p>The Treasurer will send a single <strong>e-transfer</strong> for the full amount to <strong>' . htmlspecialchars($b['user_email']) . '</strong> shortly. Use <code>' . htmlspecialchars($b['ref_code']) . '</code> as the reference if prompted.</p>'
                . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-claim-view.php?id=' . (int)$b['id'] . '">View Your Claim</a></p>';
    expNotify(
        $b['user_email'],
        'Expense Claim Authorized — ' . $b['ref_code'] . ' — E-transfer coming',
        _expHtmlWrap('Expense Claim Authorized — Payment Coming', $memberBody)
    );

    $treasurerBody = '<p>This expense claim has received both required signatures and is <strong>ready for payment</strong>.</p>'
                   . _expBatchDetailBox($b, $total, $itemCount)
                   . '<div class="detail-box" style="background:#f0fdf4;border-color:#bbf7d0;">'
                   . '<div class="row"><span class="lbl">Send e-transfer to</span><span class="val">' . htmlspecialchars($b['user_email']) . '</span></div>'
                   . '<div class="row"><span class="lbl">Amount</span><span class="val">$' . number_format($total, 2) . '</span></div>'
                   . '<div class="row"><span class="lbl">Message / Ref</span><span class="val">' . htmlspecialchars($b['ref_code']) . '</span></div>'
                   . '</div>'
                   . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-claim-review.php">Open Treasurer Dashboard</a></p>';
    foreach (expGetTreasurerEmails() as $email) {
        expNotify(
            $email,
            'Ready to Pay — ' . $b['ref_code'],
            _expHtmlWrap('Expense Claim Ready for Payment', $treasurerBody)
        );
    }
}

function expBatchEmailRejected(array $b): void {
    $body = '<p>Your expense claim has been rejected.</p>'
          . '<div class="detail-box">'
          . '<div class="row"><span class="lbl">Reference</span><span class="val">' . htmlspecialchars($b['ref_code']) . '</span></div>'
          . '<div class="row"><span class="lbl">Rejected by</span><span class="val">' . htmlspecialchars($b['rejected_by_name'] ?? '—') . '</span></div>'
          . '<div class="row"><span class="lbl">Reason</span><span class="val">' . htmlspecialchars($b['rejection_note'] ?? '—') . '</span></div>'
          . '</div>'
          . '<p>If you have questions, please contact the BVTU Treasurer.</p>'
          . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-claim-view.php?id=' . (int)$b['id'] . '">View Your Claim</a></p>';
    expNotify(
        $b['user_email'],
        'Expense Claim Rejected — ' . $b['ref_code'],
        _expHtmlWrap('Expense Claim Rejected', $body)
    );
}

function expBatchEmailPaid(array $b, float $total): void {
    $body = '<p>Your expense claim payment has been sent!</p>'
          . '<div class="detail-box">'
          . '<div class="row"><span class="lbl">Reference</span><span class="val">' . htmlspecialchars($b['ref_code']) . '</span></div>'
          . '<div class="row"><span class="lbl">Amount</span><span class="val">$' . number_format($total, 2) . '</span></div>'
          . '<div class="row"><span class="lbl">Paid by</span><span class="val">' . htmlspecialchars($b['paid_by_name'] ?? '—') . '</span></div>'
          . '<div class="row"><span class="lbl">Date</span><span class="val">' . ($b['paid_at'] ? date('F j, Y', strtotime($b['paid_at'])) : '—') . '</span></div>'
          . '</div>'
          . '<p>Watch for an Interac e-transfer for the full amount. Use <code>' . htmlspecialchars($b['ref_code']) . '</code> as the reference if asked.</p>'
          . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-claim-view.php?id=' . (int)$b['id'] . '">View Your Claim</a></p>';
    expNotify(
        $b['user_email'],
        'Expense Claim Paid — ' . $b['ref_code'],
        _expHtmlWrap('Payment Sent', $body)
    );
}

// ── Ref code generation ────────────────────────────────────────────────────────
function expGenerateRefCode(): string {
    $year = date('Y');
    $s = getDB()->prepare("SELECT COUNT(*) FROM exp_expenses WHERE ref_code LIKE ?");
    $s->execute(["EXP-{$year}-%"]);
    $n = (int)$s->fetchColumn() + 1;
    return 'EXP-' . $year . '-' . str_pad($n, 4, '0', STR_PAD_LEFT);
}

// ── Role helpers ───────────────────────────────────────────────────────────────

function expGetRoles(string $email): array {
    $roles = [];
    if (expIsAdmin($email)) {
        $roles[] = 'admin';
    }
    $s = getDB()->prepare("SELECT role FROM exp_roles WHERE user_email=?");
    $s->execute([strtolower(trim($email))]);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $r) {
        if (!in_array($r, $roles)) {
            $roles[] = $r;
        }
    }
    return $roles;
}

function expHasRole(string $email, string $role): bool {
    $s = getDB()->prepare("SELECT COUNT(*) FROM exp_roles WHERE user_email=? AND role=?");
    $s->execute([strtolower(trim($email)), $role]);
    return (int)$s->fetchColumn() > 0;
}

function expHasExecRole(string $email, string $role): bool {
    $s = getDB()->prepare("SELECT COUNT(*) FROM exec_roles WHERE user_email=? AND role=?");
    $s->execute([strtolower(trim($email)), $role]);
    return (int)$s->fetchColumn() > 0;
}

function expIsAdmin(string $email): bool {
    if (defined('EXPENSE_ADMIN_EMAIL') && strtolower(trim($email)) === strtolower(trim(EXPENSE_ADMIN_EMAIL))) {
        return true;
    }
    if (defined('PROD_ADMIN_EMAIL') && strtolower(trim($email)) === strtolower(trim(PROD_ADMIN_EMAIL))) {
        return true;
    }
    return expHasRole($email, 'admin');
}

function expIsTreasurer(string $email): bool {
    // Checks exp_roles OR exec_roles (treasurer slug matches in both)
    return expHasRole($email, 'treasurer') || expHasExecRole($email, 'treasurer') || expIsAdmin($email);
}

function expIsVP(string $email): bool {
    // exp_roles uses 'vp'; exec_roles uses 'vice_president'
    return expHasRole($email, 'vp') || expHasExecRole($email, 'vice_president');
}

function expIsPresident(string $email): bool {
    // exp_roles uses 'president'; exec_roles uses 'president'
    return expHasRole($email, 'president') || expHasExecRole($email, 'president') || expIsAdmin($email);
}

function expIsEligibleSigner2(string $email): bool {
    // Second signer for regular member expenses is the Local President
    return expIsAdmin($email);
}

function expCanReview(string $email): bool {
    return expIsTreasurer($email) || expIsEligibleSigner2($email) || expIsAdmin($email);
}

// ── Expense CRUD ───────────────────────────────────────────────────────────────

function expCreate(
    string $userEmail,
    string $userName,
    string $expenseDate,
    string $category,
    float $amount,
    string $description,
    string $receiptPath = '',
    string $receiptFilename = '',
    array $scanData = [],
    string $submittedByEmail = '',
    string $submittedByName = ''
): int {
    $db      = getDB();
    $refCode = expGenerateRefCode();
    expEnsureOnBehalfColumns();

    $db->prepare(
        "INSERT INTO exp_expenses
         (ref_code, user_email, user_name, expense_date, category, amount, description,
          receipt_path, receipt_filename, extracted_vendor, extracted_date, extracted_amount,
          extraction_flag, extraction_concerns, submitted_by_email, submitted_by_name, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')"
    )->execute([
        $refCode,
        strtolower(trim($userEmail)),
        $userName,
        $expenseDate,
        $category,
        round($amount, 2),
        $description,
        $receiptPath    ?: null,
        $receiptFilename ?: null,
        $scanData['vendor']   ?? null,
        $scanData['date']     ?? null,
        isset($scanData['amount']) && is_numeric($scanData['amount'])
            ? round((float)$scanData['amount'], 2)
            : null,
        $scanData['flag']     ?? null,
        $scanData['concerns'] ?? null,
        $submittedByEmail ? strtolower(trim($submittedByEmail)) : null,
        $submittedByName  ?: null,
    ]);

    return (int)$db->lastInsertId();
}

function expGet(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM exp_expenses WHERE id=? LIMIT 1");
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ?: null;
}

function expGetByMember(string $email): array {
    $s = getDB()->prepare(
        "SELECT * FROM exp_expenses WHERE user_email=? ORDER BY created_at DESC"
    );
    $s->execute([strtolower(trim($email))]);
    return $s->fetchAll();
}

function expGetAll(array $filters = [], int $limit = 200, int $offset = 0): array {
    $where  = ['1=1'];
    $params = [];

    if (!empty($filters['status'])) {
        $where[]  = 'status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['email'])) {
        $where[]  = 'user_email = ?';
        $params[] = strtolower(trim($filters['email']));
    }
    if (!empty($filters['date_from'])) {
        $where[]  = 'expense_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[]  = 'expense_date <= ?';
        $params[] = $filters['date_to'];
    }
    if (!empty($filters['category'])) {
        $where[]  = 'category = ?';
        $params[] = $filters['category'];
    }
    if (!empty($filters['email_search'])) {
        $where[]  = 'user_email LIKE ?';
        $params[] = '%' . $filters['email_search'] . '%';
    }

    $sql = "SELECT * FROM exp_expenses WHERE " . implode(' AND ', $where)
         . " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    $s = getDB()->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

function expCountAll(array $filters = []): int {
    $where  = ['1=1'];
    $params = [];

    if (!empty($filters['status'])) {
        $where[]  = 'status = ?';
        $params[] = $filters['status'];
    }
    if (!empty($filters['email'])) {
        $where[]  = 'user_email = ?';
        $params[] = strtolower(trim($filters['email']));
    }
    if (!empty($filters['date_from'])) {
        $where[]  = 'expense_date >= ?';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[]  = 'expense_date <= ?';
        $params[] = $filters['date_to'];
    }
    if (!empty($filters['category'])) {
        $where[]  = 'category = ?';
        $params[] = $filters['category'];
    }

    $sql = "SELECT COUNT(*) FROM exp_expenses WHERE " . implode(' AND ', $where);
    $s   = getDB()->prepare($sql);
    $s->execute($params);
    return (int)$s->fetchColumn();
}

function expGetPaid(array $filters = []): array {
    $filters['status'] = 'paid';
    return expGetAll($filters);
}

// ── Workflow transitions ───────────────────────────────────────────────────────

function expApproveAsSigner1(int $id, string $email, string $name, string $note = ''): void {
    $exp = expGet($id);
    if (!$exp) {
        throw new RuntimeException("Expense #{$id} not found.");
    }
    if ($exp['status'] !== 'pending') {
        throw new RuntimeException("Cannot approve as Signer 1: expense is not in 'pending' state (current: {$exp['status']}).");
    }
    if (!expIsTreasurer($email)) {
        throw new RuntimeException("Only a Treasurer can approve as Signer 1.");
    }

    getDB()->prepare(
        "UPDATE exp_expenses SET
            status       = 'signer1_approved',
            signer1_email = ?,
            signer1_name  = ?,
            signer1_at    = NOW(),
            signer1_note  = ?
         WHERE id = ?"
    )->execute([$email, $name, $note ?: null, $id]);
}

function expReject(int $id, string $email, string $name, string $note): void {
    $exp = expGet($id);
    if (!$exp) {
        throw new RuntimeException("Expense #{$id} not found.");
    }
    if (!in_array($exp['status'], ['pending', 'signer1_approved'])) {
        throw new RuntimeException("Cannot reject: expense is not in a rejectable state (current: {$exp['status']}).");
    }
    if (!expIsTreasurer($email) && !expIsEligibleSigner2($email)) {
        throw new RuntimeException("You do not have permission to reject this expense.");
    }
    if (!$note) {
        throw new RuntimeException("A rejection note is required.");
    }

    getDB()->prepare(
        "UPDATE exp_expenses SET
            status             = 'rejected',
            rejected_by_email  = ?,
            rejected_by_name   = ?,
            rejected_at        = NOW(),
            rejection_note     = ?
         WHERE id = ?"
    )->execute([$email, $name, $note, $id]);
}

function expApproveAsSigner2(int $id, string $email, string $name, string $note = ''): void {
    $exp = expGet($id);
    if (!$exp) {
        throw new RuntimeException("Expense #{$id} not found.");
    }
    if ($exp['status'] !== 'signer1_approved') {
        throw new RuntimeException("Cannot approve as Signer 2: expense must be in 'signer1_approved' state (current: {$exp['status']}).");
    }
    if (!expIsEligibleSigner2($email)) {
        throw new RuntimeException("Only a VP, President, or Admin can approve as Signer 2.");
    }
    if ($email === $exp['signer1_email']) {
        throw new RuntimeException("Signer 2 cannot be the same person as Signer 1.");
    }

    getDB()->prepare(
        "UPDATE exp_expenses SET
            status       = 'signer2_approved',
            signer2_email = ?,
            signer2_name  = ?,
            signer2_at    = NOW(),
            signer2_note  = ?
         WHERE id = ?"
    )->execute([$email, $name, $note ?: null, $id]);
}

function expMarkPaid(int $id, string $email, string $name, string $paymentNote, string $paymentRef = '', string $paymentDate = ''): void {
    $exp = expGet($id);
    if (!$exp) {
        throw new RuntimeException("Expense #{$id} not found.");
    }
    if ($exp['status'] !== 'signer2_approved') {
        throw new RuntimeException("Cannot mark as paid: expense must be in 'signer2_approved' state (current: {$exp['status']}).");
    }
    if (!expIsTreasurer($email)) {
        throw new RuntimeException("Only a Treasurer can mark an expense as paid.");
    }
    $pd = ($paymentDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) ? $paymentDate : date('Y-m-d');
    getDB()->prepare(
        "UPDATE exp_expenses SET
            status        = 'paid',
            paid_at       = NOW(),
            paid_by_email = ?,
            paid_by_name  = ?,
            payment_note  = ?,
            payment_ref   = ?,
            payment_date  = ?
         WHERE id = ?"
    )->execute([$email, $name, $paymentNote ?: null, $paymentRef ?: null, $pd, $id]);
}

// ── Upload token helpers ───────────────────────────────────────────────────────

function expCreateUploadToken(int $expenseId, string $email): string {
    $token = bin2hex(random_bytes(16));
    $db    = getDB();
    // One token per expense — delete old ones
    $db->prepare("DELETE FROM exp_upload_tokens WHERE expense_id=?")->execute([$expenseId]);
    $db->prepare("INSERT INTO exp_upload_tokens (token, expense_id, created_by) VALUES (?,?,?)")
       ->execute([$token, $expenseId, $email]);
    return $token;
}

function expValidateUploadToken(string $token): ?array {
    $s = getDB()->prepare("SELECT * FROM exp_upload_tokens WHERE token=? LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

function expAddPendingReceipt(int $expenseId, string $savedPath, string $origName, array $scanData): int {
    $db = getDB();
    $db->prepare(
        "INSERT INTO exp_pending_receipts (expense_id, saved_path, original_name, scan_json)
         VALUES (?,?,?,?)"
    )->execute([$expenseId, $savedPath, $origName, json_encode($scanData)]);
    return (int)$db->lastInsertId();
}

function expGetPendingReceipt(int $expenseId): ?array {
    $s = getDB()->prepare(
        "SELECT * FROM exp_pending_receipts WHERE expense_id=? AND claimed=0
         ORDER BY created_at DESC LIMIT 1"
    );
    $s->execute([$expenseId]);
    $r = $s->fetch();
    if (!$r) {
        return null;
    }
    $r['scan_data'] = $r['scan_json'] ? json_decode($r['scan_json'], true) : [];
    return $r;
}

function expClaimPendingReceipt(int $id): void {
    getDB()->prepare("UPDATE exp_pending_receipts SET claimed=1 WHERE id=?")->execute([$id]);
}

// ── Badge counts ───────────────────────────────────────────────────────────────

function expPendingForTreasurer(): int {
    return (int)getDB()->query(
        "SELECT COUNT(*) FROM exp_expenses WHERE status='pending'"
    )->fetchColumn();
}

function expPendingForSigner2(): int {
    return (int)getDB()->query(
        "SELECT COUNT(*) FROM exp_expenses WHERE status='signer1_approved'"
    )->fetchColumn();
}

// ── Email notifications ────────────────────────────────────────────────────────

function expNotify(string $to, string $subject, string $body): void {
    require_once __DIR__ . '/smtp.php';
    siteMail($to, $subject, $body, true);
}

function expGetTreasurerEmails(): array {
    $emails = [];
    $db     = getDB();

    // Primary: EC directory (exec_roles)
    $s = $db->query("SELECT DISTINCT user_email FROM exec_roles WHERE role='treasurer'");
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $email) {
        $email = strtolower(trim($email));
        if ($email && !in_array($email, $emails)) $emails[] = $email;
    }

    // Legacy: exp_roles (manual assignments, kept for backward compat)
    $s = $db->query("SELECT DISTINCT user_email FROM exp_roles WHERE role='treasurer'");
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $email) {
        $email = strtolower(trim($email));
        if ($email && !in_array($email, $emails)) $emails[] = $email;
    }

    // Always include the Local President so they stay in the loop
    $lpEmail = defined('EXPENSE_ADMIN_EMAIL') && EXPENSE_ADMIN_EMAIL
        ? strtolower(trim(EXPENSE_ADMIN_EMAIL))
        : (defined('PROD_ADMIN_EMAIL') && PROD_ADMIN_EMAIL ? strtolower(trim(PROD_ADMIN_EMAIL)) : null);
    if ($lpEmail && !in_array($lpEmail, $emails)) $emails[] = $lpEmail;

    return $emails;
}

function expGetSigner2Emails(): array {
    // Second signer for regular member expenses is always the Local President
    $emails = [];
    if (defined('EXPENSE_ADMIN_EMAIL') && EXPENSE_ADMIN_EMAIL) {
        $emails[] = strtolower(trim(EXPENSE_ADMIN_EMAIL));
    } elseif (defined('PROD_ADMIN_EMAIL') && PROD_ADMIN_EMAIL) {
        $emails[] = strtolower(trim(PROD_ADMIN_EMAIL));
    }
    return $emails;
}

function _expHtmlWrap(string $title, string $body): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;background:#f4f6f8;margin:0;padding:0;}
        .wrap{max-width:560px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);}
        .hdr{background:#1a6b35;padding:20px 28px;color:#fff;}
        .hdr h1{margin:0;font-size:18px;font-weight:700;}
        .hdr p{margin:4px 0 0;font-size:13px;opacity:.8;}
        .body{padding:24px 28px;}
        .body p{color:#374151;font-size:14px;line-height:1.6;margin:0 0 12px;}
        .detail-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;margin:16px 0;}
        .detail-box .row{display:flex;padding:5px 0;border-bottom:1px solid #f3f4f6;}
        .detail-box .row:last-child{border-bottom:none;}
        .detail-box .lbl{color:#6b7280;font-size:13px;width:130px;flex-shrink:0;}
        .detail-box .val{color:#111827;font-size:13px;font-weight:600;}
        .btn{display:inline-block;background:#1a6b35;color:#fff;padding:10px 22px;border-radius:7px;text-decoration:none;font-size:14px;font-weight:700;margin-top:8px;}
        .ftr{background:#f9fafb;padding:16px 28px;font-size:12px;color:#9ca3af;border-top:1px solid #e5e7eb;}
    </style></head><body>
    <div class="wrap">
        <div class="hdr"><h1>BVTU Expense Portal</h1><p>' . htmlspecialchars($title) . '</p></div>
        <div class="body">' . $body . '</div>
        <div class="ftr">Bulkley Valley Teachers\' Union &mdash; This is an automated notification.</div>
    </div></body></html>';
}

function _expDetailBox(array $exp): string {
    $catLabel = ucfirst($exp['category']);
    $html  = '<div class="detail-box">';
    $html .= '<div class="row"><span class="lbl">Reference</span><span class="val">' . htmlspecialchars($exp['ref_code']) . '</span></div>';
    $html .= '<div class="row"><span class="lbl">Member</span><span class="val">' . htmlspecialchars($exp['user_name']) . '</span></div>';
    $html .= '<div class="row"><span class="lbl">Email</span><span class="val">' . htmlspecialchars($exp['user_email']) . '</span></div>';
    if (!empty($exp['submitted_by_email']) && strtolower(trim($exp['submitted_by_email'])) !== strtolower(trim($exp['user_email']))) {
        $html .= '<div class="row"><span class="lbl">Submitted by</span><span class="val">' . htmlspecialchars($exp['submitted_by_name'] ?: $exp['submitted_by_email']) . ' (on behalf of member)</span></div>';
    }
    $html .= '<div class="row"><span class="lbl">Date</span><span class="val">' . htmlspecialchars($exp['expense_date']) . '</span></div>';
    $html .= '<div class="row"><span class="lbl">Category</span><span class="val">' . htmlspecialchars($catLabel) . '</span></div>';
    $html .= '<div class="row"><span class="lbl">Amount</span><span class="val">$' . number_format((float)$exp['amount'], 2) . '</span></div>';
    $html .= '<div class="row"><span class="lbl">Description</span><span class="val">' . htmlspecialchars($exp['description']) . '</span></div>';
    $html .= '</div>';
    return $html;
}

function expEmailSubmitted(array $exp): void {
    $onBehalf = !empty($exp['submitted_by_email'])
             && strtolower(trim($exp['submitted_by_email'])) !== strtolower(trim($exp['user_email']));

    // To member
    $intro = $onBehalf
        ? '<p>An expense reimbursement was submitted on your behalf by <strong>' . htmlspecialchars($exp['submitted_by_name'] ?: $exp['submitted_by_email']) . '</strong> and is awaiting review by the BVTU Treasurer.</p>'
        : '<p>Your expense reimbursement has been submitted and is awaiting review by the BVTU Treasurer.</p>';
    $body = $intro
          . _expDetailBox($exp)
          . '<p>You will receive an email when it has been reviewed.</p>';
    expNotify(
        $exp['user_email'],
        'Expense Submitted — ' . $exp['ref_code'],
        _expHtmlWrap('Expense Submitted', $body)
    );

    // To treasurers
    $submitterLine = $onBehalf
        ? '<p><strong>' . htmlspecialchars($exp['submitted_by_name'] ?: $exp['submitted_by_email']) . '</strong> has submitted a new expense reimbursement on behalf of <strong>' . htmlspecialchars($exp['user_name']) . '</strong> for your review.</p>'
        : '<p><strong>' . htmlspecialchars($exp['user_name']) . '</strong> has submitted a new expense reimbursement for your review.</p>';
    $treasurerBody = $submitterLine
                   . _expDetailBox($exp)
                   . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-treasurer.php">Review in Expense Portal</a></p>';
    foreach (expGetTreasurerEmails() as $email) {
        expNotify(
            $email,
            'New Expense for Review — ' . $exp['ref_code'],
            _expHtmlWrap('New Expense Submitted', $treasurerBody)
        );
    }

}

function expEmailSigner1Approved(array $exp): void {
    $body = '<p>The following expense has been approved by the BVTU Treasurer (<strong>' . htmlspecialchars($exp['signer1_name']) . '</strong>) and now requires a second signature.</p>'
          . _expDetailBox($exp)
          . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-signer2.php">Review &amp; Sign in Expense Portal</a></p>';
    foreach (expGetSigner2Emails() as $email) {
        expNotify(
            $email,
            'Second Signature Required — ' . $exp['ref_code'],
            _expHtmlWrap('Second Signature Required', $body)
        );
    }
}

function expEmailSigner2Approved(array $exp): void {
    // To member — payment coming
    $memberBody = '<p>Great news! Your expense reimbursement has been authorized by two signing officers.</p>'
                . _expDetailBox($exp)
                . '<p>The Treasurer will send an <strong>e-transfer</strong> to <strong>' . htmlspecialchars($exp['user_email']) . '</strong> shortly. Use <code>' . htmlspecialchars($exp['ref_code']) . '</code> as the reference if prompted.</p>';
    expNotify(
        $exp['user_email'],
        'Expense Authorized — ' . $exp['ref_code'] . ' — E-transfer coming',
        _expHtmlWrap('Expense Authorized — Payment Coming', $memberBody)
    );

    // To treasurers — ready to send
    $treasurerBody = '<p>This expense has received both required signatures and is <strong>ready for payment</strong>.</p>'
                   . _expDetailBox($exp)
                   . '<div class="detail-box" style="background:#f0fdf4;border-color:#bbf7d0;">'
                   . '<div class="row"><span class="lbl">Send e-transfer to</span><span class="val">' . htmlspecialchars($exp['user_email']) . '</span></div>'
                   . '<div class="row"><span class="lbl">Amount</span><span class="val">$' . number_format((float)$exp['amount'], 2) . '</span></div>'
                   . '<div class="row"><span class="lbl">Message / Ref</span><span class="val">' . htmlspecialchars($exp['ref_code']) . '</span></div>'
                   . '</div>'
                   . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-treasurer.php">Open Treasurer Dashboard</a></p>';
    foreach (expGetTreasurerEmails() as $email) {
        expNotify(
            $email,
            'Ready to Pay — ' . $exp['ref_code'],
            _expHtmlWrap('Expense Ready for Payment', $treasurerBody)
        );
    }
}

function expEmailPaid(array $exp): void {
    $body = '<p>Your expense reimbursement payment has been sent!</p>'
          . _expDetailBox($exp)
          . '<div class="detail-box" style="background:#f0fdf4;border-color:#bbf7d0;">'
          . '<div class="row"><span class="lbl">Paid by</span><span class="val">' . htmlspecialchars($exp['paid_by_name']) . '</span></div>'
          . '<div class="row"><span class="lbl">Date</span><span class="val">' . date('F j, Y', strtotime($exp['paid_at'])) . '</span></div>'
          . '<div class="row"><span class="lbl">Note</span><span class="val">' . htmlspecialchars($exp['payment_note'] ?: '—') . '</span></div>'
          . '</div>'
          . '<p><a class="btn" href="' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . '/members/exp-receipt-print.php?id=' . (int)$exp['id'] . '">View &amp; Print Receipt</a></p>';
    expNotify(
        $exp['user_email'],
        'Expense Paid — ' . $exp['ref_code'],
        _expHtmlWrap('Expense Payment Sent', $body)
    );
}

function expEmailRejected(array $exp): void {
    $body = '<p>Unfortunately, the following expense reimbursement has been rejected.</p>'
          . _expDetailBox($exp)
          . '<div class="detail-box" style="background:#fef2f2;border-color:#fecaca;">'
          . '<div class="row"><span class="lbl">Rejected by</span><span class="val">' . htmlspecialchars($exp['rejected_by_name']) . '</span></div>'
          . '<div class="row"><span class="lbl">Reason</span><span class="val">' . htmlspecialchars($exp['rejection_note']) . '</span></div>'
          . '</div>'
          . '<p>If you have questions about this decision, please contact the Treasurer or BVTU Executive.</p>';
    expNotify(
        $exp['user_email'],
        'Expense Rejected — ' . $exp['ref_code'],
        _expHtmlWrap('Expense Rejected', $body)
    );
}
