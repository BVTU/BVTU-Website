<?php
/**
 * bctf-db.php — membership forms collected for sending to the BCTF.
 *
 * The President photographs each new member's BCTF paperwork on their phone;
 * the photo is named from the surname Claude reads off the form, and the batch
 * is emailed to membership@bctf.ca in one go.
 */
require_once __DIR__ . '/db.php';

define('BCTF_FORMS_DIR', __DIR__ . '/bctf-forms/');
define('BCTF_TO_ADDRESS', 'membership@bctf.ca');
define('BCTF_SUBJECT',    'Local 54 Membership Forms');
define('BCTF_REPLY_TO',   'lp54@bctf.ca');

function bctfEnsureTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS bctf_forms (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        saved_path    VARCHAR(255) NOT NULL,
        original_name VARCHAR(255),
        last_name     VARCHAR(120) NOT NULL DEFAULT '',
        auto_named    TINYINT(1) NOT NULL DEFAULT 0,
        uploaded_by   VARCHAR(255) NOT NULL,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        sent_at       DATETIME DEFAULT NULL,
        INDEX idx_sent (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS bctf_upload_tokens (
        token      CHAR(32) PRIMARY KEY,
        created_by VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!is_dir(BCTF_FORMS_DIR)) mkdir(BCTF_FORMS_DIR, 0750, true);
    // Uploaded paperwork is personal data — never served directly.
    $ht = BCTF_FORMS_DIR . '.htaccess';
    if (!file_exists($ht)) file_put_contents($ht, "Require all denied\n");
}

/** One live token per president; scanning the QR again reuses it. */
function bctfCreateUploadToken(string $email): string {
    bctfEnsureTables();
    $db = getDB();
    $db->prepare("DELETE FROM bctf_upload_tokens WHERE created_by=?")->execute([$email]);
    $token = bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO bctf_upload_tokens (token, created_by) VALUES (?,?)")
       ->execute([$token, $email]);
    return $token;
}

function bctfValidateUploadToken(string $token): ?array {
    bctfEnsureTables();
    $s = getDB()->prepare("SELECT * FROM bctf_upload_tokens WHERE token=? LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

/** Forms photographed but not yet emailed. */
function bctfGetPending(): array {
    bctfEnsureTables();
    return getDB()->query(
        "SELECT * FROM bctf_forms WHERE sent_at IS NULL ORDER BY created_at"
    )->fetchAll();
}

function bctfGetSentBatches(int $limit = 20): array {
    bctfEnsureTables();
    $s = getDB()->prepare(
        "SELECT DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i') AS batch,
                COUNT(*) AS n, MAX(sent_at) AS sent_at
         FROM bctf_forms WHERE sent_at IS NOT NULL
         GROUP BY batch ORDER BY sent_at DESC LIMIT " . (int)$limit
    );
    $s->execute();
    return $s->fetchAll();
}

function bctfAddForm(string $savedPath, string $origName, string $lastName, bool $autoNamed, string $by): int {
    bctfEnsureTables();
    $db = getDB();
    $db->prepare(
        "INSERT INTO bctf_forms (saved_path, original_name, last_name, auto_named, uploaded_by)
         VALUES (?,?,?,?,?)"
    )->execute([$savedPath, $origName, $lastName, $autoNamed ? 1 : 0, $by]);
    return (int)$db->lastInsertId();
}

function bctfRenameForm(int $id, string $lastName): void {
    getDB()->prepare("UPDATE bctf_forms SET last_name=?, auto_named=0 WHERE id=? AND sent_at IS NULL")
           ->execute([$lastName, $id]);
}

function bctfDeleteForm(int $id): void {
    $s = getDB()->prepare("SELECT saved_path FROM bctf_forms WHERE id=? AND sent_at IS NULL");
    $s->execute([$id]);
    if ($row = $s->fetch()) {
        $f = BCTF_FORMS_DIR . basename($row['saved_path']);
        if (file_exists($f)) @unlink($f);
        getDB()->prepare("DELETE FROM bctf_forms WHERE id=?")->execute([$id]);
    }
}

/** "O'Brien-Smith" -> "obrien-smith"; keeps the filename safe and predictable. */
function bctfSlugName(string $lastName): string {
    $s = strtolower(trim($lastName));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? $s : 'unnamed';
}

/**
 * Attachment filenames, deduped. Two forms for the same surname become
 * smith_bctf.jpg and smith_bctf_2.jpg rather than silently overwriting.
 */
function bctfAttachmentNames(array $forms): array {
    $used = [];
    $out  = [];
    foreach ($forms as $f) {
        $ext  = strtolower(pathinfo($f['saved_path'], PATHINFO_EXTENSION)) ?: 'jpg';
        $base = bctfSlugName($f['last_name']) . '_bctf';
        $name = $base . '.' . $ext;
        $n = 1;
        while (isset($used[$name])) { $name = $base . '_' . (++$n) . '.' . $ext; }
        $used[$name] = true;
        $out[(int)$f['id']] = $name;
    }
    return $out;
}

/** Every form in one sent batch, keyed by the minute it went out. */
function bctfGetBatch(string $batchKey): array {
    bctfEnsureTables();
    $s = getDB()->prepare(
        "SELECT * FROM bctf_forms
         WHERE sent_at IS NOT NULL
         AND DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i') = ?
         ORDER BY created_at"
    );
    $s->execute([$batchKey]);
    return $s->fetchAll();
}

function bctfMarkSent(array $ids): void {
    if (!$ids) return;
    $in = implode(',', array_fill(0, count($ids), '?'));
    getDB()->prepare("UPDATE bctf_forms SET sent_at=NOW() WHERE id IN ($in)")
           ->execute(array_values($ids));
}
