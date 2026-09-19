<?php
/**
 * contacts-sync-run.php — one batch of a full sync, called repeatedly by JS.
 *
 * Mailchimp's rate limit means a few hundred contacts take longer than PHP will
 * allow in one request, so the work is chunked and the browser drives the loop.
 * Each batch commits on its own, so closing the tab halfway leaves the finished
 * contacts synced and the rest simply not yet done — nothing half-written.
 *
 * Progress is an id cursor: each batch takes the next contacts by id and
 * reports the highest id it reached. That guarantees forward movement whatever
 * Mailchimp says — a contact it rejects is simply behind the cursor. Defining
 * progress by "has been synced" instead would leave a permanently failing
 * contact outstanding forever, and the browser would re-POST it endlessly.
 *
 * `since` is fixed at the start of each run and excludes anything synced after
 * it began — belt-and-braces alongside the cursor.
 *
 * The cursor lives in the browser, not here. While the page stays open it is
 * kept across an interrupted run, so pressing the button after a dropped
 * connection carries on from where it stopped rather than re-sending the whole
 * list. Reloading the page loses it and the next run starts from the top.
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

$after = (int)($_POST['after'] ?? 0);

$s = getDB()->prepare(
    "SELECT id, email FROM contacts
     WHERE status <> 'archived' AND id > ?
     AND (mailchimp_last_synced_at IS NULL OR mailchimp_last_synced_at < ?)
     ORDER BY id LIMIT {$BATCH}"
);
$s->execute([$after, $since]);
$rows = $s->fetchAll();

foreach ($rows as $r) {
    if ((int)$r['id'] > $after) $after = (int)$r['id'];
}

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

// Still ahead of the cursor. Failures sit behind it and are reported
// separately, so they neither block the run nor inflate what is left.
$rem = getDB()->prepare(
    "SELECT COUNT(*) FROM contacts
     WHERE status <> 'archived' AND id > ?
     AND (mailchimp_last_synced_at IS NULL OR mailchimp_last_synced_at < ?)"
);
$rem->execute([$after, $since]);
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
    'after'     => $after,
    'finished'  => $remaining === 0,
]);
