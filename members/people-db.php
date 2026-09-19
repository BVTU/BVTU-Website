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
