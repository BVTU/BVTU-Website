<?php
/**
 * onedrive.php — connect OneDrive, then photograph straight into a folder.
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

$uploadToken = $live ? odCreateUploadToken($member['email']) : '';
$mobileUrl   = $live
    ? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bvtu.ca') . '/members/onedrive-mobile.php?token=' . $uploadToken
    : '';
$recent = odRecentUploads();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OneDrive Photos — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
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
    <h1>OneDrive Photos</h1>
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
          <span>Photos upload straight into the folder you pick.</span>
        </div>
        <a href="onedrive-connect.php?action=disconnect" class="btn btn-outline"
           style="padding:.5rem 1.1rem;font-size:.9rem;"
           onclick="return confirm('Disconnect OneDrive? Uploads stop until you reconnect.')">Disconnect</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($live): ?>
  <h2 class="sec">Take photos</h2>
  <div class="pcard qr-row">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=1a2e1a&bgcolor=ffffff&data=<?= urlencode($mobileUrl) ?>"
         width="160" height="160" alt="QR code to open the camera page on your phone">
    <div class="txt">
      <strong style="color:var(--gray-800);">Scan with your phone</strong><br>
      Browse to any folder in your OneDrive, then photograph into it. Give a photo a
      name and it is filed as that plus the date; leave it blank and it is named by
      date and time.
    </div>
  </div>
  <?php endif; ?>

  <h2 class="sec">Recent uploads</h2>
  <?php if (!$recent): ?>
    <p class="empty">Nothing uploaded yet.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>File</th><th>Folder</th><th>When</th></tr></thead>
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
        <td style="color:var(--gray-400);white-space:nowrap;">
          <?= date('M j, g:ia', strtotime($u['created_at'])) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

</div>
</body>
</html>
