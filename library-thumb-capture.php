<?php
/**
 * library-thumb-capture.php
 * Accepts a base64-encoded image from the PDF.js canvas and saves it as the
 * resource thumbnail. Only the uploader or an admin may call this.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/library-db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'error' => 'not logged in']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$member  = getMember();
$isAdmin = libIsAdmin($member['email']);
$id      = (int)($_POST['id'] ?? 0);
$dataUrl = $_POST['image'] ?? '';

$resource = $id ? libGetResource($id) : null;
if (!$resource) {
    echo json_encode(['ok' => false, 'error' => 'resource not found']);
    exit;
}
if ($resource['uploader_email'] !== $member['email'] && !$isAdmin) {
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

// Parse the data URL: "data:image/jpeg;base64,/9j/..."
if (!preg_match('/^data:image\/(jpeg|png|webp);base64,(.+)$/s', $dataUrl, $m)) {
    echo json_encode(['ok' => false, 'error' => 'invalid image data']);
    exit;
}
$ext     = $m[1] === 'jpeg' ? 'jpg' : $m[1];
$imgData = base64_decode($m[2]);
if (!$imgData || strlen($imgData) < 100) {
    echo json_encode(['ok' => false, 'error' => 'decode failed']);
    exit;
}

// Validate it's actually an image
$tmpPath = sys_get_temp_dir() . '/bvtu_thumb_' . uniqid() . '.' . $ext;
file_put_contents($tmpPath, $imgData);
$info = @getimagesize($tmpPath);
if (!$info) {
    @unlink($tmpPath);
    echo json_encode(['ok' => false, 'error' => 'not a valid image']);
    exit;
}

// Delete old thumbnail if one exists
if (!empty($resource['thumbnail_path'])) {
    $old = LIB_THUMB_DIR . basename($resource['thumbnail_path']);
    if (file_exists($old)) @unlink($old);
}

// Save to lib-thumbs/
if (!is_dir(LIB_THUMB_DIR)) mkdir(LIB_THUMB_DIR, 0755, true);
$stored = 'thumb_' . uniqid('', true) . '.' . $ext;
$dest   = LIB_THUMB_DIR . $stored;

if (!rename($tmpPath, $dest)) {
    // rename may fail across filesystems — fall back to copy+unlink
    if (!copy($tmpPath, $dest)) {
        @unlink($tmpPath);
        echo json_encode(['ok' => false, 'error' => 'could not save file']);
        exit;
    }
    @unlink($tmpPath);
}
chmod($dest, 0644);

// Update just the thumbnail column
getDB()->prepare("UPDATE library_resources SET thumbnail_path=? WHERE id=?")
       ->execute([$stored, $id]);

echo json_encode(['ok' => true, 'path' => LIB_THUMB_URL . $stored]);
