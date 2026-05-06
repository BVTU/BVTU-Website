<?php
/**
 * prod-scan.php — AJAX endpoint: upload receipt → Claude Vision → return JSON
 *
 * POST multipart/form-data: file=<receipt>
 * Returns JSON: { vendor, date, amount, category, concerns, flag, saved_path, original_name }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['receipt'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

if (!defined('CLAUDE_API_KEY')) {
    echo json_encode(['error' => 'Claude API key not configured']);
    exit;
}

$file     = $_FILES['receipt'];
$tmpPath  = $file['tmp_name'];
$origName = basename($file['name']);
$mimeType = mime_content_type($tmpPath);

// Validate file type
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
if (!in_array($mimeType, $allowedMimes)) {
    echo json_encode(['error' => 'Unsupported file type. Please upload a JPG, PNG, WebP, or PDF.']);
    exit;
}

// Validate file size (10MB max)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['error' => 'File too large. Maximum size is 10 MB.']);
    exit;
}

// Save to receipts directory
prodEnsureTables(); // ensures directory exists
$ext      = pathinfo($origName, PATHINFO_EXTENSION) ?: 'bin';
$savedName = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
$savedPath = PROD_RECEIPTS_DIR . $savedName;

if (!move_uploaded_file($tmpPath, $savedPath)) {
    echo json_encode(['error' => 'Failed to save file. Check server write permissions.']);
    exit;
}

// Build Claude API payload
$fileData  = base64_encode(file_get_contents($savedPath));
$isPdf     = ($mimeType === 'application/pdf');

$contentBlock = $isPdf ? [
    'type'   => 'document',
    'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $fileData],
] : [
    'type'   => 'image',
    'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $fileData],
];

$prompt = 'You are reviewing a receipt submitted for a professional development expense reimbursement. ' .
          'Extract the following fields and return ONLY valid JSON with no preamble or markdown: ' .
          '{"vendor": string or null, "date": "YYYY-MM-DD" or null, "total_amount": number or null, ' .
          '"likely_category": "conference" | "course" | "materials" | "travel" | "other" | null, ' .
          '"concerns": string or null}. ' .
          'In the concerns field, note anything suspicious: illegible receipt, food or alcohol, ' .
          'personal expense, or anything that looks out of place for a professional development claim.';

$payload = json_encode([
    'model'      => 'claude-sonnet-4-5',
    'max_tokens' => 400,
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
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode([
        'error'       => 'Receipt scan failed. You can still fill in the form manually.',
        'saved_path'  => $savedName,
        'original_name' => $origName,
    ]);
    exit;
}

$claudeData = json_decode($response, true);
$rawText    = $claudeData['content'][0]['text'] ?? '';

// Strip markdown code fences if Claude wrapped the JSON
$rawText = preg_replace('/^```json\s*/i', '', trim($rawText));
$rawText = preg_replace('/\s*```$/', '', $rawText);

$extracted = json_decode($rawText, true);

if (!is_array($extracted)) {
    echo json_encode([
        'error'          => 'Could not parse receipt data. Fill in the form manually.',
        'saved_path'     => $savedName,
        'original_name'  => $origName,
    ]);
    exit;
}

// Determine extraction flag (none by default)
$flag = null;
if (!empty($extracted['concerns'])) {
    $flag = 'suspicious_category';
}
// Date mismatch: if extracted date is more than 12 months ago or in the future
if (!empty($extracted['date'])) {
    $expTs  = strtotime($extracted['date']);
    $nowTs  = time();
    $yearAgo = strtotime('-12 months');
    if ($expTs < $yearAgo || $expTs > $nowTs) {
        $flag = $flag ?? 'date_mismatch';
    }
}

echo json_encode([
    'vendor'        => $extracted['vendor']         ?? null,
    'date'          => $extracted['date']            ?? null,
    'amount'        => $extracted['total_amount']    ?? null,
    'category'      => $extracted['likely_category'] ?? null,
    'concerns'      => $extracted['concerns']        ?? null,
    'flag'          => $flag,
    'saved_path'    => $savedName,
    'original_name' => $origName,
]);
