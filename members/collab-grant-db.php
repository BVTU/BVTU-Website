<?php
/**
 * collab-grant-db.php — Collaboration Grant database helpers
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email-templates-db.php';
require_once __DIR__ . '/smtp.php';
date_default_timezone_set('America/Vancouver');

// School year: Sep 1 = new year (matches lp-db.php)
function cgCurrentYear(): int {
    $m = (int)date('n');
    return $m >= 9 ? (int)date('Y') : (int)date('Y') - 1;
}

function cgEnsureTable(): void {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS collab_grant_applications (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        applicant_name      VARCHAR(255) NOT NULL,
        applicant_email     VARCHAR(255) NOT NULL,
        school              VARCHAR(255) NOT NULL,
        position            VARCHAR(255) NOT NULL,
        years_in_role       VARCHAR(50),
        has_collaborator    TINYINT(1) DEFAULT 0,
        collaborator_name   VARCHAR(255),
        collaborator_school VARCHAR(255),
        needs_partner       TINYINT(1) DEFAULT 0,
        collaboration_desc  TEXT,
        goals               TEXT,
        proposed_dates      TEXT,
        days_requested      TINYINT NOT NULL DEFAULT 1,
        status              VARCHAR(20) DEFAULT 'pending',
        admin_notes         TEXT,
        school_year         INT NOT NULL,
        submitted_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        reviewed_at         DATETIME,
        reviewed_by         VARCHAR(255),
        INDEX idx_email  (applicant_email),
        INDEX idx_status (status),
        INDEX idx_year   (school_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Follow-through, recorded after approval: the absence actually reaching
    // Atrieve and the district's invoice number. Approving a grant is a
    // decision; these are what tell you it was carried out and paid for, and
    // without them the year's record cannot be reconciled against the district.
    foreach ([
        'atrieve_confirmed'    => 'TINYINT(1) NOT NULL DEFAULT 0',
        'atrieve_confirmed_at' => 'DATETIME DEFAULT NULL',
        'atrieve_confirmed_by' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'invoice_number'       => "VARCHAR(100) NOT NULL DEFAULT ''",
        // Nullable on purpose: NULL is "not recorded yet" and 0.00 is a real
        // figure someone entered. A NOT NULL default of 0 cannot tell those
        // apart, and would leave a genuinely free release permanently counted
        // as outstanding.
        'release_cost'         => 'DECIMAL(10,2) DEFAULT NULL',
    ] as $col => $type) {
        try {
            $has = $db->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='collab_grant_applications'
                 AND COLUMN_NAME='{$col}'"
            )->fetchColumn();
            if (!$has) $db->exec("ALTER TABLE collab_grant_applications ADD COLUMN {$col} {$type}");
        } catch (Exception $e) {
            // Non-fatal: applications keep working, the fields just do not appear.
        }
    }

    // Migration: add proposed_dates if upgrading an existing table
    try {
        $db->exec("ALTER TABLE collab_grant_applications ADD COLUMN proposed_dates TEXT DEFAULT NULL");
    } catch (Exception $e) { /* column already exists — safe to ignore */ }
}

function cgSubmitApplication(array $d): int {
    cgEnsureTable();
    $db = getDB();
    $s  = $db->prepare("INSERT INTO collab_grant_applications
        (applicant_name, applicant_email, school, position, years_in_role,
         has_collaborator, collaborator_name, collaborator_school, needs_partner,
         collaboration_desc, goals, proposed_dates, days_requested, school_year)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->execute([
        $d['name'], $d['email'], $d['school'], $d['position'], $d['years_in_role'],
        $d['has_collaborator'] ? 1 : 0,
        $d['collaborator_name'] ?? null,
        $d['collaborator_school'] ?? null,
        $d['needs_partner'] ? 1 : 0,
        $d['collaboration_desc'], $d['goals'],
        $d['proposed_dates'] ?? null,
        (int)$d['days_requested'],
        cgCurrentYear(),
    ]);
    return (int)$db->lastInsertId();
}

function cgGetApplications(int $year = 0, string $status = ''): array {
    if (!$year) $year = cgCurrentYear();
    $sql    = "SELECT * FROM collab_grant_applications WHERE school_year=?";
    $params = [$year];
    if ($status) { $sql .= " AND status=?"; $params[] = $status; }
    $sql .= " ORDER BY submitted_at DESC";
    $s = getDB()->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
}

function cgGetApplication(int $id): ?array {
    $s = getDB()->prepare("SELECT * FROM collab_grant_applications WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function cgUpdateStatus(int $id, string $status, string $reviewedBy, string $notes = ''): void {
    $s = getDB()->prepare(
        "UPDATE collab_grant_applications
         SET status=?, admin_notes=?, reviewed_at=NOW(), reviewed_by=?
         WHERE id=?"
    );
    $s->execute([$status, $notes, $reviewedBy, $id]);
}

// Sent to applicant when their application is approved
function cgSendApprovalEmail(array $app): void {
    $name      = $app['applicant_name'];
    $email     = $app['applicant_email'];
    $days      = (int)$app['days_requested'];
    $dayWord   = $days === 1 ? 'day' : 'days';
    $hasCollab = !empty($app['collaborator_name']);
    $collab    = $app['collaborator_name'] ?? '';

    $collabLine = $hasCollab
        ? "Please also give {$collab} a heads-up so they can submit their own absence in Atrieve for the days you'll be working together."
        : '';

    $tv = ['{{name}}' => $name, '{{days}}' => (string)$days,
           '{{day_word}}' => $dayWord, '{{collab_line}}' => $collabLine];

    $subject = emailTplSubject('collab_approved', $tv);
    $body = emailTplBlock('collab_approved', 'intro', $tv) . "\n\n"
          . "─── NEXT STEP: BOOKING YOUR ABSENCE IN ATRIEVE ────────────────────\n\n"
          . emailTplBlock('collab_approved', 'booking', $tv) . "\n\n"
          . "─────────────────────────────────────────────────────────────────────\n\n"
          . emailTplBlock('collab_approved', 'signoff', $tv) . "\n";

    siteMail($email, $subject, $body);
}

// Sent to lp54@bctf.ca when a new application is submitted
function cgSendNewApplicationNotification(array $app): void {
    $name      = $app['applicant_name'];
    $email     = $app['applicant_email'];
    $days      = (int)$app['days_requested'];
    $collab    = !empty($app['collaborator_name'])
        ? $app['collaborator_name'] . ' (' . ($app['collaborator_school'] ?? '') . ')'
        : ($app['needs_partner'] ? 'None — needs help finding partner' : 'None identified');

    $datesLine = '';
    if (!empty($app['proposed_dates'])) {
        $dates = json_decode($app['proposed_dates'], true);
        if (is_array($dates) && count($dates)) {
            $formatted = array_map(fn($d) => date('D, M j Y', strtotime($d)), $dates);
            $datesLine = "\nProposed dates:   " . implode(', ', $formatted);
        }
    }

    $subject = "New Collaboration Grant Application — {$name}";
    $body    = <<<TEXT
A new collaboration grant application has been submitted.

Applicant:        {$name}
Email:            {$email}
School:           {$app['school']}
Position:         {$app['position']}
Time in role:     {$app['years_in_role']}
Collaborator:     {$collab}
Days requested:   {$days}{$datesLine}

DESCRIPTION:
{$app['collaboration_desc']}

GOALS:
{$app['goals']}

Review applications at:
https://bvtu.ca/members/collab-grant-admin.php
TEXT;

    siteMail('lp54@bctf.ca', $subject, $body);
}

// Confirmation sent to the applicant on submission
function cgSendSubmissionConfirmation(array $app): void {
    $name    = $app['applicant_name'];
    $email   = $app['applicant_email'];
    $tv      = ['{{name}}' => $name];
    $subject = emailTplSubject('collab_received', $tv);
    $body    = emailTplBlock('collab_received', 'body', $tv);

    siteMail($email, $subject, $body);
}

/**
 * Record the follow-through on an approved grant: whether the absence has been
 * logged in Atrieve, and the district's invoice number.
 *
 * The confirmation stamps who and when, because "somebody said it was done" is
 * not much of a record a year later when the district queries an invoice.
 * Unticking clears the stamp, so the two can never disagree.
 *
 * $cost is the district's charge for the release time: a number to record one,
 * '' to clear it back to "not recorded", or null to leave it untouched.
 */
function cgSetFulfilment(int $id, bool $atrieve, string $invoice, string $actor, $cost = null): bool {
    cgEnsureTable();
    try {
        $cur = cgGetApplication($id);
        if (!$cur) return false;

        $was = !empty($cur['atrieve_confirmed']);
        if ($atrieve && !$was) {
            $sql = "UPDATE collab_grant_applications
                    SET atrieve_confirmed=1, atrieve_confirmed_at=NOW(), atrieve_confirmed_by=?,
                        invoice_number=? WHERE id=?";
            $args = [$actor, mb_substr(trim($invoice), 0, 100), $id];
        } elseif (!$atrieve) {
            $sql = "UPDATE collab_grant_applications
                    SET atrieve_confirmed=0, atrieve_confirmed_at=NULL, atrieve_confirmed_by='',
                        invoice_number=? WHERE id=?";
            $args = [mb_substr(trim($invoice), 0, 100), $id];
        } else {
            // Already confirmed: keep the original stamp, just update the invoice.
            $sql  = "UPDATE collab_grant_applications SET invoice_number=? WHERE id=?";
            $args = [mb_substr(trim($invoice), 0, 100), $id];
        }
        getDB()->prepare($sql)->execute($args);

        if ($cost !== null) {
            // '' stores NULL — an emptied box means the figure is unknown
            // again, not that the release was free.
            $val = (trim((string)$cost) === '') ? null : round((float)$cost, 2);
            getDB()->prepare("UPDATE collab_grant_applications SET release_cost=? WHERE id=?")
                   ->execute([$val, $id]);
        }
        return true;
    } catch (Exception $e) {
        error_log('cgSetFulfilment: ' . $e->getMessage());
        return false;
    }
}

/**
 * What the year's release time has cost, and how much of that is actually known.
 *
 * Both numbers, always: a total that quietly omits the approved grants nobody
 * has costed yet looks like a complete figure and is not one. "$4,200 across 6
 * of 9" is an answer; "$4,200" on its own invites a budget decision on a third
 * of the picture.
 */
function cgYearReleaseCost(int $year): array {
    cgEnsureTable();
    $out = ['total' => 0.0, 'costed' => 0, 'approved' => 0, 'error' => false];
    try {
        $s = getDB()->prepare(
            "SELECT COALESCE(SUM(release_cost),0) AS total,
                    SUM(CASE WHEN release_cost IS NOT NULL THEN 1 ELSE 0 END) AS costed,
                    COUNT(*) AS approved
             FROM collab_grant_applications
             WHERE school_year=? AND status='approved'"
        );
        $s->execute([$year]);
        $r = $s->fetch() ?: [];
        $out['total']    = (float)($r['total'] ?? 0);
        $out['costed']   = (int)($r['costed'] ?? 0);
        $out['approved'] = (int)($r['approved'] ?? 0);
    } catch (Exception $e) {
        // Flagged, not swallowed: zeros here would render a confident "$0.00"
        // that is indistinguishable from a year in which nothing was spent.
        error_log('cgYearReleaseCost: ' . $e->getMessage());
        $out['error'] = true;
    }
    return $out;
}
