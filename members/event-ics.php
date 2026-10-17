<?php
/**
 * event-ics.php — BVTU events as iCalendar.
 *
 * One open format that Google, Apple, Outlook, Proton and Thunderbird all read,
 * so "add this to my calendar" works without us choosing anybody's product.
 *
 * Deliberately hand-written rather than pulled from a library: the whole format
 * we need is a dozen lines, and this project keeps no composer vendor tree.
 */
require_once __DIR__ . '/event-db.php';

/**
 * Escape a value for an iCalendar text field.
 *
 * Backslash first, or the escapes we add get escaped again. Newlines become a
 * literal \\n, because a real one would end the property and corrupt the file.
 */
function icsText(string $v): string {
    $v = str_replace('\\', '\\\\', $v);
    $v = str_replace([';', ','], ['\;', '\\,'], $v);
    $v = preg_replace("/\r\n|\r|\n/", '\\n', $v);
    return (string)$v;
}

/**
 * Fold a property line to 75 octets, as the spec requires.
 *
 * Folded on bytes rather than characters, then nudged back so a multi-byte
 * character is never cut in half — a split accent arrives as a replacement
 * glyph in somebody's calendar.
 */
function icsFold(string $line): string {
    if (strlen($line) <= 75) return $line;
    $out = '';
    $i   = 0;
    $len = strlen($line);
    $max = 75;
    while ($i < $len) {
        $take = min($max, $len - $i);
        if ($i + $take < $len) {
            // Do not split inside a UTF-8 sequence: continuation bytes are
            // 10xxxxxx, so walk back to the start of the character.
            while ($take > 1 && (ord($line[$i + $take]) & 0xC0) === 0x80) $take--;
        }
        $out .= ($i === 0 ? '' : "\r\n ") . substr($line, $i, $take);
        $i   += $take;
        $max  = 74;   // continuation lines carry a leading space
    }
    return $out;
}

/** Our timezone, carried explicitly rather than taken from the server. */
function icsZone(): DateTimeZone {
    static $z = null;
    if ($z === null) $z = new DateTimeZone('America/Vancouver');
    return $z;
}

/** A stored 'Y-m-d H:i:s' as UTC, which every calendar reads without a VTIMEZONE. */
function icsUtc(?string $v): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $v, icsZone());
    if (!$d instanceof DateTime) {
        try { $d = new DateTime($v, icsZone()); } catch (\Throwable $e) { return null; }
    }
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
}

/** A stable identity for an event, so an update replaces rather than duplicates. */
function icsUid(array $e, string $host): string {
    // Our configured address, not the one this request came in on. www and the
    // apex would otherwise mint different UIDs for one event, and a subscriber
    // who had both would see it twice instead of once.
    $domain = parse_url(icsBase(), PHP_URL_HOST);
    if (!is_string($domain) || $domain === '') $domain = $host;
    return 'bvtu-event-' . (int)$e['id'] . '@' . $domain;
}

/**
 * One VEVENT. Returns '' for an event with no start — a calendar entry with no
 * date is not something another calendar can hold.
 */
function icsEvent(array $e, string $host, string $base): string {
    $start = icsUtc($e['starts_at'] ?? '');
    if ($start === null) return '';

    // An hour is the least misleading default: a calendar with no end renders
    // as a zero-length sliver that is easy to miss.
    $end = icsUtc($e['ends_at'] ?? '');
    if ($end === null) {
        $d = DateTime::createFromFormat('Ymd\THis\Z', $start, new DateTimeZone('UTC'));
        $end = $d ? $d->modify('+1 hour')->format('Ymd\THis\Z') : $start;
    }

    $url  = $base . '/event.php?s=' . rawurlencode((string)$e['slug']);
    $desc = trim((string)($e['summary'] ?? ''));
    $body = trim((string)($e['description'] ?? ''));
    if ($body !== '') $desc = ($desc !== '' ? $desc . "\n\n" : '') . $body;
    $desc = ($desc !== '' ? $desc . "\n\n" : '') . $url;

    $lines = [
        'BEGIN:VEVENT',
        'UID:' . icsUid($e, $host),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $start,
        'DTEND:' . $end,
        'SUMMARY:' . icsText((string)$e['title']),
        'DESCRIPTION:' . icsText($desc),
        'URL:' . icsText($url),
    ];
    // Without these a calendar that already holds the event may keep the old
    // time after it moves. SEQUENCE climbs with each edit; LAST-MODIFIED gives
    // apps that prefer a timestamp something to compare.
    $touched = icsUtc((string)($e['updated_at'] ?? ''));
    if ($touched !== null) {
        $lines[] = 'LAST-MODIFIED:' . $touched;
        $lines[] = 'SEQUENCE:' . (int)min(2000000000, max(0, strtotime((string)$e['updated_at']) - 1760000000));
    }
    if (trim((string)($e['location'] ?? '')) !== '') {
        $lines[] = 'LOCATION:' . icsText((string)$e['location']);
    }
    if (evHasMap($e)) {
        $lines[] = 'GEO:' . (float)$e['map_lat'] . ';' . (float)$e['map_lng'];
    }
    // Closed registration is not a cancelled event — the seminar still runs,
    // you just cannot sign up any more. Nothing we publish is CANCELLED today.
    $lines[] = 'STATUS:CONFIRMED';
    $lines[] = 'END:VEVENT';

    return implode("\r\n", array_map('icsFold', $lines));
}

/** A whole calendar document. */
function icsDocument(array $events, string $host, string $base, string $name): string {
    $out = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Bulkley Valley Teachers Union//BVTU Events//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:' . icsText($name),
        'X-WR-TIMEZONE:America/Vancouver',
    ];
    $out = array_map('icsFold', $out);
    foreach ($events as $e) {
        $v = icsEvent($e, $host, $base);
        if ($v !== '') $out[] = $v;
    }
    $out[] = 'END:VCALENDAR';
    return implode("\r\n", $out) . "\r\n";
}

function icsHost(): string {
    // The host only names the UID's domain part, so the port comes off rather
    // than having its colon stripped and mashed in: "127.0.0.1:8796" became
    // "127.0.0.18796", which is nobody's domain.
    $h = (string)($_SERVER['HTTP_HOST'] ?? 'bvtu.ca');
    $h = explode(':', $h)[0];
    $h = preg_replace('/[^A-Za-z0-9.\-]/', '', $h);
    return $h !== '' ? $h : 'bvtu.ca';
}

function icsBase(): string {
    return defined('SITE_URL') && SITE_URL ? rtrim(SITE_URL, '/') : 'https://bvtu.ca';
}
