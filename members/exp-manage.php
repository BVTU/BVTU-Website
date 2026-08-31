<?php
/**
 * exp-manage.php — Admin-only expense portal management: accounts
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();

if (!expIsAdmin($member['email'])) {
    header('Location: exp-dashboard.php');
    exit;
}

$notice = null;
$error  = null;

// ── Handle POST actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create portal account
    if ($action === 'create_account') {
        $name     = trim($_POST['new_name']     ?? '');
        $email    = strtolower(trim($_POST['new_email']    ?? ''));
        $password = $_POST['new_password']      ?? '';

        if (!$name || !$email || strlen($password) < 8) {
            $error = 'Name and email are required; password must be at least 8 characters.';
        } else {
            $s = getDB()->prepare("SELECT id FROM members WHERE email=?");
            $s->execute([$email]);
            $exists = $s->fetchColumn();

            if ($exists) {
                $error = "An account with {$email} already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                getDB()->prepare("INSERT INTO members (name, email, password_hash) VALUES (?,?,?)")
                       ->execute([$name, $email, $hash]);

                $notice = "Account created for {$name} ({$email}). Share the temporary password with them.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expense Portal Admin — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .notice  { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }

    /* Form cards */
    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .form-card h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1.1rem; }
    .field { margin-bottom: .9rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .55rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 600px) { .field-row { grid-template-columns: 1fr; } }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .25rem; }

    /* Password reveal */
    .pw-field { position: relative; }
    .pw-field input { padding-right: 2.5rem; }
    .pw-toggle { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--gray-400); padding: .2rem; }
    .pw-toggle:hover { color: var(--gray-700); }

    .section-note { font-size: .82rem; color: var(--gray-500); background: #f8f9fa; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1.25rem; line-height: 1.6; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>Expense Portal Admin</h1>
    <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="section-note">
    Expense portal roles (Treasurer, VP, President) are determined automatically from the
    <strong>EC Directory</strong> — no manual assignment needed. Use this page to create a
    portal login for someone who does not yet have an account.
  </div>

  <div class="form-card">
    <h2>Create Portal Account</h2>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="create_account">
      <div class="field-row">
        <div class="field">
          <label>Full Name *</label>
          <input type="text" name="new_name" required placeholder="e.g. Jane Smith" autocomplete="off">
        </div>
        <div class="field">
          <label>Email Address *</label>
          <input type="email" name="new_email" required placeholder="e.g. jane@example.com" autocomplete="off">
          <div class="field-hint">This is what they will log in with.</div>
        </div>
      </div>
      <div class="field">
        <label>Temporary Password *</label>
        <div class="pw-field">
          <input type="password" name="new_password" id="newPw" required minlength="8"
            placeholder="Min. 8 characters" autocomplete="new-password">
          <button type="button" class="pw-toggle" onclick="togglePw()" title="Show/hide password">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div class="field-hint">Share this privately &mdash; they should change it on first login.</div>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">Create Account</button>
    </form>
  </div>

</div>

<script>
function togglePw() {
    var input = document.getElementById('newPw');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
