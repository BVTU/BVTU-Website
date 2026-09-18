<?php
/**
 * bctf-rename.php — correct the surname on a captured form.
 * Accepts the phone's upload token or an admin session, same as bctf-upload.php.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/bctf-db.php';

header('Content-Type: application/json');

$token = $_POST['token'] ?? '';
$ok    = (bool)($token ? bctfValidateUploadToken($token) : null);
if (!$ok) {
    startSession();
    $ok = isLoggedIn() && execIsAdmin(getMember()['email']);
}
if (!$ok) { echo json_encode(['error' => 'Not authorised.']); exit; }

$id   = (int)($_POST['id'] ?? 0);
$name = trim($_POST['last_name'] ?? '');
if (!$id) { echo json_encode(['error' => 'Missing form.']); exit; }

bctfRenameForm($id, $name);

$s = getDB()->prepare("SELECT saved_path FROM bctf_forms WHERE id=?");
$s->execute([$id]);
$row = $s->fetch();
$ext = $row ? (strtolower(pathinfo($row['saved_path'], PATHINFO_EXTENSION)) ?: 'jpg') : 'jpg';

echo json_encode(['ok' => true, 'filename' => bctfSlugName($name) . '_bctf.' . $ext]);
