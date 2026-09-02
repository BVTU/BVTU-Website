<?php
/**
 * member-manage.php — Admin: manage accounts (Members tab) + send invitations (Invitations tab)
 */
require_once 'auth.php';
require_once 'db.php';
require_once 'exec-db.php';
require_once 'invite-db.php';

requireLogin();
ensureMembersColumns();

$member = getMember();

if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$notice = null;
$error  = null;
$db     = getDB();

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add new member
    if ($action === 'add_member') {
        $name    = trim($_POST['name']     ?? '');
        $email   = strtolower(trim($_POST['email']    ?? ''));
        $empNum  = trim($_POST['employee_number'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$empNum || strlen($password) < 8) {
            $error = 'Please fill in all required fields. Password must be at least 8 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $s = $db->prepare("SELECT employee_number FROM valid_employee_numbers WHERE employee_number=?");
            $s->execute([$empNum]);
            if (!$s->fetch()) {
                $error = 'That employee number was not found. Please check it and try again.';
            } else {
                $s = $db->prepare("SELECT id FROM members WHERE email=? OR employee_number=?");
                $s->execute([$email, $empNum]);
                if ($s->fetch()) {
                    $error = 'An account with that email or employee number already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare(
                        "INSERT INTO members (name, email, password_hash, must_change_password, employee_number)
                         VALUES (?,?,?,1,?)"
                    )->execute([$name, $email, $hash, $empNum]);
                    $notice = htmlspecialchars($name) . ' (' . htmlspecialchars($email) . ') added.'
                            . ' They will be prompted to set a new password on first login.';
                }
            }
        }
    }

    // Edit member name / email
    if ($action === 'edit_member') {
        $id       = (int)($_POST['member_id'] ?? 0);
        $newName  = trim($_POST['new_name']  ?? '');
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));

        if (!$id || !$newName || !$newEmail) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            // Fetch current values
            $s = $db->prepare("SELECT name, email FROM members WHERE id=?");
            $s->execute([$id]);
            $old = $s->fetch();

            if (!$old) {
                $error = 'Member not found.';
            } else {
                $oldEmail = strtolower(trim($old['email']));
                $oldName  = $old['name'];

                // Check new email not already taken by someone else
                if ($newEmail !== $oldEmail) {
                    $s = $db->prepare("SELECT id FROM members WHERE email=? AND id != ?");
                    $s->execute([$newEmail, $id]);
                    if ($s->fetch()) {
                        $error = 'That email is already used by another account.';
                    }
                }

                if (!$error) {
                    $db->prepare("UPDATE members SET name=?, email=? WHERE id=?")
                       ->execute([$newName, $newEmail, $id]);

                    // Cascade to role tables
                    foreach ([
                        "UPDATE exec_roles SET user_email=?, user_name=? WHERE user_email=?",
                        "UPDATE exp_roles  SET user_email=?, user_name=? WHERE user_email=?",
                        "UPDATE prod_roles SET user_email=?, user_name=? WHERE user_email=?",
                    ] as $sql) {
                        $db->prepare($sql)->execute([$newEmail, $newName, $oldEmail]);
                    }
                    // Cascade name to denormalized columns in expense/batch tables
                    $db->prepare("UPDATE exp_expenses SET user_email=?, user_name=? WHERE user_email=?")
                       ->execute([$newEmail, $newName, $oldEmail]);
                    $db->prepare("UPDATE exp_batches SET user_email=?, user_name=? WHERE user_email=?")
                       ->execute([$newEmail, $newName, $oldEmail]);
                    $db->prepare("UPDATE lp_vouchers SET submitted_by_email=?, submitted_by=? WHERE submitted_by_email=?")
                       ->execute([$newEmail, $newName, $oldEmail]);

                    $notice = 'Account updated'
                        . ($newEmail !== $oldEmail ? ' — email changed from ' . htmlspecialchars($oldEmail) . ' to ' . htmlspecialchars($newEmail) . ', all role records updated' : '')
                        . '.';
                }
            }
        }
    }

    // Deactivate / reactivate
    if ($action === 'toggle_active') {
        $id     = (int)($_POST['member_id'] ?? 0);
        $active = (int)($_POST['set_active'] ?? 1);
        $selfId = (int)($member['id'] ?? 0);
        if ($id && $id !== $selfId) {
            $db->prepare("UPDATE members SET active=? WHERE id=?")->execute([$active, $id]);
            $notice = $active ? 'Account reactivated.' : 'Account deactivated — member can no longer log in.';
        }
    }

    // Reset password
    if ($action === 'reset_password') {
        $id       = (int)($_POST['member_id'] ?? 0);
        $password = $_POST['new_password'] ?? '';
        if ($id > 0 && strlen($password) >= 8) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE members SET password_hash=?, must_change_password=1 WHERE id=?")
               ->execute([$hash, $id]);
            $notice = 'Password reset. They will be prompted to choose a new password on next login.';
        } else {
            $error = 'Password must be at least 8 characters.';
        }
    }

    // ── Invite actions ────────────────────────────────────────────────────────
    if ($action === 'send_invites') {
        $lines  = [];
        $iErrors = [];
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $fh = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($fh) {
                $header = null; $emailCol = null; $nameCol = null;
                while (($row = fgetcsv($fh)) !== false) {
                    if (!$header) {
                        $header = array_map('strtolower', array_map('trim', $row));
                        foreach ($header as $i => $h) {
                            if (in_array($h, ['email','email address','e-mail','emailaddress'])) $emailCol = $i;
                            if (in_array($h, ['name','full name','fullname','first name','firstname','display name'])) $nameCol = $i;
                        }
                        if ($emailCol === null) {
                            $emailCol = 0; $nameCol = isset($row[1]) ? 1 : null; $header = ['auto'];
                            $e = strtolower(trim($row[0] ?? '')); $n = $nameCol !== null ? trim($row[1] ?? '') : '';
                            if ($e) $lines[] = $n ? "{$n}, {$e}" : $e;
                        }
                        continue;
                    }
                    $e = strtolower(trim($row[$emailCol] ?? '')); $n = $nameCol !== null ? trim($row[$nameCol] ?? '') : '';
                    if ($e) $lines[] = $n ? "{$n}, {$e}" : $e;
                }
                fclose($fh);
            }
        }
        $raw = trim($_POST['invite_list'] ?? '');
        if ($raw) $lines = array_merge($lines, array_filter(array_map('trim', explode("\n", $raw))));
        $sent = $iskip = $fail = 0;
        foreach ($lines as $line) {
            if (strpos($line, ',') !== false) { [$iname, $iemail] = array_map('trim', explode(',', $line, 2)); }
            else { $iname = ''; $iemail = trim($line); }
            $iemail = strtolower($iemail);
            if (!filter_var($iemail, FILTER_VALIDATE_EMAIL)) { $iErrors[] = "Invalid: {$line}"; $iskip++; continue; }
            $s = $db->prepare("SELECT id FROM members WHERE email=?"); $s->execute([$iemail]);
            if ($s->fetch()) { $iErrors[] = "{$iemail} already has an account."; $iskip++; continue; }
            $tok = inviteCreate($iemail, $iname, $member['email']);
            $ok  = inviteSendEmail($iemail, $iname ?: $iemail, $tok);
            if ($ok) { $sent++; } else { $fail++; $iErrors[] = "Send failed: {$iemail}"; }
            if ($sent % 20 === 0 && $sent > 0) usleep(500000);
        }
        $parts = [];
        if ($sent)   $parts[] = "{$sent} invite" . ($sent !== 1 ? 's' : '') . " sent";
        if ($iskip)  $parts[] = "{$iskip} skipped";
        if ($fail)   $parts[] = "{$fail} failed";
        $notice = implode(', ', $parts) . '.';
        if ($iErrors) $notice .= ' — ' . implode(' | ', $iErrors);
    }

    if ($action === 'resend_invite') {
        $iid = (int)($_POST['invite_id'] ?? 0);
        $s   = $db->prepare("SELECT * FROM member_invitations WHERE id=?"); $s->execute([$iid]);
        $inv = $s->fetch();
        if ($inv && !$inv['accepted_at']) {
            $tok = inviteCreate($inv['email'], $inv['name'] ?? '', $member['email']);
            $ok  = inviteSendEmail($inv['email'], $inv['name'] ?: $inv['email'], $tok);
            $notice = $ok ? 'Invite re-sent to ' . $inv['email'] . '.' : 'Send failed.';
        }
    }

    if ($action === 'revoke_invite') {
        $iid = (int)($_POST['invite_id'] ?? 0);
        inviteRevoke($iid);
        $notice = 'Invite revoked.';
    }

    $tab = in_array($action, ['send_invites','resend_invite','revoke_invite']) ? '&tab=invitations' : '';
    header('Location: member-manage.php' . ($notice ? '?notice=' . urlencode($notice) . $tab : ($error ? '?error=' . urlencode($error) . $tab : ($tab ? '?'.ltrim($tab,'&') : ''))));
    exit;
}

$notice  = $notice ?: htmlspecialchars($_GET['notice'] ?? '');
$error   = $error  ?: htmlspecialchars($_GET['error']  ?? '');
$activeTab = ($_GET['tab'] ?? '') === 'invitations' ? 'invitations' : 'members';

// ── Load all members ──────────────────────────────────────────────────────────
$members = $db->query(
    "SELECT id, name, email, must_change_password, created_at, COALESCE(active,1) AS active
     FROM members ORDER BY name"
)->fetchAll();

// Quick role lookup: exec + exp roles per email
$roleMap = [];
$execRows = $db->query("SELECT user_email, role FROM exec_roles")->fetchAll();
foreach ($execRows as $r) $roleMap[strtolower($r['user_email'])][] = $r['role'];
$expRows  = $db->query("SELECT user_email, role FROM exp_roles")->fetchAll();
foreach ($expRows  as $r) $roleMap[strtolower($r['user_email'])][] = $r['role'];

// ── Load invitations ──────────────────────────────────────────────────────────
inviteEnsureTable();
$invites = inviteGetAll();
$invCounts = ['pending' => 0, 'accepted' => 0, 'expired' => 0];
foreach ($invites as $i) $invCounts[$i['invite_status']]++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Management — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1020px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
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
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
                  overflow: hidden; margin-bottom: 1.5rem; }
    table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    thead tr { background: #1a2e1a; }
    th { padding: .6rem .85rem; text-align: left; font-size: .71rem; font-weight: 700;
         text-transform: uppercase; letter-spacing: .05em; color: #fff; white-space: nowrap; }
    td { padding: .6rem .85rem; border-bottom: 1px solid var(--gray-100);
         color: var(--gray-700); vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    tr.inactive-row td { opacity: .55; }
    tr.inactive-row { background: #fafafa; }

    .badge-pending  { display: inline-block; background: #fef3c7; color: #d97706; font-size: .68rem; font-weight: 700; border-radius: 100px; padding: .15rem .5rem; }
    .badge-inactive { display: inline-block; background: #fee2e2; color: #991b1b; font-size: .68rem; font-weight: 700; border-radius: 100px; padding: .15rem .5rem; }
    .badge-active   { display: inline-block; background: #dcfce7; color: #166534; font-size: .68rem; font-weight: 700; border-radius: 100px; padding: .15rem .5rem; }
    .badge-you      { display: inline-block; background: #eff6ff; color: #1d4ed8; font-size: .68rem; font-weight: 700; border-radius: 100px; padding: .15rem .5rem; margin-left: .3rem; }
    .role-tag { display: inline-block; background: var(--accent); color: var(--primary); font-size: .65rem; font-weight: 700; border-radius: 4px; padding: .1rem .4rem; margin: .1rem .1rem 0 0; }

    /* Inline action buttons */
    .act-btn { background: none; border: 1px solid var(--gray-200); border-radius: 6px; padding: .28rem .6rem; font-size: .76rem; cursor: pointer; color: var(--gray-600); white-space: nowrap; }
    .act-btn:hover { background: var(--accent); border-color: var(--primary); color: var(--primary); }
    .act-btn.danger:hover { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
    .act-btn.go:hover { background: #f0fdf4; border-color: #86efac; color: #166534; }
    .acts { display: flex; gap: .35rem; flex-wrap: wrap; }

    /* Inline edit row */
    .edit-row { display: none; background: #f8fafc; border-top: 1px solid var(--gray-100); }
    .edit-row.open { display: table-row; }
    .edit-row td { padding: .75rem .85rem; }
    .edit-inner { display: flex; gap: .6rem; align-items: flex-end; flex-wrap: wrap; }
    .edit-inner .ef { display: flex; flex-direction: column; gap: .2rem; }
    .edit-inner .ef label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-400); }
    .edit-inner input { border: 1px solid var(--gray-300); border-radius: 6px; padding: .4rem .6rem; font-size: .86rem; font-family: inherit; min-width: 180px; }
    .edit-inner input:focus { outline: none; border-color: var(--primary); }

    /* Reset password row */
    .reset-form { display: flex; gap: .35rem; align-items: center; }
    .reset-form input { width: 130px; border: 1px solid var(--gray-300); border-radius: 6px; padding: .3rem .5rem; font-size: .8rem; }

    .pw-field { position: relative; }
    .pw-field input { padding-right: 2.5rem; }
    .pw-toggle { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%);
                 background: none; border: none; cursor: pointer; color: var(--gray-400); padding: .2rem; }

    .info-note { font-size: .78rem; color: var(--gray-500); background: #f8f9fa; border: 1px solid var(--gray-200); border-radius: 8px; padding: .6rem .85rem; margin-bottom: 1rem; }

    /* Tabs */
    .tabs { display: flex; gap: 0; border-bottom: 2px solid var(--gray-200); margin-bottom: 1.75rem; }
    .tab-btn { background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px;
               padding: .6rem 1.1rem; font-size: .9rem; font-weight: 700; color: var(--gray-400);
               cursor: pointer; font-family: inherit; }
    .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
    .tab-btn .badge { display: inline-block; background: var(--accent); color: var(--primary);
                      font-size: .65rem; font-weight: 800; border-radius: 100px;
                      padding: .1rem .45rem; margin-left: .35rem; vertical-align: middle; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* Invite table */
    .badge-pending-inv  { display:inline-block;background:#fef3c7;color:#d97706;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .badge-accepted-inv { display:inline-block;background:#dcfce7;color:#166534;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .badge-expired-inv  { display:inline-block;background:#f1f5f9;color:#64748b;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .stat-row { display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap; }
    .stat { background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:.75rem 1.1rem;flex:1;min-width:90px;text-align:center; }
    .stat .n { font-size:1.8rem;font-weight:800;color:var(--gray-800);line-height:1; }
    .stat .l { font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-400);margin-top:.2rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Member Management</h1>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <div class="tabs">
    <button class="tab-btn <?= $activeTab === 'members' ? 'active' : '' ?>"
            onclick="switchTab('members')">
      Members
      <span class="badge"><?= count($members) ?></span>
    </button>
    <button class="tab-btn <?= $activeTab === 'invitations' ? 'active' : '' ?>"
            onclick="switchTab('invitations')">
      Invitations
      <?php if ($invCounts['pending']): ?>
      <span class="badge"><?= $invCounts['pending'] ?> pending</span>
      <?php endif; ?>
    </button>
  </div>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <!-- TAB: Members                                                          -->
  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <div id="tab-members" class="tab-panel <?= $activeTab === 'members' ? 'active' : '' ?>">

  <div class="info-note">
    &#x1F4CB; This list and the <a href="roles-overview.php">Roles &amp; Directory</a> both read from the same member database.
    Editing a name or email here automatically updates all role tables.
  </div>

  <!-- ── Add member ─────────────────────────────────────────────────────────── -->
  <div class="sec-head">Add a New Member</div>
  <div class="form-card">
    <h2>Create Account</h2>
    <p style="font-size:.83rem;color:var(--gray-500);margin:-.25rem 0 1rem;">
      The member will be asked to set their own password the first time they log in.
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
      <div class="field" style="max-width:200px;">
        <label>Employee Number *</label>
        <input type="text" name="employee_number" required placeholder="e.g. 12345" autocomplete="off">
        <div class="field-hint">Must be on the approved employee list.</div>
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

  <!-- ── Member list ────────────────────────────────────────────────────────── -->
  <div class="sec-head">All Members (<?= count($members) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Status</th>
          <th>Roles</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m):
          $isYou    = (strtolower($m['email']) === strtolower($member['email']));
          $isActive = (int)($m['active'] ?? 1);
          $roles    = $roleMap[strtolower($m['email'])] ?? [];
        ?>
        <tr id="main-<?= $m['id'] ?>" class="<?= !$isActive ? 'inactive-row' : '' ?>">
          <td>
            <strong><?= htmlspecialchars($m['name']) ?></strong>
            <?php if ($isYou): ?><span class="badge-you">you</span><?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--gray-500);"><?= htmlspecialchars($m['email']) ?></td>
          <td>
            <?php if (!$isActive): ?>
              <span class="badge-inactive">&#x2715; Deactivated</span>
            <?php elseif ($m['must_change_password']): ?>
              <span class="badge-pending">&#x23F3; Pending first login</span>
            <?php else: ?>
              <span class="badge-active">&#x2713; Active</span>
            <?php endif; ?>
          </td>
          <td>
            <?php foreach ($roles as $r): ?>
            <span class="role-tag"><?= htmlspecialchars($r) ?></span>
            <?php endforeach; ?>
            <?php if (!$roles): ?><span style="color:var(--gray-300);font-size:.78rem;">—</span><?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;">
            <?= $m['created_at'] ? date('M j, Y', strtotime($m['created_at'])) : '—' ?>
          </td>
          <td>
            <div class="acts">
              <?php if (!$isYou): ?>
              <button type="button" class="act-btn" onclick="toggleEdit(<?= $m['id'] ?>)">✏ Edit</button>
              <?php if ($isActive): ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Deactivate <?= htmlspecialchars(addslashes($m['name'])) ?>? They will not be able to log in.')">
                <input type="hidden" name="action"     value="toggle_active">
                <input type="hidden" name="member_id"  value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="set_active" value="0">
                <button type="submit" class="act-btn danger">⊘ Deactivate</button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"     value="toggle_active">
                <input type="hidden" name="member_id"  value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="set_active" value="1">
                <button type="submit" class="act-btn go">&#x21BA; Reactivate</button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>

        <!-- Inline edit row -->
        <tr id="edit-<?= $m['id'] ?>" class="edit-row">
          <td colspan="6">
            <form method="POST" class="edit-inner">
              <input type="hidden" name="action"    value="edit_member">
              <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
              <div class="ef">
                <label>Full Name</label>
                <input type="text" name="new_name" value="<?= htmlspecialchars($m['name']) ?>" required>
              </div>
              <div class="ef">
                <label>Email</label>
                <input type="email" name="new_email" value="<?= htmlspecialchars($m['email']) ?>" required>
              </div>
              <div style="display:flex;gap:.35rem;align-self:flex-end;">
                <button type="submit" class="btn btn-primary" style="padding:.4rem .85rem;font-size:.82rem;">Save</button>
                <button type="button" class="act-btn" onclick="toggleEdit(<?= $m['id'] ?>)">Cancel</button>
              </div>
            </form>
            <div style="margin-top:.5rem;">
              <form method="POST" class="reset-form"
                    onsubmit="return confirm('Reset password for <?= htmlspecialchars(addslashes($m['name'])) ?>?')">
                <input type="hidden" name="action"    value="reset_password">
                <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                <span style="font-size:.74rem;color:var(--gray-400);margin-right:.25rem;">Reset password:</span>
                <input type="password" name="new_password" placeholder="New temp password" minlength="8" required>
                <button type="submit" class="act-btn">Reset</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  </div><!-- /tab-members -->

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <!-- TAB: Invitations                                                      -->
  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <div id="tab-invitations" class="tab-panel <?= $activeTab === 'invitations' ? 'active' : '' ?>">

    <div class="stat-row">
      <div class="stat"><div class="n"><?= $invCounts['pending'] ?></div><div class="l">Pending</div></div>
      <div class="stat"><div class="n"><?= $invCounts['accepted'] ?></div><div class="l">Accepted</div></div>
      <div class="stat"><div class="n"><?= $invCounts['expired'] ?></div><div class="l">Expired</div></div>
      <div class="stat"><div class="n"><?= count($invites) ?></div><div class="l">Total</div></div>
    </div>

    <div class="sec-head">Send Invitations</div>
    <div class="form-card">
      <h2>Invite Members</h2>
      <p style="font-size:.83rem;color:var(--gray-500);margin:-.25rem 0 1rem;">
        Each person gets a personal, one-time link valid for 72 hours.
        Sending to someone with a pending invite replaces their old link with a fresh one.
        Members who already have accounts are automatically skipped.
      </p>
      <form method="POST" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="action" value="send_invites">
        <div class="field">
          <label>Upload a CSV file</label>
          <input type="file" name="csv_file" accept=".csv,text/csv"
                 style="display:block;border:1px solid var(--gray-300);border-radius:7px;padding:.5rem .75rem;font-size:.88rem;width:100%;box-sizing:border-box;background:#fff;">
          <div class="field-hint">Must have an <code>email</code> column. A <code>name</code> column is optional. Google Contacts / Excel exports work as-is.</div>
        </div>
        <div class="field">
          <label>Or paste emails manually</label>
          <textarea name="invite_list" rows="5"
                    style="width:100%;border:1px solid var(--gray-300);border-radius:7px;padding:.6rem .75rem;font-size:.88rem;font-family:monospace;box-sizing:border-box;resize:vertical;"
                    placeholder="One per line — optionally with a name:&#10;&#10;Jane Smith, jane@example.com&#10;john@example.com"></textarea>
          <div class="field-hint">CSV and paste are merged before sending.</div>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">Send Invites</button>
      </form>
    </div>

    <div class="sec-head">All Invitations (<?= count($invites) ?>)</div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Email</th>
            <th>Name</th>
            <th>Status</th>
            <th>Sent</th>
            <th>Expires / Accepted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$invites): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--gray-400);padding:2rem;">No invitations sent yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($invites as $inv):
            $istatus = $inv['invite_status'];
          ?>
          <tr>
            <td><?= htmlspecialchars($inv['email']) ?></td>
            <td style="color:var(--gray-500);"><?= htmlspecialchars($inv['name'] ?? '—') ?></td>
            <td>
              <?php if ($istatus === 'pending'): ?>
                <span class="badge-pending-inv">&#x23F3; Pending</span>
              <?php elseif ($istatus === 'accepted'): ?>
                <span class="badge-accepted-inv">&#x2713; Accepted</span>
              <?php else: ?>
                <span class="badge-expired-inv">Expired</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;"><?= date('M j, Y', strtotime($inv['created_at'])) ?></td>
            <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;">
              <?php if ($inv['accepted_at']): ?>
                Accepted <?= date('M j, Y', strtotime($inv['accepted_at'])) ?>
              <?php elseif ($istatus === 'expired'): ?>
                Expired <?= date('M j, Y', strtotime($inv['expires_at'])) ?>
              <?php else: ?>
                Expires <?= date('M j g:ia', strtotime($inv['expires_at'])) ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($istatus !== 'accepted'): ?>
              <div style="display:flex;gap:.35rem;">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="action"    value="resend_invite">
                  <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                  <button type="submit" class="act-btn">&#x21BA; Resend</button>
                </form>
                <?php if ($istatus === 'pending'): ?>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Revoke invite for <?= htmlspecialchars(addslashes($inv['email'])) ?>?')">
                  <input type="hidden" name="action"    value="revoke_invite">
                  <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                  <button type="submit" class="act-btn danger">Revoke</button>
                </form>
                <?php endif; ?>
              </div>
              <?php else: ?>
              <span style="font-size:.75rem;color:var(--gray-300);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /tab-invitations -->

</div><!-- /wrap -->
<script>
function togglePw(id) {
    var el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
function toggleEdit(id) {
    var row = document.getElementById('edit-' + id);
    var isOpen = row.classList.toggle('open');
    if (isOpen) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
    document.querySelector('.tab-btn[onclick*="' + name + '"]').classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
    history.replaceState(null, '', '?tab=' + name);
}
</script>
</body>
</html>
