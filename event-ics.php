<?php
/**
 * event-ics.php — one event as a calendar file, for "add this to my calendar".
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/event-db.php';
require_once __DIR__ . '/members/event-ics.php';

try {
    evEnsureTables();
    $event = evBySlug(evStr($_GET['s'] ?? ''));
} catch (\Throwable $e) {
    error_log('event-ics: ' . $e->getMessage());
    $event = null;
}

if (!$event || ($event['status'] ?? '') === 'draft') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('That event could not be found.');
}

$name = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower((string)$event['title']));
$name = trim((string)$name, '-') ?: 'bvtu-event';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '.ics"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
echo icsDocument([$event], icsHost(), icsBase(), (string)$event['title']);
