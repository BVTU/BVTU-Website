<?php
/**
 * resend-invite.php — Self-serve invite resend for teachers who haven't registered yet
 */
require_once 'auth.php';
require_once 'db.php';
require_once 'invite-db.php';

startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        inviteEnsureTable();
        $db = getDB();

        // Only act if they don't already have an account
        $s = $db->prepare("SELECT id FROM members WHERE email=?");
        $s->execute([$email]);
        $hasMember = (bool)$s->fetch();

        if (!$hasMember) {
            // Check whether we have a prior invite on file (pending or expired — not accepted)
            $s = $db->prepare(
                "SELECT * FROM member_invitations
                 WHERE email=? AND accepted_at IS NULL
                 ORDER BY created_at DESC LIMIT 1"
            );
            $s->execute([$email]);
            $existing = $s->fetch();

            if ($existing) {
                // Re-issue a fresh token and send
                $token = inviteCreate($email, $existing['name'] ?? '', 'self-serve');
                inviteSendEmail($email, $existing['name'] ?: $email, $token);
            }
            // If no invite exists at all, we don't send — the generic message covers it
        }
    }

    // Always show the same response — don't leak whether the email is on file
    $submitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resend Registration Link — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    .auth-wrap { min-height: calc(100vh - var(--hdr-h)); display: flex; align-items: center;
                 justify-content: center; background: var(--off-white); padding: 2rem 1.25rem; }
    .auth-card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-l);
                 box-shadow: var(--shadow); padding: 2.5rem 2rem; width: 100%; max-width: 440px; }
    .auth-logo { display: flex; align-items: center; gap: .65rem; margin-bottom: 1.75rem; text-decoration: none; }
    .auth-logo img { height: 40px; }
    .auth-logo span { font-size: .95rem; font-weight: 700; color: var(--primary); line-height: 1.3; }
    h1 { font-size: 1.35rem; font-weight: 800; color: var(--primary); margin-bottom: .35rem; }
    p.sub { font-size: .88rem; color: var(--gray-500); margin-bottom: 1.5rem; line-height: 1.6; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .88rem; font-weight: 600; color: var(--gray-700); margin-bottom: .35rem; }
    .field input { width: 100%; padding: .7rem .9rem; border: 1px solid var(--border);
                   border-radius: var(--radius-s); font-size: .95rem; font-family: var(--font);
                   color: var(--text); box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(21,101,192,.12); }
    .auth-submit { width: 100%; padding: .8rem; background: var(--primary); color: var(--white); border: none;
                   border-radius: var(--radius-s); font-size: 1rem; font-weight: 700; cursor: pointer;
                   font-family: var(--font); }
    .auth-submit:hover { background: var(--blue); }
    .auth-footer { margin-top: 1.5rem; text-align: center; font-size: .88rem; color: var(--gray-500); }
    .auth-footer a { color: var(--blue); font-weight: 600; }
    .success-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
                   padding: 1.25rem 1.25rem; font-size: .9rem; color: #166534; line-height: 1.7; }
    .success-box strong { display: block; font-size: 1rem; margin-bottom: .4rem; }
    .contact-note { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #bbf7d0;
                    font-size: .83rem; color: #166534; }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="header-inner container">
      <a href="../index.php" class="logo">
        <img src="../bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
    </div>
  </header>

  <div class="auth-wrap">
    <div class="auth-card">
      <a href="../index.php" class="auth-logo">
        <img src="../bvtu-logo.png" alt="BVTU">
        <span>Bulkley Valley<br>Teachers' Union</span>
      </a>

      <?php if ($submitted): ?>

        <div class="success-box">
          <strong>Check your inbox</strong>
          If we have your email on file, you'll receive a registration link shortly.
          Please also check your spam or junk folder — the email comes from
          <strong>noreply@bvtu.ca</strong>.
          <div class="contact-note">
            Still no email? Please connect with the Local President to make sure
            we have your current personal email address on file.
          </div>
        </div>

        <div class="auth-footer">
          <a href="login.php">&#x2190; Back to sign in</a>
        </div>

      <?php else: ?>

        <h1>Get Your Registration Link</h1>
        <p class="sub">
          Enter your personal email address below. If we have it on file,
          we'll send you a link to set up your account.
        </p>

        <form method="POST">
          <div class="field">
            <label for="email">Personal email address</label>
            <input type="email" id="email" name="email" required autocomplete="email"
                   placeholder="e.g. jane@gmail.com">
          </div>
          <button type="submit" class="auth-submit">Send Registration Link</button>
        </form>

        <div class="auth-footer">
          Already have an account? <a href="login.php">Sign in</a>
        </div>

      <?php endif; ?>
    </div>
  </div>
</body>
</html>
