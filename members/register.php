<?php
require_once 'auth.php';
require_once 'db.php';

startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $emp_num  = trim($_POST['employee_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (!$name || !$email || !$emp_num || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();

        // Check employee number is on the approved list
        $stmt = $db->prepare("SELECT employee_number FROM valid_employee_numbers WHERE employee_number = ?");
        $stmt->execute([$emp_num]);
        if (!$stmt->fetch()) {
            $error = 'That employee number was not found. Please check it and try again, or contact the union president.';
        } else {
            // Check employee number not already registered
            $stmt = $db->prepare("SELECT id FROM members WHERE employee_number = ?");
            $stmt->execute([$emp_num]);
            if ($stmt->fetch()) {
                $error = 'An account with that employee number already exists. Try logging in, or contact the union president.';
            } else {
                // Check email not already registered
                $stmt = $db->prepare("SELECT id FROM members WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'An account with that email already exists. Try logging in.';
                } else {
                    // All good — create the account
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare(
                        "INSERT INTO members (name, email, password_hash, employee_number) VALUES (?, ?, ?, ?)"
                    );
                    $stmt->execute([$name, $email, $hash, $emp_num]);

                    // Log them in immediately
                    $member = [
                        'id'    => $db->lastInsertId(),
                        'name'  => $name,
                        'email' => $email,
                    ];
                    loginMember($member);
                    header('Location: dashboard.php?welcome=1');
                    exit;
                }
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
  <meta name="site-root" content="../">
  <title>Create Account — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    .auth-wrap {
      min-height: calc(100vh - var(--hdr-h));
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--off-white);
      padding: 2rem 1.25rem;
    }
    .auth-card {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-l);
      box-shadow: var(--shadow);
      padding: 2.5rem 2rem;
      width: 100%;
      max-width: 460px;
    }
    .auth-logo { display: flex; align-items: center; gap: .65rem; margin-bottom: 1.75rem; text-decoration: none; }
    .auth-logo img { height: 40px; }
    .auth-logo span { font-size: .95rem; font-weight: 700; color: var(--primary); line-height: 1.3; }
    .auth-card h1 { font-size: 1.4rem; font-weight: 800; color: var(--primary); margin-bottom: .35rem; }
    .auth-card p.sub { font-size: .88rem; color: var(--gray-500); margin-bottom: 1.75rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .88rem; font-weight: 600; color: var(--gray-700); margin-bottom: .35rem; }
    .field input {
      width: 100%;
      padding: .7rem .9rem;
      border: 1px solid var(--border);
      border-radius: var(--radius-s);
      font-size: .95rem;
      font-family: var(--font);
      color: var(--text);
      transition: border-color .15s;
    }
    .field input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(21,101,192,.12); }
    .field .hint { font-size: .80rem; color: var(--gray-500); margin-top: .3rem; }
    .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: var(--radius-s); padding: .75rem 1rem; font-size: .88rem; margin-bottom: 1rem; }
    .auth-submit { width: 100%; padding: .8rem; background: var(--primary); color: var(--white); border: none; border-radius: var(--radius-s); font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .18s; font-family: var(--font); }
    .auth-submit:hover { background: var(--blue); }
    .auth-footer { margin-top: 1.5rem; text-align: center; font-size: .88rem; color: var(--gray-500); }
    .auth-footer a { color: var(--blue); font-weight: 600; }
    .divider { border: none; border-top: 1px solid var(--gray-200); margin: 1.25rem 0; }
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
      <nav class="main-nav">
        <ul>
          <li><a href="../about.php">About</a></li>
          <li><a href="../documents.php">Documents</a></li>
<li class="has-dropdown">
            <a href="../members.php">Members</a>
            <ul class="dropdown">
              <li><a href="../members.php">Member Resources</a></li>
              <li><a href="../remedy-tracker.php">Remedy Tracker</a></li>
            </ul>
          </li>
          <li><a href="../prod.php">PRO-D</a></li>
          <li><a href="../health-safety.php">Health &amp; Safety</a></li>
          <li><a href="../bctf.php">BCTF</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div class="auth-wrap">
    <div class="auth-card">
      <a href="../index.php" class="auth-logo">
        <img src="../bvtu-logo.png" alt="BVTU">
        <span>Bulkley Valley<br>Teachers' Union</span>
      </a>
      <h1>Create Account</h1>
      <p class="sub">You'll need your SD54 employee number to register. This verifies your BVTU membership.</p>

      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" required autocomplete="name"
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="email">Personal email address</label>
          <input type="email" id="email" name="email" required autocomplete="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          <p class="hint">Use your personal email — not your school email.</p>
        </div>
        <div class="field">
          <label for="employee_number">Employee number</label>
          <input type="text" id="employee_number" name="employee_number" required
                 value="<?= htmlspecialchars($_POST['employee_number'] ?? '') ?>"
                 placeholder="e.g. 12345">
          <p class="hint">Found on your pay stub or SD54 employee profile. This verifies your membership.</p>
        </div>
        <hr class="divider">
        <div class="field">
          <label for="password">Create a password</label>
          <input type="password" id="password" name="password" required autocomplete="new-password">
          <p class="hint">At least 8 characters.</p>
        </div>
        <div class="field">
          <label for="confirm_password">Confirm password</label>
          <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
        </div>
        <button type="submit" class="auth-submit">Create Account</button>
      </form>

      <div class="auth-footer">
        Already have an account? <a href="login.php">Sign in</a>
      </div>
    </div>
  </div>

  <script src="../js/site.js"></script>
  <script src="../js/search.js"></script>
</body>
</html>
