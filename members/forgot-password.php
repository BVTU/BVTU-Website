<?php
/**
 * forgot-password.php — Request a password reset link
 */
require_once 'auth.php';
require_once 'db.php';

startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

// Load config for CONTACT_EMAIL (used as the from address)
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) require_once $configPath;
$fromEmail = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'noreply@bvtu.ca';

$submitted = false;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, name FROM members WHERE email = ?");
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if ($member) {
            // Generate a secure random token
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $db->prepare("UPDATE members SET reset_token = ?, reset_expires = ? WHERE id = ?")
               ->execute([$token, $expires, $member['id']]);

            // Build reset URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'new.bvtu.ca';
            $resetUrl = "{$protocol}://{$host}/members/reset-password.php?token={$token}";

            // Send email
            $name    = htmlspecialchars($member['name']);
            $subject = 'BVTU — Password Reset Request';
            $body    = "Hi {$name},\n\n"
                     . "We received a request to reset your BVTU member portal password.\n\n"
                     . "Click the link below to set a new password. This link expires in 1 hour.\n\n"
                     . "{$resetUrl}\n\n"
                     . "If you did not request a password reset, you can safely ignore this email — your password has not changed.\n\n"
                     . "— Bulkley Valley Teachers' Union";

            $headers  = "From: BVTU Member Portal <{$fromEmail}>\r\n";
            $headers .= "Reply-To: {$fromEmail}\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            mail($email, $subject, $body, $headers);
        }

        // Always show the same message — never reveal whether email exists
        $submitted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Forgot Password — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    .auth-wrap {
      min-height: calc(100vh - var(--hdr-h));
      display: flex; align-items: center; justify-content: center;
      background: var(--off-white); padding: 2rem 1.25rem;
    }
    .auth-card {
      background: var(--white); border: 1px solid var(--border);
      border-radius: var(--radius-l); box-shadow: var(--shadow);
      padding: 2.5rem 2rem; width: 100%; max-width: 420px;
    }
    .auth-logo { display:flex; align-items:center; gap:.65rem; margin-bottom:1.75rem; text-decoration:none; }
    .auth-logo img { height: 40px; }
    .auth-logo span { font-size:.95rem; font-weight:700; color:var(--primary); line-height:1.3; }
    .auth-card h1 { font-size:1.4rem; font-weight:800; color:var(--primary); margin-bottom:.35rem; }
    .auth-card p.sub { font-size:.88rem; color:var(--gray-500); margin-bottom:1.75rem; line-height:1.6; }
    .field { margin-bottom:1rem; }
    .field label { display:block; font-size:.88rem; font-weight:600; color:var(--gray-700); margin-bottom:.35rem; }
    .field input {
      width:100%; padding:.7rem .9rem; border:1px solid var(--border);
      border-radius:var(--radius-s); font-size:.95rem; font-family:var(--font);
      color:var(--text); transition:border-color .15s;
    }
    .field input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(27,107,66,.12); }
    .error-msg {
      background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;
      border-radius:var(--radius-s); padding:.75rem 1rem; font-size:.88rem; margin-bottom:1rem;
    }
    .success-msg {
      background:var(--accent); border:1.5px solid var(--primary); color:var(--primary);
      border-radius:var(--radius-s); padding:1rem 1.1rem; font-size:.9rem; line-height:1.6;
    }
    .auth-submit {
      width:100%; padding:.8rem; background:var(--primary); color:var(--white);
      border:none; border-radius:var(--radius-s); font-size:1rem; font-weight:700;
      cursor:pointer; transition:background .18s; font-family:var(--font);
    }
    .auth-submit:hover { background:var(--primary-dk); }
    .auth-footer { margin-top:1.5rem; text-align:center; font-size:.88rem; color:var(--gray-500); }
    .auth-footer a { color:var(--primary); font-weight:600; }
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
        <h1>Check your email</h1>
        <div class="success-msg">
          If that email address is registered, you'll receive a password reset link within a few minutes. Check your spam folder if it doesn't arrive.
        </div>
        <div class="auth-footer" style="margin-top:1.5rem;">
          <a href="login.php">← Back to sign in</a>
        </div>

      <?php else: ?>
        <h1>Forgot password?</h1>
        <p class="sub">Enter your email address and we'll send you a link to reset your password. The link expires in 1 hour.</p>

        <?php if ($error): ?>
          <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
          <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <button type="submit" class="auth-submit">Send Reset Link</button>
        </form>

        <div class="auth-footer">
          <a href="login.php">← Back to sign in</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="../js/site.js"></script>
</body>
</html>
