<?php
// ============================================================
//  BVTU Newsletter Archive — DB layer + Mailchimp sync
// ============================================================
require_once __DIR__ . '/db.php';

// ── Mailchimp credentials ─────────────────────────────────────
// Define MC_API_KEY in members/config.php — never commit the key itself.
// Example: define('MC_API_KEY', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-us9');
if (!defined('MC_API_KEY')) define('MC_API_KEY', '');
define('NL_MC_BASE', 'https://us9.api.mailchimp.com/3.0');

// ── Table setup ───────────────────────────────────────────────
function nlEnsureTables(): void {
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS newsletters (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id  VARCHAR(50)  NOT NULL UNIQUE,
        subject      VARCHAR(500) NOT NULL DEFAULT '',
        preview_text TEXT,
        send_date    DATETIME,
        html_content LONGTEXT,
        content_text TEXT,
        archive_url  VARCHAR(500),
        emails_sent  INT DEFAULT 0,
        synced_at    DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // FULLTEXT index — wrapped in try/catch so it's safe to re-run
    try {
        $db->exec("ALTER TABLE newsletters
            ADD FULLTEXT KEY ft_nl (subject, preview_text, content_text)");
    } catch (PDOException $e) { /* already exists */ }
}

// ── Mailchimp API GET ─────────────────────────────────────────
function nlApiGet(string $path): ?array {
    $ch = curl_init(NL_MC_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => 'anystring:' . MC_API_KEY,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) { error_log("nlApiGet {$path} — cURL error: {$err}"); return null; }
    if ($code !== 200) { error_log("nlApiGet {$path} — HTTP {$code}"); return null; }
    return json_decode($body, true);
}

// ── Sync campaigns from Mailchimp ─────────────────────────────
// $force = true re-fetches content for already-synced campaigns
function nlSyncCampaigns(bool $force = false): array {
    nlEnsureTables();
    $db = getDB();

    $added   = 0;
    $updated = 0;
    $skipped = 0;
    $errors  = [];

    // Paginate through ALL sent campaigns
    $all    = [];
    $offset = 0;
    do {
        $data = nlApiGet("/campaigns?status=sent&count=100&offset={$offset}"
                       . "&sort_field=send_time&sort_dir=DESC");
        if (!$data || empty($data['campaigns'])) break;
        $all    = array_merge($all, $data['campaigns']);
        $total  = (int)($data['total_items'] ?? count($all));
        $offset += count($data['campaigns']);
    } while (count($all) < $total);

    if (empty($all)) {
        $errors[] = 'Could not fetch campaigns from Mailchimp — check the API key.';
        return compact('added', 'updated', 'skipped', 'errors');
    }

    foreach ($all as $c) {
        $cid        = $c['id'];
        $subject    = $c['settings']['subject_line'] ?? '(No subject)';
        $preview    = $c['settings']['preview_text'] ?? '';
        $sendDate   = !empty($c['send_time'])
                      ? date('Y-m-d H:i:s', strtotime($c['send_time']))
                      : null;
        $archiveUrl = $c['archive_url'] ?? null;
        $emailsSent = (int)($c['emails_sent'] ?? 0);

        // Check existing
        $chk = $db->prepare("SELECT id FROM newsletters WHERE campaign_id = ?");
        $chk->execute([$cid]);
        $exists = $chk->fetch();

        if ($exists && !$force) {
            $skipped++;
            continue;
        }

        // Fetch full HTML content (separate API call per campaign)
        $content = nlApiGet("/campaigns/{$cid}/content");
        if (!$content) {
            $errors[] = "Could not fetch content for: " . htmlspecialchars($subject);
            continue;
        }

        $html        = $content['html'] ?? '';
        // Strip tags + normalise whitespace for full-text search (cap at 8 000 chars)
        $contentText = mb_substr(
            preg_replace('/\s+/', ' ', strip_tags($html)),
            0, 8000
        );

        $now = date('Y-m-d H:i:s');

        if ($exists) {
            $db->prepare("UPDATE newsletters SET
                subject=?, preview_text=?, send_date=?, html_content=?,
                content_text=?, archive_url=?, emails_sent=?, synced_at=?
                WHERE campaign_id=?")
               ->execute([$subject, $preview, $sendDate, $html,
                          $contentText, $archiveUrl, $emailsSent, $now, $cid]);
            $updated++;
        } else {
            $db->prepare("INSERT INTO newsletters
                (campaign_id, subject, preview_text, send_date, html_content,
                 content_text, archive_url, emails_sent, synced_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$cid, $subject, $preview, $sendDate, $html,
                          $contentText, $archiveUrl, $emailsSent, $now]);
            $added++;
        }
    }

    return compact('added', 'updated', 'skipped', 'errors');
}

// ── FULLTEXT availability ─────────────────────────────────────
function nlHasFulltext(): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $r   = getDB()->query("SHOW INDEX FROM newsletters WHERE Key_name = 'ft_nl'")->fetchAll();
        $has = !empty($r);
    } catch (PDOException $e) { $has = false; }
    return $has;
}

// ── Fetch newsletters (browse / search) ──────────────────────
function nlGetNewsletters(string $search = '', int $offset = 0, int $limit = 15): array {
    nlEnsureTables();
    $db = getDB();

    if ($search !== '' && nlHasFulltext()) {
        $q = $db->prepare(
            "SELECT id, campaign_id, subject, preview_text, send_date, archive_url, emails_sent
             FROM newsletters
             WHERE MATCH(subject, preview_text, content_text) AGAINST(? IN BOOLEAN MODE)
             ORDER BY send_date DESC LIMIT ? OFFSET ?");
        $q->execute([$search . '*', $limit, $offset]);
    } elseif ($search !== '') {
        $like = '%' . $search . '%';
        $q = $db->prepare(
            "SELECT id, campaign_id, subject, preview_text, send_date, archive_url, emails_sent
             FROM newsletters
             WHERE subject LIKE ? OR preview_text LIKE ? OR content_text LIKE ?
             ORDER BY send_date DESC LIMIT ? OFFSET ?");
        $q->execute([$like, $like, $like, $limit, $offset]);
    } else {
        $q = $db->prepare(
            "SELECT id, campaign_id, subject, preview_text, send_date, archive_url, emails_sent
             FROM newsletters ORDER BY send_date DESC LIMIT ? OFFSET ?");
        $q->execute([$limit, $offset]);
    }
    return $q->fetchAll();
}

function nlCountNewsletters(string $search = ''): int {
    nlEnsureTables();
    $db = getDB();

    if ($search !== '' && nlHasFulltext()) {
        $q = $db->prepare(
            "SELECT COUNT(*) FROM newsletters
             WHERE MATCH(subject, preview_text, content_text) AGAINST(? IN BOOLEAN MODE)");
        $q->execute([$search . '*']);
    } elseif ($search !== '') {
        $like = '%' . $search . '%';
        $q = $db->prepare(
            "SELECT COUNT(*) FROM newsletters
             WHERE subject LIKE ? OR preview_text LIKE ? OR content_text LIKE ?");
        $q->execute([$like, $like, $like]);
    } else {
        $q = $db->query("SELECT COUNT(*) FROM newsletters");
    }
    return (int)$q->fetchColumn();
}

function nlGetNewsletter(int $id): ?array {
    nlEnsureTables();
    $q = getDB()->prepare("SELECT * FROM newsletters WHERE id = ?");
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function nlDeleteNewsletter(int $id): void {
    getDB()->prepare("DELETE FROM newsletters WHERE id = ?")->execute([$id]);
}

function nlGetLastSync(): ?string {
    try {
        $r = getDB()->query("SELECT MAX(synced_at) FROM newsletters")->fetchColumn();
        return $r ?: null;
    } catch (PDOException $e) { return null; }
}

function nlGetYears(): array {
    try {
        $r = getDB()->query(
            "SELECT DISTINCT YEAR(send_date) y FROM newsletters
             WHERE send_date IS NOT NULL ORDER BY y DESC"
        )->fetchAll(PDO::FETCH_COLUMN);
        return $r;
    } catch (PDOException $e) { return []; }
}
