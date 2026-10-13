<?php
/**
 * lp-share.php — read-only review links for a single expense voucher.
 *
 * A former officer, an auditor or a BCTF contact can be shown one voucher and
 * its receipts without a portal account, a role, or any signing authority. The
 * link is the credential, so it expires, it can be withdrawn, and it says when
 * it was last opened.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lp-db.php';
date_default_timezone_set('America/Vancouver');

const LP_SHARE_DAYS = 60;

function lpShareEnsureTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    getDB()->exec("CREATE TABLE IF NOT EXISTS lp_voucher_shares (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        voucher_id     INT          NOT NULL,
        token          VARCHAR(64)  NOT NULL UNIQUE,
        shared_with    VARCHAR(255),
        created_by     VARCHAR(255),
        created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at     DATETIME,
        revoked_at     DATETIME,
        last_viewed_at DATETIME,
        view_count     INT DEFAULT 0,
        INDEX idx_voucher (voucher_id),
        INDEX idx_token   (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function lpShareCreate(int $voucherId, string $sharedWith, string $createdBy): string {
    lpShareEnsureTables();
    $token = bin2hex(random_bytes(24));
    // Computed here rather than with MySQL's DATE_ADD(NOW(), ...): the database
    // may well be on UTC while lpShareState() compares against PHP's clock in
    // America/Vancouver, which would quietly give every link an extra evening.
    $expires = date('Y-m-d H:i:s', strtotime('+' . LP_SHARE_DAYS . ' days'));
    getDB()->prepare(
        "INSERT INTO lp_voucher_shares (voucher_id, token, shared_with, created_by, expires_at)
         VALUES (?,?,?,?,?)"
    )->execute([
        $voucherId, $token,
        mb_substr(trim($sharedWith), 0, 255) ?: null,
        mb_substr(trim($createdBy), 0, 255) ?: null,
        $expires,
    ]);
    return $token;
}

/** Every link ever made for this voucher, newest first — live, expired or withdrawn. */
function lpSharesFor(int $voucherId): array {
    lpShareEnsureTables();
    $s = getDB()->prepare(
        "SELECT * FROM lp_voucher_shares WHERE voucher_id = ? ORDER BY created_at DESC, id DESC"
    );
    $s->execute([$voucherId]);
    return $s->fetchAll();
}

function lpShareRevoke(int $id, int $voucherId): void {
    lpShareEnsureTables();
    getDB()->prepare(
        "UPDATE lp_voucher_shares SET revoked_at = NOW() WHERE id = ? AND voucher_id = ? AND revoked_at IS NULL"
    )->execute([$id, $voucherId]);
}

/**
 * The share a token names, or null.
 *
 * Returns the row for an expired or withdrawn link too, so the page can say
 * which it is — "this link has expired" is a useful answer and "not found" is
 * not.
 */
function lpShareByToken(string $token): ?array {
    if (strlen($token) < 20) return null;
    lpShareEnsureTables();
    $s = getDB()->prepare("SELECT * FROM lp_voucher_shares WHERE token = ? LIMIT 1");
    $s->execute([$token]);
    return $s->fetch() ?: null;
}

function lpShareState(?array $share): array {
    if (!$share)                        return ['ok' => false, 'why' => 'notfound'];
    if (!empty($share['revoked_at']))   return ['ok' => false, 'why' => 'revoked'];
    if (!empty($share['expires_at']) && strtotime($share['expires_at']) < time()) {
        return ['ok' => false, 'why' => 'expired'];
    }
    return ['ok' => true, 'why' => ''];
}

function lpShareTouch(int $id): void {
    lpShareEnsureTables();
    getDB()->prepare(
        "UPDATE lp_voucher_shares SET last_viewed_at = NOW(), view_count = view_count + 1 WHERE id = ?"
    )->execute([$id]);
}

function lpShareUrl(string $token): string {
    // Built from our own configured address, not from the request's Host
    // header: this URL carries a live credential and gets emailed out, so a
    // forged Host would send the token to whoever forged it.
    $base = defined('SITE_URL') && SITE_URL ? rtrim(SITE_URL, '/') : 'https://bvtu.ca';
    return $base . '/voucher.php?t=' . urlencode($token);
}

/**
 * Is this receipt filename actually on this voucher?
 *
 * The token opens one voucher. Without this a holder could pass any filename
 * and read receipts from somebody else's.
 */
function lpShareReceiptName(int $voucherId, string $file): ?string {
    $s = getDB()->prepare(
        "SELECT receipt_path FROM lp_expenses
         WHERE voucher_id = ? AND receipt_path = ? LIMIT 1"
    );
    $s->execute([$voucherId, $file]);
    $found = $s->fetchColumn();
    if ($found === false || $found === null || $found === '') return null;

    // The comparison above is the database's, which ignores case and trailing
    // spaces. Hand back what is actually stored and read that from disk, or a
    // request could match one row and open a different file.
    return basename((string)$found);
}
