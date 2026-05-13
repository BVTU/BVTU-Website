<?php
/**
 * curated-fetch-meta.php
 * Fetches Open Graph / meta preview data from an external URL.
 * Called via JS fetch() from curated-admin.php.
 * Returns JSON: { ok, thumbnail, title, description }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/curated-db.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$member = getMember();
if (!curatedIsCurator($member['email'])) {
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

$url = trim($_GET['url'] ?? '');
if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid URL first.']);
    exit;
}

// ── Fetch the page ────────────────────────────────────────────────────────────
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_ENCODING       => 'gzip, deflate',
]);
$html    = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html === false || $html === '') {
    echo json_encode(['ok' => false, 'error' => 'Could not reach that URL' . ($curlErr ? ': ' . $curlErr : '.') ]);
    exit;
}

// Only look at the <head> section — avoid parsing huge bodies
$headEnd = stripos($html, '</head>');
$head    = $headEnd !== false ? substr($html, 0, $headEnd + 7) : substr($html, 0, 60000);

// ── Parser helper ─────────────────────────────────────────────────────────────
// Handles both attribute-order variants:
//   <meta property="og:image" content="...">
//   <meta content="..." property="og:image">
function ogMeta(string $head, string $prop): string {
    // property/name before content
    $pat1 = '/<meta\s[^>]*(?:property|name)=["\']' . preg_quote($prop, '/') . '["\'][^>]*content=["\'](.*?)["\']/is';
    // content before property/name
    $pat2 = '/<meta\s[^>]*content=["\'](.*?)["\'][^>]*(?:property|name)=["\']' . preg_quote($prop, '/') . '["\'][^>]*>/is';
    if (preg_match($pat1, $head, $m)) return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match($pat2, $head, $m)) return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return '';
}

// ── Extract thumbnail ─────────────────────────────────────────────────────────
$thumbnail = ogMeta($head, 'og:image');
if (!$thumbnail) $thumbnail = ogMeta($head, 'og:image:secure_url');
if (!$thumbnail) $thumbnail = ogMeta($head, 'twitter:image');
if (!$thumbnail) $thumbnail = ogMeta($head, 'twitter:image:src');

// Resolve relative thumbnail URLs against the base URL
if ($thumbnail && strpos($thumbnail, 'http') !== 0) {
    $parts = parse_url($url);
    $base  = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    if (strpos($thumbnail, '//') === 0) {
        $thumbnail = ($parts['scheme'] ?? 'https') . ':' . $thumbnail;
    } elseif (strpos($thumbnail, '/') === 0) {
        $thumbnail = $base . $thumbnail;
    } else {
        $thumbnail = $base . '/' . $thumbnail;
    }
}

// No og:image found — fall back to a live screenshot via thum.io (free, no key needed)
if (!$thumbnail) {
    $thumbnail = 'https://image.thum.io/get/width/600/crop/400/' . $url;
}

// ── Extract title ─────────────────────────────────────────────────────────────
$title = ogMeta($head, 'og:title');
if (!$title) $title = ogMeta($head, 'twitter:title');
if (!$title && preg_match('/<title[^>]*>(.*?)<\/title>/is', $head, $m)) {
    $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
// Trim " | Site Name" suffixes that make titles too long
if ($title && preg_match('/^(.+?)\s*[|\-–—:]\s*.+$/', $title, $m) && strlen($m[1]) >= 10) {
    $title = trim($m[1]);
}
if (strlen($title) > 150) $title = substr($title, 0, 147) . '...';

// ── Extract description ───────────────────────────────────────────────────────
$description = ogMeta($head, 'og:description');
if (!$description) $description = ogMeta($head, 'twitter:description');
if (!$description) $description = ogMeta($head, 'description');
if (strlen($description) > 280) $description = substr($description, 0, 277) . '...';

echo json_encode([
    'ok'          => true,
    'thumbnail'   => $thumbnail,
    'title'       => $title,
    'description' => $description,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
