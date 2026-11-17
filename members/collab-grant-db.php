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
    // Repeat calls are free, so the functions that need the newer columns can
    // ask for them without a CREATE and a dozen information_schema lookups
    // every time.
    static $done = false;
    if ($done) return;
    $done = true;
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
        days_requested      DECIMAL(4,2) NOT NULL DEFAULT 1,
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
        // Reaching the collaborator. Their days come out of their own three,
        // so being named has a cost to them and they are owed the telling.
        'collaborator_email'   => "VARCHAR(255) NOT NULL DEFAULT ''",
        'collab_notified_at'   => 'DATETIME DEFAULT NULL',
        'collab_token'         => 'VARCHAR(64) DEFAULT NULL',
        'collab_disputed_at'   => 'DATETIME DEFAULT NULL',
        'collab_dispute_note'  => 'TEXT',
        // A send that was actually tried and failed. Without this, "nobody has
        // told them" and "we tried and it bounced" look identical — every grant
        // approved before collaborator emails existed has a NULL
        // collab_notified_at, and the panel accused us of a failed send that
        // was never attempted.
        'collab_attempt_at'    => 'DATETIME DEFAULT NULL',
        'collab_attempt_why'   => "VARCHAR(255) NOT NULL DEFAULT ''",
        // Deliberately not emailing them: the grant is long finished, or it was
        // handled by phone. A flag rather than a fake notified_at, so the
        // record never claims an email went out that did not.
        'collab_waived_at'     => 'DATETIME DEFAULT NULL',
        'collab_waived_by'     => "VARCHAR(255) NOT NULL DEFAULT ''",
        // One collaboration entered twice: the lead applies, then a
        // collaborator applies for the same days. Both are real applications
        // and both were approved, so neither is deleted — this says which one
        // the costing belongs to. Only the primary is invoiced, carries an
        // Atrieve confirmation and appears in the year's list; the days were
        // already counted once each, by date, in the ledger.
        'duplicate_of'         => 'INT DEFAULT NULL',
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

/**
 * Is the collaborator_email column actually there?
 *
 * Its ALTER is best-effort like the rest, and naming a missing column in the
 * INSERT would take down the public application form — which is a far worse
 * outcome than losing one optional address.
 */
function cgHasCollabColumn(string $col): bool {
    static $seen = [];
    if (array_key_exists($col, $seen)) return $seen[$col];
    try {
        $s = getDB()->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'collab_grant_applications'
               AND COLUMN_NAME = ?"
        );
        $s->execute([$col]);
        $seen[$col] = (bool)$s->fetchColumn();
    } catch (\Throwable $e) {
        error_log('cgHasCollabColumn ' . $col . ': ' . $e->getMessage());
        $seen[$col] = false;
    }
    return $seen[$col];
}

function cgHasCollabEmail(): bool { return cgHasCollabColumn('collaborator_email'); }

function cgSubmitApplication(array $d): int {
    cgEnsureTable();
    cgEnsureEditSupport();
    $db = getDB();
    $hasParts = cgHasCollabColumn('date_parts');
    $s  = $db->prepare("INSERT INTO collab_grant_applications
        (applicant_name, applicant_email, school, position, years_in_role,
         has_collaborator, collaborator_name, collaborator_school,"
        . (cgHasCollabEmail() ? " collaborator_email," : "") . "
         needs_partner, collaboration_desc, goals, proposed_dates, days_requested, school_year"
        . ($hasParts ? ", date_parts" : "") . ")
        VALUES (?,?,?,?,?,?,?,?," . (cgHasCollabEmail() ? "?," : "") . "?,?,?,?,?,?"
        . ($hasParts ? ",?" : "") . ")");
    $args = [
        $d['name'], $d['email'], $d['school'], $d['position'], $d['years_in_role'],
        $d['has_collaborator'] ? 1 : 0,
        $d['collaborator_name'] ?? null,
        $d['collaborator_school'] ?? null,
    ];
    if (cgHasCollabEmail()) $args[] = $d['collaborator_email'] ?? '';
    $args = array_merge($args, [
        $d['needs_partner'] ? 1 : 0,
        $d['collaboration_desc'], $d['goals'],
        $d['proposed_dates'] ?? null,
        round((float)$d['days_requested'], 2),
        cgCurrentYear(),
    ]);
    if ($hasParts) $args[] = !empty($d['date_parts']) ? json_encode($d['date_parts']) : null;
    $s->execute($args);
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

function cgUpdateStatus(int $id, string $status, string $reviewedBy, string $notes = '',
                        ?float $daysApproved = null, ?array $grantMap = null): void {
    cgEnsureEditSupport();
    // Only an approval carries a day count. Anything else clears it, so a grant
    // reset to pending does not keep counting against the applicant's three days.
    if ($status !== 'approved') { $daysApproved = null; $grantMap = null; }
    $hasGp = cgHasCollabColumn('granted_parts');
    $s = getDB()->prepare(
        "UPDATE collab_grant_applications
         SET status=?, admin_notes=?, reviewed_at=NOW(), reviewed_by=?, days_approved=?"
        . ($hasGp ? ", granted_parts=?" : "") . "
         WHERE id=?"
    );
    $args = [$status, $notes, $reviewedBy, $daysApproved];
    if ($hasGp) {
        if ($grantMap !== null) ksort($grantMap);
        $args[] = $grantMap ? json_encode(array_map(function ($v) { return round((float)$v, 2); }, $grantMap)) : null;
    }
    $args[] = $id;
    $s->execute($args);
}

// Sent to applicant when their application is approved
/**
 * The address we would write to for this application's collaborator.
 *
 * What the applicant typed wins. Failing that we try to match the name against
 * contacts — the same lookup the days ledger uses — but only when that match is
 * unambiguous: two people share a name often enough that guessing would
 * eventually hand someone else's release day to the wrong person.
 *
 * Returns ['email' => string, 'source' => 'typed'|'matched'|'none', 'why' => string].
 */
function cgCollaboratorAddress(array $app): array {
    if (empty($app['collaborator_name'])) {
        return ['email' => '', 'source' => 'none', 'why' => 'No collaborator was named.'];
    }

    $typed = trim((string)($app['collaborator_email'] ?? ''));
    if ($typed !== '' && filter_var($typed, FILTER_VALIDATE_EMAIL)) {
        return ['email' => $typed, 'source' => 'typed', 'why' => ''];
    }

    $key = cgNameKey((string)$app['collaborator_name']);
    $map = cgContactsByName();
    if ($key !== '' && isset($map[$key])) {
        if (!empty($map[$key]['ambiguous'])) {
            return ['email' => '', 'source' => 'none',
                    'why'   => 'More than one contact goes by that name, so we did not guess.'];
        }
        if (!empty($map[$key]['archived'])) {
            return ['email' => '', 'source' => 'none',
                    'why'   => 'The only contact with that name has left, so we did not write '
                             . 'to their old address.'];
        }
        $found = trim((string)$map[$key]['email']);
        if ($found === '' || !filter_var($found, FILTER_VALIDATE_EMAIL)) {
            return ['email' => '', 'source' => 'none',
                    'why'   => 'The contact we matched has no usable email address.'];
        }
        return ['email' => $found, 'source' => 'matched', 'why' => ''];
    }

    return ['email' => '', 'source' => 'none',
            'why'   => 'That name does not match anyone in Contacts.'];
}

/** True when an approved application still has a collaborator nobody has told. */
function cgCollaboratorNeedsAttention(array $app): bool {
    if (($app['status'] ?? '') !== 'approved') return false;
    if (empty($app['collaborator_name']))      return false;
    if (empty($app['has_collaborator']))       return false;
    if (!empty($app['collab_notified_at']))    return false;
    // Someone decided not to write to them. Still on the application, just no
    // longer asking to be dealt with.
    if (!empty($app['collab_waived_at']))      return false;
    /* The cap does not apply to them, so neither does the email. Taken out of
     * the queue rather than left in it: the Send button would refuse, and a
     * row that cannot be cleared is a row that teaches you to ignore the
     * whole list. */
    $addr = cgCollaboratorAddress($app);
    if (cgEmailSuppressed($addr['email'], (string)$app['collaborator_name'],
                          (int)($app['school_year'] ?? 0))) {
        return false;
    }
    return true;
}

/**
 * Which of the three states an unreached collaborator is actually in.
 *
 * 'failed'  — a send was tried and did not go. Press Send to try again.
 * 'never'   — nothing has ever been attempted. The whole backlog from before
 *             collaborator emails existed lands here, and telling the user it
 *             "did not go" was simply untrue.
 * 'noaddr'  — never attempted, and we have nowhere to send it either.
 */
function cgCollaboratorState(array $app, array $addr): string {
    if (!empty($app['collab_attempt_at'])) return 'failed';
    return $addr['email'] === '' ? 'noaddr' : 'never';
}

/**
 * Every application, whatever school year it belongs to.
 *
 * cgGetApplications(0) means "the current year", not "all of them" — so the
 * two collaborator lists need their own query or a June approval disappears
 * from them on 1 September.
 */
function cgApplicationsAllYears(): array {
    cgEnsureEditSupport();
    try {
        return getDB()->query(
            "SELECT * FROM collab_grant_applications ORDER BY submitted_at DESC"
        )->fetchAll();
    } catch (\Throwable $e) {
        error_log('cgApplicationsAllYears: ' . $e->getMessage());
        return [];
    }
}

/** Approved applications whose collaborator could not be reached. */
function cgCollaboratorsToFix(): array {
    $out = [];
    // Across every year: somebody never told about a day off their allowance
    // is still owed the telling after 1 September.
    foreach (cgApplicationsAllYears() as $a) {
        if (!cgCollaboratorNeedsAttention($a)) continue;
        $addr = cgCollaboratorAddress($a);
        // Including the ones we DO have an address for: if the send failed,
        // this is the only place it can be tried again, and filtering them out
        // left no retry anywhere.
        $a['_state'] = cgCollaboratorState($a, $addr);
        // The address the SEND will use, which may have come from Contacts
        // rather than the column. Without it the row said "we have jane@… for
        // them" above an empty, required box demanding the same address again.
        $a['_addr']  = $addr['email'];
        if ($a['_state'] === 'failed') {
            $a['_why'] = 'Tried on ' . date('M j', strtotime((string)$a['collab_attempt_at']))
                       . ' and it did not go'
                       . (trim((string)($a['collab_attempt_why'] ?? '')) !== ''
                          ? ' — ' . lcfirst(rtrim((string)$a['collab_attempt_why'], '.')) . '.'
                          : '.');
        } elseif ($a['_state'] === 'never') {
            // Approved before we started writing to collaborators, almost
            // always. Not a failure, and saying so sent the user looking for
            // a mail problem that was not there.
            $a['_why'] = 'Nothing has been sent to them yet. We have '
                       . $addr['email'] . ' for them.';
        } else {
            $a['_why'] = $addr['why'];
        }
        $out[] = $a;
    }
    return $out;
}

function cgSetCollaboratorEmail(int $id, string $email): bool {
    cgEnsureEditSupport();
    if (!cgHasCollabEmail()) return false;
    $email = trim($email);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    getDB()->prepare("UPDATE collab_grant_applications SET collaborator_email = ? WHERE id = ?")
           ->execute([mb_substr($email, 0, 255), $id]);
    return true;
}

/**
 * Record that one application is the same collaboration as another, or clear it.
 *
 * Refuses a chain. If B is folded under A and someone then folds A under B,
 * both vanish from the list and the cost of either is nobody's; and resolving
 * "the one that is costed" would loop. A duplicate may only point at an
 * application that is not itself a duplicate.
 */
function cgSetDuplicateOf(int $id, ?int $primaryId): string {
    cgEnsureTable();
    if ($id < 1) return 'That application could not be found.';

    $app = cgGetApplication($id);
    if (!$app) return 'That application could not be found.';

    // Guarded like every other optional column. duplicate_of arrives through a
    // best-effort ALTER, so on a host that could not run it this must say so
    // rather than throw a 500 at someone pressing a button.
    try {
        if ($primaryId === null) {
            getDB()->prepare("UPDATE collab_grant_applications SET duplicate_of=NULL WHERE id=?")
                   ->execute([$id]);
            return '';
        }

        if ($primaryId === $id) return 'An application cannot be folded under itself.';
        $primary = cgGetApplication($primaryId);
        if (!$primary) return 'The application to fold it under could not be found.';
        if (!empty($primary['duplicate_of'])) {
            return 'That one is itself folded under another — fold this under the same one.';
        }
        // Different years are different budgets; folding across them would move
        // money between them without saying so.
        if ((int)$primary['school_year'] !== (int)$app['school_year']) {
            return 'Those are different school years, and so different budgets.';
        }
        // Something is already folded under this one, so it cannot become a
        // duplicate itself — the same chain, from the other end.
        $kids = getDB()->prepare("SELECT COUNT(*) FROM collab_grant_applications WHERE duplicate_of=?");
        $kids->execute([$id]);
        if ((int)$kids->fetchColumn() > 0) {
            return 'Something is already folded under this one, so it cannot be folded itself.';
        }

        /*
         * Money already recorded against it would otherwise disappear.
         *
         * The year's cost total skips folded applications, and folding neither
         * moves nor clears release_cost, so an invoiced application folded away
         * takes its spend out of the year silently and out of the follow-up
         * queue that would have caught it. Refused rather than moved: which
         * invoice belongs to which collaboration is the Treasurer's call, not
         * a guess this function should make.
         */
        if (($app['release_cost'] ?? null) !== null
            || trim((string)($app['invoice_number'] ?? '')) !== '') {
            return 'That application already has an invoice or a release cost on it. '
                 . 'Fold the other one instead, or clear the figures here first — '
                 . 'folding it now would take that spending out of the year without saying so.';
        }

        getDB()->prepare("UPDATE collab_grant_applications SET duplicate_of=? WHERE id=?")
               ->execute([$primaryId, $id]);
        return '';
    } catch (Exception $e) {
        error_log('cgSetDuplicateOf: ' . $e->getMessage());
        return 'That could not be saved — the duplicate_of column is missing on this server. '
             . 'Tell the site admin.';
    }
}

/** The token on the collaborator's "this is not me" link. */
function cgCollabToken(int $id): string {
    cgEnsureEditSupport();
    if (!cgHasCollabColumn('collab_token')) return '';
    $db = getDB();
    $s  = $db->prepare("SELECT collab_token FROM collab_grant_applications WHERE id = ?");
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) return '';
    if (trim((string)($row['collab_token'] ?? '')) !== '') return (string)$row['collab_token'];

    $t = bin2hex(random_bytes(24));
    $db->prepare("UPDATE collab_grant_applications SET collab_token = ? WHERE id = ?")
       ->execute([$t, $id]);
    return $t;
}

function cgAppByCollabToken(string $token): ?array {
    if (strlen($token) < 20) return null;
    cgEnsureEditSupport();
    $s = getDB()->prepare("SELECT * FROM collab_grant_applications WHERE collab_token = ? LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

function cgCollabDisputeUrl(int $id): string {
    $base = defined('SITE_URL') && SITE_URL ? rtrim(SITE_URL, '/') : 'https://bvtu.ca';
    return $base . '/collab-not-me.php?t=' . urlencode(cgCollabToken($id));
}

/**
 * Record that the named collaborator says it is not them, and tell the
 * president. Nothing on the application is changed — whether the days still
 * stand is a decision for a person, not for a link in an email.
 */
function cgCollabDispute(int $id, string $note): void {
    cgEnsureEditSupport();
    getDB()->prepare(
        "UPDATE collab_grant_applications
         SET collab_disputed_at = NOW(), collab_dispute_note = ? WHERE id = ?"
    )->execute([mb_substr(trim($note), 0, 2000), $id]);

    $app = cgGetApplication($id);
    if (!$app) return;

    $to = defined('CG_ADMIN_EMAIL') && CG_ADMIN_EMAIL ? CG_ADMIN_EMAIL : 'lp54@bctf.ca';
    $body = "The person named as a collaborator on a Collaboration Grant application says "
          . "it is not them.\n\n"
          . "Application: #" . (int)$app['id'] . "\n"
          . "Applicant:   " . $app['applicant_name'] . ' (' . $app['applicant_email'] . ")\n"
          . "Named:       " . $app['collaborator_name'] . "\n"
          . "Written to:  " . (string)($app['collaborator_email'] ?? '') . "\n\n"
          . ($note !== '' ? "What they said:\n" . trim($note) . "\n\n" : '')
          . "Nothing on the application has been changed. The days are still counted "
          . "against that name until you decide otherwise.";

    siteMail($to, 'Collaborator says it is not them — application #' . (int)$app['id'], $body);
}

/**
 * Mark a dispute as dealt with. The note stays on the application — clearing
 * the flag means "I have seen this", not "it did not happen".
 */
function cgCollabDisputeAck(int $id): bool {
    cgEnsureEditSupport();
    if (!cgHasCollabColumn('collab_disputed_at')) return false;
    getDB()->prepare(
        "UPDATE collab_grant_applications SET collab_disputed_at = NULL WHERE id = ?"
    )->execute([$id]);
    return true;
}

/**
 * Did that UPDATE actually land on an application?
 *
 * rowCount() counts CHANGED rows on this connection, not matched ones, so a
 * second press that asks for the state the row is already in returns zero.
 * Reporting that as a failure sent the user looking for a missing column;
 * reporting a bad id as success would leave a collaborator silently unflagged.
 * So zero changes means "already so" when the application exists, and a
 * genuine failure only when it does not.
 */
function cgRowTouched(PDOStatement $st, int $id): bool {
    if ($st->rowCount() > 0) return true;
    $chk = getDB()->prepare("SELECT COUNT(*) FROM collab_grant_applications WHERE id = ?");
    $chk->execute([$id]);
    return (bool)$chk->fetchColumn();
}

/**
 * Forget everything we recorded about telling a collaborator.
 *
 * For when the collaborator changes: whatever we did, we did it to somebody
 * else, and the address we had reaches them rather than the new name. Each
 * column is cleared only if it exists, so an older server with a failed ALTER
 * still edits applications normally.
 */
/**
 * The people a collaborator field names, as comparable keys.
 *
 * Identity has to survive being rewritten. "Vittoria Yano and Tanya Davidson"
 * and "Vittoria Yano, Tanya Davidson" name the same two teachers, and pressing
 * Save on the rename boxes turns the first into the second — so comparing the
 * whole field as one key made a no-op save look like a change of person and
 * wiped the record of who had been emailed.
 */
function cgNameKeySet(string $raw): array {
    $keys = [];
    foreach (cgNameParts($raw) as $one) {
        $k = cgNameKey($one);
        if ($k !== '') $keys[$k] = true;
    }
    ksort($keys);
    return array_keys($keys);
}

/**
 * Do these two fields name a different set of people?
 *
 * The question the collaborator record turns on. That record — one address,
 * one "we emailed them" date — belongs to the application, not to a person,
 * so it cannot say "we told A but not B". While it cannot, any change to who
 * is named has to invalidate the lot:
 *
 *   - somebody added and the record kept: the new teacher is never told. The
 *     application already counts as notified, so the follow-up panel does not
 *     list it and the Send button refuses, and the only address on file is
 *     the first collaborator's. A day comes off their three in silence, which
 *     is the one thing this notice exists to prevent.
 *   - somebody removed and the record kept: the same, in reverse, for whoever
 *     is left.
 *
 * The cost is that a teacher who was already told may be told again when a
 * second name is added. Being told twice about a day that really is counted
 * against them is noise; never being told is the fault.
 *
 * What it does NOT count is the field being rewritten: same people in a
 * different order, in different capitals, or with the separators changed by
 * the rename boxes. That is what made pressing Save on an unchanged field
 * throw the record away.
 *
 * Per-person notices would let an addition leave the first collaborator's
 * record alone. That is a column per collaborator, or a table — a bigger
 * change than this one, and worth doing if two-collaborator grants stop being
 * rare.
 */
function cgCollaboratorSetChanged(string $was, string $now): bool {
    return cgNameKeySet($was) !== cgNameKeySet($now);
}

/**
 * Take back the "changed after it was approved" stamp.
 *
 * cgApplyEdit() sets it on any material change, which is right for a change
 * of substance and wrong for tidying somebody's capitalisation: the applicant
 * sees that banner on their own application for ever. Only the caller knows
 * which it was, so only the caller can undo it.
 */
function cgUnflagChangedAfterOk(int $id): bool {
    cgEnsureEditSupport();
    try {
        getDB()->prepare("UPDATE collab_grant_applications SET changed_after_ok=0 WHERE id=?")
               ->execute([$id]);
        return true;
    } catch (Exception $e) {
        error_log('cgUnflagChangedAfterOk: ' . $e->getMessage());
        return false;
    }
}

/**
 * Forget everything we recorded about telling a collaborator.
 *
 * For when a collaborator is taken off: whatever we did, we did it to somebody
 * else, and the address we had reaches them rather than whoever is named now.
 * Each column is cleared only if it exists, so an older server with a failed
 * ALTER still edits applications normally.
 */
function cgResetCollabNotice(int $id): bool {
    // These columns are created by cgEnsureTable(), not cgEnsureEditSupport():
    // without this the first press on a freshly deployed server reports them
    // missing and the notice says nothing was cleared when it could have been.
    cgEnsureTable();
    cgEnsureEditSupport();
    $sets = [];
    foreach (['collab_notified_at' => 'NULL', 'collab_attempt_at' => 'NULL',
              'collab_waived_at'   => 'NULL', 'collab_attempt_why' => "''",
              'collab_waived_by'   => "''",
              // The typed address belongs to the person who was named before.
              // Kept, it would send "a day is booked against your name" to
              // entirely the wrong teacher; cleared, the address resolves from
              // Contacts by the new name, or the panel asks for it. A retype
              // after fixing a misspelt name is the cheaper mistake.
              'collaborator_email'  => "''",
              // The dispute too. Renaming is the usual ANSWER to "that is not
              // me", so leaving the flag set makes the new collaborator look
              // like they objected, with the old person's note attached. The
              // token goes with it: the old person's link must not reach into
              // somebody else's application, and cgCollabToken() mints a fresh
              // one on demand.
              'collab_disputed_at'  => 'NULL',
              'collab_dispute_note' => 'NULL',
              'collab_token'        => 'NULL'] as $col => $blank) {
        if (cgHasCollabColumn($col)) $sets[] = "{$col} = {$blank}";
    }
    if (!$sets) return false;
    try {
        getDB()->prepare("UPDATE collab_grant_applications SET " . implode(', ', $sets)
                         . " WHERE id = ?")->execute([$id]);
        return true;
    } catch (\Throwable $e) {
        error_log('cgResetCollabNotice: ' . $e->getMessage());
        return false;
    }
}

/** Remember that a send was tried and did not go. */
function cgNoteCollabAttempt(int $id, string $why): bool {
    // These columns are created by cgEnsureTable(), not cgEnsureEditSupport():
    // without this the first press on a freshly deployed server reports them
    // missing and the notice says nothing was cleared when it could have been.
    cgEnsureTable();
    cgEnsureEditSupport();
    if (!cgHasCollabColumn('collab_attempt_at')) return false;
    try {
        $sql = cgHasCollabColumn('collab_attempt_why')
            ? "UPDATE collab_grant_applications
                  SET collab_attempt_at = NOW(), collab_attempt_why = ? WHERE id = ?"
            : "UPDATE collab_grant_applications SET collab_attempt_at = NOW() WHERE id = ?";
        $args = cgHasCollabColumn('collab_attempt_why')
            ? [mb_substr($why, 0, 255), $id] : [$id];
        getDB()->prepare($sql)->execute($args);
        return true;
    } catch (\Throwable $e) {
        // Never worth failing a send report over.
        error_log('cgNoteCollabAttempt: ' . $e->getMessage());
        return false;
    }
}

/**
 * Decide not to email this collaborator, and take the row off the panel.
 *
 * The flag is kept rather than faking collab_notified_at: the application must
 * never claim an email went out that did not, because that is exactly how a
 * collaborator ends up with a day booked against their name and no idea.
 */
function cgCollabWaive(int $id, string $by): bool {
    // These columns are created by cgEnsureTable(), not cgEnsureEditSupport():
    // without this the first press on a freshly deployed server reports them
    // missing and the notice says nothing was cleared when it could have been.
    cgEnsureTable();
    cgEnsureEditSupport();
    if (!cgHasCollabColumn('collab_waived_at')) return false;
    try {
        $sql = cgHasCollabColumn('collab_waived_by')
            ? "UPDATE collab_grant_applications
                  SET collab_waived_at = NOW(), collab_waived_by = ? WHERE id = ?"
            : "UPDATE collab_grant_applications SET collab_waived_at = NOW() WHERE id = ?";
        $args = cgHasCollabColumn('collab_waived_by')
            ? [mb_substr($by, 0, 255), $id] : [$id];
        $st = getDB()->prepare($sql);
        $st->execute($args);
        return cgRowTouched($st, $id);
    } catch (\Throwable $e) {
        error_log('cgCollabWaive: ' . $e->getMessage());
        return false;
    }
}

/** Put a waived one back on the panel, for a waive pressed by mistake. */
function cgCollabUnwaive(int $id): bool {
    // These columns are created by cgEnsureTable(), not cgEnsureEditSupport():
    // without this the first press on a freshly deployed server reports them
    // missing and the notice says nothing was cleared when it could have been.
    cgEnsureTable();
    cgEnsureEditSupport();
    if (!cgHasCollabColumn('collab_waived_at')) return false;
    try {
        $st = getDB()->prepare(
            "UPDATE collab_grant_applications SET collab_waived_at = NULL WHERE id = ?"
        );
        $st->execute([$id]);
        return cgRowTouched($st, $id);
    } catch (\Throwable $e) {
        error_log('cgCollabUnwaive: ' . $e->getMessage());
        return false;
    }
}

/**
 * Clear, in one press, every row where no send was ever attempted.
 *
 * That is both the pre-feature backlog and the ones we have no address for:
 * in neither case did an email go, so neither is waiting on a retry. A send
 * that was tried and failed is a live problem with a live Send button, and
 * sweeping it up here would hide the one row that still needs doing.
 *
 * Takes the rows it is clearing, so the button and the press agree on what
 * they are about rather than scanning twice and possibly differing.
 * Returns how many were cleared.
 */
function cgCollabWaiveUntried(string $by, ?array $onlyIds = null): int {
    $n = 0;
    foreach (cgCollabUntried(cgCollaboratorsToFix()) as $a) {
        // The button named the rows it was offering. Anything approved since
        // the page was drawn is somebody else's problem to look at, not
        // something to sweep up on a press aimed at the old backlog.
        if ($onlyIds !== null && !in_array((int)$a['id'], $onlyIds, true)) continue;
        if (cgCollabWaive((int)$a['id'], $by)) $n++;
    }
    return $n;
}

/** Of the outstanding rows, the ones where nothing was ever sent. */
function cgCollabUntried(array $toFix): array {
    return array_values(array_filter($toFix, function ($a) {
        return ($a['_state'] ?? '') !== 'failed';
    }));
}

/**
 * Tell the collaborator, on approval.
 *
 * Returns ['sent' => bool, 'why' => string]. A failure here never stops an
 * approval: the applicant has been told and the grant stands, and the gap is
 * surfaced on the admin page instead.
 */
function cgSendCollaboratorEmail(array $app, bool $force = false): array {
    if (empty($app['collaborator_name'])) return ['sent' => false, 'why' => 'No collaborator named.'];
    // The form hides these inputs rather than disabling them, so a name typed
    // and then "not yet" chosen still arrives. Telling that person a day is
    // coming off their three would be simply wrong.
    if (empty($app['has_collaborator'])) {
        return ['sent' => false, 'why' => 'This application is not down as having a collaborator.'];
    }
    // ── 3: approving twice must not write to them twice.
    if (!$force && !empty($app['collab_notified_at'])) {
        return ['sent' => false, 'why' => 'They were already emailed on '
              . date('M j', strtotime((string)$app['collab_notified_at'])) . '.'];
    }
    // Somebody decided not to write to this person. Re-approving to change the
    // day count must not quietly overturn that decision — the Send button on
    // the panel passes $force and is the way to change your mind.
    if (!$force && !empty($app['collab_waived_at'])) {
        return ['sent' => false, 'why' => 'It was decided on '
              . date('M j', strtotime((string)$app['collab_waived_at']))
              . ' not to email them.'];
    }

    $addr = cgCollaboratorAddress($app);
    if ($addr['email'] === '') return ['sent' => false, 'why' => $addr['why']];
    // Checked after the address, so the refusal can name who it would have
    // written to rather than saying something vague about the name typed.
    if (cgEmailSuppressed($addr['email'], (string)$app['collaborator_name'], (int)($app['school_year'] ?? 0))) {
        return ['sent' => false,
                'why'  => 'the cap does not apply to them, so the grant does not write to them.'];
    }

    $days    = cgEffectiveDays($app);
    $dayWord = abs($days - 1) < 0.001 ? 'day' : 'days';

    $tv = [
        '{{collaborator}}' => (string)$app['collaborator_name'],
        '{{applicant}}'    => (string)$app['applicant_name'],
        '{{days}}'         => cgFmtDays($days),
        '{{day_word}}'     => $dayWord,
    ];

    /* Which days, not just how many.
     *
     * The booking paragraph opens "once you have your date(s) confirmed", and
     * the applicant knows their own dates because they chose them. The
     * collaborator was named on somebody else's form: they were told they had
     * a day and sent to Atrieve to book it, without ever being told which day
     * it was. Written here rather than put in the template, because the
     * template can be edited and a booking date that can go missing is worse
     * than none at all.
     *
     * They are released on the same days as the lead — the same days the
     * ledger charges them for. */
    /* cgAppDates(), not the raw column: it refuses a date list that does not
     * match the days granted, and those are the dates the ledger charges. Off
     * the raw column, an application proposing three dates with one day
     * approved told the collaborator to book three days in a letter whose
     * next paragraph said one. */
    $bookDates = cgAppDateWeights($app);
    if ($bookDates) {
        $dateLine = 'The ' . (count($bookDates) === 1 ? 'day' : 'days') . ' to book: '
                  . cgPartsList($bookDates) . "\n\n";
    } elseif (cgDateList($app['proposed_dates'] ?? '') !== 'none') {
        /* Dates are on the application, but not as many as the days granted,
         * so which of them this release covers is genuinely unknown. Saying
         * "still to be settled" would contradict the applicant's own letter,
         * which lists them. */
        $dateLine = 'The application lists ' . cgDateList($app['proposed_dates'] ?? '', $app['date_parts'] ?? null)
                  . ', which does not match the ' . cgFmtDays($days) . ' ' . $dayWord
                  . ' approved — ' . (string)$app['applicant_name']
                  . " will confirm which with you.\n\n";
    } else {
        $dateLine = 'The dates are still to be settled — ' . (string)$app['applicant_name']
                  . " will confirm them with you.\n\n";
    }

    $body = emailTplBlock('collab_collaborator', 'intro', $tv) . "\n\n"
          . "─── NEXT STEP: BOOKING YOUR ABSENCE IN ATRIEVE ────────────────────\n\n"
          . $dateLine
          // The same block the applicant is sent, so the two accounts of how to
          // book can never drift apart.
          . emailTplBlock('collab_approved', 'booking',
                          ['{{name}}' => (string)$app['collaborator_name'],
                           '{{days}}' => cgFmtDays($days), '{{day_word}}' => $dayWord,
                           '{{collab_line}}' => '']) . "\n\n"
          . "─────────────────────────────────────────────────────────────────────\n\n"
          . emailTplBlock('collab_collaborator', 'signoff', $tv) . "\n"
          . "\n─────────────────────────────────────────────────────────────────────\n"
          . "Not you, or not something you agreed to? Say so here and nothing will\n"
          . "be booked in your name:\n"
          . cgCollabDisputeUrl((int)$app['id']) . "\n";

    // A throw has to leave the same trace as a false. Otherwise a send that may
    // even have gone out reads as "never tried", and the bulk clear retires it
    // for good — the one outcome this panel exists to prevent.
    try {
        $ok = siteMail($addr['email'], emailTplSubject('collab_collaborator', $tv), $body);
    } catch (\Throwable $e) {
        error_log('cgSendCollaboratorEmail: ' . $e->getMessage());
        cgNoteCollabAttempt((int)$app['id'], 'The email could not be sent.');
        return ['sent' => false, 'why' => 'The email could not be sent.'];
    }
    if ($ok && cgHasCollabColumn('collab_notified_at')) {
        $sql = cgHasCollabEmail()
            ? "UPDATE collab_grant_applications SET collab_notified_at = NOW(), collaborator_email = ? WHERE id = ?"
            : "UPDATE collab_grant_applications SET collab_notified_at = NOW() WHERE id = ?";
        $args = cgHasCollabEmail() ? [$addr['email'], (int)$app['id']] : [(int)$app['id']];
        getDB()->prepare($sql)->execute($args);
    }
    // Remembered either way, so the panel can say "tried on the 3rd and it
    // bounced" instead of accusing us of a failure nobody ever attempted.
    if (!$ok) cgNoteCollabAttempt((int)$app['id'], 'The email could not be sent.');
    return ['sent' => (bool)$ok, 'why' => $ok ? '' : 'The email could not be sent.'];
}

function cgSendApprovalEmail(array $app): void {
    $name      = $app['applicant_name'];
    $email     = $app['applicant_email'];
    if (cgEmailSuppressed($email, (string)$name, (int)($app['school_year'] ?? 0))) return;
    $days      = cgEffectiveDays($app);
    $dayWord   = abs($days - 1) < 0.001 ? 'day' : 'days';
    // has_collaborator as well as a name: the form hides its collaborator
    // inputs rather than disabling them, so a name typed and then "not yet"
    // chosen still arrives, and talking about a collaborator they said they do
    // not have is nonsense.
    $hasCollab = !empty($app['collaborator_name']) && !empty($app['has_collaborator']);
    $collab    = $app['collaborator_name'] ?? '';

    // What this says depends on whether we could actually reach them. Telling
    // somebody we have it covered when we have not is how a collaborator ends
    // up never hearing about a day booked against their name.
    $collabLine = '';
    if ($hasCollab) {
        // Keyed on the send having happened, not on an address existing. A
        // failed send used to tell the applicant it was handled, and then
        // nobody told the collaborator at all.
        if (!empty($app['collab_notified_at'])) {
            $collabLine = "We have emailed {$collab} as well, so they know to submit their own absence in Atrieve for the days you'll be working together.";
        } elseif (!empty($app['collab_waived_at'])) {
            // Somebody decided not to write to them, so asking the applicant to
            // forward it both contradicts that decision and quietly carries it
            // out anyway. Say nothing; the decision is on the application.
            $collabLine = '';
        } elseif (cgEmailSuppressed(cgCollaboratorAddress($app)['email'],
                                    (string)$collab,
                                    (int)($app['school_year'] ?? 0))) {
            // "Please forward this to them" is the collaborator notice
            // delivered by hand, which is the one thing suppression exists to
            // prevent. Say nothing; the admin is told to tell them instead.
            $collabLine = '';
        } else {
            // Two different reasons we did not reach them, and only one of them
            // is "no address". Telling an applicant we have no address for
            // somebody we plainly do have an address for reads as carelessness,
            // and they cannot act on it either way except by forwarding.
            // Asked of the same resolver the send itself uses, not of the
            // raw column: an address matched out of Contacts is one we have,
            // and telling the applicant otherwise is the same lie in a
            // narrower case.
            $why = cgCollaboratorAddress($app)['email'] !== ''
                ? "we could not get an email through to {$collab}"
                : "we do not have an email address for {$collab}";
            $collabLine = ucfirst($why) . ", so please forward this to them "
                        . "so they can submit their own absence in Atrieve for the days "
                        . "you'll be working together.";
        }
    }

    $tv = ['{{name}}' => $name, '{{days}}' => cgFmtDays($days),
           '{{day_word}}' => $dayWord, '{{collab_line}}' => $collabLine];

    $subject = emailTplSubject('collab_approved', $tv);
    $body = emailTplBlock('collab_approved', 'intro', $tv) . "\n\n"
          . "─── NEXT STEP: BOOKING YOUR ABSENCE IN ATRIEVE ────────────────────\n\n"
          // What was granted, date by date. The Executive may have granted a
          // morning where a full day was asked, or turned one date down, and
          // the edit page shows what was asked, not what was decided.
          . (($granted = cgAppDateWeights($app))
                ? 'The ' . (count($granted) === 1 ? 'day' : 'days') . ' to book: '
                  . cgPartsList($granted) . "\n\n"
                : '')
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
    $days      = cgFmtDays($app['days_requested']);
    $collab    = !empty($app['collaborator_name'])
        ? $app['collaborator_name'] . ' (' . ($app['collaborator_school'] ?? '') . ')'
        : ($app['needs_partner'] ? 'None — needs help finding partner' : 'None identified');

    $datesLine = '';
    if (!empty($app['proposed_dates'])) {
        $dates = json_decode($app['proposed_dates'], true);
        if (is_array($dates) && count($dates)) {
            $datesLine = "\nProposed dates:   " . cgDateList($dates, $app['date_parts'] ?? null);
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
    if (cgEmailSuppressed($email, (string)$name, (int)($app['school_year'] ?? 0))) return;
    $tv      = ['{{name}}' => $name];
    $subject = emailTplSubject('collab_received', $tv);
    $body    = emailTplBlock('collab_received', 'body', $tv) . "\n" . cgEditFooter((int)$app['id']);

    siteMail($email, $subject, $body);
}

/**
 * Invoice lines: one grant can be billed across several district invoices.
 *
 * Each teacher a grant releases is a TTOC of their own, billed on its own —
 * and not always on the same invoice as the others. So a grant holds any
 * number of lines, each for one person on it (or the grant as a whole, for
 * what was recorded before lines had a person): the invoice number and what
 * it charged. The grant's cost is the sum.
 *
 * invoice_number and release_cost on the application stay, as the summary of
 * the lines ("556, 571" and their sum), so everything that already reads them
 * — the year's cost, the Follow-through queue, the lock on the applicant's
 * edit, the fold refusal, the CSVs — keeps working unchanged. Only
 * cgSetFulfilment() writes them, and always from the lines.
 */
function cgEnsureInvoiceLines(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    $db = getDB();
    try {
        $db->query("SELECT 1 FROM collab_grant_invoices LIMIT 1");
        return $ok = true;
    } catch (\PDOException $e) { /* not there yet */ }
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS collab_grant_invoices (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            app_id         INT NOT NULL,
            person         VARCHAR(255) NOT NULL DEFAULT '',
            invoice_number VARCHAR(100) NOT NULL DEFAULT '',
            amount         DECIMAL(10,2) DEFAULT NULL,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by     VARCHAR(255) NOT NULL DEFAULT '',
            INDEX idx_app (app_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // The single invoice and cost every grant had until now become its
        // first line. NOT EXISTS so a second request racing this one cannot
        // copy them twice.
        $db->exec("INSERT INTO collab_grant_invoices (app_id, invoice_number, amount, created_by)
                   SELECT a.id, a.invoice_number, a.release_cost, 'carried over'
                   FROM collab_grant_applications a
                   WHERE (a.invoice_number <> '' OR a.release_cost IS NOT NULL)
                     AND NOT EXISTS (SELECT 1 FROM collab_grant_invoices i WHERE i.app_id = a.id)");
        return $ok = true;
    } catch (\Exception $e) {
        error_log('cgEnsureInvoiceLines: ' . $e->getMessage());
        return $ok = false;
    }
}

/**
 * Every application's invoice lines, app_id => [['person', 'invoice_number', 'amount'], …],
 * in the order entered. $apps supplies the fallback: where the table could not
 * be made, an application's own invoice and cost are its one line.
 */
function cgInvoiceLinesFor(array $apps): array {
    $out = [];
    cgInvoiceReadFailed(false);
    $ids = array_values(array_filter(array_map(function ($a) { return (int)$a['id']; }, $apps)));
    if ($ids && cgEnsureInvoiceLines()) {
        try {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = getDB()->prepare("SELECT app_id, person, invoice_number, amount FROM collab_grant_invoices
                                    WHERE app_id IN ($in) ORDER BY id");
            $st->execute($ids);
            foreach ($st->fetchAll() as $r) {
                $out[(int)$r['app_id']][] = ['person' => (string)$r['person'],
                                             'invoice_number' => (string)$r['invoice_number'],
                                             'amount' => $r['amount'] === null ? null : (float)$r['amount']];
            }
        } catch (\Exception $e) {
            error_log('cgInvoiceLinesFor: ' . $e->getMessage());
            cgInvoiceReadFailed(true);
        }
    } elseif ($ids) {
        cgInvoiceReadFailed(true);
    }
    /* An application with figures of its own and no lines was never carried
     * over — the copy failed, or the table could not be made. Its figures are
     * its one line, so the form shows them and saving it keeps them rather
     * than writing an empty set over a real invoice. */
    foreach ($apps as $a) {
        if (isset($out[(int)$a['id']])) continue;
        if (trim((string)($a['invoice_number'] ?? '')) !== '' || ($a['release_cost'] ?? null) !== null) {
            // 'unread': the real lines exist but could not be read, so this
            // summary says nothing about who has been billed.
            $out[(int)$a['id']][] = ['person' => '', 'unread' => cgInvoiceReadFailed(),
                                     'invoice_number' => (string)($a['invoice_number'] ?? ''),
                                     'amount' => ($a['release_cost'] ?? null) === null ? null : (float)$a['release_cost']];
        }
    }
    return $out;
}

/**
 * The teachers a grant releases, by name: the applicant, everyone named as a
 * collaborator, and the applicants of any copies folded under it (usually
 * the same collaborators, applying themselves). Each is a TTOC to pay for.
 */
function cgGrantPeople(array $app, array $folded = []): array {
    // Only the people a release costs: a teacher the cap does not apply to is
    // released at no cost to the local, so there is nothing to invoice or count.
    return array_column(array_filter(cgGrantPeopleDetail($app, $folded),
                                     fn($p) => !$p['exempt']), 'name');
}

/**
 * Everyone a grant releases, with what is known about each: 'known' when an
 * address identifies them, 'from' — the applications whose collaborator field
 * names them, so a misspelled name can be corrected where it was typed — and
 * 'exempt', the reason when the cap does not apply to them, '' otherwise.
 * cgGrantPeople() is the same list without the exempt, who cost nothing.
 */
function cgGrantPeopleDetail(array $app, array $folded = []): array {
    $year   = (int)($app['school_year'] ?? 0);
    $out    = [];
    $seenId = [];
    $seenNm = [];   // name key => index in $out
    /* Who someone is, then what to call them: an email address settles that
     * a preferred name on one form and a full name on another are one
     * teacher, the same way the days ledger decides it. A name is all there
     * is to go on only where no address is known. */
    $add = function (string $id, string $name, int $fromApp = 0) use (&$out, &$seenId, &$seenNm) {
        $name = trim($name);
        $nk   = cgNameKey($name);
        if ($nk === '') return;
        $at = $id !== '' && isset($seenId[$id]) ? $seenId[$id] : ($seenNm[$nk] ?? null);
        if ($at === null) {
            $at = count($out);
            $out[] = ['name' => $name, 'known' => $id !== '', 'from' => [], 'id' => $id];
        }
        if ($id !== '') {
            $seenId[$id] = $at; $out[$at]['known'] = true;
            if ($out[$at]['id'] === '') $out[$at]['id'] = $id;
        }
        $seenNm[$nk] = $at;
        if ($fromApp && !in_array($fromApp, $out[$at]['from'], true)) $out[$at]['from'][] = $fromApp;
    };
    $copies = [$app];
    foreach ($folded as $f) {
        // Only copies that release anyone — the same approved-or-pending test
        // the days ledger uses. A declined copy put nobody in a classroom.
        if (in_array($f['status'] ?? '', ['approved', 'pending'], true)) $copies[] = $f;
    }
    foreach ($copies as $r) {
        $em = cgCanonEmail((string)($r['applicant_email'] ?? ''));
        $add($em !== '' ? 'e:' . $em : '', (string)$r['applicant_name']);
    }
    // Collaborators after every applicant, so a teacher who both applied and
    // was named keeps the name they gave on their own form.
    foreach ($copies as $r) {
        if (empty($r['has_collaborator'])) continue;
        $named = cgSplitNames((string)($r['collaborator_name'] ?? ''));
        $typed = cgCanonEmail((string)($r['collaborator_email'] ?? ''));
        if (count($named) === 1 && $typed !== '' && filter_var($typed, FILTER_VALIDATE_EMAIL)) {
            $add('e:' . $typed, $named[0], (int)$r['id']);
            continue;
        }
        foreach ($named as $n) {
            $hit = cgResolveCollaborator($n, cgContactsByName(), cgApplicantsByName($year));
            $add(strpos($hit['key'], 'e:') === 0 ? $hit['key'] : '', $n, (int)$r['id']);
        }
    }
    foreach ($out as &$p) {
        $email = strpos($p['id'], 'e:') === 0 ? substr($p['id'], 2) : '';
        $ex = cgExemptFor(['key' => $p['id'] !== '' ? $p['id'] : 'n:' . cgNameKey($p['name']),
                           'email' => $email, 'name' => $p['name']], $year);
        $p['exempt'] = $ex === null ? '' : ((string)($ex['reason'] ?? '') !== '' ? (string)$ex['reason'] : 'not capped');
    }
    unset($p);
    return $out;
}

/**
 * Two names on one grant are one teacher, one of them misspelled: correct the
 * spelling where it was typed, and move any invoice line across.
 *
 * $from is the name as it appears on the grant's rows, $to the right one.
 * Every application in $appIds whose collaborator field names $from has that
 * name replaced. A spelling fix, not a change of person, so the record of
 * telling the collaborator is left alone (cgApplyEdit() would reset it, as it
 * should for a different teacher) and the "changed after approval" stamp is
 * not set. Written to the edit history like any other change.
 * Returns how many applications were corrected.
 */
function cgMergeGrantPerson(int $primaryId, array $appIds, string $from, string $to, string $actor): int {
    cgEnsureEditSupport();
    $fk = cgNameKey($from);
    $to = trim(preg_replace('/\s+/', ' ', $to) ?? '');
    if ($fk === '' || $to === '' || $fk === cgNameKey($to)) return 0;
    $db = getDB();
    $n  = 0;
    foreach (array_unique(array_map('intval', $appIds)) as $id) {
        $app = cgGetApplication($id);
        if (!$app) continue;
        $was   = (string)($app['collaborator_name'] ?? '');
        $parts = cgSplitNames($was);
        $hit   = false;
        $new   = [];
        foreach ($parts as $p) {
            if (cgNameKey($p) === $fk) { $p = $to; $hit = true; }
            if (!in_array(cgNameKey($p), array_map('cgNameKey', $new), true)) $new[] = $p;
        }
        if (!$hit) continue;
        $now = implode(', ', $new);
        if (mb_strlen($now) > 255) continue;
        $db->prepare("UPDATE collab_grant_applications SET collaborator_name=?, edited_at=NOW(), edited_by=? WHERE id=?")
           ->execute([$now, $actor, $id]);
        $db->prepare("INSERT INTO collab_grant_edits (app_id, changed_by, by_admin, changes) VALUES (?,?,1,?)")
           ->execute([$id, $actor, json_encode(['collaborator_name' => [$was, $now]], JSON_UNESCAPED_UNICODE)]);
        $n++;
    }
    // The invoice reader's record of saved absences moves with the name, or
    // the corrected name would read as typed in by hand. Where both names
    // already hold the same absence, the one on the right name stays.
    if (function_exists('cgEnsureInvoiceImports') && cgEnsureInvoiceImports()) {
        try {
            $db->prepare("UPDATE IGNORE collab_grant_invoice_imports SET person_key=? WHERE app_id=? AND person_key=?")
               ->execute([cgNameKey($to), $primaryId, $fk]);
            $db->prepare("DELETE FROM collab_grant_invoice_imports WHERE app_id=? AND person_key=?")
               ->execute([$primaryId, $fk]);
        } catch (\Exception $e) { error_log('cgMergeGrantPerson imports: ' . $e->getMessage()); }
    }
    if (cgEnsureInvoiceLines()) {
        $st = $db->prepare("SELECT id, person FROM collab_grant_invoices WHERE app_id=?");
        $st->execute([$primaryId]);
        $up = $db->prepare("UPDATE collab_grant_invoices SET person=? WHERE id=?");
        foreach ($st->fetchAll() as $l) {
            if (cgNameKey((string)$l['person']) === $fk) $up->execute([$to, (int)$l['id']]);
        }
    }
    cgCachesReset();
    return $n;
}

/**
 * Whether the last cgInvoiceLinesFor() could not read the lines and showed
 * each grant's summary instead. A form built from that must not be saved over
 * real lines: they may hold per-person detail the summary does not.
 */
function cgInvoiceReadFailed(?bool $set = null): bool {
    static $failed = false;
    if ($set !== null) $failed = $set;
    return $failed;
}

/**
 * How many of the people on a grant have a cost recorded: [costed, people].
 * A cost for the grant as a whole covers everyone; so does 0, for a release
 * that cost the local nothing.
 */
function cgCostedCount(array $lines, array $people): array {
    $n = count($people);
    foreach ($lines as $l) if (!empty($l['unread'])) return [0, $n];   // not known
    $done = [];
    foreach ($lines as $l) {
        if ($l['amount'] === null) continue;
        if (trim((string)$l['person']) === '') return [$n, $n];
        $done[cgNameKey((string)$l['person'])] = true;
    }
    $c = 0;
    foreach ($people as $p) if (!empty($done[cgNameKey($p)])) $c++;
    return [$c, $n];
}

/** A line with a number and no amount, or an amount and no number, is not finished. */
function cgInvoiceLinesOpen(array $lines): bool {
    foreach ($lines as $l) {
        if (trim((string)$l['invoice_number']) === '' || $l['amount'] === null) return true;
    }
    return false;
}

/**
 * Is the grant's invoicing unfinished? A half-filled line is, and so is
 * anyone on the grant with no complete line of their own — each teacher is
 * a TTOC that gets billed. 0 is a complete amount, for someone whose release
 * cost the local nothing. A complete line for the whole grant, which is what
 * every grant recorded before lines had a person holds, covers everyone.
 */
function cgInvoicingOpen(array $lines, array $people): bool {
    if (cgInvoiceLinesOpen($lines)) return true;
    // Lines that could not be read prove nothing finished; keep it open.
    foreach ($lines as $l) if (!empty($l['unread'])) return true;
    $done = [];
    foreach ($lines as $l) {
        if (trim((string)$l['person']) === '') return false;
        $done[cgNameKey((string)$l['person'])] = true;
    }
    foreach ($people as $p) {
        if (empty($done[cgNameKey($p)])) return true;
    }
    return false;
}

/**
 * Record the follow-through on an approved grant: the district's invoices for
 * it, which also confirms the absence is in Atrieve.
 *
 * The confirmation stamps who and when, because "somebody said it was done" is
 * not much of a record a year later when the district queries an invoice.
 * Clearing every line clears the stamp, so the two can never disagree.
 *
 * $lines is every invoice line as the form holds it, [['person' =>,
 * 'invoice_number' =>, 'amount' =>], …] — or a function given the locked
 * application row that returns them, for a caller building on the current lines; a line with both boxes empty is dropped, and the set
 * replaces what was there. An amount left empty is "not recorded", not $0.
 * Returns '' on success or the reason it was refused.
 */
function cgSetFulfilment(int $id, $lines, string $actor, bool $builtBlind = false,
                         ?callable $alsoInside = null): string {
    cgEnsureTable();
    $db = getDB();
    try {
        $hasLines = cgEnsureInvoiceLines();
        // The form was built when the lines could not be read. If there are
        // lines now, saving the summary it showed would erase them.
        if ($builtBlind && $hasLines) {
            $c = $db->prepare("SELECT COUNT(*) FROM collab_grant_invoices WHERE app_id=?");
            $c->execute([$id]);
            if ((int)$c->fetchColumn() > 0) {
                return 'The invoices could not be read when this page loaded, so nothing was saved. Reload the page and try again.';
            }
        }
        // The lines are the record and the summary is worked out from them.
        // Saving one without the other would leave them disagreeing once the
        // table is back, so nothing is saved at all.
        if (!$hasLines) {
            return 'The invoices cannot be saved just now, so nothing was changed. The problem has been logged.';
        }
        $db->beginTransaction();
        /* One save at a time per grant. The row lock is held to the commit, so
         * a caller that works out new lines from the current ones — the
         * invoice reader adding absences — reads them only once any other
         * save has finished, and cannot overwrite it with a stale total. */
        $lk = $db->prepare("SELECT * FROM collab_grant_applications WHERE id=? FOR UPDATE");
        $lk->execute([$id]);
        $cur = $lk->fetch();
        if (!$cur) { $db->rollBack(); return 'That application could not be found.'; }
        if (is_callable($lines)) $lines = $lines($cur);
        if (!is_array($lines)) { $db->rollBack(); return 'Nothing was saved.'; }

        $clean = [];
        foreach ($lines as $l) {
            $num = mb_substr(trim((string)($l['invoice_number'] ?? '')), 0, 100);
            $raw = str_replace([',', '$', ' '], '', trim((string)($l['amount'] ?? '')));
            if ($num === '' && $raw === '') continue;
            if ($raw !== '' && (!is_numeric($raw) || (float)$raw < 0)) {
                $db->rollBack();
                return '"' . trim((string)$l['amount']) . '" is not a dollar amount.';
            }
            $clean[] = ['person' => mb_substr(trim((string)($l['person'] ?? '')), 0, 255),
                        'invoice_number' => $num, 'amount' => $raw === '' ? null : round((float)$raw, 2)];
        }

        // The summary everything else reads: the numbers, and what is known of the cost.
        $nums = [];
        $sum  = null;
        foreach ($clean as $l) {
            if ($l['invoice_number'] !== '' && !in_array($l['invoice_number'], $nums, true)) $nums[] = $l['invoice_number'];
            if ($l['amount'] !== null) $sum = round(($sum ?? 0) + $l['amount'], 2);
        }
        $summary = implode(', ', $nums);
        if (mb_strlen($summary) > 100) $summary = mb_substr($summary, 0, 99) . '…';

        // Entering the district's invoice or cost is the confirmation that the
        // release is in Atrieve, so there is no separate tick: the stamp
        // follows the lines.
        $atrieve = (bool)$clean;
        $was = !empty($cur['atrieve_confirmed']);
        if ($atrieve && !$was) {
            $db->prepare("UPDATE collab_grant_applications
                          SET atrieve_confirmed=1, atrieve_confirmed_at=NOW(), atrieve_confirmed_by=? WHERE id=?")
               ->execute([$actor, $id]);
        } elseif (!$atrieve) {
            $db->prepare("UPDATE collab_grant_applications
                          SET atrieve_confirmed=0, atrieve_confirmed_at=NULL, atrieve_confirmed_by='' WHERE id=?")
               ->execute([$id]);
        }   // already confirmed and still ticked: keep the original stamp
        $db->prepare("UPDATE collab_grant_applications SET invoice_number=?, release_cost=? WHERE id=?")
           ->execute([$summary, $sum, $id]);

        $db->prepare("DELETE FROM collab_grant_invoices WHERE app_id=?")->execute([$id]);
        $ins = $db->prepare("INSERT INTO collab_grant_invoices (app_id, person, invoice_number, amount, created_by)
                             VALUES (?,?,?,?,?)");
        foreach ($clean as $l) $ins->execute([$id, $l['person'], $l['invoice_number'], $l['amount'], $actor]);

        // Anything that must stand or fall with these lines — the reader's
        // record of which absences it saved — is written in the same
        // transaction, so a cost is never saved without its record.
        if ($alsoInside) $alsoInside($db);
        $db->commit();
        return '';
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('cgSetFulfilment: ' . $e->getMessage());
        return 'Could not save. The problem has been logged.';
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
            "SELECT * FROM collab_grant_applications WHERE school_year=? AND status IN ('approved','pending')");
        $s->execute([$year]);
        $rows = $s->fetchAll();
    } catch (Exception $e) {
        // Flagged, not swallowed: zeros here would render a confident "$0.00"
        // that is indistinguishable from a year in which nothing was spent.
        error_log('cgYearReleaseCost: ' . $e->getMessage());
        $out['error'] = true;
        return $out;
    }
    $folded = [];
    foreach ($rows as $r) {
        if (!empty($r['duplicate_of'])) $folded[(int)$r['duplicate_of']][] = $r;
    }
    // duplicate_of IS NULL: a collaboration entered twice is costed once.
    // Counting the folded one as approved-but-not-costed left "1 not yet
    // costed" showing for ever against an invoice that was never going to exist.
    $grants = array_values(array_filter($rows, function ($r) {
        return $r['status'] === 'approved' && empty($r['duplicate_of']);
    }));
    $lines = cgInvoiceLinesFor($grants);
    foreach ($grants as $g) {
        $out['approved']++;
        if (($g['release_cost'] ?? null) !== null) $out['total'] += (float)$g['release_cost'];
        /* Costed means finished: every person on it billed, every line with its
         * amount. A grant with one teacher's TTOC recorded and another's still
         * to come has a subtotal in the total, but is not counted as costed —
         * or the panel would drop its "so far" for a figure that is not final. */
        if (($g['release_cost'] ?? null) !== null
            && !cgInvoicingOpen($lines[(int)$g['id']] ?? [], cgGrantPeople($g, $folded[(int)$g['id']] ?? []))) {
            $out['costed']++;
        }
    }
    $out['total'] = round($out['total'], 2);
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
    'date_parts'          => 'Part days',
    'days_requested'      => 'Days requested',
];

/** Changes that alter what was approved, as opposed to fixing a typo. */
const CG_MATERIAL = ['days_requested', 'proposed_dates', 'date_parts', 'collaborator_name'];

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
        // What the Executive actually granted, which need not be what was asked
        // for. NULL means nobody has decided yet, which is not the same as zero.
        'days_approved'    => 'DECIMAL(4,2) DEFAULT NULL',
        // The dates that are less than a full day, as {"Y-m-d": 0.6}. NULL
        // or {} means every listed date is a whole day.
        'date_parts'       => 'TEXT DEFAULT NULL',
        // What the Executive decided for each listed date, {"Y-m-d": 0.6},
        // with 0 for a date not granted. Kept apart from date_parts, which is
        // what was asked. NULL until an approval decides it date by date.
        'granted_parts'    => 'TEXT DEFAULT NULL',
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

    // days_approved began as INT; a part day (0.6) needs a decimal. MODIFY
    // keeps every whole number already there. Best effort, like the columns
    // above: a host that cannot ALTER keeps whole days and loses nothing.
    // days_requested too, now that it is the sum of the dates' parts.
    foreach (['days_approved'  => 'DECIMAL(4,2) DEFAULT NULL',
              'days_requested' => 'DECIMAL(4,2) NOT NULL DEFAULT 1'] as $c => $type) {
        try {
            $col = $db->query("SHOW COLUMNS FROM collab_grant_applications LIKE '$c'")->fetch();
            if ($col && stripos((string)$col['Type'], 'decimal') !== 0) {
                $db->exec("ALTER TABLE collab_grant_applications MODIFY COLUMN `$c` $type");
            }
        } catch (\Exception $e) {}
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
        // Best-effort column: a host that could not add it keeps whole days.
        if ($col === 'date_parts' && !cgHasCollabColumn('date_parts')) continue;
        $new = $values[$col];
        $old = $app[$col] ?? null;
        if ($col === 'date_parts') {
            // Compared as what they mean, so {} and NULL are the same nothing.
            $norm = function ($v) {
                $m = cgPartsOf(['date_parts' => is_array($v) ? json_encode($v) : (string)$v]);
                return $m ? json_encode($m) : '';
            };
            $new = $norm($new);
            $oldCmp = $norm($old);
        } else {
            $new = in_array($col, ['has_collaborator', 'needs_partner'], true) ? (int)(bool)$new
                 : ($col === 'days_requested' ? round((float)$new, 2) : trim((string)$new));
            $oldCmp = in_array($col, ['has_collaborator', 'needs_partner'], true) ? (int)$old
                    : ($col === 'days_requested' ? round((float)$old, 2) : trim((string)$old));
        }
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

    // Naming a different collaborator makes every record of the telling wrong:
    // the new person has not been emailed, no send to them has failed, and
    // nobody has decided not to write to them. Left alone, a new name would
    // inherit the old one's "emailed" or "cleared" and never be flagged — a
    // release day against their name that they never hear about.
    // Keyed on the NAME only. A has_collaborator toggle leaves the same person
    // named: wiping their address and the proof we emailed them, then flagging
    // them again when it is toggled back, means a second "a day is counted
    // against your three" to somebody who already had one.
    // Compared the way Contacts compares them: fixing "pat smith" to
    // "Pat Smith" is the same teacher, and wiping the record would re-flag them
    // for a second "a day is counted against your three".
    // Only when the people named actually change. Rewriting "A and B" as
    // "A, B" names the same two teachers and must leave the record alone;
    // anybody added or removed invalidates it, because it cannot be held per
    // person. See cgCollaboratorSetChanged().
    if (array_key_exists('collaborator_name', $changed)
        && cgCollaboratorSetChanged((string)$changed['collaborator_name'][0],
                                    (string)$changed['collaborator_name'][1])) {
        cgResetCollabNotice($id);
    }

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
        } elseif ($col === 'date_parts') {
            $pl = function ($v) {
                $m = cgPartsOf(['date_parts' => (string)$v]);
                return $m ? cgPartsList($m) : 'all full days';
            };
            $parts[] = 'Part days: ' . $pl($pair[0]) . ' → ' . $pl($pair[1]);
        } elseif ($col === 'days_requested') {
            $parts[] = $label . ': ' . cgFmtDays($pair[0]) . ' → ' . cgFmtDays($pair[1]);
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

/** A stored proposed_dates JSON value as readable dates. With the
 *  application's date_parts, a short day is named: "Thu Nov 5 (morning)". */
function cgDateList($json, $partsJson = null): string {
    $a = is_array($json) ? $json : json_decode((string)$json, true);
    if (!is_array($a) || !$a) return 'none';
    $parts = cgPartsOf(['date_parts' => (string)$partsJson]);
    return implode(', ', array_map(function ($d) use ($parts) {
        $iso = date('Y-m-d', strtotime((string)$d));
        return date('D M j', strtotime((string)$d))
             . (isset($parts[$iso]) ? ' (' . cgPartLabel($parts[$iso]) . ')' : '');
    }, $a));
}

/**
 * Tell the president an application changed.
 *
 * Sent for every applicant edit, not only edits after approval: a change to a
 * pending application still arrives at the Executive meeting differently from
 * the copy that was printed for it.
 */
function cgSendApplicantEditNotice(array $app, array $changed): void {
    if (cgEmailSuppressed($app['applicant_email'] ?? '', (string)$app['applicant_name'], (int)($app['school_year'] ?? 0))) return;
    $body = 'Hi ' . $app['applicant_name'] . ",\n\n"
          . "The BVTU has updated your Collaboration Grant application.\n\n"
          . "What changed:\n  " . str_replace('; ', "\n  ", cgDescribeChanges($changed)) . "\n\n"
          . 'Your release days are now: ' . cgDateList($app['proposed_dates'], $app['date_parts'] ?? null)
          . ' (' . cgDaysLine($app) . ").\n\n"
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
          . 'Release days: ' . cgDaysLine($app) . "\n"
          . 'Dates now: ' . cgDateList($app['proposed_dates'], $app['date_parts'] ?? null) . "\n\n"
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
    // Returns false rather than true: the caller reports whether a link went,
    // and claiming one did would leave somebody waiting for an email that was
    // never going to arrive.
    if (cgEmailSuppressed($app['applicant_email'] ?? '', (string)$app['applicant_name'], (int)($app['school_year'] ?? 0))) return false;
    $tv   = ['{{name}}' => $app['applicant_name']];
    $body = emailTplBlock('collab_edit_link', 'body', $tv) . "\n" . cgEditFooter((int)$app['id']);
    $ok   = siteMail($app['applicant_email'], emailTplSubject('collab_edit_link', $tv), $body);
    if ($ok) {
        getDB()->prepare("UPDATE collab_grant_applications SET link_sent_at=NOW(), link_sent_by=? WHERE id=?")
               ->execute([$actor, (int)$app['id']]);
    }
    return $ok;
}

// ── Days used, per person, per year ──────────────────────────────────────────
//
// The grant is three release days per person per school year, and the FAQ
// promises priority to teachers who have not used the fund. Both need a running
// total, and a total built from applicants alone would be wrong: one application
// covers two teachers. The lead applies; the collaborator is named on the same
// form and books their own Atrieve absence for the same days. So a collaborator
// spends their own three days without ever appearing as an applicant.
//
// That is also where the ledger's accuracy runs out. An applicant is identified
// by email address. A collaborator is a typed name with no address, matched
// against the contacts table, so anything attributed to a collaborator is marked
// approximate and the panel says so rather than presenting a guess as a count.

const CG_DAY_CAP = 3;

/*
 * Release days, whole and part.
 *
 * A teacher can be released for part of a day, and what a part is depends on
 * the school: a secondary block is a quarter (0.75 is three of four blocks),
 * an elementary morning is 0.6 and an afternoon 0.4. Each date carries its own
 * part, chosen from CG_DAY_PARTS, so the ledger knows which day was the short
 * one. date_parts holds only the dates that are not a full day; a date missing
 * from it is a whole day, which is every application from before parts existed.
 *
 * days_requested is the sum of the dates' parts and days_approved what the
 * Executive granted, both DECIMAL(4,2). Tenths are not exact in binary, so
 * every total is rounded to hundredths as it is made and the cap is tested with
 * a margin: 0.6 + 0.4 + 2 is 3, never a hair over.
 */

/** What a date can be, in the order offered. Keys are the day value as text. */
const CG_DAY_PARTS = [
    '1'    => 'Full day',
    '0.6'  => 'Morning',
    '0.4'  => 'Afternoon',
    '0.75' => '3 blocks',
    '0.5'  => '2 blocks',
    '0.25' => '1 block',
];

/** Suggestions for the one box left that takes a typed number: a grant with no dates. */
const CG_PART_DAYS = [0.25, 0.4, 0.5, 0.6, 0.75];

/** A typed day count as a number of days, or null when it is not an amount
 *  above zero with at most two decimal places. Accepts "0.6", ".75", "1,5". */
function cgParseDays($raw): ?float {
    $s = str_replace(',', '.', trim((string)$raw));
    if (!preg_match('/^(\d+(\.\d{0,2})?|\.\d{1,2})$/', $s)) return null;
    $n = round((float)$s, 2);
    return $n > 0 ? $n : null;
}

/** A day count as people write it: 2, 1.5, 0.75 — never 2.00. */
function cgFmtDays($n): string {
    $s = number_format(round((float)$n, 2), 2, '.', '');
    return rtrim(rtrim($s, '0'), '.');
}

/** "1 day", "0.75 days", "2 days". */
function cgDaysWord($n): string {
    return cgFmtDays($n) . ' ' . (abs((float)$n - 1) < 0.001 ? 'day' : 'days');
}

/** A submitted part as a day value, or null when it is not one on offer. */
function cgPartValue($raw): ?float {
    $k = cgFmtDays((float)str_replace(',', '.', trim((string)$raw)));
    return array_key_exists($k, CG_DAY_PARTS) && trim((string)$raw) !== '' ? (float)$k : null;
}

/** "morning", "3 blocks" — or the number, for a part no longer on the list. */
function cgPartLabel(float $part): string {
    $k = cgFmtDays($part);
    return isset(CG_DAY_PARTS[$k]) ? strtolower(CG_DAY_PARTS[$k]) : cgDaysWord($part);
}

/**
 * The part of each listed date, as stored: date => value for the short ones.
 *
 * $parts runs alongside $dates (the edit and hand-add forms send one select per
 * date box) or is keyed by date (the approval form). Full days are left out,
 * so an application that never used a part day stores nothing.
 * Returns ['parts' => [...]] or ['error' => string].
 */
function cgCleanParts(array $dates, array $parts, bool $byDate = false): array {
    $out = [];
    foreach (array_values($dates) as $i => $d) {
        $raw = $byDate ? ($parts[$d] ?? '1') : ($parts[$i] ?? '1');
        $v = cgPartValue($raw);
        if ($v === null) return ['error' => 'Choose how much of ' . date('D M j', strtotime($d)) . ' is used.'];
        if ($v < 1) $out[$d] = $v;
    }
    ksort($out);
    return ['parts' => $out];
}

/** Stored date_parts as date => value. Anything unreadable counts as a full day. */
function cgPartsOf(array $app): array {
    $raw = json_decode((string)($app['date_parts'] ?? ''), true);
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $d => $v) {
        $t = strtotime((string)$d);
        $v = round((float)$v, 2);
        if ($t && $v > 0 && $v < 1) $out[date('Y-m-d', $t)] = $v;
    }
    return $out;
}

/** The day value of every listed date, with no check against the days granted. */
function cgAppDayParts(array $app): array {
    $parts = cgPartsOf($app);
    $out   = [];
    foreach (cgAppDatesLoose($app) as $d) $out[$d] = $parts[$d] ?? 1.0;
    return $out;
}

/**
 * What the Executive decided for each listed date, 0 meaning not granted — or
 * [] when nothing was decided date by date, or the dates have changed since.
 * An applicant who moves a date after approval makes the decision about the
 * old one meaningless, and applying it to the new one would be a guess.
 */
function cgGrantedParts(array $app): array {
    $raw = json_decode((string)($app['granted_parts'] ?? ''), true);
    if (!is_array($raw) || !$raw) return [];
    $out = [];
    foreach ($raw as $d => $v) {
        $t = strtotime((string)$d);
        $v = round((float)$v, 2);
        if (!$t || $v < 0 || $v > 1) return [];
        $out[date('Y-m-d', $t)] = $v;
    }
    ksort($out);
    $listed = cgAppDatesLoose($app);
    sort($listed);
    return array_keys($out) === $listed ? $out : [];
}

/**
 * Which part of the day a share covers, so the ledger can tell the same
 * morning claimed twice (counted once) from a morning and an afternoon
 * (a whole day). Blocks carry no position — two blocks on one grant and one
 * on another may be the same blocks — so they are one period and the larger
 * stands. A full day is all of them.
 */
function cgDayPeriod(float $w): string {
    $k = cgFmtDays($w);
    if ($k === '1')   return 'full';
    if ($k === '0.6') return 'am';
    if ($k === '0.4') return 'pm';
    return 'blocks';
}

/** The sum of a date => value map, to hundredths. */
function cgSumParts(array $parts): float {
    $n = 0.0;
    foreach ($parts as $v) $n = round($n + (float)$v, 2);
    return $n;
}

/** "Tue Nov 3, Thu Nov 5 (morning)" from a date => value map. */
function cgPartsList(array $parts): string {
    if (!$parts) return 'none';
    $bits = [];
    foreach ($parts as $d => $v) {
        $bits[] = date('D M j', strtotime((string)$d)) . ($v < 1 ? ' (' . cgPartLabel((float)$v) . ')' : '');
    }
    return implode(', ', $bits);
}

/** The days a grant actually spends: what was granted, or what was asked when
 *  nobody has decided yet. */
function cgEffectiveDays(array $app): float {
    $a = $app['days_approved'] ?? null;
    return round($a === null ? (float)$app['days_requested'] : (float)$a, 2);
}

/** A name reduced to something comparable: case, punctuation and spacing gone. */
function cgNameKey(string $name): string {
    $n = function_exists('mb_strtolower') ? mb_strtolower(trim($name), 'UTF-8') : strtolower(trim($name));
    // Folded rather than stripped: the character class below deletes anything
    // non-ASCII, so without this "René Dubé" would key as "ren dub" and never
    // meet a colleague who typed "Rene Dube".
    $n = strtr($n, [
        'á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','ā'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ē'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ø'=>'o','ō'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ū'=>'u',
        'ç'=>'c','ñ'=>'n','ý'=>'y','ÿ'=>'y','š'=>'s','ž'=>'z','ł'=>'l',
        'ß'=>'ss','æ'=>'ae','œ'=>'oe',
    ]);
    $n = str_replace(['’', '‘', '`', '´'], "'", $n);
    $n = preg_replace('/[^a-z0-9\' ]+/u', ' ', $n);
    $n = preg_replace('/\s+/', ' ', $n);
    return trim((string)$n);
}

/** How far a name may be mistyped before it stops being the same name. */
function cgTypoLimit(string $s): int {
    $n = function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
    if ($n <= 4) return 1;
    if ($n <= 8) return 2;
    return 3;
}

/**
 * The contact a mistyped collaborator name probably meant, or null.
 *
 * Nobody misspells their own name, but the lead types the collaborator's, and
 * "Mcyntire" for "McIntyre" is two transposed letters. An unmatched name is
 * not a small problem: those days sit under the name as typed instead of the
 * person's total, and cgSendCollaboratorEmail() has no address, so the
 * collaborator is never told to book their absence.
 *
 * Offered to an admin, never applied on its own. Two candidates equally close
 * return nothing, the same refusal to guess that ambiguous names already get.
 * A collaborator is often written as a surname alone, so a single typed word is
 * compared against surnames rather than whole names.
 */
function cgNearestPerson(string $typed): ?array {
    $key = cgNameKey($typed);
    if ($key === '' || strlen($key) < 4) return null;

    $tTokens = preg_split('/\s+/', $key) ?: [$key];
    $tLast   = (string)end($tTokens);
    $oneWord = count($tTokens) === 1;

    $best = null;
    $bestD = PHP_INT_MAX;
    $tie   = false;

    foreach (cgContactsByName() as $ck => $c) {
        // It matched after all, so this is not a typo and not our business.
        if ($ck === $key) return null;
        if (!empty($c['ambiguous']) || !empty($c['archived'])) continue;

        $cTokens = preg_split('/\s+/', $ck) ?: [$ck];
        $cLast   = (string)end($cTokens);

        $a   = $oneWord ? $tLast : $key;
        $b   = $oneWord ? $cLast : $ck;
        $d   = levenshtein($a, $b);
        $lim = cgTypoLimit($a);

        // Names that sound alike are allowed to be spelled a little further
        // apart — which is most of how surnames are got wrong.
        if ($d > $lim) {
            if (metaphone($tLast) === '' || metaphone($tLast) !== metaphone($cLast)) continue;
            if ($d > $lim + 1) continue;
        }

        if ($d < $bestD)       { $bestD = $d; $best = $c; $tie = false; }
        elseif ($d === $bestD) { $tie = true; }
    }

    if ($best === null || $tie) return null;
    $email = trim((string)$best['email']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
    return ['name' => $best['name'], 'email' => $email, 'distance' => $bestD];
}

/**
 * Contacts indexed by comparable name, for resolving a typed collaborator.
 *
 * A name shared by two contacts resolves to neither: guessing which colleague
 * used three days is worse than admitting the name is ambiguous.
 */
function cgContactsByName(bool $fresh = false): array {
    static $map = null;
    if ($fresh) $map = null;
    if ($map !== null) return $map;
    $map = [];
    try {
        // SELECT * rather than naming status: the days ledger depends on this
        // map, and a host without that column would throw, return an empty map
        // and quietly split one person into two ledger rows — which is how the
        // three-day cap stops firing without anyone noticing.
        $rows = getDB()->query("SELECT * FROM contacts")->fetchAll();
    } catch (Exception $e) {
        return $map;   // no contacts table yet: every collaborator stays unmatched
    }
    foreach ($rows as $r) {
        $names = [trim($r['first_name'] . ' ' . $r['last_name'])];
        if (trim((string)$r['preferred_name']) !== '') {
            $names[] = trim($r['preferred_name'] . ' ' . $r['last_name']);
        }
        foreach ($names as $n) {
            $k = cgNameKey($n);
            if ($k === '') continue;
            if (isset($map[$k]) && $map[$k]['email'] !== $r['email']) {
                $map[$k]['ambiguous'] = true;
                continue;
            }
            // Keep an ambiguity once found. Writing the entry fresh here let a
            // third contact with the same name clear the flag, and a shared
            // name would then resolve confidently to one address.
            $already = $map[$k]['ambiguous'] ?? false;
            $map[$k] = ['email' => $r['email'], 'name' => trim($r['first_name'] . ' ' . $r['last_name']),
                        'ambiguous' => $already,
                        // Carried, not filtered. The days ledger needs every
                        // contact so one person stays one row in the 3-day
                        // count; only the notification lookup cares that they
                        // have left.
                        'archived'  => ((string)($r['status'] ?? 'active') === 'archived')];
        }
    }
    return $map;
}

/**
 * Split a collaborator field that names more than one person.
 *
 * The field is free text and people use it as such: "Leone MacDonald, Ellen
 * Faubert" was being keyed as a single thirty-character pseudo-person, so the
 * days landed on nobody real and neither teacher could reach the cap from that
 * grant. Each named person is charged the grant's days in full, the same as a
 * sole collaborator — being named means taking those release days off.
 *
 * Splitting has to be earned, because a wrong split invents a teacher and
 * charges them days. Three things must hold, or the field is left whole and
 * behaves exactly as it did before:
 *
 *   - every piece has at least two words, so a stray "team" or "etc" cannot
 *     become a person;
 *   - no piece contains a digit, which rules out "Grade 4 team" and room
 *     numbers;
 *   - at least one piece is the name of a real contact, which is what tells
 *     "Leone MacDonald, Ellen Faubert" apart from "Smithers Secondary,
 *     Riverside Elementary". Shape alone cannot separate those two, and only
 *     one of them is a list of people.
 *
 * Pieces that match nobody still count, exactly as a lone unmatched
 * collaborator does today — they are simply not exact.
 */
/**
 * The collaborator field cut at its separators, with nothing judged.
 *
 * cgSplitNames() asks on top of this whether the pieces look like people.
 * The admin rename boxes do not need that question answered — one box per
 * piece is what the field says, and saving joins them back with the first
 * separator in this list, so the round trip is stable even for a value this
 * list would cut but cgSplitNames() would refuse to call two teachers.
 */
function cgNameParts(string $raw): array {
    $parts = preg_split('/\s*(?:,|;|\n|\r|&|\band\b|\+)\s*/iu', trim($raw)) ?: [];
    return array_values(array_filter(array_map('trim', $parts), function ($p) {
        return $p !== '';
    }));
}

function cgSplitNames(string $raw): array {
    $whole = [trim($raw)];
    $parts = cgNameParts($raw);
    if (count($parts) < 2) return $whole;

    foreach ($parts as $p) {
        $k = cgNameKey($p);
        if (count(preg_split('/\s+/', $k) ?: []) < 2) return $whole;
        if (preg_match('/\d/', $k)) return $whole;
    }

    $byName = cgContactsByName();
    foreach ($parts as $p) {
        $k = cgNameKey($p);
        if ($k !== '' && isset($byName[$k]) && empty($byName[$k]['ambiguous'])) return $parts;
    }
    return $whole;
}

/**
 * Every address a person uses, mapped to the one they are counted under.
 *
 * One teacher applying from work and from home became two ledger rows of one
 * day each, and the three-day cap could not see either. Built from the contact
 * record, where an admin has said these addresses are one person — a shared
 * name is a coincidence and is never merged here.
 *
 * Any number of them. This read contacts.secondary_email, which held exactly
 * one, until a teacher applied from three addresses and there was nowhere to
 * put the third; contact_emails holds as many as somebody turns out to have,
 * and contacts-db.php moved the old column's values into it.
 */
function cgEmailAliases(bool $fresh = false): array {
    static $map = null;
    if ($fresh) $map = null;
    if ($map !== null) return $map;
    $map = [];
    /* contacts-db.php owns contact_emails and no grant page loads it, so
     * without this the table may not exist here yet: the catch below would
     * return an empty map, memoised for the request, and a teacher applying
     * from a second address would get past the three-day cap with nothing on
     * screen to say so. The old column needed no such care, contacts having
     * always existed. Required inside the one function that reads the table,
     * so it cannot stop happening because some other call path changed. */
    require_once __DIR__ . '/contacts-db.php';
    contactsEnsureTables();
    try {
        $rows = getDB()->query(
            "SELECT e.email AS alias, c.email AS primary_email
             FROM contact_emails e JOIN contacts c ON c.id = e.contact_id"
        )->fetchAll();
    } catch (Exception $e) {
        return $map;   // tables not there yet: every address stands alone
    }
    foreach ($rows as $r) {
        $primary = strtolower(trim((string)($r['primary_email'] ?? '')));
        $alias   = strtolower(trim((string)($r['alias'] ?? '')));
        if ($primary === '' || $alias === '' || $primary === $alias) continue;
        // Only the extras fold into the primary, never the other way. Mapping
        // both directions would let two addresses chase one another, and
        // cgCanonEmail() follows exactly one hop.
        $map[$alias] = $primary;
    }
    return $map;
}

/** The address a person is counted under, following one alias hop. */
function cgCanonEmail(string $email): string {
    $e = strtolower(trim($email));
    if ($e === '') return '';
    $map = cgEmailAliases();
    return $map[$e] ?? $e;
}

/**
 * The release dates an application charges, or [] when they cannot be trusted.
 *
 * A teacher can only be released once on a given day, so knowing the actual
 * dates is what lets the ledger count a day once when two applications both
 * claim it. That only works when the dates and the day count agree: an
 * applicant may add dates after approval and be granted fewer, and then nobody
 * knows which of the listed days were the granted ones. Rather than guess, the
 * caller is told to fall back to summing.
 */
function cgAppDates(array $app): array {
    return array_keys(cgAppDateWeights($app));
}

/**
 * The release dates an application charges, each with its part of a day, or
 * [] when they cannot be trusted — the same test as cgAppDates(): the parts of
 * the listed dates have to add up to the days granted.
 */
function cgAppDateWeights(array $app): array {
    $raw = json_decode((string)($app['proposed_dates'] ?? ''), true);
    if (!is_array($raw) || !$raw) return [];
    // Decided date by date: the dates granted, at the part granted.
    if (($app['status'] ?? '') === 'approved' && ($app['days_approved'] ?? null) !== null) {
        $g = cgGrantedParts($app);
        if ($g && abs(cgSumParts($g) - cgEffectiveDays($app)) < 0.001) {
            return array_filter($g, function ($v) { return $v > 0; });
        }
    }
    $parts = cgPartsOf($app);
    $out = [];
    foreach ($raw as $d) {
        $t = strtotime((string)$d);
        if (!$t) return [];   // one unreadable date and none of them are trusted
        $iso = date('Y-m-d', $t);
        if (!isset($out[$iso])) $out[$iso] = $parts[$iso] ?? 1.0;
    }
    ksort($out);
    return abs(cgSumParts($out) - cgEffectiveDays($app)) < 0.001 ? $out : [];
}

/**
 * The dates an application proposes, without asking whether they are the days
 * that were granted.
 *
 * cgAppDates() refuses to answer when the count disagrees with the day total,
 * because it is used to COUNT days and guessing which of five listed dates
 * were the two granted would invent an absence. Asking whether two
 * applications are the same collaboration is a different question: overlapping
 * proposed dates is good evidence either way, and the strict gate was silently
 * suppressing the offer whenever one of the pair had listed more dates than
 * days — which is most of the pairs worth asking about.
 */
function cgAppDatesLoose(array $app): array {
    $raw = json_decode((string)($app['proposed_dates'] ?? ''), true);
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $d) {
        $t = strtotime((string)$d);
        if (!$t) continue;   // skip the unreadable one, keep the rest
        $iso = date('Y-m-d', $t);
        if (!in_array($iso, $out, true)) $out[] = $iso;
    }
    return $out;
}

/**
 * Who a typed collaborator name refers to.
 *
 * Shared by the ledger and the duplicate detector so the two can never
 * disagree about whether Heather-named-on-a-form is Heather-who-applied. A
 * name matching no contact and no applicant still gets a key of its own, the
 * same as the ledger gives it, so those days are kept rather than dropped.
 */
function cgResolveCollaborator(string $name, array $byName, array $appsByName): array {
    $k = cgNameKey($name);
    if ($k !== '') {
        if (isset($byName[$k]) && empty($byName[$k]['ambiguous'])) {
            $e = cgCanonEmail((string)$byName[$k]['email']);
            return ['key' => 'e:' . $e, 'name' => $byName[$k]['name'], 'email' => $e, 'exact' => false];
        }
        if (isset($appsByName[$k]) && empty($appsByName[$k]['ambiguous'])) {
            $e = cgCanonEmail((string)$appsByName[$k]['email']);
            return ['key' => 'e:' . $e, 'name' => $appsByName[$k]['name'], 'email' => $e, 'exact' => false];
        }
    }
    return ['key' => 'n:' . $k, 'name' => trim($name), 'email' => '', 'exact' => false];
}

/** Applicants of $year indexed by comparable name; a shared name resolves to neither. */
function cgApplicantsByName(int $year, bool $fresh = false): array {
    static $cache = [];
    if ($fresh) { $cache = []; if ($year <= 0) return []; }
    if (isset($cache[$year])) return $cache[$year];
    $out = [];
    try {
        $st = getDB()->prepare(
            "SELECT applicant_name, applicant_email FROM collab_grant_applications
             WHERE school_year=? AND status IN ('approved','pending')");
        $st->execute([$year]);
        foreach ($st->fetchAll() as $r) {
            $email = cgCanonEmail((string)$r['applicant_email']);
            $k     = cgNameKey((string)$r['applicant_name']);
            if ($k === '' || $email === '') continue;
            if (isset($out[$k]) && $out[$k]['email'] !== $email) { $out[$k]['ambiguous'] = true; continue; }
            $out[$k] = ['email' => $email, 'name' => trim((string)$r['applicant_name']),
                        'ambiguous' => $out[$k]['ambiguous'] ?? false];
        }
    } catch (Exception $e) { /* none: collaborators fall back to contacts alone */ }
    return $cache[$year] = $out;
}

/**
 * Everyone an application releases, as ledger keys.
 *
 * The lead plus every name in the collaborator field, resolved the same way
 * the ledger resolves them.
 */
function cgAppPeople(array $r, int $year): array {
    $out   = [];
    $email = cgCanonEmail((string)$r['applicant_email']);
    $out[] = $email !== '' ? 'e:' . $email : 'n:' . cgNameKey((string)$r['applicant_name']);

    $cName = trim((string)($r['collaborator_name'] ?? ''));
    if ($cName === '' || empty($r['has_collaborator'])) return $out;

    $typed = cgCanonEmail((string)($r['collaborator_email'] ?? ''));
    $named = cgSplitNames($cName);
    if (count($named) === 1 && $typed !== '' && filter_var($typed, FILTER_VALIDATE_EMAIL)) {
        $out[] = 'e:' . $typed;
        return $out;
    }
    $byName     = cgContactsByName();
    $appsByName = cgApplicantsByName($year);
    foreach ($named as $one) {
        $hit = cgResolveCollaborator($one, $byName, $appsByName);
        // A name that reduces to nothing — "-", "n/a", punctuation alone —
        // would key as a bare 'n:' that every such application shares, and two
        // unrelated grants on one date would be offered as one collaboration.
        if ($hit['key'] === 'n:') continue;
        $out[] = $hit['key'];
    }
    return $out;
}

/**
 * Applications that look like the same collaboration entered more than once.
 *
 * Two applications qualify when they share a teacher and share a date. That is
 * a weaker test than the ledger's — deliberately. The ledger has to know which
 * days were granted before it will count them; this only has to know the
 * question is worth asking, and an admin answers it.
 */
function cgOverlappingApps(int $year): array {
    cgEnsureEditSupport();
    try {
        $st = getDB()->prepare(
            "SELECT * FROM collab_grant_applications
             WHERE school_year=? AND status IN ('approved','pending') AND duplicate_of IS NULL
             ORDER BY submitted_at, id");
        $st->execute([$year]);
        $rows = $st->fetchAll();
    } catch (Exception $e) {
        error_log('cgOverlappingApps: ' . $e->getMessage());
        return [];
    }

    $info = [];
    foreach ($rows as $r) {
        $dates = cgAppDatesLoose($r);
        if (!$dates) continue;   // nothing to overlap on
        $info[] = ['row' => $r, 'dates' => $dates, 'people' => cgAppPeople($r, $year)];
    }

    // Grouped, not paired: three applications for one collaboration should be
    // one question with two answers, not three questions.
    $groups = [];
    $of     = [];
    for ($i = 0; $i < count($info); $i++) {
        for ($j = $i + 1; $j < count($info); $j++) {
            if (!array_intersect($info[$i]['people'], $info[$j]['people'])) continue;
            if (!array_intersect($info[$i]['dates'],  $info[$j]['dates']))  continue;
            $gi = $of[$i] ?? null;
            $gj = $of[$j] ?? null;
            if ($gi === null && $gj === null) {
                $groups[] = [$i, $j];
                $of[$i] = $of[$j] = count($groups) - 1;
            } elseif ($gi === null) {
                $groups[$gj][] = $i; $of[$i] = $gj;
            } elseif ($gj === null) {
                $groups[$gi][] = $j; $of[$j] = $gi;
            } elseif ($gi !== $gj) {
                foreach ($groups[$gj] as $k) { $groups[$gi][] = $k; $of[$k] = $gi; }
                $groups[$gj] = [];
            }
        }
    }

    $out = [];
    foreach ($groups as $g) {
        if (count($g) < 2) continue;
        $g = array_values(array_unique($g));
        $set = array_map(function ($i) use ($info) { return $info[$i]['row']; }, $g);
        /*
         * The one already holding money leads; otherwise the earliest.
         *
         * Folding refuses to move a recorded invoice or release cost, and the
         * page only ever offers to fold the others under the first of the set.
         * Ordered by submission alone, a pair whose later copy had been costed
         * had one button, always refused, advising the admin to "fold the
         * other one instead" — a control that does not exist.
         */
        usort($set, function ($x, $y) {
            $unpaid = function ($a) {
                return (($a['release_cost'] ?? null) !== null
                        || trim((string)($a['invoice_number'] ?? '')) !== '') ? 0 : 1;
            };
            return [$unpaid($x), (string)$x['submitted_at'], (int)$x['id']]
               <=> [$unpaid($y), (string)$y['submitted_at'], (int)$y['id']];
        });
        $out[] = $set;
    }
    return $out;
}

/**
 * Everyone who spent a collaboration day in $year, keyed by person.
 *
 * Keys are 'e:<address>' for anyone we can identify by email and 'n:<name>' for
 * a collaborator whose name matches no contact — kept in the ledger rather than
 * dropped, because those days were still taken.
 */
function cgDaysLedger(int $year, bool $fresh = false): array {
    static $cache = [];
    if ($fresh) {
        $cache = [];
        // Clearing is all that was asked for. Falling through would build a
        // whole ledger for school year 0 and file the empty result under it.
        if ($year <= 0) return [];
    }
    if (isset($cache[$year])) return $cache[$year];

    cgEnsureEditSupport();
    $people = [];

    // $seen is the address as it was written, before the alias map folded it.
    // Without it a merged row lists only the address it is counted under, and
    // the page cannot show the reader what it joined together.
    $add = function (string $key, string $name, string $email, string $status,
                     float $days, int $appId, string $role, bool $exact,
                     string $seen = '', array $dates = []) use (&$people) {
        if (!isset($people[$key])) {
            $people[$key] = ['key' => $key, 'name' => $name, 'email' => $email,
                             'approved' => 0, 'pending' => 0, 'exact' => true,
                             'emails' => [], 'apps' => [],
                             // date => ['status' => …, 'apps' => […]]. A day a
                             // person is released counts once however many
                             // applications claim it: they can only be away
                             // once. The applications that claim it are kept so
                             // a double claim can be named rather than silently
                             // absorbed.
                             'days' => [], 'undated_approved' => 0, 'undated_pending' => 0];
        }
        if ($email !== '' && $people[$key]['email'] === '') $people[$key]['email'] = $email;
        foreach ([$email, strtolower(trim($seen))] as $addr) {
            if ($addr !== '' && !in_array($addr, $people[$key]['emails'], true)) {
                $people[$key]['emails'][] = $addr;
            }
        }
        if ($dates) {
            foreach ($dates as $d => $w) {
                $ap = $status === 'approved';
                if (!isset($people[$key]['days'][$d])) {
                    // a / p: the largest approved and pending share of each
                    // period of the day (see cgDayPeriod()).
                    $people[$key]['days'][$d] = ['status' => $status, 'apps' => [], 'a' => [], 'p' => []];
                }
                $day =& $people[$key]['days'][$d];
                if (!in_array($appId, $day['apps'], true)) $day['apps'][] = $appId;
                /* A teacher is away once per period, however many applications
                 * claim it. The same morning entered twice is one morning; a
                 * morning on one grant and the afternoon on another is the
                 * whole day. Approved and pending are held apart, so a pending
                 * full day on top of an approved afternoon is 0.4 approved and
                 * 0.6 awaiting a decision. */
                $per = cgDayPeriod($w);
                $bin = $ap ? 'a' : 'p';
                $day[$bin][$per] = max($day[$bin][$per] ?? 0.0, $w);
                if ($ap) $day['status'] = 'approved';
                unset($day);
            }
        } elseif ($status === 'approved') {
            $people[$key]['undated_approved'] += $days;
        } else {
            $people[$key]['undated_pending'] += $days;
        }
        if (!$exact) $people[$key]['exact'] = false;
        $people[$key]['apps'][] = ['id' => $appId, 'role' => $role, 'status' => $status,
                                   'days' => $days, 'exact' => $exact, 'dated' => (bool)$dates];
    };

    try {
        // collaborator_email is the address an admin confirmed, or the
        // collaborator themselves corrected through the "this is not me" link.
        // The ledger used to ignore it and re-match by name, so a collaborator
        // whose address was already settled still counted as approximate and
        // still sat apart from their own applications.
        $hasCe = cgHasCollabEmail();
        // Guarded like collaborator_email above. proposed_dates is added by a
        // migration in cgEnsureTable(), and cgDaysLedger() does not call it —
        // on a host that has not run it, naming the column unguarded would
        // throw and every teacher's days would read as zero.
        $hasPd = cgHasCollabColumn('proposed_dates');
        $st = getDB()->prepare(
            "SELECT id, applicant_name, applicant_email, collaborator_name,
                    " . ($hasCe ? "collaborator_email," : "'' AS collaborator_email,") . "
                    " . ($hasPd ? "proposed_dates," : "NULL AS proposed_dates,") . "
                    " . (cgHasCollabColumn('date_parts') ? "date_parts," : "NULL AS date_parts,") . "
                    " . (cgHasCollabColumn('granted_parts') ? "granted_parts," : "NULL AS granted_parts,") . "
                    has_collaborator, status, days_requested, days_approved
             FROM collab_grant_applications
             WHERE school_year=? AND status IN ('approved','pending')");
        $st->execute([$year]);
        $rows = $st->fetchAll();
    } catch (Exception $e) {
        error_log('cgDaysLedger: ' . $e->getMessage());
        return $cache[$year] = [];
    }

    $byName     = null;   // contacts, read lazily: most loads never need it
    $appsByName = [];     // this year's applicants, by comparable name
    $datesOf    = [];     // application id => its release dates, parsed once

    /*
     * The leads first, so the collaborator pass can use them.
     *
     * A teacher who applied this year gave their own address on their own
     * application, which is the best evidence of identity the system holds —
     * better than a contact record, which may be stale or missing. A
     * collaborator typed as a bare name is very often one of them, and until
     * now that was never checked: Vittoria Yano sat in the ledger twice, once
     * as a lead with her address and once as a nameless collaborator row,
     * because the match was only ever attempted against contacts.
     */
    foreach ($rows as $r) {
        $days = cgEffectiveDays($r);
        if ($days <= 0) continue;

        $rawEmail = strtolower(trim((string)$r['applicant_email']));
        $email    = cgCanonEmail($rawEmail);
        $lName    = trim((string)$r['applicant_name']);
        $add($email !== '' ? 'e:' . $email : 'n:' . cgNameKey($lName),
             $lName, $email, $r['status'], $days, (int)$r['id'], 'lead', true, $rawEmail,
             $datesOf[(int)$r['id']] = cgAppDateWeights($r));

        $ak = cgNameKey($lName);
        if ($ak === '' || $email === '') continue;
        if (isset($appsByName[$ak]) && $appsByName[$ak]['email'] !== $email) {
            // Two applicants of one name are two people. Charging a
            // collaborator's days to whichever applied first would be a guess
            // with somebody's cap riding on it.
            $appsByName[$ak]['ambiguous'] = true;
            continue;
        }
        $appsByName[$ak] = ['email' => $email, 'name' => $lName,
                            'ambiguous' => $appsByName[$ak]['ambiguous'] ?? false];
    }

    foreach ($rows as $r) {
        $days = cgEffectiveDays($r);
        if ($days <= 0) continue;

        // 'has_collaborator' is what the review panel reads, so the ledger reads
        // it too: a name left behind after someone switched back to "not yet"
        // must not charge three days to a teacher the panel calls None identified.
        $cName = trim((string)$r['collaborator_name']);
        if ($cName === '' || empty($r['has_collaborator'])) continue;

        // An address typed or confirmed against the application settles who
        // this is, so it is used ahead of the name and counts as exact. It
        // belongs to the field as a whole, so it only settles anything when
        // the field names one person.
        // The collaborator is released on the same days as the lead — that is
        // what the approval email tells them to book, and the lead pass has
        // already parsed them.
        $cDates   = $datesOf[(int)$r['id']] ?? cgAppDateWeights($r);
        $named    = cgSplitNames($cName);
        $rawTyped = $hasCe ? strtolower(trim((string)($r['collaborator_email'] ?? ''))) : '';
        $typed    = cgCanonEmail($rawTyped);
        $settled = (count($named) === 1 && $typed !== ''
                    && filter_var($typed, FILTER_VALIDATE_EMAIL)) ? $typed : '';

        foreach ($named as $one) {
            if ($settled !== '') {
                $add('e:' . $settled, $one, $settled,
                     $r['status'], $days, (int)$r['id'], 'collaborator', true, $rawTyped, $cDates);
                continue;
            }
            // Matched by name, so never exact — the panel shows this as approximate.
            $k = cgNameKey($one);
            if ($byName === null) $byName = cgContactsByName();

            // Contacts first, then this year's applicants, then the name as
            // typed. Shared with the duplicate detector so the two can never
            // disagree about whether a name on a form is someone who applied.
            $hit = cgResolveCollaborator($one, $byName, $appsByName);
            $add($hit['key'], $hit['name'] !== '' ? $hit['name'] : $one, $hit['email'],
                 $r['status'], $days, (int)$r['id'], 'collaborator', false, '', $cDates);
        }
    }

    foreach ($people as $k => $p) {
        /*
         * A day claimed by two applications is still one day off work. Summing
         * the applications charged Vittoria Yano six days for three — she led
         * one grant and was named on a second for the same dates, which is one
         * collaboration entered twice, not two absences.
         *
         * Days that could not be tied to dates are added on as before. Nothing
         * is deduplicated there because nothing is known there, and quietly
         * dropping them would understate a real total.
         */
        $appr = $pend = 0.0;
        foreach ($p['days'] as $d => $info) {
            $both = $info['a'];
            foreach ($info['p'] as $per => $v) $both[$per] = max($both[$per] ?? 0.0, $v);
            $wa = min(1.0, cgSumParts($info['a']));
            $wb = min(1.0, cgSumParts($both));
            $appr = round($appr + $wa, 2);
            $pend = round($pend + max(0.0, $wb - $wa), 2);
            // What the date costs in all, for the page's date chips.
            $p['days'][$d]['w'] = $wb;
        }
        $p['approved'] = round($appr + (float)$p['undated_approved'], 2);
        $p['pending']  = round($pend + (float)$p['undated_pending'], 2);

        // Days two applications both claim, and who claimed them. Named on the
        // page: the person has not used those days twice, and a total that
        // quietly differs from the applications behind it reads as a bug.
        $shared = [];
        $withApps = [];
        foreach ($p['days'] as $d => $info) {
            if (count($info['apps']) < 2) continue;
            $shared[] = $d;
            foreach ($info['apps'] as $id) {
                if (!in_array($id, $withApps, true)) $withApps[] = $id;
            }
        }
        sort($shared);
        // Applications in their own order, not the order this person happened
        // to be reached in — a lead is added before any collaborator, so the
        // list came out reversed for whoever led the later application.
        sort($withApps);
        $p['shared_days'] = $shared;
        $p['shared_apps'] = $withApps;
        // Some of this total was summed rather than counted by date, so the
        // deduplication above could not reach it.
        $p['undated'] = ((float)$p['undated_approved'] + (float)$p['undated_pending']) > 0;

        $people[$k] = cgApplyCap($p, $year);
    }

    uasort($people, function ($a, $b) {
        return [$b['used'], strtolower($a['name'])] <=> [$a['used'], strtolower($b['name'])];
    });

    return $cache[$year] = $people;
}

/**
 * Release days the year has committed, counted per teacher.
 *
 * Not the same as adding up the applications, which is what the summary card
 * used to do and why its figure never matched the table under it. An
 * application releases the lead and everyone named on it, so a three-day grant
 * with two collaborators is nine days of coverage the local pays for, not
 * three. And where one collaboration was entered twice, summing applications
 * counted the same three days again.
 *
 * Exempt teachers are counted separately, not in the headline.
 *
 * Someone is exempt precisely because releasing them costs the local nothing —
 * a district support teacher is covered from elsewhere. This figure sits beside
 * the release cost and answers "how much release did the local fund", so days
 * that cost it nothing do not belong in it. They are still counted, in their own
 * fields, because "we supported six days of collaboration this year" is a real
 * and useful fact and quietly dropping it would be a different kind of wrong.
 */
function cgYearPersonDays(int $year): array {
    $out = ['approved' => 0, 'pending' => 0, 'people' => 0,
            'exempt_approved' => 0, 'exempt_pending' => 0, 'exempt_people' => 0];
    foreach (cgDaysLedger($year) as $p) {
        /* cgApplyCap() sets capped on every ledger row, so the key is always
         * there. Checked for anyway, and treated as capped when it is not:
         * reading a missing key as exempt would put the whole year in the
         * exempt bucket and show a headline of zero, which is the worst way
         * for this to be wrong. */
        $free = array_key_exists('capped', $p) && empty($p['capped']);
        $out[$free ? 'exempt_approved' : 'approved'] += (float)$p['approved'];
        $out[$free ? 'exempt_pending'  : 'pending']  += (float)$p['pending'];
        $out[$free ? 'exempt_people'   : 'people']++;
    }
    foreach (['approved', 'pending', 'exempt_approved', 'exempt_pending'] as $k) {
        $out[$k] = round($out[$k], 2);
    }
    return $out;
}

/** One person's standing for the year, whether or not they appear in the ledger. */
function cgPersonDays(int $year, string $email, string $name = ''): array {
    $ledger = cgDaysLedger($year);
    // Through the alias map, or asking about the address someone applied from
    // would miss the row their days were counted under.
    $email  = cgCanonEmail($email);
    if ($email !== '' && isset($ledger['e:' . $email])) return $ledger['e:' . $email];

    $nk = $name === '' ? '' : cgNameKey($name);
    $matched = '';   // an address we know belongs to them, even with no days
    if ($nk !== '') {
        $byName = cgContactsByName();
        if (isset($byName[$nk]) && !$byName[$nk]['ambiguous']) {
            $matched = cgCanonEmail((string)$byName[$nk]['email']);
            $ck = 'e:' . $matched;
            if (isset($ledger[$ck])) return $ledger[$ck];
        }
        if (isset($ledger['n:' . $nk])) return $ledger['n:' . $nk];
    }
    /* Nothing in the ledger, which means no days this year — not that we do
     * not know who they are. Keyed on whatever did identify them, because the
     * panel reads an 'n:' key as "this name matched nobody" and said exactly
     * that about a teacher who is in Contacts and simply has not used the
     * fund yet. */
    $key = $email !== '' ? 'e:' . $email : ($matched !== '' ? 'e:' . $matched : 'n:' . $nk);
    return cgApplyCap(['key' => $key,
            'name' => $name, 'email' => $email ?: $matched, 'approved' => 0, 'pending' => 0,
            'exact' => true, 'emails' => array_values(array_filter([$email ?: $matched])),
            'apps' => [],
            'days' => [], 'undated_approved' => 0, 'undated_pending' => 0,
            'shared_days' => [], 'shared_apps' => [], 'undated' => false], $year);
}

/**
 * Forget what was worked out from the contact list this request.
 *
 * Three caches are built from contacts and never expected to change mid-page:
 * the alias map, the name index, and the day ledger that rests on both. Adding
 * an address to a contact changes all three underneath whatever has already
 * asked, so a page that does that and then redraws its figures has to say so.
 */
function cgCachesReset(): void {
    cgEmailAliases(true);
    cgContactsByName(true);
    // Also folds addresses through the alias map, so it is as stale as the
    // rest. Nothing warms it before the one handler that resets today, which
    // is exactly why it would be missed later.
    cgApplicantsByName(0, true);
    cgContactPrimaries(true);
    cgContactNameList(true);
    cgDaysLedger(0, true);
}

/**
 * Active contacts' names, for an admin-side autocomplete.
 *
 * Only ever rendered behind the login: the public application form must not
 * get one of these, because an autocomplete over this list hands the staff
 * directory to anybody who opens the page.
 *
 * Archived contacts are left out — offering somebody who has left as the
 * completion for a name being corrected is how the wrong person gets picked.
 */
function cgContactNameList(bool $fresh = false): array {
    static $out = null;
    if ($fresh) $out = null;
    if ($out !== null) return $out;
    $names = [];
    try {
        /* Its own query, not the name index. That index keeps one entry per
         * name, so for two people called Pat Smith the archived flag is
         * whichever row happened to be read last — and the active Pat Smith
         * would be dropped from the list, or the retired one offered, which
         * is the opposite of what this is for. */
        $rows = getDB()->query(
            "SELECT first_name, last_name, preferred_name, status FROM contacts")->fetchAll();
    } catch (Exception $e) {
        return $out = [];
    }
    foreach ($rows as $r) {
        if ((string)($r['status'] ?? 'active') === 'archived') continue;
        foreach ([trim($r['first_name'] . ' ' . $r['last_name']),
                  trim(($r['preferred_name'] ?? '') . ' ' . $r['last_name'])] as $n) {
            $n = trim(preg_replace('/\s+/', ' ', $n) ?? '');
            if ($n !== '' && strpos($n, ' ') !== false) $names[$n] = true;
        }
    }
    $out = array_keys($names);
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

/**
 * Every contact's main address, folded, as a set.
 *
 * Read once so that asking "is this address on somebody's record?" costs
 * nothing per card. An address that is one of somebody's extras folds to their
 * main one through cgCanonEmail(), so it is in here too — which is the whole
 * question.
 *
 * Its own query rather than the name index already in memory: that index is
 * keyed by name, so it holds nothing for a contact imported with an address
 * and no name, and only the first of two people who share one. Either gap
 * showed "not on anybody's contact record" about an address plainly on
 * somebody's record — and for the shared name, with no fix offered, because
 * the link target refuses to guess between them.
 */
function cgContactPrimaries(bool $fresh = false): array {
    static $set = null;
    if ($fresh) $set = null;
    if ($set !== null) return $set;
    $set = [];
    try {
        foreach (getDB()->query("SELECT email FROM contacts")->fetchAll() as $r) {
            $e = cgCanonEmail((string)($r['email'] ?? ''));
            if ($e !== '') $set[$e] = true;
        }
    } catch (Exception $e) {
        // No contacts table: nothing is on file, and the link target's own
        // check still has the last word before anything is written.
    }
    return $set;
}

/**
 * The contact this application's collaborator should be linked to, or null.
 *
 * Both the page that offers the link and the handler that performs it work it
 * out from the application, so a contact id arriving in a POST is checked
 * against the answer rather than trusted. Attaching an address to the wrong
 * record merges two teachers' day totals, and this page has no un-link.
 *
 * Returns ['id','name','email'] only when the application settles on an
 * address, that address is on nobody's record, and the collaborator's name
 * matches exactly one contact.
 */
function cgCollaboratorLinkTarget(array $app): ?array {
    $addr = cgCollaboratorKeyEmail($app);
    if ($addr === '') return null;
    if (!function_exists('contactEmailOwner')) return null;
    $k = cgNameKey((string)($app['collaborator_name'] ?? ''));
    if ($k === '') return null;

    $byName = cgContactsByName();
    if (!isset($byName[$k]) || !empty($byName[$k]['ambiguous'])) return null;
    /* Archived as well as ambiguous. cgCollaboratorAddress() already refuses
     * to write to a contact who has left, and this is a heavier step than an
     * email: welding a working teacher's address onto a retired namesake's
     * record merges their day totals, and this page has no un-link. */
    if (!empty($byName[$k]['archived'])) return null;

    // Already somebody's, including already theirs: nothing to offer.
    if (contactEmailOwner($addr)) return null;

    $owner = contactEmailOwner((string)$byName[$k]['email']);
    if (!$owner) return null;
    return ['id' => $owner['id'], 'name' => $owner['name'], 'email' => $addr];
}

/**
 * The address this application settles its collaborator on, or ''.
 *
 * The same test cgDaysLedger() applies: an address typed or confirmed against
 * the application, when the field names one person. Shared so that asking
 * "how is this collaborator doing for days?" cannot ask under a different key
 * from the one their days were counted under — which is what made a
 * collaborator who had been emailed, and charged a day, read as 0 of 3 with
 * no match.
 */
function cgCollaboratorKeyEmail(array $app): string {
    if (trim((string)($app['collaborator_name'] ?? '')) === '') return '';
    if (empty($app['has_collaborator'])) return '';
    if (count(cgSplitNames((string)$app['collaborator_name'])) !== 1) return '';
    $typed = cgCanonEmail((string)($app['collaborator_email'] ?? ''));
    return filter_var($typed, FILTER_VALIDATE_EMAIL) ? $typed : '';
}

/**
 * Ledger rows that look like one person under several addresses.
 *
 * Never merged on this evidence — two teachers really do share a name, and
 * silently adding their days together would be worse than the split rows it
 * fixed. Shown instead, with the contact record to edit: listing the other
 * addresses on the contact is what actually merges them, and it does so
 * everywhere rather than only here. Any number of them, which is the point:
 * a teacher turned up having applied from three.
 */
function cgLedgerDuplicates(int $year): array {
    $byName = [];
    foreach (cgDaysLedger($year) as $p) {
        $k = cgNameKey((string)$p['name']);
        if ($k === '') continue;
        $byName[$k][] = $p;
    }
    $out = [];
    foreach ($byName as $k => $rows) {
        if (count($rows) < 2) continue;
        $addrs = [];
        foreach ($rows as $r) {
            foreach ($r['emails'] as $e) {
                if (!in_array($e, $addrs, true)) $addrs[] = $e;
            }
        }
        // Two rows and no address between them is one unmatched name counted
        // twice, which the name key already rules out. Needs real addresses to
        // be worth asking about.
        if (count($addrs) < 2) continue;
        $out[] = ['name' => $rows[0]['name'], 'addresses' => $addrs,
                  'used' => array_sum(array_column($rows, 'used'))];
    }
    usort($out, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    return $out;
}

/**
 * The day count for an email, naming both figures when they disagree.
 *
 * An applicant can raise the number of dates after approval, so "3 days" and
 * "1 day granted" can both be true at once; printing one alone misleads whoever
 * is reading.
 */
function cgDaysLine(array $app): string {
    $asked   = (float)$app['days_requested'];
    $granted = $app['days_approved'] ?? null;
    if ($granted === null || abs((float)$granted - $asked) < 0.001) return cgDaysWord($asked);
    return cgDaysWord($asked) . ' now listed, ' . cgFmtDays($granted) . ' granted';
}


// ── People the three-day cap doesn't apply to ────────────────────────────────
//
// The cap rations TTOC coverage the local pays for. Releasing a district support
// teacher costs the local nothing, so there is nothing to ration and the cap is
// meaningless for them — but their days are still worth counting, because
// "supporting six days of collaboration this year" is a real and useful fact.
// So an exempt person keeps their total and loses the limit, rather than
// disappearing from the ledger.
//
// A list the president manages, not a name in the source: the next district
// support teacher should cost a click, not a deploy.

function cgExemptEnsure(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        getDB()->exec("CREATE TABLE IF NOT EXISTS collab_grant_exempt (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            person_key VARCHAR(255) NOT NULL,
            email      VARCHAR(255) NOT NULL DEFAULT '',
            name_key   VARCHAR(255) NOT NULL DEFAULT '',
            label      VARCHAR(255) NOT NULL DEFAULT '',
            reason     VARCHAR(255) NOT NULL DEFAULT '',
            from_year  INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by VARCHAR(255) NOT NULL DEFAULT '',
            UNIQUE KEY uniq_person (person_key),
            INDEX idx_email (email),
            INDEX idx_name (name_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

function cgExemptAll(bool $fresh = false): array {
    static $rows = null;
    if ($fresh) $rows = null;
    if ($rows !== null) return $rows;
    cgExemptEnsure();
    try {
        $rows = getDB()->query("SELECT * FROM collab_grant_exempt ORDER BY label")->fetchAll();
    } catch (Exception $e) {
        $rows = [];
    }
    return $rows;
}

/**
 * The exemption covering a person, or null.
 *
 * Matched on the address and the comparable name as well as the stored key,
 * because a collaborator keyed by name today becomes keyed by email the moment
 * someone adds them to contacts — and an exemption that quietly stopped applying
 * at that point would be worse than none.
 */
function cgExemptFor(array $person, int $year): ?array {
    $key   = (string)($person['key'] ?? '');
    // Through the alias map on both sides. An exemption recorded against the
    // address someone used last year must still find them after their two
    // addresses are folded into one — otherwise a district support teacher
    // silently becomes capped and shows over the limit in red.
    $email = cgCanonEmail((string)($person['email'] ?? ''));
    $nk    = cgNameKey((string)($person['name'] ?? ''));
    foreach (cgExemptAll() as $r) {
        // Carried forward from the year it was recorded, never backwards.
        if ((int)$r['from_year'] > $year) continue;
        $rEmail = cgCanonEmail((string)$r['email']);
        if ($r['person_key'] === $key) return $r;
        if ($email !== '' && $rEmail === $email) return $r;
        // Two different addresses are two different people however alike the
        // names read, so a shared name alone does not uncap anybody.
        if ($nk !== '' && $r['name_key'] === $nk
            && ($email === '' || $rEmail === '' || $rEmail === $email)) return $r;
    }
    return null;
}

/**
 * Is this person off the collaboration grant's mailing entirely?
 *
 * Somebody the cap does not apply to is a district support teacher or the
 * like: their release is covered elsewhere, so every word the grant writes to
 * them is wrong. The collaborator notice is the plainest case — it tells them
 * a day is coming off their three and to book an absence — but Cody asked for
 * all of it (2026-11-03), approval included, and said he would tell those
 * teachers himself.
 *
 * The two notifications addressed to lp54@bctf.ca are not covered: they are
 * the union writing to itself about an application, not the grant writing to
 * a participant, and an exec member who happened to be marked not capped
 * would otherwise stop hearing about applications altogether.
 *
 * Matched exactly as the day ledger matches, cgExemptFor's shared-name rule
 * included, so "not capped on the ledger" and "not written to" can never come
 * apart. That rule can match a namesake when neither side has an address on
 * record — and silence is a worse way to be wrong than a mis-stated cap,
 * because nobody reads an email that did not arrive. So every caller says on
 * screen that nothing was sent and who has to be told by hand: approval, the
 * edit notice, the edit link, and the public page a suppressed applicant sees
 * after submitting. Narrowing the match here instead would have broken the
 * case Cody actually has — a collaborator exempted by name, with no address
 * of their own on the exemption, whose address the send then finds in
 * Contacts.
 */
function cgEmailSuppressed(?string $email, string $name = '', int $year = 0): bool {
    $email = cgCanonEmail((string)$email);
    $nk    = cgNameKey($name);
    if ($email === '' && $nk === '') return false;
    if (!$year) $year = cgCurrentYear();
    return cgExemptFor([
        'key'   => $email !== '' ? 'e:' . $email : 'n:' . $nk,
        'email' => $email,
        'name'  => $name,
    ], $year) !== null;
}

function cgExemptAdd(array $person, string $reason, string $actor, int $fromYear = 0): bool {
    cgExemptEnsure();
    if (!$fromYear) $fromYear = cgCurrentYear();
    try {
        getDB()->prepare(
            "INSERT INTO collab_grant_exempt
                 (person_key, email, name_key, label, reason, from_year, created_by)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE reason=VALUES(reason), from_year=VALUES(from_year),
                                     created_by=VALUES(created_by)"
        )->execute([
            (string)($person['key'] ?? ''),
            strtolower(trim((string)($person['email'] ?? ''))),
            cgNameKey((string)($person['name'] ?? '')),
            (string)($person['name'] ?? ''),
            trim($reason),
            $fromYear,
            $actor,
        ]);
        cgExemptAll(true);
        return true;
    } catch (Exception $e) {
        error_log('cgExemptAdd: ' . $e->getMessage());
        return false;
    }
}

function cgExemptRemove(array $person): bool {
    cgExemptEnsure();
    $key   = (string)($person['key'] ?? '');
    $email = strtolower(trim((string)($person['email'] ?? '')));
    $nk    = cgNameKey((string)($person['name'] ?? ''));
    try {
        // Matched the same three ways cgExemptFor matches, or a row found by
        // address would survive a button that reported success.
        $st = getDB()->prepare(
            "DELETE FROM collab_grant_exempt
             WHERE person_key=?
                OR (email    <> '' AND email    = ?)
                OR (name_key <> '' AND name_key = ?)");
        $st->execute([$key, $email, $nk]);
        cgExemptAll(true);
        return $st->rowCount() > 0;
    } catch (Exception $e) {
        error_log('cgExemptRemove: ' . $e->getMessage());
        return false;
    }
}

/** Fill in a person's cap standing, exempt or not. Shared so the ledger and the
 *  not-in-the-ledger default can never disagree about it. */
function cgApplyCap(array $p, int $year): array {
    $used  = round((float)$p['approved'] + (float)$p['pending'], 2);
    $ex    = cgExemptFor($p, $year);
    $p['used']   = $used;
    $p['capped'] = ($ex === null);
    $p['exempt_reason'] = $ex === null ? '' : (string)$ex['reason'];
    $p['left'] = $ex === null ? max(0.0, round(CG_DAY_CAP - $used, 2)) : 0;
    $p['over'] = $ex === null ? $used > CG_DAY_CAP + 0.001 : false;
    return $p;
}

/** The school year a date falls in — Sep 1 starts a new one, as cgCurrentYear(). */
function cgYearOfDate(string $iso): int {
    $t = strtotime($iso);
    $y = (int)date('Y', $t);
    return (int)date('n', $t) >= 9 ? $y : $y - 1;
}

/**
 * Record a grant that never went through the form — one arranged by phone or
 * at a meeting, usually after the fact.
 *
 * It goes in already approved and filed under the school year its dates fall
 * in, not the year it is typed, so a grant from last spring counts against
 * last year's three days. Nobody is emailed: the approval and the
 * collaborator's notice are for grants still to happen, and this one has been
 * arranged already. The collaborator's notice is marked waived rather than
 * sent, so the record never claims an email went out.
 *
 * $d: applicant_name, applicant_email (optional when the name is one contact),
 * collaborator_name, collaborator_email, dates (Y-m-d list), parts (one
 * CG_DAY_PARTS value per date box, in the same order), notes.
 * Returns ['id' => int, 'year' => int] or ['error' => string].
 */
function cgAddManualGrant(array $d, string $actor): array {
    cgEnsureTable();
    cgEnsureEditSupport();

    // A contact's address wins over one typed beside it only when nothing was
    // typed: a typed address is someone saying which teacher they mean.
    $who = function (string $name, string $email): array {
        $name  = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $email = strtolower(trim($email));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => '"' . $email . '" is not an email address.'];
        }
        if ($email === '' && $name !== '') {
            $hit = cgContactsByName()[cgNameKey($name)] ?? null;
            if ($hit && empty($hit['ambiguous'])) $email = strtolower((string)$hit['email']);
        }
        return ['name' => $name, 'email' => $email];
    };

    $app = $who((string)($d['applicant_name'] ?? ''), (string)($d['applicant_email'] ?? ''));
    if (isset($app['error'])) return $app;
    if ($app['name'] === '') return ['error' => 'Who was the grant for?'];
    if ($app['email'] === '') {
        return ['error' => 'No single contact is called ' . $app['name'] . ' — add their email.'];
    }

    $col = $who((string)($d['collaborator_name'] ?? ''), (string)($d['collaborator_email'] ?? ''));
    if (isset($col['error'])) return $col;
    if ($col['name'] === '' && $col['email'] !== '') {
        return ['error' => 'Give the collaborator a name as well as an email.'];
    }

    $dates = [];
    foreach ((array)($d['dates'] ?? []) as $raw) {
        $raw = trim((string)$raw);
        if ($raw === '') continue;
        $t = strtotime($raw);
        if (!$t || date('Y-m-d', $t) !== $raw) return ['error' => '"' . $raw . '" is not a date.'];
        if ((int)date('N', $t) > 5) {
            return ['error' => date('D M j', $t) . ' is a ' . date('l', $t) . ' — release days fall on weekdays.'];
        }
        if (!in_array($raw, $dates, true)) $dates[] = $raw;
    }
    sort($dates);
    if (!$dates) return ['error' => 'Add the day or days they were released.'];
    if (count($dates) > CG_DAY_CAP) {
        return ['error' => 'One grant carries at most ' . CG_DAY_CAP . ' days. Add the rest as another grant.'];
    }
    $year = cgYearOfDate($dates[0]);
    if (cgYearOfDate(end($dates)) !== $year) {
        return ['error' => 'Those dates span two school years. Add each year as its own grant.'];
    }

    $note = trim('Added by hand by ' . $actor . ' on ' . date('M j, Y') . '.'
          . "\n" . trim((string)($d['notes'] ?? '')));
    // Each date with the part chosen beside it; dates and parts arrive as two
    // lists in box order, so they are paired before the dates were sorted.
    $partOf = [];
    $rawDates = array_values((array)($d['dates'] ?? []));
    foreach ($rawDates as $i => $rd) {
        $rd = trim((string)$rd);
        if ($rd !== '' && !isset($partOf[$rd])) $partOf[$rd] = (string)(($d['parts'] ?? [])[$i] ?? '1');
    }
    $pr = cgCleanParts($dates, $partOf, true);
    if (isset($pr['error'])) return $pr;
    $parts  = $pr['parts'];
    $dayMap = [];
    foreach ($dates as $dd) $dayMap[$dd] = $parts[$dd] ?? 1.0;
    $days = cgSumParts($dayMap);
    $hasCol = $col['name'] !== '';

    $cols = ['applicant_name', 'applicant_email', 'school', 'position',
             'has_collaborator', 'collaborator_name', 'needs_partner',
             'proposed_dates', 'days_requested', 'days_approved', 'status',
             'admin_notes', 'school_year', 'reviewed_at', 'reviewed_by'];
    $vals = [$app['name'], $app['email'], '', '',
             $hasCol ? 1 : 0, $hasCol ? $col['name'] : null, 0,
             json_encode($dates), $days, $days, 'approved',
             $note, $year, date('Y-m-d H:i:s'), $actor];
    if ($hasCol && cgHasCollabEmail()) {
        $cols[] = 'collaborator_email'; $vals[] = $col['email'];
    }
    if ($parts && cgHasCollabColumn('date_parts')) {
        $cols[] = 'date_parts'; $vals[] = json_encode($parts);
    }
    if ($hasCol && cgHasCollabColumn('collab_waived_at') && cgHasCollabColumn('collab_waived_by')) {
        $cols[] = 'collab_waived_at'; $vals[] = date('Y-m-d H:i:s');
        $cols[] = 'collab_waived_by'; $vals[] = $actor;
    }

    try {
        $db = getDB();
        $db->prepare("INSERT INTO collab_grant_applications (" . implode(', ', $cols) . ")
                      VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")")
           ->execute($vals);
        $id = (int)$db->lastInsertId();
    } catch (\Throwable $e) {
        error_log('cgAddManualGrant: ' . $e->getMessage());
        return ['error' => 'Could not save it. The problem has been logged.'];
    }
    cgCachesReset();
    return ['id' => $id, 'year' => $year];
}
