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
