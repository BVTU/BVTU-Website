<?php
/**
 * roles-overview.php — Unified executive & role directory
 * Shows all role-holders across EC, Pro-D, and Expense portal systems.
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

// Accessible to EC admin, prod exec, or expense admin
if (!execIsAdmin($member['email']) && !prodIsExec($member['email']) && !expIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

// ── Executive Committee data ───────────────────────────────────────────────────
$ecRosterMap = execGetRosterMap();
$ecPeople    = execGetPeople();
$ecFilled    = count(array_filter($ecRosterMap, function($v) { return $v !== null; }));
$ecVacant    = count($ecRosterMap) - $ecFilled;

// ── Load raw role data ─────────────────────────────────────────────────────────

$expRoles = getDB()->query(
    "SELECT user_email, user_name, role, assigned_by FROM exp_roles ORDER BY user_name, role"
)->fetchAll();

$prodRoles = getDB()->query(
    "SELECT r.id, r.user_email, r.user_name, r.role, r.school_id, r.assigned_by,
            s.name AS school_name
     FROM prod_roles r
     LEFT JOIN prod_schools s ON s.id = r.school_id
     ORDER BY r.role, r.user_name"
)->fetchAll();

$adminEmail = defined('PROD_ADMIN_EMAIL') ? strtolower(trim(PROD_ADMIN_EMAIL)) : null;

// ── Role metadata ──────────────────────────────────────────────────────────────

$expMeta = [
    'president' => ['label' => 'President',       'color' => '#7c3aed', 'bg' => '#f5f3ff', 'order' => 0],
    'admin'     => ['label' => 'Admin',            'color' => '#991b1b', 'bg' => '#fef2f2', 'order' => 1],
    'vp'        => ['label' => 'Vice President',   'color' => '#1e40af', 'bg' => '#eff6ff', 'order' => 2],
    'treasurer' => ['label' => 'BVTU Treasurer',   'color' => '#166534', 'bg' => '#f0fdf4', 'order' => 3],
];
$prodMeta = [
    'exec'      => ['label' => 'Executive (Pro-D)','color' => '#1e40af', 'bg' => '#eff6ff', 'order' => 10],
    'treasurer' => ['label' => 'Pro-D Treasurer',  'color' => '#166534', 'bg' => '#f0fdf4', 'order' => 11],
    'site_rep'  => ['label' => 'Site Rep',         'color' => '#7c3aed', 'bg' => '#f5f3ff', 'order' => 12],
];

// ── Build unified people map ───────────────────────────────────────────────────
// Key: email → ['name', 'roles' => [...], 'is_const_admin']

$people = [];

// Seed the constant admin first so they always appear
if ($adminEmail) {
    // Try to get their real name from members table
    $s = getDB()->prepare("SELECT name FROM members WHERE email=?");
    $s->execute([$adminEmail]);
    $realName = $s->fetchColumn() ?: 'Admin (constant)';
    $people[$adminEmail] = [
        'name'           => $realName,
        'roles'          => [],
        'is_const_admin' => true,
    ];
}

foreach ($expRoles as $r) {
    $e = strtolower(trim($r['user_email']));
    if (!isset($people[$e])) {
        $people[$e] = ['name' => $r['user_name'] ?: $e, 'roles' => [], 'is_const_admin' => ($e === $adminEmail)];
    } elseif ($r['user_name'] && $people[$e]['name'] === $adminEmail) {
        $people[$e]['name'] = $r['user_name']; // use real name when available
    }
    $meta = $expMeta[$r['role']] ?? ['label' => $r['role'], 'color' => '#555', 'bg' => '#f8f9fa', 'order' => 20];
    $people[$e]['roles'][] = [
        'system' => 'expense',
        'role'   => $r['role'],
        'label'  => $meta['label'],
        'color'  => $meta['color'],
        'bg'     => $meta['bg'],
        'order'  => $meta['order'],
        'school' => null,
    ];
}

foreach ($prodRoles as $r) {
    $e = strtolower(trim($r['user_email']));
    if (!isset($people[$e])) {
        $people[$e] = ['name' => $r['user_name'] ?: $e, 'roles' => [], 'is_const_admin' => ($e === $adminEmail)];
    }
    $meta = $prodMeta[$r['role']] ?? ['label' => $r['role'], 'color' => '#555', 'bg' => '#f8f9fa', 'order' => 20];
    $label = ($r['role'] === 'site_rep' && $r['school_name'])
        ? 'Site Rep &middot; ' . htmlspecialchars($r['school_name'])
        : $meta['label'];
    $people[$e]['roles'][] = [
        'system'    => 'prod',
        'role'      => $r['role'],
        'label'     => $label,
        'color'     => $meta['color'],
        'bg'        => $meta['bg'],
        'order'     => $meta['order'],
        'school'    => $r['school_name'] ?? null,
        'school_id' => $r['school_id']   ?? null,
    ];
}

// Sort each person's badges by order
foreach ($people as &$p) {
    usort($p['roles'], function($a, $b) { return $a['order'] - $b['order']; });
}
unset($p);

// ── Sort people: executive first, then treasurer, VP, site reps ───────────────
function personSortKey($p) {
    $roles = array_column($p['roles'], 'role');
    if ($p['is_const_admin'])              return 0;
    if (in_array('president', $roles))     return 0;
    if (in_array('admin', $roles))         return 1;
    if (in_array('exec', $roles))          return 2;
    if (in_array('vp', $roles))            return 3;
    if (in_array('treasurer', $roles))     return 4;
    if (in_array('site_rep', $roles))      return 5;
    return 6;
}

uasort($people, function($a, $b) {
    $ka = personSortKey($a);
    $kb = personSortKey($b);
    if ($ka !== $kb) return $ka - $kb;
    return strcmp($a['name'], $b['name']);
});

// ── Site reps grouped by school (for the bottom section) ──────────────────────
$siteReps = [];
foreach ($prodRoles as $r) {
    if ($r['role'] === 'site_rep') {
        $school = $r['school_name'] ?? 'Unassigned';
        $siteReps[$school][] = $r;
    }
}
ksort($siteReps);

// ── Vacancy checks ─────────────────────────────────────────────────────────────
$expRoleCols     = array_column($expRoles, 'role');
$hasExpTreasurer = in_array('treasurer', $expRoleCols);
$hasSigner2      = in_array('vp', $expRoleCols) || in_array('president', $expRoleCols) || (bool)$adminEmail;
$prodRoleCols    = array_column($prodRoles, 'role');
$hasProdTreasurer = in_array('treasurer', $prodRoleCols);

$totalSiteReps = count(array_filter($prodRoles, function($r) { return $r['role'] === 'site_rep'; }));

// ── Schools list for tooltip/count ────────────────────────────────────────────
$allSchools = prodGetSchools(false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Roles &amp; Executive Directory — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

    .portal-header { display: flex; align-items: center; justify-content: space-between;
                     margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .header-actions { display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
    .btn-sm { padding: .4rem .85rem; font-size: .82rem; border-radius: 7px; font-weight: 600;
               text-decoration: none; border: 1px solid var(--gray-300); color: var(--gray-700);
               background: #fff; cursor: pointer; transition: background .12s; }
    .btn-sm:hover { background: #f0fdf4; border-color: var(--primary); color: var(--primary); }

    /* Vacancy alerts */
    .vacancy-strip { display: flex; flex-wrap: wrap; gap: .65rem; margin-bottom: 1.5rem; }
    .vacancy-chip { display: flex; align-items: center; gap: .4rem; background: #fffbeb;
                    border: 1px solid #fde68a; border-radius: 8px; padding: .45rem .8rem;
                    font-size: .8rem; color: #92400e; font-weight: 600; }
    .vacancy-chip.ok { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }

    /* Section headings */
    .section-heading { font-size: .72rem; font-weight: 800; text-transform: uppercase;
                       letter-spacing: .08em; color: var(--gray-400); margin: 2rem 0 .75rem; }

    /* People cards */
    .people-grid { display: flex; flex-direction: column; gap: .55rem; }
    .person-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
                   padding: .85rem 1.1rem; display: flex; align-items: center;
                   gap: 1rem; flex-wrap: wrap; }
    .person-card.is-you { border-color: #86efac; background: #f0fdf4; }
    .person-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary);
                     color: #fff; font-weight: 800; font-size: 1rem; display: flex;
                     align-items: center; justify-content: center; flex-shrink: 0; }
    .person-info { flex: 1; min-width: 0; }
    .person-name { font-weight: 700; font-size: .95rem; color: var(--gray-800); }
    .person-email { font-size: .78rem; color: var(--gray-400); margin-top: .1rem; word-break: break-all; }
    .person-badges { display: flex; flex-wrap: wrap; gap: .4rem; }
    .role-badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase;
                  letter-spacing: .04em; padding: .22rem .65rem; border-radius: 100px; white-space: nowrap; }
    .const-badge { font-size: .7rem; color: #9ca3af; font-style: italic; padding: .22rem 0; }

    /* Site rep school table */
    .school-section { margin-bottom: 1.25rem; }
    .school-name-heading { font-size: .88rem; font-weight: 800; color: var(--gray-700);
                           margin-bottom: .4rem; display: flex; align-items: center; gap: .5rem; }
    .school-dot { width: 8px; height: 8px; border-radius: 50%; background: #7c3aed; flex-shrink: 0; }
    .school-cards { display: flex; flex-direction: column; gap: .35rem; }
    .rep-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
                padding: .6rem 1rem; display: flex; align-items: center; gap: .75rem; }
    .rep-card .person-avatar { width: 30px; height: 30px; font-size: .82rem; }
    .rep-name  { font-weight: 700; font-size: .88rem; color: var(--gray-800); }
    .rep-email { font-size: .75rem; color: var(--gray-400); }
    .no-rep { font-size: .82rem; color: var(--gray-400); font-style: italic; padding: .5rem 0; }

    /* Stat strip */
    .stat-strip { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .stat-chip { background: #fff; border: 1px solid var(--gray-200); border-radius: 8px;
                 padding: .55rem .9rem; text-align: center; }
    .stat-chip .num { font-size: 1.3rem; font-weight: 800; color: var(--primary); line-height: 1; }
    .stat-chip .lbl { font-size: .7rem; color: var(--gray-400); margin-top: .2rem; text-transform: uppercase;
                      letter-spacing: .04em; font-weight: 600; }

    @media (max-width: 600px) {
      .person-card { flex-direction: column; align-items: flex-start; gap: .5rem; }
    }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Roles &amp; Executive Directory</h1>
    </div>
    <div class="header-actions">
      <a href="exec-manage.php" class="btn-sm">&#x270F; Edit EC Roster</a>
      <a href="prod-manage.php" class="btn-sm">Pro-D Roles</a>
      <a href="exp-manage.php"  class="btn-sm">Expense Roles</a>
    </div>
  </div>

  <!-- ── Stat strip ─────────────────────────────────────────────────────────── -->
  <div class="stat-strip">
    <div class="stat-chip">
      <div class="num"><?= $ecFilled ?> / <?= count(EXEC_ROLES) ?></div>
      <div class="lbl">EC positions filled</div>
    </div>
    <div class="stat-chip">
      <div class="num"><?= count($ecPeople) ?></div>
      <div class="lbl">EC members</div>
    </div>
    <div class="stat-chip">
      <div class="num"><?= $totalSiteReps ?></div>
      <div class="lbl">Site reps</div>
    </div>
    <div class="stat-chip">
      <div class="num"><?= count($allSchools) ?></div>
      <div class="lbl">Schools</div>
    </div>
  </div>

  <!-- ── Vacancy warnings ───────────────────────────────────────────────────── -->
  <?php $anyVacancy = !$hasExpTreasurer || !$hasSigner2 || !$hasProdTreasurer; ?>
  <?php if ($anyVacancy): ?>
  <div class="vacancy-strip">
    <?php if (!$hasExpTreasurer): ?>
    <div class="vacancy-chip">&#x26A0; No BVTU Treasurer assigned &mdash; <a href="exp-manage.php" style="color:inherit;text-decoration:underline;margin-left:.25rem;">assign one</a></div>
    <?php endif; ?>
    <?php if (!$hasSigner2): ?>
    <div class="vacancy-chip">&#x26A0; No VP or President assigned for expense sign-off &mdash; <a href="exp-manage.php" style="color:inherit;text-decoration:underline;margin-left:.25rem;">assign one</a></div>
    <?php endif; ?>
    <?php if (!$hasProdTreasurer): ?>
    <div class="vacancy-chip">&#x26A0; No Pro-D Treasurer assigned &mdash; <a href="prod-manage.php" style="color:inherit;text-decoration:underline;margin-left:.25rem;">assign one</a></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ── EC Roster ────────────────────────────────────────────────────────────── -->
  <div class="section-heading">
    Executive Committee Roster
    <?php if ($ecVacant > 0): ?>
      <span style="font-weight:500;color:#d97706;margin-left:.5rem;"><?= $ecVacant ?> vacant</span>
    <?php endif; ?>
  </div>

  <div style="background:#fff;border:1px solid var(--gray-200);border-radius:10px;overflow:hidden;margin-bottom:1.75rem;">
    <table style="width:100%;border-collapse:collapse;font-size:.86rem;">
      <thead><tr style="background:#f8f9fa;">
        <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:1px solid var(--gray-200);">Position</th>
        <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:1px solid var(--gray-200);">Name</th>
        <th style="padding:.55rem 1rem;text-align:left;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500);border-bottom:1px solid var(--gray-200);">Email</th>
      </tr></thead>
      <tbody>
        <?php foreach (EXEC_ROLES as $slug => $label):
          $row = $ecRosterMap[$slug];
        ?>
        <tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:.6rem 1rem;font-weight:600;color:var(--gray-700);"><?= htmlspecialchars($label) ?></td>
          <?php if ($row): ?>
            <td style="padding:.6rem 1rem;">
              <strong><?= htmlspecialchars($row['user_name']) ?></strong>
              <?php
                // Show a second-role badge if this person also holds another EC role
                $otherRoles = array_filter($ecRosterMap, function($r) use ($row, $slug) {
                    return $r !== null && $r['user_email'] === $row['user_email'] && $r['role'] !== $slug;
                });
                foreach ($otherRoles as $oSlug => $oRow): ?>
                <span style="font-size:.7rem;background:#eff6ff;color:#1e40af;border-radius:100px;padding:.1rem .5rem;margin-left:.35rem;font-weight:700;">also <?= htmlspecialchars(EXEC_ROLES[$oSlug] ?? $oSlug) ?></span>
              <?php endforeach; ?>
            </td>
            <td style="padding:.6rem 1rem;font-size:.79rem;color:var(--gray-400);"><?= htmlspecialchars($row['user_email']) ?></td>
          <?php else: ?>
            <td style="padding:.6rem 1rem;color:var(--gray-300);font-style:italic;font-size:.84rem;">Vacant</td>
            <td style="padding:.6rem 1rem;color:var(--gray-200);">&#x2014;</td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Executive & role holders ──────────────────────────────────────────── -->
  <div class="section-heading">Executive &amp; Role Holders</div>

  <?php if (empty($people)): ?>
  <div style="color:var(--gray-400);font-size:.88rem;padding:1.5rem 0;">No roles assigned yet. Use <a href="prod-manage.php">Pro-D Roles</a> or <a href="exp-manage.php">Expense Roles</a> to get started.</div>
  <?php else: ?>
  <div class="people-grid">
    <?php foreach ($people as $email => $p):
      $initial  = strtoupper(mb_substr($p['name'], 0, 1));
      $isYou    = ($email === strtolower(trim($member['email'])));
      $isSiteRepOnly = (count($p['roles']) > 0 && array_sum(array_map(function($r) {
          return ($r['role'] === 'site_rep') ? 1 : 0;
      }, $p['roles'])) === count($p['roles']));
      // Skip pure site reps — they get their own section below
      if ($isSiteRepOnly) continue;
    ?>
    <div class="person-card <?= $isYou ? 'is-you' : '' ?>">
      <div class="person-avatar"><?= htmlspecialchars($initial) ?></div>
      <div class="person-info">
        <div class="person-name"><?= htmlspecialchars($p['name']) ?><?= $isYou ? ' <span style="font-size:.72rem;color:#166534;font-weight:600;">(you)</span>' : '' ?></div>
        <div class="person-email"><?= htmlspecialchars($email) ?></div>
      </div>
      <div class="person-badges">
        <?php if ($p['is_const_admin'] && $email === $adminEmail): ?>
          <span class="const-badge">&#x1F512; admin via config</span>
        <?php endif; ?>
        <?php foreach ($p['roles'] as $role): ?>
          <span class="role-badge" style="background:<?= $role['bg'] ?>;color:<?= $role['color'] ?>;">
            <?= $role['label'] ?>
          </span>
        <?php endforeach; ?>
        <?php if (empty($p['roles']) && $p['is_const_admin']): ?>
          <span class="role-badge" style="background:#fef2f2;color:#991b1b;">Admin</span>
          <span class="role-badge" style="background:#f5f3ff;color:#7c3aed;">President</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── Pro-D Site Reps by school ─────────────────────────────────────────── -->
  <div class="section-heading" style="margin-top:2.5rem;">Pro-D Site Reps by School</div>

  <?php if (empty($siteReps)): ?>
  <p style="font-size:.85rem;color:var(--gray-400);">No site reps assigned yet. <a href="prod-manage.php">Assign a site rep</a>.</p>

  <?php else: ?>

  <?php
  // Also show schools with NO rep assigned
  $schoolsWithRep = array_keys($siteReps);
  $schoolsNoRep   = array_filter($allSchools, function($s) use ($schoolsWithRep) {
      return !in_array($s['name'], $schoolsWithRep);
  });
  ?>

  <?php foreach ($siteReps as $schoolName => $reps): ?>
  <div class="school-section">
    <div class="school-name-heading">
      <span class="school-dot"></span>
      <?= htmlspecialchars($schoolName) ?>
      <span style="font-size:.75rem;color:var(--gray-400);font-weight:500;"><?= count($reps) ?> rep<?= count($reps) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="school-cards">
      <?php foreach ($reps as $rep): ?>
      <div class="rep-card">
        <div class="person-avatar" style="background:#7c3aed;"><?= strtoupper(mb_substr($rep['user_name'] ?: '?', 0, 1)) ?></div>
        <div>
          <div class="rep-name"><?= htmlspecialchars($rep['user_name'] ?: '—') ?></div>
          <div class="rep-email"><?= htmlspecialchars($rep['user_email']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if ($schoolsNoRep): ?>
  <div class="section-heading" style="margin-top:1.5rem;color:#d97706;">Schools without a site rep</div>
  <?php foreach ($schoolsNoRep as $s): ?>
  <div class="school-section">
    <div class="school-name-heading" style="color:var(--gray-400);">
      <span class="school-dot" style="background:#d1d5db;"></span>
      <?= htmlspecialchars($s['name']) ?>
    </div>
    <div class="no-rep">No rep assigned &mdash; <a href="prod-manage.php?tab=roles">assign one</a></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php endif; ?>

</div>
</body>
</html>
