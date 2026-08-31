<?php
/**
 * test-email.php — Admin-only tool to verify SMTP mail is working
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';
require_once __DIR__ . '/smtp.php';

requireLogin();
$member = getMember();

if (!expIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

// Check SMTP config
$cfg = __DIR__ . '/config.php';
if (file_exists($cfg)) require_once $cfg;

$smtpConfigured = defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')
               && SMTP_HOST && SMTP_USER && SMTP_PASS;

$sent   = false;
$failed = false;
$to     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $smtpConfigured) {
    $to = strtolower(trim($_POST['to'] ?? ''));
    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $subject = 'BVTU Site — Test Email';
        $body    = "This is a test message from the BVTU member portal.\n\n"
                 . "If you received this, the site's SMTP mail is working correctly.\n\n"
                 . "Sent by: " . $member['name'] . " <" . $member['email'] . ">\n"
                 . "Time: " . date('Y-m-d H:i:s T') . "\n"
                 . "Server: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . "\n\n"
                 . "— BVTU Member Portal";

        $result = siteMail($to, $subject, $body);
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
    .wrap { max-width: 580px; margin: 3rem auto; padding: 0 1.5rem 4rem; }
    h1 { font-size: 1.3rem; font-weight: 800; color: var(--gray-800); margin-bottom: .3rem; }
    .sub { font-size: .88rem; color: var(--gray-500); margin-bottom: 1.75rem; }
    .card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.25rem; }
    .card h2 { font-size: .75rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); margin: 0 0 1rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    .field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .8rem; font-size: .95rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .note { font-size: .78rem; color: var(--gray-400); margin-top: .3rem; }
    .notice  { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .warn { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: .75rem 1rem; font-size: .82rem; color: #92400e; margin-bottom: 1.25rem; line-height: 1.6; }
    .cfg-row { display: flex; justify-content: space-between; font-size: .83rem; padding: .4rem 0; border-bottom: 1px solid var(--gray-100); }
    .cfg-row:last-child { border: none; }
    .cfg-row span:first-child { color: var(--gray-500); font-weight: 600; }
    .cfg-row span:last-child  { font-family: monospace; }
    .ok  { color: #166534; font-weight: 700; }
    .bad { color: #991b1b; font-weight: 700; }
    .back { display: inline-block; font-size: .85rem; color: var(--primary); text-decoration: none; margin-bottom: 1.25rem; }
    .back:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="wrap">
  <a class="back" href="dashboard.php">&#x2190; Dashboard</a>
  <h1>Test Email</h1>
  <p class="sub">Send a test message to verify SMTP is working.</p>

  <?php if ($sent): ?>
  <div class="notice">
    &#x2713; <strong>Email sent successfully</strong> to <strong><?= htmlspecialchars($to) ?></strong>.<br>
    Check that inbox now (including spam/junk folder). It should arrive within a minute.
  </div>
  <?php elseif ($failed): ?>
  <div class="error-box">
    &#x26A0; <strong>SMTP send failed.</strong> Double-check your SMTP credentials in <code>config.php</code>
    and make sure the mailbox exists in Hostinger hPanel.
    Check the PHP error log for details.
  </div>
  <?php endif; ?>

  <!-- SMTP config status -->
  <div class="card">
    <h2>SMTP Configuration</h2>
    <?php if (!$smtpConfigured): ?>
    <div class="warn" style="margin:0;">
      &#x26A0; SMTP is not configured yet. Add the following to <code>members/config.php</code> on the server:<br><br>
      <code style="display:block;background:#fef3c7;padding:.75rem;border-radius:6px;font-size:.82rem;line-height:1.8;">
        define('SMTP_HOST',&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'smtp.hostinger.com');<br>
        define('SMTP_PORT',&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;587);<br>
        define('SMTP_USER',&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'noreply@bvtu.ca');<br>
        define('SMTP_PASS',&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'your-mailbox-password');<br>
        define('SMTP_FROM_NAME',&nbsp;'BVTU Member Portal');
      </code>
      <br>Replace <code>noreply@bvtu.ca</code> with the mailbox you created in Hostinger hPanel,
      and <code>your-mailbox-password</code> with its password.
    </div>
    <?php else: ?>
    <div class="cfg-row"><span>SMTP_HOST</span><span><?= htmlspecialchars(SMTP_HOST) ?></span></div>
    <div class="cfg-row"><span>SMTP_PORT</span><span><?= htmlspecialchars(SMTP_PORT) ?></span></div>
    <div class="cfg-row"><span>SMTP_USER</span><span><?= htmlspecialchars(SMTP_USER) ?></span></div>
    <div class="cfg-row"><span>SMTP_PASS</span><span class="ok">&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022;&#x2022; (set)</span></div>
    <div class="cfg-row"><span>Status</span><span class="ok">&#x2713; Configured</span></div>
    <?php endif; ?>
  </div>

  <!-- Send form -->
  <?php if ($smtpConfigured): ?>
  <div class="card">
    <h2>Send Test</h2>
    <form method="POST">
      <div class="field">
        <label>Send to</label>
        <input type="email" name="to" required
               value="<?= htmlspecialchars($to ?: $member['email']) ?>"
               placeholder="e.g. lp54@bctf.ca">
        <p class="note">Try your LP email first, then your treasurer's and VP's to confirm each inbox receives mail.</p>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.6rem 1.25rem;font-size:.95rem;">Send Test Email</button>
    </form>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
