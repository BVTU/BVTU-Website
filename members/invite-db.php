<?php
/**
 * invite-db.php — Invite token helpers for member self-registration
 */
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
        INDEX idx_token (token),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function inviteGenerateToken(): string {
    return bin2hex(random_bytes(32)); // 64 hex chars
}

/** Create (or replace) an invite for this email. Returns the token. */
function inviteCreate(string $email, string $name, string $createdBy): string {
    inviteEnsureTable();
    $token = inviteGenerateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+72 hours'));
    // Remove any prior pending invite for this email
    getDB()->prepare("DELETE FROM member_invitations WHERE email=? AND accepted_at IS NULL")
           ->execute([strtolower($email)]);
    getDB()->prepare(
        "INSERT INTO member_invitations (email, name, token, expires_at, created_by)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([strtolower($email), $name ?: null, $token, $expires, $createdBy]);
    return $token;
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
                  WHEN expires_at < NOW()      THEN 'expired'
                  ELSE 'pending'
                END AS invite_status
         FROM member_invitations i
         ORDER BY created_at DESC"
    )->fetchAll();
}

/** Revoke a pending invite. */
function inviteRevoke(int $id): void {
    getDB()->prepare(
        "DELETE FROM member_invitations WHERE id=? AND accepted_at IS NULL"
    )->execute([$id]);
}

/** Send the invite email. Plain text for best deliverability. */
function inviteSendEmail(string $email, string $name, string $token): bool {
    $host    = 'bvtu.ca';
    $url     = "https://{$host}/members/invite-register.php?token={$token}";
    $to      = $name ? "{$name}" : $email;
    $subject = 'Set up your BVTU member account';
    $body    = "Hi {$to},\n\n"
             . "The Bulkley Valley Teachers' Union has created a member portal where you can access union resources, submit expense claims, and more.\n\n"
             . "Use the link below to set up your account. It's one-time use and expires in 72 hours.\n\n"
             . "Create your account:\n{$url}\n\n"
             . "If you weren't expecting this email, you can ignore it — no account will be created unless you click the link and set a password.\n\n"
             . "Questions? Reply to lp54@bctf.ca\n\n"
             . "— Bulkley Valley Teachers' Union";

    require_once __DIR__ . '/smtp.php';
    return siteMail($email, $subject, $body);
}
