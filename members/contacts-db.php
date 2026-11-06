<?php
/**
 * contacts-db.php — the union's contact list: schema, access, audit.
 *
 * This table is the source of truth for who someone is — name, school, role,
 * organisational status. Mailchimp is the source of truth for whether they
 * consent to marketing email, and the two are deliberately not allowed to
 * overwrite each other (see contactApplyMailchimpStatus).
 *
 * Tables owned here — include these in any database backup:
 *   contacts             the people
 *   contact_emails       the other addresses a person uses
 *   contact_audit        who changed what, when
 *   contact_sync_queue   Mailchimp pushes still to retry
 */
require_once __DIR__ . '/db.php';

/** Organisational status — ours to set. */
const CONTACT_STATUSES = [
    'active'   => 'Active',
    'leave'    => 'On leave',
    'retired'  => 'Retired',
    'inactive' => 'Inactive',
    'archived' => 'Archived',
];

/** Mailchimp's subscription states — theirs to set, never ours. */
const MC_STATUSES = [
    'subscribed'    => 'Subscribed',
    'unsubscribed'  => 'Unsubscribed',
    'pending'       => 'Pending',
    'cleaned'       => 'Cleaned',
    'transactional' => 'Transactional',
    // "unknown" means we have not been told otherwise. Whether that is
    // "never checked" or "checked, and not there" depends on
    // mailchimp_last_synced_at — see contactMcLabel(), which is what the UI
    // should use. The bare label must not assert absence.
    'unknown'       => 'Not checked',
];

function contactsEnsureTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS contacts (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        first_name        VARCHAR(120) NOT NULL DEFAULT '',
        last_name         VARCHAR(120) NOT NULL DEFAULT '',
        preferred_name    VARCHAR(120) NOT NULL DEFAULT '',
        email             VARCHAR(255) NOT NULL,
        email_normalized  VARCHAR(255) NOT NULL,
        secondary_email   VARCHAR(255) NOT NULL DEFAULT '',
        phone             VARCHAR(60)  NOT NULL DEFAULT '',
        school_id         INT DEFAULT NULL,
        school_other      VARCHAR(255) NOT NULL DEFAULT '',
        position          VARCHAR(160) NOT NULL DEFAULT '',
        role              VARCHAR(160) NOT NULL DEFAULT '',
        status            VARCHAR(20)  NOT NULL DEFAULT 'active',
        notes             TEXT,
        mailchimp_id      VARCHAR(64)  DEFAULT NULL,
        mailchimp_status  VARCHAR(20)  NOT NULL DEFAULT 'unknown',
        mailchimp_last_synced_at DATETIME DEFAULT NULL,
        mailchimp_sync_status    VARCHAR(20) NOT NULL DEFAULT 'pending',
        mailchimp_sync_error     VARCHAR(500) DEFAULT NULL,
        created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by        VARCHAR(255) NOT NULL DEFAULT '',
        updated_by        VARCHAR(255) NOT NULL DEFAULT '',
        -- One contact per address. Matching is always on the normalised form,
        -- so Jane@X.ca and jane@x.ca cannot both be created.
        UNIQUE KEY uniq_email_norm (email_normalized),
        INDEX idx_status (status),
        INDEX idx_mc (mailchimp_status),
        INDEX idx_school (school_id),
        INDEX idx_last (last_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ── Every address a person uses ───────────────────────────────────────
     * contacts.email still identifies them and is still the only address
     * Mailchimp ever sees; these are the others. It replaces the single
     * secondary_email column, which held exactly one and then ran out — a
     * teacher turned up having applied for the collaboration grant from three
     * addresses, and there was nowhere to put the third.
     *
     * Checked before the CREATE so the migration below runs once, on the
     * request that first creates the table. On an older MySQL that will not
     * answer the question, it is assumed absent and the migration runs: it is
     * written to be safe to repeat, which is the cheaper way to be wrong. */
    $hadEmails = false;
    try {
        $hadEmails = (bool)$db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_emails'"
        )->fetchColumn();
    } catch (Exception $e) {
        // Left false on purpose — see above.
    }

    $db->exec("CREATE TABLE IF NOT EXISTS contact_emails (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        contact_id        INT NOT NULL,
        email             VARCHAR(255) NOT NULL,
        email_normalized  VARCHAR(255) NOT NULL,
        created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_by        VARCHAR(255) NOT NULL DEFAULT '',
        -- One owner per address, the same rule contacts.email_normalized
        -- keeps. Two people cannot both claim an address, or the grant's
        -- ledger would not know whose day it was.
        UNIQUE KEY uniq_extra_norm (email_normalized),
        INDEX idx_contact (contact_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!$hadEmails) contactsMigrateSecondary($db);

    $db->exec("CREATE TABLE IF NOT EXISTS contact_audit (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        contact_id   INT DEFAULT NULL,
        action       VARCHAR(40) NOT NULL,
        actor        VARCHAR(255) NOT NULL DEFAULT '',
        -- Field names only, never the values: the audit log should not become a
        -- second copy of everyone's personal details.
        changed      VARCHAR(500) NOT NULL DEFAULT '',
        detail       VARCHAR(255) NOT NULL DEFAULT '',
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_contact (contact_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // --- Link to login accounts -------------------------------------------
    // A contact is a person; a member account is something a person may have.
    // The link is nullable on purpose: retirees and outside subscribers are
    // contacts with no account, and that has to stay expressible.
    try {
        $hasMember = $db->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'contacts' AND COLUMN_NAME = 'member_id'"
        )->fetchColumn();
        if (!$hasMember) {
            $db->exec("ALTER TABLE contacts ADD COLUMN member_id INT DEFAULT NULL");
            $db->exec("ALTER TABLE contacts ADD INDEX idx_member (member_id)");
        }
    } catch (Exception $e) {
        // Non-fatal — the column may already exist.
    }

    $db->exec("CREATE TABLE IF NOT EXISTS contact_sync_queue (
        contact_id  INT PRIMARY KEY,
        attempts    INT NOT NULL DEFAULT 0,
        last_error  VARCHAR(500) DEFAULT NULL,
        queued_at   DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Lower-cased and trimmed. Used for every lookup and for the unique key, so
 * case differences can never produce two records for one person.
 */
function contactNormalizeEmail(string $email): string {
    return strtolower(trim($email));
}

function contactValidEmail(string $email): bool {
    return (bool)filter_var(trim($email), FILTER_VALIDATE_EMAIL);
}

/**
 * Move contacts.secondary_email into contact_emails, once.
 *
 * The column is cleared as each address is carried across, so an address lives
 * in exactly one place afterwards. Leaving both populated would mean an
 * address an admin had deleted went on being merged by whatever still read the
 * column — the kind of fault that looks like the delete button not working.
 *
 * An address already claimed by somebody else is left in the column and
 * logged, not force-moved: two contacts holding one address is a data problem
 * for a person to look at, and silently picking a winner hides it.
 */
function contactsMigrateSecondary(PDO $db): void {
    try {
        $has = $db->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contacts'
             AND COLUMN_NAME = 'secondary_email'"
        )->fetchColumn();
        if (!$has) return;

        $rows = $db->query("SELECT id, secondary_email FROM contacts
                            WHERE secondary_email <> ''")->fetchAll();
        if (!$rows) return;

        $ins   = $db->prepare("INSERT IGNORE INTO contact_emails
                               (contact_id, email, email_normalized, created_by)
                               VALUES (?,?,?,'moved from secondary_email')");
        /* Each row on its own, because the loop used to share one try/catch:
         * a failure on row 40 of 300 left the rest in a column nothing reads
         * any more, and the run never came back. contactsUnmigratedSecondary()
         * is what notices, and People offers the retry. */
        $owner = $db->prepare("SELECT contact_id FROM contact_emails WHERE email_normalized=?");
        $prim  = $db->prepare("SELECT id FROM contacts WHERE email_normalized=?");
        $clear = $db->prepare("UPDATE contacts SET secondary_email='' WHERE id=?");
        $moved = $kept = 0;

        foreach ($rows as $r) {
            try {
                $id = (int)$r['id'];
                $e  = trim((string)$r['secondary_email']);
                if ($e === '') continue;
                $n  = contactNormalizeEmail($e);

                // Somebody's primary address already. Not an extra at all.
                $prim->execute([$n]);
                $pid = (int)($prim->fetchColumn() ?: 0);
                if ($pid) {
                    if ($pid !== $id) { $kept++; continue; }
                    $clear->execute([$id]);   // their own primary, listed twice
                    continue;
                }

                $ins->execute([$id, $e, $n]);
                // Cleared only once this contact is the one holding it, so a
                // collision cannot delete the evidence of itself.
                $owner->execute([$n]);
                if ((int)($owner->fetchColumn() ?: 0) === $id) { $clear->execute([$id]); $moved++; }
                else                                           { $kept++; }
            } catch (Exception $e2) {
                $kept++;
                error_log('contact_emails migration, contact ' . (int)$r['id'] . ': ' . $e2->getMessage());
            }
        }
        if ($kept) {
            error_log("contact_emails migration: moved {$moved}, left {$kept} "
                    . "in secondary_email because another contact holds the address");
        }
    } catch (Exception $e) {
        error_log('contact_emails migration: ' . $e->getMessage());
    }
}

/**
 * Contacts still holding an address in the old secondary_email column.
 *
 * Nothing reads that column any more, so an address left there is invisible:
 * not searchable, not shown, not folded for the grant's day cap. This is what
 * gives it a reader again. People shows them and retries the move, so a
 * migration that could not finish does not sit there unnoticed.
 */
function contactsUnmigratedSecondary(): array {
    contactsEnsureTables();
    try {
        return getDB()->query(
            "SELECT id, first_name, last_name, preferred_name, email, secondary_email
             FROM contacts WHERE secondary_email <> '' ORDER BY last_name, first_name"
        )->fetchAll();
    } catch (Exception $e) {
        return [];   // the column has been dropped: nothing can be left in it
    }
}

/** The addresses a contact uses besides contacts.email, oldest first. */
function contactEmails(int $id): array {
    contactsEnsureTables();
    try {
        $s = getDB()->prepare("SELECT email FROM contact_emails WHERE contact_id=? ORDER BY id");
        $s->execute([$id]);
        return array_map('strval', array_column($s->fetchAll(), 'email'));
    } catch (Exception $e) {
        return [];   // table not there yet: a contact has the one address
    }
}

/**
 * The extra addresses for many contacts at once, as id => [email, ...].
 *
 * The export asks for every matching contact, and calling contactEmails() per
 * row made that a query per person — up to a hundred thousand of them.
 */
function contactEmailsFor(array $ids): array {
    contactsEnsureTables();
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $out = array_fill_keys($ids, []);
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $s  = getDB()->prepare("SELECT contact_id, email FROM contact_emails
                                WHERE contact_id IN ($in) ORDER BY id");
        $s->execute($ids);
        foreach ($s->fetchAll() as $r) $out[(int)$r['contact_id']][] = (string)$r['email'];
    } catch (Exception $e) {
        // No table yet: everyone has the one address.
    }
    return $out;
}

/** Primary first, then the rest. What "their email addresses" means. */
function contactAllEmails(array $c): array {
    $out = [];
    $p = trim((string)($c['email'] ?? ''));
    if ($p !== '') $out[] = $p;
    foreach (contactEmails((int)($c['id'] ?? 0)) as $e) {
        if (strcasecmp($e, $p) !== 0) $out[] = $e;
    }
    return $out;
}

/**
 * Who holds this address, counting primaries and extras alike.
 * Returns [id, name, primary] or null.
 */
function contactEmailOwner(string $email): ?array {
    contactsEnsureTables();
    $n = contactNormalizeEmail($email);
    if ($n === '') return null;
    $s = getDB()->prepare("SELECT * FROM contacts WHERE email_normalized=?");
    $s->execute([$n]);
    if ($c = $s->fetch()) {
        return ['id' => (int)$c['id'], 'name' => contactDisplayName($c), 'primary' => true];
    }
    try {
        $s = getDB()->prepare(
            "SELECT c.* FROM contact_emails e JOIN contacts c ON c.id = e.contact_id
             WHERE e.email_normalized = ?");
        $s->execute([$n]);
        if ($c = $s->fetch()) {
            return ['id' => (int)$c['id'], 'name' => contactDisplayName($c), 'primary' => false];
        }
    } catch (Exception $e) {
        // No contact_emails table yet.
    }
    return null;
}

/**
 * Are these usable as extra addresses for this contact? '' if so, else why not.
 *
 * Asked before anything is written, so a bad address in the list cannot leave
 * the other fields saved and the addresses half-applied.
 */
function contactEmailsCheck(array $emails, int $forId, string $primary = ''): string {
    $seen = [];
    $pn   = contactNormalizeEmail($primary);
    foreach ($emails as $e) {
        $e = trim((string)$e);
        if ($e === '') continue;
        if (!contactValidEmail($e)) {
            return '"' . $e . '" does not look like an email address.';
        }
        $n = contactNormalizeEmail($e);
        /* Their own main address, still sitting in the list. Dropped rather
         * than refused: promoting one of somebody's other addresses to the
         * main one is a normal edit, and refusing it threw away every other
         * change on the form and left the admin with no way through except
         * clearing the box first. contactEmailsSync() will not store it. */
        if ($pn !== '' && $n === $pn) continue;
        if (isset($seen[$n])) return 'The same address is listed twice: ' . $e;
        $seen[$n] = true;

        $own = contactEmailOwner($e);
        if ($own && $own['id'] !== $forId) {
            return $e . ' is already on ' . $own['name'] . "'s record"
                 . ($own['primary'] ? ' as their main address.' : '.')
                 . ' One address belongs to one person.';
        }
    }
    return '';
}

/**
 * Make the stored extras match this list. Returns what changed.
 *
 * A straight diff rather than delete-all-and-reinsert: the audit entry should
 * say an address was added, and created_at is worth keeping.
 */
function contactEmailsSync(int $id, array $emails, string $actor): array {
    contactsEnsureTables();
    $want = [];
    foreach ($emails as $e) {
        $e = trim((string)$e);
        if ($e !== '') $want[contactNormalizeEmail($e)] = $e;
    }
    $have = [];
    foreach (contactEmails($id) as $e) $have[contactNormalizeEmail($e)] = $e;

    $add = array_diff_key($want, $have);
    $del = array_diff_key($have, $want);

    /* Never the contact's own main address, however it got into the list:
     * stored, it would be listed twice and the alias map would carry an
     * address to itself. */
    $cur = contactGet($id);
    $pn  = $cur ? contactNormalizeEmail((string)$cur['email']) : '';
    if ($pn !== '') {
        unset($want[$pn], $add[$pn]);
        // Recomputed, not patched: a row left over from before equal to the
        // main address should now be deleted, not quietly kept.
        $del = array_diff_key($have, $want);
    }

    if ($add) {
        $ins = getDB()->prepare("INSERT IGNORE INTO contact_emails
                                 (contact_id, email, email_normalized, created_by)
                                 VALUES (?,?,?,?)");
        foreach ($add as $n => $e) {
            $ins->execute([$id, $e, $n, $actor]);
            /* IGNORE means a race or a claim this contact does not own ends as
             * zero rows, and reporting it as added would put an address in the
             * notice and the audit log that is not on the record. */
            if ($ins->rowCount() < 1) unset($add[$n]);
        }
    }
    if ($del) {
        $rm = getDB()->prepare("DELETE FROM contact_emails WHERE contact_id=? AND email_normalized=?");
        foreach ($del as $n => $e) $rm->execute([$id, $n]);
    }
    if ($add || $del) {
        contactAudit($id, 'edited', $actor, 'other email addresses',
                     count($add) . ' added, ' . count($del) . ' removed');
    }
    return ['added' => array_values($add), 'removed' => array_values($del)];
}

function contactDisplayName(array $c): string {
    $first = trim($c['preferred_name'] ?: $c['first_name']);
    $name  = trim($first . ' ' . trim($c['last_name']));
    return $name !== '' ? $name : $c['email'];
}

function contactGet(int $id): ?array {
    contactsEnsureTables();
    $s = getDB()->prepare("SELECT * FROM contacts WHERE id=?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function contactFindByEmail(string $email): ?array {
    contactsEnsureTables();
    $s = getDB()->prepare("SELECT * FROM contacts WHERE email_normalized=?");
    $s->execute([contactNormalizeEmail($email)]);
    return $s->fetch() ?: null;
}

/**
 * Filtered, sorted, paginated list. Returns [rows, totalMatching].
 * Every filter is bound, never interpolated; sort is whitelisted.
 */
function contactSearch(array $f, int $page = 1, int $perPage = 50): array {
    contactsEnsureTables();
    $where  = [];
    $params = [];

    // !== '' rather than !empty(): "0" is a legitimate search term, and the
    // pill above the list would otherwise claim a filter that never applied.
    if (($f['q'] ?? '') !== '') {
        /*
         * One word at a time, and every word has to land somewhere.
         *
         * The whole query used to be matched against each column on its own, so
         * "cody lind" was looked for inside first_name, inside last_name and
         * inside email, and found in none of them — a first name worked, a full
         * name never did, which is the first thing anyone types. Requiring each
         * word separately makes a full name work in either order, and still
         * finds "cod li".
         */
        $cols  = ['first_name', 'last_name', 'preferred_name', 'email'];
        $terms = preg_split('/\s+/', trim((string)$f['q']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // "Lind, Cody" is a name as people write it; the comma is punctuation,
        // not something to search for. Trimmed at the edges only, so an address
        // keeps its dots and dashes.
        $terms = array_values(array_filter(array_map(function ($t) {
            return trim($t, " \t\n\r,;:");
        }, $terms), function ($t) { return $t !== ''; }));
        // A pasted paragraph would otherwise build an unbounded query.
        foreach (array_slice($terms, 0, 6) as $term) {
            /*
             * Escape the LIKE metacharacters, or searching "100%" matches every
             * contact and "_" matches any single character.
             *
             * The escape character is named rather than left to the backslash
             * MySQL uses by default: that default disappears under the
             * NO_BACKSLASH_ESCAPES sql_mode, and it is not the rule in every
             * engine, so an unstated one is a wildcard escape that works until
             * the day it does not. A pipe is not special in either a string
             * literal or a LIKE pattern.
             */
            $like = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $term) . '%';
            $ors = [];
            foreach ($cols as $c) { $ors[] = "$c LIKE ? ESCAPE '|'"; $params[] = $like; }
            /* The other addresses they use. They were a column, so searching
             * them came free; now they are rows, and leaving them out would
             * mean typing the address somebody actually wrote from found
             * nobody. Both queries below select from `contacts` unaliased, so
             * the correlation names the table. */
            $ors[] = "EXISTS (SELECT 1 FROM contact_emails ce"
                   . " WHERE ce.contact_id = contacts.id AND ce.email LIKE ? ESCAPE '|')";
            $params[] = $like;
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }
    }
    if (!empty($f['school_id'])) { $where[] = "school_id = ?";        $params[] = (int)$f['school_id']; }
    if (!empty($f['role']))      { $where[] = "role = ?";             $params[] = $f['role']; }
    if (!empty($f['status']))    { $where[] = "status = ?";           $params[] = $f['status']; }
    if (!empty($f['mc']))        { $where[] = "mailchimp_status = ?"; $params[] = $f['mc']; }
    if (empty($f['include_archived'])) { $where[] = "status <> 'archived'"; }

    // An explicit selection of rows, for "export the ones I ticked". Bound, and
    // an empty set means no match rather than no filter.
    if (isset($f['id_in'])) {
        $ids = array_values(array_filter(array_map('intval', (array)$f['id_in'])));
        if (!$ids) return [[], 0];
        $where[] = 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        foreach ($ids as $i) $params[] = $i;
    }

    // Restrict to a set of addresses worked out in PHP (account and invite
    // filters). Done this way because `members` and `member_invitations` can
    // carry a different collation from `contacts`, so joining them in SQL
    // risks "Illegal mix of collations". An explicit empty set means no match,
    // which is different from no filter at all.
    if (isset($f['email_whitelist'])) {
        $list = array_values(array_unique(array_map('contactNormalizeEmail', (array)$f['email_whitelist'])));
        if (!$list) {
            return [[], 0];
        }
        $where[] = 'email_normalized IN (' . implode(',', array_fill(0, count($list), '?')) . ')';
        foreach ($list as $e) $params[] = $e;
    }

    $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $cnt = getDB()->prepare("SELECT COUNT(*) FROM contacts" . $sql);
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    // Whitelisted so the sort parameter can never reach SQL directly
    // "name" sorts by the name the list actually displays — preferred name if
    // there is one, otherwise first name, then surname — because sorting by a
    // column the screen never shows reads as no sorting at all.
    //
    // Each sort is a LIST of expressions and the direction is applied to every
    // one of them. Writing "ORDER BY a, b DESC" instead attaches DESC to b
    // alone, leaving a ascending: the toggle then appears to do nothing.
    $blankLast = "CASE WHEN TRIM(CONCAT(COALESCE(NULLIF(TRIM(preferred_name),''), TRIM(first_name)),"
               . " ' ', TRIM(last_name))) = '' THEN 1 ELSE 0 END";

    $sorts = [
        'name'    => ["COALESCE(NULLIF(TRIM(preferred_name),''), TRIM(first_name))", 'TRIM(last_name)'],
        'surname' => ['TRIM(last_name)', 'TRIM(first_name)'],
        'email'   => ['email'],
        'school'  => ['school_id'],
        'role'    => ['role'],
        'status'  => ['status'],
        'mc'      => ['mailchimp_status'],
        'updated' => ['updated_at'],
    ];
    $key  = (string)($f['sort'] ?? 'name');
    if (!isset($sorts[$key])) $key = 'name';
    $dir  = (($f['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

    $terms = [];
    // People with no name at all sit at the end either way, rather than
    // heading the list on one click and trailing it on the next.
    if ($key === 'name') $terms[] = $blankLast . ' ASC';
    foreach ($sorts[$key] as $expr) $terms[] = $expr . ' ' . $dir;
    // id last as a tiebreaker: without a unique final term, rows sharing a
    // value (every row shares updated_at right after an import) can come back
    // in a different order per page, repeating some and skipping others.
    $terms[] = 'id ' . $dir;
    $order = implode(', ', $terms);

    // Ceiling is high enough for an export to ask for everything. List pages
    // pass their own page size, so this does not make a screen unbounded.
    $perPage = max(10, min(100000, $perPage));
    $offset  = max(0, ($page - 1) * $perPage);

    $s = getDB()->prepare(
        "SELECT * FROM contacts" . $sql . " ORDER BY {$order} LIMIT {$perPage} OFFSET {$offset}"
    );
    $s->execute($params);
    return [$s->fetchAll(), $total];
}

function contactAudit(?int $contactId, string $action, string $actor, string $changed = '', string $detail = ''): void {
    contactsEnsureTables();
    getDB()->prepare(
        "INSERT INTO contact_audit (contact_id, action, actor, changed, detail) VALUES (?,?,?,?,?)"
    )->execute([$contactId, $action, $actor, substr($changed, 0, 500), substr($detail, 0, 255)]);
}

/** Fields an administrator may set. Mailchimp's own columns are not here. */
const CONTACT_EDITABLE = [
    'first_name', 'last_name', 'preferred_name', 'email',
    'phone', 'school_id', 'school_other', 'position', 'role', 'status', 'notes',
];

function contactCreate(array $d, string $actor): array {
    contactsEnsureTables();
    $email = trim($d['email'] ?? '');
    if (!contactValidEmail($email)) return ['error' => 'A valid email address is required.'];

    if ($existing = contactFindByEmail($email)) {
        return ['error' => 'A contact with that email already exists.', 'existing' => $existing];
    }
    // Also when it is somebody's second address. Creating a contact around an
    // address another record already answers for is how one person becomes two.
    if ($own = contactEmailOwner($email)) {
        return ['error' => 'That address is already listed on ' . $own['name']
                         . "'s record. One address belongs to one person."];
    }

    $cols = ['email', 'email_normalized', 'created_by', 'updated_by'];
    $vals = [$email, contactNormalizeEmail($email), $actor, $actor];
    foreach (CONTACT_EDITABLE as $c) {
        if ($c === 'email') continue;
        $cols[] = $c;
        $vals[] = $c === 'school_id'
            ? (($d[$c] ?? '') !== '' ? (int)$d[$c] : null)
            : trim((string)($d[$c] ?? ''));
    }
    $ph = implode(',', array_fill(0, count($cols), '?'));
    getDB()->prepare("INSERT INTO contacts (" . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
    $id = (int)getDB()->lastInsertId();

    contactAudit($id, 'created', $actor);
    return ['id' => $id];
}

/** Updates and returns the field names that actually changed. */
function contactUpdate(int $id, array $d, string $actor): array {
    contactsEnsureTables();
    $cur = contactGet($id);
    if (!$cur) return ['error' => 'Contact not found.'];

    $email = trim($d['email'] ?? $cur['email']);
    if (!contactValidEmail($email)) return ['error' => 'A valid email address is required.'];

    $norm = contactNormalizeEmail($email);
    if ($norm !== $cur['email_normalized']) {
        $clash = contactEmailOwner($email);
        if ($clash && $clash['id'] !== $id) {
            return ['error' => $clash['primary']
                ? 'Another contact already uses that email address.'
                : 'That address is listed as a second address on ' . $clash['name']
                  . "'s record. Take it off there first."];
        }
    }

    $set = ['email = ?', 'email_normalized = ?', 'updated_by = ?'];
    $vals = [$email, $norm, $actor];
    $changed = [];
    if ($norm !== $cur['email_normalized']) $changed[] = 'email';

    foreach (CONTACT_EDITABLE as $c) {
        if ($c === 'email') continue;
        $new = $c === 'school_id'
            ? (($d[$c] ?? '') !== '' ? (int)$d[$c] : null)
            : trim((string)($d[$c] ?? ''));
        $old = $c === 'school_id' ? ($cur[$c] !== null ? (int)$cur[$c] : null) : (string)$cur[$c];
        if ($new !== $old) $changed[] = $c;
        $set[]  = "$c = ?";
        $vals[] = $new;
    }
    $vals[] = $id;
    getDB()->prepare("UPDATE contacts SET " . implode(', ', $set) . " WHERE id = ?")->execute($vals);

    /* Promoted one of their own second addresses to the main one. Left in
     * place it would be listed twice, and the alias map would carry an address
     * to itself. */
    if ($norm !== $cur['email_normalized']) {
        try {
            getDB()->prepare("DELETE FROM contact_emails WHERE contact_id=? AND email_normalized=?")
                   ->execute([$id, $norm]);
        } catch (Exception $e) {
            // No contact_emails table yet; nothing to tidy.
        }
    }

    if ($changed) contactAudit($id, 'edited', $actor, implode(', ', $changed));
    return ['id' => $id, 'changed' => $changed];
}

/** Archive rather than delete — the person may still matter to the union. */
function contactArchive(int $id, string $actor, bool $archive = true): void {
    contactsEnsureTables();
    getDB()->prepare("UPDATE contacts SET status=?, updated_by=? WHERE id=?")
           ->execute([$archive ? 'archived' : 'active', $actor, $id]);
    contactAudit($id, $archive ? 'archived' : 'restored', $actor);
}

// ── Mailchimp state ──────────────────────────────────────────────────────────

/**
 * Record what Mailchimp says. Called by the webhook and after a push.
 *
 * Deliberately one-way: this writes Mailchimp's state into our row and nothing
 * here ever sends a status the other way. Marking someone Active locally must
 * not resubscribe them.
 */
function contactApplyMailchimpStatus(int $id, string $status, ?string $mcId = null, string $source = 'sync'): void {
    contactsEnsureTables();
    if (!isset(MC_STATUSES[$status])) $status = 'unknown';

    $cur = contactGet($id);
    if (!$cur) return;

    // updated_at is ON UPDATE CURRENT_TIMESTAMP and the People list shows it as
    // "Last activity", so it is pinned for our own housekeeping — a sync or a
    // verify pass confirming what we already knew would otherwise stamp every
    // row with today. A status that genuinely CHANGED is activity on their
    // record whichever way we heard about it — a webhook, or a sync that first
    // noticed an unsubscribe because the webhook was never configured.
    // A first check is worth an audit row — the badge goes from "Not checked" to
    // a real claim — but it must NOT stamp updated_at. On the first verify every
    // contact is unchecked, so that would restamp the entire roster with today
    // and make "Last activity" useless on exactly the run that touches everyone.
    $firstCheck = empty($cur['mailchimp_last_synced_at']);
    $changed    = ($cur['mailchimp_status'] !== $status);
    $pin = $changed ? '' : ', updated_at=updated_at';
    $sql = "UPDATE contacts SET mailchimp_status=?, mailchimp_last_synced_at=NOW(),
                   mailchimp_sync_status='ok', mailchimp_sync_error=NULL{$pin}";
    $params = [$status];
    if ($mcId !== null) { $sql .= ", mailchimp_id=?"; $params[] = $mcId; }
    $sql .= " WHERE id=?";
    $params[] = $id;
    getDB()->prepare($sql)->execute($params);

    getDB()->prepare("DELETE FROM contact_sync_queue WHERE contact_id=?")->execute([$id]);

    if ($cur['mailchimp_status'] !== $status) {
        contactAudit($id, 'mailchimp_status', $source, 'mailchimp_status',
                     $cur['mailchimp_status'] . ' → ' . $status);
    } elseif ($firstCheck) {
        contactAudit($id, 'mailchimp_checked', $source, '', 'first check: ' . $status);
    }
}

function contactMarkSyncFailed(int $id, string $error, string $actor = 'sync'): void {
    contactsEnsureTables();
    // No timestamp: a failure taught us nothing, and mailchimp_last_synced_at
    // means "when we last learned their real status". The sync runner walks an
    // id cursor, so it advances past a failing contact without needing one.
    getDB()->prepare(
        "UPDATE contacts SET mailchimp_sync_status='error', mailchimp_sync_error=?,
                updated_at=updated_at WHERE id=?"
    )->execute([substr($error, 0, 500), $id]);

    // Queued so it can be retried; a Mailchimp outage must not lose the edit.
    getDB()->prepare(
        "INSERT INTO contact_sync_queue (contact_id, attempts, last_error)
         VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1, last_error=VALUES(last_error)"
    )->execute([$id, substr($error, 0, 500)]);

    contactAudit($id, 'sync_failed', $actor, '', substr($error, 0, 200));
}

function contactSyncFailures(): array {
    contactsEnsureTables();
    return getDB()->query(
        "SELECT c.* FROM contacts c
         JOIN contact_sync_queue q ON q.contact_id = c.id
         ORDER BY q.queued_at"
    )->fetchAll();
}

function contactCounts(): array {
    contactsEnsureTables();
    $db = getDB();
    return [
        'total'    => (int)$db->query("SELECT COUNT(*) FROM contacts WHERE status<>'archived'")->fetchColumn(),
        'archived' => (int)$db->query("SELECT COUNT(*) FROM contacts WHERE status='archived'")->fetchColumn(),
        'errors'   => (int)$db->query("SELECT COUNT(*) FROM contact_sync_queue")->fetchColumn(),
    ];
}

/** Schools come from the existing Pro-D table rather than a second list. */
function contactSchools(): array {
    try {
        return getDB()->query("SELECT id, name FROM prod_schools WHERE active=1 ORDER BY name")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function contactDistinctRoles(): array {
    contactsEnsureTables();
    return getDB()->query(
        "SELECT DISTINCT role FROM contacts WHERE role <> '' ORDER BY role"
    )->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * One-time move of the imported roster into contacts.
 *
 * member_invitations held name + email for everyone imported from the
 * membership spreadsheet. Those people are contacts; the invitation is just an
 * event about them. Copies anything not already present and leaves the
 * invitations untouched, so it is safe to run more than once.
 */
function contactsMigrateFromInvitations(string $actor = 'migration'): int {
    contactsEnsureTables();
    $moved = 0;
    try {
        $rows = getDB()->query("SELECT email, name, created_at FROM member_invitations")->fetchAll();
    } catch (Exception $e) {
        return 0;   // invite system not installed
    }

    foreach ($rows as $r) {
        $email = trim($r['email'] ?? '');
        if (!contactValidEmail($email) || contactFindByEmail($email)) continue;

        // Roster names are "First Last"; split on the last space so double
        // first names stay intact and only the final token becomes the surname.
        $full  = trim($r['name'] ?? '');
        $first = $full;
        $last  = '';
        if (($sp = strrpos($full, ' ')) !== false) {
            $first = substr($full, 0, $sp);
            $last  = substr($full, $sp + 1);
        }

        getDB()->prepare(
            "INSERT INTO contacts (first_name, last_name, email, email_normalized,
                                   status, created_by, updated_by, created_at)
             VALUES (?,?,?,?,'active',?,?,?)"
        )->execute([$first, $last, $email, contactNormalizeEmail($email),
                    $actor, $actor, $r['created_at'] ?? date('Y-m-d H:i:s')]);
        $moved++;
    }
    if ($moved) contactAudit(null, 'import', $actor, '', "Imported {$moved} from the membership roster");
    return $moved;
}

/* ---------------------------------------------------------------------------
 * Reconciling accounts with people
 *
 * `members` holds login accounts, `contacts` holds people. They were built at
 * different times and nothing kept them in step, so an account could exist with
 * no contact record and never appear in a count, an export or a sync.
 *
 * Nothing here joins on members.id — the rest of the portal identifies a person
 * by email (exec_roles, exp_batches and lp_vouchers all carry user_email), so
 * the normalised address is the only key needed to put the two back together.
 * ------------------------------------------------------------------------ */

/**
 * Split a single "First Last" name into two parts on the LAST space, so
 * "Mary Jane Wilson" keeps "Mary Jane" as the given name. Returns [first, last].
 */
function contactSplitName(string $full): array {
    $full = trim($full);
    if ($full === '') return ['', ''];
    $sp = strrpos($full, ' ');
    if ($sp === false) return [$full, ''];
    return [substr($full, 0, $sp), substr($full, $sp + 1)];
}

/**
 * Point each contact at its login account, matching on the normalised address.
 * Safe to re-run: it only ever sets the link, and clears one whose addresses
 * no longer match — because the account was deleted, or because one side's
 * email was changed and the other's was not.
 *
 * Returns ['linked' => n, 'cleared' => n] rather than one total. Those are
 * opposite outcomes, and a caller reporting "linked 3" when two of them were
 * stale links being removed would be telling the admin the reverse of what
 * happened.
 */
function contactsLinkMembers(): array {
    contactsEnsureTables();
    $db = getDB();
    $linked = 0; $cleared = 0;

    try {
        $accounts = $db->query("SELECT id, email FROM members")->fetchAll();
    } catch (Exception $e) {
        return ['linked' => 0, 'cleared' => 0];   // no members table
    }

    $byEmail = [];
    foreach ($accounts as $a) {
        $byEmail[contactNormalizeEmail($a['email'])] = (int)$a['id'];
    }

    $rows = $db->query("SELECT id, email_normalized, member_id FROM contacts")->fetchAll();
    // updated_at pinned: repairing a link is our bookkeeping, and the People
    // list shows updated_at as "Last activity". Without this, pressing
    // "Fix links" restamps everyone it touches with today.
    $set  = $db->prepare("UPDATE contacts SET member_id=?, updated_at=updated_at WHERE id=?");
    foreach ($rows as $r) {
        // Normalised on both sides. A legacy row whose stored value was never
        // lower-cased would otherwise be counted as unlinked by
        // contactsAccountGap() but never matched here, leaving a "Link them"
        // prompt that does nothing however often it is pressed.
        $want = $byEmail[contactNormalizeEmail($r['email_normalized'])] ?? null;
        $have = $r['member_id'] !== null ? (int)$r['member_id'] : null;
        if ($want !== $have) {
            $set->execute([$want, (int)$r['id']]);
            if ($want === null) { $cleared++; } else { $linked++; }
        }
    }
    return ['linked' => $linked, 'cleared' => $cleared];
}

/**
 * Create a contact for every login account that has none — the mirror of
 * contactsMigrateFromInvitations(). An account is proof the person exists, so
 * there is no judgement call here; the account's own name and email are used.
 *
 * Note for the record: a contact created this way will be sent to Mailchimp by
 * the next sync as a TRANSACTIONAL contact. It does not subscribe anyone to
 * marketing email, and it cannot resubscribe someone who opted out — see
 * mcPushContact(), which never sends a status.
 */
function contactsMigrateFromMembers(string $actor = 'migration'): int {
    contactsEnsureTables();
    $added = 0;

    try {
        $rows = getDB()->query("SELECT id, name, email, created_at FROM members")->fetchAll();
    } catch (Exception $e) {
        return 0;
    }

    $ins = getDB()->prepare(
        "INSERT INTO contacts (first_name, last_name, email, email_normalized,
                               status, member_id, created_by, updated_by, created_at)
         VALUES (?,?,?,?,'active',?,?,?,?)"
    );

    foreach ($rows as $r) {
        $email = trim($r['email'] ?? '');
        if (!contactValidEmail($email) || contactFindByEmail($email)) continue;

        list($first, $last) = contactSplitName((string)($r['name'] ?? ''));
        $ins->execute([$first, $last, $email, contactNormalizeEmail($email),
                       (int)$r['id'], $actor, $actor,
                       $r['created_at'] ?? date('Y-m-d H:i:s')]);
        $added++;
    }

    if ($added) contactAudit(null, 'import', $actor, '', "Added {$added} from member accounts");
    return $added;
}

/**
 * How far apart the two stores are right now, for the reconcile prompt.
 *   accounts          login accounts that exist
 *   accounts_nocontact  accounts with no contact record — the drift
 *   contacts_withaccount  people who can log in
 */
function contactsAccountGap(): array {
    contactsEnsureTables();
    $db = getDB();

    // Compared in PHP, not SQL. `members.email` and `contacts.email_normalized`
    // can carry different collations, and comparing them in a query raises
    // "Illegal mix of collations" — the same failure that broke the email log.
    // Two small lists; the set difference is cheaper than the risk.
    try {
        $accountRows = $db->query("SELECT id, email FROM members")->fetchAll();
        $contactRows = $db->query("SELECT email_normalized, member_id FROM contacts")->fetchAll();
    } catch (Exception $e) {
        // Report the failure rather than reading as "nothing to reconcile".
        return ['accounts' => 0, 'accounts_nocontact' => 0, 'contacts_withaccount' => 0,
                'unlinked' => 0, 'error' => $e->getMessage()];
    }

    $have = [];
    foreach ($contactRows as $r) $have[contactNormalizeEmail($r['email_normalized'])] = $r;

    // id => address, so a link can be judged against the account it points at
    // rather than merely against whether that account still exists.
    $accountEmail = [];
    foreach ($accountRows as $a) $accountEmail[(int)$a['id']] = contactNormalizeEmail($a['email']);

    $orphans = 0;
    foreach ($accountEmail as $e) {
        if (!isset($have[$e])) $orphans++;
    }

    // Counted directly rather than inferred from the difference between two
    // totals: duplicate addresses among accounts mean those totals can never
    // reach parity, which would leave a "Link them" prompt on screen forever
    // with nothing left for it to do.
    $accountKeys = array_flip(array_values($accountEmail));

    $unlinked = 0;
    // Over every row, not over $have: that map keeps one row per address, so a
    // duplicate pair — one linked, one not — would hide the unlinked one and
    // the prompt to fix it would never appear.
    // "Needs work" is any row whose link disagrees with the addresses: missing
    // when an account matches, or present while pointing at an account that is
    // gone OR whose address is no longer this person's. member_id is derived
    // from the address, so a link that contradicts it is wrong by definition.
    foreach ($contactRows as $r) {
        $key = contactNormalizeEmail($r['email_normalized']);
        if ($r['member_id'] === null) {
            if (isset($accountKeys[$key])) $unlinked++;
        } else {
            $pointsAt = $accountEmail[(int)$r['member_id']] ?? null;
            if ($pointsAt === null || $pointsAt !== $key) $unlinked++;
        }
    }

    $accounts = count($accountEmail);
    $linked   = (int)$db->query("SELECT COUNT(*) FROM contacts WHERE member_id IS NOT NULL")->fetchColumn();

    return [
        'accounts'             => $accounts,
        'accounts_nocontact'   => $orphans,
        'contacts_withaccount' => $linked,
        'unlinked'             => $unlinked,
        'error'                => '',
    ];
}

/**
 * Called immediately after a login account is created, from every place that
 * creates one. Makes sure the person also exists as a contact and that the two
 * are linked, so the stores cannot drift apart again the way they did.
 *
 * Never overwrites an existing contact's details — someone may already be on
 * the list with a fuller record than the signup form collected. It only fills
 * in the account link.
 */
function contactEnsureForAccount(int $memberId, string $name, string $email, string $actor = 'signup'): void {
    if (!contactValidEmail($email)) return;
    try {
        contactsEnsureTables();
        $existing = contactFindByEmail($email);
        if ($existing) {
            if ((int)($existing['member_id'] ?? 0) !== $memberId) {
                // Not pinned: this runs when someone registers or an account is
                // created for them, which is real activity — unlike
                // contactsLinkMembers(), which is us repairing bookkeeping.
                getDB()->prepare("UPDATE contacts SET member_id=? WHERE id=?")
                       ->execute([$memberId, (int)$existing['id']]);
            }
            return;
        }
        list($first, $last) = contactSplitName($name);
        getDB()->prepare(
            "INSERT INTO contacts (first_name, last_name, email, email_normalized,
                                   status, member_id, created_by, updated_by)
             VALUES (?,?,?,?,'active',?,?,?)"
        )->execute([$first, $last, trim($email), contactNormalizeEmail($email),
                    $memberId, $actor, $actor]);
    } catch (Exception $e) {
        // Creating an account must never fail because the contact list is
        // unavailable. The reconcile prompt on contacts.php catches the gap.
    }
}

/**
 * What we can honestly say about one person's Mailchimp state.
 * Returns [label, tone, detail] where tone is one of the badge colours.
 *
 * The distinction that matters: a contact the sync has never visited is
 * "Not checked", not "Not in Mailchimp". Reporting the second when we mean the
 * first is how someone who is plainly in the audience appears to be missing.
 */
function contactMcLabel(array $c): array {
    $status  = $c['mailchimp_status'] ?? 'unknown';
    $checked = $c['mailchimp_last_synced_at'] ?? null;

    // Only when we have nothing better to say. A push that failed while their
    // status was already verified does not make that status unknown — and the
    // failure itself is surfaced separately on the list and on the record.
    if ($status === 'unknown' && !$checked && ($c['mailchimp_sync_status'] ?? '') === 'error') {
        return ['Sync failed', 'amber',
                trim((string)(($c['mailchimp_sync_error'] ?? '') ?: 'The last attempt to reach Mailchimp failed.'))];
    }

    if ($status === 'unknown') {
        if (!$checked) {
            return ['Not checked', 'grey', 'This record has never been compared with Mailchimp.'];
        }
        return ['Not in Mailchimp', 'red',
                'Checked ' . date('M j, Y', strtotime($checked)) . ' — Mailchimp had no such address.'];
    }

    $label  = MC_STATUSES[$status] ?? $status;
    $detail = $checked ? 'Checked ' . date('M j, Y', strtotime($checked)) : 'Reported by Mailchimp.';
    $tone   = [
        'subscribed'    => 'green',
        'unsubscribed'  => 'slate',
        'pending'       => 'amber',
        'cleaned'       => 'red',
        'transactional' => 'blue',
    ][$status] ?? 'grey';

    return [$label, $tone, $detail];
}

