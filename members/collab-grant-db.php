<?php
/**
 * collab-grant-db.php — Collaboration Grant database helpers
 */
require_once __DIR__ . '/db.php';
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

    $subject = "Your Collaboration Grant Application is Approved — BVTU";
    $body    = <<<TEXT
Hi {$name},

Great news — your BVTU Collaboration Grant application has been approved! You've been granted {$days} release {$dayWord} to use this school year.

─── NEXT STEP: BOOKING YOUR ABSENCE IN ATRIEVE ────────────────────

Once you have your date(s) confirmed, please submit your absence in Atrieve using "BVTU business" as the absence reason. {$collabLine}

We really appreciate it when members book with plenty of notice — please aim for at least two weeks ahead of your planned date(s). This gives the district time to arrange TTOC coverage and avoids any last-minute scrambling. The earlier, the better!

─────────────────────────────────────────────────────────────────────

If you have any questions or run into anything, don't hesitate to reach out — we're happy to help.

Cody Lind
President, Bulkley Valley Teachers' Union
lp54@bctf.ca
TEXT;

    $headers = "From: BVTU <noreply@bvtu.ca>\r\nReply-To: lp54@bctf.ca\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($email, $subject, $body, $headers);
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

    $headers = "From: BVTU Website <noreply@bvtu.ca>\r\nContent-Type: text/plain; charset=UTF-8";
    @mail('lp54@bctf.ca', $subject, $body, $headers);
}

// Confirmation sent to the applicant on submission
function cgSendSubmissionConfirmation(array $app): void {
    $name    = $app['applicant_name'];
    $email   = $app['applicant_email'];
    $subject = "We received your Collaboration Grant application — BVTU";
    $body    = <<<TEXT
Hi {$name},

Thank you for submitting your BVTU Collaboration Grant application! We've received it and it will be reviewed at the next monthly Executive Meeting. You'll hear back from us by the 15th of the month.

If you have any questions in the meantime, feel free to reach out at lp54@bctf.ca.

Bulkley Valley Teachers' Union
TEXT;

    $headers = "From: BVTU <noreply@bvtu.ca>\r\nReply-To: lp54@bctf.ca\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($email, $subject, $body, $headers);
}
