<?php
/**
 * event-doc.php — serve one seminar handout.
 *
 * Handouts live behind a deny rule, so this is the only way to them. A file on
 * a published seminar is as public as the seminar page; a file on a draft is
 * not public yet and is shown only to an organiser.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/event-db.php';
require_once __DIR__ . '/members/exec-db.php';

evEnsureTables();
execEnsureTables();

$docId = (int)($_GET['d'] ?? 0);
$event = evBySlug(evStr($_GET['s'] ?? ''));

if (!$event || $docId <= 0) {
    http_response_code(404);
    exit('That handout could not be found.');
}

$isAdmin = isLoggedIn() && execIsAdmin(getMember()['email'] ?? '');
if (($event['status'] ?? '') === 'draft' && !$isAdmin) {
    // Same answer a stranger gets for a slug that never existed, so an
    // unpublished seminar cannot be confirmed by probing.
    http_response_code(404);
    exit('That handout could not be found.');
}

$doc = evDocument($docId, (int)$event['id']);
if (!$doc) {
    http_response_code(404);
    exit('That handout could not be found.');
}

$path = EV_DOCS_DIR . basename((string)$doc['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    exit('That handout is missing from the server.');
}

$ext  = strtolower((string)$doc['ext']);
$mime = EV_DOC_TYPES[$ext] ?? null;
if ($mime === null) {
    // Stored before this extension was allowed, or tampered with. Never fall
    // back to sniffing: a sniffed text/html served from our own domain is
    // stored XSS.
    http_response_code(403);
    exit('That handout cannot be shown.');
}

// A PDF opens in the browser, which is the point of previewing. Everything
// else downloads rather than being rendered by whatever the browser guesses.
$inline = ($ext === 'pdf' || $mime === 'image/png' || $mime === 'image/jpeg');

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
     . '; filename="' . addslashes(evDocFileName($doc)) . '"');
header('Content-Length: ' . filesize($path));
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, max-age=600');
readfile($path);
