<?php
/**
 * forum-db.php — the all-candidates forum recording and its chapters.
 *
 * The video itself lives on YouTube, not here. A three-hour 1.8 GB recording
 * served from shared hosting would cost 1.8 GB of transfer per viewer, arrive
 * as a single fixed quality with no adaptation for rural mobile, and carry no
 * captions. What this site is good at is the part around the video: saying what
 * is in it and letting someone jump to the bit they care about.
 *
 * Chapters are the point. Nobody watches a three-hour forum end to end; they
 * come to hear one candidate, or one question.
 */
require_once __DIR__ . '/db.php';

function forumEnsureTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = getDB();

    /*
     * Once the tables exist, two cheap SELECTs replace two CREATE statements.
     * forum.php is a public page, so this runs for anonymous visitors — and on
     * a host whose database user has no CREATE grant, issuing DDL on every view
     * would turn the page into a 500.
     */
    try {
        $db->query("SELECT 1 FROM forum_videos LIMIT 1");
        $db->query("SELECT 1 FROM forum_chapters LIMIT 1");
        return;
    } catch (\PDOException $e) {
        // Missing; fall through and create them.
    }

    $db->exec("CREATE TABLE IF NOT EXISTS forum_videos (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        year        INT NOT NULL,
        title       VARCHAR(255) NOT NULL DEFAULT '',
        youtube_id  VARCHAR(20)  NOT NULL DEFAULT '',
        event_date  DATE DEFAULT NULL,
        blurb       TEXT,
        published   TINYINT(1) NOT NULL DEFAULT 0,
        updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_year (year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seconds rather than a formatted string: the page needs a number to build
    // a jump link, and a stored "1:04:12" only has to be parsed back again.
    $db->exec("CREATE TABLE IF NOT EXISTS forum_chapters (
        id       INT AUTO_INCREMENT PRIMARY KEY,
        video_id INT NOT NULL,
        seconds  INT NOT NULL DEFAULT 0,
        label    VARCHAR(255) NOT NULL DEFAULT '',
        INDEX idx_video (video_id, seconds)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Pull the eleven-character video id out of whatever was pasted.
 *
 * People paste a watch URL, a share link, an embed snippet or just the id, and
 * being fussy about which would only mean telling the president they typed it
 * wrong when they did not.
 */
function forumVideoId(string $input): string {
    $input = trim($input);
    if ($input === '') return '';
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $input)) return $input;
    if (preg_match('~(?:youtu\.be/|v=|/embed/|/shorts/|/live/)([A-Za-z0-9_-]{11})~', $input, $m)) {
        return $m[1];
    }
    return '';
}

/** "1:04:12" or "4:12" from a number of seconds. */
function forumStamp(int $s): string {
    $s = max(0, $s);
    $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60); $sec = $s % 60;
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $sec) : sprintf('%d:%02d', $m, $sec);
}

/** Seconds from "1:04:12", "4:12" or plain seconds. Null when unreadable. */
function forumSeconds(string $t): ?int {
    $t = trim($t);
    if ($t === '') return null;
    if (ctype_digit($t)) return (int)$t;
    if (!preg_match('~^(?:(\d+):)?(\d{1,2}):(\d{1,2})$~', $t, $m)) return null;
    $h = (int)($m[1] ?? 0); $min = (int)$m[2]; $sec = (int)$m[3];
    if ($min > 59 || $sec > 59) return null;
    return $h * 3600 + $min * 60 + $sec;
}

function forumCurrentYear(): int {
    $m = (int)date('n');
    return $m >= 9 ? (int)date('Y') : (int)date('Y') - 1;
}

/**
 * The recording to show the public: the most recently published one.
 *
 * Deliberately not keyed by the current school year. That flips on 1 September,
 * and a forum recorded in October would vanish from the site overnight with no
 * way left to reach it — the year is for organising the records, not for
 * deciding what a visitor sees.
 *
 * Read-only. This runs on public pages, and a page view should not be creating
 * rows.
 */
function forumLatest(): ?array {
    /*
     * Ordered by year and then by row, not by the recording date — that field is
     * optional, and sorting on it meant publishing a new recording without one
     * left the previous year's on the page while the editor reported success.
     *
     * Wrapped because this is a public page: if the tables are not there yet, or
     * the database is having a bad day, the right answer is "no recording to
     * show", not a 500 on a page somebody reached from a flyer.
     */
    try {
        forumEnsureTables();
        $r = getDB()->query(
            "SELECT * FROM forum_videos
              WHERE published = 1 AND youtube_id <> ''
              ORDER BY year DESC, id DESC LIMIT 1"
        )->fetch();
        return $r ?: null;
    } catch (\Throwable $e) {
        error_log('forumLatest: ' . $e->getMessage());
        return null;
    }
}

/** Years that have a row, newest first, for the editor's year switcher. */
function forumYears(): array {
    forumEnsureTables();
    $rows = getDB()->query("SELECT year FROM forum_videos ORDER BY year DESC")->fetchAll();
    return array_map(function ($r) { return (int)$r['year']; }, $rows);
}

/** The year the editor should open on: whatever the public is seeing, else now. */
function forumEditYear(): int {
    $live = forumLatest();
    return $live ? (int)$live['year'] : forumCurrentYear();
}

/**
 * The row the editor works on, created if this year has none.
 *
 * The write lives here rather than in forumLatest() because only the admin
 * screen should be able to make one. UNIQUE on year plus INSERT IGNORE means
 * two tabs open at once cannot produce two rows for the same year.
 */
function forumGetForEdit(int $year): array {
    forumEnsureTables();
    $db = getDB();
    $s  = $db->prepare("SELECT * FROM forum_videos WHERE year=? LIMIT 1");
    $s->execute([$year]);
    $row = $s->fetch();
    if (!$row) {
        $db->prepare("INSERT IGNORE INTO forum_videos (year, title) VALUES (?,?)")
           ->execute([$year, 'All-Candidates Forum']);
        $s->execute([$year]);
        $row = $s->fetch();
    }
    return $row ?: ['id' => 0, 'year' => $year, 'title' => 'All-Candidates Forum',
                    'youtube_id' => '', 'event_date' => null, 'blurb' => '', 'published' => 0];
}

function forumSave(int $id, string $title, string $youtubeId, string $date,
                   string $blurb, bool $published): void {
    forumEnsureTables();
    getDB()->prepare(
        "UPDATE forum_videos SET title=?, youtube_id=?, event_date=?, blurb=?, published=? WHERE id=?"
    )->execute([
        mb_substr(trim($title), 0, 255), $youtubeId,
        $date !== '' ? $date : null, $blurb, $published ? 1 : 0, $id,
    ]);
}

function forumChapters(int $videoId): array {
    forumEnsureTables();
    $s = getDB()->prepare("SELECT * FROM forum_chapters WHERE video_id=? ORDER BY seconds, id");
    $s->execute([$videoId]);
    return $s->fetchAll();
}

function forumAddChapter(int $videoId, int $seconds, string $label): void {
    forumEnsureTables();
    getDB()->prepare("INSERT INTO forum_chapters (video_id, seconds, label) VALUES (?,?,?)")
           ->execute([$videoId, max(0, $seconds), mb_substr(trim($label), 0, 255)]);
}

/** True when a row was actually changed — the video_id guard can reject one. */
function forumUpdateChapter(int $id, int $videoId, int $seconds, string $label): bool {
    forumEnsureTables();
    // video_id in the WHERE so an id from another year's video cannot be edited.
    $q = getDB()->prepare("UPDATE forum_chapters SET seconds=?, label=? WHERE id=? AND video_id=?");
    $q->execute([max(0, $seconds), mb_substr(trim($label), 0, 255), $id, $videoId]);
    return $q->rowCount() > 0;
}

function forumDeleteChapter(int $id, int $videoId): bool {
    forumEnsureTables();
    $q = getDB()->prepare("DELETE FROM forum_chapters WHERE id=? AND video_id=?");
    $q->execute([$id, $videoId]);
    return $q->rowCount() > 0;
}
