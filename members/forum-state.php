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
        'thumb'      => (string)($d['thumb'] ?? ''),
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
        'thumb'      => $live ? forumThumbUrl((string)($video['youtube_id'] ?? '')) : '',
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
        && $have['chapters']   === $chapterCount
        && $have['thumb']      === forumThumbUrl((string)($video['youtube_id'] ?? ''));
}

// ── Thumbnail ────────────────────────────────────────────────────────────────
//
// Fetched once, server side, and served from bvtu.ca.
//
// YouTube's own thumbnail lives on i.ytimg.com, which is Google. Pointing an
// <img> at it would contact Google the moment the page loaded — exactly what
// the click-to-play player exists to avoid, and it would make privacy.php
// untrue again. Copying the file here keeps the page free of Google until
// somebody presses play.

function forumThumbRel(string $id): string { return 'images/forum-thumb-' . $id . '.jpg'; }
function forumThumbAbs(string $id): string { return dirname(__DIR__) . '/' . forumThumbRel($id); }

/** The cached thumbnail's web path, or '' if there is not one. */
function forumThumbUrl(string $id): string {
    if ($id === '' || !preg_match('~^[A-Za-z0-9_-]{11}$~', $id)) return '';
    $f = forumThumbAbs($id);
    // Size as well as existence: a truncated download passes is_file() and then
    // renders as an empty box with no fallback, which looks broken rather than
    // plain. The same 2 KB floor forumEnsureThumb() uses to spot a 404 stub.
    return (is_file($f) && filesize($f) > 2048) ? forumThumbRel($id) : '';
}

/**
 * Copy the thumbnail here if it is not already. Returns true when one is in
 * place afterwards.
 *
 * A private video has no public thumbnail — every size answers 404 — so this
 * quietly does nothing and the page falls back to its plain play panel. It is
 * retried on later visits, so switching the video to Unlisted is enough to make
 * the picture appear without touching anything here.
 */
function forumEnsureThumb(string $id): bool {
    if ($id === '' || !preg_match('~^[A-Za-z0-9_-]{11}$~', $id)) return false;
    $dest = forumThumbAbs($id);
    if (is_file($dest) && filesize($dest) > 2048) return true;

    $dir = dirname($dest);
    if (!is_dir($dir) || !is_writable($dir)) return false;

    /*
     * Do not keep trying.
     *
     * A private video has no public thumbnail, and this is called from a public
     * page: without a cooling-off period every visitor would wait through three
     * failed fetches — up to twelve seconds of blank page — and write a line to
     * the error log each time. One attempt every six hours is enough to pick
     * the picture up shortly after the video is made Unlisted.
     */
    $miss = $dest . '.miss';
    if (is_file($miss) && (time() - filemtime($miss)) < 21600) return false;

    foreach (['maxresdefault', 'sddefault', 'hqdefault'] as $size) {
        $url = 'https://i.ytimg.com/vi/' . $id . '/' . $size . '.jpg';
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false || strlen($data) < 4096) continue;   // 404s return a tiny placeholder

        // Confirm it really is a JPEG before writing it into the web root.
        $info = @getimagesizefromstring($data);
        if (!$info || ($info[2] ?? 0) !== IMAGETYPE_JPEG) continue;

        $tmp = $dest . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $data) === strlen($data) && @rename($tmp, $dest)) {
            @unlink($miss);
            return true;
        }
        @unlink($tmp);
    }

    // Remember the failure so the next visitor is not made to wait for it too.
    @file_put_contents($miss, (string)time());
    error_log('forumEnsureThumb: no public thumbnail for ' . $id
            . ' — is the video Private rather than Unlisted?');
    return false;
}
