<?php
/**
 * env.php — knowing, on every page, whether this is the live site or the test copy.
 *
 * SITE_ENV is set in config.php, which is never deployed. So the live server
 * cannot inherit "test" from a branch, and the test server cannot lose it by
 * being redeployed. Undefined means live: the dangerous mistake is a test
 * server that believes it is live, not the reverse.
 *
 * The banner is injected into the page rather than added to 50-odd headers by
 * hand. That keeps it to one rule in one place — and a rule that is wrong in
 * one place is better than one that is missing from eleven.
 */

/*
 * Errors are logged, not printed — on public pages too.
 *
 * auth.php says the same thing and why: a notice printed into a page publishes
 * the server's filesystem path to whoever is reading it. auth.php is only
 * loaded by member pages though, so a notice on the public side printed in
 * full. A duplicated define in config.php did exactly that on the test site.
 *
 * Off BEFORE config.php is required, because config.php is itself a thing that
 * can emit a notice — which is precisely what happened, and suppressing after
 * loading it would have been too late to stop the one leak we have actually
 * seen. BVTU_DEBUG is read afterwards, once config has had its say.
 */
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

/*
 * config.php holds SITE_ENV, and this file is included from the shared nav and
 * from auth.php — sometimes before anything has loaded config. Reading it here
 * means the answer does not depend on which require happened to come first;
 * it is constants only, and db.php loads it the same way.
 */
if (!defined('SITE_ENV')) {
    $cfg = __DIR__ . '/../members/config.php';
    if (is_file($cfg)) require_once $cfg;
}

// Now that config has loaded, let BVTU_DEBUG put them back on screen.
if (defined('BVTU_DEBUG') && BVTU_DEBUG) {
    @ini_set('display_errors', '1');
}

if (!function_exists('siteIsTest')) {
    function siteIsTest(): bool {
        return defined('SITE_ENV') && strtolower(trim((string)SITE_ENV)) === 'test';
    }
}

/**
 * Put the test bar on whatever HTML this page is about to send.
 *
 * Only HTML. This site also serves PDFs, images, .ics files and CSV downloads
 * through PHP, and a stray <div> prepended to a PDF is a corrupt file rather
 * than a cosmetic problem — so everything is checked before anything is
 * touched, and anything uncertain is left exactly as it was.
 */
/** Where every email goes on the test site. */
function siteTestInbox(): string {
    return defined('SITE_TEST_INBOX') ? trim((string)SITE_TEST_INBOX) : '';
}

/**
 * The address a raw mail() call should actually use.
 *
 * A handful of places send with PHP's own mail() rather than through
 * smtp.php — the contact form, the CA assistant's alert, the library rating
 * notice. They never passed through siteDeliveryAddress(), so on the test site
 * they went to real people while the banner promised nothing leaves it.
 *
 * Returns '' on a test server with no inbox configured, and callers skip the
 * send: a staging copy that cannot mail anybody is a nuisance, one that mails
 * the membership is the accident this exists to prevent.
 */
function siteRawMailTo(string $to): string {
    return siteIsTest() ? siteTestInbox() : $to;
}

function siteTestBannerHtml(): string {
    static $done = false;
    if (!siteIsTest() || $done) return '';
    $done = true;
    return '<div style="position:sticky;top:0;z-index:99999;background:#7c2d12;color:#fff;'
         . 'font:700 .8rem/1.5 system-ui,sans-serif;text-align:center;padding:.4rem .8rem;'
         . 'letter-spacing:.02em;">TEST SITE &mdash; nothing here is live, and no email '
         . 'leaves it. <span style="font-weight:400;opacity:.85;">Changes are published '
         . 'separately.</span></div>';
}

function siteTestBannerStart(): void {
    if (!siteIsTest()) return;
    static $on = false;
    if ($on) return;             // one buffer per request, however many includes
    $on = true;

    /*
     * A chunk size, not an unlimited buffer. event-doc.php and the receipt and
     * CSV exports all include auth.php and then stream megabytes; holding that
     * whole download in memory to look for a <body> tag it will never contain
     * is how a large PDF turns into a memory_limit fatal. With a chunk size the
     * output flows as it is produced and the banner still lands, because an
     * HTML page emits <body> in its first few kilobytes.
     */
    ob_start(function (string $out): string {
        // Did this page actually send HTML? An explicit non-HTML Content-Type
        // settles it; otherwise the markup has to look like a document.
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') !== 0) continue;
            if (stripos($h, 'text/html') === false) return $out;
        }
        if (stripos($out, '<body') === false) return $out;

        $bar = siteTestBannerHtml();
        if ($bar === '') return $out;   // already emitted by the shared nav

        // After the opening <body …>, wherever it is and however it is written.
        $out = preg_replace('/(<body\b[^>]*>)/i', '$1' . $bar, $out, 1, $n);
        return $n ? $out : $out;
    }, 8192);
}

/**
 * Keep the test copy out of search results.
 *
 * A header rather than a <meta> tag, for two reasons: it covers the PDFs,
 * images and .ics files this site serves through PHP as well as the pages, and
 * it needs no edit to fifty-odd templates to take effect. The site's own
 * robots.txt cannot do this job — crawlers only read the one at the domain
 * root, which belongs to the live site.
 */
function siteTestNoIndexHeader(): void {
    // Only possible before output has started, which on a public page is not
    // guaranteed — this file is included by the shared nav, and most pages have
    // already printed their <head> by then. The .htaccess rule keyed on the
    // /test/ path is what actually guarantees it, for every response including
    // the static ones PHP never sees. This stays as the belt.
    if (!siteIsTest() || headers_sent()) return;
    header('X-Robots-Tag: noindex, nofollow', true);
}

siteTestNoIndexHeader();
siteTestBannerStart();
