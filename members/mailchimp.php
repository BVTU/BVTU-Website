<?php
/**
 * mailchimp.php — all Mailchimp Marketing API access, in one place.
 *
 * Division of responsibility, enforced here rather than by convention:
 *   we own   name, school, role — pushed up as merge fields
 *   they own subscription state — only ever read down
 *
 * mcPushContact() never sends a `status`. It sets status_if_new only, so
 * creating a contact cannot subscribe anyone and updating one cannot resurrect
 * a person who unsubscribed. Resubscribing is a separate, deliberate call.
 *
 * config.php (server-only, gitignored):
 *   define('MC_API_KEY',        '...');      // ends in -us21 or similar
 *   define('MC_LIST_ID',        '...');      // Audience ID
 *   define('MC_WEBHOOK_SECRET', '...');      // any long random string you choose
 */
require_once __DIR__ . '/contacts-db.php';

function mcConfigured(): bool {
    return defined('MC_API_KEY') && MC_API_KEY
        && defined('MC_LIST_ID') && MC_LIST_ID;
}

/** The data centre is the suffix of the API key — no separate setting needed. */
function mcServerPrefix(): string {
    if (defined('MC_SERVER_PREFIX') && MC_SERVER_PREFIX) return MC_SERVER_PREFIX;
    $parts = explode('-', MC_API_KEY);
    return end($parts) ?: 'us1';
}

/** Mailchimp addresses members by the MD5 of the lower-cased email. */
function mcSubscriberHash(string $email): string {
    return md5(contactNormalizeEmail($email));
}

/**
 * One HTTP call. Returns [httpCode, decodedBody].
 * Errors are returned, never thrown, so a Mailchimp outage cannot take a page
 * down or lose an edit that was already saved locally.
 */
function mcRequest(string $method, string $path, ?array $body = null): array {
    if (!mcConfigured()) return [0, ['detail' => 'Mailchimp is not configured.']];

    $url = 'https://' . mcServerPrefix() . '.api.mailchimp.com/3.0' . $path;
    $ch  = curl_init($url);
    $headers = ['Content-Type: application/json'];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_USERPWD        => 'anystring:' . MC_API_KEY,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) return [0, ['detail' => 'Could not reach Mailchimp: ' . $err]];
    return [$code, json_decode($raw, true) ?: []];
}

/** Short, safe message for the UI and the audit log — never the raw payload. */
function mcErrorMessage(int $code, array $body): string {
    $detail = $body['detail'] ?? ($body['title'] ?? 'Unknown error');
    if ($code === 401) $detail = 'Mailchimp rejected the API key.';
    if ($code === 404) $detail = 'Audience or contact not found in Mailchimp.';
    return 'HTTP ' . $code . ': ' . substr($detail, 0, 200);
}

/**
 * Push our fields for one contact.
 *
 * Uses PUT (add-or-update) so we needn't know whether they already exist.
 * status_if_new is 'transactional' — present in the audience, but NOT
 * subscribed to marketing. Being in our contact list is not consent, and an
 * import must never manufacture it.
 */
function mcPushContact(array $c): array {
    if (!mcConfigured()) return ['ok' => false, 'error' => 'Mailchimp is not configured.'];
    if (!contactValidEmail($c['email'])) return ['ok' => false, 'error' => 'Contact has no valid email.'];

    $school = '';
    if (!empty($c['school_id'])) {
        foreach (contactSchools() as $s) {
            if ((int)$s['id'] === (int)$c['school_id']) { $school = $s['name']; break; }
        }
    }
    if ($school === '') $school = $c['school_other'] ?? '';

    $payload = [
        'email_address' => $c['email'],
        'status_if_new' => 'transactional',
        'merge_fields'  => [
            'FNAME'  => (string)($c['preferred_name'] ?: $c['first_name']),
            'LNAME'  => (string)$c['last_name'],
            'SCHOOL' => (string)$school,
            'ROLE'   => (string)($c['role'] ?? ''),
        ],
    ];

    [$code, $body] = mcRequest(
        'PUT',
        '/lists/' . MC_LIST_ID . '/members/' . mcSubscriberHash($c['email']),
        $payload
    );

    if ($code >= 200 && $code < 300) {
        return [
            'ok'     => true,
            'status' => $body['status'] ?? 'unknown',
            'mc_id'  => $body['id'] ?? null,
        ];
    }
    return ['ok' => false, 'error' => mcErrorMessage($code, $body)];
}

/**
 * Which of the merge fields we push are missing from the audience.
 *
 * FNAME and LNAME exist in every audience; SCHOOL and ROLE do not. Mailchimp
 * rejects an entire request that names an unknown merge field, so without these
 * every sync fails with "invalid merge fields" and no hint as to which. Checked
 * up front so the setup page can name them.
 */
function mcMissingMergeFields(): array {
    if (!mcConfigured()) return [];
    [$code, $body] = mcRequest('GET', '/lists/' . MC_LIST_ID . '/merge-fields?count=100&fields=merge_fields.tag');
    if ($code < 200 || $code >= 300) return [];

    $have = [];
    foreach ($body['merge_fields'] ?? [] as $f) {
        if (!empty($f['tag'])) $have[strtoupper($f['tag'])] = true;
    }
    $missing = [];
    foreach (['SCHOOL' => 'School', 'ROLE' => 'Role'] as $tag => $label) {
        if (empty($have[$tag])) $missing[$tag] = $label;
    }
    return $missing;
}

/** Read Mailchimp's current view of one contact, without changing anything. */
function mcFetchStatus(string $email): array {
    [$code, $body] = mcRequest('GET', '/lists/' . MC_LIST_ID . '/members/' . mcSubscriberHash($email));
    if ($code === 404) return ['ok' => true, 'status' => 'unknown', 'mc_id' => null];
    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'status' => $body['status'] ?? 'unknown', 'mc_id' => $body['id'] ?? null];
    }
    return ['ok' => false, 'error' => mcErrorMessage($code, $body)];
}

/**
 * Save the contact's fields to Mailchimp and record the outcome.
 * A failure is stored and queued for retry — never silently dropped.
 */
function mcSyncContact(int $contactId, string $actor = 'sync'): array {
    $c = contactGet($contactId);
    if (!$c) return ['ok' => false, 'error' => 'Contact not found.'];

    $res = mcPushContact($c);
    if ($res['ok']) {
        contactApplyMailchimpStatus($contactId, $res['status'], $res['mc_id'], $actor);
        return ['ok' => true, 'status' => $res['status']];
    }
    contactMarkSyncFailed($contactId, $res['error'], $actor);
    return $res;
}

/**
 * Explicit, deliberate resubscribe — the only path that may set a status.
 * Mailchimp refuses to move a compliance-unsubscribed address back itself, in
 * which case 'pending' asks the person to confirm, which is the correct outcome.
 */
function mcResubscribe(int $contactId, string $actor): array {
    $c = contactGet($contactId);
    if (!$c) return ['ok' => false, 'error' => 'Contact not found.'];

    [$code, $body] = mcRequest(
        'PUT',
        '/lists/' . MC_LIST_ID . '/members/' . mcSubscriberHash($c['email']),
        ['email_address' => $c['email'], 'status' => 'pending']   // confirmed opt-in
    );
    if ($code >= 200 && $code < 300) {
        contactApplyMailchimpStatus($contactId, $body['status'] ?? 'pending', $body['id'] ?? null, $actor);
        contactAudit($contactId, 'resubscribe_requested', $actor);
        return ['ok' => true, 'status' => $body['status'] ?? 'pending'];
    }
    $msg = mcErrorMessage($code, $body);
    contactMarkSyncFailed($contactId, $msg, $actor);
    return ['ok' => false, 'error' => $msg];
}
