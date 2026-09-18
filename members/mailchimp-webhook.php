<?php
/**
 * mailchimp-webhook.php — receives subscription changes from Mailchimp.
 *
 * ON VERIFICATION, HONESTLY: Mailchimp's classic list webhooks are not signed.
 * There is no HMAC header to verify, so this cannot do signature verification
 * however much we would prefer to. What protects it instead:
 *
 *   1. a shared secret in the query string, compared with hash_equals
 *   2. POST only; GET returns 405 and never lists anything
 *   3. only the documented event types are acted on
 *   4. the only change possible is a subscription status on an address we
 *      already hold — it cannot create, rename or delete a contact
 *   5. it returns no contact data in any response, so it cannot be used to read
 *   6. writes are idempotent, so repeated deliveries are harmless
 *
 * Configure the URL in Mailchimp as:
 *   https://bvtu.ca/members/mailchimp-webhook.php?s=YOUR_MC_WEBHOOK_SECRET
 */
require_once __DIR__ . '/contacts-db.php';

// Mailchimp validates a new webhook by sending a GET and requiring a 200 — a
// 405 here makes it report the URL as unverifiable. Answer 200 with an empty
// body: it confirms the endpoint exists without listing or revealing anything.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    http_response_code(200);
    exit('');
}

// Anything that is neither GET nor POST is not part of the contract.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('');
}

if (!defined('MC_WEBHOOK_SECRET') || !MC_WEBHOOK_SECRET) {
    error_log('mailchimp-webhook: MC_WEBHOOK_SECRET is not set');
    http_response_code(503);
    exit('');
}

$given = (string)($_GET['s'] ?? '');
if (!hash_equals(MC_WEBHOOK_SECRET, $given)) {
    // Deliberately terse: an attacker learns nothing about why.
    error_log('mailchimp-webhook: rejected request with bad secret from ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    http_response_code(403);
    exit('');
}

$type = $_POST['type'] ?? '';
$data = $_POST['data'] ?? [];
if (!is_array($data)) { http_response_code(400); exit(''); }

$email = trim((string)($data['email'] ?? ''));
$newEmail = trim((string)($data['new_email'] ?? ''));

// Only the events we actually handle.
$statusFor = [
    'subscribe'   => 'subscribed',
    'unsubscribe' => 'unsubscribed',
    'cleaned'     => 'cleaned',
];

try {
    if (isset($statusFor[$type])) {
        if (!contactValidEmail($email)) { http_response_code(200); exit(''); }
        $c = contactFindByEmail($email);
        if ($c) {
            // Idempotent: applying the same status twice is a no-op.
            contactApplyMailchimpStatus((int)$c['id'], $statusFor[$type],
                                        $data['id'] ?? null, 'mailchimp-webhook');
        } else {
            // Someone subscribed who is not in our list. Record that it
            // happened without inventing a contact record from webhook data.
            contactAudit(null, 'webhook_unknown_contact', 'mailchimp-webhook', '', $type);
        }

    } elseif ($type === 'upemail') {
        // Mailchimp reports an address change. Follow it only when the new
        // address is not already someone else's, to protect the unique key.
        if (contactValidEmail($email) && contactValidEmail($newEmail)) {
            $c = contactFindByEmail($email);
            $clash = contactFindByEmail($newEmail);
            if ($c && !$clash) {
                getDB()->prepare(
                    "UPDATE contacts SET email=?, email_normalized=?, updated_by='mailchimp-webhook' WHERE id=?"
                )->execute([$newEmail, contactNormalizeEmail($newEmail), (int)$c['id']]);
                contactAudit((int)$c['id'], 'edited', 'mailchimp-webhook', 'email');
            } elseif ($c && $clash) {
                contactAudit((int)$c['id'], 'webhook_email_conflict', 'mailchimp-webhook');
            }
        }
    }
    // Any other event type is accepted and ignored.
} catch (Throwable $e) {
    // Log server-side; tell Mailchimp nothing useful.
    error_log('mailchimp-webhook failed: ' . $e->getMessage());
    http_response_code(500);
    exit('');
}

http_response_code(200);
exit('');
