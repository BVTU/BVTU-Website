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
function siteTestBannerStart(): void {
    if (!siteIsTest()) return;
    static $on = false;
    if ($on) return;             // one buffer per request, however many includes
    $on = true;

    ob_start(function (string $out): string {
        // Did this page actually send HTML? An explicit non-HTML Content-Type
        // settles it; otherwise the markup has to look like a document.
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') !== 0) continue;
            if (stripos($h, 'text/html') === false) return $out;
        }
        if (stripos($out, '<body') === false) return $out;

        $bar = '<div style="position:sticky;top:0;z-index:99999;background:#7c2d12;color:#fff;'
             . 'font:700 .8rem/1.5 system-ui,sans-serif;text-align:center;padding:.4rem .8rem;'
             . 'letter-spacing:.02em;">TEST SITE &mdash; nothing here is live, and no email '
             . 'leaves it. <span style="font-weight:400;opacity:.85;">Changes are published '
             . 'separately.</span></div>';

        // After the opening <body …>, wherever it is and however it is written.
        $out = preg_replace('/(<body\b[^>]*>)/i', '$1' . $bar, $out, 1, $n);
        return $n ? $out : $out;
    });
}

/** A <meta robots> for the test copy, so staging never reaches a search result. */
function siteTestNoIndex(): string {
    return siteIsTest() ? '<meta name="robots" content="noindex, nofollow">' : '';
}

siteTestBannerStart();
