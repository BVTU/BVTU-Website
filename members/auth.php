<?php
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['member_id']);
}

function requireLogin(bool $allowPendingPasswordChange = false): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    if (!$allowPendingPasswordChange && mustChangePassword()) {
        header('Location: change-password.php?forced=1');
        exit;
    }
}

function mustChangePassword(): bool {
    startSession();
    return !empty($_SESSION['must_change_password']);
}

function clearMustChangePassword(): void {
    startSession();
    $_SESSION['must_change_password'] = false;
}

/** Ensure must_change_password column exists on members table */
function ensureMembersColumns(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        require_once __DIR__ . '/db.php';
        $exists = getDB()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'members'
             AND COLUMN_NAME = 'must_change_password'"
        )->fetchColumn();
        if (!$exists) {
            getDB()->exec("ALTER TABLE members ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0");
        }
    } catch (Exception $e) {
        // Non-fatal — column may already exist
    }
}

function loginMember(array $member): void {
    startSession();
    session_regenerate_id(true);
    $_SESSION['member_id']             = $member['id'];
    $_SESSION['member_name']           = $member['name'];
    $_SESSION['member_email']          = $member['email'];
    $_SESSION['must_change_password']  = !empty($member['must_change_password']);
    // JS-readable cookie so static pages can update the nav instantly
    setcookie('bvtu_logged_in', '1', [
        'expires'  => time() + 60 * 60 * 24 * 7,
        'path'     => '/',
        'secure'   => true,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function logoutMember(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
    // Clear the nav cookie
    setcookie('bvtu_logged_in', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function getMember(): ?array {
    startSession();
    if (empty($_SESSION['member_id'])) return null;
    return [
        'id'    => $_SESSION['member_id'],
        'name'  => $_SESSION['member_name'],
        'email' => $_SESSION['member_email'],
    ];
}
