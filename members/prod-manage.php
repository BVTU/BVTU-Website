<?php
/**
 * prod-manage.php — Exec-only management: Pro-D role assignments and schools
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/member-picker.php';
requireLogin();

$member = getMember();
prodEnsureTables();

if (!prodIsExec($member['email'])) {
    header('Location: prod-dashboard.php');
    exit;
}

$notice = null;
$error  = null;
// Whitelisted so a stale ?tab=accounts bookmark doesn't render an empty page
// now that the Create Account tab is gone.
$tab    = in_array($_GET['tab'] ?? '', ['roles', 'schools'], true) ? $_GET['tab'] : 'roles';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add role assignment
    if ($action === 'add_role') {
        $email    = strtolower(trim($_POST['role_email'] ?? ''));
        $name     = trim($_POST['role_name']    ?? '');
        $role     = trim($_POST['role']         ?? '');
        $schoolId = (int)($_POST['school_id']   ?? 0) ?: null;

        if (!$email || !$role || !in_array($role, ['exec','treasurer','site_rep'])) {
            $error = 'Please fill in all required fields.';
        } elseif ($role === 'site_rep' && !$schoolId) {
            $error = 'Site reps must be assigned to a school.';
        } else {
            try {
                getDB()->prepare("INSERT INTO prod_roles (user_email, user_name, role, school_id, assigned_by)
                                  VALUES (?,?,?,?,?)
                                  ON DUPLICATE KEY UPDATE user_name=VALUES(user_name), school_id=VALUES(school_id), assigned_by=VALUES(assigned_by)")
                       ->execute([$email, $name, $role, $schoolId, $member['email']]);
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
            getDB()->prepare("DELETE FROM prod_roles WHERE id=?")->execute([$id]);
            $notice = 'Role removed.';
            $tab = 'roles';
        }
    }

    // Update school FTE
    if ($action === 'update_school') {
        $id  = (int)($_POST['school_id_edit'] ?? 0);
        $fte = (float)($_POST['fte_count']    ?? 0);
        if ($id > 0 && $fte > 0) {
            getDB()->prepare("UPDATE prod_schools SET fte_count=? WHERE id=?")->execute([$fte, $id]);
            $notice = 'School updated.';
            $tab = 'schools';
        }
    }

    // Add school
    if ($action === 'add_school') {
        $name = trim($_POST['school_name'] ?? '');
        $fte  = (float)($_POST['school_fte'] ?? 1.0);
        if ($name) {
            getDB()->prepare("INSERT INTO prod_schools (name, fte_count) VALUES (?,?)")->execute([$name, $fte]);
            $notice = "School '{$name}' added.";
            $tab = 'schools';
        }
    }

    // Create portal account
}

$roles   = getDB()->query("SELECT r.*, s.name as school_name FROM prod_roles r LEFT JOIN prod_schools s ON s.id = r.school_id ORDER BY r.role, r.user_name")->fetchAll();
$schools = prodGetSchools(false);

$roleLabels = ['exec' => 'Exec / President', 'treasurer' => 'Treasurer', 'site_rep' => 'Site Rep'];
$roleColors = ['exec' => '#1e40af', 'treasurer' => '#166534', 'site_rep' => '#7c3aed'];
$roleBgs    = ['exec' => '#eff6ff', 'treasurer' => '#f0fdf4', 'site_rep' => '#f5f3ff'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pro-D Management — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .notice { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
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
    .field-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
    @media (max-width: 600px) { .field-row, .field-row-3 { grid-template-columns: 1fr; } }
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

    /* School role tag shown in table */
    .school-tag { font-size: .75rem; color: var(--gray-400); display: block; margin-top: .1rem; }

    /* Password reveal */
    .pw-field { position: relative; }
    .pw-field input { padding-right: 2.5rem; }
    .pw-toggle { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--gray-400); padding: .2rem; }
    .pw-toggle:hover { color: var(--gray-700); }

    .section-note { font-size: .82rem; color: var(--gray-500); background: #f8f9fa; border-radius: 8px; padding: .75rem 1rem; margin-bottom: 1.25rem; line-height: 1.6; }
<?php memberPickerStyles(); ?>
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>Pro-D Management</h1>
    <a class="back-link" href="prod-dashboard.php">← Pro-D Portal</a>
  </div>

  <?php if ($notice): ?><div class="notice">✓ <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="tab-bar">
    <button class="tab-btn <?= $tab==='roles'    ? 'active' : '' ?>" onclick="switchTab('roles')">Role Assignments</button>
    <button class="tab-btn <?= $tab==='schools'  ? 'active' : '' ?>" onclick="switchTab('schools')">Schools</button>
  </div>

  <!-- ── ROLES TAB ─────────────────────────────────────────────────────────── -->
  <div class="tab-pane <?= $tab==='roles' ? 'active' : '' ?>" id="tab-roles">

    <div class="section-note">
      Assign roles to portal members by email. <strong>Treasurer</strong> reviews financial claims.
      <strong>Site Reps</strong> review day requests from their assigned school.
      <strong>Exec</strong> sees everything. Teachers need no role entry — any logged-in member can submit claims and day requests.
    </div>

    <!-- Existing role assignments -->
    <div class="data-table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>School</th><th></th></tr></thead>
        <tbody>
          <?php if (!$roles): ?>
          <tr class="empty-row"><td colspan="5">No roles assigned yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($roles as $r):
            $col = $roleColors[$r['role']] ?? '#555';
            $bg  = $roleBgs[$r['role']]   ?? '#f8f9fa';
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['user_name'] ?: '—') ?></strong></td>
            <td style="font-size:.82rem;color:var(--gray-500);"><?= htmlspecialchars($r['user_email']) ?></td>
            <td><span class="role-badge" style="background:<?= $bg ?>;color:<?= $col ?>;"><?= $roleLabels[$r['role']] ?? $r['role'] ?></span></td>
            <td style="font-size:.83rem;"><?= htmlspecialchars($r['school_name'] ?? '—') ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Remove this role?')">
                <input type="hidden" name="action"  value="remove_role">
                <input type="hidden" name="role_id" value="<?= $r['id'] ?>">
                <button type="submit" class="remove-btn" title="Remove">✕</button>
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
      <form method="POST" id="form-prod" onsubmit="return confirmAssign(event, 'prod')">
        <input type="hidden" name="action" value="add_role">
        <input type="hidden" name="role_email" id="sel-email-prod" value="">
        <input type="hidden" name="role_name"  id="sel-name-prod"  value="">
        <div class="field-row">
          <div class="field">
            <label>Member *</label>
            <div class="typeahead-wrap">
              <input type="text" class="typeahead-input" id="search-prod"
                     placeholder="Type a name or email&hellip;" autocomplete="off"
                     oninput="filterMembers('prod')" onkeydown="handleKey(event,'prod')">
              <ul class="suggestions" id="sugg-prod"></ul>
            </div>
            <div class="field-hint">Searches everyone on the membership list, registered or not.</div>
          </div>
          <div class="field">
            <label>Name to record</label>
            <input type="text" class="typeahead-input offlist-name" id="offname-prod"
                   placeholder="Their full name" autocomplete="off" style="display:none;"
                   oninput="offlistNameInput('prod')">
            <div class="field-hint" id="offhint-prod" style="display:none;">
              This address isn't on the member list — type their name for the directory.
            </div>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label>Role *</label>
            <select name="role" id="roleSelect" onchange="toggleSchoolField(this.value)" required>
              <option value="">Choose role…</option>
              <option value="exec">Exec / President</option>
              <option value="treasurer">Treasurer</option>
              <option value="site_rep">Site Rep</option>
            </select>
          </div>
          <div class="field" id="schoolField" style="display:none;">
            <label>Assigned School *</label>
            <select name="school_id">
              <option value="">Choose school…</option>
              <?php foreach ($schools as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary save-btn" id="save-prod"
                style="padding:.55rem 1.1rem;font-size:.9rem;">Assign Role</button>
      </form>
    </div>
  </div>

  <!-- ── SCHOOLS TAB ────────────────────────────────────────────────────────── -->
  <div class="tab-pane <?= $tab==='schools' ? 'active' : '' ?>" id="tab-schools">

    <div class="section-note">
      FTE counts are used to calculate each school's share of the 100 pro-d release days pool.
      Formula: <strong>school days = (school FTE ÷ total FTE) × 100</strong>. Update FTE when staffing changes.
    </div>

    <div class="data-table-wrap">
      <table>
        <thead><tr><th>School</th><th>FTE Count</th><th>Days Allocation</th><th>Active</th><th></th></tr></thead>
        <tbody>
          <?php
          $totalFte = array_sum(array_column($schools, 'fte_count')) ?: 1;
          foreach ($schools as $s):
            $days = $totalFte > 0 ? round(($s['fte_count'] / $totalFte) * 100, 1) : 0;
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
            <td>
              <form method="POST" style="display:flex;gap:.4rem;align-items:center;">
                <input type="hidden" name="action"       value="update_school">
                <input type="hidden" name="school_id_edit" value="<?= $s['id'] ?>">
                <input type="number" name="fte_count" value="<?= $s['fte_count'] ?>" min="0" step="0.5"
                  style="width:75px;border:1px solid var(--gray-300);border-radius:6px;padding:.3rem .5rem;font-size:.88rem;">
                <button type="submit" class="btn btn-outline" style="padding:.3rem .65rem;font-size:.78rem;">Save</button>
              </form>
            </td>
            <td style="font-weight:700;color:var(--primary);"><?= $days ?> days</td>
            <td><span style="font-size:.78rem;color:<?= $s['active'] ? '#166534' : '#999' ?>;"><?= $s['active'] ? 'Active' : 'Inactive' ?></span></td>
            <td style="font-size:.78rem;color:var(--gray-400);">ID <?= $s['id'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f8f9fa;">
            <td><strong>Total</strong></td>
            <td><strong><?= number_format($totalFte, 1) ?> FTE</strong></td>
            <td><strong>100 days</strong></td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Add school -->
    <div class="form-card">
      <h2>Add a School</h2>
      <form method="POST">
        <input type="hidden" name="action" value="add_school">
        <div class="field-row">
          <div class="field">
            <label>School Name *</label>
            <input type="text" name="school_name" required placeholder="e.g. New School">
          </div>
          <div class="field">
            <label>FTE Count</label>
            <input type="number" name="school_fte" value="1.0" min="0.5" step="0.5">
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">Add School</button>
      </form>
    </div>
  </div>


</div>

<?php memberPickerScript(true); ?>
<script>
function switchTab(name) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  event.currentTarget.classList.add('active');
}

function toggleSchoolField(role) {
  document.getElementById('schoolField').style.display = role === 'site_rep' ? 'block' : 'none';
}

</script>
</body>
</html>
