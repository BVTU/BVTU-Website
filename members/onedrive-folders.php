<?php
/**
 * onedrive-folders.php — JSON folder listing for the picker.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/onedrive-db.php';

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';
$ok    = (bool)($token ? odValidateUploadToken($token) : null);
if (!$ok) {
    startSession();
    $ok = isLoggedIn() && execIsAdmin(getMember()['email']);
}
if (!$ok) { echo json_encode(['error' => 'Not authorised.']); exit; }

if (!odAccessToken()) { echo json_encode(['error' => 'OneDrive needs reconnecting.']); exit; }

$id      = trim($_GET['id'] ?? 'root');
$folders = odListFolders($id);

echo json_encode(['ok' => true, 'folders' => array_map(function ($f) {
    return ['id' => $f['id'], 'name' => $f['name'], 'count' => $f['folder']['childCount'] ?? 0];
}, $folders)]);
