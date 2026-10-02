<?php
/**
 * analytics-db.php — first-party site analytics.
 *
 * Built in-house rather than handed to a third party because of what this site
 * is. Browsing data here is not neutral: it is who read the grievance page, who
 * looked at Health & Safety, who was reading about benefits before taking
 * leave. That is information about members' union activity, and it stays with
 * BVTU.
 *
 * What is deliberately NOT stored:
 *   - IP addresses. Used for one second to derive a hash, never written down.
 *   - User-agent strings. Read to classify device and spot bots, then dropped.
 *   - Member identity. The members area is not tracked at all.
 *
 * Visitors are counted with a hash that rotates every day, so "how many people
 * came" is answerable while "which pages did this person read over the term" is
 * not — the same visitor is a different hash tomorrow, and the salt that made
 * today's hash is gone.
 */
require_once __DIR__ . '/db.php';

/** Raw rows are deleted after this many days; daily totals are kept. */
const AN_RETAIN_DAYS = 90;

function anEnsureTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    /*
     * This one runs on the hot path — every view of every public page — unlike
     * the other *-db.php helpers, which back occasional admin screens. Once the
     * tables exist, a single cheap SELECT replaces three CREATE statements.
     */
    try {
        $db->query("SELECT 1 FROM site_views LIMIT 1");
        $db->query("SELECT 1 FROM site_salt LIMIT 1");
        $db->query("SELECT 1 FROM site_daily LIMIT 1");
        $db->query("SELECT 1 FROM site_clicks LIMIT 1");
        return;
    } catch (\PDOException $e) {
        // A table is missing; fall through and create them.
    }

    $db->exec("CREATE TABLE IF NOT EXISTS site_views (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        path      VARCHAR(255) NOT NULL,
        ref_kind  VARCHAR(12)  NOT NULL DEFAULT 'direct',
        ref_host  VARCHAR(120) NOT NULL DEFAULT '',
        device    VARCHAR(8)   NOT NULL DEFAULT '',
        visitor   CHAR(16)     NOT NULL DEFAULT '',
        viewed_at DATETIME     NOT NULL,
        INDEX idx_viewed (viewed_at),
        INDEX idx_path (path),
        INDEX idx_visitor (visitor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // What survives the 90-day cull: one row per page per day. No visitor
    // column, because a count of people is all that is ever looked at later.
    $db->exec("CREATE TABLE IF NOT EXISTS site_daily (
        day      DATE NOT NULL,
        path     VARCHAR(255) NOT NULL,
        views    INT NOT NULL DEFAULT 0,
        visitors INT NOT NULL DEFAULT 0,
        PRIMARY KEY (day, path)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /*
     * Clicks. No visitor column at all: the question a click answers is "which
     * links do people use", which is a count, and a per-person trail of what
     * someone clicked is both more than is needed and more than should be kept.
     */
    $db->exec("CREATE TABLE IF NOT EXISTS site_clicks (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        path       VARCHAR(255) NOT NULL,
        kind       VARCHAR(8)   NOT NULL DEFAULT 'int',
        target     VARCHAR(255) NOT NULL,
        clicked_at DATETIME     NOT NULL,
        INDEX idx_clicked (clicked_at),
        INDEX idx_kind (kind)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // The daily salt. Yesterday's is overwritten, so yesterday's hashes can no
    // longer be reproduced even with the raw IP in hand.
    $db->exec("CREATE TABLE IF NOT EXISTS site_salt (
        day  DATE PRIMARY KEY,
        salt CHAR(32) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Today's salt, created on first use.
 *
 * Kept in the database rather than derived from a constant so that it is
 * genuinely random each day and genuinely gone afterwards.
 */
function anSalt(): string {
    anEnsureTables();
    $db  = getDB();
    $day = date('Y-m-d');
    $s   = $db->prepare("SELECT salt FROM site_salt WHERE day=?");
    $s->execute([$day]);
    $row = $s->fetch();
    if ($row) return $row['salt'];

    $salt = bin2hex(random_bytes(16));
    // INSERT IGNORE: two requests can race on the first hit of the day, and
    // either salt is fine as long as both end up using the same one.
    $db->prepare("INSERT IGNORE INTO site_salt (day, salt) VALUES (?,?)")->execute([$day, $salt]);

    /*
     * Drop every older salt the moment a new day starts, rather than leaving it
     * to anCull()'s 1-in-500 roll. On a quiet site that roll might not come up
     * for days, and until it does yesterday's salt still sits beside yesterday's
     * rows — enough to reproduce a hash from a guessed address and follow one
     * visitor across days. That is precisely what this design promises not to
     * allow, so it cannot be left to chance.
     */
    $db->prepare("DELETE FROM site_salt WHERE day < ?")->execute([$day]);

    $s->execute([$day]);
    $row = $s->fetch();
    return $row ? $row['salt'] : $salt;
}

/** Non-reversible, non-linkable visitor marker for today only. */
function anVisitorHash(string $ip, string $ua): string {
    return substr(hash('sha256', anSalt() . '|' . $ip . '|' . $ua), 0, 16);
}

/** Crawlers announce themselves; this keeps the obvious ones out of the counts. */
function anIsBot(string $ua): bool {
    if ($ua === '') return true;
    return (bool)preg_match(
        '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|preview|monitor|headless|'
      . 'lighthouse|pingdom|uptime|curl|wget|python-requests|axios|postman/i', $ua);
}

function anDevice(string $ua): string {
    if (preg_match('/iPad|Tablet/i', $ua))                  return 'tablet';
    if (preg_match('/Mobi|Android|iPhone|iPod/i', $ua))      return 'phone';
    return 'desktop';
}

/**
 * Where they came from, as a category rather than a full URL — a referring URL
 * can carry a search query, and search queries are the personal part.
 */
function anReferrer(string $ref, string $selfHost): array {
    if ($ref === '') return ['direct', ''];

    /*
     * js/site.js sends a bare hostname, deliberately — a full referring URL can
     * carry a search query, and the query is the personal part. parse_url()
     * reads a scheme-less string as a path and returns no host at all, so
     * parsing it as a URL classified every single visit as 'direct'. Take the
     * host as given, and still accept a full URL in case anything else calls
     * this.
     */
    $host = strtolower(trim($ref));
    if (strpos($host, '//') !== false) {
        $host = strtolower((string)parse_url($ref, PHP_URL_HOST));
    }
    // Anything not legal in a hostname is dropped, then leading and trailing
    // dots and hyphens — legal inside a name, never at its ends — so a crafted
    // referrer cannot leave punctuation sitting in the reports.
    $host = trim(preg_replace('/[^a-z0-9.\-]/', '', $host), '.-');
    if ($host === '' || strpos($host, '.') === false) return ['direct', ''];
    if ($host === strtolower($selfHost) || $host === 'www.' . strtolower($selfHost)) {
        return ['internal', ''];
    }
    if (preg_match('/google|bing|duckduckgo|yahoo|ecosia|brave/i', $host))        return ['search', $host];
    if (preg_match('/facebook|instagram|twitter|x\.com|linkedin|reddit|t\.co/i', $host)) return ['social', $host];
    return ['other', $host];
}

/** One page view. Returns false when the hit was dropped, with no row written. */
function anRecordView(string $path, string $ref, string $ip, string $ua, string $selfHost): bool {
    if (anIsBot($ua)) return false;

    $path = '/' . ltrim(trim($path), '/');
    // mb_*, not the byte versions: the column is utf8mb4, and cutting a
    // multibyte character in half makes MySQL reject the row in strict mode,
    // losing the view with nothing said.
    if (mb_strlen($path) > 255) $path = mb_substr($path, 0, 255);
    // The members area is not tracked. Checked here as well as in the browser,
    // so a stray or hand-made request cannot put a members path in the table.
    if (strpos($path, '/members/') === 0) return false;

    anEnsureTables();
    list($kind, $host) = anReferrer($ref, $selfHost);

    getDB()->prepare(
        "INSERT INTO site_views (path, ref_kind, ref_host, device, visitor, viewed_at)
         VALUES (?,?,?,?,?,?)"
    )->execute([
        $path, $kind, substr($host, 0, 120), anDevice($ua),
        anVisitorHash($ip, $ua),
        // Truncated to the hour: enough to see when traffic comes, not enough
        // to line one person's visits up against anything else.
        date('Y-m-d H:00:00'),
    ]);
    return true;
}

/** Allowed click kinds. Anything else is dropped rather than stored as junk. */
const AN_CLICK_KINDS = ['int', 'out', 'doc', 'go'];

/**
 * One click. $target is already reduced by the browser to a path or a
 * host + path — never a full URL with a query string.
 */
function anRecordClick(string $path, string $kind, string $target, string $ua): bool {
    if (anIsBot($ua)) return false;
    if (!in_array($kind, AN_CLICK_KINDS, true)) return false;

    $target = trim($target);
    if ($target === '') return false;
    // Same reasoning as the page path: the column is utf8mb4, so cut by
    // characters, and strip anything that has no business in a link target.
    $target = preg_replace('/[^\x20-\x7E]/', '', $target);
    if (mb_strlen($target) > 255) $target = mb_substr($target, 0, 255);

    $path = '/' . ltrim(trim($path), '/');
    if (strpos($path, '/members/') === 0) return false;
    if (mb_strlen($path) > 255) $path = mb_substr($path, 0, 255);

    anEnsureTables();
    getDB()->prepare(
        "INSERT INTO site_clicks (path, kind, target, clicked_at) VALUES (?,?,?,?)"
    )->execute([$path, $kind, $target, date('Y-m-d H:00:00')]);
    return true;
}

/**
 * Roll days older than the retention window into site_daily and delete the raw
 * rows. Called opportunistically rather than from cron, which shared hosting
 * does not always offer.
 */
function anCull(): void {
    anEnsureTables();
    $db     = getDB();
    $cutoff = date('Y-m-d', strtotime('-' . AN_RETAIN_DAYS . ' days'));

    /*
     * One whole day at a time, aggregated and then deleted before the next is
     * touched.
     *
     * Aggregating everything older than the cutoff while deleting only a capped
     * number of rows loses data: the part-deleted day is re-counted on the next
     * run and its correct total in site_daily is overwritten with a smaller
     * one. site_daily is the table meant to outlive the raw rows, so a wrong
     * number there is permanent. Finishing a day before starting the next means
     * each total is written once, from the complete day.
     *
     * Comparisons are against viewed_at directly rather than DATE(viewed_at):
     * wrapping the column in a function stops MySQL using idx_viewed.
     */
    $oldest = $db->prepare("SELECT MIN(viewed_at) AS m FROM site_views WHERE viewed_at < ?");
    $agg    = $db->prepare(
        "INSERT INTO site_daily (day, path, views, visitors)
         SELECT DATE(viewed_at), path, COUNT(*), COUNT(DISTINCT visitor)
           FROM site_views WHERE viewed_at >= ? AND viewed_at < ?
          GROUP BY DATE(viewed_at), path
         ON DUPLICATE KEY UPDATE views=VALUES(views), visitors=VALUES(visitors)");
    $del    = $db->prepare("DELETE FROM site_views WHERE viewed_at >= ? AND viewed_at < ?");

    // A handful of days per call: enough to clear a backlog over a few visits,
    // few enough that no single request runs long.
    for ($i = 0; $i < 3; $i++) {
        $oldest->execute([$cutoff . ' 00:00:00']);
        $row = $oldest->fetch();
        if (!$row || empty($row['m'])) break;

        $dayStart = date('Y-m-d 00:00:00', strtotime($row['m']));
        $dayEnd   = date('Y-m-d 00:00:00', strtotime($row['m'] . ' +1 day'));
        $agg->execute([$dayStart, $dayEnd]);
        $del->execute([$dayStart, $dayEnd]);
    }

    // Clicks are counts already, with nothing to roll up, so they are simply
    // dropped past the same window rather than aggregated.
    $db->prepare("DELETE FROM site_clicks WHERE clicked_at < ? LIMIT 5000")
       ->execute([$cutoff . ' 00:00:00']);

    $db->prepare("DELETE FROM site_salt WHERE day < ?")->execute([date('Y-m-d')]);
}

// ── Reporting ────────────────────────────────────────────────────────────────
//
// Every query here reads site_views, which only covers the retention window.
// Days older than that live in site_daily with page totals only, so the
// dashboard says plainly which figures a long range can and cannot include
// rather than quietly reporting a smaller number.

/** Views and visitors per day, oldest first. */
function anDaily(int $days): array {
    anEnsureTables();
    $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $q = getDB()->prepare(
        "SELECT DATE(viewed_at) AS day, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors
           FROM site_views WHERE viewed_at >= ?
          GROUP BY DATE(viewed_at) ORDER BY day"
    );
    $q->execute([$from]);
    return $q->fetchAll();
}

/** Totals for the window: views, people, and the busiest hour of the day. */
function anTotals(int $days): array {
    anEnsureTables();
    $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $q = getDB()->prepare(
        "SELECT COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors FROM site_views WHERE viewed_at >= ?"
    );
    $q->execute([$from]);
    $t = $q->fetch() ?: ['views' => 0, 'visitors' => 0];

    $h = getDB()->prepare(
        "SELECT HOUR(viewed_at) AS hr, COUNT(*) AS n FROM site_views WHERE viewed_at >= ?
          GROUP BY HOUR(viewed_at) ORDER BY n DESC LIMIT 1"
    );
    $h->execute([$from]);
    $row = $h->fetch();
    $t['busiest_hour'] = $row ? (int)$row['hr'] : null;
    return $t;
}

function anTopPages(int $days, int $limit = 15): array {
    anEnsureTables();
    $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $q = getDB()->prepare(
        "SELECT path, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors
           FROM site_views WHERE viewed_at >= ?
          GROUP BY path ORDER BY views DESC LIMIT " . (int)$limit
    );
    $q->execute([$from]);
    return $q->fetchAll();
}

/** Grouped by how someone arrived, with the hosts inside each group. */
function anReferrers(int $days): array {
    anEnsureTables();
    $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $q = getDB()->prepare(
        "SELECT ref_kind, ref_host, COUNT(*) AS n FROM site_views WHERE viewed_at >= ?
          GROUP BY ref_kind, ref_host ORDER BY n DESC"
    );
    $q->execute([$from]);
    $out = [];
    foreach ($q->fetchAll() as $r) {
        $k = $r['ref_kind'];
        if (!isset($out[$k])) $out[$k] = ['total' => 0, 'hosts' => []];
        $out[$k]['total'] += (int)$r['n'];
        if ($r['ref_host'] !== '') $out[$k]['hosts'][$r['ref_host']] = (int)$r['n'];
    }
    uasort($out, function ($a, $b) { return $b['total'] <=> $a['total']; });
    return $out;
}

function anDevices(int $days): array {
    anEnsureTables();
    $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $q = getDB()->prepare(
        "SELECT device, COUNT(*) AS n FROM site_views WHERE viewed_at >= ?
          GROUP BY device ORDER BY n DESC"
    );
    $q->execute([$from]);
    $out = [];
    foreach ($q->fetchAll() as $r) $out[$r['device'] ?: 'unknown'] = (int)$r['n'];
    return $out;
}

/** Most-used links of one kind: 'doc', 'out', 'go' or 'int'. */
function anTopClicks(int $days, string $kind, int $limit = 12): array {
    anEnsureTables();
    if (!in_array($kind, AN_CLICK_KINDS, true)) return [];
    $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
    $q = getDB()->prepare(
        "SELECT target, COUNT(*) AS n FROM site_clicks
          WHERE clicked_at >= ? AND kind = ?
          GROUP BY target ORDER BY n DESC LIMIT " . (int)$limit
    );
    $q->execute([$from, $kind]);
    return $q->fetchAll();
}

/** The oldest view still held, so the dashboard can say how far back it sees. */
function anOldestView(): ?string {
    anEnsureTables();
    $r = getDB()->query("SELECT MIN(viewed_at) AS m FROM site_views")->fetch();
    return ($r && $r['m']) ? $r['m'] : null;
}
