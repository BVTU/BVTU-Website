<?php
/**
 * invite-register.php — Token-gated member self-registration
 */
require_once 'auth.php';
require_once 'db.php';
require_once 'invite-db.php';

startSession();
ensureMembersColumns();
inviteEnsureTable();

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$token  = trim($_GET['token'] ?? $_POST['token'] ?? '');
$invite = $token ? inviteGetByToken($token) : null;

$error   = '';
$success = false;

// ── Validate token on every request ──────────────────────────────────────────
if (!$token) {
    $tokenError = 'No invitation token provided. Please use the link from your invite email.';
} elseif (!$invite) {
    $tokenError = 'This invitation link is invalid, has already been used, or has expired. Contact lp54@bctf.ca to request a new one.';
} else {
    $tokenError = '';
}

// ── Handle form submission ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tokenError) {
    $name     = trim($_POST['name']             ?? '');
    $password = $_POST['password']              ?? '';
    $confirm  = $_POST['confirm_password']      ?? '';
    $email    = strtolower($invite['email']);   // locked — not from POST

    if (!$name) {
        $error = 'Please enter your full name.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();

        // Check email not already registered
        $s = $db->prepare("SELECT id FROM members WHERE email=?");
        $s->execute([$email]);
        if ($s->fetch()) {
            $error = 'An account with this email already exists. Try logging in instead.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare(
                "INSERT INTO members (name, email, password_hash) VALUES (?,?,?)"
            )->execute([$name, $email, $hash]);
            $memberId = $db->lastInsertId();

            inviteAccept($token);

            // Welcome email (plain text)
            require_once __DIR__ . '/smtp.php';
            $subject = 'Welcome to the BVTU Member Portal';
            $body    = "Hi {$name},\n\n"
                     . "Your BVTU member account is set up. You can log in any time at:\n"
                     . "https://bvtu.ca/members/dashboard.php\n\n"
                     . "If you have questions, reach out at lp54@bctf.ca.\n\n"
                     . "— Bulkley Valley Teachers' Union";
            siteMail($email, $subject, $body);

            loginMember(['id' => $memberId, 'name' => $name, 'email' => $email, 'must_change_password' => 0]);
            header('Location: dashboard.php?welcome=1');
            exit;
        }
    }
}

$prefillName = htmlspecialchars($invite['name'] ?? '');
$lockEmail   = htmlspecialchars($invite['email'] ?? '');
$expiresIn   = $invite ? (strtotime($invite['expires_at']) - time()) : 0;
$hoursLeft   = $invite ? max(1, (int)ceil($expiresIn / 3600)) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — BVTU</title>
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
    h1 { font-size: 1.4rem; font-weight: 800; color: var(--primary); margin-bottom: .35rem; }
    p.sub { font-size: .88rem; color: var(--gray-500); margin-bottom: 1.75rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .88rem; font-weight: 600; color: var(--gray-700); margin-bottom: .35rem; }
    .field input { width: 100%; padding: .7rem .9rem; border: 1px solid var(--border);
                   border-radius: var(--radius-s); font-size: .95rem; font-family: var(--font);
                   color: var(--text); transition: border-color .15s; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(21,101,192,.12); }
    .field input[readonly] { background: #f8f9fa; color: var(--gray-500); cursor: default; }
    .field .hint { font-size: .80rem; color: var(--gray-500); margin-top: .3rem; }
    .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
                 border-radius: var(--radius-s); padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1rem; }
    .warn-msg  { background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
                 border-radius: var(--radius-s); padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1rem; }
    .auth-submit { width: 100%; padding: .8rem; background: var(--primary); color: var(--white); border: none;
                   border-radius: var(--radius-s); font-size: 1rem; font-weight: 700; cursor: pointer;
                   transition: background .18s; font-family: var(--font); }
    .auth-submit:hover { background: var(--blue); }
    .auth-footer { margin-top: 1.5rem; text-align: center; font-size: .88rem; color: var(--gray-500); }
    .auth-footer a { color: var(--blue); font-weight: 600; }
    .expiry-note { font-size: .78rem; color: var(--gray-400); text-align: center; margin-top: .75rem; }
    .divider { border: none; border-top: 1px solid var(--gray-200); margin: 1.25rem 0; }
    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 2.5rem; }
    .pw-toggle { position: absolute; right: .7rem; top: 50%; transform: translateY(-50%);
                 background: none; border: none; cursor: pointer; color: var(--gray-400); padding: .2rem; }
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

      <?php if ($tokenError): ?>
        <h1>Invalid Link</h1>
        <div class="error-msg"><?= htmlspecialchars($tokenError) ?></div>
        <div class="auth-footer">Already have an account? <a href="login.php">Sign in</a></div>

      <?php else: ?>
        <h1>Create Your Account</h1>
        <p class="sub">You've been invited to the BVTU member portal. Set a password to get started.</p>

        <?php if ($error): ?>
          <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="field">
            <label>Email address</label>
            <input type="email" value="<?= $lockEmail ?>" readonly>
            <p class="hint">This is the email your invite was sent to — it can't be changed here.</p>
          </div>

          <div class="field">
            <label for="name">Full name *</label>
            <input type="text" id="name" name="name" required autocomplete="name"
                   value="<?= $prefillName ?: htmlspecialchars($_POST['name'] ?? '') ?>"
                   placeholder="e.g. Jane Smith">
          </div>

          <hr class="divider">

          <div class="field">
            <label for="password">Create a password *</label>
            <div class="pw-wrap">
              <input type="password" id="password" name="password" required
                     autocomplete="new-password" placeholder="At least 8 characters">
              <button type="button" class="pw-toggle" onclick="togglePw('password')" title="Show/hide">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>
          <div class="field">
            <label for="confirm_password">Confirm password *</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required autocomplete="new-password">
          </div>

          <button type="submit" class="auth-submit">Create Account</button>
        </form>

        <p class="expiry-note">This link expires in <?= $hoursLeft ?> hour<?= $hoursLeft !== 1 ? 's' : '' ?>.
          Need a new one? Email <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a>.</p>

        <div class="auth-footer">Already have an account? <a href="login.php">Sign in</a></div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  function togglePw(id) {
    var el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
  }
  </script>
</body>
</html>
