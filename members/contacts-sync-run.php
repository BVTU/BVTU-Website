<?php
/**
 * contacts-sync-run.php — one batch of a full sync, called repeatedly by JS.
 *
 * Mailchimp's rate limit means a few hundred contacts take longer than PHP will
 * allow in one request, so the work is chunked and the browser drives the loop.
 * Each batch commits on its own, so closing the tab halfway leaves the finished
 * contacts synced and the rest simply not yet done — nothing half-written.
 *
 * Resumable without storing a cursor: "still to do" is defined as not synced
 * since this run began, so every batch naturally picks up where the last ended.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/contacts-db.php';
require_once __DIR__ . '/mailchimp.php';

header('Content-Type: application/json');

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { http_response_code(403); echo json_encode(['error' => 'Access denied.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST only.']); exit; }
csrfCheck();

if (!mcConfigured()) { echo json_encode(['error' => 'Mailchimp is not configured.']); exit; }

contactsEnsureTables();

$since     = trim($_POST['since'] ?? '');
$direction = ($_POST['direction'] ?? 'push') === 'pull' ? 'pull' : 'push';
if ($since === '') $since = date('Y-m-d H:i:s');

// Batch small enough to finish well inside the request timeout even if
// Mailchimp is slow, large enough not to spend the time on round trips.
$BATCH = 20;

$s = getDB()->prepare(
    "SELECT id, email FROM contacts
     WHERE status <> 'archived'
     AND (mailchimp_last_synced_at IS NULL OR mailchimp_last_synced_at < ?)
     ORDER BY id LIMIT {$BATCH}"
);
$s->execute([$since]);
$rows = $s->fetchAll();

$done = 0; $failed = 0;
foreach ($rows as $c) {
    if ($direction === 'pull') {
        $r = mcFetchStatus($c['email']);
        if ($r['ok']) { contactApplyMailchimpStatus((int)$c['id'], $r['status'], $r['mc_id'], $member['email']); $done++; }
        else          { contactMarkSyncFailed((int)$c['id'], $r['error'], $member['email']); $failed++; }
    } else {
        $r = mcSyncContact((int)$c['id'], $member['email']);
        $r['ok'] ? $done++ : $failed++;
    }
    usleep(120000);   // ~8/sec, comfortably inside Mailchimp's limit
}

// How many are still outstanding for this run
$rem = getDB()->prepare(
    "SELECT COUNT(*) FROM contacts
     WHERE status <> 'archived'
     AND (mailchimp_last_synced_at IS NULL OR mailchimp_last_synced_at < ?)"
);
$rem->execute([$since]);
$remaining = (int)$rem->fetchColumn();

if ($remaining === 0) {
    contactAudit(null, 'manual_sync', $member['email'], '', $direction . ' completed');
}

echo json_encode([
    'ok'        => true,
    'since'     => $since,
    'processed' => $done,
    'failed'    => $failed,
    'remaining' => $remaining,
    'finished'  => $remaining === 0,
]);
