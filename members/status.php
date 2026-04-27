<?php
// Returns login status as JSON — used by site.js to update the nav
require_once __DIR__ . '/auth.php';
startSession();
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (isLoggedIn()) {
    $member = getMember();
    echo json_encode(['loggedIn' => true, 'name' => $member['name']]);
} else {
    echo json_encode(['loggedIn' => false]);
}
