<?php
/**
 * reindex.php — Push all BVTU content to Algolia
 *
 * SECURITY: This file checks a secret token before running.
 * Visit: /reindex.php?token=YOUR_REINDEX_TOKEN
 *
 * The token is defined in members/config.php (gitignored, on server only).
 * Add to config.php:
 *   define('ALGOLIA_ADMIN_KEY',   '8524626093e061fb60bf50960246600e');
 *   define('REINDEX_TOKEN',       'choose-a-long-random-string-here');
 *
 * Run this whenever you publish new pages or upload new documents.
 */

// ── Load config ──────────────────────────────────────────────────────────────
$configPath = __DIR__ . '/members/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

$adminKey     = defined('ALGOLIA_ADMIN_KEY') ? ALGOLIA_ADMIN_KEY : '';
$reindexToken = defined('REINDEX_TOKEN')     ? REINDEX_TOKEN     : '';

// ── Auth gate ─────────────────────────────────────────────────────────────────
if (!$adminKey) {
    die("Error: ALGOLIA_ADMIN_KEY not set in members/config.php\n");
}
if ($reindexToken && ($_GET['token'] ?? '') !== $reindexToken) {
    http_response_code(403);
    die("403 Forbidden — wrong or missing token.\n");
}

// ── Algolia config ────────────────────────────────────────────────────────────
const ALGOLIA_APP_ID = 'IUEMJN3YMB';
const ALGOLIA_INDEX  = 'bvtu_content';

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Strip HTML tags and compress whitespace */
function extractText(string $html): string {
    $text = preg_replace('/<(style|script|noscript)[^>]*>.*?<\/\1>/is', '', $html);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

/** Extract <title> from HTML */
function extractTitle(string $html): string {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $t = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Strip " — BVTU" suffix
        $t = preg_replace('/\s*[—\-–]\s*BVTU.*$/u', '', $t);
        return trim($t);
    }
    return '';
}

/** Extract <meta name="description"> */
function extractDescription(string $html): string {
    if (preg_match('/<meta\s[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\']/is', $html, $m)) {
        return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

/** Extract <main> or <article> body text */
function extractMainContent(string $html): string {
    // Try <main>
    if (preg_match('/<main[^>]*>(.*?)<\/main>/is', $html, $m)) {
        return extractText($m[1]);
    }
    // Try <article>
    if (preg_match('/<article[^>]*>(.*?)<\/article>/is', $html, $m)) {
        return extractText($m[1]);
    }
    return extractText($html);
}

/** POST records to Algolia batch endpoint */
function algoliaRequest(string $method, string $path, array $body): array {
    global $adminKey;
    $appId = ALGOLIA_APP_ID;
    $url   = "https://{$appId}.algolia.net{$path}";
    $json  = json_encode($body);
    $ctx   = stream_context_create([
        'http' => [
            'method'  => $method,
            'header'  => implode("\r\n", [
                'Content-Type: application/json',
                "X-Algolia-Application-Id: {$appId}",
                "X-Algolia-API-Key: {$adminKey}",
            ]),
            'content'        => $json,
            'timeout'        => 15,
            'ignore_errors'  => true,
        ],
    ]);
    $resp   = file_get_contents($url, false, $ctx);
    $status = $http_response_header[0] ?? 'HTTP/1.1 000 Unknown';
    return ['status' => $status, 'body' => json_decode($resp ?: '{}', true)];
}

/** Send a batch of records to Algolia */
function algoliaAddBatch(array $records): void {
    $ops = array_map(fn($r) => ['action' => 'addObject', 'body' => $r], $records);
    $res = algoliaRequest('POST', '/1/indexes/' . ALGOLIA_INDEX . '/batch', ['requests' => $ops]);
    $ok  = str_contains($res['status'], '200') || str_contains($res['status'], '201');
    echo $ok
        ? "  ✓ Batch of " . count($records) . " records sent.\n"
        : "  ✗ Batch failed: {$res['status']}\n";
}

// ── Output ────────────────────────────────────────────────────────────────────
// Force real-time output — disable buffering
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no'); // disable nginx buffering
echo "BVTU Algolia Reindex\n";
echo str_repeat('=', 50) . "\n\n";

// ── 1. Clear existing index ───────────────────────────────────────────────────
echo "Clearing index '" . ALGOLIA_INDEX . "'...\n";
$res = algoliaRequest('POST', '/1/indexes/' . ALGOLIA_INDEX . '/clear', []);
echo str_contains($res['status'], '200') ? "  ✓ Index cleared.\n\n" : "  ✗ Clear failed: {$res['status']}\n\n";

// ── 2. Configure index settings ───────────────────────────────────────────────
echo "Configuring index settings...\n";
$settings = [
    'searchableAttributes' => ['title', 'description', 'content'],
    'attributesForFaceting'=> ['type', 'members_only'],
    'attributesToHighlight'=> ['title', 'content'],
    'attributesToSnippet'  => ['content:40'],
    'snippetEllipsisText'  => '…',
    'customRanking'        => ['desc(priority)'],
    'removeStopWords'      => true,
    'minWordSizefor1Typo'  => 4,
    'minWordSizefor2Typos' => 8,
];
$res = algoliaRequest('PUT', '/1/indexes/' . ALGOLIA_INDEX . '/settings', $settings);
echo str_contains($res['status'], '200') ? "  ✓ Settings saved.\n\n" : "  ✗ Settings failed: {$res['status']}\n\n";

// ── 3. Index public pages ─────────────────────────────────────────────────────
echo "Indexing public pages...\n";

$root = __DIR__;
$baseUrl = 'https://bvtu.ca'; // update if site is at a different domain

$publicPages = [
    ['file' => 'index.php',         'url' => '/',                    'priority' => 10],
    ['file' => 'about.php',         'url' => '/about.php',           'priority' => 8],
    ['file' => 'documents.php',     'url' => '/documents.php',       'priority' => 8],
    ['file' => 'members.php',       'url' => '/members.php',         'priority' => 7],
    ['file' => 'prod.php',          'url' => '/prod.php',            'priority' => 7],
    ['file' => 'health-safety.php', 'url' => '/health-safety.php',   'priority' => 7],
    ['file' => 'bctf.php',          'url' => '/bctf.php',            'priority' => 6],
    ['file' => 'remedy-tracker.php','url' => '/remedy-tracker.php',  'priority' => 6],
];

$records = [];

foreach ($publicPages as $page) {
    $path = $root . '/' . $page['file'];
    if (!file_exists($path)) {
        echo "  - Skipped (not found): {$page['file']}\n";
        continue;
    }

    // Capture rendered output (PHP pages)
    ob_start();
    // Simple include — pages that call requireLogin() will redirect; we avoid members/ pages here
    // For public pages, we fake minimal superglobals
    $_GET = $_POST = [];
    try {
        include $path;
    } catch (Throwable $e) {
        // ignore render errors; we'll still try to parse what we got
    }
    $html = ob_get_clean();

    if (!$html) {
        // Fallback: read file as-is
        $html = file_get_contents($path);
    }

    $title   = extractTitle($html)         ?: basename($page['file'], '.php');
    $content = extractMainContent($html);
    $desc    = extractDescription($html);

    $records[] = [
        'objectID'    => 'page_' . basename($page['file'], '.php'),
        'type'        => 'page',
        'title'       => $title,
        'description' => $desc,
        'content'     => mb_substr($content, 0, 8000),
        'url'         => $baseUrl . $page['url'],
        'priority'    => $page['priority'],
        'members_only'=> false,
    ];

    echo "  + {$page['file']} — {$title}\n";
}

if ($records) {
    algoliaAddBatch($records);
}

// ── 4. Index public PDFs ──────────────────────────────────────────────────────
echo "\nIndexing public documents...\n";

$publicDocsDir = $root . '/documents/';
$pdfRecords    = [];

if (is_dir($publicDocsDir)) {
    foreach (new DirectoryIterator($publicDocsDir) as $f) {
        if ($f->isDot() || strtolower($f->getExtension()) !== 'pdf') continue;
        $name   = $f->getBasename('.pdf');
        $label  = ucwords(str_replace(['-', '_'], ' ', $name));
        $pdfRecords[] = [
            'objectID'    => 'doc_public_' . $f->getBasename('.pdf'),
            'type'        => 'document',
            'title'       => $label,
            'description' => 'BVTU public document',
            'content'     => $label,
            'url'         => $baseUrl . '/documents/' . $f->getFilename(),
            'priority'    => 5,
            'members_only'=> false,
        ];
        echo "  + {$f->getFilename()}\n";
    }
} else {
    echo "  (no /documents/ directory found)\n";
}

if ($pdfRecords) {
    algoliaAddBatch($pdfRecords);
}

// ── 5. Index protected (members-only) PDFs ───────────────────────────────────
echo "\nIndexing members-only documents...\n";

$protectedDocsDir = $root . '/members/protected-docs/';
$memberRecords    = [];

if (is_dir($protectedDocsDir)) {
    foreach (new DirectoryIterator($protectedDocsDir) as $f) {
        if ($f->isDot() || strtolower($f->getExtension()) !== 'pdf') continue;
        $name   = $f->getBasename('.pdf');
        $label  = ucwords(str_replace(['-', '_'], ' ', $name));
        $memberRecords[] = [
            'objectID'    => 'doc_members_' . $name,
            'type'        => 'document',
            'title'       => $label,
            'description' => 'BVTU members-only document',
            'content'     => $label,
            'url'         => $baseUrl . '/members/serve-doc.php?file=' . urlencode($f->getFilename()),
            'priority'    => 5,
            'members_only'=> true,
        ];
        echo "  + {$f->getFilename()} (members only)\n";
    }
} else {
    echo "  (no /members/protected-docs/ directory found)\n";
}

if ($memberRecords) {
    algoliaAddBatch($memberRecords);
}

// ── Done ──────────────────────────────────────────────────────────────────────
$total = count($records) + count($pdfRecords) + count($memberRecords);
echo "\n" . str_repeat('=', 50) . "\n";
echo "Done. {$total} total records sent to Algolia.\n";
echo "Visit https://dashboard.algolia.com to verify the index.\n";
