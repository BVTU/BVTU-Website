<?php
/**
 * contacts.php — the contact list.
 *
 * Reads only our own database. Mailchimp is never called on a page load; the
 * stored mailchimp_status is what shows, refreshed by the webhook or by an
 * explicit sync. A list view that called an API per row would be slow and would
 * burn the rate limit for nothing.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/contacts-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

sendPrivateHeaders();
contactsEnsureTables();

$notice = htmlspecialchars($_GET['notice'] ?? '');
$error  = htmlspecialchars($_GET['error']  ?? '');

// One-time move of the imported roster; safe to re-run, skips anyone present.
if (($_POST['action'] ?? '') === 'import_roster') {
    csrfCheck();
    $n = contactsMigrateFromInvitations($member['email']);
    header('Location: contacts.php?notice=' . urlencode($n
        ? "Brought {$n} people across from the membership roster."
        : 'Nothing new to bring across — everyone on the roster is already here.'));
    exit;
}

// Pull login accounts into the contact list, then link the two by email, so
// the contact count is the number of people the union actually has.
if (($_POST['action'] ?? '') === 'reconcile_accounts') {
    csrfCheck();
    $added  = contactsMigrateFromMembers($member['email']);
    $linked = contactsLinkMembers();
    $msg = $added
        ? "Added {$added} " . ($added === 1 ? 'person' : 'people') . " who had a login but no contact record."
        : 'Every login account already had a contact record.';
    if ($linked) $msg .= " Linked {$linked} to their account.";
    header('Location: contacts.php?notice=' . urlencode($msg));
    exit;
}

$filters = [
    'q'                => trim($_GET['q'] ?? ''),
    'school_id'        => $_GET['school_id'] ?? '',
    'role'             => $_GET['role'] ?? '',
    'status'           => $_GET['status'] ?? '',
    'mc'               => $_GET['mc'] ?? '',
    'include_archived' => !empty($_GET['include_archived']),
    'sort'             => $_GET['sort'] ?? 'name',
    'dir'              => $_GET['dir'] ?? 'asc',
];
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

[$rows, $total] = contactSearch($filters, $page, $perPage);
$pages   = max(1, (int)ceil($total / $perPage));
$counts  = contactCounts();
$gap     = contactsAccountGap();
$schools = contactSchools();
$roles   = contactDistinctRoles();

$schoolName = [];
foreach ($schools as $s) $schoolName[(int)$s['id']] = $s['name'];

/** Keep current filters when building sort links and pagination. */
function qs(array $over = []): string {
    $base = array_intersect_key($_GET, array_flip(
        ['q','school_id','role','status','mc','include_archived','sort','dir','page']));
    return http_build_query(array_merge($base, $over));
}
function sortLink(string $key, string $label, array $f): string {
    $dir = ($f['sort'] === $key && $f['dir'] === 'asc') ? 'desc' : 'asc';
    $arr = $f['sort'] === $key ? ($f['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="?' . htmlspecialchars(qs(['sort' => $key, 'dir' => $dir, 'page' => 1]))
         . '">' . htmlspecialchars($label) . $arr . '</a>';
}
function mcBadge(string $s): string {
    $map = [
        'subscribed'    => ['#f0fdf4', '#166534'],
        'unsubscribed'  => ['#f1f5f9', '#475569'],
        'pending'       => ['#fffbeb', '#b45309'],
        'cleaned'       => ['#fef2f2', '#991b1b'],
        'transactional' => ['#eff6ff', '#1e40af'],
        'unknown'       => ['#f8fafc', '#94a3b8'],
    ];
    $c = $map[$s] ?? $map['unknown'];
    $label = MC_STATUSES[$s] ?? $s;
    return '<span class="badge" style="background:' . $c[0] . ';color:' . $c[1] . ';">'
         . htmlspecialchars($label) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Contacts — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:1180px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .page-header { display:flex;justify-content:space-between;align-items:flex-end;
                   gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem; }
    .page-header h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 0; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    .tools { display:flex;gap:.45rem;flex-wrap:wrap; }
    .notice { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#166534;margin-bottom:1rem; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#991b1b;margin-bottom:1rem; }
    .warn-box { background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#92400e;margin-bottom:1rem; }

    .filters { background:#fff;border:1px solid var(--gray-200);border-radius:10px;
               padding:.85rem 1rem;margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center; }
    .filters input[type=text], .filters select {
        border:1px solid var(--gray-300);border-radius:7px;padding:.4rem .6rem;
        font-size:.86rem;font-family:inherit; }
    .filters input[type=text] { min-width:200px; }
    .filters label { font-size:.82rem;color:var(--gray-500);display:flex;align-items:center;gap:.3rem; }

    table { width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--gray-200);
            border-radius:10px;overflow:hidden;font-size:.85rem; }
    thead tr { background:#1a2e1a; }
    th { padding:.55rem .8rem;text-align:left;font-size:.7rem;font-weight:700;
         text-transform:uppercase;letter-spacing:.05em;color:#fff;white-space:nowrap; }
    th a { color:#fff;text-decoration:none; }
    th a:hover { text-decoration:underline; }
    td { padding:.5rem .8rem;border-bottom:1px solid var(--gray-100);vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr.arch td { opacity:.55; }
    .nm { font-weight:700;color:var(--gray-800); }
    .em { font-size:.8rem;color:var(--gray-500); }
    .badge { display:inline-block;font-size:.66rem;font-weight:800;text-transform:uppercase;
             letter-spacing:.03em;padding:.12rem .45rem;border-radius:100px;white-space:nowrap; }
    .syncerr { display:block;font-size:.7rem;color:#b45309;margin-top:.15rem; }
    .act-btn { background:none;border:1px solid var(--gray-200);border-radius:6px;
               padding:.2rem .5rem;font-size:.74rem;cursor:pointer;color:var(--gray-600);
               text-decoration:none;display:inline-block; }
    .act-btn:hover { background:var(--accent);border-color:var(--primary);color:var(--primary); }
    .pager { display:flex;gap:.4rem;align-items:center;justify-content:center;margin-top:1rem;
             font-size:.85rem;flex-wrap:wrap; }
    .pager a, .pager span { padding:.3rem .6rem;border-radius:6px;text-decoration:none;
                            border:1px solid var(--gray-200);color:var(--gray-600); }
    .pager .cur { background:var(--primary);color:#fff;border-color:var(--primary); }
    .count { font-size:.82rem;color:var(--gray-500);margin-bottom:.5rem; }
    .empty { font-size:.9rem;color:var(--gray-400);font-style:italic;padding:2rem;text-align:center; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1>Contacts</h1>
    </div>
    <div class="tools">
      <a class="act-btn" href="contact-edit.php">+ Add contact</a>
      <a class="act-btn" href="contacts-import.php">Import CSV</a>
      <a class="act-btn" href="contacts-export.php?<?= htmlspecialchars(qs(['format'=>'csv'])) ?>">Export CSV</a>
      <a class="act-btn" href="contacts-export.php?<?= htmlspecialchars(qs(['format'=>'xlsx'])) ?>">Export Excel</a>
      <a class="act-btn" href="contacts-sync.php">Mailchimp</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <?php if (!empty($gap['error'])): ?>
  <div class="error-box">
    Could not compare the contact list with login accounts:
    <?= htmlspecialchars($gap['error']) ?>
  </div>
  <?php elseif ($gap['accounts_nocontact'] > 0): ?>
  <div class="warn-box">
    <strong><?= (int)$gap['accounts_nocontact'] ?></strong>
    member<?= $gap['accounts_nocontact'] === 1 ? ' has' : 's have' ?> a login but no contact record,
    so they are missing from this list, from exports and from Mailchimp.
    <form method="POST" style="display:inline;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reconcile_accounts">
      <button class="act-btn" style="border-color:#92400e;color:#92400e;font-weight:700;">
        Add them &rarr;</button>
    </form>
    <span style="display:block;font-size:.8rem;color:#92400e;margin-top:.4rem;">
      Uses the name and email on the account. Nobody is subscribed to marketing email
      &mdash; new contacts reach Mailchimp as transactional only.
    </span>
  </div>
  <?php endif; ?>

  <?php if ($counts['errors'] > 0): ?>
  <div class="warn-box">
    <?= (int)$counts['errors'] ?> contact<?= $counts['errors'] === 1 ? '' : 's' ?>
    failed to sync with Mailchimp.
    <a href="contacts-sync.php" style="color:#92400e;font-weight:700;">Review and retry &rarr;</a>
  </div>
  <?php endif; ?>

  <?php if ($counts['total'] === 0): ?>
  <div class="filters" style="display:block;">
    <strong style="font-size:.9rem;color:var(--gray-800);">Start from the membership roster</strong>
    <p style="font-size:.85rem;color:var(--gray-500);margin:.3rem 0 .6rem;">
      The people already imported for invitations can be brought in as contacts.
      Anyone already here is skipped, so this is safe to run more than once.
    </p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="import_roster">
      <button class="btn btn-primary" style="padding:.45rem 1rem;font-size:.88rem;">Bring roster across</button>
    </form>
  </div>
  <?php endif; ?>

  <form class="filters" method="GET">
    <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Name or email&hellip;">
    <select name="school_id">
      <option value="">All schools</option>
      <?php foreach ($schools as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= (string)$filters['school_id'] === (string)$s['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="role">
      <option value="">All roles</option>
      <?php foreach ($roles as $r): ?>
      <option value="<?= htmlspecialchars($r) ?>" <?= $filters['role'] === $r ? 'selected' : '' ?>>
        <?= htmlspecialchars($r) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach (CONTACT_STATUSES as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $filters['status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <select name="mc">
      <option value="">Any Mailchimp state</option>
      <?php foreach (MC_STATUSES as $k => $lbl): ?>
      <option value="<?= $k ?>" <?= $filters['mc'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
      <?php endforeach; ?>
    </select>
    <label><input type="checkbox" name="include_archived" value="1"
                  <?= $filters['include_archived'] ? 'checked' : '' ?>> Show archived</label>
    <button class="btn btn-primary" style="padding:.4rem .9rem;font-size:.85rem;">Search</button>
    <a class="act-btn" href="contacts.php">Reset</a>
  </form>

  <div class="count">
    Showing <?= count($rows) ?> of <?= (int)$total ?> matching
    &middot; <?= (int)$counts['total'] ?> active contacts<?php
      if ($counts['archived']): ?>, <?= (int)$counts['archived'] ?> archived<?php endif; ?>
    &middot; <?= (int)$gap['contacts_withaccount'] ?> of <?= (int)$gap['accounts'] ?> login accounts linked<?php
      if (empty($gap['error']) && $gap['accounts_nocontact'] === 0 && $gap['accounts'] > 0): ?>
      &middot; every account has a contact record<?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:10px;">
      <p class="empty">No contacts match those filters.</p>
    </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th><?= sortLink('name','Name',$filters) ?></th>
        <th><?= sortLink('email','Email',$filters) ?></th>
        <th><?= sortLink('school','School',$filters) ?></th>
        <th><?= sortLink('role','Role',$filters) ?></th>
        <th><?= sortLink('status','Status',$filters) ?></th>
        <th><?= sortLink('mc','Mailchimp',$filters) ?></th>
        <th><?= sortLink('updated','Updated',$filters) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $c): ?>
      <tr class="<?= $c['status'] === 'archived' ? 'arch' : '' ?>">
        <td><span class="nm"><?= htmlspecialchars(contactDisplayName($c)) ?></span></td>
        <td class="em"><?= htmlspecialchars($c['email']) ?></td>
        <td><?= htmlspecialchars($c['school_id']
                  ? ($schoolName[(int)$c['school_id']] ?? '') : $c['school_other']) ?></td>
        <td><?= htmlspecialchars($c['role']) ?></td>
        <td><?= htmlspecialchars(CONTACT_STATUSES[$c['status']] ?? $c['status']) ?></td>
        <td>
          <?= mcBadge($c['mailchimp_status']) ?>
          <?php if ($c['mailchimp_sync_status'] === 'error'): ?>
            <span class="syncerr">&#9888; sync failed</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--gray-400);white-space:nowrap;">
          <?= $c['updated_at'] ? date('M j, Y', strtotime($c['updated_at'])) : '' ?>
        </td>
        <td style="white-space:nowrap;">
          <a class="act-btn" href="contact-edit.php?id=<?= (int)$c['id'] ?>">Edit</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($page > 1): ?>
      <a href="?<?= htmlspecialchars(qs(['page' => $page - 1])) ?>">&larr; Prev</a>
    <?php endif; ?>
    <span class="cur">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
      <a href="?<?= htmlspecialchars(qs(['page' => $page + 1])) ?>">Next &rarr;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>
</body>
</html>
