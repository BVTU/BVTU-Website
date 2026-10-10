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

/*
 * Three ways in, in order of how pleasant they are.
 *
 * The token in a query string was the only way, and it is the worst of the
 * three: it has to be looked up in config.php every time, it lands in the
 * server's access log, and a token containing & + # or a space is mangled by
 * the URL before it ever arrives — which looks exactly like a wrong token and
 * is why one copied straight out of config.php can still be refused.
 */
$reindexWhy = '';
$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    // Run by cron as `php reindex.php`. Being able to read the file at all
    // means being able to read ALGOLIA_ADMIN_KEY out of it, so a second
    // secret on top of that proves nothing.
    $reindexWhy = 'command line';
} else {
    $sent = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
    if ($reindexToken !== '' && $sent !== '' && hash_equals($reindexToken, $sent)) {
        $reindexWhy = 'token';
    } else {
        // A signed-in admin, so this can be a link rather than a secret.
        require_once __DIR__ . '/members/auth.php';
        require_once __DIR__ . '/members/exec-db.php';
        if (isLoggedIn() && execIsAdmin(getMember()['email'] ?? '')) {
            /* This empties the index and rebuilds it, and a session cookie
             * travels with any request a browser can be talked into making.
             * The token happened to prevent that; a plain link would not. So
             * the link asks first, and the asking carries a CSRF token. */
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValid()) {
                $reindexWhy = 'signed in as ' . (getMember()['email'] ?? 'admin');
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><meta charset="utf-8">'
                   . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                   . '<title>Rebuild the search index</title>'
                   . '<div style="font-family:system-ui,sans-serif;max-width:34rem;'
                   . 'margin:3rem auto;padding:0 1.25rem;line-height:1.6;">'
                   . '<h1 style="font-size:1.2rem;">Rebuild the search index</h1>'
                   . '<p>This rebuilds the search index from the pages and documents on '
                   . 'the site. Search returns nothing for a second or two while it runs.</p>'
                   . '<form method="post">' . csrfField()
                   . '<button type="submit" style="background:#1a6b35;color:#fff;border:none;'
                   . 'border-radius:8px;padding:.7rem 1.2rem;font-size:1rem;font-weight:600;'
                   . 'cursor:pointer;">Rebuild it now</button></form></div>';
                exit;
            }
        }
    }
}

if ($reindexWhy === '') {
    http_response_code(403);
    die("403 Forbidden — sign in as an administrator, or pass ?token=…\n"
      . "(If you pasted the token and still see this: a token containing & + # or\n"
      . " a space is cut short by the URL. Sign in instead, or run it from cron.)\n");
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

/*
 * The index is NOT cleared here.
 *
 * It used to be the first thing this did, which was fine when the records
 * were twenty-six literals in this file. Building them now means fetching
 * every page over HTTP, and anything that can take time can be cut short —
 * by the proxy, by a timeout, by a page that hangs. Clearing first and then
 * being killed would leave the index empty and the site unsearchable, with
 * nothing on screen to say so.
 *
 * So: build everything first, and clear only once there is something to put
 * back. See the clear immediately before the first batch is sent.
 */

// ── 2. Configure index settings ───────────────────────────────────────────────
$log .= "Authorised by: {$reindexWhy}\n\n";
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
    // What members type when they are looking for the collaboration grant.
    // Almost nobody types its full name.
    ['objectID' => 'syn-collab',    'type' => 'synonym', 'synonyms' => ['collaboration grant', 'collab grant', 'collaboration days', 'collab days', 'collaboration funding', 'mentorship grant']],
    ['objectID' => 'syn-ca',        'type' => 'synonym', 'synonyms' => ['collective agreement', 'contract', 'the agreement', 'ca']],
    ['objectID' => 'syn-lou',       'type' => 'synonym', 'synonyms' => ['lou', 'loa', 'letter of understanding', 'letter of agreement', 'memorandum of agreement']],
    ['objectID' => 'syn-expense',   'type' => 'synonym', 'synonyms' => ['expense claim', 'reimbursement', 'expenses', 'mileage claim', 'receipt']],
    ['objectID' => 'syn-seniority', 'type' => 'synonym', 'synonyms' => ['seniority', 'seniority list', 'years of service']],
    ['objectID' => 'syn-layoff',    'type' => 'synonym', 'synonyms' => ['layoff', 'laid off', 'riffed', 'severance', 're-engagement']],
    ['objectID' => 'syn-prep2',     'type' => 'synonym', 'synonyms' => ['staff meeting', 'staff meetings', 'school meetings']],
];
$res  = algoliaRequest('POST', '/1/indexes/' . ALGOLIA_INDEX . '/synonyms/batch?replaceExistingSynonyms=true', $synonyms);
$log .= ((strpos($res['status'], '200') !== false) || (strpos($res['status'], '201') !== false))
    ? "  ✓ " . count($synonyms) . " synonym groups pushed.\n\n"
    : "  ✗ Synonyms failed: {$res['status']}\n\n";

/* ── Finding the pages, instead of being told them ──────────────────────────
 *
 * The list below used to be the whole index. A page was findable only if
 * someone remembered to add it here, and ten were missing — the collective
 * agreement and the Contract Assistant among them.
 *
 * So the site is now asked what pages it has. Each one is fetched over HTTP
 * and its real title, description and text are read: that is what a visitor
 * sees, which is what should be searchable. Fetching rather than including
 * also keeps the old warning in this file true — including a page here would
 * start its session and run its auth.
 *
 * The hand-written entries stay, as overrides. Discovery answers "is this page
 * in the index at all"; the overrides answer "and does it rank properly" —
 * which is where the collaboration grant's extra keywords live.
 */

/** Pages that are endpoints, actions or fragments — never results. */
function reindexSkipPage(string $file): bool {
    $never = [
        'reindex.php',            // would call itself
        'ask.php', 'ca-ask.php', 'ca-search.php', 'search.php',
        'go.php', 'track.php',
        'trustee-candidates-data.php', 'trustee-zones.php',
        'collab-grant-edit.php',  // reached by a private link from an email
        'library-bookmark.php', 'library-edit.php', 'library-resource.php',
        'library-saved.php', 'library-thumb-capture.php', 'library-upload.php',
        'index-offline.php', 'default.php',
        'newsletter.php',         // one issue; with no ?id= it bounces to the archive
    ];
    return in_array($file, $never, true);
}

/** Title, description and visible text of a fetched page. */
function reindexParsePage(string $html): array {
    $title = '';
    if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
        $title = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Every page ends "… — BVTU"; the suffix is noise in a result list.
        // preg_replace returns null on invalid UTF-8, and '' would pass the
        // "did we get a title" test below and index a nameless page.
        $title = (string)(preg_replace('~\s*[—–-]\s*(BVTU|Bulkley Valley Teachers.? Union)\s*$~iu', '', $title) ?? $title);
    }
    $desc = '';
    /* The closing quote must be the SAME quote that opened it. Matching
     * either one cut "About the Bulkley Valley Teachers' Union" down to
     * "…Teachers", and almost every description here contains Teachers'. */
    if (preg_match('~<meta[^>]+name=(["\'])description\1[^>]*content=(["\'])(.*?)\2~is', $html, $m)) {
        $desc = html_entity_decode(trim($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // The words on the page, minus the furniture that is on every page.
    $body = preg_replace('~<head\b.*?</head>~is', ' ', $html);   // title and meta have their own fields
    $body = preg_replace('~<(script|style|svg|noscript)\b.*?</\1>~is', ' ', $body);
    $body = preg_replace('~<(nav|header|footer)\b.*?</\1>~is', ' ', $body);
    $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $body = trim((string)(preg_replace('~\s+~u', ' ', $body) ?? $body));
    if (mb_strlen($body) > 2000) $body = mb_substr($body, 0, 2000);

    return ['title' => $title, 'description' => $desc, 'content' => $body];
}

/**
 * Fetch one page without following redirects.
 *
 * A redirect to the login page is not a failure — it is how a members-only
 * page announces itself, and the record wants that flag so the result carries
 * a Members badge instead of bouncing whoever taps it.
 */
function reindexFetch(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_USERAGENT      => 'BVTU-reindex/1.0',
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($raw === false) return ['ok' => false];

    $headers = substr($raw, 0, $hlen);
    $body    = substr($raw, $hlen);
    $gated   = $code >= 300 && $code < 400
            && preg_match('~^location:\s*\S*login\.php~im', $headers) === 1;

    return ['ok' => true, 'code' => $code, 'type' => $type, 'body' => $body, 'gated' => $gated];
}

// ── 3. Index public pages ─────────────────────────────────────────────────────
// Hardcoded records — avoids including PHP files which causes session/auth side-effects.
// Update content here whenever a page's text changes significantly.
$log .= "Indexing public pages...\n";

/*
 * Paths, not absolute URLs.
 *
 * Records used to be written with SITE_URL baked in. The site moved from
 * new.bvtu.ca to bvtu.ca and every result in the index went on pointing at
 * the old host until someone remembered to re-run this — which nobody did,
 * because nothing says to. A path cannot go stale when the domain changes,
 * and the browser resolves it against whichever host the member is on.
 *
 * Records written before this still carry the old host; bvtuLocalUrl() in
 * inc/site-url.php strips it at display time, so both shapes work.
 */
$baseUrl = '';

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
        // Taken from the page itself rather than invented, and carrying the
        // words members search with: "collab days", "release time", "how many
        // days", "apply". The old record said "funding application project"
        // and was findable only by someone who already knew its name.
        'description' => 'Apply for the BVTU Collaboration Grant — up to 3 release days a year for mentorship and professional collaboration between SD54 educators.',
        'content'     => 'collaboration grant collab grant collaboration days collab days mentorship '
                       . 'professional collaboration release time release days TTOC coverage three days '
                       . 'per year who can apply what is covered how to apply application form deadline '
                       . 'frequently asked questions professional development funding project partner '
                       . 'co-teaching classroom visit peer observation',
        'url'         => $baseUrl . '/collab-grant.php',
        'priority'    => 9,   // a thing members come looking for by name
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

    /* ── Pages that existed but were never indexed ────────────────────────
     *
     * The records in this file are written by hand, so a page is only
     * findable if someone remembered to add it here. Ten were missing,
     * including the collective agreement itself and the Contract Assistant —
     * a member searching "collective agreement" could not reach either. */
    [
        'objectID'    => 'page_collective-agreement',
        'type'        => 'page',
        'title'       => 'Collective Agreement',
        'description' => 'Download the SD54 Bulkley Valley Collective Agreement 2022–2025. Covers salary, leaves, working conditions, TTOC rights, and more.',
        'content'     => 'collective agreement contract SD54 BVTU 2022 2025 download pdf articles salary '
                       . 'leaves working conditions TTOC rights grievance seniority provincial local provisions',
        'url'         => $baseUrl . '/collective-agreement.php',
        'priority'    => 9,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_ca-assistant',
        'type'        => 'page',
        'title'       => 'Contract Assistant',
        'description' => 'Ask questions about the BVTU collective agreement and letters of understanding, and get a plain-language answer with the article number.',
        'content'     => 'contract assistant ask a question collective agreement letters of understanding '
                       . 'plain language answer article clause what does my contract say AI help',
        'url'         => $baseUrl . '/ca-assistant.php',
        'priority'    => 8,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_library',
        'type'        => 'page',
        'title'       => 'Resource Library',
        'description' => 'Teaching and union resources collected by BVTU, searchable and saveable.',
        'content'     => 'resource library resources teaching materials saved bookmarks documents links',
        'url'         => $baseUrl . '/library.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_curated',
        'type'        => 'page',
        'title'       => 'Curated Resources',
        'description' => 'Hand-picked teaching resources organised by grade band and subject — curated by BVTU educators.',
        'content'     => 'curated resources teaching grade band subject elementary secondary lesson planning recommended',
        'url'         => $baseUrl . '/curated.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_newsletter-archive',
        'type'        => 'page',
        'title'       => 'Newsletter Archive',
        'description' => 'Past issues of the BVTU newsletter.',
        'content'     => 'newsletter archive past issues bulletin updates president report back issues',
        'url'         => $baseUrl . '/newsletter-archive.php',
        'priority'    => 5,
        // It redirects a logged-out visitor to the login page, so without this
        // the result carries no "Members" badge and bounces whoever taps it.
        'members_only'=> true,
    ],
    [
        'objectID'    => 'page_about',
        'type'        => 'page',
        'title'       => 'About BVTU',
        'description' => 'About the Bulkley Valley Teachers\' Union — leadership, meetings, committees, and governance.',
        'content'     => 'about BVTU leadership executive table officers meetings committees governance '
                       . 'constitution bylaws general meeting staff representatives who we are',
        'url'         => $baseUrl . '/about.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_forum',
        'type'        => 'page',
        'title'       => 'All-Candidates Forum',
        'description' => 'Watch the BVTU all-candidates forum for the School District 54 trustee election.',
        'content'     => 'all candidates forum trustee election school district 54 video recording questions candidates debate',
        'url'         => $baseUrl . '/forum.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_trustee-candidates',
        'type'        => 'page',
        'title'       => 'Trustee Candidate Responses',
        'description' => 'Responses from SD54 school trustee candidates to the BVTU survey, published as submitted.',
        'content'     => 'trustee candidates responses survey school board election SD54 questionnaire positions',
        'url'         => $baseUrl . '/trustee-candidates.php',
        'priority'    => 6,
        'members_only'=> false,
    ],
    [
        'objectID'    => 'page_privacy',
        'type'        => 'page',
        'title'       => 'Privacy',
        'description' => 'How the BVTU website handles personal information.',
        'content'     => 'privacy policy personal information data collection cookies member accounts',
        'url'         => $baseUrl . '/privacy.php',
        'priority'    => 3,
        'members_only'=> false,
    ],
];

/* ── Merge what was found with what was written by hand ─────────────────────
 *
 * Discovery decides WHICH pages exist; the hand-written entry above decides
 * how one of them should read and rank. A discovered page with no entry is
 * indexed on what the page itself says — which is the whole point, because
 * that is what happens to the next page anyone adds.
 */
$overrides = [];
foreach ($records as $r) $overrides[$r['url']] = $r;

/*
 * The host serving this request, not the one in config.php.
 *
 * SITE_URL is exactly the setting that went stale when the site moved off
 * new.bvtu.ca. Crawling it would make every fetch a redirect, every page
 * "skipped", and the run would report success having discovered nothing.
 * On the command line there is no request to learn from, so config decides.
 */
if (!$isCli && !empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $crawlBase = $scheme . '://' . $_SERVER['HTTP_HOST'];
} else {
    $crawlBase = rtrim(defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca', '/');
}
$log .= "\nLooking for pages at {$crawlBase} …\n";

$discovered = [];
$files = glob(__DIR__ . '/*.php') ?: [];
sort($files);
foreach ($files as $path) {
    $file = basename($path);
    if (reindexSkipPage($file)) continue;

    $res = reindexFetch($crawlBase . '/' . $file);
    if (!$res['ok']) { $log .= "  ? {$file} — no answer\n"; continue; }

    if ($res['gated']) {
        // Behind a login. Indexed, flagged, and titled from the override if
        // there is one — the page itself will not tell us from a redirect.
        $url = '/' . $file;
        $base = $overrides[$url] ?? null;
        $discovered[$url] = [
            'objectID'    => 'page_' . pathinfo($file, PATHINFO_FILENAME),
            'type'        => 'page',
            'title'       => $base['title'] ?? ucwords(str_replace('-', ' ', pathinfo($file, PATHINFO_FILENAME))),
            'description' => $base['description'] ?? 'BVTU members-only page',
            'content'     => $base['content'] ?? '',
            'url'         => $url,
            'priority'    => $base['priority'] ?? 5,
            'members_only'=> true,
        ];
        $log .= "  + {$file} (members only)\n";
        continue;
    }

    if ($res['code'] !== 200 || stripos($res['type'], 'html') === false) {
        $log .= "  - {$file} — skipped (HTTP {$res['code']})\n";
        continue;
    }

    $parsed = reindexParsePage($res['body']);
    if ($parsed['title'] === '') { $log .= "  - {$file} — skipped (no title)\n"; continue; }

    $url  = ($file === 'index.php') ? '/' : '/' . $file;
    $base = $overrides[$url] ?? [];
    $discovered[$url] = [
        'objectID'    => 'page_' . ($file === 'index.php' ? 'index' : pathinfo($file, PATHINFO_FILENAME)),
        'type'        => 'page',
        'title'       => $base['title']       ?? $parsed['title'],
        'description' => $base['description'] ?? $parsed['description'],
        // Both: the page's own words, plus any search terms added by hand.
        'content'     => trim(($base['content'] ?? '') . ' ' . $parsed['content']),
        'url'         => $url,
        'priority'    => $base['priority']     ?? 5,
        'members_only'=> $base['members_only'] ?? false,
    ];
    $log .= "  + {$file} — {$discovered[$url]['title']}\n";
}

/*
 * Anything written by hand that the crawl did not reach is kept as it was.
 *
 * This is the safety net, and it is the whole reason the hand-written list is
 * still here: if the site cannot be fetched at all — DNS, a firewall, an
 * outage — discovery returns nothing, and without this the index would be
 * cleared and then filled with nothing, leaving the search dead until someone
 * noticed. A thin index beats an empty one.
 */
$found = count($discovered);
if ($found === 0) {
    /* Loud, because the run would otherwise finish green having indexed the
     * same twenty-six entries it indexed last time, and the one symptom —
     * new pages never turning up — looks like a search problem for months. */
    $log .= "\n  ✗ NOTHING was reachable at {$crawlBase}. Falling back to the\n"
          . "    hand-written list, so nothing is lost, but no new page will be\n"
          . "    found until this works. Check the address and that the site can\n"
          . "    fetch itself.\n";
}

$kept = 0;
foreach ($records as $r) {
    if (!isset($discovered[$r['url']])) { $discovered[$r['url']] = $r; $kept++; }
}
if ($kept) $log .= "  ({$kept} hand-written entries kept for pages the crawl did not reach)\n";

$records = array_values($discovered);
$log .= "\n" . count($records) . " page records.\n";

if (!$records) {
    // Nothing to put back, so nothing is taken away.
    $log .= "\n  ✗ No page records were built — the index has been left exactly as it was.\n";
} else {
    // Everything is in hand; now is the moment to empty the index.
    $log .= "\nClearing index '" . ALGOLIA_INDEX . "'...\n";
    $res  = algoliaRequest('POST', '/1/indexes/' . ALGOLIA_INDEX . '/clear', []);
    $log .= (strpos($res['status'], '200') !== false)
        ? "  ✓ Index cleared.\n" : "  ✗ Clear failed: {$res['status']}\n";
    algoliaAddBatch($records);
}

// ── 4. Index public PDFs ──────────────────────────────────────────────────────
$log .= "\nIndexing public documents...\n";

/* $root was used by both document sections and never defined anywhere, so
 * is_dir() was being asked about "/documents/" at the filesystem root, said
 * no, and the loops indexed nothing. The seven PDFs in documents/ have never
 * been in the search index — which is a large part of why searching for a
 * specific thing turned up a page about it rather than the document itself. */
$root = __DIR__;

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
