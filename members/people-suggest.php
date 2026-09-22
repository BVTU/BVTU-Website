<?php
/**
 * people-suggest.php — up to eight people matching what has been typed.
 *
 * Exists so the search box can jump straight to a person instead of making the
 * admin load a filtered page and then click. Read-only, admin-only, and it
 * returns the same fields the list shows — never a phone number, note or
 * anything else the caller did not already have on screen.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/contacts-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

sendPrivateHeaders();
header('Content-Type: application/json');

$q = reqStr('q');
// Two characters before we go to the database: one letter matches most of the
// roster and the dropdown would be noise.
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

list($rows) = contactSearch(['q' => $q, 'sort' => 'name', 'dir' => 'asc'], 1, 10);

$out = [];
foreach (array_slice($rows, 0, 8) as $c) {
    $out[] = [
        'id'    => (int)$c['id'],
        'name'  => contactDisplayName($c),
        'email' => $c['email'],
    ];
}
echo json_encode($out);
