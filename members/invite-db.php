<?php
/**
 * invite-db.php — Invite token helpers for member self-registration
 */
require_once __DIR__ . '/email-templates-db.php';
require_once __DIR__ . '/db.php';

function inviteEnsureTable(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    getDB()->exec("CREATE TABLE IF NOT EXISTS member_invitations (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        email         VARCHAR(255) NOT NULL,
        name          VARCHAR(255),
        token         CHAR(64) NOT NULL UNIQUE,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at    DATETIME NOT NULL,
        accepted_at   DATETIME DEFAULT NULL,
        created_by    VARCHAR(255) NOT NULL,
        sent_at       DATETIME DEFAULT NULL,
        INDEX idx_token (token),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migration for tables created before importing and sending were separated.
    try {
        $hasSent = getDB()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'member_invitations' AND COLUMN_NAME = 'sent_at'"
        )->fetchColumn();
        if (!$hasSent) {
            getDB()->exec("ALTER TABLE member_invitations ADD COLUMN sent_at DATETIME DEFAULT NULL");
            // Every pre-existing invite was emailed the moment it was created,
            // so backfill rather than leaving them all looking unsent.
            getDB()->exec("UPDATE member_invitations SET sent_at = created_at");
        }
    } catch (Exception $ex) {}
}

function inviteGenerateToken(): string {
    return bin2hex(random_bytes(32)); // 64 hex chars
}

/**
 * Create (or replace) an invite and mark it as sent. Returns the token.
 * For flows that email immediately; bulk import should use inviteImport().
 */
function inviteCreate(string $email, string $name, string $createdBy): string {
    inviteEnsureTable();
    $token = inviteGenerateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+72 hours'));
    // Remove any prior pending invite for this email
    getDB()->prepare("DELETE FROM member_invitations WHERE email=? AND accepted_at IS NULL")
           ->execute([strtolower($email)]);
    getDB()->prepare(
        "INSERT INTO member_invitations (email, name, token, expires_at, created_by, sent_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    )->execute([strtolower($email), $name ?: null, $token, $expires, $createdBy]);
    return $token;
}

/**
 * Add an invite to the list WITHOUT emailing it. Returns 'added', 'updated'
 * or 'exists'. The token stored here is a placeholder — inviteIssue() mints a
 * fresh one at send time so the 72-hour clock starts when the member is
 * actually emailed, not whenever the roster happened to be imported.
 */
function inviteImport(string $email, string $name, string $createdBy): string {
    inviteEnsureTable();
    $email = strtolower(trim($email));

    $s = getDB()->prepare(
        "SELECT id, name FROM member_invitations WHERE email=? AND accepted_at IS NULL LIMIT 1"
    );
    $s->execute([$email]);
    $existing = $s->fetch();

    if ($existing) {
        // Already on the list — refresh the name but leave send state alone.
        if ($name && $name !== $existing['name']) {
            getDB()->prepare("UPDATE member_invitations SET name=? WHERE id=?")
                   ->execute([$name, $existing['id']]);
            return 'updated';
        }
        return 'exists';
    }

    getDB()->prepare(
        "INSERT INTO member_invitations (email, name, token, expires_at, created_by, sent_at)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 72 HOUR), ?, NULL)"
    )->execute([$email, $name ?: null, inviteGenerateToken(), $createdBy]);
    return 'added';
}

/**
 * Mint a fresh token, restart the 72-hour window, mark as sent and email it.
 * Returns true if the message went out.
 */
function inviteIssue(int $id): bool {
    inviteEnsureTable();
    $s = getDB()->prepare("SELECT * FROM member_invitations WHERE id=? AND accepted_at IS NULL");
    $s->execute([$id]);
    $inv = $s->fetch();
    if (!$inv) return false;

    $token = inviteGenerateToken();
    getDB()->prepare(
        "UPDATE member_invitations
         SET token=?, expires_at=DATE_ADD(NOW(), INTERVAL 72 HOUR), sent_at=NOW()
         WHERE id=?"
    )->execute([$token, $id]);

    return inviteSendEmail($inv['email'], $inv['name'] ?: $inv['email'], $token);
}

/** Invites on the list that have never been emailed. */
function inviteGetUnsent(): array {
    inviteEnsureTable();
    return getDB()->query(
        "SELECT * FROM member_invitations
         WHERE sent_at IS NULL AND accepted_at IS NULL
         ORDER BY created_at"
    )->fetchAll();
}

/** Look up a valid (unexpired, unused) invite by token. */
function inviteGetByToken(string $token): ?array {
    inviteEnsureTable();
    $s = getDB()->prepare(
        "SELECT * FROM member_invitations
         WHERE token=? AND accepted_at IS NULL AND expires_at > NOW()"
    );
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

/** Mark an invite as used. */
function inviteAccept(string $token): void {
    getDB()->prepare(
        "UPDATE member_invitations SET accepted_at=NOW() WHERE token=?"
    )->execute([$token]);
}

/** List all invites, newest first. */
function inviteGetAll(): array {
    inviteEnsureTable();
    return getDB()->query(
        "SELECT i.*,
                CASE
                  WHEN accepted_at IS NOT NULL THEN 'accepted'
                  WHEN sent_at IS NULL         THEN 'not_sent'
                  WHEN expires_at < NOW()      THEN 'expired'
                  ELSE 'pending'
                END AS invite_status
         FROM member_invitations i
         ORDER BY (sent_at IS NULL) DESC, created_at DESC"
    )->fetchAll();
}

/** Revoke a pending invite. */
function inviteRevoke(int $id): void {
    getDB()->prepare(
        "DELETE FROM member_invitations WHERE id=? AND accepted_at IS NULL"
    )->execute([$id]);
}

/** "B" -> 1, "AA" -> 26. Used to place cells that the sheet omits when blank. */
function _inviteColToIndex(string $letters): int {
    $n = 0;
    $letters = strtoupper($letters);
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

function _inviteDecode(string $v): string {
    return html_entity_decode($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Read the first worksheet of an .xlsx into rows of plain strings.
 * An .xlsx is a ZIP of XML, so this needs no PHP extension beyond ZipArchive.
 * Returns [] if the file can't be read as a spreadsheet.
 */
function _inviteReadXlsx(string $path): array {
    if (!class_exists('ZipArchive')) return [];
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return [];

    // Shared string table — most text cells are references into this.
    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false && preg_match_all('/<si>(.*?)<\/si>/s', $ss, $m)) {
        foreach ($m[1] as $si) {
            // Rich text is split across multiple <t> runs; join them.
            preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $tm);
            $shared[] = _inviteDecode(implode('', $tm[1]));
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetXml = $zip->getFromName($name);
                break;
            }
        }
    }
    $zip->close();
    if (!$sheetXml) return [];

    $rows = [];
    preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rm);
    foreach ($rm[1] as $rowXml) {
        preg_match_all('/<c\b([^>]*?)(?:\/>|>(.*?)<\/c>)/s', $rowXml, $cm, PREG_SET_ORDER);
        $cells = [];
        foreach ($cm as $c) {
            $attrs = $c[1];
            $inner = $c[2] ?? '';
            $col   = preg_match('/r="([A-Za-z]+)/', $attrs, $r) ? _inviteColToIndex($r[1]) : count($cells);
            $type  = preg_match('/t="([^"]+)"/', $attrs, $t) ? $t[1] : 'n';

            $val = '';
            if ($type === 'inlineStr') {
                preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $inner, $im);
                $val = _inviteDecode(implode('', $im[1]));
            } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $vm)) {
                $val = $type === 's' ? ($shared[(int)$vm[1]] ?? '') : _inviteDecode($vm[1]);
            }
            $cells[$col] = $val;
        }
        if (!$cells) continue;
        // Fill gaps so column positions line up with the header row
        $dense = [];
        for ($i = 0, $max = max(array_keys($cells)); $i <= $max; $i++) {
            $dense[$i] = $cells[$i] ?? '';
        }
        $rows[] = $dense;
    }
    return $rows;
}

/**
 * Read an uploaded roster (.xlsx or .csv) into rows of plain strings.
 * Detects by content rather than filename — a mislabelled file is common.
 */
function inviteReadSheet(string $path): array {
    // Every .xlsx starts with the ZIP magic bytes "PK\x03\x04"
    $fh = fopen($path, 'rb');
    $magic = $fh ? fread($fh, 4) : '';
    if ($fh) fclose($fh);

    if (strncmp($magic, "PK\x03\x04", 4) === 0) {
        return _inviteReadXlsx($path);
    }

    $rows = [];
    $fh = fopen($path, 'r');
    if (!$fh) return $rows;
    $first = true;
    while (($row = fgetcsv($fh)) !== false) {
        if ($first && isset($row[0])) {
            // Strip the UTF-8 BOM Excel writes, which otherwise corrupts the
            // first header name and breaks column detection.
            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            $first = false;
        }
        $rows[] = $row;
    }
    fclose($fh);
    return $rows;
}

/** Send the invite email. Plain text for best deliverability. */
function inviteSendEmail(string $email, string $name, string $token): bool {
    $host    = 'bvtu.ca';
    $url     = "https://{$host}/members/invite-register.php?token={$token}";
    $to      = $name ? "{$name}" : $email;
    $vars    = ['{{email}}' => $to, '{{link}}' => $url];
    $subject = emailTplSubject('invite_link', $vars);
    $body    = emailTplBlock('invite_link', 'intro', $vars) . "\n\n"
             . "Create your account:\n{$url}\n\n"
             . emailTplBlock('invite_link', 'outro', $vars);

    require_once __DIR__ . '/smtp.php';
    return siteMail($email, $subject, $body);
}
