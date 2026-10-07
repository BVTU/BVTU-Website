<?php
/**
 * onedrive.php — connect OneDrive, then photograph documents straight into a folder.
 *
 * Shows the setup steps until the Azure details are in config.php, and the
 * connection state afterwards. Both credentials behind this expire and fail
 * quietly, so the state is stated plainly rather than assumed healthy.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/onedrive-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

odEnsureTables();
$notice = htmlspecialchars($_GET['notice'] ?? '');
$error  = htmlspecialchars($_GET['error']  ?? '');

$siteBase = defined('SITE_URL') ? rtrim(SITE_URL, '/')
          : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bvtu.ca');

$configured = odIsConfigured();
$account    = $configured ? odGetAccount() : null;
$live       = false;
$whoami     = '';

if ($account) {
    // Prove the connection rather than trusting the stored row — a lapsed
    // refresh token looks identical until something is actually attempted.
    [$code, $me] = odGraph('GET', '/me?$select=displayName,userPrincipalName');
    $live   = ($code === 200);
    $whoami = $live ? ($me['userPrincipalName'] ?? $me['displayName'] ?? '') : '';
}

// ── Lending upload access for a while ─────────────────────────────────────
//
// Both handlers redirect afterwards. Without that, reloading the page re-posts
// the form and mints a second live link with the same name, and there is no way
// to tell afterwards which of the two was the one actually sent to anybody.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['od_action'] ?? '') === 'share') {
    csrfCheck();
    startSession();
    // Sharing needs a working connection: the panel that shows the new link, and
    // the list that can turn it off, are both behind the live check. A link
    // created without one would exist, work, and be unreachable.
    if (!$live) {
        $_SESSION['od_flash'] = 'Connect OneDrive first — a shared link would have nowhere to upload to.';
    } else {
        $made = odShareCols()
              ? odCreateShare((string)($_POST['label'] ?? ''), (int)($_POST['hours'] ?? 0), $member['email'])
              : null;
        if (!odShareCols()) {
            $_SESSION['od_flash'] = 'Sharing is unavailable on this server — the database '
                                  . 'could not be updated for it. The details are in the error log.';
            header('Location: onedrive.php'); exit;
        }
        if ($made) {
            // Carried through the redirect rather than put in the URL: the whole
            // point of the token is that it is not written down anywhere public,
            // and a URL lands in history and in the server's logs.
            $_SESSION['od_new_share'] = $made;
            $_SESSION['od_flash'] = 'Link created for ' . $made['label'] . '. Send it to them below.';
        } else {
            $_SESSION['od_flash'] = 'Give the link a name and a length of time.';
        }
    }
    header('Location: onedrive.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['od_action'] ?? '') === 'revoke') {
    csrfCheck();
    startSession();
    $_SESSION['od_flash'] = odRevokeShare((string)($_POST['ref'] ?? ''), $member['email'])
        ? 'That link has been turned off. It stops working immediately.'
        : 'That link was already off.';
    header('Location: onedrive.php'); exit;
}

startSession();
$newShare = $_SESSION['od_new_share'] ?? null;
unset($_SESSION['od_new_share']);          // shown once, as the panel promises
if (!empty($_SESSION['od_flash'])) {
    $notice = htmlspecialchars($_SESSION['od_flash']);
    unset($_SESSION['od_flash']);
}

$shares   = odShares();
$shareUrl = $newShare ? $siteBase . '/members/onedrive-mobile.php?token=' . $newShare['token'] : '';

$uploadToken = $live ? odCreateUploadToken($member['email']) : '';
$mobileUrl   = $live ? $siteBase . '/members/onedrive-mobile.php?token=' . $uploadToken : '';
$recent = odRecentUploads();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OneDrive Doc Upload — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:820px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .page-header h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 0; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    .notice { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem 1rem;
              font-size:.88rem;color:#166534;margin:1rem 0; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.75rem 1rem;
              font-size:.88rem;color:#991b1b;margin:1rem 0; }
    h2.sec { font-size:1rem;font-weight:800;color:var(--gray-800);margin:2rem 0 .75rem;
             padding-bottom:.4rem;border-bottom:2px solid var(--accent); }
    .pcard { background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:1.25rem; }
    .state { display:flex;gap:1rem;align-items:center;flex-wrap:wrap; }
    .dot { width:10px;height:10px;border-radius:50%;flex-shrink:0; }
    .dot.on { background:#16a34a; } .dot.off { background:#dc2626; } .dot.warn { background:#d97706; }
    .state .txt { flex:1;min-width:220px;font-size:.9rem; }
    .state .txt strong { display:block;color:var(--gray-800); }
    .state .txt span { font-size:.82rem;color:var(--gray-500); }
    ol.steps { font-size:.88rem;color:var(--gray-700);line-height:1.8;padding-left:1.2rem;margin:.5rem 0 0; }
    ol.steps code { background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;font-size:.85rem; }
    .qr-row { display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap; }
    .qr-row .txt { flex:1;min-width:220px;font-size:.85rem;color:var(--gray-500);line-height:1.6; }
    table { width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--gray-200);
            border-radius:12px;overflow:hidden;font-size:.85rem; }
    thead tr { background:#1a2e1a; }
    th { padding:.55rem .85rem;text-align:left;font-size:.7rem;font-weight:700;
         text-transform:uppercase;letter-spacing:.05em;color:#fff; }
    td { padding:.5rem .85rem;border-bottom:1px solid var(--gray-100); }
    tr:last-child td { border-bottom:none; }
    .empty { font-size:.86rem;color:var(--gray-400);font-style:italic;padding:.8rem 0; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
    <h1>OneDrive Doc Upload</h1>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <h2 class="sec">Connection</h2>
  <div class="pcard">
    <?php if (!$configured): ?>
      <div class="state">
        <span class="dot off"></span>
        <div class="txt">
          <strong>Not set up yet</strong>
          <span>Needs a free Microsoft app registration — about ten minutes, once.</span>
        </div>
      </div>
      <ol class="steps">
        <li>Go to <strong>portal.azure.com</strong> &rarr; <em>App registrations</em> &rarr; <em>New registration</em>.</li>
        <li>Name it anything. Under <em>Supported account types</em> choose
            <strong>Personal Microsoft accounts only</strong>.</li>
        <li>Set the <em>Redirect URI</em> to Web:
            <code><?= htmlspecialchars(odRedirectUri()) ?></code></li>
        <li>Register, then copy the <em>Application (client) ID</em>.</li>
        <li>Under <em>Certificates &amp; secrets</em> &rarr; <em>New client secret</em>, copy the
            <strong>Value</strong> (not the ID) &mdash; it is shown only once.</li>
        <li>Add both to <code>members/config.php</code>:<br>
            <code>define('MS_CLIENT_ID', '...');</code><br>
            <code>define('MS_CLIENT_SECRET', '...');</code><br>
            <code>define('MS_TENANT', 'consumers');</code></li>
        <li>Reload this page and press Connect.</li>
      </ol>
      <p style="font-size:.82rem;color:var(--gray-500);margin-top:.9rem;">
        Note the secret's expiry date when you create it — uploads stop when it lapses,
        and Microsoft gives no warning.
      </p>

    <?php elseif (!$account): ?>
      <div class="state">
        <span class="dot warn"></span>
        <div class="txt">
          <strong>Set up, not connected</strong>
          <span>Sign in once to give the site access to your OneDrive.</span>
        </div>
        <a href="onedrive-connect.php" class="btn btn-primary"
           style="padding:.5rem 1.1rem;font-size:.9rem;">Connect OneDrive</a>
      </div>

    <?php elseif (!$live): ?>
      <div class="state">
        <span class="dot off"></span>
        <div class="txt">
          <strong>Reconnect needed</strong>
          <span>
            <?= $account['last_error']
                  ? htmlspecialchars($account['last_error'])
                  : 'The saved sign-in is no longer accepted by Microsoft.' ?>
          </span>
        </div>
        <a href="onedrive-connect.php" class="btn btn-primary"
           style="padding:.5rem 1.1rem;font-size:.9rem;">Reconnect</a>
      </div>

    <?php else: ?>
      <div class="state">
        <span class="dot on"></span>
        <div class="txt">
          <strong>Connected<?= $whoami ? ' — ' . htmlspecialchars($whoami) : '' ?></strong>
          <span>Uploads go straight into the folder you pick.</span>
        </div>
        <a href="onedrive-connect.php?action=disconnect" class="btn btn-outline"
           style="padding:.5rem 1.1rem;font-size:.9rem;"
           onclick="return confirm('Disconnect OneDrive? Uploads stop until you reconnect.')">Disconnect</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($live): ?>
  <h2 class="sec">Upload documents</h2>
  <div class="pcard qr-row">
    <img id="qrImg" width="160" height="160" alt="QR code to open the camera page on your phone"
         style="border-radius:8px;background:#fff;">
    <noscript><a href="<?= htmlspecialchars($mobileUrl) ?>">Open the uploader link</a></noscript>
    <div class="txt">
      <strong style="color:var(--gray-800);">Scan with your phone</strong><br>
      Browse to any folder in your OneDrive, then photograph a document into it. Give it a
      name and it is filed as that plus the date; leave it blank and it is named by
      date and time.
    </div>
  </div>
  <?php endif; ?>

  <?php if ($live): ?>
  <h2 class="sec">Lend someone upload access</h2>
  <div class="pcard">
    <p style="margin:0 0 1rem;font-size:.9rem;color:var(--gray-600);line-height:1.65;max-width:62ch;">
      Creates a separate link that opens the same phone uploader without a login, for
      as long as you say. It does not touch your own QR code above, and it gives them
      nothing else on this site — no dashboard, no documents, no account. Uploads made
      through it are recorded under the name you give here.
    </p>

    <form method="post" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem;">
      <?= csrfField() ?>
      <input type="hidden" name="od_action" value="share">
      <label style="font-size:.82rem;font-weight:700;color:var(--gray-600);">
        Who is it for?
        <input type="text" name="label" maxlength="120" required placeholder="e.g. Dana — office help"
               style="display:block;margin-top:.25rem;min-width:240px;border:1px solid var(--border);
                      border-radius:7px;padding:.45rem .6rem;font:inherit;font-size:.9rem;">
      </label>
      <label style="font-size:.82rem;font-weight:700;color:var(--gray-600);">
        For how long?
        <select name="hours" style="display:block;margin-top:.25rem;border:1px solid var(--border);
                      border-radius:7px;padding:.45rem .6rem;font:inherit;font-size:.9rem;">
          <option value="4">4 hours</option>
          <option value="8" selected>The rest of today (8 hours)</option>
          <option value="24">24 hours</option>
          <option value="72">3 days</option>
          <option value="168">A week</option>
        </select>
      </label>
      <button type="submit" class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;">Create link</button>
    </form>

    <?php if ($newShare): ?>
      <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:1rem;
                  display:flex;gap:1.25rem;align-items:flex-start;flex-wrap:wrap;">
        <img id="shareQr" width="150" height="150" alt="QR code for the shared uploader"
             style="border-radius:8px;background:#fff;">
        <div style="flex:1;min-width:260px;">
          <strong style="color:#166534;">Link for <?= htmlspecialchars($newShare['label']) ?></strong>
          <p style="font-size:.84rem;color:#14532d;margin:.35rem 0 .6rem;line-height:1.6;">
            They can scan this, or you can send them the address. It stops working by
            itself, and you can turn it off sooner from the list below.
          </p>
          <input type="text" readonly value="<?= htmlspecialchars($shareUrl) ?>"
                 onclick="this.select()" style="width:100%;border:1px solid #86efac;border-radius:7px;
                 padding:.45rem .6rem;font-size:.78rem;font-family:ui-monospace,monospace;background:#fff;">
          <p style="font-size:.78rem;color:#166534;margin:.5rem 0 0;">
            This is the only time the full link is shown — the list below can turn it
            off, but cannot show it again. Copy it now.
          </p>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($shares): ?>
      <table style="margin-top:1.25rem;">
        <thead><tr><th>Shared with</th><th>Status</th><th>Used</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($shares as $sh): $st = odShareState($sh); ?>
          <tr>
            <td style="font-weight:600;"><?= htmlspecialchars($sh['label']) ?></td>
            <td style="white-space:nowrap;color:<?= $st['state'] === 'live' ? '#166534' : 'var(--gray-400)' ?>;">
              <?= htmlspecialchars($st['text']) ?>
              <?php if ($st['state'] === 'live'): ?>
                <span style="color:var(--gray-400);font-weight:400;">
                  (until <?= date('g:ia D j M', time() + (int)$sh['secs_left']) ?>)</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--gray-500);white-space:nowrap;">
              <?= (int)$sh['uses'] ? (int)$sh['uses'] . ' upload' . ((int)$sh['uses'] === 1 ? '' : 's') : '—' ?>
              <?php if (!empty($sh['last_used_at'])): ?>
                <span style="color:var(--gray-400);">· last <?= date('M j, g:ia', strtotime($sh['last_used_at'])) ?></span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;">
              <?php if ($st['state'] === 'live'): ?>
              <form method="post" style="display:inline;"
                    onsubmit="return confirm('Turn off the link for <?= htmlspecialchars(addslashes($sh['label'])) ?>? It stops working straight away.');">
                <?= csrfField() ?>
                <input type="hidden" name="od_action" value="revoke">
                <input type="hidden" name="ref" value="<?= htmlspecialchars($sh['ref']) ?>">
                <button type="submit" style="background:none;border:1px solid var(--border);border-radius:6px;
                        padding:.25rem .6rem;font:inherit;font-size:.78rem;color:#991b1b;cursor:pointer;">Turn off</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p style="font-size:.78rem;color:var(--gray-400);margin:.6rem 0 0;">
        Links that have finished stay listed for a week so you can see they were used,
        then drop off.
      </p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <h2 class="sec">Recent uploads</h2>
  <?php if (!$recent): ?>
    <p class="empty">Nothing uploaded yet.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>File</th><th>Folder</th><th>By</th><th>When</th></tr></thead>
    <tbody>
      <?php foreach ($recent as $u): ?>
      <tr>
        <td>
          <?php if ($u['web_url']): ?>
            <a href="<?= htmlspecialchars($u['web_url']) ?>" target="_blank" rel="noopener"
               style="color:var(--primary);font-weight:600;"><?= htmlspecialchars($u['file_name']) ?></a>
          <?php else: ?>
            <?= htmlspecialchars($u['file_name']) ?>
          <?php endif; ?>
        </td>
        <td style="color:var(--gray-500);"><?= htmlspecialchars($u['folder_path']) ?></td>
        <td style="color:var(--gray-500);"><?= htmlspecialchars($u['uploaded_by']) ?></td>
        <td style="color:var(--gray-400);white-space:nowrap;">
          <?= date('M j, g:ia', strtotime($u['created_at'])) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

</div>
<script src="../js/qrcode.js?v=<?= @filemtime(__DIR__ . '/../js/qrcode.js') ?>"></script>
<script src="../js/qr-img.js?v=<?= @filemtime(__DIR__ . '/../js/qr-img.js') ?>"></script>
<?php if ($live): ?>
<script>
(function () {
  var img = document.getElementById('qrImg');
  var url = <?= json_encode($mobileUrl) ?>;
  const share = document.getElementById('shareQr');
  if (share) bvtuQrInto(share, <?= json_encode($shareUrl) ?>, 150);
  if (bvtuQrInto(img, url, 160)) return;
  // Say so and give them the link rather than leaving an empty box.
  var a = document.createElement('a');
  a.href = url;
  a.textContent = "Open the uploader on your phone";
  a.style.fontWeight = '700';
  if (img && img.parentNode) img.parentNode.replaceChild(a, img);
})();
</script>
<?php endif; ?>
</body>
</html>
