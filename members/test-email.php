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

$sent    = false;
$failed  = false;
$to      = '';

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
    .wrap { max-width: 540px; margin: 3rem auto; padding: 0 1.5rem 4rem; }
    h1 { font-size: 1.3rem; font-weight: 800; color: var(--gray-800); margin-bottom: .3rem; }
    .sub { font-size: .88rem; color: var(--gray-500); margin-bottom: 1.75rem; }
    .card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.5rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    .field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .8rem; font-size: .95rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .note { font-size: .78rem; color: var(--gray-400); margin-top: .3rem; }
    .notice  { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .warn { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: .75rem 1rem; font-size: .82rem; color: #92400e; margin-top: 1rem; line-height: 1.6; }
    .back { display: inline-block; font-size: .85rem; color: var(--primary); text-decoration: none; margin-bottom: 1.25rem; }
    .back:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="wrap">
  <a class="back" href="dashboard.php">&#x2190; Dashboard</a>
  <h1>Test Email</h1>
  <p class="sub">Send a test message to verify the site's mail system is working.</p>

  <?php if ($sent): ?>
  <div class="notice">
    &#x2713; <strong>mail() returned success</strong> — message dispatched to <strong><?= htmlspecialchars($to) ?></strong>.<br>
    Check that inbox (including spam/junk). If it doesn't arrive within a few minutes, the server's mail relay may need attention.
  </div>
  <?php elseif ($failed): ?>
  <div class="error-box">
    &#x26A0; <strong>mail() returned false</strong> — the server rejected the send attempt.<br>
    The PHP <code>mail()</code> function is not configured correctly on this server. Contact your hosting provider.
  </div>
  <?php endif; ?>

  <div class="card">
    <form method="POST">
      <div class="field">
        <label>Send to</label>
        <input type="email" name="to" required
               value="<?= htmlspecialchars($to ?: $member['email']) ?>"
               placeholder="e.g. lp54@bctf.ca">
        <p class="note">Enter your LP email, your treasurer's email, or your VP's email to check each one.</p>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.6rem 1.25rem;font-size:.95rem;">Send Test Email</button>
    </form>
  </div>

  <div class="warn">
    <strong>Note:</strong> <code>mail()</code> returning success only means the server accepted the message for delivery.
    It does not guarantee the email will arrive — it may still be filtered as spam, or the server's outbound relay may be misconfigured.
    Always check the destination inbox (and junk folder) to confirm end-to-end delivery.
  </div>
</div>
</body>
</html>
