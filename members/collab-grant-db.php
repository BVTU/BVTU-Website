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
    cgEnsureEditSupport();
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
    cgEnsureEditSupport();
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
          . emailTplBlock('collab_approved', 'signoff', $tv) . "\n"
          . cgEditFooter((int)$app['id']);

    siteMail($email, $subject, $body);
}

/**
 * The "change your application" link, appended in code rather than kept in the
 * editable template: the wording in email-templates.php is prose the president
 * can rewrite, and a URL quietly broken by an edit there would strand people.
 */
function cgEditFooter(int $id): string {
    return "\n─────────────────────────────────────────────────────────────────────\n"
         . "Need to change something — a different date, an extra day, a new\n"
         . "collaborator? You can edit your application yourself here:\n\n"
         . cgEditUrl($id) . "\n\n"
         . "That link is the key to your application — please don't forward this\n"
         . "email. If you need to send someone the booking details, copy the text\n"
         . "above instead.\n";
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
    $body    = emailTplBlock('collab_received', 'body', $tv) . "\n" . cgEditFooter((int)$app['id']);

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

// ── Editing a submitted application ──────────────────────────────────────────
//
// Teachers could not reach their own application once it was sent, so a date
// change meant an email and someone retyping it. They can now, and so can the
// president — but a collaboration grant carries release days and money, so an
// edit is never silent: every change is recorded, and a change after approval
// says so and tells the president.

/** The fields an applicant may change. Email is not among them: it is how the
 *  application is identified, and changing it would hand the record to someone
 *  else. The president can change it from the admin screen. */
const CG_EDITABLE = [
    'applicant_name'      => 'Name',
    'school'              => 'School',
    'position'            => 'Position',
    'years_in_role'       => 'Years in role',
    'has_collaborator'    => 'Has a collaborator',
    'collaborator_name'   => 'Collaborator',
    'collaborator_school' => 'Collaborator school',
    'needs_partner'       => 'Looking for a partner',
    'collaboration_desc'  => 'What the collaboration involves',
    'goals'               => 'Goals',
    'proposed_dates'      => 'Proposed dates',
    'days_requested'      => 'Days requested',
];

/** Changes that alter what was approved, as opposed to fixing a typo. */
const CG_MATERIAL = ['days_requested', 'proposed_dates', 'collaborator_name'];

function cgEnsureEditSupport(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    foreach ([
        'edit_token'       => "VARCHAR(64) NOT NULL DEFAULT ''",
        'edited_at'        => 'DATETIME DEFAULT NULL',
        'edited_by'        => "VARCHAR(255) NOT NULL DEFAULT ''",
        'changed_after_ok' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'link_sent_at'     => 'DATETIME DEFAULT NULL',
        'link_sent_by'     => "VARCHAR(255) NOT NULL DEFAULT ''",
    ] as $col => $type) {
        try {
            $db->query("SELECT `$col` FROM collab_grant_applications LIMIT 1");
        } catch (\PDOException $e) {
            // As in cgEnsureTable(): a host whose DB user cannot ALTER should
            // lose the new column, not the page.
            try {
                $db->exec("ALTER TABLE collab_grant_applications ADD COLUMN `$col` $type");
            } catch (\Exception $e2) {}
        }
    }

    // One row per edit, holding what actually changed. A grant that quietly
    // moved between approval and invoicing is near impossible to reconstruct a
    // year later when the district queries the bill.
    try {
    $db->exec("CREATE TABLE IF NOT EXISTS collab_grant_edits (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        app_id      INT NOT NULL,
        changed_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        changed_by  VARCHAR(255) NOT NULL DEFAULT '',
        by_admin    TINYINT(1) NOT NULL DEFAULT 0,
        changes     TEXT,
        INDEX idx_app (app_id, changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Exception $e) {}
}

/** The application's edit token, created on first use. */
function cgEditToken(int $id): string {
    cgEnsureEditSupport();
    $db = getDB();
    $s  = $db->prepare("SELECT edit_token FROM collab_grant_applications WHERE id=?");
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) return '';
    if (($row['edit_token'] ?? '') !== '') return $row['edit_token'];

    $t = bin2hex(random_bytes(24));
    $db->prepare("UPDATE collab_grant_applications SET edit_token=? WHERE id=?")->execute([$t, $id]);
    return $t;
}

/** The application a token belongs to, or null. */
function cgAppByToken(string $token): ?array {
    cgEnsureEditSupport();
    $token = trim($token);
    // Length-checked first so a short or empty token cannot match a row whose
    // column is still at its '' default.
    if (strlen($token) !== 48) return null;
    $s = getDB()->prepare("SELECT * FROM collab_grant_applications WHERE edit_token=? LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

/** Applications belonging to an email address, newest first. */
function cgAppsForEmail(string $email): array {
    cgEnsureEditSupport();
    $e = strtolower(trim($email));
    if ($e === '') return [];
    // idx_email covers this, and the column's collation is case-insensitive, so
    // the comparison does not need LOWER() on either side.
    $st = getDB()->prepare(
        "SELECT * FROM collab_grant_applications WHERE applicant_email=? ORDER BY submitted_at DESC");
    $st->execute([$e]);
    return $st->fetchAll();
}

/**
 * Locked to the applicant once the district paperwork exists.
 *
 * After Atrieve is confirmed or an invoice number is recorded, the application
 * is the record the district's bill is matched against. Changing the dates then
 * makes the two disagree, and the invoice is the one that cannot be edited.
 */
function cgIsLockedToApplicant(array $app): bool {
    return !empty($app['atrieve_confirmed']) || trim((string)($app['invoice_number'] ?? '')) !== '';
}

/**
 * Apply an edit. Returns ['changed' => [field => [old, new]], 'material' => bool].
 *
 * Writes nothing and records nothing when nothing actually differs — resaving an
 * unchanged form should not produce an audit entry or an email.
 */
function cgApplyEdit(int $id, array $values, string $actor, bool $isAdmin): array {
    cgEnsureEditSupport();
    $app = cgGetApplication($id);
    if (!$app) return ['changed' => [], 'material' => false];

    $changed = [];
    foreach (CG_EDITABLE as $col => $label) {
        if (!array_key_exists($col, $values)) continue;
        $new = $values[$col];
        $new = in_array($col, ['has_collaborator', 'needs_partner'], true) ? (int)(bool)$new
             : ($col === 'days_requested' ? (int)$new : trim((string)$new));
        $old = $app[$col];
        $oldCmp = in_array($col, ['has_collaborator', 'needs_partner'], true) ? (int)$old
                : ($col === 'days_requested' ? (int)$old : trim((string)$old));
        if ($oldCmp === $new) continue;
        $changed[$col] = [$oldCmp, $new];
    }
    if (!$changed) return ['changed' => [], 'material' => false];

    $sets = [];
    $args = [];
    foreach ($changed as $col => $pair) { $sets[] = "`$col`=?"; $args[] = $pair[1]; }
    $sets[] = 'edited_at=NOW()';
    $sets[] = 'edited_by=?';  $args[] = $actor;

    $material = (bool)array_intersect(array_keys($changed), CG_MATERIAL);
    if ($material && ($app['status'] ?? '') === 'approved') {
        $sets[] = 'changed_after_ok=1';
    }
    $args[] = $id;

    getDB()->prepare("UPDATE collab_grant_applications SET " . implode(', ', $sets) . " WHERE id=?")
           ->execute($args);

    getDB()->prepare("INSERT INTO collab_grant_edits (app_id, changed_by, by_admin, changes) VALUES (?,?,?,?)")
           ->execute([$id, $actor, $isAdmin ? 1 : 0, json_encode($changed, JSON_UNESCAPED_UNICODE)]);

    return ['changed' => $changed, 'material' => $material];
}

function cgEditHistory(int $id): array {
    cgEnsureEditSupport();
    $s = getDB()->prepare("SELECT * FROM collab_grant_edits WHERE app_id=? ORDER BY changed_at DESC");
    $s->execute([$id]);
    return $s->fetchAll();
}

/** The president (and PROD_ADMIN_EMAIL, who is always exec). */
function cgIsAdmin(string $email): bool {
    $email = strtolower(trim($email));
    if (defined('PROD_ADMIN_EMAIL') && $email === strtolower(trim(PROD_ADMIN_EMAIL))) return true;
    return $email === 'lp54@bctf.ca';
}

/** Where an applicant goes to change their application. */
function cgEditUrl(int $id): string {
    $base = defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca';
    return $base . '/collab-grant-edit.php?t=' . cgEditToken($id);
}

/** One readable line describing an edit, for the page and the email. */
function cgDescribeChanges(array $changed): string {
    $parts = [];
    foreach ($changed as $col => $pair) {
        $label = CG_EDITABLE[$col] ?? $col;
        if ($col === 'proposed_dates') {
            $parts[] = 'Dates: ' . cgDateList($pair[0]) . ' → ' . cgDateList($pair[1]);
        } elseif (in_array($col, ['has_collaborator', 'needs_partner'], true)) {
            $parts[] = $label . ': ' . ($pair[1] ? 'yes' : 'no');
        } elseif (in_array($col, ['collaboration_desc', 'goals'], true)) {
            // The long prose fields are not quoted in a summary line; the page
            // shows the text itself.
            $parts[] = $label . ' reworded';
        } else {
            $from = $pair[0] === '' ? '(blank)' : $pair[0];
            $to   = $pair[1] === '' ? '(blank)' : $pair[1];
            $parts[] = $label . ': ' . $from . ' → ' . $to;
        }
    }
    return implode('; ', $parts);
}

/** A stored proposed_dates JSON value as readable dates. */
function cgDateList($json): string {
    $a = is_array($json) ? $json : json_decode((string)$json, true);
    if (!is_array($a) || !$a) return 'none';
    return implode(', ', array_map(function ($d) { return date('D M j', strtotime($d)); }, $a));
}

/**
 * Tell the president an application changed.
 *
 * Sent for every applicant edit, not only edits after approval: a change to a
 * pending application still arrives at the Executive meeting differently from
 * the copy that was printed for it.
 */
function cgSendApplicantEditNotice(array $app, array $changed): void {
    $body = 'Hi ' . $app['applicant_name'] . ",\n\n"
          . "The BVTU has updated your Collaboration Grant application.\n\n"
          . "What changed:\n  " . str_replace('; ', "\n  ", cgDescribeChanges($changed)) . "\n\n"
          . 'Your release days are now: ' . cgDateList($app['proposed_dates'])
          . ' (' . (int)$app['days_requested'] . ' '
          . ((int)$app['days_requested'] === 1 ? 'day' : 'days') . ").\n\n"
          . "If that isn't what you expected, reply to this email or write to\n"
          . "lp54@bctf.ca and we'll put it right.\n\n"
          . "Bulkley Valley Teachers' Union\n"
          . cgEditFooter((int)$app['id']);
    siteMail($app['applicant_email'], 'Your Collaboration Grant application was updated — BVTU', $body);
}

function cgSendEditNotification(array $app, array $changed, string $actor): void {
    $after = ($app['status'] ?? '') === 'approved' ? ' (already approved)' : '';
    $subject = 'Collaboration Grant changed — ' . $app['applicant_name'] . $after;
    $body = $app['applicant_name'] . ' (' . $app['applicant_email'] . ') changed their '
          . "Collaboration Grant application.\n\n"
          . "What changed:\n  " . str_replace('; ', "\n  ", cgDescribeChanges($changed)) . "\n\n"
          . 'Status: ' . ucfirst((string)$app['status']) . "\n"
          . 'Release days now: ' . (int)$app['days_requested'] . "\n"
          . 'Dates now: ' . cgDateList($app['proposed_dates']) . "\n\n"
          . 'Review it: ' . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca')
          . '/members/collab-grant-admin.php' . "\n";
    siteMail('lp54@bctf.ca', $subject, $body);
}

/**
 * Send an applicant the link to change their own application.
 *
 * Needed as its own button because the link only started going out with the
 * emails from this change: everyone who applied before it has an application
 * they cannot reach.
 */
function cgSendEditLink(array $app, string $actor): bool {
    cgEnsureEditSupport();
    $tv   = ['{{name}}' => $app['applicant_name']];
    $body = emailTplBlock('collab_edit_link', 'body', $tv) . "\n" . cgEditFooter((int)$app['id']);
    $ok   = siteMail($app['applicant_email'], emailTplSubject('collab_edit_link', $tv), $body);
    if ($ok) {
        getDB()->prepare("UPDATE collab_grant_applications SET link_sent_at=NOW(), link_sent_by=? WHERE id=?")
               ->execute([$actor, (int)$app['id']]);
    }
    return $ok;
}
