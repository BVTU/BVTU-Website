<?php
/**
 * track.php — the analytics beacon. Public, unauthenticated, deliberately dull.
 *
 * js/site.js posts a path here after a page loads. It answers 204 either way:
 * whether a hit was counted is not the visitor's business, and a body gives an
 * abuser something to measure their attempts against.
 *
 * The browser never sends an identifier. The IP and user-agent are read from
 * the request, used to derive a day-scoped hash and a device class, and are
 * never written anywhere — see members/analytics-db.php.
 */
/*
 * Whatever happens below, this endpoint answers 204 and says nothing.
 *
 * getDB() calls die() when the database is unreachable, and die() runs past
 * try/catch and past any cleanup written after it — so the endpoint would
 * answer 200 with "Unable to connect to the database…", handing anyone probing
 * it exactly the signal 204 exists to withhold. Buffering alone does not fix
 * that, because PHP flushes open buffers at shutdown. A shutdown handler does:
 * it runs even after die(), the buffered output is discarded there before any
 * of it reaches the wire, and the status is set while headers are still
 * unsent. Every exit path below therefore just returns.
 */
ob_start();
register_shutdown_function(function () {
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) http_response_code(204);
});
function anDone(): void { exit; }

require_once __DIR__ . '/members/analytics-db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') anDone();

// Same-origin only. Not a real security boundary — a referrer can be forged —
// but it keeps casual noise and accidental crawls out of the numbers.
$selfHost = $_SERVER['HTTP_HOST'] ?? '';
$origin   = (string)parse_url((string)($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
if ($origin !== '' && strcasecmp($origin, $selfHost) !== 0
    && strcasecmp($origin, 'www.' . $selfHost) !== 0) anDone();

$path = (string)($_POST['p'] ?? '');
if ($path === '' || $path[0] !== '/' || strlen($path) > 300) anDone();

/*
 * Only paths that are really pages here get counted.
 *
 * This endpoint takes an unauthenticated insert, and the Origin check above is
 * a courtesy, not a boundary — a header is trivially forged. Without this a
 * loop could post made-up paths until the hosting quota is gone and every
 * per-page figure is meaningless. The public site is a fixed set of files, so
 * the cheapest correct answer is to ask whether the path names one of them.
 *
 * /go/{slug} is the one rewritten path, and it is right that it fails here: it
 * is a redirect with no page to run the beacon on, the visitor is counted when
 * they land on the real destination, and short_links.click_count already counts
 * the hop itself.
 */
$clean = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: '', '/');
if ($clean === '/') {
    $file = 'index.php';                       // the site root
} else {
    if (!preg_match('~^/[A-Za-z0-9._/-]{1,200}$~', $clean) || strpos($clean, '..') !== false) {
        ob_end_clean(); anDone();
    }
    $file = ltrim($clean, '/');
}
if (strpos($file, 'members/') === 0 || !is_file(__DIR__ . '/' . $file)) anDone();
$path = $clean;

$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

// A click posts the same page path plus what was clicked. The path has already
// been checked against a real file above, so both shapes share that guarantee.
$kind   = (string)($_POST['k'] ?? '');
$target = (string)($_POST['t'] ?? '');
if ($kind !== '' || $target !== '') {
    /*
     * The target needs the same treatment the page path got. Without it the
     * is_file() check above protects only half the endpoint: a loop could still
     * post invented click targets until the table is full and every link figure
     * is meaningless.
     *
     * Internal targets must name a real file, exactly as a page view must.
     * Outbound targets cannot be checked that way — the whole point is that
     * they are elsewhere — so they are held to a hostname shape instead.
     */
    $ok = strlen($target) <= 300 && preg_match('~^[A-Za-z0-9._/\-]{1,255}$~', $target)
          && strpos($target, '..') === false;

    if ($ok && ($kind === 'int' || $kind === 'doc')) {
        $f = ltrim((string)parse_url('/' . ltrim($target, '/'), PHP_URL_PATH), '/');
        // "/" is the home page, the same mapping the page-view path uses above.
        // Without it every click on the logo or a Home link was thrown away —
        // which would have quietly removed the site's most-clicked link from
        // the figures.
        if ($f === '') $f = 'index.php';
        $ok = strpos($f, 'members/') !== 0 && is_file(__DIR__ . '/' . $f);
    } elseif ($ok && $kind === 'go') {
        $ok = (bool)preg_match('~^/go/[A-Za-z0-9-]{1,100}$~', '/' . ltrim($target, '/'));
    } elseif ($ok && $kind === 'out') {
        // host, then optionally a path: "bctf.ca" or "bctf.ca/some/page"
        $ok = (bool)preg_match('~^[a-z0-9.-]+\.[a-z]{2,24}(/[A-Za-z0-9._/\-]*)?$~i', $target);
    }

    if ($ok) {
        try {
            anRecordClick($path, $kind, $target, $ua);
        } catch (\Throwable $e) {
            error_log('track click: ' . $e->getMessage());
        }
    }
    anDone();
}

try {
    anRecordView(
        $path,
        (string)($_POST['r'] ?? ''),
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        $ua,
        $selfHost
    );
    // Roughly once in 500 hits, tidy up. Cheap on average, and it means the
    // retention promise keeps itself without a cron job the host may not offer.
    if (random_int(1, 500) === 1) anCull();
} catch (\Throwable $e) {
    // Analytics must never be the reason a page misbehaves. The visitor already
    // has their page; this is a side effect and it fails silently.
    error_log('track: ' . $e->getMessage());
}

anDone();
