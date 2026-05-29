<?php
/**
 * roles-overview.php — Unified executive & role directory (with inline EC editing)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
require_once __DIR__ . '/exp-db.php';
require_once __DIR__ . '/exec-db.php';

requireLogin();
$member = getMember();
prodEnsureTables();
expEnsureTables();
execEnsureTables();

$isAdmin = execIsAdmin($member['email']);

// Accessible to EC admin, prod exec, or expense admin
if (!$isAdmin && !prodIsExec($member['email']) && !expIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

// ── POST handlers (admin only) ─────────────────────────────────────────────────
$notice = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign_role') {
        $slug  = trim($_POST['role_slug']     ?? '');
        $email = strtolower(trim($_POST['member_email'] ?? ''));
        $name  = trim($_POST['member_name']   ?? '');
        $allR  = execGetAllRoles();

        if (!$slug || !array_key_exists($slug, $allR) || !$email || !$name) {
            $error = 'Select a member before saving.';
        } else {
            // Remove whoever currently holds this position
            getDB()->prepare("DELETE FROM exec_roles WHERE role=?")->execute([$slug]);
            // Enforce 2-role max (re-count after removal)
            if (execCountRoles($email) >= 2) {
                $error = htmlspecialchars($name) . ' already holds 2 roles — remove one first.';
            } else {
                execAssignRole($email, $name, $slug, $member['email']);
                $notice = htmlspecialchars($name) . ' assigned as ' . htmlspecialchars($allR[$slug]) . '.';
            }
        }
    }

    if ($action === 'remove_role') {
        $id = (int)($_POST['role_id'] ?? 0);
        if ($id > 0) {
            execRemoveRole($id);
            $notice = 'Position cleared.';
        }
    }

    // PRG: redirect to avoid resubmit on refresh
    $qs = $notice ? '?saved=1' : ($error ? '?err=' . urlencode($error) : '');
    header('Location: roles-overview.php' . $qs);
    exit;
}

if (isset($_GET['saved']))  $notice = 'Saved.';
if (isset($_GET['err']))    $error  = htmlspecialchars($_GET['err']);

// ── Load data ──────────────────────────────────────────────────────────────────

$ecAllRoles  = execGetAllRoles();
$ecRosterMap = execGetRosterMap();
$ecPeople    = execGetPeople();
$ecFilled    = count(array_filter($ecRosterMap, function($v) { return $v !== null; }));
$ecVacant    = count($ecRosterMap) - $ecFilled;
$ecPositions = array_filter($ecAllRoles, function($label, $slug) {
    return strpos($slug, 'staff_rep_') !== 0;
}, ARRAY_FILTER_USE_BOTH);
$ecStaffReps = array_filter($ecAllRoles, function($label, $slug) {
    return strpos($slug, 'staff_rep_') === 0;
}, ARRAY_FILTER_USE_BOTH);

// System roles (exp + prod)
$expRoles = getDB()->query(
    "SELECT user_email, user_name, role, assigned_by FROM exp_roles ORDER BY user_name, role"
)->fetchAll();
$prodRoles = getDB()->query(
    "SELECT r.id, r.user_email, r.user_name, r.role, r.school_id, r.assigned_by,
            s.name AS school_name
     FROM prod_roles r LEFT JOIN prod_schools s ON s.id = r.school_id
     ORDER BY r.role, r.user_name"
)->fetchAll();

$allSchools   = prodGetSchools(false);
$totalSiteReps = count(array_filter($prodRoles, function($r) { return $r['role'] === 'site_rep'; }));
$adminEmail   = defined('PROD_ADMIN_EMAIL') ? strtolower(trim(PROD_ADMIN_EMAIL)) : null;

// Vacancy checks for system roles
$expRoleCols      = array_column($expRoles, 'role');
$hasExpTreasurer  = in_array('treasurer', $expRoleCols);
$hasSigner2       = in_array('vp', $expRoleCols) || in_array('president', $expRoleCols) || (bool)$adminEmail;
$prodRoleCols     = array_column($prodRoles, 'role');
$hasProdTreasurer = in_array('treasurer', $prodRoleCols);

// Member list for inline typeahead (JSON-embedded)
$memberRows = getDB()->query("SELECT name, email FROM members ORDER BY name")->fetchAll();
$memberJson = json_encode(array_map(function($m) {
    return ['name' => $m['name'], 'email' => $m['email']];
}, $memberRows));

// Site reps grouped by school
$siteReps = [];
foreach ($prodRoles as $r) {
    if ($r['role'] === 'site_rep') {
        $siteReps[$r['school_name'] ?? 'Unassigned'][] = $r;
    }
}
ksort($siteReps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Executive &amp; Roles Directory — BVTU</title>
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
    .btn-sm { padding: .4rem .85rem; font-size: .82rem; border-radius: 7px; font-weight: 600;
               text-decoration: none; border: 1px solid var(--gray-300); color: var(--gray-700);
               background: #fff; cursor: pointer; transition: background .12s; }
    .btn-sm:hover { background: #f0fdf4; border-color: var(--primary); color: var(--primary); }

    .notice   { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                padding: .7rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box{ background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
                padding: .7rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }

    /* Stats */
    .stat-strip { display: flex; gap: .65rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .stat-chip { background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
                 padding: .5rem .9rem; text-align: center; }
    .stat-chip .num { font-size: 1.2rem; font-weight: 800; color: var(--primary); line-height: 1; }
    .stat-chip .lbl { font-size: .68rem; color: var(--gray-400); margin-top: .15rem;
                      text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }

    /* Vacancy chips */
    .vacancy-strip { display: flex; flex-wrap: wrap; gap: .6rem; margin-bottom: 1.5rem; }
    .vacancy-chip { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;
                    padding: .4rem .8rem; font-size: .8rem; color: #92400e; font-weight: 600; }
    .vacancy-chip a { color: inherit; }

    /* Section heading */
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase;
                letter-spacing: .08em; color: var(--gray-400); margin: 2rem 0 .65rem;
                display: flex; align-items: center; gap: .5rem; }
    .sec-head .badge { font-size: .7rem; font-weight: 700; background: #fef3c7;
                       color: #d97706; border-radius: 100px; padding: .1rem .5rem; }

    /* ── EC Roster table ────────────────────────────────────────────────────── */
    .roster-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
                   overflow: visible; margin-bottom: 1.5rem; }
    .roster-wrap table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .roster-wrap thead tr { background: #f8f9fa; }
    .roster-wrap th { padding: .55rem 1rem; text-align: left; font-size: .7rem; font-weight: 700;
                      text-transform: uppercase; letter-spacing: .05em; color: var(--gray-400);
                      border-bottom: 1px solid var(--gray-200); }
    .roster-wrap td { padding: 0; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .roster-wrap tr:last-child td { border-bottom: none; }
    .roster-wrap tr.editing { background: #f0fdf4; }

    /* Cell inner padding */
    .cell-pad { padding: .65rem 1rem; display: flex; align-items: center; gap: .5rem;
                min-height: 44px; }
    .pos-label { font-weight: 600; color: var(--gray-700); }

    /* Display mode */
    .member-name  { font-weight: 700; color: var(--gray-800); }
    .member-email { font-size: .78rem; color: var(--gray-400); margin-left: .35rem; }
    .vacant-label { color: var(--gray-300); font-style: italic; font-size: .84rem;
                    cursor: pointer; }
    .vacant-label:hover { color: var(--primary); }

    /* Action buttons */
    .act-btn { background: none; border: none; cursor: pointer; padding: .25rem .4rem;
               border-radius: 5px; font-size: .78rem; color: var(--gray-300); line-height: 1;
               transition: background .1s, color .1s; }
    .act-btn:hover { background: #f0fdf4; color: var(--primary); }
    .act-btn.danger:hover { background: #fef2f2; color: #dc2626; }
    .act-btn.editing-active { color: var(--primary); }

    /* Edit mode */
    .edit-zone { display: none; padding: .5rem 1rem; align-items: center; gap: .5rem; flex-wrap: wrap; }
    .edit-zone.open { display: flex; }

    .typeahead-wrap { position: relative; flex: 1; min-width: 180px; max-width: 320px; }
    .typeahead-input { width: 100%; border: 1.5px solid var(--primary); border-radius: 7px;
                       padding: .45rem .75rem; font-size: .88rem; font-family: inherit;
                       outline: none; box-shadow: 0 0 0 3px rgba(26,107,53,.1); box-sizing: border-box; }
    .suggestions { position: absolute; top: calc(100% + 3px); left: 0; right: 0; z-index: 100;
                   background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
                   box-shadow: 0 4px 16px rgba(0,0,0,.1); list-style: none; padding: .3rem 0;
                   margin: 0; max-height: 200px; overflow-y: auto; display: none; }
    .suggestions li { padding: .45rem .85rem; cursor: pointer; font-size: .85rem; color: var(--gray-700); }
    .suggestions li:hover, .suggestions li.active { background: #f0fdf4; color: var(--primary); }
    .suggestions .sugg-name { font-weight: 700; }
    .suggestions .sugg-email { font-size: .75rem; color: var(--gray-400); }
    .save-btn { background: var(--primary); color: #fff; border: none; border-radius: 7px;
                padding: .45rem .9rem; font-size: .85rem; font-weight: 700; cursor: pointer;
                opacity: .45; pointer-events: none; transition: opacity .15s; }
    .save-btn.ready { opacity: 1; pointer-events: auto; }
    .cancel-btn { background: none; border: 1px solid var(--gray-300); border-radius: 7px;
                  padding: .43rem .75rem; font-size: .85rem; cursor: pointer; color: var(--gray-500); }
    .cancel-btn:hover { background: var(--gray-100); }

    /* System roles person cards */
    .people-grid { display: flex; flex-direction: column; gap: .5rem; }
    .person-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
                   padding: .8rem 1.1rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .person-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--primary);
                     color: #fff; font-weight: 800; font-size: .95rem; display: flex;
                     align-items: center; justify-content: center; flex-shrink: 0; }
    .person-info { flex: 1; min-width: 0; }
    .person-name  { font-weight: 700; font-size: .92rem; color: var(--gray-800); }
    .person-email { font-size: .77rem; color: var(--gray-400); word-break: break-all; }
    .person-badges { display: flex; flex-wrap: wrap; gap: .35rem; }
    .role-badge { display: inline-block; font-size: .7rem; font-weight: 700; text-transform: uppercase;
                  letter-spacing: .04em; padding: .2rem .6rem; border-radius: 100px; }

    /* Site rep section */
    .school-section { margin-bottom: 1rem; }
    .school-heading { font-size: .86rem; font-weight: 800; color: var(--gray-700);
                      margin-bottom: .35rem; display: flex; align-items: center; gap: .45rem; }
    .school-dot { width: 7px; height: 7px; border-radius: 50%; background: #7c3aed; flex-shrink: 0; }
    .rep-row { background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
               padding: .55rem .9rem; display: flex; align-items: center; gap: .7rem;
               font-size: .86rem; margin-bottom: .3rem; }
    .rep-avatar { width: 28px; height: 28px; border-radius: 50%; background: #7c3aed; color: #fff;
                  font-weight: 800; font-size: .78rem; display: flex; align-items: center;
                  justify-content: center; flex-shrink: 0; }
    .no-rep { font-size: .82rem; color: var(--gray-300); font-style: italic; padding: .4rem 0; }

    @media(max-width:600px) {
      .person-card { flex-direction: column; align-items: flex-start; }
      .edit-zone { flex-direction: column; align-items: stretch; }
      .typeahead-wrap { max-width: 100%; }
    }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Executive &amp; Roles Directory</h1>
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
      <a href="prod-manage.php" class="btn-sm">Pro-D Roles</a>
      <a href="exp-manage.php"  class="btn-sm">Expense Roles</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <!-- Stat strip -->
  <div class="stat-strip">
    <div class="stat-chip">
      <div class="num"><?= $ecFilled ?>&thinsp;/&thinsp;<?= count($ecAllRoles) ?></div>
      <div class="lbl">EC positions filled</div>
    </div>
    <div class="stat-chip">
      <div class="num"><?= count($ecPeople) ?></div>
      <div class="lbl">EC members</div>
    </div>
    <div class="stat-chip">
      <div class="num"><?= $totalSiteReps ?></div>
      <div class="lbl">Pro-D site reps</div>
    </div>
    <div class="stat-chip">
      <div class="num"><?= count($allSchools) ?></div>
      <div class="lbl">Schools</div>
    </div>
  </div>

  <!-- Vacancy alerts for system roles -->
  <?php if (!$hasExpTreasurer || !$hasSigner2 || !$hasProdTreasurer): ?>
  <div class="vacancy-strip">
    <?php if (!$hasExpTreasurer): ?>
    <div class="vacancy-chip">&#x26A0; No BVTU Treasurer assigned for expense approvals &mdash; <a href="exp-manage.php">assign one</a></div>
    <?php endif; ?>
    <?php if (!$hasSigner2): ?>
    <div class="vacancy-chip">&#x26A0; No VP or President assigned for expense second signature &mdash; <a href="exp-manage.php">assign one</a></div>
    <?php endif; ?>
    <?php if (!$hasProdTreasurer): ?>
    <div class="vacancy-chip">&#x26A0; No Pro-D Treasurer assigned &mdash; <a href="prod-manage.php">assign one</a></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       EC ROSTER
  ════════════════════════════════════════════════════════════════════════════ -->

  <?php
  // ── Render one roster table (display + optional inline edit) ──────────────
  function renderRoster(array $roles, array $rosterMap, bool $isAdmin, string $slug_prefix = '') {
  ?>
  <div class="roster-wrap">
    <table>
      <thead><tr>
        <th style="width:38%;">Position</th>
        <th>Member</th>
        <?php if ($isAdmin): ?><th style="width:90px;"></th><?php endif; ?>
      </tr></thead>
      <tbody>
        <?php foreach ($roles as $slug => $label):
          $row = $rosterMap[$slug] ?? null;
          $rowId = 'row-' . $slug;
        ?>
        <tr id="<?= $rowId ?>">

          <!-- Position label -->
          <td>
            <div class="cell-pad">
              <span class="pos-label"><?= htmlspecialchars($label) ?></span>
            </div>
          </td>

          <!-- Member (display + edit) -->
          <td>
            <!-- Display -->
            <div class="cell-pad" id="display-<?= $slug ?>">
              <?php if ($row): ?>
                <span class="member-name"><?= htmlspecialchars($row['user_name']) ?></span>
                <span class="member-email"><?= htmlspecialchars($row['user_email']) ?></span>
              <?php else: ?>
                <span class="vacant-label" <?= $isAdmin ? 'onclick="openEdit(\'' . $slug . '\')" title="Click to assign"' : '' ?>>Vacant</span>
              <?php endif; ?>
            </div>
            <!-- Edit (admins only) -->
            <?php if ($isAdmin): ?>
            <form method="POST" id="form-<?= $slug ?>">
              <input type="hidden" name="action"       value="assign_role">
              <input type="hidden" name="role_slug"    value="<?= $slug ?>">
              <input type="hidden" name="member_email" id="sel-email-<?= $slug ?>" value="">
              <input type="hidden" name="member_name"  id="sel-name-<?= $slug ?>"  value="">
              <div class="edit-zone" id="edit-<?= $slug ?>">
                <div class="typeahead-wrap">
                  <input type="text" class="typeahead-input" id="search-<?= $slug ?>"
                         placeholder="Type name or email&hellip;" autocomplete="off"
                         oninput="filterMembers('<?= $slug ?>')"
                         onkeydown="handleKey(event,'<?= $slug ?>')">
                  <ul class="suggestions" id="sugg-<?= $slug ?>"></ul>
                </div>
                <button type="submit" class="save-btn" id="save-<?= $slug ?>">Save</button>
                <button type="button" class="cancel-btn" onclick="closeEdit('<?= $slug ?>')">Cancel</button>
              </div>
            </form>
            <?php endif; ?>
          </td>

          <!-- Actions (admins only) -->
          <?php if ($isAdmin): ?>
          <td>
            <div class="cell-pad" id="actions-<?= $slug ?>">
              <button type="button" class="act-btn" id="edit-btn-<?= $slug ?>"
                      onclick="openEdit('<?= $slug ?>')" title="<?= $row ? 'Change' : 'Assign' ?>">
                &#x270E;
              </button>
              <?php if ($row): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Clear <?= htmlspecialchars(addslashes($label)) ?>?')">
                <input type="hidden" name="action"  value="remove_role">
                <input type="hidden" name="role_id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="act-btn danger" title="Clear position">&#x2715;</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>

        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php } // end renderRoster ?>

  <!-- Executive Positions -->
  <div class="sec-head">
    Executive Committee
    <?php $epVacant = count(array_filter(array_intersect_key($ecRosterMap, $ecPositions), function($v) { return $v === null; }));
          if ($epVacant > 0) echo '<span class="badge">' . $epVacant . ' vacant</span>'; ?>
  </div>
  <?php renderRoster($ecPositions, $ecRosterMap, $isAdmin); ?>

  <!-- School Staff Representatives -->
  <?php if (!empty($ecStaffReps)): ?>
  <div class="sec-head">
    School Staff Representatives
    <?php $srVacant = count(array_filter(array_intersect_key($ecRosterMap, $ecStaffReps), function($v) { return $v === null; }));
          if ($srVacant > 0) echo '<span class="badge">' . $srVacant . ' vacant</span>'; ?>
  </div>
  <?php renderRoster($ecStaffReps, $ecRosterMap, $isAdmin); ?>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
  <p style="font-size:.78rem;color:var(--gray-400);margin-top:-.5rem;margin-bottom:1.75rem;">
    &#x270E; Click a pencil to assign a member. Type at least 2 characters to search by name or email.
  </p>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       SYSTEM ROLES (Expense portal + Pro-D)
  ════════════════════════════════════════════════════════════════════════════ -->

  <?php
  // Build the people map for system roles
  $expMeta = [
      'president' => ['label' => 'President',      'color' => '#7c3aed', 'bg' => '#f5f3ff'],
      'admin'     => ['label' => 'Admin',           'color' => '#991b1b', 'bg' => '#fef2f2'],
      'vp'        => ['label' => 'Vice President',  'color' => '#1e40af', 'bg' => '#eff6ff'],
      'treasurer' => ['label' => 'BVTU Treasurer',  'color' => '#166534', 'bg' => '#f0fdf4'],
  ];
  $prodMeta = [
      'exec'      => ['label' => 'Executive (Pro-D)','color' => '#1e40af', 'bg' => '#eff6ff'],
      'treasurer' => ['label' => 'Pro-D Treasurer',  'color' => '#166534', 'bg' => '#f0fdf4'],
      'site_rep'  => ['label' => 'Site Rep',         'color' => '#7c3aed', 'bg' => '#f5f3ff'],
  ];

  $sysPeople = [];
  if ($adminEmail) {
      $s = getDB()->prepare("SELECT name FROM members WHERE email=?");
      $s->execute([$adminEmail]);
      $sysPeople[$adminEmail] = ['name' => $s->fetchColumn() ?: 'Admin', 'email' => $adminEmail, 'roles' => [], 'is_const' => true];
  }
  foreach ($expRoles as $r) {
      $e = strtolower(trim($r['user_email']));
      if (!isset($sysPeople[$e])) $sysPeople[$e] = ['name' => $r['user_name'] ?: $e, 'email' => $e, 'roles' => [], 'is_const' => ($e === $adminEmail)];
      $m = $expMeta[$r['role']] ?? ['label' => $r['role'], 'color' => '#555', 'bg' => '#f8f9fa'];
      $sysPeople[$e]['roles'][] = ['label' => $m['label'], 'color' => $m['color'], 'bg' => $m['bg']];
  }
  foreach ($prodRoles as $r) {
      if ($r['role'] === 'site_rep') continue; // site reps get their own section
      $e = strtolower(trim($r['user_email']));
      if (!isset($sysPeople[$e])) $sysPeople[$e] = ['name' => $r['user_name'] ?: $e, 'email' => $e, 'roles' => [], 'is_const' => ($e === $adminEmail)];
      $m = $prodMeta[$r['role']] ?? ['label' => $r['role'], 'color' => '#555', 'bg' => '#f8f9fa'];
      $sysPeople[$e]['roles'][] = ['label' => $m['label'], 'color' => $m['color'], 'bg' => $m['bg']];
  }
  ?>

  <?php if (!empty($sysPeople)): ?>
  <div class="sec-head" style="margin-top:2.5rem;">Portal System Roles
    <span style="font-weight:400;font-size:.7rem;color:var(--gray-400);text-transform:none;letter-spacing:0;">
      (expense approvals &amp; Pro-D) &mdash; manage via
      <a href="exp-manage.php" style="color:var(--primary);">Expense Roles</a> /
      <a href="prod-manage.php" style="color:var(--primary);">Pro-D Roles</a>
    </span>
  </div>
  <div class="people-grid">
    <?php foreach ($sysPeople as $email => $p):
      $initial = strtoupper(mb_substr($p['name'], 0, 1));
      $isYou   = ($email === strtolower(trim($member['email'])));
    ?>
    <div class="person-card" style="<?= $isYou ? 'border-color:#86efac;background:#f0fdf4;' : '' ?>">
      <div class="person-avatar"><?= htmlspecialchars($initial) ?></div>
      <div class="person-info">
        <div class="person-name"><?= htmlspecialchars($p['name']) ?><?= $isYou ? ' <span style="font-size:.7rem;color:#166534;font-weight:600;">(you)</span>' : '' ?></div>
        <div class="person-email"><?= htmlspecialchars($email) ?></div>
      </div>
      <div class="person-badges">
        <?php if ($p['is_const']): ?>
          <span style="font-size:.7rem;color:#9ca3af;font-style:italic;">&#x1F512; via config</span>
        <?php endif; ?>
        <?php foreach ($p['roles'] as $role): ?>
          <span class="role-badge" style="background:<?= $role['bg'] ?>;color:<?= $role['color'] ?>;"><?= htmlspecialchars($role['label']) ?></span>
        <?php endforeach; ?>
        <?php if (empty($p['roles']) && $p['is_const']): ?>
          <span class="role-badge" style="background:#fef2f2;color:#991b1b;">Admin</span>
          <span class="role-badge" style="background:#f5f3ff;color:#7c3aed;">President</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       PRO-D SITE REPS BY SCHOOL
  ════════════════════════════════════════════════════════════════════════════ -->

  <div class="sec-head" style="margin-top:2.5rem;">Pro-D Site Reps by School</div>

  <?php
  $schoolsWithRep = array_keys($siteReps);
  $schoolsNoRep   = array_filter($allSchools, function($s) use ($schoolsWithRep) {
      return !in_array($s['name'], $schoolsWithRep);
  });
  ?>

  <?php foreach ($siteReps as $schoolName => $reps): ?>
  <div class="school-section">
    <div class="school-heading">
      <span class="school-dot"></span>
      <?= htmlspecialchars($schoolName) ?>
      <span style="font-size:.73rem;color:var(--gray-400);font-weight:500;"><?= count($reps) ?> rep<?= count($reps) !== 1 ? 's' : '' ?></span>
    </div>
    <?php foreach ($reps as $rep): ?>
    <div class="rep-row">
      <div class="rep-avatar"><?= strtoupper(mb_substr($rep['user_name'] ?: '?', 0, 1)) ?></div>
      <div>
        <strong><?= htmlspecialchars($rep['user_name'] ?: '—') ?></strong>
        <span style="font-size:.78rem;color:var(--gray-400);margin-left:.4rem;"><?= htmlspecialchars($rep['user_email']) ?></span>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>

  <?php if (empty($siteReps)): ?>
  <p style="font-size:.85rem;color:var(--gray-400);">No site reps assigned yet. <a href="prod-manage.php">Assign a site rep</a>.</p>
  <?php endif; ?>

  <?php if ($schoolsNoRep): ?>
  <div class="sec-head" style="color:#d97706;">Schools without a Pro-D site rep</div>
  <?php foreach ($schoolsNoRep as $s): ?>
  <div class="school-section">
    <div class="school-heading" style="color:var(--gray-400);">
      <span class="school-dot" style="background:#d1d5db;"></span>
      <?= htmlspecialchars($s['name']) ?>
    </div>
    <div class="no-rep">No rep assigned &mdash; <a href="prod-manage.php?tab=roles">assign one</a></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php if ($isAdmin): ?>
<script>
const MEMBERS = <?= $memberJson ?>;
let activeSuggIdx = {};

function openEdit(slug) {
    // Close any other open editors first
    document.querySelectorAll('.edit-zone.open').forEach(function(z) {
        var s = z.id.replace('edit-', '');
        if (s !== slug) closeEdit(s);
    });

    var display = document.getElementById('display-' + slug);
    var editZone = document.getElementById('edit-' + slug);
    var row = document.getElementById('row-' + slug);
    var editBtn = document.getElementById('edit-btn-' + slug);

    display.style.display = 'none';
    editZone.classList.add('open');
    row.classList.add('editing');
    if (editBtn) editBtn.classList.add('editing-active');

    var input = document.getElementById('search-' + slug);
    input.value = '';
    document.getElementById('sel-email-' + slug).value = '';
    document.getElementById('sel-name-' + slug).value = '';
    document.getElementById('save-' + slug).classList.remove('ready');
    input.focus();
    activeSuggIdx[slug] = -1;
}

function closeEdit(slug) {
    var display = document.getElementById('display-' + slug);
    var editZone = document.getElementById('edit-' + slug);
    var row = document.getElementById('row-' + slug);
    var editBtn = document.getElementById('edit-btn-' + slug);

    if (display)  display.style.display = '';
    if (editZone) editZone.classList.remove('open');
    if (row)      row.classList.remove('editing');
    if (editBtn)  editBtn.classList.remove('editing-active');
    hideSuggestions(slug);
}

function filterMembers(slug) {
    var q = document.getElementById('search-' + slug).value.trim().toLowerCase();
    var list = document.getElementById('sugg-' + slug);
    activeSuggIdx[slug] = -1;

    // Clear selection when user edits
    document.getElementById('sel-email-' + slug).value = '';
    document.getElementById('sel-name-' + slug).value = '';
    document.getElementById('save-' + slug).classList.remove('ready');

    if (q.length < 2) { hideSuggestions(slug); return; }

    var matches = MEMBERS.filter(function(m) {
        return m.name.toLowerCase().indexOf(q) !== -1 || m.email.toLowerCase().indexOf(q) !== -1;
    }).slice(0, 8);

    if (!matches.length) { hideSuggestions(slug); return; }

    list.innerHTML = '';
    matches.forEach(function(m, i) {
        var li = document.createElement('li');
        li.setAttribute('data-idx', i);
        li.innerHTML = '<span class="sugg-name">' + escHtml(m.name) + '</span>'
                     + '<br><span class="sugg-email">' + escHtml(m.email) + '</span>';
        li.addEventListener('mousedown', function(e) {
            e.preventDefault(); // don't blur input
            selectMember(slug, m);
        });
        list.appendChild(li);
    });
    list.style.display = 'block';
    list._matches = matches;
}

function handleKey(e, slug) {
    var list = document.getElementById('sugg-' + slug);
    if (!list || list.style.display === 'none') return;
    var items = list.querySelectorAll('li');
    var idx = activeSuggIdx[slug] || -1;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        idx = Math.min(idx + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        idx = Math.max(idx - 1, -1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (idx >= 0 && list._matches && list._matches[idx]) {
            selectMember(slug, list._matches[idx]);
        }
        return;
    } else if (e.key === 'Escape') {
        closeEdit(slug);
        return;
    } else {
        return;
    }

    activeSuggIdx[slug] = idx;
    items.forEach(function(li, i) { li.classList.toggle('active', i === idx); });
}

function selectMember(slug, m) {
    document.getElementById('search-' + slug).value = m.name;
    document.getElementById('sel-email-' + slug).value = m.email;
    document.getElementById('sel-name-' + slug).value = m.name;
    document.getElementById('save-' + slug).classList.add('ready');
    hideSuggestions(slug);
}

function hideSuggestions(slug) {
    var list = document.getElementById('sugg-' + slug);
    if (list) list.style.display = 'none';
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Close suggestions when clicking outside
document.addEventListener('click', function(e) {
    document.querySelectorAll('.edit-zone.open').forEach(function(zone) {
        var slug = zone.id.replace('edit-', '');
        if (!zone.contains(e.target)) {
            hideSuggestions(slug);
        }
    });
});
</script>
<?php endif; ?>

</body>
</html>
