<?php
/**
 * inc/site-url.php — render an indexed URL against whatever host we are on.
 *
 * The search index stores a URL per record, and those URLs were written with
 * SITE_URL at the time of indexing. The site moved from new.bvtu.ca to
 * bvtu.ca and every result kept pointing at the old host until someone
 * remembered to re-run the reindex — which is a thing nobody remembers.
 *
 * So the host in a stored URL is now ignored at display time and the path is
 * used on its own. A result found on bvtu.ca goes to bvtu.ca, whatever the
 * index happens to say, and the next domain change needs no reindex at all.
 *
 * Anything that is not an http(s) URL on some host — a path already, a mailto:,
 * a link to a genuinely external site — is left exactly as it is.
 */
function bvtuLocalUrl(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') return '#';
    if (!preg_match('~^https?://([^/]+)(/.*)?$~i', $url, $m)) return $url;

    $host = strtolower($m[1]);
    // Only our own hosts are rewritten. A link out to bctf.ca stays absolute.
    $ours = ['bvtu.ca', 'www.bvtu.ca', 'new.bvtu.ca'];
    if (!in_array($host, $ours, true)) return $url;

    $path = $m[2] ?? '';
    return $path !== '' ? $path : '/';
}
