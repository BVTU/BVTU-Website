<?php
/**
 * exec-db.php — Executive Committee roles DB layer
 * Tracks the 15 official BVTU EC positions.
 * Each person may hold up to 2 roles (enforced at the application layer).
 */
require_once __DIR__ . '/db.php';

// ── Official EC role definitions ───────────────────────────────────────────────
define('EXEC_ROLES', [
    'president'            => 'President',
    'vice_president'       => 'Vice-President',
    'recording_secretary'  => 'Recording Secretary',
    'treasurer'            => 'Treasurer',
    'local_representative' => 'Local Representative',
    'bargaining_chair'     => 'Bargaining Chair',
    'grievance_chair'      => 'Grievance Chair',
    'djohsc_rep'           => 'District Joint OH&S Committee Representative',
    'french_language_rep'  => 'French Language Representative',
    'prod_committee_chair' => 'Professional Development Committee Chair',
    'political_action'     => 'Political Action Contact',
    'social_justice'       => 'Social Justice Contact',
    'aboriginal_education' => 'Local Aboriginal Education Contact',
    'past_president'       => 'Past President',
    'ttoc_rep'             => 'TTOC Representative',
]);

// ── Table setup ────────────────────────────────────────────────────────────────
function execEnsureTables(): void {
    getDB()->exec("CREATE TABLE IF NOT EXISTS exec_roles (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_email   VARCHAR(200) NOT NULL,
        user_name    VARCHAR(200) NOT NULL DEFAULT '',
        role         VARCHAR(60)  NOT NULL,
        assigned_by  VARCHAR(200) NOT NULL DEFAULT '',
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_email_role (user_email, role),
        INDEX idx_email (user_email),
        INDEX idx_role  (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Access helpers ─────────────────────────────────────────────────────────────

/** True if email is the constant admin or holds the President EC role */
function execIsAdmin(string $email): bool {
    $e = strtolower(trim($email));
    if (defined('PROD_ADMIN_EMAIL') && strtolower(trim(PROD_ADMIN_EMAIL)) === $e) return true;
    return execHasRole($e, 'president');
}

/** True if the person holds any EC role (or is the constant admin) */
function execIsEC(string $email): bool {
    $e = strtolower(trim($email));
    if (execIsAdmin($e)) return true;
    $s = getDB()->prepare("SELECT COUNT(*) FROM exec_roles WHERE user_email=?");
    $s->execute([$e]);
    return (int)$s->fetchColumn() > 0;
}

function execHasRole(string $email, string $role): bool {
    $s = getDB()->prepare("SELECT COUNT(*) FROM exec_roles WHERE user_email=? AND role=?");
    $s->execute([strtolower(trim($email)), $role]);
    return (int)$s->fetchColumn() > 0;
}

// ── Data access ────────────────────────────────────────────────────────────────

/** All rows, ordered by name then role */
function execGetAll(): array {
    return getDB()->query(
        "SELECT * FROM exec_roles ORDER BY user_name, role"
    )->fetchAll();
}

/**
 * Returns people keyed by email, each with their role rows.
 * e.g. ['jane@bvtu.ca' => ['name'=>'Jane','email'=>'...','rows'=>[...]]]
 */
function execGetPeople(): array {
    $people = [];
    foreach (execGetAll() as $r) {
        $e = strtolower(trim($r['user_email']));
        if (!isset($people[$e])) {
            $people[$e] = ['name' => $r['user_name'], 'email' => $e, 'rows' => []];
        }
        $people[$e]['rows'][] = $r;
    }
    return $people;
}

/** Number of roles currently assigned to this email */
function execCountRoles(string $email): int {
    $s = getDB()->prepare("SELECT COUNT(*) FROM exec_roles WHERE user_email=?");
    $s->execute([strtolower(trim($email))]);
    return (int)$s->fetchColumn();
}

/**
 * Returns a map of role slug => row (or null) for quick roster lookup.
 * e.g. ['president' => [...row...], 'treasurer' => null, ...]
 */
function execGetRosterMap(): array {
    $rows = getDB()->query(
        "SELECT * FROM exec_roles ORDER BY created_at"
    )->fetchAll();

    $map = [];
    foreach (array_keys(EXEC_ROLES) as $slug) {
        $map[$slug] = null;
    }
    foreach ($rows as $r) {
        if (array_key_exists($r['role'], $map)) {
            $map[$r['role']] = $r;
        }
    }
    return $map;
}

// ── Write operations ───────────────────────────────────────────────────────────

/**
 * Assign a role. Returns true on success, false if the person already has 2 roles.
 * Throws PDOException on duplicate role assignment.
 */
function execAssignRole(string $email, string $name, string $role, string $assignedBy): bool {
    $e = strtolower(trim($email));
    if (execCountRoles($e) >= 2) return false;
    getDB()->prepare(
        "INSERT INTO exec_roles (user_email, user_name, role, assigned_by)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE user_name=VALUES(user_name), assigned_by=VALUES(assigned_by)"
    )->execute([$e, trim($name), $role, $assignedBy]);
    return true;
}

function execRemoveRole(int $id): void {
    getDB()->prepare("DELETE FROM exec_roles WHERE id=?")->execute([$id]);
}
