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
    'unknown'       => 'Not in Mailchimp',
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

    if (!empty($f['q'])) {
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR preferred_name LIKE ?
                     OR email LIKE ? OR secondary_email LIKE ?)";
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if (!empty($f['school_id'])) { $where[] = "school_id = ?";        $params[] = (int)$f['school_id']; }
    if (!empty($f['role']))      { $where[] = "role = ?";             $params[] = $f['role']; }
    if (!empty($f['status']))    { $where[] = "status = ?";           $params[] = $f['status']; }
    if (!empty($f['mc']))        { $where[] = "mailchimp_status = ?"; $params[] = $f['mc']; }
    if (empty($f['include_archived'])) { $where[] = "status <> 'archived'"; }

    $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $cnt = getDB()->prepare("SELECT COUNT(*) FROM contacts" . $sql);
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    // Whitelisted so the sort parameter can never reach SQL directly
    $sorts = [
        'name'    => 'last_name, first_name',
        'email'   => 'email',
        'school'  => 'school_id',
        'role'    => 'role',
        'status'  => 'status',
        'mc'      => 'mailchimp_status',
        'updated' => 'updated_at',
    ];
    $col = $sorts[$f['sort'] ?? 'name'] ?? $sorts['name'];
    $dir = (($f['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';

    $perPage = max(10, min(200, $perPage));
    $offset  = max(0, ($page - 1) * $perPage);

    $s = getDB()->prepare(
        "SELECT * FROM contacts" . $sql . " ORDER BY {$col} {$dir} LIMIT {$perPage} OFFSET {$offset}"
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
    'first_name', 'last_name', 'preferred_name', 'email', 'secondary_email',
    'phone', 'school_id', 'school_other', 'position', 'role', 'status', 'notes',
];

function contactCreate(array $d, string $actor): array {
    contactsEnsureTables();
    $email = trim($d['email'] ?? '');
    if (!contactValidEmail($email)) return ['error' => 'A valid email address is required.'];

    if ($existing = contactFindByEmail($email)) {
        return ['error' => 'A contact with that email already exists.', 'existing' => $existing];
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
        $clash = contactFindByEmail($email);
        if ($clash && (int)$clash['id'] !== $id) {
            return ['error' => 'Another contact already uses that email address.'];
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

    $sql = "UPDATE contacts SET mailchimp_status=?, mailchimp_last_synced_at=NOW(),
                   mailchimp_sync_status='ok', mailchimp_sync_error=NULL";
    $params = [$status];
    if ($mcId !== null) { $sql .= ", mailchimp_id=?"; $params[] = $mcId; }
    $sql .= " WHERE id=?";
    $params[] = $id;
    getDB()->prepare($sql)->execute($params);

    getDB()->prepare("DELETE FROM contact_sync_queue WHERE contact_id=?")->execute([$id]);

    if ($cur['mailchimp_status'] !== $status) {
        contactAudit($id, 'mailchimp_status', $source, 'mailchimp_status',
                     $cur['mailchimp_status'] . ' → ' . $status);
    }
}

function contactMarkSyncFailed(int $id, string $error, string $actor = 'sync'): void {
    contactsEnsureTables();
    getDB()->prepare(
        "UPDATE contacts SET mailchimp_sync_status='error', mailchimp_sync_error=? WHERE id=?"
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
