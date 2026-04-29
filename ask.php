<?php
/**
 * ask.php — Claude AI Q&A endpoint
 *
 * POST /ask.php   body: {"q": "your question"}
 * Returns:        {"answer": "...", "sources": [{"title":"...", "url":"..."}]}
 *
 * Requires CLAUDE_API_KEY defined in members/config.php (gitignored).
 * Add to config.php:
 *   define('CLAUDE_API_KEY', 'sk-ant-...');
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Rate limit (5 asks per minute per session) ────────────────────────────────
session_start();
$now = time();
$_SESSION['ask_times'] = array_filter(
    $_SESSION['ask_times'] ?? [],
    fn($t) => $t > $now - 60
);
if (count($_SESSION['ask_times']) >= 5) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests — please wait a moment.']);
    exit;
}
$_SESSION['ask_times'][] = $now;

// ── Load config ───────────────────────────────────────────────────────────────
$configPath = __DIR__ . '/members/config.php';
if (file_exists($configPath)) require_once $configPath;

$claudeKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';
if (!$claudeKey) {
    http_response_code(503);
    echo json_encode(['error' => 'AI search is not configured yet.']);
    exit;
}

// ── Validate input ────────────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
$q    = trim($body['q'] ?? '');

if (!$q) {
    http_response_code(400);
    echo json_encode(['error' => 'No question provided.']);
    exit;
}
if (mb_strlen($q) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Question too long (max 500 characters).']);
    exit;
}

// ── Query Algolia for relevant context ────────────────────────────────────────
const ALGOLIA_APP_ID    = 'IUEMJN3YMB';
const ALGOLIA_SEARCH_KEY = 'f743d9e8113b01fbb593d0d5ea592854';
const ALGOLIA_INDEX     = 'bvtu_content';

// Strip common question words to extract searchable keywords.
// "how much prep time am I entitled to?" → "prep time entitled"
$searchQuery = preg_replace(
    '/\b(how much|how many|how do i|how can i|what is|what are|what does|can i|do i|am i|when can|when do|where is|who is|is there|is it|tell me about|explain)\b/i',
    ' ', $q
);
$searchQuery = trim(preg_replace('/\s+/', ' ', $searchQuery)) ?: $q;

$hits    = [];
$sources = [];

// ── 1. Search CA directly from parsed JSON (no Algolia needed) ────────────────
// Score every CA article by keyword frequency and return the top matches.
$caPath = __DIR__ . '/../ca-content.json';
if (file_exists($caPath)) {
    $caArticles = json_decode(file_get_contents($caPath), true) ?: [];

    // Build word list — keep words 3+ chars, skip generic stop words
    $stopWords = ['the','and','for','are','was','that','this','with','have',
                  'from','they','will','been','has','its','not','but','can',
                  'you','your','our','their','what','how','much','many','who'];
    $words = preg_split('/\s+/', strtolower($searchQuery), -1, PREG_SPLIT_NO_EMPTY);
    $words = array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, $stopWords));
    $words = array_values($words);

    if ($words) {
        $scored = [];
        foreach ($caArticles as $idx => $article) {
            $titleLower   = strtolower($article['title']   ?? '');
            $contentLower = strtolower($article['content'] ?? '');
            $score = 0;
            foreach ($words as $word) {
                // Title matches are worth 5×, content matches 1×
                $score += substr_count($titleLower,   $word) * 5;
                $score += substr_count($contentLower, $word);
            }
            if ($score > 0) {
                $scored[] = ['score' => $score, 'idx' => $idx];
            }
        }
        usort($scored, fn($a, $b) => $b['score'] - $a['score']);

        foreach (array_slice($scored, 0, 5) as $s) {
            $art = $caArticles[$s['idx']];
            $hits[] = [
                'title'       => $art['title'],
                'content'     => $art['content'],
                'url'         => 'https://new.bvtu.ca/collective-agreement.php',
                'type'        => 'collective-agreement',
                'members_only'=> false,
            ];
        }
    }

    // CA source link (shown once regardless of how many articles matched)
    if ($hits) {
        $sources[] = [
            'title' => 'Collective Agreement (SD54–BVTU)',
            'url'   => 'https://new.bvtu.ca/collective-agreement.php',
            'type'  => 'collective-agreement',
        ];
    }
}

// ── 2. Algolia: general site pages only (not CA) ─────────────────────────────
$algoliaUrl = 'https://' . ALGOLIA_APP_ID . '-dsn.algolia.net/1/indexes/' . ALGOLIA_INDEX . '/query';
$algoliaCh  = curl_init($algoliaUrl);
curl_setopt_array($algoliaCh, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'query'                => $searchQuery,
        'hitsPerPage'          => 3,
        'filters'              => 'NOT type:collective-agreement',
        'attributesToRetrieve' => ['title', 'content', 'url', 'type', 'members_only'],
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'X-Algolia-Application-Id: ' . ALGOLIA_APP_ID,
        'X-Algolia-API-Key: ' . ALGOLIA_SEARCH_KEY,
    ],
]);
$algoliaResp = curl_exec($algoliaCh);
curl_close($algoliaCh);

if ($algoliaResp) {
    $algoliaData = json_decode($algoliaResp, true);
    foreach (($algoliaData['hits'] ?? []) as $h) {
        $hits[] = $h;
        $url = $h['url'] ?? '';
        if ($url) {
            $sources[] = ['title' => $h['title'] ?? '', 'url' => $url, 'type' => $h['type'] ?? 'page'];
        }
    }
}

// ── Build context string from hits ────────────────────────────────────────────
$contextBlocks = [];
foreach ($hits as $i => $hit) {
    $title   = $hit['title']   ?? '';
    $content = $hit['content'] ?? '';
    // Use snippet if full content is empty
    if (!$content && isset($hit['_snippetResult']['content']['value'])) {
        $content = $hit['_snippetResult']['content']['value'];
    }
    $content = strip_tags($content); // strip Algolia highlight <em> tags
    $content = preg_replace('/\s+/', ' ', trim($content));
    if ($title || $content) {
        $contextBlocks[] = "Source " . ($i + 1) . " — {$title}:\n{$content}";
    }
}
$context = implode("\n\n", $contextBlocks);

// ── Build Claude prompt ───────────────────────────────────────────────────────
$systemPrompt = <<<SYS
You are a knowledgeable assistant for the Bulkley Valley Teachers' Union (BVTU), a local of the BC Teachers' Federation representing educators in School District 54 (Smithers, Telkwa, and Houston, BC).

Your job is to answer members' and visitors' questions accurately and helpfully, using the context from the BVTU website provided below. Follow these guidelines:

- Answer in plain, friendly language — no markdown, no bullet symbols, no headers with # signs.
- If the context covers the question, answer from it directly and concisely (2–4 sentences is usually enough).
- If the context is incomplete or unclear, say what you do know and suggest the person contact the BVTU office or visit the relevant page.
- Never make up specific names, dates, dollar amounts, or policy details that aren't in the context.
- Keep answers under 150 words unless the question genuinely requires more.
SYS;

$userMessage = $context
    ? "Context from the BVTU website:\n\n{$context}\n\nMember question: {$q}"
    : "Member question: {$q}\n\n(No relevant pages were found in the search index for this query. Answer based on general BC teachers' union knowledge, and note that the member should verify specifics with BVTU directly.)";

// ── Call Claude API ───────────────────────────────────────────────────────────
$claudePayload = json_encode([
    'model'      => 'claude-haiku-4-5',
    'max_tokens' => 400,
    'system'     => $systemPrompt,
    'messages'   => [['role' => 'user', 'content' => $userMessage]],
]);

$claudeCh = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($claudeCh, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $claudePayload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "x-api-key: {$claudeKey}",
        'anthropic-version: 2023-06-01',
    ],
]);
$claudeResp = curl_exec($claudeCh);
curl_close($claudeCh);

if (!$claudeResp) {
    http_response_code(503);
    echo json_encode(['error' => 'AI is temporarily unavailable — please try again.']);
    exit;
}

$claudeData = json_decode($claudeResp, true);

// Handle API-level errors (bad key, quota, etc.)
if (isset($claudeData['error'])) {
    http_response_code(503);
    echo json_encode(['error' => 'AI error: ' . ($claudeData['error']['message'] ?? 'unknown')]);
    exit;
}

$answer = $claudeData['content'][0]['text'] ?? '';
if (!$answer) {
    http_response_code(503);
    echo json_encode(['error' => 'AI returned an empty response.']);
    exit;
}

// ── Return result ─────────────────────────────────────────────────────────────
echo json_encode([
    'answer'  => $answer,
    'sources' => $sources,
]);
