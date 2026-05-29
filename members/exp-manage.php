<?php
/**
 * exp-manage.php — Admin-only expense portal management: roles, accounts
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
$tab    = $_GET['tab'] ?? 'roles';

// ── Handle POST actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add role assignment
    if ($action === 'add_role') {
        $email = strtolower(trim($_POST['role_email'] ?? ''));
        $name  = trim($_POST['role_name'] ?? '');
        $role  = trim($_POST['role']      ?? '');

        if (!$email || !$role || !in_array($role, ['treasurer', 'vp', 'president', 'admin'])) {
            $error = 'Please fill in all required fields with a valid role.';
        } else {
            try {
                getDB()->prepare(
                    "INSERT INTO exp_roles (user_email, user_name, role, assigned_by)
                     VALUES (?,?,?,?)
                     ON DUPLICATE KEY UPDATE user_name=VALUES(user_name), assigned_by=VALUES(assigned_by)"
                )->execute([$email, $name, $role, $member['email']]);
                $notice = "Role '{$role}' assigned to {$email}.";
                $tab = 'roles';
            } catch (Exception $e) {
                $error = 'Could not save role: ' . $e->getMessage();
            }
        }
    }

    // Remove role
    if ($action === 'remove_role') {
        $id = (int)($_POST['role_id'] ?? 0);
        if ($id > 0) {
            getDB()->prepare("DELETE FROM exp_roles WHERE id=?")->execute([$id]);
            $notice = 'Role removed.';
            $tab = 'roles';
        }
    }

    // Create portal account
    if ($action === 'create_account') {
        $name     = trim($_POST['new_name']     ?? '');
        $email    = strtolower(trim($_POST['new_email']    ?? ''));
        $password = $_POST['new_password']      ?? '';
        $role     = trim($_POST['new_role']     ?? '');

        if (!$name || !$email || strlen($password) < 8) {
            $error = 'Name and email are required; password must be at least 8 characters.';
        } else {
            // Check if member already exists
            $s = getDB()->prepare("SELECT id FROM members WHERE email=?");
            $s->execute([$email]);
            $exists = $s->fetchColumn();

            if ($exists) {
                $error = "An account with {$email} already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                getDB()->prepare("INSERT INTO members (name, email, password_hash) VALUES (?,?,?)")
                       ->execute([$name, $email, $hash]);

                if ($role && in_array($role, ['treasurer', 'vp', 'president', 'admin'])) {
                    getDB()->prepare(
                        "INSERT IGNORE INTO exp_roles (user_email, user_name, role, assigned_by)
                         VALUES (?,?,?,?)"
                    )->execute([$email, $name, $role, $member['email']]);
                }

                $notice = "Account created for {$name} ({$email})."
                        . ($role ? " Role '{$role}' assigned." : '')
                        . ' Share the temporary password with them.';
                $tab = 'accounts';
            }
        }
    }
}

$roles = getDB()->query(
    "SELECT * FROM exp_roles ORDER BY role, user_name"
)->fetchAll();

$roleLabels = [
    'treasurer' => 'Treasurer',
    'vp'        => 'Vice President',
    'president' => 'President',
    'admin'     => 'Admin',
];
$roleColors = [
    'treasurer' => '#166534',
    'vp'        => '#1e40af',
    'president' => '#7c3aed',
    'admin'     => '#991b1b',
];
$roleBgs = [
    'treasurer' => '#f0fdf4',
    'vp'        => '#eff6ff',
    'president' => '#f5f3ff',
    'admin'     => '#fef2f2',
];
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

    /* Tabs */
    .tab-bar { display: flex; gap: .3rem; border-bottom: 2px solid var(--gray-200); margin-bottom: 1.75rem; }
    .tab-btn { padding: .6rem 1.2rem; font-size: .88rem; font-weight: 600; color: var(--gray-500); border: none; border-bottom: 3px solid transparent; background: none; cursor: pointer; margin-bottom: -2px; transition: color .15s, border-color .15s; }
    .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
    .tab-btn:hover { color: var(--primary); }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }

    /* Form cards */
    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .form-card h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1.1rem; }
    .field { margin-bottom: .9rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .field input, .field select { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .55rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus, .field select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 600px) { .field-row { grid-template-columns: 1fr; } }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .25rem; }

    /* Role badges */
    .role-badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .2rem .6rem; border-radius: 100px; }

    /* Tables */
    .data-table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; overflow: hidden; margin-bottom: 1.5rem; }
    table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .6rem 1rem; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); }
    td { padding: .65rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .remove-btn { background: none; border: none; cursor: pointer; color: var(--gray-300); padding: .2rem .35rem; border-radius: 4px; font-size: .8rem; }
    .remove-btn:hover { color: #dc2626; background: #fef2f2; }
    .empty-row td { color: var(--gray-400); text-align: center; padding: 2rem; font-size: .88rem; }

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

  <div class="tab-bar">
    <button class="tab-btn <?= $tab === 'roles'    ? 'active' : '' ?>" onclick="switchTab('roles',    this)">Role Assignments</button>
    <button class="tab-btn <?= $tab === 'accounts' ? 'active' : '' ?>" onclick="switchTab('accounts', this)">Create Account</button>
  </div>

  <!-- ── ROLES TAB ─────────────────────────────────────────────────────────────── -->
  <div class="tab-pane <?= $tab === 'roles' ? 'active' : '' ?>" id="tab-roles">

    <div class="section-note">
      Assign roles to portal members by email.
      <strong>Treasurer</strong> reviews expenses and marks payments.
      <strong>VP / President</strong> provides the second signature.
      <strong>Admin</strong> has full access.
      Any logged-in member can submit expenses — no role needed.
    </div>

    <div class="data-table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Assigned By</th><th></th></tr></thead>
        <tbody>
          <?php if (!$roles): ?>
          <tr class="empty-row"><td colspan="5">No roles assigned yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($roles as $r):
            $col = $roleColors[$r['role']] ?? '#555';
            $bg  = $roleBgs[$r['role']]   ?? '#f8f9fa';
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['user_name'] ?: '&#x2014;') ?></strong></td>
            <td style="font-size:.82rem;color:var(--gray-500);"><?= htmlspecialchars($r['user_email']) ?></td>
            <td>
              <span class="role-badge" style="background:<?= $bg ?>;color:<?= $col ?>;">
                <?= htmlspecialchars($roleLabels[$r['role']] ?? $r['role']) ?>
              </span>
            </td>
            <td style="font-size:.82rem;color:var(--gray-400);"><?= htmlspecialchars($r['assigned_by'] ?: '&#x2014;') ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Remove this role?')">
                <input type="hidden" name="action"  value="remove_role">
                <input type="hidden" name="role_id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="remove-btn" title="Remove">&#x2715;</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Add role form -->
    <div class="form-card">
      <h2>Assign a Role</h2>
      <form method="POST">
        <input type="hidden" name="action" value="add_role">
        <div class="field-row">
          <div class="field">
            <label>Full Name</label>
            <input type="text" name="role_name" placeholder="e.g. Jane Smith">
          </div>
          <div class="field">
            <label>Email Address *</label>
            <input type="email" name="role_email" required placeholder="e.g. treasurer@bvtu.ca">
          </div>
        </div>
        <div class="field" style="max-width:260px;">
          <label>Role *</label>
          <select name="role" required>
            <option value="">Choose role&hellip;</option>
            <option value="treasurer">Treasurer</option>
            <option value="vp">Vice President</option>
            <option value="president">President</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">Assign Role</button>
      </form>
    </div>
  </div>

  <!-- ── ACCOUNTS TAB ──────────────────────────────────────────────────────────── -->
  <div class="tab-pane <?= $tab === 'accounts' ? 'active' : '' ?>" id="tab-accounts">

    <div class="section-note">
      Create a portal login for someone who does not have one yet.
      Set a temporary password and share it with them privately. They can use
      <strong>Forgot Password</strong> on the login page to change it once their email is active.
    </div>

    <div class="form-card">
      <h2>Create Portal Account</h2>
      <form method="POST" autocomplete="off">
        <input type="hidden" name="action" value="create_account">
        <div class="field-row">
          <div class="field">
            <label>Full Name *</label>
            <input type="text" name="new_name" required placeholder="e.g. BVTU Treasurer" autocomplete="off">
          </div>
          <div class="field">
            <label>Email Address *</label>
            <input type="email" name="new_email" required placeholder="e.g. treasurer@bvtu.ca" autocomplete="off">
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
        <div class="field" style="max-width:260px;">
          <label>Assign Role (optional)</label>
          <select name="new_role">
            <option value="">No role yet</option>
            <option value="treasurer">Treasurer</option>
            <option value="vp">Vice President</option>
            <option value="president">President</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">Create Account</button>
      </form>
    </div>
  </div>

</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
function togglePw() {
    var input = document.getElementById('newPw');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
