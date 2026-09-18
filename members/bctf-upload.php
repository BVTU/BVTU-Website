<?php
/**
 * bctf-upload.php — receives one photographed membership form.
 *
 * Reached either from the phone (token in the URL, no session) or from the
 * desktop page while signed in. Saves the image, then asks Claude to read the
 * member's surname off the form so the attachment can be named lastname_bctf.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/bctf-db.php';

header('Content-Type: application/json');

$token    = $_POST['token'] ?? '';
$tokenRow = $token ? bctfValidateUploadToken($token) : null;

// Phone uploads authenticate with the token; desktop uploads with the session.
$uploader = null;
if ($tokenRow) {
    $uploader = $tokenRow['created_by'];
} else {
    startSession();
    if (isLoggedIn()) {
        $m = getMember();
        if (execIsAdmin($m['email'])) $uploader = $m['email'];
    }
}
if (!$uploader) { echo json_encode(['error' => 'Not authorised.']); exit; }

if (empty($_FILES['photo']['tmp_name'])) {
    echo json_encode(['error' => 'No photo received.']); exit;
}
if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Upload failed — try again.']); exit;
}

$tmp  = $_FILES['photo']['tmp_name'];
$info = @getimagesize($tmp);
if (!$info) { echo json_encode(['error' => 'That file is not an image.']); exit; }

$mime = $info['mime'];
$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic'];
$ext = $extMap[$mime] ?? null;
if (!$ext) { echo json_encode(['error' => 'Use a JPEG, PNG or WebP photo.']); exit; }

bctfEnsureTables();
$stored = 'bctf_' . uniqid('', true) . '.' . $ext;
$dest   = BCTF_FORMS_DIR . $stored;
if (!move_uploaded_file($tmp, $dest)) {
    echo json_encode(['error' => 'Could not save the photo.']); exit;
}
@chmod($dest, 0640);

// ── Read the surname off the form ────────────────────────────────────────────
$lastName = '';
$autoNamed = false;
$noticeMsg = '';

if (defined('CLAUDE_API_KEY') && CLAUDE_API_KEY) {
    // Claude's vision input caps at 5MB per image; skip the call rather than
    // send something that will be rejected.
    if (filesize($dest) <= 5 * 1024 * 1024) {
        $payload = json_encode([
            'model'      => 'claude-opus-5',
            'max_tokens' => 200,
            // Structured output beats parsing prose or stripping code fences.
            'output_config' => [
                'format' => [
                    'type' => 'json_schema',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'last_name' => [
                                // Plain string rather than a ["string","null"]
                                // union, which strict schema validation rejects.
                                'type' => 'string',
                                'description' => "The member's surname exactly as written, or an empty string if not legible",
                            ],
                        ],
                        'required' => ['last_name'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => [
                        'type' => 'base64', 'media_type' => $mime,
                        'data' => base64_encode(file_get_contents($dest)),
                    ]],
                    ['type' => 'text', 'text' =>
                        "This is a BCTF membership form for a new teacher.\n\n"
                      . "Return the member's LAST NAME (surname) only — not their first name, "
                      . "not the school name, not a witness or signatory. If the form is too "
                      . "blurry or the surname is not visible, return an empty string rather than guessing."],
                ],
            ]],
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
            CURLOPT_TIMEOUT => 45,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $text = $data['content'][0]['text'] ?? '';
            $parsed = json_decode($text, true);
            $candidate = is_array($parsed) ? ($parsed['last_name'] ?? null) : null;
            if (is_string($candidate) && trim($candidate) !== '') {
                $lastName  = trim($candidate);
                $autoNamed = true;
            } else {
                $noticeMsg = 'Could not read the surname — type it in.';
            }
        } else {
            $noticeMsg = 'Name lookup unavailable — type the surname in.';
        }
    } else {
        $noticeMsg = 'Photo too large to read automatically — type the surname in.';
    }
} else {
    $noticeMsg = 'Name lookup not configured — type the surname in.';
}

$id = bctfAddForm($stored, $_FILES['photo']['name'] ?? '', $lastName, $autoNamed, $uploader);

echo json_encode([
    'ok'         => true,
    'id'         => $id,
    'last_name'  => $lastName,
    'auto_named' => $autoNamed,
    'filename'   => bctfSlugName($lastName ?: 'unnamed') . '_bctf.' . $ext,
    'notice'     => $noticeMsg,
]);
