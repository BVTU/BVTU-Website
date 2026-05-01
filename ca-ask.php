<?php
/**
 * ca-ask.php — Collective Agreement + LOU AI Assistant endpoint
 *
 * POST /ca-ask.php
 * Body: {"question": "...", "history": [{"role":"user|assistant","content":"..."}]}
 * Returns: {"answer":"...", "sources":[...], "usage":{...}}
 *
 * Token alerts require DB_HOST, DB_NAME, DB_USER, DB_PASS in members/config.php.
 * Optional: TOKEN_ALERT_THRESHOLD (default 500000), TOKEN_ALERT_EMAIL (default lp54@bctf.ca)
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Rate limit (10 asks per minute per session) ───────────────────────────────
session_start();
$now = time();
$_SESSION['ca_ask_times'] = array_filter(
    $_SESSION['ca_ask_times'] ?? [],
    fn($t) => $t > $now - 60
);
if (count($_SESSION['ca_ask_times']) >= 10) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many requests — please wait a moment before asking again.']);
    exit;
}
$_SESSION['ca_ask_times'][] = $now;

// ── Load config ───────────────────────────────────────────────────────────────
$configPath = __DIR__ . '/members/config.php';
if (file_exists($configPath)) require_once $configPath;

$claudeKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';
if (!$claudeKey) {
    http_response_code(503);
    echo json_encode(['error' => 'AI assistant is not configured yet.']);
    exit;
}

// ── Validate input ────────────────────────────────────────────────────────────
$body     = json_decode(file_get_contents('php://input'), true);
$question = trim($body['question'] ?? '');
$history  = $body['history']  ?? [];

if (!$question) {
    http_response_code(400);
    echo json_encode(['error' => 'No question provided.']);
    exit;
}
if (mb_strlen($question) > 600) {
    http_response_code(400);
    echo json_encode(['error' => 'Question too long (max 600 characters).']);
    exit;
}
if (!is_array($history)) $history = [];
// Keep last 10 turns (20 messages) to control token usage
$history = array_slice($history, -20);

// ── Keyword extraction ────────────────────────────────────────────────────────
$stopWords = ['the','and','for','are','was','that','this','with','have','from',
              'they','will','been','has','its','not','but','can','you','your',
              'our','their','what','how','much','many','who','when','where',
              'why','does','did','get','tell','about','also','into','more',
              'per','than','been','just','then','some','any'];

$searchable = preg_replace(
    '/\b(how much|how many|how do i|how can i|what is|what are|what does|can i|do i|am i|when can|when do|where is|who is|is there|is it|tell me about|explain|what about|does the|should i)\b/i',
    ' ', $question
);
$words = preg_split('/\s+/', strtolower($searchable), -1, PREG_SPLIT_NO_EMPTY);
$words = array_values(array_filter($words, fn($w) => strlen($w) >= 3 && !in_array($w, $stopWords)));

// ── Search Collective Agreement ───────────────────────────────────────────────
$hits    = [];
$sources = [];

$caPath = __DIR__ . '/ca-content.json';
if (file_exists($caPath) && $words) {
    $caArticles = json_decode(file_get_contents($caPath), true) ?: [];
    $scored = [];
    foreach ($caArticles as $idx => $article) {
        $titleLower   = strtolower($article['title']   ?? '');
        $contentLower = strtolower($article['content'] ?? '');
        $score = 0;
        foreach ($words as $word) {
            $score += substr_count($titleLower,   $word) * 5;
            $score += substr_count($contentLower, $word);
        }
        if ($score > 0) $scored[] = ['score' => $score, 'idx' => $idx];
    }
    usort($scored, fn($a, $b) => $b['score'] - $a['score']);
    foreach (array_slice($scored, 0, 4) as $s) {
        $art    = $caArticles[$s['idx']];
        $hits[] = [
            'title'   => $art['title'],
            'content' => $art['content'],
            'source'  => 'Collective Agreement',
        ];
    }
    if ($hits) {
        $sources[] = ['title' => 'Local Collective Agreement (SD54–BVTU)', 'url' => 'collective-agreement.php', 'type' => 'ca'];
    }
}

// ── Search Letters of Understanding ──────────────────────────────────────────
$louPath = __DIR__ . '/lou-content.json';
if (file_exists($louPath) && $words) {
    $louDocs = json_decode(file_get_contents($louPath), true) ?: [];
    $scored  = [];
    foreach ($louDocs as $idx => $doc) {
        $titleLower   = strtolower($doc['title']    ?? '');
        $contentLower = strtolower($doc['content']  ?? '');
        $keywordStr   = strtolower(implode(' ', $doc['keywords'] ?? []));
        $score = 0;
        foreach ($words as $word) {
            $score += substr_count($titleLower,   $word) * 5;
            $score += substr_count($keywordStr,   $word) * 3;
            $score += substr_count($contentLower, $word);
        }
        if ($score > 0) $scored[] = ['score' => $score, 'idx' => $idx];
    }
    usort($scored, fn($a, $b) => $b['score'] - $a['score']);
    $louAdded = 0;
    foreach (array_slice($scored, 0, 3) as $s) {
        if ($louAdded >= 3) break;
        $doc    = $louDocs[$s['idx']];
        $hits[] = [
            'title'   => $doc['title'],
            'content' => $doc['content'],
            'source'  => 'Letter of Understanding / Settlement',
        ];
        $sources[] = [
            'title' => $doc['title'],
            'url'   => $doc['file'],
            'type'  => 'lou',
        ];
        $louAdded++;
    }
}

// ── Build context string ──────────────────────────────────────────────────────
$contextBlocks = [];
foreach ($hits as $hit) {
    $content = preg_replace('/\s+/', ' ', trim($hit['content'] ?? ''));
    // Truncate very long blocks to keep token count manageable
    if (mb_strlen($content) > 1800) {
        $content = mb_substr($content, 0, 1800) . '...';
    }
    $contextBlocks[] = "[Source: {$hit['source']}] {$hit['title']}:\n{$content}";
}
$context = implode("\n\n---\n\n", $contextBlocks);

// ── System prompt ─────────────────────────────────────────────────────────────
$systemPrompt = <<<SYS
You are a knowledgeable assistant for the Bulkley Valley Teachers' Union (BVTU), a local of the BC Teachers' Federation representing educators in School District 54 (Smithers, Telkwa, and Houston, BC).

Your primary role is to help teachers understand their collective agreement, letters of understanding, and signed settlements that together form their contract with the school district.

Guidelines:
- Answer questions accurately based ONLY on the context provided.
- When referencing the collective agreement, cite the article number (e.g., "Under Article D.4.5...").
- When referencing a Letter of Understanding or settlement, name it by year and topic (e.g., "The 2024 LOA on elementary prep time states...").
- If the context doesn't fully answer the question, clearly say so and recommend the teacher contact the BVTU president for confirmation.
- Be concise and practical — teachers want actionable answers they can use.
- Write in plain, direct language. Do not use markdown symbols, bullet symbols, or # headers.
- Never invent specific article numbers, dollar amounts, dates, or policy details not found in the provided context.
- For multi-turn conversations, use context from previous messages where relevant.
- Keep answers focused and readable. Complex questions may need more detail.
- Always note: you provide information only, not legal advice. For individual workplace situations, recommend consulting the BVTU.
SYS;

// ── Build Claude messages (multi-turn) ───────────────────────────────────────
$messages = [];
foreach ($history as $h) {
    $role    = $h['role']    ?? '';
    $content = trim($h['content'] ?? '');
    if (in_array($role, ['user', 'assistant']) && $content !== '') {
        $messages[] = ['role' => $role, 'content' => $content];
    }
}

// Current turn: inject context with the question
$currentMsg = $context
    ? "Relevant collective agreement and LOU context:\n\n{$context}\n\n---\n\nMy question: {$question}"
    : "My question: {$question}\n\n(No specific articles were found matching this query. Please answer based on general BC teachers' collective agreement knowledge and note the member should verify specifics with BVTU directly.)";

$messages[] = ['role' => 'user', 'content' => $currentMsg];

// ── Call Claude API ───────────────────────────────────────────────────────────
$claudePayload = json_encode([
    'model'      => 'claude-haiku-4-5',
    'max_tokens' => 600,
    'system'     => $systemPrompt,
    'messages'   => $messages,
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

// ── Token usage tracking & alert ──────────────────────────────────────────────
$usage        = $claudeData['usage'] ?? [];
$inputTokens  = (int)($usage['input_tokens']  ?? 0);
$outputTokens = (int)($usage['output_tokens'] ?? 0);
$totalTokens  = $inputTokens + $outputTokens;

if ($totalTokens > 0 && defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );

        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_usage (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            endpoint      VARCHAR(50)  NOT NULL DEFAULT 'ca-ask',
            input_tokens  INT          NOT NULL DEFAULT 0,
            output_tokens INT          NOT NULL DEFAULT 0,
            total_tokens  INT          NOT NULL DEFAULT 0,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Log this request
        $stmt = $pdo->prepare(
            "INSERT INTO api_usage (endpoint, input_tokens, output_tokens, total_tokens)
             VALUES ('ca-ask', :in, :out, :tot)"
        );
        $stmt->execute([':in' => $inputTokens, ':out' => $outputTokens, ':tot' => $totalTokens]);

        // Monthly total
        $monthStart = date('Y-m-01 00:00:00');
        $monthTotal = (int)$pdo->query(
            "SELECT COALESCE(SUM(total_tokens),0) FROM api_usage WHERE created_at >= '{$monthStart}'"
        )->fetchColumn();

        $alertThreshold = defined('TOKEN_ALERT_THRESHOLD') ? (int)TOKEN_ALERT_THRESHOLD : 500000;
        $alertEmail     = defined('TOKEN_ALERT_EMAIL')     ? TOKEN_ALERT_EMAIL          : 'lp54@bctf.ca';
        $prevTotal      = $monthTotal - $totalTokens;

        // Determine if we just crossed a threshold
        $crossed = null;
        foreach ([1.0, 0.9, 0.8] as $pct) {
            $threshold = (int)($alertThreshold * $pct);
            if ($monthTotal >= $threshold && $prevTotal < $threshold) {
                $crossed = $pct;
                break;
            }
        }

        if ($crossed !== null) {
            $pctLabel = (int)($crossed * 100) . '%';
            $subject  = "BVTU CA Assistant — Token alert: {$pctLabel} of monthly budget used";
            $body     = "Monthly Claude API token usage has reached {$pctLabel} of the configured budget.\n\n"
                      . "Month-to-date: " . number_format($monthTotal) . " tokens\n"
                      . "Budget: " . number_format($alertThreshold) . " tokens\n"
                      . "Usage: " . round($monthTotal / $alertThreshold * 100, 1) . "%\n\n"
                      . "To adjust the alert threshold, set TOKEN_ALERT_THRESHOLD in members/config.php.\n"
                      . "To review usage, query: SELECT DATE(created_at) AS day, SUM(total_tokens) AS tokens FROM api_usage GROUP BY day ORDER BY day DESC;";
            mail($alertEmail, $subject, $body,
                 "From: noreply@bvtu.ca\r\nContent-Type: text/plain; charset=UTF-8\r\n");
        }

    } catch (Exception $e) {
        // Silently fail — logging errors must never break the API response
        error_log('BVTU ca-ask token logging error: ' . $e->getMessage());
    }
}

// ── Return result ─────────────────────────────────────────────────────────────
echo json_encode([
    'answer'  => $answer,
    'sources' => array_values(array_unique($sources, SORT_REGULAR)),
    'usage'   => ['input' => $inputTokens, 'output' => $outputTokens, 'total' => $totalTokens],
]);
