<?php
/**
 * my-account.php — Member profile: update name, link to change password
 */
require_once 'auth.php';
require_once 'db.php';

requireLogin();
ensureMembersColumns();
$member = getMember();

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // This page had no CSRF token, and now it can redirect a person's mail.
    csrfCheck();

    $action = $_POST['action'] ?? 'name';

    if ($action === 'name') {
        $name = trim($_POST['name'] ?? '');
        if (strlen($name) < 2) {
            $error = 'Please enter your full name (at least 2 characters).';
        } else {
            getDB()->prepare("UPDATE members SET name=? WHERE id=?")
                   ->execute([$name, $member['id']]);

            // Update session so the dashboard greeting changes immediately
            $_SESSION['member_name'] = $name;
            $member['name'] = $name;
            $notice = 'Name updated successfully.';
        }
    } elseif ($action === 'notify') {
        $notify  = trim($_POST['notify_email'] ?? '');
        $confirm = (string)($_POST['current_password'] ?? '');

        /* The password, because this decides where password resets go.
         * Someone who got hold of a signed-in tab could otherwise point this
         * account's mail at themselves and then ask for a reset. */
        $chk = getDB()->prepare("SELECT password_hash FROM members WHERE id=?");
        $chk->execute([$member['id']]);
        $hash = (string)$chk->fetchColumn();

        if (!$hash || !password_verify($confirm, $hash)) {
            $error = 'That password is not right, so nothing was changed.';
        } elseif ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            $error = 'That does not look like an email address.';
        } elseif (strlen($notify) > 255) {
            // The column is VARCHAR(255); longer is either a hard error or a
            // silent truncation into an address that goes nowhere.
            $error = 'That email address is too long.';
        } else {
            // Blank means "no redirect" — back to the account's own address.
            getDB()->prepare("UPDATE members SET notify_email=? WHERE id=?")
                   ->execute([$notify !== '' ? $notify : null, $member['id']]);
            // Escaped once, where it is printed — not here as well.
            $notice = $notify !== ''
                ? 'Mail from the portal will now go to ' . $notify . '.'
                : 'Mail from the portal will go to your account address again.';
        }
    }
}

// Fetch current details fresh from DB
$row = getDB()->prepare("SELECT name, email, created_at, notify_email FROM members WHERE id=?");
$row->execute([$member['id']]);
$row = $row->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Account — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 560px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { margin-bottom: 1.75rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0 0 .2rem; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice   { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box{ background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .pcard { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
            padding: 1.5rem; margin-bottom: 1.25rem; }
    .pcard h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1.1rem; }
    .field { margin-bottom: .9rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase;
                   letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px;
                   padding: .55rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field input[disabled] { background: #f8f9fa; color: var(--gray-400); cursor: not-allowed; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .25rem; }
    .meta-row { display: flex; justify-content: space-between; padding: .5rem 0;
                border-bottom: 1px solid var(--gray-100); font-size: .88rem; }
    .meta-row:last-child { border-bottom: none; }
    .meta-label { color: var(--gray-500); }
    .meta-value { font-weight: 600; color: var(--gray-800); }
    .action-link { display: flex; align-items: center; gap: .75rem; padding: .85rem 1rem;
                   background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
                   color: var(--text); font-size: .9rem; font-weight: 500; text-decoration: none;
                   transition: border-color .12s, background .12s; margin-bottom: .5rem; }
    .action-link:hover { border-color: var(--primary); background: #f0fdf4; color: var(--primary); }
    .action-link svg { width: 18px; height: 18px; flex-shrink: 0; stroke: currentColor;
                       fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
    <h1 style="margin-top:.3rem;">My Account</h1>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- ── Profile info ────────────────────────────────────────────────────────── -->
  <div class="pcard">
    <h2>Profile</h2>
    <div class="meta-row">
      <span class="meta-label">Email</span>
      <span class="meta-value"><?= htmlspecialchars($row['email']) ?></span>
    </div>
    <div class="meta-row">
      <span class="meta-label">Member since</span>
      <span class="meta-value"><?= $row['created_at'] ? date('F j, Y', strtotime($row['created_at'])) : '—' ?></span>
    </div>
  </div>

  <!-- ── Update name ──────────────────────────────────────────────────────────── -->
  <div class="pcard">
    <h2>Update Name</h2>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="name">
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="name" required minlength="2"
               value="<?= htmlspecialchars($row['name']) ?>"
               placeholder="Your full name">
      </div>
      <div class="field">
        <label>Email Address</label>
        <input type="email" value="<?= htmlspecialchars($row['email']) ?>" disabled>
        <div class="field-hint">Email address can only be changed by an administrator.</div>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">
        Save Name
      </button>
    </form>
  </div>

  <!-- ── Where portal mail goes ───────────────────────────────────────────────── -->
  <div class="pcard">
    <h2>Where Portal Email Goes</h2>
    <p style="font-size:.85rem;color:var(--gray-500);line-height:1.55;margin:0 0 1rem;">
      By default the portal emails you at your account address.
      If you read union mail somewhere else &mdash; a work address, say &mdash;
      put it here and everything the portal sends will go there instead.
      You still sign in with
      <strong><?= htmlspecialchars($row['email']) ?></strong>, and this changes
      nothing about what you are allowed to do.
    </p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="notify">
      <div class="field">
        <label>Send portal email to</label>
        <input type="email" name="notify_email"
               value="<?= htmlspecialchars((string)($row['notify_email'] ?? '')) ?>"
               placeholder="<?= htmlspecialchars($row['email']) ?>">
        <div class="field-hint">Leave this empty to go back to your account address.</div>
      </div>
      <div class="field">
        <label>Your password</label>
        <input type="password" name="current_password" required autocomplete="current-password">
        <div class="field-hint">Asked for because this is also where password resets are sent.</div>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">
        Save Email Address
      </button>
    </form>
  </div>

  <!-- ── Password ────────────────────────────────────────────────────────────── -->
  <div class="pcard">
    <h2>Password</h2>
    <a href="change-password.php" class="action-link">
      <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Change Password
    </a>
  </div>

</div>
</body>
</html>
