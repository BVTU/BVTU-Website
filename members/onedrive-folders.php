<?php
/**
 * onedrive-folders.php — the picker's folder listing, and making a new one.
 *
 * GET  ?id=<itemId>            → { ok, folders: [...] }
 * POST action=create, parent, name → { ok, folder: {id,name} }
 *                                  or { ok:false, error, existing?: {id,name} }
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/onedrive-db.php';

header('Content-Type: application/json');

$token = reqStr('token') ?: reqPostStr('token');
$row   = $token ? odValidateUploadToken($token) : null;
$ok    = (bool)$row;

// Browsing and creating are not the same permission — see odTokenMayCreate().
$mayCreate = odTokenMayCreate($row);

if (!$ok) {
    startSession();
    $ok = isLoggedIn() && execIsAdmin(getMember()['email']);
    $mayCreate = $ok;
}
if (!$ok) { echo json_encode(['error' => 'Not authorised.']); exit; }

if (!odAccessToken()) { echo json_encode(['error' => 'OneDrive needs reconnecting.']); exit; }

if (($_POST['action'] ?? '') === 'create') {
    if (!$mayCreate) {
        echo json_encode(['ok' => false,
            'error' => 'This link can upload into a folder, but not make new ones.']);
        exit;
    }
    // A session-authenticated POST is a form post and needs the usual token;
    // an upload-token POST carries its own credential in the token itself.
    if (!$row && !csrfValid()) {
        echo json_encode(['ok' => false, 'error' => 'That form expired. Reload and try again.']);
        exit;
    }
    // reqPostStr, not $_POST directly: parent[]=root is fatal on PHP 8 and on
    // 7.4 prints a warning ahead of the JSON, which breaks the reply itself.
    $made = odCreateFolder(reqPostStr('parent') ?: 'root', reqPostStr('name'));
    echo json_encode($made);
    exit;
}

$id      = reqStr('id') ?: 'root';
$folders = odListFolders($id);

echo json_encode(['ok' => true, 'folders' => array_map(function ($f) {
    return ['id' => $f['id'], 'name' => $f['name'], 'count' => $f['folder']['childCount'] ?? 0];
}, $folders)]);
