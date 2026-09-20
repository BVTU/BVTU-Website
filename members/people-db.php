<?php
/**
 * people-db.php — one view of a person: contact record + login account + invite.
 *
 * The three stores are read separately and joined in PHP on the normalised
 * email address. Not in SQL: `members`, `member_invitations` and `contacts`
 * can carry different collations, and comparing those columns in a query
 * raises "Illegal mix of collations". At a few hundred people the set work is
 * far cheaper than the failure mode.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/contacts-db.php';
require_once __DIR__ . '/invite-db.php';
require_once __DIR__ . '/exec-db.php';   // EXEC_ROLES labels for the timeline

/** All login accounts, keyed by normalised email. */
function peopleAccountsByEmail(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        $rows = getDB()->query(
            "SELECT id, name, email, active, must_change_password, created_at FROM members"
        )->fetchAll();
        foreach ($rows as $r) $cache[contactNormalizeEmail($r['email'])] = $r;
    } catch (Exception $e) {
        $cache = [];
    }
    return $cache;
}

/** All invitations, keyed by normalised email, newest row winning. */
function peopleInvitesByEmail(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        inviteEnsureTable();
        $rows = getDB()->query(
            "SELECT id, email, name, sent_at, accepted_at, expires_at, created_at
             FROM member_invitations ORDER BY id"
        )->fetchAll();
        foreach ($rows as $r) $cache[contactNormalizeEmail($r['email'])] = $r;
    } catch (Exception $e) {
        $cache = [];
    }
    return $cache;
}

/**
 * Where someone stands on getting into the portal. Returns one of:
 *   registered  — has a login account
 *   accepted    — invite accepted but no account found (shouldn't happen; shown if it does)
 *   sent        — link emailed, not yet used
 *   expired     — link emailed, window closed, never used
 *   listed      — on the invite list, never emailed
 *   none        — not invited at all
 */
function peopleInviteState(?array $account, ?array $invite): string {
    if ($account) return 'registered';
    if (!$invite)  return 'none';
    if (!empty($invite['accepted_at'])) return 'accepted';
    if (empty($invite['sent_at']))      return 'listed';
    $exp = $invite['expires_at'] ?? null;
    if ($exp && strtotime($exp) < time()) return 'expired';
    return 'sent';
}

const PEOPLE_INVITE_LABELS = [
    'registered' => 'Registered',
    'accepted'   => 'Accepted',
    'sent'       => 'Link sent',
    'expired'    => 'Link expired',
    'listed'     => 'Not yet emailed',
    'none'       => 'Not invited',
];

/** Attach account + invite to each contact row, in place. */
function peopleDecorate(array $rows): array {
    $accounts = peopleAccountsByEmail();
    $invites  = peopleInvitesByEmail();
    foreach ($rows as &$r) {
        $k = $r['email_normalized'] ?? contactNormalizeEmail($r['email'] ?? '');
        $r['_account'] = $accounts[$k] ?? null;
        $r['_invite']  = $invites[$k]  ?? null;
        $r['_state']   = peopleInviteState($r['_account'], $r['_invite']);
    }
    unset($r);
    return $rows;
}

/**
 * The addresses matching an account/invite filter, for contactSearch's
 * email_whitelist. Returns null when no such filter is active, so the caller
 * can tell "no filter" from "filter matched nobody".
 */
function peopleFilterEmails(string $account, string $state): ?array {
    if ($account === '' && $state === '') return null;

    $accounts = peopleAccountsByEmail();
    $invites  = peopleInvitesByEmail();

    // Every address either store knows about, plus every contact.
    $all = array_keys($accounts);
    foreach (array_keys($invites) as $e) $all[] = $e;
    try {
        foreach (getDB()->query("SELECT email_normalized FROM contacts")->fetchAll(PDO::FETCH_COLUMN) as $e) {
            $all[] = $e;
        }
    } catch (Exception $e) {}
    $all = array_values(array_unique($all));

    $out = [];
    foreach ($all as $e) {
        $acct = $accounts[$e] ?? null;
        $inv  = $invites[$e]  ?? null;
        if ($account === 'yes' && !$acct) continue;
        if ($account === 'no'  &&  $acct) continue;
        if ($state !== '' && peopleInviteState($acct, $inv) !== $state) continue;
        $out[] = $e;
    }
    return $out;
}

/** Counts for the filter chips, so the page can say what each filter holds. */
function peopleCounts(): array {
    $accounts = peopleAccountsByEmail();
    $invites  = peopleInvitesByEmail();
    $emails   = [];
    try {
        $emails = getDB()->query("SELECT email_normalized FROM contacts WHERE status<>'archived'")
                         ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    $out = ['people' => count($emails), 'with_account' => 0];
    foreach (PEOPLE_INVITE_LABELS as $k => $_) $out[$k] = 0;

    foreach ($emails as $e) {
        $e = contactNormalizeEmail($e);
        $acct = $accounts[$e] ?? null;
        if ($acct) $out['with_account']++;
        $out[peopleInviteState($acct, $invites[$e] ?? null)]++;
    }
    return $out;
}

/* ---------------------------------------------------------------------------
 * The person record: what the union knows about one person.
 *
 * Every lookup matches on the address with plain equality against an indexed
 * column, passing both the stored and lower-cased forms. No LOWER() or other
 * function on the column: wrapping one and comparing it to a bound parameter
 * is what raised "Illegal mix of collations" on the email log, and it would
 * also throw the index away.
 * ------------------------------------------------------------------------ */

/** The address forms to match on, for an IN (?,?) lookup. */
function personEmailForms(string $email): array {
    $forms = array_values(array_unique([trim($email), contactNormalizeEmail($email)]));
    return array_filter($forms, 'strlen');
}

function _personIn(array $forms): string {
    return '(' . implode(',', array_fill(0, count($forms), '?')) . ')';
}

/** Executive roles held. exec_roles is the source of truth for these. */
function personRoles(string $email): array {
    $forms = personEmailForms($email);
    if (!$forms) return [];
    try {
        $s = getDB()->prepare(
            "SELECT role, assigned_by, created_at FROM exec_roles
             WHERE user_email IN " . _personIn($forms) . " ORDER BY created_at"
        );
        $s->execute($forms);
        return $s->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Expense claims, whether the person is the claimant or submitted on someone
 * else's behalf. Both are worth seeing on their record.
 */
function personClaims(string $email, int $limit = 10): array {
    $forms = personEmailForms($email);
    if (!$forms) return [];
    $in = _personIn($forms);
    try {
        $s = getDB()->prepare(
            "SELECT b.id, b.ref_code, b.title, b.status, b.created_at, b.user_email,
                    COALESCE((SELECT SUM(i.amount) FROM exp_batch_items i WHERE i.batch_id=b.id),0) AS total
             FROM exp_batches b
             WHERE b.user_email IN {$in} OR b.submitted_by_email IN {$in}
             ORDER BY b.created_at DESC LIMIT {$limit}"
        );
        $s->execute(array_merge($forms, $forms));
        return $s->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/** President's expense vouchers submitted by this person. */
function personVouchers(string $email, int $limit = 10): array {
    $forms = personEmailForms($email);
    if (!$forms) return [];
    try {
        $s = getDB()->prepare(
            "SELECT id, voucher_number, name, status, created_at, submitted_at
             FROM lp_vouchers WHERE submitted_by_email IN " . _personIn($forms) . "
             ORDER BY created_at DESC LIMIT {$limit}"
        );
        $s->execute($forms);
        return $s->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/** Every invitation ever issued to this address, oldest first. */
function personInvites(string $email): array {
    $forms = personEmailForms($email);
    if (!$forms) return [];
    try {
        inviteEnsureTable();
        $s = getDB()->prepare(
            "SELECT id, sent_at, accepted_at, expires_at, created_at, created_by
             FROM member_invitations WHERE email IN " . _personIn($forms) . " ORDER BY id"
        );
        $s->execute($forms);
        return $s->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * One timeline from every store that records something about this person, so
 * the record answers "what has happened to them" rather than only "what did
 * someone type into this form". Newest first.
 *
 * Each entry: ['at' => timestamp, 'what' => string, 'who' => string, 'kind' => string]
 */
function personTimeline(int $contactId, string $email, int $limit = 40): array {
    $out = [];
    $add = function ($at, $what, $who = '', $kind = 'note') use (&$out) {
        if (!$at) return;
        $ts = strtotime($at);
        if (!$ts) return;
        $out[] = ['at' => $ts, 'what' => $what, 'who' => $who, 'kind' => $kind];
    };

    try {
        $s = getDB()->prepare(
            "SELECT action, actor, changed, detail, created_at FROM contact_audit
             WHERE contact_id=? ORDER BY created_at DESC LIMIT 40"
        );
        $s->execute([$contactId]);
        foreach ($s->fetchAll() as $h) {
            $what = ucfirst(str_replace('_', ' ', $h['action']));
            if ($h['changed']) $what .= ' (' . $h['changed'] . ')';
            if ($h['detail'])  $what .= ' — ' . $h['detail'];
            $add($h['created_at'], $what, $h['actor'], 'contact');
        }
    } catch (Exception $e) {}

    foreach (personInvites($email) as $i) {
        $add($i['created_at'],  'Added to the invitation list', $i['created_by'], 'invite');
        $add($i['sent_at'],     'Registration link emailed',    $i['created_by'], 'invite');
        $add($i['accepted_at'], 'Registered an account',        '',               'invite');
    }

    foreach (personClaims($email, 20) as $b) {
        $add($b['created_at'], 'Expense claim ' . ($b['ref_code'] ?: '#' . $b['id'])
             . ' (' . str_replace('_', ' ', $b['status']) . ')', '', 'claim');
    }

    foreach (personVouchers($email, 20) as $v) {
        $add($v['created_at'], 'Expense voucher ' . ($v['voucher_number'] ?: '#' . $v['id'])
             . ' (' . str_replace('_', ' ', $v['status']) . ')', '', 'claim');
    }

    foreach (personRoles($email) as $r) {
        $add($r['created_at'], 'Assigned ' . (EXEC_ROLES[$r['role']] ?? $r['role']),
             $r['assigned_by'], 'role');
    }

    usort($out, function ($a, $b) { return $b['at'] <=> $a['at']; });
    return array_slice($out, 0, $limit);
}

/* ---------------------------------------------------------------------------
 * Saved views — a named set of filters.
 *
 * Stored as the query string rather than parsed columns, so adding a filter to
 * the page never needs a migration. Nothing is trusted on the way back in: the
 * saved string is re-parsed and whitelisted by peopleViewParams() before it
 * reaches a query.
 * ------------------------------------------------------------------------ */

/** The only parameters a saved view may carry. */
const PEOPLE_VIEW_KEYS = ['q','school_id','role','status','mc','account','state',
                          'include_archived','sort','dir'];

function peopleViewsEnsure(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        getDB()->exec("CREATE TABLE IF NOT EXISTS contact_views (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            owner_email VARCHAR(255) NOT NULL,
            name        VARCHAR(120) NOT NULL,
            query       VARCHAR(1000) NOT NULL DEFAULT '',
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_owner (owner_email),
            UNIQUE KEY uniq_owner_name (owner_email, name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Tables created before the unique key existed. Fails harmlessly if
        // duplicates are already present; saving still works, it just cannot
        // replace by name until they are cleared.
        try {
            $has = getDB()->query(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_views'
                 AND INDEX_NAME = 'uniq_owner_name'"
            )->fetchColumn();
            if (!$has) {
                getDB()->exec("ALTER TABLE contact_views
                               ADD UNIQUE KEY uniq_owner_name (owner_email, name)");
            }
        } catch (Exception $e2) {}
    } catch (Exception $e) {}
}

/**
 * Views belong to whoever saved them. They are a convenience, not a sharing
 * mechanism, and one officer's working set should not clutter another's.
 */
function peopleViewsList(string $owner): array {
    peopleViewsEnsure();
    try {
        $s = getDB()->prepare(
            "SELECT id, name, query FROM contact_views WHERE owner_email=? ORDER BY name"
        );
        $s->execute([contactNormalizeEmail($owner)]);
        return $s->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/** Keep only the parameters the page understands, with values it recognises. */
function peopleViewParams(string $query): array {
    $raw = [];
    parse_str($query, $raw);
    $out = [];
    foreach (PEOPLE_VIEW_KEYS as $k) {
        if (!isset($raw[$k]) || is_array($raw[$k])) continue;
        $v = trim((string)$raw[$k]);
        if ($v === '') continue;
        $out[$k] = $v;
    }
    return $out;
}

/**
 * Like peopleViewParams(), plus `page`. A saved view should not pin a page
 * number, but "take me back where I was" should — archiving someone on page 5
 * and landing on page 1 is exactly the lost place this is meant to avoid.
 */
function peopleBackParams(string $query): array {
    $out = peopleViewParams($query);
    $raw = [];
    parse_str($query, $raw);
    if (isset($raw['page']) && is_scalar($raw['page']) && (int)$raw['page'] > 1) {
        $out['page'] = (string)(int)$raw['page'];
    }
    return $out;
}

/** Returns '' on success, or a message saying what actually went wrong. */
function peopleViewSave(string $owner, string $name, array $params): string {
    peopleViewsEnsure();
    $name = trim($name);
    if ($name === '') return 'Give the view a name.';
    $query = http_build_query(array_intersect_key($params, array_flip(PEOPLE_VIEW_KEYS)));
    // Refused, not truncated: a cut query loses its trailing sort/dir or splits
    // a %XX escape, and the view would reopen as something other than what was
    // on screen while reporting that it saved fine.
    if (strlen($query) > 1000) {
        return 'That view is too complex to save — narrow the search text and try again.';
    }
    try {
        // Re-saving a name updates it. Two views with the same name and
        // different filters are indistinguishable in the list.
        getDB()->prepare(
            "INSERT INTO contact_views (owner_email, name, query) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE query=VALUES(query)"
        )->execute([contactNormalizeEmail($owner), mb_substr($name, 0, 120), $query]);
        return '';
    } catch (Exception $e) {
        // Distinct from the empty-name case, so the admin is not left retyping
        // against a problem no name can fix — but the driver's own words stay
        // in the log rather than going into a URL and onto the screen.
        error_log('peopleViewSave: ' . $e->getMessage());
        return 'The view could not be saved. The problem has been logged.';
    }
}

/** Owner is part of the WHERE, so one admin cannot delete another's view. */
function peopleViewDelete(int $id, string $owner): bool {
    peopleViewsEnsure();
    try {
        $s = getDB()->prepare("DELETE FROM contact_views WHERE id=? AND owner_email=?");
        $s->execute([$id, contactNormalizeEmail($owner)]);
        return $s->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}
