<?php
/**
 * onedrive-connect.php — links the site to the President's OneDrive.
 *
 * Doubles as the OAuth redirect target: with no query string it starts the
 * consent flow; Microsoft returns here with ?code= to be exchanged for tokens.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/onedrive-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

startSession();

if (!odIsConfigured()) {
    header('Location: onedrive.php?error=' . urlencode('OneDrive is not set up yet — add the Azure details to config.php.'));
    exit;
}

// Disconnect
if (($_GET['action'] ?? '') === 'disconnect') {
    odDisconnect();
    header('Location: onedrive.php?notice=' . urlencode('OneDrive disconnected.'));
    exit;
}

// Microsoft reports a refused or failed consent here too
if (!empty($_GET['error'])) {
    $msg = $_GET['error_description'] ?? $_GET['error'];
    header('Location: onedrive.php?error=' . urlencode('Microsoft returned: ' . $msg));
    exit;
}

// ── Step 2: exchange the code ────────────────────────────────────────────────
if (!empty($_GET['code'])) {
    // state ties the callback to the session that began it
    if (empty($_GET['state']) || empty($_SESSION['od_state'])
        || !hash_equals($_SESSION['od_state'], $_GET['state'])) {
        header('Location: onedrive.php?error=' . urlencode('Sign-in could not be verified — please try again.'));
        exit;
    }
    unset($_SESSION['od_state']);

    $tok = odTokenRequest([
        'client_id'     => MS_CLIENT_ID,
        'client_secret' => MS_CLIENT_SECRET,
        'code'          => $_GET['code'],
        'grant_type'    => 'authorization_code',
        'redirect_uri'  => odRedirectUri(),
        'scope'         => MS_SCOPES,
    ]);
    if (!empty($tok['error'])) {
        header('Location: onedrive.php?error=' . urlencode($tok['error']));
        exit;
    }

    odSaveTokens($tok, $member['email']);

    // Record whose OneDrive it is, so the page can show it
    [$code, $me] = odGraph('GET', '/me?$select=displayName,userPrincipalName');
    $who = $code === 200
        ? ($me['userPrincipalName'] ?? $me['displayName'] ?? '')
        : '';
    if ($who) odSaveTokens($tok, $member['email'], $who);

    header('Location: onedrive.php?notice=' . urlencode('OneDrive connected' . ($who ? ' as ' . $who : '') . '.'));
    exit;
}

// ── Step 1: send them to Microsoft ───────────────────────────────────────────
$state = bin2hex(random_bytes(16));
$_SESSION['od_state'] = $state;

$params = http_build_query([
    'client_id'     => MS_CLIENT_ID,
    'response_type' => 'code',
    'redirect_uri'  => odRedirectUri(),
    'response_mode' => 'query',
    'scope'         => MS_SCOPES,
    'state'         => $state,
    // Force the consent screen so a refresh token is always issued
    'prompt'        => 'consent',
]);
header('Location: ' . MS_AUTH_BASE . '/' . odTenant() . '/oauth2/v2.0/authorize?' . $params);
exit;
