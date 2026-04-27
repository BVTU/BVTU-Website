<?php
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['member_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function loginMember(array $member): void {
    startSession();
    session_regenerate_id(true);
    $_SESSION['member_id']    = $member['id'];
    $_SESSION['member_name']  = $member['name'];
    $_SESSION['member_email'] = $member['email'];
}

function logoutMember(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
}

function getMember(): ?array {
    startSession();
    if (empty($_SESSION['member_id'])) return null;
    return [
        'id'    => $_SESSION['member_id'],
        'name'  => $_SESSION['member_name'],
        'email' => $_SESSION['member_email'],
    ];
}
