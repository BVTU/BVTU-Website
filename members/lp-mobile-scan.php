<?php
/**
 * lp-mobile-scan.php — AJAX: token-gated receipt upload from phone
 *
 * POST multipart: token=<token>  receipt=<file>
 * Returns JSON: { ok, id, description, amount, date, concerns, saved_path, original_name, error? }
 */
require_once __DIR__ . '/lp-db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok' => false, 'error' => 'Method not allowed']); exit;
}

$token = trim($_POST['token'] ?? '');
if (!$token) {
    http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Missing token']); exit;
}

lpEnsureTables();

$tokenRow = lpValidateUploadToken($token);
if (!$tokenRow) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'This upload link has expired. Ask the LP to open the voucher again to get a fresh QR code.']);
    exit;
}

$voucherId = (int)$tokenRow['voucher_id'];
$voucher   = lpGetVoucher($voucherId);
if (!$voucher) {
    http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Voucher not found']); exit;
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
$savedName = date('Ymd-His') . '-mob-' . bin2hex(random_bytes(4)) . '.' . $ext;
$savedPath = LP_RECEIPTS_DIR . $savedName;

if (!move_uploaded_file($tmpPath, $savedPath)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save file. Contact admin.']); exit;
}

// ── Claude scan ───────────────────────────────────────────────────────────────
$scanData = [];

if (defined('CLAUDE_API_KEY')) {
    $grants      = lpGetGrants();
    $budgetLines = lpGetBudgetLines();
    $grantNames  = implode(', ', array_column($grants, 'name'));
    $blNames     = implode(', ', array_column($budgetLines, 'name'));
    $fileData    = base64_encode(file_get_contents($savedPath));
    $isPdf       = ($mimeType === 'application/pdf');

    $contentBlock = $isPdf ? [
        'type'   => 'document',
        'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $fileData],
    ] : [
        'type'   => 'image',
        'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $fileData],
    ];

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

Available BCTF grants: {$grantNames}
Available BVTU budget lines: {$blNames}
For description: write a concise 3-7 word description (e.g. "Smithers school visit lunch").
For concerns: note anything suspicious. Null if clean.
PROMPT;

    $payload = json_encode([
        'model'      => 'claude-sonnet-4-5',
        'max_tokens' => 600,
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
            // Resolve grant/BL IDs
            $grantId = null;
            $blId    = null;
            if (!empty($extracted['suggested_grant'])) {
                foreach ($grants as $g) {
                    if (stripos($g['name'], substr($extracted['suggested_grant'], 0, 10)) !== false ||
                        stripos($extracted['suggested_grant'], substr($g['name'], 0, 10)) !== false) {
                        $grantId = $g['id']; break;
                    }
                }
            }
            if (!empty($extracted['suggested_budget_line'])) {
                foreach ($budgetLines as $b) {
                    if (stripos($b['name'], substr($extracted['suggested_budget_line'], 0, 10)) !== false ||
                        stripos($extracted['suggested_budget_line'], substr($b['name'], 0, 10)) !== false) {
                        $blId = $b['id']; break;
                    }
                }
            }

            $flag = null;
            if (!empty($extracted['concerns'])) $flag = 'flagged';
            if (!empty($extracted['date'])) {
                $ts = strtotime($extracted['date']);
                if ($ts && ($ts < strtotime('-18 months') || $ts > time() + 86400)) {
                    $flag = $flag ?? 'date_mismatch';
                }
            }

            $scanData = [
                'vendor'              => $extracted['vendor']          ?? null,
                'date'                => $extracted['date']            ?? null,
                'description'         => $extracted['description']     ?? null,
                'travel_amount'       => $extracted['travel_amount']   ?? null,
                'meals_amount'        => $extracted['meals_amount']    ?? null,
                'gifts_amount'        => $extracted['gifts_amount']    ?? null,
                'misc_amount'         => $extracted['misc_amount']     ?? null,
                'office_amount'       => $extracted['office_amount']   ?? null,
                'phone_amount'        => $extracted['phone_amount']    ?? null,
                'total_amount'        => $extracted['total_amount']    ?? null,
                'suggested_grant_id'  => $grantId,
                'suggested_bl_id'     => $blId,
                'concerns'            => $extracted['concerns']        ?? null,
                'flag'                => $flag,
            ];
        }
    }
}

// Store in pending receipts
$pendingId = lpAddPendingReceipt($voucherId, $savedName, $origName, $scanData);

// Build a human-readable summary for the phone success screen
$desc   = $scanData['description'] ?? null;
$amount = null;
foreach (['travel_amount','meals_amount','gifts_amount','misc_amount','office_amount','phone_amount'] as $k) {
    if (!empty($scanData[$k]) && (float)$scanData[$k] > 0) {
        $amount = (float)$scanData[$k]; break;
    }
}
if (!$amount && !empty($scanData['total_amount'])) $amount = (float)$scanData['total_amount'];

echo json_encode([
    'ok'          => true,
    'id'          => $pendingId,
    'description' => $desc,
    'amount'      => $amount ? number_format($amount, 2) : null,
    'date'        => $scanData['date'] ?? null,
    'concerns'    => $scanData['concerns'] ?? null,
    'saved_path'  => $savedName,
]);
