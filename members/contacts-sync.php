<?php
/**
 * contacts-sync.php — Mailchimp connection state, retries, and a bounded pull.
 *
 * Nothing here runs on a normal page load. Every call is an explicit action, so
 * browsing contacts never touches the API or spends rate limit.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/contacts-db.php';
require_once __DIR__ . '/mailchimp.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

sendPrivateHeaders();
contactsEnsureTables();

$notice = htmlspecialchars($_GET['notice'] ?? '');
$error  = htmlspecialchars($_GET['error']  ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    // Bulk work now runs through contacts-sync-run.php, a batch at a time.
}

$failed  = contactSyncFailures();
$counts  = contactCounts();
$configured = mcConfigured();

// One cheap call to prove the credentials work, rather than assuming.
$connOk = false; $connMsg = ''; $listName = '';
if ($configured) {
    [$code, $body] = mcRequest('GET', '/lists/' . MC_LIST_ID . '?fields=id,name,stats.member_count');
    $connOk  = ($code >= 200 && $code < 300);
    $listName = $connOk ? ($body['name'] ?? '') : '';
    $connMsg = $connOk ? '' : mcErrorMessage($code, $body);
}
$missingFields = $connOk ? mcMissingMergeFields() : [];
$webhookUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bvtu.ca') . '/members/mailchimp-webhook.php?s=YOUR_SECRET';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Mailchimp — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:820px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .page-header h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 0; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    .notice { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#166534;margin:1rem 0; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#991b1b;margin:1rem 0; }
    h2.sec { font-size:1rem;font-weight:800;color:var(--gray-800);margin:2rem 0 .75rem;
             padding-bottom:.4rem;border-bottom:2px solid var(--accent); }
    .pcard { background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:1.25rem; }
    .state { display:flex;gap:1rem;align-items:center;flex-wrap:wrap; }
    .dot { width:10px;height:10px;border-radius:50%;flex-shrink:0; }
    .dot.on{background:#16a34a;} .dot.off{background:#dc2626;} .dot.warn{background:#d97706;}
    .state .txt { flex:1;min-width:220px;font-size:.9rem; }
    .state .txt strong { display:block;color:var(--gray-800); }
    .state .txt span { font-size:.82rem;color:var(--gray-500); }
    code { background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;font-size:.84rem;word-break:break-all; }
    ol.steps { font-size:.87rem;color:var(--gray-700);line-height:1.8;padding-left:1.2rem;margin:.4rem 0 0; }
    table { width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--gray-200);
            border-radius:10px;overflow:hidden;font-size:.85rem;margin-top:.6rem; }
    thead tr { background:#1a2e1a; }
    th { padding:.5rem .8rem;text-align:left;font-size:.7rem;font-weight:700;color:#fff;
         text-transform:uppercase;letter-spacing:.05em; }
    td { padding:.45rem .8rem;border-bottom:1px solid var(--gray-100); }
    tr:last-child td { border-bottom:none; }
    .act-btn { background:none;border:1px solid var(--gray-200);border-radius:6px;padding:.3rem .65rem;
               font-size:.78rem;cursor:pointer;color:var(--gray-600);text-decoration:none; }
    .empty { font-size:.86rem;color:var(--gray-400);font-style:italic;padding:.6rem 0; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="people.php">&#x2190; People</a>
    <h1>Mailchimp</h1>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <h2 class="sec">Connection</h2>
  <div class="pcard">
    <?php if (!$configured): ?>
      <div class="state">
        <span class="dot off"></span>
        <div class="txt"><strong>Not set up</strong>
          <span>Add the Mailchimp values to <code>members/config.php</code>.</span></div>
      </div>
      <ol class="steps">
        <li>In Mailchimp: <em>Account &rarr; Extras &rarr; API keys</em> &rarr; create a key.</li>
        <li><em>Audience &rarr; Settings &rarr; Audience name and defaults</em> &rarr; copy the <strong>Audience ID</strong>.</li>
        <li>Choose any long random string as a webhook secret.</li>
        <li>Add to <code>members/config.php</code>:<br>
          <code>define('MC_API_KEY', '...');</code><br>
          <code>define('MC_LIST_ID', '...');</code><br>
          <code>define('MC_WEBHOOK_SECRET', '...');</code></li>
      </ol>
    <?php elseif (!$connOk): ?>
      <div class="state">
        <span class="dot off"></span>
        <div class="txt"><strong>Configured, but Mailchimp refused the request</strong>
          <span><?= htmlspecialchars($connMsg) ?></span></div>
      </div>
    <?php else: ?>
      <div class="state">
        <span class="dot on"></span>
        <div class="txt"><strong>Connected<?= $listName ? ' — ' . htmlspecialchars($listName) : '' ?></strong>
          <span>Audience reachable. Contact edits push name, school and role.</span></div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($missingFields): ?>
  <h2 class="sec">Merge fields needed</h2>
  <div class="pcard" style="border-color:#fde68a;background:#fffbeb;">
    <p style="font-size:.88rem;color:#92400e;margin:0 0 .6rem;line-height:1.7;">
      Mailchimp rejects any update naming a merge field the audience doesn't have, so
      <strong>every sync will fail</strong> until these exist. Add them under
      <em>Audience &rarr; Settings &rarr; Audience fields and *|MERGE|* tags</em>, as
      <strong>Text</strong> fields, with exactly these tags:
    </p>
    <table style="margin:0;">
      <thead><tr><th>Field label</th><th>Tag</th></tr></thead>
      <tbody>
        <?php foreach ($missingFields as $tag => $label): ?>
        <tr><td><?= htmlspecialchars($label) ?></td><td><code><?= htmlspecialchars($tag) ?></code></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:.82rem;color:#92400e;margin:.6rem 0 0;">
      The tag must match exactly — Mailchimp sometimes suggests a different one when you
      type the label. Reload this page once they are added.
    </p>
  </div>
  <?php elseif ($connOk): ?>
  <div class="pcard" style="border-color:#bbf7d0;background:#f0fdf4;margin-top:.6rem;">
    <span style="font-size:.87rem;color:#166534;">&#x2713; SCHOOL and ROLE merge fields are present.</span>
  </div>
  <?php endif; ?>

  <?php if ($configured): ?>
  <h2 class="sec">Webhook</h2>
  <div class="pcard">
    <p style="font-size:.87rem;color:var(--gray-700);margin:0 0 .5rem;line-height:1.7;">
      Add this in Mailchimp under <em>Audience &rarr; Settings &rarr; Webhooks</em>, replacing
      <code>YOUR_SECRET</code> with the <code>MC_WEBHOOK_SECRET</code> from config.php:
    </p>
    <p><code><?= htmlspecialchars($webhookUrl) ?></code></p>
    <p style="font-size:.85rem;color:var(--gray-600);margin:.6rem 0 0;">
      Tick <strong>Subscribes</strong>, <strong>Unsubscribes</strong>, <strong>Profile updates</strong>,
      <strong>Email changed</strong> and <strong>Cleaned addresses</strong>.
    </p>
    <p style="font-size:.8rem;color:var(--gray-500);margin:.7rem 0 0;line-height:1.6;">
      Mailchimp does not sign list webhooks, so there is no signature to verify. The secret in
      the URL is what authenticates the request; treat it like a password. The endpoint accepts
      POST only, returns nothing, and can change only a subscription status on an address
      already held here.
    </p>
  </div>
  <?php endif; ?>

  <h2 class="sec">Failed syncs<?= $failed ? ' (' . count($failed) . ')' : '' ?></h2>
  <?php if (!$failed): ?>
    <p class="empty">Nothing waiting to retry.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Contact</th><th>Email</th><th>Error</th></tr></thead>
    <tbody>
      <?php foreach (array_slice($failed, 0, 25) as $c): ?>
      <tr>
        <td><a href="contact-edit.php?id=<?= (int)$c['id'] ?>" style="color:var(--primary);font-weight:600;">
          <?= htmlspecialchars(contactDisplayName($c)) ?></a></td>
        <td style="color:var(--gray-500);"><?= htmlspecialchars($c['email']) ?></td>
        <td style="color:#b45309;font-size:.8rem;"><?= htmlspecialchars($c['mailchimp_sync_error'] ?: '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p style="font-size:.84rem;color:var(--gray-500);margin-top:.7rem;">
    These are picked up automatically by the sync below.
  </p>
  <?php endif; ?>

  <h2 class="sec">Sync</h2>
  <?php if (!$connOk): ?>
  <div class="pcard" style="border-color:#fde68a;background:#fffbeb;">
    <p style="font-size:.88rem;color:#92400e;margin:0;line-height:1.7;">
      Syncing is unavailable until the connection above works. Nothing is lost &mdash;
      contact edits are queued and the first sync will send them.
    </p>
  </div>
  <?php elseif ($missingFields): ?>
  <div class="pcard" style="border-color:#fde68a;background:#fffbeb;">
    <p style="font-size:.88rem;color:#92400e;margin:0;line-height:1.7;">
      Syncing is unavailable until the merge fields above exist &mdash; without them
      Mailchimp rejects every contact, so the button is hidden rather than letting you
      start a run that would fail on every contact. Add them, then reload this page.
    </p>
  </div>
  <?php else: ?>
  <div class="pcard">
    <p style="font-size:.88rem;color:var(--gray-700);margin:0 0 .9rem;line-height:1.7;">
      Sends every contact's name, school and role to Mailchimp and reads back their
      subscription status. Runs in batches because Mailchimp limits how fast we may
      call it &mdash; leave the page open until it finishes. Stopping early is safe:
      whatever has been done stays done.
    </p>

    <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;">
      <button class="btn btn-primary" id="syncBtn" style="padding:.55rem 1.2rem;font-size:.92rem;"
              onclick="runSync('push')">Sync contacts</button>
      <button class="act-btn" id="pullBtn" onclick="runSync('pull')">Check statuses only</button>
      <span id="syncMsg" style="font-size:.86rem;color:var(--gray-500);"></span>
    </div>

    <p style="font-size:.84rem;color:var(--gray-500);margin:.9rem 0 0;line-height:1.7;">
      Not sure the stored statuses are right?
      <a href="contacts-verify.php" style="color:var(--primary);font-weight:600;">Verify against Mailchimp</a>
      reads the whole audience and shows you every difference before changing anything.
    </p>

    <div id="bar" style="display:none;height:6px;background:#e5e7eb;border-radius:100px;margin-top:.9rem;overflow:hidden;">
      <div id="barFill" style="height:100%;width:0;background:var(--primary);transition:width .25s;"></div>
    </div>
  </div>

  <script>
  var CSRF = <?= json_encode(csrfToken()) ?>;

  // Kept outside runSync so an interrupted run can genuinely be carried on.
  // As locals these reset to zero on every press, which made the "press Sync
  // contacts to carry on" message false: a connection that drops every few
  // batches would re-do the same prefix forever and never reach the end.
  var syncState = { dir: null, after: 0, since: '', done: 0, failed: 0 };

  function runSync(direction) {
    var btn  = document.getElementById('syncBtn');
    var pull = document.getElementById('pullBtn');
    var msg  = document.getElementById('syncMsg');
    var bar  = document.getElementById('bar');
    var fill = document.getElementById('barFill');

    btn.disabled = pull.disabled = true;
    bar.style.display = 'block';
    // Clear the red left by whatever interrupted the previous attempt, or a
    // healthy resume reads as a continuing failure.
    msg.style.color = '';

    // A different direction is a different run, so start it from the top.
    if (syncState.dir !== direction) {
      syncState = { dir: direction, after: 0, since: '', done: 0, failed: 0 };
    }
    var total = 0;

    function step() {
      var fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('direction', direction);
      if (syncState.since) fd.append('since', syncState.since);
      fd.append('after', syncState.after);

      fetch('contacts-sync-run.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.error) {
            msg.textContent = d.error;
            msg.style.color = '#991b1b';
            btn.disabled = pull.disabled = false;
            return;
          }
          syncState.since   = d.since;
          syncState.after    = d.after;
          syncState.done    += d.processed;
          syncState.failed  += d.failed;
          var done   = syncState.done;
          var failed = syncState.failed;
          // Recomputed each batch from the run's own totals, which persist
          // across an interruption, so an uninterrupted run counts exactly.
          // A resume after a batch was lost in flight does not: its successes
          // are skipped by the `since` predicate and never counted, and its
          // failures carry no timestamp, so they sit ahead of the stale cursor
          // and are attempted — and counted — a second time. The contacts
          // themselves end up correct; only this tally is approximate.
          total = done + failed + d.remaining;

          var pct = total ? Math.round((done + failed) / total * 100) : 100;
          fill.style.width = Math.min(100, pct) + '%';
          msg.textContent = d.finished
            ? 'Finished — ' + done + ' synced' + (failed ? ', ' + failed + ' failed' : '') + '.'
            : (done + failed) + ' of ' + total + '\u2026';

          if (d.finished) {
            syncState = { dir: null, after: 0, since: '', done: 0, failed: 0 };
            msg.style.color = failed ? '#b45309' : '#166534';
            btn.disabled = pull.disabled = false;
            // Reload so the failure list and counts reflect the run
            setTimeout(function () { location.reload(); }, 1200);
          } else {
            step();
          }
        })
        .catch(function () {
          msg.textContent = 'Connection lost — press ' +
            (direction === 'pull' ? 'Check statuses only' : 'Sync contacts') +
            ' to carry on.';
          msg.style.color = '#991b1b';
          btn.disabled = pull.disabled = false;
        });
    }
    step();
  }
  </script>
  <?php endif; ?>

</div>
</body>
</html>
