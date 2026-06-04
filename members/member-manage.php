<?php
/**
 * member-manage.php — Admin: add members, list all accounts, reset passwords
 */
require_once 'auth.php';
require_once 'db.php';
require_once 'exec-db.php';

requireLogin();
ensureMembersColumns();

$member = getMember();

if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$notice = null;
$error  = null;

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add new member
    if ($action === 'add_member') {
        $name     = trim($_POST['name']     ?? '');
        $email    = strtolower(trim($_POST['email']    ?? ''));
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || strlen($password) < 8) {
            $error = 'Name and email are required; password must be at least 8 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $s = getDB()->prepare("SELECT id FROM members WHERE email=?");
            $s->execute([$email]);
            if ($s->fetch()) {
                $error = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                getDB()->prepare(
                    "INSERT INTO members (name, email, password_hash, must_change_password)
                     VALUES (?,?,?,1)"
                )->execute([$name, $email, $hash]);
                $notice = htmlspecialchars($name) . ' (' . htmlspecialchars($email) . ') added.'
                        . ' They will be prompted to set a new password on first login.';
            }
        }
    }

    // Reset another member's password
    if ($action === 'reset_password') {
        $id       = (int)($_POST['member_id'] ?? 0);
        $password = $_POST['new_password'] ?? '';

        if ($id > 0 && strlen($password) >= 8) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            getDB()->prepare(
                "UPDATE members SET password_hash=?, must_change_password=1 WHERE id=?"
            )->execute([$hash, $id]);
            $notice = 'Password reset. They will be prompted to choose a new password on next login.';
        } else {
            $error = 'Password must be at least 8 characters.';
        }
    }
}

// ── Load all members ───────────────────────────────────────────────────────────
$members = getDB()->query(
    "SELECT id, name, email, must_change_password, created_at
     FROM members ORDER BY name"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Members — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between;
                   margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice   { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box{ background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase;
                letter-spacing: .08em; color: var(--gray-400); margin: 2rem 0 .75rem; }
    /* Add member form */
    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
                 padding: 1.5rem; margin-bottom: 1.75rem; }
    .form-card h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1rem; }
    .field { margin-bottom: .9rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase;
                   letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px;
                   padding: .55rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .25rem; }
    @media(max-width:600px) { .field-row { grid-template-columns: 1fr; } }
    /* Members table */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
                  overflow: hidden; margin-bottom: 1.5rem; }
    table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .6rem 1rem; text-align: left; font-size: .72rem; font-weight: 700;
         text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500);
         border-bottom: 1px solid var(--gray-200); }
    td { padding: .65rem 1rem; border-bottom: 1px solid var(--gray-100);
         color: var(--gray-700); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .badge-pending { display: inline-block; background: #fef3c7; color: #d97706; font-size: .7rem;
                     font-weight: 700; border-radius: 100px; padding: .15rem .5rem; }
    .you-badge { display: inline-block; background: #f0fdf4; color: #166534; font-size: .7rem;
                 font-weight: 700; border-radius: 100px; padding: .15rem .5rem; margin-left: .3rem; }
    /* Reset password inline form */
    .reset-form { display: flex; gap: .4rem; align-items: center; }
    .reset-form input { width: 140px; border: 1px solid var(--gray-300); border-radius: 6px;
                        padding: .3rem .5rem; font-size: .82rem; }
    .reset-btn { background: none; border: 1px solid var(--gray-300); border-radius: 6px;
                 padding: .3rem .65rem; font-size: .78rem; cursor: pointer; color: var(--gray-600);
                 white-space: nowrap; }
    .reset-btn:hover { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
    /* Password toggle */
    .pw-field { position: relative; }
    .pw-field input { padding-right: 2.5rem; }
    .pw-toggle { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%);
                 background: none; border: none; cursor: pointer; color: var(--gray-400); padding: .2rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Manage Members</h1>
    </div>
    <span style="font-size:.85rem;color:var(--gray-400);"><?= count($members) ?> accounts</span>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- ── Add member ──────────────────────────────────────────────────────────── -->
  <div class="sec-head">Add a New Member</div>
  <div class="form-card">
    <h2>Create Account</h2>
    <p style="font-size:.83rem;color:var(--gray-500);margin:-.25rem 0 1rem;">
      The member will be asked to set their own password the first time they log in.
      Share the temporary password with them privately.
    </p>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="add_member">
      <div class="field-row">
        <div class="field">
          <label>Full Name *</label>
          <input type="text" name="name" required placeholder="e.g. Jane Smith" autocomplete="off">
        </div>
        <div class="field">
          <label>Email Address *</label>
          <input type="email" name="email" required placeholder="e.g. jane@bctf.ca" autocomplete="off">
        </div>
      </div>
      <div class="field" style="max-width:300px;">
        <label>Temporary Password *</label>
        <div class="pw-field">
          <input type="password" id="addPw" name="password" required minlength="8"
                 placeholder="Min. 8 characters" autocomplete="new-password">
          <button type="button" class="pw-toggle" onclick="togglePw('addPw')" title="Show/hide">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="field-hint">They will be prompted to change this on first login.</div>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">
        Create Account
      </button>
    </form>
  </div>

  <!-- ── Member list ─────────────────────────────────────────────────────────── -->
  <div class="sec-head">All Members (<?= count($members) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Status</th>
          <th>Joined</th>
          <th>Reset Password</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m):
          $isYou = (strtolower($m['email']) === strtolower($member['email']));
        ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($m['name']) ?></strong>
            <?php if ($isYou): ?><span class="you-badge">you</span><?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--gray-500);"><?= htmlspecialchars($m['email']) ?></td>
          <td>
            <?php if ($m['must_change_password']): ?>
              <span class="badge-pending">&#x23F3; Awaiting password set</span>
            <?php else: ?>
              <span style="font-size:.82rem;color:#166534;">&#x2713; Active</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--gray-400);">
            <?= $m['created_at'] ? date('M j, Y', strtotime($m['created_at'])) : '—' ?>
          </td>
          <td>
            <?php if (!$isYou): ?>
            <form method="POST" class="reset-form"
                  onsubmit="return confirm('Reset password for <?= htmlspecialchars(addslashes($m['name'])) ?>?')">
              <input type="hidden" name="action"    value="reset_password">
              <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
              <input type="password" name="new_password" placeholder="New temp password" minlength="8" required>
              <button type="submit" class="reset-btn">Reset</button>
            </form>
            <?php else: ?>
            <a href="change-password.php" style="font-size:.82rem;color:var(--primary);">Change my password</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
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
