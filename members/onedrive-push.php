<?php
/**
 * onedrive-push.php — receives a photo or document and puts it in the chosen OneDrive folder.
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
    // A share is logged under the name it was issued to, so the upload list says
    // who actually did it rather than crediting the president with everything.
    $label = trim((string)($row['label'] ?? ''));
    $who   = ($row['kind'] ?? 'self') === 'share' && $label !== ''
           ? $label . ' (shared link)'
           : $row['created_by'];
    odTouchToken($token);
} else {
    startSession();
    if (isLoggedIn() && execIsAdmin(getMember()['email'])) $who = getMember()['email'];
}
if (!$who) { echo json_encode(['error' => 'Not authorised.']); exit; }

if (!odIsConfigured())     { echo json_encode(['error' => 'OneDrive is not set up.']); exit; }
if (!odAccessToken())      { echo json_encode(['error' => 'OneDrive needs reconnecting — open Membership/OneDrive on your computer.']); exit; }

if (empty($_FILES['photo']['tmp_name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file was received.']); exit;
}

$folderId   = trim($_POST['folder_id']   ?? 'root');
$folderPath = trim($_POST['folder_path'] ?? 'OneDrive');
$tmp        = $_FILES['photo']['tmp_name'];

/*
 * A photo, or a document picked from the phone's files. The saved extension
 * always comes from this list, never from the name the browser sent. The one
 * place the sent name helps is Office's newer formats: they are zip files
 * underneath, and libmagic often reports exactly that, so a zip is accepted
 * only when the name says docx/xlsx/pptx — and is then filed as that.
 */
$info = @getimagesize($tmp);
$origName = (string)($_FILES['photo']['name'] ?? '');
$origExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($info) {
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/heic'=>'heic'];
    $mime = $info['mime'];
    $ext  = $extMap[$mime] ?? null;
    if (!$ext) { echo json_encode(['error' => 'Use a JPEG, PNG or WebP photo.']); exit; }
} else {
    $mime = function_exists('mime_content_type') ? (string)@mime_content_type($tmp) : '';
    $docMap = [
        'application/pdf'    => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.ms-excel'      => 'xls',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];
    $ext = $docMap[$mime] ?? null;
    // Old Office files: one container format for all three, so the name picks.
    if (!$ext && in_array($mime, ['application/CDFV2', 'application/x-ole-storage'], true)
        && in_array($origExt, ['doc', 'xls', 'ppt'], true)) {
        $ext = $origExt;
    }
    if (!$ext && in_array($mime, ['application/zip', 'application/octet-stream'], true)
        && in_array($origExt, ['docx', 'xlsx', 'pptx'], true)) {
        $ext = $origExt;
    }
    if (!$ext) { echo json_encode(['error' => 'Use a photo, PDF, or Word, Excel or PowerPoint file.']); exit; }
    $byExt = array_flip($docMap);
    $byExt['doc'] = 'application/msword'; $byExt['xls'] = 'application/vnd.ms-excel';
    $byExt['ppt'] = 'application/vnd.ms-powerpoint';
    $mime = $byExt[$ext];
}
$info = ['mime' => $mime];

// A caption becomes the filename; otherwise date and time, so shots from one
// session don't collide.
$label = trim($_POST['label'] ?? '');
$slug  = $label !== '' ? preg_replace('/[^A-Za-z0-9 _-]+/', '', $label) : '';
$slug  = trim(preg_replace('/\s+/', '-', $slug), '-');
// A document with no caption keeps its own name; a photo's is only "IMG_4021".
if ($slug === '' && !in_array($ext, ['jpg', 'png', 'webp', 'heic'], true)) {
    $own  = preg_replace('/[^A-Za-z0-9 _-]+/', '', pathinfo($origName, PATHINFO_FILENAME));
    $slug = substr(trim(preg_replace('/\s+/', '-', $own), '-'), 0, 80);
}
$name  = ($slug !== '' ? $slug : 'photo') . '-' . date('Y-m-d-His') . '.' . $ext;

$size = filesize($tmp);
[$code, $data] = odUploadFile($folderId, $name, $tmp, $info['mime']);

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
