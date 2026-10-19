<?php
/**
 * event-state.php — which seminars are coming up, readable without a database.
 *
 * index.php has never needed a database and it should stay that way: getDB()
 * ends the request with die() when it cannot connect, so a database hiccup
 * would take the front page down rather than degrade it. Asking "what is on?"
 * is not worth that risk.
 *
 * So the seminar tools write the answer to a small file whenever anything
 * changes, and the front page reads the file. It holds nothing that is not
 * already on the public seminar page.
 *
 * Deliberately requires nothing. Include it from anywhere.
 */

function evStateFile(): string { return __DIR__ . '/event-state.json'; }

/**
 * Our timezone, carried explicitly.
 *
 * This file is included by index.php, which sets no timezone — that call lives
 * in event-db.php, and this one deliberately requires nothing. Stored times are
 * local wall-clock strings, so on a host defaulting to UTC a comparison against
 * time() is out by the offset and an evening seminar drops off the front page
 * in the early afternoon. Passing the zone beats setting it globally from an
 * include nobody expects to change the request.
 */
function evStateZone(): DateTimeZone {
    static $z = null;
    if ($z === null) $z = new DateTimeZone('America/Vancouver');
    return $z;
}

/** One stored 'Y-m-d H:i:s' as a moment, or null. */
function evStateMoment(string $v): ?DateTime {
    $v = trim($v);
    if ($v === '') return null;
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $v, evStateZone());
    if ($d instanceof DateTime) return $d;
    try {
        return new DateTime($v, evStateZone());
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * The published seminars, soonest first.
 *
 * Past ones are dropped here rather than at write time, so a seminar falls off
 * the front page the morning after it happens without anyone having to save
 * anything.
 */
function evStateUpcoming(int $limit = 3): array {
    $f = evStateFile();
    if (!is_file($f)) return [];
    $raw = @file_get_contents($f);
    if ($raw === false) return [];
    $d = json_decode($raw, true);
    if (!is_array($d) || !isset($d['events']) || !is_array($d['events'])) return [];

    $now = time();
    $out = [];
    foreach ($d['events'] as $e) {
        if (!is_array($e) || empty($e['slug']) || empty($e['title'])) continue;
        // An event with no date stays listed: "date to be confirmed" is a real
        // state here, and dropping it would hide a seminar people can sign up
        // for.
        // ?? falls back on null, and these are written as strings — a seminar
        // with no end time stores "", which is present, so the coalesce kept
        // every past event that had only a start.
        // Absent means yes, matching evTakesSignups(): an older cache written
        // before this column existed should show its events, not hide them.
        if (array_key_exists('signup', $e) && !$e['signup']) continue;
        // And only training. Ticking "people can sign up" on an exec meeting
        // should not put it on the front page under "SURTs and seminars".
        if (array_key_exists('kind', $e) && $e['kind'] !== '' && $e['kind'] !== 'seminar') continue;

        $ends = trim((string)($e['ends_at'] ?? ''));
        if ($ends === '') $ends = trim((string)($e['starts_at'] ?? ''));
        $m = $ends === '' ? null : evStateMoment($ends);
        if ($m !== null && $m->getTimestamp() < $now) continue;
        $out[] = $e;
        if (count($out) >= $limit) break;
    }
    return $out;
}

/**
 * Rewrite the file from the database. Called from the seminar tools whenever
 * one is created, edited, published, closed or deleted.
 *
 * Failure is logged and swallowed: a cache that could not be written must not
 * stop an organiser saving their seminar.
 */
function evStateRebuild(): bool {
    require_once __DIR__ . '/db.php';
    try {
        // SELECT * rather than naming signup: the column is added best-effort,
        // and a host without it would throw here and leave the front page with
        // no events at all.
        // Upcoming, not simply the first twenty. Ordering by start and taking
        // twenty kept the OLDEST open events, so once twenty meetings had been
        // and gone the box would filter them all out as past and show nothing
        // — with real seminars sitting open behind them.
        $rows = getDB()->query(
            "SELECT *
             FROM ev_events
             WHERE status = 'open'
               AND (starts_at IS NULL
                    OR COALESCE(ends_at, starts_at) >= DATE_SUB(NOW(), INTERVAL 1 DAY))
             ORDER BY (starts_at IS NULL) ASC, starts_at ASC
             LIMIT 200"
        )->fetchAll();
    } catch (\Throwable $e) {
        error_log('evStateRebuild: ' . $e->getMessage());
        return false;
    }

    $events = [];
    foreach ($rows as $r) {
        // The box is headed "SURTs you can sign up for", so only those are
        // kept. Done here, not in the WHERE clause: naming a column that a
        // failed ALTER never created would throw and leave the front page
        // with nothing at all.
        $kind = (string)($r['kind'] ?? 'seminar');
        if ($kind !== '' && $kind !== 'seminar') continue;
        if (array_key_exists('signup', $r) && empty($r['signup'])) continue;
        if (count($events) >= 20) break;

        $events[] = [
            'slug'      => (string)$r['slug'],
            'title'     => (string)$r['title'],
            'summary'   => (string)($r['summary'] ?? ''),
            'starts_at' => (string)($r['starts_at'] ?? ''),
            'ends_at'   => (string)($r['ends_at'] ?? ''),
            'location'  => (string)($r['location'] ?? ''),
            // Carried so the home-page box can keep its promise: it is headed
            // "SURTs you can sign up for", and a grant deadline under that is
            // a claim that was never true.
            'signup'    => array_key_exists('signup', $r) ? (int)!empty($r['signup']) : 1,
            'kind'       => (string)($r['kind'] ?? 'seminar'),
        ];
    }

    $json = json_encode(['written_at' => date('c'), 'events' => $events],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;

    // Written whole and moved into place, so a front page reading it mid-write
    // never sees half a file.
    $tmp = evStateFile() . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json) === false) {
        error_log('evStateRebuild: could not write ' . $tmp);
        return false;
    }
    if (!@rename($tmp, evStateFile())) {
        @unlink($tmp);
        error_log('evStateRebuild: could not move the cache into place');
        return false;
    }
    return true;
}

/** "Thursday 12 November, 9 am" — as much of it as we know. */
function evStateWhen(array $e): string {
    $m = evStateMoment((string)($e['starts_at'] ?? ''));
    if ($m === null) return 'Date to be confirmed';
    $clock = ($m->format('i') === '00' ? $m->format('g') : $m->format('g:i')) . $m->format(' a');
    return $m->format('l j F') . ', ' . $clock;
}
