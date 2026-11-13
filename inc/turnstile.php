<?php
/**
 * inc/turnstile.php — Cloudflare Turnstile, for the public forms.
 *
 * The honeypot, the three-second timing check and the keyword list on
 * contact.php are the defences bots were built to walk past years ago, and the
 * office inbox showed it. Turnstile is the one that actually costs a bot
 * something: Cloudflare scores the browser and most real people never see a
 * puzzle at all.
 *
 * Two deliberate choices, both about not locking people out:
 *
 *   1. With no keys in config.php this does nothing and says so, so the form
 *      keeps working exactly as before. Shipping the widget and the keys as one
 *      step would have taken the contact form down until somebody made a
 *      Cloudflare account.
 *   2. If Cloudflare itself cannot be reached, the message goes through,
 *      flagged as unverified. This is a teachers' union: a member writing about
 *      a grievance with a filing deadline must be able to reach the office. One
 *      spam email is the cheaper failure.
 *
 * Setup: Cloudflare dashboard → Turnstile → Add site. Both keys into
 * members/config.php (see members/config.example.php).
 */

function turnstileConfigured(): bool {
    return defined('TURNSTILE_SITE_KEY') && trim((string)TURNSTILE_SITE_KEY) !== ''
        && defined('TURNSTILE_SECRET_KEY') && trim((string)TURNSTILE_SECRET_KEY) !== '';
}

/** The widget. Renders nothing at all when no keys are set. */
function turnstileWidget(): string {
    if (!turnstileConfigured()) return '';
    return '<div class="cf-turnstile" data-sitekey="'
         . htmlspecialchars((string)TURNSTILE_SITE_KEY, ENT_QUOTES) . '"'
         . ' data-theme="light" data-size="flexible"></div>';
}

/** The script tag that draws it. Also nothing without keys. */
function turnstileScript(): string {
    if (!turnstileConfigured()) return '';
    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"'
         . ' async defer></script>';
}

/**
 * Check the token the widget put in the form.
 *
 * Returns one of:
 *   'off'        — not configured; the caller carries on as if this file
 *                  were not here.
 *   'ok'         — Cloudflare says this was a person.
 *   'unverified' — Cloudflare could not be reached. Caller should send the
 *                  message anyway and mark it.
 *   'failed'     — Cloudflare says no, or the token was missing or stale.
 */
function turnstileCheck(?string $token, string $ip = ''): string {
    if (!turnstileConfigured()) return 'off';

    $token = trim((string)$token);
    if ($token === '') return 'failed';
    // Cloudflare's own ceiling on the token; anything longer is not one.
    if (strlen($token) > 2048) return 'failed';

    $post = ['secret' => (string)TURNSTILE_SECRET_KEY, 'response' => $token];
    if ($ip !== '') $post['remoteip'] = $ip;

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($post),
        CURLOPT_RETURNTRANSFER => true,
        // Short on purpose. Somebody is sitting there waiting for the page.
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $raw  = curl_exec($ch);
    $err  = curl_errno($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== 0 || $code !== 200 || !is_string($raw)) return 'unverified';

    $json = json_decode($raw, true);
    // A 200 that is not the shape we expect is Cloudflare having a bad day,
    // not a verdict on the person typing. Treat it as unreachable.
    if (!is_array($json) || !array_key_exists('success', $json)) return 'unverified';

    return $json['success'] === true ? 'ok' : 'failed';
}

/** The visitor's address, for the optional remoteip field. */
function turnstileClientIp(): string {
    $ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}
