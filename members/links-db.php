<?php
/**
 * links-db.php — Short link helpers for bvtu.ca/go/{slug}
 */
require_once __DIR__ . '/db.php';

function linksEnsureTable(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    getDB()->exec("CREATE TABLE IF NOT EXISTS short_links (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        slug         VARCHAR(100) NOT NULL UNIQUE,
        destination  VARCHAR(2000) NOT NULL,
        label        VARCHAR(255) NOT NULL DEFAULT '',
        created_by   VARCHAR(255) NOT NULL,
        created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        click_count  INT NOT NULL DEFAULT 0,
        active       TINYINT(1) NOT NULL DEFAULT 1,
        INDEX idx_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function linksGetAll(): array {
    linksEnsureTable();
    return getDB()->query(
        "SELECT * FROM short_links ORDER BY active DESC, click_count DESC, created_at DESC"
    )->fetchAll();
}

function linksGetBySlug(string $slug): ?array {
    linksEnsureTable();
    $s = getDB()->prepare("SELECT * FROM short_links WHERE slug=? AND active=1");
    $s->execute([strtolower(trim($slug))]);
    return $s->fetch() ?: null;
}

function linksCreate(string $slug, string $destination, string $label, string $createdBy): void {
    linksEnsureTable();
    getDB()->prepare(
        "INSERT INTO short_links (slug, destination, label, created_by) VALUES (?,?,?,?)"
    )->execute([strtolower(trim($slug)), trim($destination), trim($label), $createdBy]);
}

function linksUpdate(int $id, string $slug, string $destination, string $label): void {
    getDB()->prepare(
        "UPDATE short_links SET slug=?, destination=?, label=? WHERE id=?"
    )->execute([strtolower(trim($slug)), trim($destination), trim($label), $id]);
}

function linksSetActive(int $id, int $active): void {
    getDB()->prepare("UPDATE short_links SET active=? WHERE id=?")->execute([$active, $id]);
}

function linksDelete(int $id): void {
    getDB()->prepare("DELETE FROM short_links WHERE id=?")->execute([$id]);
}

function linksIncrementClick(int $id): void {
    getDB()->prepare("UPDATE short_links SET click_count=click_count+1 WHERE id=?")->execute([$id]);
}

function linksValidateSlug(string $slug): bool {
    return (bool)preg_match('/^[a-z0-9\-]+$/', $slug);
}
