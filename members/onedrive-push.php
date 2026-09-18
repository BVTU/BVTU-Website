<?php
/**
 * onedrive-push.php — receives a photo and puts it in the chosen OneDrive folder.
 *
 * Authorised by the phone's upload token or an admin session, same as the BCTF
 * tool. Small files go up in one PUT; anything over 4MB uses an upload session,
 * which phone photos routinely need.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/onedrive-db.php';

header('Content-Type: application/json');

$token = $_POST['token'] ?? '';
$row   = $token ? odValidateUploadToken($token) : null;
$who   = null;
if ($row) {
    $who = $row['created_by'];
} else {
    startSession();
    if (isLoggedIn() && execIsAdmin(getMember()['email'])) $who = getMember()['email'];
}
if (!$who) { echo json_encode(['error' => 'Not authorised.']); exit; }

if (!odIsConfigured())     { echo json_encode(['error' => 'OneDrive is not set up.']); exit; }
if (!odAccessToken())      { echo json_encode(['error' => 'OneDrive needs reconnecting — open Membership/OneDrive on your computer.']); exit; }

if (empty($_FILES['photo']['tmp_name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No photo received.']); exit;
}

$folderId   = trim($_POST['folder_id']   ?? 'root');
$folderPath = trim($_POST['folder_path'] ?? 'OneDrive');
$tmp        = $_FILES['photo']['tmp_name'];

$info = @getimagesize($tmp);
if (!$info) { echo json_encode(['error' => 'That file is not an image.']); exit; }

$extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic'];
$ext = $extMap[$info['mime']] ?? null;
if (!$ext) { echo json_encode(['error' => 'Use a JPEG, PNG or WebP photo.']); exit; }

// A caption becomes the filename; otherwise date and time, so shots from one
// session don't collide.
$label = trim($_POST['label'] ?? '');
$slug  = $label !== '' ? preg_replace('/[^A-Za-z0-9 _-]+/', '', $label) : '';
$slug  = trim(preg_replace('/\s+/', '-', $slug), '-');
$name  = ($slug !== '' ? $slug : 'photo') . '-' . date('Y-m-d-His') . '.' . $ext;

$size = filesize($tmp);
$dest = $folderId === 'root'
    ? '/me/drive/root:/' . rawurlencode($name) . ':'
    : '/me/drive/items/' . rawurlencode($folderId) . ':/' . rawurlencode($name) . ':';

if ($size <= 4 * 1024 * 1024) {
    [$code, $data] = odGraph('PUT', $dest . '/content', [
        'body'        => file_get_contents($tmp),
        'contentType' => $info['mime'],
    ]);
} else {
    // Larger than Graph's simple-upload limit: open a session and send chunks.
    [$sc, $sess] = odGraph('POST', $dest . '/createUploadSession', [
        'json' => ['item' => ['@microsoft.graph.conflictBehavior' => 'rename']],
    ]);
    if ($sc >= 400 || empty($sess['uploadUrl'])) {
        echo json_encode(['error' => 'Could not start the upload.' ]); exit;
    }

    $chunk = 5 * 1024 * 1024;   // must be a multiple of 320 KiB
    $fh    = fopen($tmp, 'rb');
    $sent  = 0;
    $code  = 0;
    $data  = [];
    while ($sent < $size) {
        $buf  = fread($fh, $chunk);
        $len  = strlen($buf);
        $range = 'bytes ' . $sent . '-' . ($sent + $len - 1) . '/' . $size;
        [$code, $data] = odGraph('PUT', $sess['uploadUrl'], [
            'body'         => $buf,
            'contentType'  => 'application/octet-stream',
            'contentRange' => $range,
            'noAuth'       => true,   // the session URL carries its own auth
        ]);
        if ($code >= 400) break;
        $sent += $len;
    }
    fclose($fh);
}

if ($code >= 400 || empty($data['id'])) {
    $msg = $data['error']['message'] ?? 'Upload failed.';
    odRecordError($msg);
    echo json_encode(['error' => $msg]);
    exit;
}

odLogUpload($data['name'] ?? $name, $folderPath, $data['webUrl'] ?? '', $size, $who);

echo json_encode([
    'ok'       => true,
    'name'     => $data['name'] ?? $name,
    'folder'   => $folderPath,
    'web_url'  => $data['webUrl'] ?? '',
]);
