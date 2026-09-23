<?php
function startSession(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // Cookie flags were never set, so the session cookie was readable by
    // JavaScript and sent cross-site. Set before session_start() or they are
    // ignored. Applies to every page, not only the contact system.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,   // only over HTTPS; left off locally so dev still works
        'httponly' => true,     // not reachable from JavaScript
        'samesite' => 'Lax',    // blocks cross-site POSTs while keeping normal links working
    ]);
    session_start();
}

/**
 * Per-session CSRF token for state-changing forms.
 * Emit with csrfField(); check with csrfCheck() before acting on any POST.
 */
function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/** True when the submitted token matches. hash_equals avoids timing leaks. */
function csrfValid(): bool {
    startSession();
    $sent = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && is_string($sent)
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/** Stop a POST that fails CSRF, without leaking why to a crawler. */
function csrfCheck(): void {
    if (!csrfValid()) {
        http_response_code(400);
        exit('Request could not be verified. Go back, reload the page and try again.');
    }
}

/**
 * A query parameter as a trimmed scalar, or ''.
 *
 * ?q[]=x arrives as an array; passing that to trim() is fatal on PHP 8, and
 * binding it through PDO is worse. Lives in auth.php because every member page
 * includes it: four private copies had drifted apart within a day of being
 * written, and then it sat in contacts-db.php where a page that reads request
 * parameters but not contacts could not see it at all.
 */
function reqStr(string $key): string {
    $v = $_GET[$key] ?? '';
    return is_scalar($v) ? trim((string)$v) : '';
}

/**
 * Headers for pages showing personal data: keep them out of caches and out of
 * search results. Authentication is the actual protection; this is hygiene.
 */
function sendPrivateHeaders(): void {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
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
        $hasActive = getDB()->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'members' AND COLUMN_NAME = 'active'"
        )->fetchColumn();
        if (!$hasActive) {
            getDB()->exec("ALTER TABLE members ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1");
        }

        // Drop FK on employee_number so invite-based registration (no employee number) works.
        // Find and drop any FK referencing valid_employee_numbers from members.
        $fkName = getDB()->query(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'members'
             AND REFERENCED_TABLE_NAME = 'valid_employee_numbers'
             LIMIT 1"
        )->fetchColumn();
        if ($fkName) {
            getDB()->exec("ALTER TABLE members DROP FOREIGN KEY `{$fkName}`");
        }
        // Also make employee_number nullable so the column can be omitted on insert.
        $empNullable = getDB()->query(
            "SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'members' AND COLUMN_NAME = 'employee_number'"
        )->fetchColumn();
        if ($empNullable === 'NO') {
            getDB()->exec("ALTER TABLE members MODIFY COLUMN employee_number VARCHAR(50) NULL DEFAULT NULL");
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
