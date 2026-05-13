<?php
/**
 * curated-db.php — Curated Resources database helpers
 *
 * Roles:
 *   - Public:   browse curated.php
 *   - Curator:  add / edit / delete curated entries (managed in curated_curators table)
 *   - Admin:    everything + manage who is a curator (PROD_ADMIN_EMAIL)
 */
require_once __DIR__ . '/db.php';

const CURATED_BANDS = [
    'k3'  => 'Primary K–3',
    '47'  => 'Intermediate 4–7',
    '812' => 'Secondary 8–12',
    'all' => 'All Grades',
];

const CURATED_SUBJECTS = [
    'Math', 'English / ELA', 'Science', 'Social Studies',
    'French', 'Arts', 'PE / Health', 'ADST',
    'Physics', 'Chemistry', 'Biology', 'Earth Science',
    'Computer Science', 'Business Education', 'Psychology',
    'Drama', 'Visual Art', 'Music', 'Other',
];

// ── Table setup ───────────────────────────────────────────────────────────────
function curatedEnsureTables(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS curated_resources (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        title         VARCHAR(500) NOT NULL,
        description   TEXT,
        url           VARCHAR(1000) NOT NULL,
        type          ENUM('external','internal') NOT NULL DEFAULT 'external',
        grade_band    VARCHAR(10) NOT NULL DEFAULT 'all',
        subject       VARCHAR(100) DEFAULT '',
        thumbnail_url VARCHAR(500) DEFAULT '',
        added_by      VARCHAR(255) NOT NULL,
        added_name    VARCHAR(255) NOT NULL DEFAULT '',
        sort_order    INT DEFAULT 0,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_band    (grade_band),
        INDEX idx_subject (subject)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS curated_curators (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(255) NOT NULL UNIQUE,
        name       VARCHAR(255) NOT NULL DEFAULT '',
        added_by   VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Role checks ───────────────────────────────────────────────────────────────
function curatedIsAdmin(string $email): bool {
    $e = strtolower(trim($email));
    if (defined('PROD_ADMIN_EMAIL') && $e === strtolower(trim(PROD_ADMIN_EMAIL))) return true;
    return $e === 'lp54@bctf.ca';
}

function curatedIsCurator(string $email): bool {
    if (curatedIsAdmin($email)) return true;
    $e  = strtolower(trim($email));
    $db = getDB();
    $st = $db->prepare("SELECT id FROM curated_curators WHERE LOWER(email)=? LIMIT 1");
    $st->execute([$e]);
    return (bool)$st->fetch();
}

// ── Curators management ───────────────────────────────────────────────────────
function curatedGetCurators(): array {
    return getDB()->query("SELECT * FROM curated_curators ORDER BY created_at ASC")->fetchAll();
}

function curatedAddCurator(string $email, string $name, string $addedBy): void {
    $db = getDB();
    $st = $db->prepare("INSERT IGNORE INTO curated_curators (email, name, added_by) VALUES (?,?,?)");
    $st->execute([strtolower(trim($email)), trim($name), $addedBy]);
}

function curatedRemoveCurator(string $email): void {
    $db = getDB();
    $st = $db->prepare("DELETE FROM curated_curators WHERE LOWER(email)=?");
    $st->execute([strtolower(trim($email))]);
}

// ── Resource CRUD ─────────────────────────────────────────────────────────────
function curatedGetAll(?string $band = null, ?string $subject = null): array {
    $db     = getDB();
    $where  = [];
    $params = [];

    if ($band && $band !== 'all' && isset(CURATED_BANDS[$band])) {
        $where[]  = "(grade_band = ? OR grade_band = 'all')";
        $params[] = $band;
    }
    if ($subject && in_array($subject, CURATED_SUBJECTS, true)) {
        $where[]  = "subject = ?";
        $params[] = $subject;
    }

    $sql = "SELECT * FROM curated_resources"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . " ORDER BY sort_order ASC, created_at DESC";

    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function curatedGet(int $id): ?array {
    $st = getDB()->prepare("SELECT * FROM curated_resources WHERE id=?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function curatedAdd(array $d, string $addedBy, string $addedName): int {
    $db = getDB();
    $st = $db->prepare(
        "INSERT INTO curated_resources
         (title, description, url, type, grade_band, subject, thumbnail_url, added_by, added_name, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?)"
    );
    $st->execute([
        trim($d['title']),
        trim($d['description'] ?? ''),
        trim($d['url']),
        $d['type'] === 'internal' ? 'internal' : 'external',
        $d['grade_band'] ?? 'all',
        $d['subject'] ?? '',
        trim($d['thumbnail_url'] ?? ''),
        $addedBy,
        $addedName,
        (int)($d['sort_order'] ?? 0),
    ]);
    return (int)$db->lastInsertId();
}

function curatedUpdate(int $id, array $d): void {
    $db = getDB();
    $st = $db->prepare(
        "UPDATE curated_resources SET
         title=?, description=?, url=?, type=?, grade_band=?,
         subject=?, thumbnail_url=?, sort_order=?
         WHERE id=?"
    );
    $st->execute([
        trim($d['title']),
        trim($d['description'] ?? ''),
        trim($d['url']),
        $d['type'] === 'internal' ? 'internal' : 'external',
        $d['grade_band'] ?? 'all',
        $d['subject'] ?? '',
        trim($d['thumbnail_url'] ?? ''),
        (int)($d['sort_order'] ?? 0),
        $id,
    ]);
}

function curatedDelete(int $id): void {
    $st = getDB()->prepare("DELETE FROM curated_resources WHERE id=?");
    $st->execute([$id]);
}
