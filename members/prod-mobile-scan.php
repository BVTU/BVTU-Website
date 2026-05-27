<?php
/**
 * prod-mobile-scan.php — AJAX: token-gated receipt upload from phone (Pro-D)
 *
 * POST multipart: token=<token>  receipt=<file>
 * Returns JSON: { ok, id, vendor, date, amount, category, concerns, saved_path, original_name, error? }
 */
require_once __DIR__ . '/prod-db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}

$token = trim($_POST['token'] ?? '');
if (!$token) {
    http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Missing token']); exit;
}

prodEnsureTables();

$tokenRow = prodValidateUploadToken($token);
if (!$tokenRow) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'This upload link has expired. Please reload the final claim form to get a fresh QR code.']);
    exit;
}

$requestId = (int)$tokenRow['request_id'];
$s = getDB()->prepare("SELECT id, activity_description FROM prod_requests WHERE id=? LIMIT 1");
$s->execute([$requestId]);
$req = $s->fetch();
if (!$req) {
    http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Request not found']); exit;
}

if (empty($_FILES['receipt'])) {
    http_response_code(400); echo json_encode(['ok' => false, 'error' => 'No file uploaded']); exit;
}

$file     = $_FILES['receipt'];
$tmpPath  = $file['tmp_name'];
$origName = basename($file['name']);
$mimeType = mime_content_type($tmpPath);

$allowedMimes = ['image/jpeg','image/png','image/webp','image/gif','image/heic','image/heif','application/pdf'];
if (!in_array($mimeType, $allowedMimes)) {
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif','heic','heif','pdf'])) {
        echo json_encode(['ok' => false, 'error' => 'Unsupported file type. Please upload a photo or PDF.']); exit;
    }
    $mimeType = 'image/jpeg';
}
if ($file['size'] > 15 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'File too large (max 15 MB).']); exit;
}

// Save file
$ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION)) ?: 'jpg';
$savedName = date('Ymd-His') . '-prod-mob-' . bin2hex(random_bytes(4)) . '.' . $ext;
$savedPath = PROD_RECEIPTS_DIR . $savedName;

if (!move_uploaded_file($tmpPath, $savedPath)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save file. Contact admin.']); exit;
}

// ── Claude scan ───────────────────────────────────────────────────────────────
$scanData = [];

if (defined('CLAUDE_API_KEY')) {
    $fileData = base64_encode(file_get_contents($savedPath));
    $isPdf    = ($mimeType === 'application/pdf');

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
        'messages'   => [['role' => 'user', 'content' => [$contentBlock, ['type' => 'text', 'text' => $prompt]]]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $claudeData = json_decode($response, true);
        $rawText    = $claudeData['content'][0]['text'] ?? '';
        $rawText    = preg_replace('/^```json\s*/i', '', trim($rawText));
        $rawText    = preg_replace('/\s*```$/', '', $rawText);
        $extracted  = json_decode($rawText, true);

        if (is_array($extracted)) {
            $flag = null;
            if (!empty($extracted['concerns'])) $flag = 'suspicious_category';
            if (!empty($extracted['date'])) {
                $ts      = strtotime($extracted['date']);
                $yearAgo = strtotime('-12 months');
                if ($ts && ($ts < $yearAgo || $ts > time() + 86400)) {
                    $flag = $flag ? $flag : 'date_mismatch';
                }
            }

            $scanData = [
                'vendor'          => $extracted['vendor']          ?? null,
                'date'            => $extracted['date']            ?? null,
                'total_amount'    => $extracted['total_amount']    ?? null,
                'likely_category' => $extracted['likely_category'] ?? null,
                'concerns'        => $extracted['concerns']        ?? null,
                'flag'            => $flag,
            ];
        }
    }
}

// Store in pending receipts
$pendingId = prodAddPendingReceipt($requestId, $savedName, $origName, $scanData);

echo json_encode([
    'ok'           => true,
    'id'           => $pendingId,
    'vendor'       => $scanData['vendor']          ?? null,
    'date'         => $scanData['date']            ?? null,
    'amount'       => isset($scanData['total_amount']) ? $scanData['total_amount'] : null,
    'category'     => $scanData['likely_category'] ?? null,
    'concerns'     => $scanData['concerns']        ?? null,
    'saved_path'   => $savedName,
    'original_name' => $origName,
]);
