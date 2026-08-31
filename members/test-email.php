<?php
/**
 * test-email.php — Admin-only tool to verify the site mail system is working
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();

if (!expIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

// Gather server mail diagnostics
$diag = [
    'sendmail_path'     => ini_get('sendmail_path') ?: '(not set)',
    'sendmail_from'     => ini_get('sendmail_from') ?: '(not set)',
    'SMTP'              => ini_get('SMTP')           ?: '(not set)',
    'smtp_port'         => ini_get('smtp_port')      ?: '(not set)',
    'disable_functions' => ini_get('disable_functions') ?: '(none)',
    'php_version'       => phpversion(),
    'server_software'   => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
];
$mailDisabled = strpos($diag['disable_functions'], 'mail') !== false;

$sent   = false;
$failed = false;
$to     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to = strtolower(trim($_POST['to'] ?? ''));

    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $subject = 'BVTU Site — Test Email';
        $body    = "This is a test message from the BVTU member portal.\n\n"
                 . "If you received this, the site's mail system is working correctly.\n\n"
                 . "Sent by: " . $member['name'] . " <" . $member['email'] . ">\n"
                 . "Time: " . date('Y-m-d H:i:s T') . "\n"
                 . "Server: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n\n"
                 . "— BVTU Member Portal";

        $headers  = "From: BVTU Member Portal <lp54@bctf.ca>\r\n";
        $headers .= "Reply-To: lp54@bctf.ca\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $result = mail($to, $subject, $body, $headers);
        $sent   = $result;
        $failed = !$result;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test Email — BVTU Admin</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 600px; margin: 3rem auto; padding: 0 1.5rem 4rem; }
    h1 { font-size: 1.3rem; font-weight: 800; color: var(--gray-800); margin-bottom: .3rem; }
    .sub { font-size: .88rem; color: var(--gray-500); margin-bottom: 1.75rem; }
    .card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.25rem; }
    .card h2 { font-size: .9rem; font-weight: 800; color: var(--gray-700); margin: 0 0 1rem; text-transform: uppercase; letter-spacing: .05em; font-size: .75rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    .field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .8rem; font-size: .95rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .note { font-size: .78rem; color: var(--gray-400); margin-top: .3rem; }
    .notice  { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .warn { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: .75rem 1rem; font-size: .82rem; color: #92400e; margin-bottom: 1.25rem; line-height: 1.6; }
    .info  { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: .75rem 1rem; font-size: .82rem; color: #1e40af; margin-bottom: 1.25rem; line-height: 1.6; }
    .diag-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .diag-table tr:not(:last-child) td { border-bottom: 1px solid var(--gray-100); }
    .diag-table td { padding: .45rem .5rem; vertical-align: top; }
    .diag-table td:first-child { color: var(--gray-500); font-weight: 600; width: 45%; white-space: nowrap; }
    .diag-table td:last-child  { font-family: monospace; color: var(--gray-700); word-break: break-all; }
    .ok   { color: #166534; font-weight: 700; }
    .bad  { color: #991b1b; font-weight: 700; }
    .back { display: inline-block; font-size: .85rem; color: var(--primary); text-decoration: none; margin-bottom: 1.25rem; }
    .back:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="wrap">
  <a class="back" href="dashboard.php">&#x2190; Dashboard</a>
  <h1>Test Email</h1>
  <p class="sub">Send a test message and inspect the server's mail configuration.</p>

  <?php if ($mailDisabled): ?>
  <div class="error-box">
    &#x26A0; <strong>mail() is disabled</strong> on this server (<code>disable_functions</code> includes <code>mail</code>).
    PHP cannot send any email at all. You will need to use SMTP instead — see your hosting provider's documentation.
  </div>
  <?php endif; ?>

  <?php if ($sent): ?>
  <div class="notice">
    &#x2713; <strong>mail() returned success</strong> for <strong><?= htmlspecialchars($to) ?></strong>.<br>
    Check the inbox now (including spam/junk). If nothing arrives within 5 minutes, see the configuration notes below — the server accepted the call but may not be relaying it.
  </div>
  <?php elseif ($failed): ?>
  <div class="error-box">
    &#x26A0; <strong>mail() returned false</strong> — the server rejected the call outright.<br>
    PHP's <code>mail()</code> is not working. Check the configuration below and contact your hosting provider.
  </div>
  <?php endif; ?>

  <!-- Send form -->
  <div class="card">
    <h2>Send Test</h2>
    <form method="POST">
      <div class="field">
        <label>Send to</label>
        <input type="email" name="to" required
               value="<?= htmlspecialchars($to ?: $member['email']) ?>"
               placeholder="e.g. lp54@bctf.ca">
        <p class="note">Try your LP email first, then your treasurer's and VP's.</p>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.6rem 1.25rem;font-size:.95rem;"<?= $mailDisabled ? ' disabled' : '' ?>>Send Test Email</button>
    </form>
  </div>

  <!-- Server diagnostics -->
  <div class="card">
    <h2>Server Mail Configuration</h2>
    <table class="diag-table">
      <tr>
        <td>sendmail_path</td>
        <td><?php
          $sp = $diag['sendmail_path'];
          if ($sp === '(not set)') echo '<span class="bad">(not set) — no local MTA configured</span>';
          else echo '<span class="ok">' . htmlspecialchars($sp) . '</span>';
        ?></td>
      </tr>
      <tr>
        <td>sendmail_from</td>
        <td><?= htmlspecialchars($diag['sendmail_from']) ?></td>
      </tr>
      <tr>
        <td>SMTP (Windows only)</td>
        <td><?= htmlspecialchars($diag['SMTP']) ?></td>
      </tr>
      <tr>
        <td>smtp_port</td>
        <td><?= htmlspecialchars($diag['smtp_port']) ?></td>
      </tr>
      <tr>
        <td>mail() disabled?</td>
        <td><?= $mailDisabled ? '<span class="bad">YES — mail is in disable_functions</span>' : '<span class="ok">No</span>' ?></td>
      </tr>
      <tr>
        <td>PHP version</td>
        <td><?= htmlspecialchars($diag['php_version']) ?></td>
      </tr>
      <tr>
        <td>Server</td>
        <td><?= htmlspecialchars($diag['server_software']) ?></td>
      </tr>
    </table>
  </div>

  <div class="info">
    <strong>What this page tells you:</strong><br>
    If <strong>sendmail_path</strong> is blank or shows <code>(not set)</code>, your hosting server has no local mail transfer agent configured — <code>mail()</code> silently drops every message. The fix is to configure your host to relay through an SMTP service (Gmail, Mailgun, Postmark, etc.) or ask your hosting provider to enable PHP mail. Share this page's output with them if needed.
  </div>
</div>
</body>
</html>
