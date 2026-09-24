<?php
/**
 * qr-db.php — Saved QR codes for the Link Shortener & QR Codes page.
 *
 * What is stored is the recipe, not the picture: the text that was encoded
 * plus the settings it was built with. A QR code is fully determined by those,
 * so the image can always be redrawn — and a row stays a few hundred bytes
 * instead of a megabyte of PNG that nobody can search or correct.
 */
require_once __DIR__ . '/db.php';

function qrEnsureTable(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    getDB()->exec("CREATE TABLE IF NOT EXISTS qr_codes (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        content_hash CHAR(64) NOT NULL UNIQUE,
        content      VARCHAR(2000) NOT NULL,
        label        VARCHAR(255) NOT NULL DEFAULT '',
        ec           CHAR(1) NOT NULL DEFAULT 'M',
        margin       TINYINT NOT NULL DEFAULT 4,
        created_by   VARCHAR(255) NOT NULL,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * The identity of a saved code. Settings are part of it because the same text
 * at 'M' and at 'H' are different images with different uses (a poster that
 * will get rained on wants 'H'), and overwriting one with the other would lose
 * a code somebody is relying on.
 */
function qrHash(string $content, string $ec, int $margin): string {
    return hash('sha256', $content . "\0" . $ec . "\0" . $margin);
}

/** Saves, or refreshes an identical code that is already saved. */
function qrSave(string $content, string $label, string $ec, int $margin, string $by): void {
    qrEnsureTable();
    $content = trim($content);
    $ec      = in_array($ec, ['L', 'M', 'Q', 'H'], true) ? $ec : 'M';
    $margin  = ($margin >= 0 && $margin <= 16) ? $margin : 4;
    getDB()->prepare(
        "INSERT INTO qr_codes (content_hash, content, label, ec, margin, created_by)
         VALUES (?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
             -- Two guards on the label. An empty box means "none supplied",
             -- not "clear the one I set last time". And because the key is the
             -- code itself, two people can land on the same row — so someone
             -- else re-saving an identical code must not rename yours.
             label = IF(VALUES(label) = '' OR created_by <> VALUES(created_by),
                        label, VALUES(label)),
             last_used_at = NOW()"
    )->execute([qrHash($content, $ec, $margin), $content, trim($label), $ec, $margin, $by]);
}

/**
 * Most-recently-used first, capped: downloading saves automatically, so this
 * list only grows. 200 is far more than this local will ever have, and stops
 * one runaway day from making the page unloadable.
 */
function qrGetAll(int $limit = 200): array {
    qrEnsureTable();
    $q = getDB()->prepare("SELECT * FROM qr_codes ORDER BY last_used_at DESC, id DESC LIMIT ?");
    $q->bindValue(1, $limit, PDO::PARAM_INT);
    $q->execute();
    return $q->fetchAll();
}

function qrDelete(int $id): void {
    qrEnsureTable();
    getDB()->prepare("DELETE FROM qr_codes WHERE id=?")->execute([$id]);
}
