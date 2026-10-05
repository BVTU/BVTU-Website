<?php
/**
 * forum-state.php — whether there is a forum recording to watch, readable
 * without a database.
 *
 * index.php has never needed a database, and it should stay that way: getDB()
 * ends the request with die() when it cannot connect, so a database hiccup
 * would take the front page down rather than degrade it. Asking "is the
 * recording published?" is not worth that risk.
 *
 * So the editor writes the answer to a small file whenever it saves, and public
 * pages read the file. It holds nothing that is not already public — a title, a
 * date, and the YouTube id that is in the page markup anyway.
 *
 * Deliberately requires nothing. Include it from anywhere.
 */

function forumStatePath(): string { return __DIR__ . '/forum-state.json'; }

/**
 * ['live' => bool, 'title' => string, 'youtube_id' => string,
 *  'event_date' => string, 'chapters' => int] — or null if never written.
 *
 * Any problem reading it returns null, which every caller treats as "no
 * recording yet". A missing or corrupt cache should hide a button, never break
 * a page.
 */
function forumState(): ?array {
    $f = forumStatePath();
    if (!is_file($f)) return null;
    $raw = @file_get_contents($f);
    if ($raw === false) return null;
    $d = json_decode($raw, true);
    if (!is_array($d) || empty($d['live'])) return null;
    return [
        'live'       => true,
        'title'      => (string)($d['title'] ?? 'All-Candidates Forum'),
        'youtube_id' => (string)($d['youtube_id'] ?? ''),
        'event_date' => (string)($d['event_date'] ?? ''),
        'chapters'   => (int)($d['chapters'] ?? 0),
    ];
}

/**
 * Write the cache. Returns false if it could not be written.
 *
 * Called from the editor and, when it notices a mismatch, from forum.php — so
 * the file repairs itself after a deploy without anyone opening the editor.
 */
function forumWriteState(array $video, int $chapterCount): bool {
    $live = (int)($video['published'] ?? 0) === 1 && (string)($video['youtube_id'] ?? '') !== '';
    $json = json_encode([
        'live'       => $live,
        'title'      => (string)($video['title'] ?? ''),
        'youtube_id' => (string)($video['youtube_id'] ?? ''),
        'event_date' => (string)($video['event_date'] ?? ''),
        'chapters'   => $chapterCount,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    // json_encode returns false on failure, which would write an empty file over
    // a good one — and file_put_contents would return 0, not false, so even the
    // error check would pass.
    if (!is_string($json) || $json === '') {
        error_log('forumWriteState: could not encode state');
        return false;
    }

    /*
     * A unique temporary name per writer. A single fixed .tmp path is not
     * atomic: two requests writing at once can rename a half-written file into
     * place, and a truncated cache reads as "no recording" and takes the band
     * off the homepage.
     */
    $tmp = forumStatePath() . '.' . getmypid() . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $bytes = @file_put_contents($tmp, $json, LOCK_EX);
    if ($bytes === false || $bytes !== strlen($json)) {
        @unlink($tmp);
        error_log('forumWriteState: could not write ' . $tmp);
        return false;
    }
    if (!@rename($tmp, forumStatePath())) {
        @unlink($tmp);
        // Worth saying out loud: a stale cache means the homepage keeps
        // advertising a recording that has been unpublished, or hides one that
        // has not.
        error_log('forumWriteState: could not move state into place');
        return false;
    }
    return true;
}

/** True when the cache already says exactly this — so callers can skip writing. */
function forumStateMatches(array $video, int $chapterCount): bool {
    $live  = (int)($video['published'] ?? 0) === 1 && (string)($video['youtube_id'] ?? '') !== '';
    $have  = forumState();
    if (!$live) return $have === null;
    return $have !== null
        && $have['youtube_id'] === (string)($video['youtube_id'] ?? '')
        && $have['title']      === (string)($video['title'] ?? '')
        && $have['event_date'] === (string)($video['event_date'] ?? '')
        && $have['chapters']   === $chapterCount;
}
