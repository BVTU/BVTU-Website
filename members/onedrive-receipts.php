<?php
/**
 * onedrive-receipts.php — expense receipts in the Treasurer's OneDrive.
 *
 * New submissions copy themselves (see od-receipts.php). This page copies
 * everything else: every claim and voucher from before that existed, and any
 * receipt whose copy failed. It works through them a few at a time from the
 * browser, so a long backlog never meets the server's time limit.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/od-receipts.php';

requireLogin();
$member = getMember();
// Whoever can open Expenses & Grants: the President, and the Treasurer whose
// folder this fills. Copying a receipt changes nothing on the voucher.
if (!lpCanView($member['email'])) { header('Location: dashboard.php'); exit; }
$isAdmin = execIsAdmin($member['email']);

odEnsureTables();
odrEnsureTable();

$live = odIsConfigured() && odGetAccount() && odAccessToken() !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && reqPostStr('action') === 'run') {
    header('Content-Type: application/json');
    if (!csrfValid()) { echo json_encode(['ok' => false, 'error' => 'That page expired. Reload it.']); exit; }
    if (!$live)       { echo json_encode(['ok' => false, 'error' => 'OneDrive needs reconnecting.']); exit; }
    // Let go of the session: uploads can take minutes, and holding it would
    // freeze every other portal page open in the same browser until they finish.
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    @set_time_limit(180);
    $since = reqPostStr('since');
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) $since = odrNow();
    try {
        echo json_encode(['ok' => true] + odrRun('', 0, 8, $since));
    } catch (\Throwable $e) {
        error_log('onedrive-receipts run: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Something went wrong. The details are in the error log.']);
    }
    exit;
}

$all     = odrCandidates();
$done    = 0;
$failed  = [];
$waiting = 0;
foreach ($all as $c) {
    $st = $c['sync']['status'] ?? '';
    if ($st === 'done')       $done++;
    elseif ($st === 'error')  $failed[] = $c;
    else                      $waiting++;
}
$toCopy = $waiting + count($failed);
$recent = getDB()->query("SELECT * FROM onedrive_receipt_sync WHERE status='done'
                          ORDER BY updated_at DESC LIMIT 15")->fetchAll();

$csrf  = csrfToken();
$since = odrNow();

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$viewUrl = function (array $c): string {
    if ($c['source'] === 'lp')  return 'lp-voucher-view.php?id=' . (int)$c['parent_id'];
    if ($c['source'] === 'old') return 'exp-view.php?id=' . (int)$c['parent_id'];
    return 'exp-claim-view.php?id=' . (int)$c['parent_id'];
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expense Receipts in OneDrive — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:820px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .page-header h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 0; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.75rem 1rem;
              font-size:.88rem;color:#991b1b;margin:1rem 0; }
    h2.sec { font-size:1rem;font-weight:800;color:var(--gray-800);margin:2rem 0 .75rem;
             padding-bottom:.4rem;border-bottom:2px solid var(--accent); }
    .pcard { background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:1.25rem; }
    .odr-dest { font-size:.85rem;color:var(--gray-500);margin:.4rem 0 0; }
    .odr-dest code { background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;font-size:.82rem; }
    .odr-counts { display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1rem; }
    .odr-counts div { font-size:.8rem;color:var(--gray-500); }
    .odr-counts strong { display:block;font-size:1.5rem;color:var(--gray-800); }
    #odrStatus { font-size:.88rem;color:var(--gray-600);margin-left:.75rem; }
    table { width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--gray-200);
            border-radius:12px;overflow:hidden;font-size:.85rem; }
    thead tr { background:#1a2e1a; }
    th { padding:.55rem .85rem;text-align:left;font-size:.7rem;font-weight:700;
         text-transform:uppercase;letter-spacing:.05em;color:#fff; }
    td { padding:.5rem .85rem;border-bottom:1px solid var(--gray-100);vertical-align:top; }
    tr:last-child td { border-bottom:none; }
    .empty { font-size:.86rem;color:var(--gray-400);font-style:italic;padding:.8rem 0; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="lp-dashboard.php">&#x2190; Expenses &amp; Grants</a>
    <h1>Expense Receipts in OneDrive</h1>
    <p class="odr-dest"><code><?= $h(OD_RECEIPTS_BASE) ?> / <em>year</em> / <?= $h(OD_RECEIPTS_MONTHLY) ?> / <em>month</em></code></p>
  </div>

  <?php if (!$live): ?>
    <div class="error-box">&#x26A0; OneDrive is not connected.
      <?= $isAdmin ? '<a href="onedrive.php">Connect it</a> first.' : 'Ask the President to reconnect it.' ?></div>
  <?php endif; ?>

  <div class="pcard" style="margin-top:1.25rem;">
    <div class="odr-counts">
      <div><strong><?= (int)$done ?></strong>in OneDrive</div>
      <div><strong id="odrLeft"><?= (int)$toCopy ?></strong>to copy</div>
      <?php if ($failed): ?><div><strong style="color:#991b1b;"><?= count($failed) ?></strong>failed last time</div><?php endif; ?>
    </div>
    <?php if ($toCopy && $live): ?>
      <button type="button" id="odrGo" class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;">
        Copy <?= (int)$toCopy ?> receipt<?= $toCopy === 1 ? '' : 's' ?></button>
      <span id="odrStatus"></span>
    <?php endif; ?>
  </div>

  <?php if ($failed): ?>
  <h2 class="sec">Not copied</h2>
  <table>
    <thead><tr><th>Voucher</th><th>Date</th><th>Problem</th></tr></thead>
    <tbody>
      <?php foreach ($failed as $c): ?>
      <tr>
        <td><a href="<?= $h($viewUrl($c)) ?>" style="color:var(--primary);font-weight:600;"><?= $h($c['ref']) ?></a>
            <span style="color:var(--gray-500);"><?= $h($c['who']) ?></span></td>
        <td style="white-space:nowrap;"><?= $h($c['date'] ?: '—') ?></td>
        <td style="color:#991b1b;"><?= $h($c['sync']['error']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <h2 class="sec">Recently copied</h2>
  <?php if (!$recent): ?>
    <p class="empty">Nothing yet.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>File</th><th>Folder</th><th>When</th></tr></thead>
    <tbody>
      <?php foreach ($recent as $r): ?>
      <tr>
        <td><?php if ($r['web_url']): ?><a href="<?= $h($r['web_url']) ?>" target="_blank" rel="noopener"
               style="color:var(--primary);font-weight:600;"><?= $h($r['file_name']) ?></a>
            <?php else: ?><?= $h($r['file_name']) ?><?php endif; ?></td>
        <td style="color:var(--gray-500);"><?= $h($r['folder_path']) ?></td>
        <td style="color:var(--gray-400);white-space:nowrap;"><?= date('M j, g:ia', strtotime($r['updated_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

</div>
<?php if ($toCopy && $live): ?>
<script>
(function () {
  var btn = document.getElementById('odrGo');
  var out = document.getElementById('odrStatus');
  var left = document.getElementById('odrLeft');
  var csrf = <?= json_encode($csrf) ?>;
  var since = <?= json_encode($since) ?>;
  var copied = 0, failed = 0;

  function step() {
    var body = new FormData();
    body.append('action', 'run');
    body.append('since', since);
    body.append('csrf_token', csrf);
    fetch('onedrive-receipts.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { out.textContent = d.error || 'Stopped.'; btn.disabled = false; return; }
        copied += d.copied; failed += d.failed;
        left.textContent = d.left;
        out.textContent = copied + ' copied' + (failed ? ', ' + failed + ' failed' : '') + '…';
        if (d.left > 0 && (d.copied + d.failed) > 0) { step(); return; }
        out.textContent = copied + ' copied' + (failed ? ', ' + failed + ' failed' : '') + '.';
        setTimeout(function () { location.reload(); }, 1200);
      })
      .catch(function () { out.textContent = 'Lost the connection. Press it again to carry on.'; btn.disabled = false; });
  }

  btn.addEventListener('click', function () { btn.disabled = true; out.textContent = 'Starting…'; step(); });
})();
</script>
<?php endif; ?>
</body>
</html>
