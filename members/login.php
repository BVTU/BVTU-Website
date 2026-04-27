<?php
require_once 'auth.php';
require_once 'db.php';

startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, name, email, password_hash FROM members WHERE email = ?");
        $stmt->execute([$email]);
        $member = $stmt->fetch();

        if ($member && password_verify($password, $member['password_hash'])) {
            loginMember($member);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Incorrect email or password. Please try again.';
        }
    } else {
        $error = 'Please enter your email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Login — BVTU</title>
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
      max-width: 420px;
    }
    .auth-logo {
      display: flex;
      align-items: center;
      gap: .65rem;
      margin-bottom: 1.75rem;
      text-decoration: none;
    }
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
    .error-msg {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      border-radius: var(--radius-s);
      padding: .75rem 1rem;
      font-size: .88rem;
      margin-bottom: 1rem;
    }
    .auth-submit {
      width: 100%;
      padding: .8rem;
      background: var(--primary);
      color: var(--white);
      border: none;
      border-radius: var(--radius-s);
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: background .18s;
      font-family: var(--font);
    }
    .auth-submit:hover { background: var(--blue); }
    .auth-footer { margin-top: 1.5rem; text-align: center; font-size: .88rem; color: var(--gray-500); }
    .auth-footer a { color: var(--blue); font-weight: 600; }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="header-inner container">
      <a href="../index.html" class="logo">
        <img src="../bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <nav class="main-nav">
        <ul>
          <li><a href="../about.html">About</a></li>
          <li><a href="../documents.php">Documents</a></li>
          <li><a href="../members.html">Members</a></li>
          <li><a href="../prod.html">PRO-D</a></li>
          <li><a href="../health-safety.html">Health &amp; Safety</a></li>
          <li><a href="../bctf.html">BCTF</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div class="auth-wrap">
    <div class="auth-card">
      <a href="../index.html" class="auth-logo">
        <img src="../bvtu-logo.png" alt="BVTU">
        <span>Bulkley Valley<br>Teachers' Union</span>
      </a>
      <h1>Member Login</h1>
      <p class="sub">Sign in to access members-only resources and documents.</p>

      <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" required autocomplete="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="auth-submit">Sign In</button>
      </form>

      <div class="auth-footer">
        Don't have an account? <a href="register.php">Create one</a>
      </div>
    </div>
  </div>

</body>
</html>
