<?php
/**
 * reset-password.php — Set a new password via a reset token
 */
require_once 'auth.php';
require_once 'db.php';

startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = false;
$validToken = false;
$member  = null;

// ── Validate token ────────────────────────────────────────────────────────────
if ($token) {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT id, name, email FROM members
         WHERE reset_token = ? AND reset_expires > NOW()"
    );
    $stmt->execute([$token]);
    $member = $stmt->fetch();
    if ($member) $validToken = true;
}

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Update password and clear the reset token in one query
        $db->prepare(
            "UPDATE members SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?"
        )->execute([$hash, $member['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Reset Password — BVTU</title>
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
    .field .hint { font-size:.8rem; color:var(--gray-500); margin-top:.3rem; }
    .error-msg {
      background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;
      border-radius:var(--radius-s); padding:.75rem 1rem; font-size:.88rem; margin-bottom:1rem;
    }
    .success-msg {
      background:var(--accent); border:1.5px solid var(--primary); color:var(--primary);
      border-radius:var(--radius-s); padding:1rem 1.1rem; font-size:.9rem; line-height:1.6;
    }
    .invalid-msg {
      background:#fef2f2; border:1.5px solid #fca5a5; color:#991b1b;
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

      <?php if ($success): ?>
        <!— ── Password updated ── -->
        <h1>Password updated</h1>
        <div class="success-msg">
          Your password has been changed successfully. You can now sign in with your new password.
        </div>
        <div class="auth-footer" style="margin-top:1.5rem;">
          <a href="login.php">Sign in →</a>
        </div>

      <?php elseif (!$token || !$validToken): ?>
        <!— ── Invalid / expired token ── -->
        <h1>Link expired</h1>
        <div class="invalid-msg">
          This password reset link is invalid or has expired. Reset links are only valid for 1 hour.
        </div>
        <div class="auth-footer" style="margin-top:1.5rem;">
          <a href="forgot-password.php">Request a new link</a>
        </div>

      <?php else: ?>
        <!— ── Reset form ── -->
        <h1>Set new password</h1>
        <p class="sub">Choose a strong password for your BVTU member account.</p>

        <?php if ($error): ?>
          <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="field">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" required
                   autocomplete="new-password" minlength="8">
            <div class="hint">Minimum 8 characters</div>
          </div>
          <div class="field">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required autocomplete="new-password" minlength="8">
          </div>
          <button type="submit" class="auth-submit">Update Password</button>
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
