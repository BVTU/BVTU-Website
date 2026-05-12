<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

/** POST records to Algolia batch endpoint (cURL) */
function algoliaRequest(string $method, string $path, array $body): array {
    global $adminKey;
    $appId = ALGOLIA_APP_ID;
    $url   = "https://{$appId}.algolia.net{$path}";
    $json  = json_encode($body);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            "X-Algolia-Application-Id: {$appId}",
            "X-Algolia-API-Key: {$adminKey}",
        ],
    ]);
    $resp   = curl_exec($ch);
    $status = 'HTTP/1.1 ' . curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err) { global $log; $log .= "  cURL error: {$err}\n"; }
    return ['status' => $status, 'body' => json_decode($resp ?: '{}', true)];
}

/** Send a batch of records to Algolia */
function algoliaAddBatch(array $records): void {
    global $log;
    $ops = array_map(function($r) { return ['action' => 'addObject', 'body' => $r]; }, $records);
    $res = algoliaRequest('POST', '/1/indexes/' . ALGOLIA_INDEX . '/batch', ['requests' => $ops]);
    $ok  = (strpos($res['status'], '200') !== false) || (strpos($res['status'], '201') !== false);
    $log .= $ok
        ? "  ✓ Batch of " . count($records) . " records sent.\n"
        : "  ✗ Batch failed: {$res['status']}\n";
}

// ── Output ────────────────────────────────────────────────────────────────────
// Collect all output into a buffer, then send at the end.
// Streaming/flush-based approaches don't work reliably behind Hostinger's nginx proxy.
set_time_limit(120);
$log = '';

$log .= "BVTU Algolia Reindex\n";
$log .= str_repeat('=', 50) . "\n\n";

// ── 1. Clear existing index ───────────────────────────────────────────────────
$log .= "Clearing index '" . ALGOLIA_INDEX . "'...\n";
$res  = algoliaRequest('POST', '/1/indexes/' . ALGOLIA_INDEX . '/clear', []);
$log .= (strpos($res['status'], '200') !== false) ? "  ✓ Index cleared.\n\n" : "  ✗ Clear failed: {$res['status']}\n\n";

// ── 2. Configure index settings ───────────────────────────────────────────────
$log .= "Configuring index settings...\n";
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
$res  = algoliaRequest('PUT', '/1/indexes/' . ALGOLIA_INDEX . '/settings', $settings);
$log .= (strpos($res['status'], '200') !== false) ? "  ✓ Settings saved.\n\n" : "  ✗ Settings failed: {$res['status']}\n\n";

// ── 2b. Push synonyms ─────────────────────────────────────────────────────────
$log .= "Pushing synonyms...\n";
$synonyms = [
    ['objectID' => 'syn-prep',      'type' => 'synonym', 'synonyms' => ['prep time', 'preparation time', 'preparation period']],
    ['objectID' => 'syn-prod',      'type' => 'synonym', 'synonyms' => ['pro-d', 'prod', 'professional development', 'pro d']],
    ['objectID' => 'syn-sick',      'type' => 'synonym', 'synonyms' => ['sick days', 'sick leave', 'illness leave']],
    ['objectID' => 'syn-mat',       'type' => 'synonym', 'synonyms' => ['maternity leave', 'mat leave', 'parental leave', 'paternity leave']],
    ['objectID' => 'syn-ttoc',      'type' => 'synonym', 'synonyms' => ['ttoc', 'teacher on call', 'teacher teaching on call', 'toc', 'substitute teacher']],
    ['objectID' => 'syn-salary',    'type' => 'synonym', 'synonyms' => ['salary', 'pay', 'wages', 'compensation', 'salary grid', 'pay grid']],
    ['objectID' => 'syn-grievance', 'type' => 'synonym', 'synonyms' => ['grievance', 'complaint', 'dispute', 'remedy']],
    ['objectID' => 'syn-release',   'type' => 'synonym', 'synonyms' => ['release time', 'released time', 'release day', 'time release']],
    ['objectID' => 'syn-benefits',  'type' => 'synonym', 'synonyms' => ['benefits', 'extended health', 'dental', 'msp', 'health benefits']],
];
$res  = algoliaRequest('POST', '/1/indexes/' . ALGOLIA_INDEX . '/synonyms/batch?replaceExistingSynonyms=true', $synonyms);
$log .= ((strpos($res['status'], '200') !== false) || (strpos($res['status'], '201') !== false))
    ? "  ✓ " . count($synonyms) . " synonym groups pushed.\n\n"
    : "  ✗ Synonyms failed: {$res['status']}\n\n";

// ── 3. Index public pages ─────────────────────────────────────────────────────
// Hardcoded records — avoids including PHP files which causes session/auth side-effects.
// Update content here whenever a page's text changes significantly.
$log .= "Indexing public pages...\n";

$baseUrl = 'https://new.bvtu.ca';

$records = [
    [
        'objectID'    => 'page_index',
        'type'        => 'page',
        'title'       => 'Home',
        'description' => 'Bulkley Valley Teachers\' Union — local of the BC Teachers\' Federation representing educators in Smithers, Telkwa, and Houston.',
        'content'     => 'Bulkley Valley Teachers Union BVTU local BC Teachers Federation SD54 School District 54 Smithers Telkwa Houston collective agreement member resources professional development health safety benefits',
        'url'         => $baseUrl . '/',
        'priority'    => 10,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_documents',
        'type'        => 'page',
        'title'       => 'Documents',
        'description' => 'BVTU documents — collective agreement, letters of understanding, constitution and bylaws, school calendars.',
        'content'     => 'documents collective agreement letters of understanding LOUs constitution bylaws school calendars contract assistant settlements',
        'url'         => $baseUrl . '/documents.php',
        'priority'    => 8,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_lous',
        'type'        => 'page',
        'title'       => 'Letters of Understanding',
        'description' => 'BVTU Letters of Understanding — local agreements between BVTU and SD54.',
        'content'     => 'letters of understanding LOUs local agreements SD54 BVTU memorandum side agreements',
        'url'         => $baseUrl . '/lous.php',
        'priority'    => 7,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_members',
        'type'        => 'page',
        'title'       => 'Member Resources',
        'description' => 'Resources for BVTU members — benefits, salary grids, TTOC, release time, and more.',
        'content'     => 'member resources health dental benefits life insurance loan forgiveness salary grids TTOC teacher on call release time atrieve remedy tracker collaboration grant',
        'url'         => $baseUrl . '/members.php',
        'priority'    => 8,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_benefits',
        'type'        => 'page',
        'title'       => 'Health & Dental Benefits',
        'description' => 'Pacific Blue Cross health and dental benefits for SD54 teachers — extended health, dental, prescription drugs, vision, paramedical, and how to make a claim.',
        'content'     => 'health dental benefits Pacific Blue Cross extended health prescription drugs vision paramedical physiotherapy massage chiropractor counselling naturopath podiatrist speech therapy travel emergency hearing aids orthotics hospital ambulance deductible reimbursement claims',
        'url'         => $baseUrl . '/benefits.php',
        'priority'    => 8,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_salary',
        'type'        => 'page',
        'title'       => 'Salary Grids',
        'description' => 'SD54 teacher salary grids — pay scales by category and experience.',
        'content'     => 'salary grid pay scale category experience teachers compensation wages annual',
        'url'         => $baseUrl . '/salary.php',
        'priority'    => 7,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_ttoc',
        'type'        => 'page',
        'title'       => 'TTOC Resources',
        'description' => 'Resources for Teachers Teaching on Call in SD54.',
        'content'     => 'TTOC teacher on call substitute teacher resources pay rate rights seniority',
        'url'         => $baseUrl . '/ttoc.php',
        'priority'    => 7,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_prod',
        'type'        => 'page',
        'title'       => 'PRO-D',
        'description' => 'Professional development resources for BVTU members — Pro-D portal, collaboration grants, and BCTF PRO-D programs.',
        'content'     => 'professional development PRO-D pro d portal collaboration grant BCTF learning committee independent learning',
        'url'         => $baseUrl . '/prod.php',
        'priority'    => 7,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_collab-grant',
        'type'        => 'page',
        'title'       => 'Collaboration Grant',
        'description' => 'BVTU collaboration grant for teacher professional development projects.',
        'content'     => 'collaboration grant professional development funding application teachers project',
        'url'         => $baseUrl . '/collab-grant.php',
        'priority'    => 7,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_health-safety',
        'type'        => 'page',
        'title'       => 'Health & Safety',
        'description' => 'BVTU health and safety resources — workplace committees, WorkSafe forms, employee assistance, and mental health support.',
        'content'     => 'health safety workplace committee WorkSafe BC violence incident report EFAP employee family assistance mental health wellness occupational',
        'url'         => $baseUrl . '/health-safety.php',
        'priority'    => 7,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_bctf',
        'type'        => 'page',
        'title'       => 'BCTF',
        'description' => 'BC Teachers\' Federation resources — provincial agreement, member benefits, and bargaining updates.',
        'content'     => 'BCTF BC Teachers Federation provincial collective agreement bargaining member benefits services professional development social justice equity',
        'url'         => $baseUrl . '/bctf.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_remedy-tracker',
        'type'        => 'page',
        'title'       => 'Remedy Tracker',
        'description' => 'Track BVTU remedy requests and grievance outcomes.',
        'content'     => 'remedy tracker grievance dispute resolution outcome arbitration',
        'url'         => $baseUrl . '/remedy-tracker.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_atrieve',
        'type'        => 'page',
        'title'       => 'Release Time / Atrieve',
        'description' => 'Information on release time and the Atrieve system for BVTU members.',
        'content'     => 'release time atrieve system union leave pro-d days scheduling',
        'url'         => $baseUrl . '/atrieve.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_life-insurance',
        'type'        => 'page',
        'title'       => 'Life Insurance',
        'description' => 'Life insurance coverage for BVTU members through the BCTF.',
        'content'     => 'life insurance coverage beneficiary BCTF death benefit',
        'url'         => $baseUrl . '/life-insurance.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_loan-forgiveness',
        'type'        => 'page',
        'title'       => 'Student Loan Forgiveness',
        'description' => 'Student loan forgiveness programs available to BC teachers.',
        'content'     => 'student loan forgiveness BC teacher rural remote repayment program',
        'url'         => $baseUrl . '/loan-forgiveness.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_calendars',
        'type'        => 'page',
        'title'       => 'School Calendars',
        'description' => 'SD54 school year calendars — instructional days, Pro-D days, and key dates.',
        'content'     => 'school calendar SD54 2025 2026 2027 instructional days pro-d days key dates schedule',
        'url'         => $baseUrl . '/calendars.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_contact',
        'type'        => 'page',
        'title'       => 'Contact',
        'description' => 'Contact the Bulkley Valley Teachers\' Union.',
        'content'     => 'contact BVTU president Cody Lind Smithers address phone email',
        'url'         => $baseUrl . '/contact.php',
        'priority'    => 5,
        'members_only'=> false,
    ],
];

foreach ($records as $r) {
    $log .= "  + {$r['url']} — {$r['title']}\n";
}

if ($records) {
    algoliaAddBatch($records);
}

// ── 4. Index public PDFs ──────────────────────────────────────────────────────
$log .= "\nIndexing public documents...\n";

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
        $log .= "  + {$f->getFilename()}\n";
    }
} else {
    $log .= "  (no /documents/ directory found)\n";
}

if ($pdfRecords) {
    algoliaAddBatch($pdfRecords);
}

// ── 5. Index protected (members-only) PDFs ───────────────────────────────────
$log .= "\nIndexing members-only documents...\n";

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
        $log .= "  + {$f->getFilename()} (members only)\n";
    }
} else {
    $log .= "  (no /members/protected-docs/ directory found)\n";
}

if ($memberRecords) {
    algoliaAddBatch($memberRecords);
}

// ── Done ──────────────────────────────────────────────────────────────────────
$total = count($records) + count($pdfRecords) + count($memberRecords);
$log  .= "\n" . str_repeat('=', 50) . "\n";
$log  .= "Done. {$total} total records sent to Algolia.\n";
$log  .= "Visit https://dashboard.algolia.com to verify the index.\n";

// Output everything at once — avoids Hostinger nginx buffering cutting off the response
header('Content-Type: text/plain; charset=utf-8');
echo $log;
