<?php
/**
 * exec-manage.php — Assign and remove Executive Committee roles
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';

requireLogin();
$member = getMember();
execEnsureTables();

// Access: EC admin only
if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$notice = null;
$error  = null;

// ── Handle POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign') {
        $email  = strtolower(trim($_POST['email']  ?? ''));
        $name   = trim($_POST['name']   ?? '');
        $role1  = trim($_POST['role1']  ?? '');
        $role2  = trim($_POST['role2']  ?? '');

        $validRoles = array_keys(EXEC_ROLES);

        if (!$email || !$name || !$role1 || !in_array($role1, $validRoles)) {
            $error = 'Name, email, and at least one valid role are required.';
        } elseif ($role2 && !in_array($role2, $validRoles)) {
            $error = 'Invalid second role selected.';
        } elseif ($role2 && $role2 === $role1) {
            $error = 'Role 1 and Role 2 must be different.';
        } else {
            try {
                // How many roles does this person already hold?
                $existing = execCountRoles($email);
                $toAssign = array_filter([$role1, $role2]);
                $wouldHave = $existing;
                foreach ($toAssign as $r) {
                    if (!execHasRole($email, $r)) $wouldHave++;
                }
                if ($wouldHave > 2) {
                    $error = htmlspecialchars($email) . ' already holds ' . $existing
                           . ' role(s). Adding these would exceed the 2-role limit.';
                } else {
                    $assigned = [];
                    foreach ($toAssign as $r) {
                        execAssignRole($email, $name, $r, $member['email']);
                        $assigned[] = EXEC_ROLES[$r];
                    }
                    $notice = htmlspecialchars($name) . ' assigned: ' . implode(' + ', $assigned) . '.';
                }
            } catch (Exception $e) {
                $error = 'Could not save: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'remove') {
        $id = (int)($_POST['role_id'] ?? 0);
        if ($id > 0) {
            execRemoveRole($id);
            $notice = 'Role removed.';
        }
    }
}

// ── Load data ──────────────────────────────────────────────────────────────────
$rosterMap = execGetRosterMap();
$people    = execGetPeople();

// Count filled / vacant
$filled  = count(array_filter($rosterMap, function($v) { return $v !== null; }));
$vacant  = count($rosterMap) - $filled;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive Committee Roles — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

    .page-header { display: flex; align-items: center; justify-content: space-between;
                   margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .notice   { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box{ background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }

    /* Stat strip */
    .stat-strip { display: flex; gap: .65rem; margin-bottom: 1.75rem; }
    .stat-chip { background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
                 padding: .5rem .9rem; text-align: center; }
    .stat-chip .num { font-size: 1.25rem; font-weight: 800; color: var(--primary); line-height: 1; }
    .stat-chip .lbl { font-size: .68rem; color: var(--gray-400); margin-top: .15rem;
                      text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }

    /* Section headings */
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase;
                letter-spacing: .08em; color: var(--gray-400); margin: 2rem 0 .75rem; }

    /* Roster table */
    .roster-table-wrap { background: #fff; border: 1px solid var(--gray-200);
                         border-radius: 10px; overflow: hidden; margin-bottom: 1.75rem; }
    table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .6rem 1rem; text-align: left; font-size: .72rem; font-weight: 700;
         text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500);
         border-bottom: 1px solid var(--gray-200); }
    td { padding: .65rem 1rem; border-bottom: 1px solid var(--gray-100);
         color: var(--gray-700); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .vacant-name { color: var(--gray-300); font-style: italic; font-size: .84rem; }
    .vacant-email { color: var(--gray-200); font-size: .76rem; }
    .remove-btn { background: none; border: none; cursor: pointer; color: var(--gray-300);
                  padding: .2rem .35rem; border-radius: 4px; font-size: .8rem; }
    .remove-btn:hover { color: #dc2626; background: #fef2f2; }

    /* Add form */
    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
                 padding: 1.5rem; margin-bottom: 1.5rem; }
    .form-card h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1.1rem; }
    .field { margin-bottom: .9rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase;
                   letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .field input, .field select { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px;
                                   padding: .55rem .75rem; font-size: .9rem; font-family: inherit;
                                   box-sizing: border-box; }
    .field input:focus, .field select:focus { outline: none; border-color: var(--primary);
                                              box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .25rem; }
    @media(max-width:600px) { .field-row { grid-template-columns: 1fr; } }

    .section-note { font-size: .82rem; color: var(--gray-500); background: #f8f9fa;
                    border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1.25rem; line-height: 1.6; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="roles-overview.php">&#x2190; Roles Overview</a>
      <h1 style="margin-top:.3rem;">Executive Committee Roles</h1>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Stat strip -->
  <div class="stat-strip">
    <div class="stat-chip">
      <div class="num"><?= $filled ?></div>
      <div class="lbl">Filled</div>
    </div>
    <div class="stat-chip">
      <div class="num" style="<?= $vacant > 0 ? 'color:#d97706;' : '' ?>"><?= $vacant ?></div>
      <div class="lbl">Vacant</div>
    </div>
    <div class="stat-chip">
      <div class="num"><?= count($people) ?></div>
      <div class="lbl">Members</div>
    </div>
  </div>

  <!-- ── Full roster ──────────────────────────────────────────────────────────── -->
  <div class="sec-head">Complete Roster (<?= count(EXEC_ROLES) ?> positions)</div>

  <div class="roster-table-wrap">
    <table>
      <thead>
        <tr><th>Position</th><th>Name</th><th>Email</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach (EXEC_ROLES as $slug => $label):
          $row = $rosterMap[$slug];
        ?>
        <tr>
          <td style="font-weight:600;color:var(--gray-700);"><?= htmlspecialchars($label) ?></td>
          <?php if ($row): ?>
            <td><strong><?= htmlspecialchars($row['user_name']) ?></strong></td>
            <td style="font-size:.8rem;color:var(--gray-400);"><?= htmlspecialchars($row['user_email']) ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($row['user_name'])) ?> from <?= htmlspecialchars(addslashes($label)) ?>?')">
                <input type="hidden" name="action"  value="remove">
                <input type="hidden" name="role_id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="remove-btn" title="Remove">&#x2715;</button>
              </form>
            </td>
          <?php else: ?>
            <td class="vacant-name">Vacant</td>
            <td class="vacant-email">&#x2014;</td>
            <td></td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Assign roles ─────────────────────────────────────────────────────────── -->
  <div class="sec-head">Assign a Role</div>

  <div class="section-note">
    Each executive member can hold <strong>up to 2 roles</strong>. Assigning roles here also grants
    access to admin pages (mileage tracker, etc.). The person must already have a portal account, or
    <a href="prod-manage.php?tab=accounts">create one first</a>.
  </div>

  <div class="form-card">
    <h2>Add / Update EC Member</h2>
    <form method="POST">
      <input type="hidden" name="action" value="assign">
      <div class="field-row">
        <div class="field">
          <label>Full Name *</label>
          <input type="text" name="name" required placeholder="e.g. Jane Smith">
        </div>
        <div class="field">
          <label>Email Address *</label>
          <input type="email" name="email" required placeholder="e.g. jane@example.com">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label>Role 1 *</label>
          <select name="role1" required>
            <option value="">Choose position&hellip;</option>
            <?php foreach (EXEC_ROLES as $slug => $label): ?>
            <option value="<?= $slug ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Role 2 <span style="font-weight:400;color:var(--gray-400);">(optional)</span></label>
          <select name="role2">
            <option value="">None</option>
            <?php foreach (EXEC_ROLES as $slug => $label): ?>
            <option value="<?= $slug ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="field-hint">Only if this person holds two positions.</div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">Assign Role(s)</button>
    </form>
  </div>

</div>
</body>
</html>
