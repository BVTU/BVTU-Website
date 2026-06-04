<?php
/**
 * change-password.php — Change password (voluntary or forced on first login)
 */
require_once 'auth.php';
require_once 'db.php';

requireLogin(true); // allow access even when must_change_password is set
ensureMembersColumns();

$member  = getMember();
$forced  = mustChangePassword();
$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT password_hash FROM members WHERE id=?");
        $stmt->execute([$member['id']]);
        $row  = $stmt->fetch();

        // Forced change: skip current password check
        // Voluntary change: verify current password
        $currentOk = $forced || ($row && password_verify($current, $row['password_hash']));

        if (!$currentOk) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $db->prepare(
                "UPDATE members SET password_hash=?, must_change_password=0 WHERE id=?"
            )->execute([$hash, $member['id']]);

            clearMustChangePassword();
            $success = true;

            // Redirect to dashboard after a moment
            header('refresh:2;url=dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    .auth-wrap { min-height: calc(100vh - var(--hdr-h)); display: flex; align-items: center;
                 justify-content: center; background: var(--off-white); padding: 2rem 1.25rem; }
    .auth-card { background: var(--white); border: 1px solid var(--border);
                 border-radius: var(--radius-l); box-shadow: var(--shadow);
                 padding: 2.5rem 2rem; width: 100%; max-width: 420px; }
    .auth-card h1 { font-size: 1.4rem; font-weight: 800; color: var(--primary); margin-bottom: .35rem; }
    .auth-card p.sub { font-size: .88rem; color: var(--gray-500); margin-bottom: 1.75rem; line-height: 1.55; }
    .forced-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-s);
                     padding: .85rem 1rem; font-size: .88rem; color: #92400e; margin-bottom: 1.5rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .88rem; font-weight: 600; color: var(--gray-700); margin-bottom: .35rem; }
    .field input { width: 100%; padding: .7rem .9rem; border: 1px solid var(--border);
                   border-radius: var(--radius-s); font-size: .95rem; font-family: var(--font);
                   color: var(--text); transition: border-color .15s; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(21,101,192,.12); }
    .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
                 border-radius: var(--radius-s); padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1rem; }
    .success-msg { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;
                   border-radius: var(--radius-s); padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1rem; }
    .auth-submit { width: 100%; padding: .8rem; background: var(--primary); color: var(--white);
                   border: none; border-radius: var(--radius-s); font-size: 1rem; font-weight: 700;
                   cursor: pointer; transition: background .18s; font-family: var(--font); }
    .auth-submit:hover { background: var(--blue); }
    .back-link { display: block; text-align: center; margin-top: 1rem; font-size: .88rem; color: var(--gray-500); }
    .back-link a { color: var(--blue); }
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
      <h1><?= $forced ? 'Set Your Password' : 'Change Password' ?></h1>
      <p class="sub">
        <?= $forced
            ? 'Your account was set up by an administrator. Please choose a new password before continuing.'
            : 'Update the password for <strong>' . htmlspecialchars($member['email']) . '</strong>.' ?>
      </p>

      <?php if ($forced && !$success): ?>
      <div class="forced-banner">&#x26A0; You must set a new password to access the portal.</div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
      <div class="success-msg">&#x2713; Password updated! Redirecting to dashboard&hellip;</div>
      <?php else: ?>
      <form method="POST">
        <?php if (!$forced): ?>
        <div class="field">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <?php endif; ?>
        <div class="field">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required minlength="8"
                 autocomplete="new-password" placeholder="At least 8 characters">
        </div>
        <div class="field">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required
                 autocomplete="new-password">
        </div>
        <button type="submit" class="auth-submit">
          <?= $forced ? 'Set Password &amp; Continue' : 'Update Password' ?>
        </button>
      </form>
      <?php if (!$forced): ?>
      <p class="back-link"><a href="dashboard.php">&#x2190; Back to dashboard</a></p>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
