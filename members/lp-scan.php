<?php
/**
 * lp-scan.php — AJAX: upload receipt → Claude Vision → return JSON with LP-specific fields
 *
 * POST multipart: file=<receipt>  (optionally: grant_names=JSON, budget_line_names=JSON)
 * Returns JSON: { vendor, date, description, travel_amount, meals_amount, gifts_amount,
 *                 misc_amount, office_amount, phone_amount, total_amount,
 *                 suggested_grant, suggested_budget_line, concerns, flag,
 *                 saved_path, original_name }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/scan-concerns.php';
require_once __DIR__ . '/lp-db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}
/*
 * A file bigger than post_max_size arrives as nothing at all: PHP discards the
 * whole body, so $_FILES and $_POST are both empty and the old message said
 * "No file uploaded" — which is true and useless. CONTENT_LENGTH still tells us
 * something was sent.
 */
if (empty($_FILES['receipt'])) {
    $sent = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $cap  = trim((string)ini_get('post_max_size'));
    http_response_code(400);
    echo json_encode(['error' => $sent > 0
        ? 'That file was too big for the server to accept (its limit is ' . $cap . ').'
        : 'No file was received.']);
    exit;
}
// PHP's own upload errors, which are not all about size.
$uploadErr = (int)($_FILES['receipt']['error'] ?? UPLOAD_ERR_NO_FILE);
if ($uploadErr !== UPLOAD_ERR_OK) {
    $why = [
        UPLOAD_ERR_INI_SIZE   => 'That file is larger than the server accepts ('
                               . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_FORM_SIZE  => 'That file is larger than this form accepts.',
        UPLOAD_ERR_PARTIAL    => 'The upload was interrupted — try again.',
        UPLOAD_ERR_NO_FILE    => 'No file was received.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server has nowhere to put uploads. Tell the webmaster.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the file. Tell the webmaster.',
        UPLOAD_ERR_EXTENSION  => 'The server blocked that upload. Tell the webmaster.',
    ][$uploadErr] ?? 'The upload failed (code ' . $uploadErr . ').';
    http_response_code(400); echo json_encode(['error' => $why]); exit;
}
if (!defined('CLAUDE_API_KEY')) { echo json_encode(['error' => 'Claude API key not configured']); exit; }

lpEnsureTables();

$file     = $_FILES['receipt'];
$tmpPath  = $file['tmp_name'];
$origName = basename($file['name']);
$mimeType = mime_content_type($tmpPath);

$byMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
           'image/gif' => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heif',
           'application/pdf' => 'pdf'];
$okExt  = ['jpg','jpeg','png','webp','gif','heic','heif','pdf'];
$ext    = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

/*
 * The extension that gets SAVED is chosen here, never carried over from the
 * name the browser sent. A real JPEG called "shell.php" passes a check on
 * content alone, and writing it back as .php would drop an executable file
 * into the web root with only an .htaccess between it and being run — and
 * .htaccess is inert under nginx or with AllowOverride off.
 *
 * Content decides when it can. Extension is the fallback only for the case
 * that made a fallback necessary: iPhone HEIC often arrives as
 * application/octet-stream.
 */
if (isset($byMime[$mimeType])) {
    $ext = $byMime[$mimeType];
} elseif (in_array($ext, $okExt, true)) {
    $mimeType = 'image/jpeg'; // treat as jpeg for API
} else {
    echo json_encode(['error' => 'Unsupported file type. Please upload a photo or PDF.']); exit;
}
if ($file['size'] > 15 * 1024 * 1024) {
    echo json_encode(['error' => 'File too large. Maximum 15 MB.']); exit;
}

// Save file
$savedName = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
$savedPath = LP_RECEIPTS_DIR . $savedName;

if (!move_uploaded_file($tmpPath, $savedPath)) {
    echo json_encode(['error' => 'Failed to save file. Check server write permissions.']); exit;
}

// Build grant and budget line lists for the prompt
$grants      = lpGetGrants();
$budgetLines = lpGetBudgetLines();
$grantNames  = implode(', ', array_column($grants, 'name'));
$blNames     = implode(', ', array_column($budgetLines, 'name'));

// Build Claude API payload
$fileData = base64_encode(file_get_contents($savedPath));
$isPdf    = ($mimeType === 'application/pdf');

$contentBlock = $isPdf ? [
    'type'   => 'document',
    'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $fileData],
] : [
    'type'   => 'image',
    'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $fileData],
];

$concernRules = scanConcernPrompt();
$prompt = <<<PROMPT
You are reviewing a receipt for a teachers' union president's expense reimbursement.
Extract the following and return ONLY valid JSON — no markdown, no preamble:
{
  "vendor": string or null,
  "date": "YYYY-MM-DD" or null,
  "description": string or null,
  "travel_amount": number or null,
  "meals_amount": number or null,
  "gifts_amount": number or null,
  "misc_amount": number or null,
  "office_amount": number or null,
  "phone_amount": number or null,
  "total_amount": number or null,
  "suggested_grant": string or null,
  "suggested_budget_line": string or null,
  "concerns": string or null
}

Expense category rules:
- travel_amount: transportation, gas, parking, taxi, ferry, flights, hotels
- meals_amount: restaurants, food, catering, coffee
- gifts_amount: gift cards, presents, tokens of appreciation
- office_amount: office supplies, printing, equipment, software, subscriptions
- phone_amount: phone bills, internet charges
- misc_amount: anything else

Put the full receipt total in the most appropriate single category. Split across multiple only if clearly mixed (e.g. a receipt with food AND office supplies).

Available BCTF grants (suggest the best match or null):
{$grantNames}

Available BVTU budget lines (suggest the best match or null):
{$blNames}

For description: write a concise 3-7 word description of what was purchased (e.g. "Smithers Secondary school visit lunch" or "Office supplies — Staples").
{$concernRules}
PROMPT;

$payload = json_encode([
    'model'      => 'claude-sonnet-4-5',
    'max_tokens' => 600,
    'messages'   => [[
        'role'    => 'user',
        'content' => [$contentBlock, ['type' => 'text', 'text' => $prompt]],
    ]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'x-api-key: '         . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode([
        'error'         => 'Receipt scan failed — fill in fields manually.',
        'saved_path'    => $savedName,
        'original_name' => $origName,
    ]);
    exit;
}

$claudeData = json_decode($response, true);
$rawText    = $claudeData['content'][0]['text'] ?? '';

// Strip markdown fences
$rawText = preg_replace('/^```json\s*/i', '', trim($rawText));
$rawText = preg_replace('/\s*```$/', '', $rawText);
$extracted = json_decode($rawText, true);

if (!is_array($extracted)) {
    echo json_encode([
        'error'         => 'Could not parse receipt — fill in fields manually.',
        'saved_path'    => $savedName,
        'original_name' => $origName,
    ]);
    exit;
}

// Match suggested grant/budget line to IDs
$suggestedGrantId = null;
$suggestedBlId    = null;
if (!empty($extracted['suggested_grant'])) {
    foreach ($grants as $g) {
        if (stripos($g['name'], substr($extracted['suggested_grant'], 0, 10)) !== false ||
            stripos($extracted['suggested_grant'], substr($g['name'], 0, 10)) !== false) {
            $suggestedGrantId = $g['id'];
            break;
        }
    }
}
if (!empty($extracted['suggested_budget_line'])) {
    foreach ($budgetLines as $b) {
        if (stripos($b['name'], substr($extracted['suggested_budget_line'], 0, 10)) !== false ||
            stripos($extracted['suggested_budget_line'], substr($b['name'], 0, 10)) !== false) {
            $suggestedBlId = $b['id'];
            break;
        }
    }
}

// Flag logic
$flag = null;
if (scanConcern($extracted['concerns'] ?? null) !== null) $flag = 'flagged';
if (!empty($extracted['date'])) {
    $ts = strtotime($extracted['date']);
    if ($ts && ($ts < strtotime('-18 months') || $ts > time() + 86400)) {
        $flag = $flag ?? 'date_mismatch';
    }
}

echo json_encode([
    'vendor'                => $extracted['vendor']          ?? null,
    'date'                  => $extracted['date']            ?? null,
    'description'           => $extracted['description']     ?? null,
    'travel_amount'         => $extracted['travel_amount']   ?? null,
    'meals_amount'          => $extracted['meals_amount']    ?? null,
    'gifts_amount'          => $extracted['gifts_amount']    ?? null,
    'misc_amount'           => $extracted['misc_amount']     ?? null,
    'office_amount'         => $extracted['office_amount']   ?? null,
    'phone_amount'          => $extracted['phone_amount']    ?? null,
    'total_amount'          => $extracted['total_amount']    ?? null,
    'suggested_grant'       => $extracted['suggested_grant'] ?? null,
    'suggested_budget_line' => $extracted['suggested_budget_line'] ?? null,
    'suggested_grant_id'    => $suggestedGrantId,
    'suggested_bl_id'       => $suggestedBlId,
    'concerns'              => scanConcern($extracted['concerns'] ?? null),
    'flag'                  => $flag,
    'saved_path'            => $savedName,
    'original_name'         => $origName,
]);
