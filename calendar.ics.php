<?php
/**
 * calendar.ics.php — the whole BVTU calendar, for subscribing to.
 *
 * Added once in Google, Apple, Proton or Outlook and everything we publish
 * afterwards arrives on its own. That is what makes a single newsletter link
 * worth more than a list of dates that is stale the day after it is sent.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/event-db.php';
require_once __DIR__ . '/members/event-ics.php';

try {
    evEnsureTables();
} catch (\Throwable $e) {
    error_log('calendar.ics: ' . $e->getMessage());
}

// A subscribed calendar is re-read for ever, so it carries a window rather than
// the whole history: the recent past for context, and everything still to come.
$events = [];
foreach (evPublished() as $e) {
    $when = trim((string)($e['ends_at'] ?: $e['starts_at']));
    if ($when === '') continue;
    $t = strtotime($when);
    if ($t === false) continue;
    if ($t < strtotime('-120 days')) continue;
    $events[] = $e;
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="bvtu-calendar.ics"');
header('X-Content-Type-Options: nosniff');
// Short, because a calendar app re-reads on its own schedule anyway and a long
// cache here only adds to the lag.
header('Cache-Control: public, max-age=1800');
echo icsDocument($events, icsHost(), icsBase(), "BVTU Events");
