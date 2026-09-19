<?php
/**
 * people.php — one list of everyone: contact record, portal account, invite,
 * and Mailchimp status on the same row.
 *
 * Replaces the split between Contacts and Member Management. A person is one
 * record here; having a login is something a person may or may not have, which
 * is why retirees and outside subscribers can sit in the same list without
 * being given accounts.
 *
 * Account and invite actions POST to member-manage.php, which holds the single
 * implementation of each, and come back here via its whitelisted redirect. No
 * handler is duplicated — that duplication is what this page exists to end.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/contacts-db.php';
require_once __DIR__ . '/people-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

sendPrivateHeaders();
contactsEnsureTables();

$notice = htmlspecialchars($_GET['notice'] ?? '');
$error  = htmlspecialchars($_GET['error']  ?? '');

// Contact-level actions. Everything to do with accounts or invites is posted
// to member-manage.php instead, so there is only ever one copy of that logic.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $act = $_POST['action'] ?? '';
    if ($act === 'reconcile_accounts') {
        $added  = contactsMigrateFromMembers($member['email']);
        $link = contactsLinkMembers();
        $msg = $added
            ? "Added {$added} " . ($added === 1 ? 'person' : 'people') . " who had a login but no contact record."
            : 'Every login account already had a contact record.';
        if ($link['linked'])  $msg .= " Linked {$link['linked']} to their account.";
        if ($link['cleared']) $msg .= " Cleared {$link['cleared']} link" .
                                      ($link['cleared'] === 1 ? '' : 's') .
                                      " whose addresses no longer match.";
        header('Location: people.php?notice=' . urlencode($msg));
        exit;
    }
    // Archive and restore, one row or many. Archiving hides someone from the
    // list, exports and sync without deleting anything — the record and its
    // audit trail stay, and "Show archived" brings them back into view.
    if ($act === 'archive' || $act === 'restore') {
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        if (!$ids && !empty($_POST['id'])) $ids = [(int)$_POST['id']];
        $n = 0;
        $stillActive = 0;
        foreach ($ids as $cid) {
            $c = contactGet($cid);
            if (!$c) continue;
            contactArchive($cid, $member['email'], $act === 'archive');
            $n++;
            // Archiving is about the contact list; it does not touch a login.
            // Say so rather than leaving someone to assume access was removed.
            if ($act === 'archive') {
                $acct = peopleAccountsByEmail()[$c['email_normalized']] ?? null;
                if ($acct && (int)$acct['active']) $stillActive++;
            }
        }
        $msg = $n . ($act === 'archive' ? ' archived.' : ' restored.');
        if ($stillActive) {
            $msg .= " {$stillActive} can still log in — deactivate the account separately if that is the intent.";
        }
        header('Location: people.php?notice=' . urlencode($msg));
        exit;
    }
    // Linking only. reconcile_accounts also CREATES a contact for every account
    // that lacks one, so reusing it here would turn "link 3 records" into
    // "create 40 people and push them to Mailchimp".
    if ($act === 'link_accounts') {
        $link  = contactsLinkMembers();
        $parts = [];
        if ($link['linked'])  $parts[] = "linked {$link['linked']} to their login account";
        if ($link['cleared']) $parts[] = "cleared {$link['cleared']} link" .
                                         ($link['cleared'] === 1 ? '' : 's') .
                                         ' whose addresses no longer match';
        header('Location: people.php?notice=' . urlencode(
            $parts ? ucfirst(implode(', ', $parts)) . '.' : 'Nothing left to link.'));
        exit;
    }
    if ($act === 'import_roster') {
        $n = contactsMigrateFromInvitations($member['email']);
        header('Location: people.php?notice=' . urlencode($n
            ? "Brought {$n} people across from the membership roster."
            : 'Nothing new to bring across.'));
        exit;
    }
    header('Location: people.php');
    exit;
}

$fAccount = in_array($_GET['account'] ?? '', ['yes','no'], true) ? $_GET['account'] : '';
$fState   = isset(PEOPLE_INVITE_LABELS[$_GET['state'] ?? '']) ? $_GET['state'] : '';

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

// Account and invite filters are resolved to a set of addresses in PHP, then
// handed to the query — see people-db.php for why they are not a SQL join.
$whitelist = peopleFilterEmails($fAccount, $fState);
if ($whitelist !== null) $filters['email_whitelist'] = $whitelist;

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

list($rows, $total) = contactSearch($filters, $page, $perPage);
$rows    = peopleDecorate($rows);
$pages   = max(1, (int)ceil($total / $perPage));
$counts  = contactCounts();
$gap     = contactsAccountGap();
$pc      = peopleCounts();
$schools = contactSchools();
$roles   = contactDistinctRoles();

$schoolName = [];
foreach ($schools as $s) $schoolName[(int)$s['id']] = $s['name'];

function qs(array $over = []): string {
    $base = array_intersect_key($_GET, array_flip(
        ['q','school_id','role','status','mc','account','state','include_archived','sort','dir','page']));
    return http_build_query(array_merge($base, $over));
}
function sortLink(string $key, string $label, array $f): string {
    $dir = (($f['sort'] ?? '') === $key && ($f['dir'] ?? '') === 'asc') ? 'desc' : 'asc';
    $arr = ($f['sort'] ?? '') === $key ? (($f['dir'] ?? '') === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="?' . htmlspecialchars(qs(['sort' => $key, 'dir' => $dir, 'page' => 1]))
         . '">' . htmlspecialchars($label) . $arr . '</a>';
}
function mcBadge(array $c): string {
    // Rendered from contactMcLabel() so the badge can distinguish "we have
    // never checked" from "we checked and they are not there". The hover text
    // carries the date, because a status is only as good as its last check.
    list($label, $tone, $detail) = contactMcLabel($c);
    $tones = [
        'green' => ['#f0fdf4', '#166534'],
        'slate' => ['#f1f5f9', '#475569'],
        'amber' => ['#fffbeb', '#b45309'],
        'red'   => ['#fef2f2', '#991b1b'],
        'blue'  => ['#eff6ff', '#1e40af'],
        'grey'  => ['#f8fafc', '#94a3b8'],
    ];
    $col = $tones[$tone] ?? $tones['grey'];
    return '<span class="badge" title="' . htmlspecialchars($detail) . '" style="background:'
         . $col[0] . ';color:' . $col[1] . ';">' . htmlspecialchars($label) . '</span>';
}

function stateBadge(string $s): string {
    $map = [
        'registered' => ['#f0fdf4', '#166534'],
        'accepted'   => ['#f0fdf4', '#166534'],
        'sent'       => ['#eff6ff', '#1e40af'],
        'expired'    => ['#fef2f2', '#991b1b'],
        'listed'     => ['#fffbeb', '#b45309'],
        'none'       => ['#f8fafc', '#94a3b8'],
    ];
    $c = $map[$s] ?? $map['none'];
    return '<span class="badge" style="background:' . $c[0] . ';color:' . $c[1] . ';">'
         . htmlspecialchars(PEOPLE_INVITE_LABELS[$s] ?? $s) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>People — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:1240px; margin:0 auto; padding:2rem 1.5rem 4rem; }
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

    .chips { display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.9rem; }
    .chip { font-size:.8rem;padding:.3rem .7rem;border-radius:100px;text-decoration:none;
            border:1px solid var(--gray-200);background:#fff;color:var(--gray-600); }
    .chip.on { background:var(--primary);border-color:var(--primary);color:#fff;font-weight:700; }
    .chip b { font-weight:700; }

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
    td { padding:.5rem .8rem;border-bottom:1px solid var(--gray-100);vertical-align:top; }
    tr.arch td { opacity:.55; }
    .nm { font-weight:700;color:var(--gray-800); }
    a.nm:hover { color:var(--primary);text-decoration:underline !important; }
    .em { color:var(--gray-500);font-size:.82rem; }
    .badge { display:inline-block;padding:.12rem .5rem;border-radius:100px;
             font-size:.72rem;font-weight:700;white-space:nowrap; }
    .syncerr { color:#b45309;font-size:.75rem;display:block;margin-top:.15rem; }
    .act-btn { background:none;border:1px solid var(--gray-200);border-radius:6px;padding:.25rem .6rem;
               font-size:.78rem;cursor:pointer;color:var(--gray-600);text-decoration:none;
               display:inline-block;font-family:inherit; }
    .act-btn:hover { border-color:var(--primary);color:var(--primary); }
    .act-btn.warn { color:#991b1b;border-color:#fecaca; }
    .rowacts { display:flex;gap:.3rem;flex-wrap:wrap;justify-content:flex-end; }
    .count { font-size:.82rem;color:var(--gray-500);margin:.8rem 0; }
    .empty { font-size:.88rem;color:var(--gray-400);font-style:italic;padding:1.2rem;text-align:center;margin:0; }
    details.panel { background:#fff;border:1px solid var(--gray-200);border-radius:10px;
                    padding:.75rem 1rem;margin-bottom:1rem; }
    details.panel summary { font-size:.88rem;font-weight:700;color:var(--gray-700);cursor:pointer; }
    details.panel .body { padding-top:.8rem; }
    .fgrid { display:flex;gap:.5rem;flex-wrap:wrap;align-items:flex-end; }
    .fgrid input, .fgrid select { border:1px solid var(--gray-300);border-radius:7px;
                                  padding:.4rem .6rem;font-size:.86rem;font-family:inherit; }
    .pager { display:flex;gap:.35rem;justify-content:center;margin-top:1.2rem;flex-wrap:wrap; }
    .pager a, .pager span { font-size:.82rem;padding:.3rem .6rem;border-radius:6px;
                            border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-600); }
    .pager .cur { background:var(--primary);color:#fff;border-color:var(--primary);font-weight:700; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1>People</h1>
    </div>
    <div class="tools">
      <a class="act-btn" href="contact-edit.php">+ Add person</a>
      <a class="act-btn" href="contacts-import.php">Import CSV</a>
      <a class="act-btn" href="contacts-export.php?<?= htmlspecialchars(qs(['format'=>'csv'])) ?>">Export CSV</a>
      <a class="act-btn" href="contacts-export.php?<?= htmlspecialchars(qs(['format'=>'xlsx'])) ?>">Export Excel</a>
      <a class="act-btn" href="contacts-sync.php">Mailchimp</a>
      <a class="act-btn" href="contacts-verify.php">Verify</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <?php if (!empty($gap['error'])): ?>
  <div class="error-box">
    Could not compare the people list with login accounts:
    <?= htmlspecialchars($gap['error']) ?>
  </div>
  <?php elseif ($gap['accounts_nocontact'] > 0): ?>
  <div class="warn-box">
    <strong><?= (int)$gap['accounts_nocontact'] ?></strong>
    member<?= $gap['accounts_nocontact'] === 1 ? ' has' : 's have' ?> a login but no record here,
    so they are missing from this list, from exports and from Mailchimp.
    <form method="POST" style="display:inline;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="reconcile_accounts">
      <button class="act-btn" style="border-color:#92400e;color:#92400e;font-weight:700;">Add them &rarr;</button>
    </form>
    <span style="display:block;font-size:.8rem;color:#92400e;margin-top:.4rem;">
      Uses the name and email on the account. Nobody is subscribed to marketing email
      &mdash; new records reach Mailchimp as transactional only.
    </span>
  </div>
  <?php endif; ?>

  <?php if ($counts['errors'] > 0): ?>
  <div class="warn-box">
    <?= (int)$counts['errors'] ?> record<?= $counts['errors'] === 1 ? '' : 's' ?>
    failed to sync with Mailchimp.
    <a href="contacts-sync.php" style="color:#92400e;font-weight:700;">Review and retry &rarr;</a>
  </div>
  <?php endif; ?>

  <div class="chips">
    <a class="chip <?= ($fAccount === '' && $fState === '') ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'','page'=>1])) ?>">
       Everyone <b><?= (int)$pc['people'] ?></b></a>
    <a class="chip <?= $fAccount === 'yes' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'yes','state'=>'','page'=>1])) ?>">
       Has a login <b><?= (int)$pc['with_account'] ?></b></a>
    <a class="chip <?= $fState === 'sent' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'sent','page'=>1])) ?>">
       Invited, not registered <b><?= (int)$pc['sent'] ?></b></a>
    <a class="chip <?= $fState === 'listed' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'listed','page'=>1])) ?>">
       Not yet emailed <b><?= (int)$pc['listed'] ?></b></a>
    <a class="chip <?= $fState === 'expired' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'expired','page'=>1])) ?>">
       Link expired <b><?= (int)$pc['expired'] ?></b></a>
    <a class="chip <?= $fState === 'none' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'none','page'=>1])) ?>">
       Never invited <b><?= (int)$pc['none'] ?></b></a>
    <?php if ($counts['archived']): ?>
    <a class="chip <?= $filters['status'] === 'archived' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'','status'=>'archived',
                                       'include_archived'=>1,'page'=>1])) ?>">
       Archived <b><?= (int)$counts['archived'] ?></b></a>
    <?php endif; ?>
  </div>

  <form class="filters" method="GET">
    <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Name or email&hellip;">
    <input type="hidden" name="account" value="<?= htmlspecialchars($fAccount) ?>">
    <input type="hidden" name="state"   value="<?= htmlspecialchars($fState) ?>">
    <input type="hidden" name="status"  value="<?= htmlspecialchars($filters['status']) ?>">
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
    <select name="mc">
      <option value="">Any Mailchimp status</option>
      <?php foreach (MC_STATUSES as $k => $lbl): ?>
      <option value="<?= htmlspecialchars($k) ?>" <?= $filters['mc'] === $k ? 'selected' : '' ?>>
        <?= htmlspecialchars($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <label><input type="checkbox" name="include_archived" value="1"
                  <?= $filters['include_archived'] ? 'checked' : '' ?>> Show archived</label>
    <button class="btn btn-primary" style="padding:.4rem .9rem;font-size:.85rem;">Search</button>
    <a class="act-btn" href="people.php">Reset</a>
  </form>

  <details class="panel">
    <summary>Invite people to the portal</summary>
    <div class="body">
      <p style="font-size:.85rem;color:var(--gray-600);line-height:1.7;margin:0 0 .7rem;">
        Adding people to the list never emails anyone. Sending is always a separate,
        deliberate step, so uploading a roster cannot blast the membership.
      </p>
      <form method="POST" action="member-manage.php" enctype="multipart/form-data" style="margin-bottom:.9rem;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="send_invites">
        <input type="hidden" name="redirect" value="people.php">
        <div class="fgrid">
          <input type="file" name="csv_file" accept=".csv,.xlsx">
          <button class="act-btn">Add to list</button>
        </div>
        <textarea name="invite_list" rows="2" placeholder="Or paste: Jane Smith, jane@example.com"
                  style="width:100%;margin-top:.5rem;border:1px solid var(--gray-300);border-radius:7px;
                         padding:.45rem .6rem;font-size:.86rem;font-family:inherit;"></textarea>
      </form>
      <form method="POST" action="member-manage.php"
            onsubmit="return confirm('Email a registration link to everyone on the list who has never been sent one?');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="send_all_invites">
        <input type="hidden" name="redirect" value="people.php">
        <button class="act-btn">Send links to everyone not yet emailed (<?= (int)$pc['listed'] ?>)</button>
      </form>
    </div>
  </details>

  <details class="panel">
    <summary>Create a login account directly</summary>
    <div class="body">
      <p style="font-size:.85rem;color:var(--gray-600);line-height:1.7;margin:0 0 .7rem;">
        For someone who cannot use the invite link. They are prompted to choose a new
        password on first login, and a record is created here automatically.
      </p>
      <form method="POST" action="member-manage.php" class="fgrid">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="redirect" value="people.php">
        <input type="text" name="name" placeholder="Full name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="employee_number" placeholder="Employee number" required>
        <input type="password" name="password" placeholder="Temporary password" minlength="8" required>
        <button class="act-btn">Create account</button>
      </form>
    </div>
  </details>

  <div class="count">
    Showing <?= count($rows) ?> of <?= (int)$total ?> matching
    &middot; <?= (int)$counts['total'] ?> people<?php
      if ($counts['archived']): ?>, <?= (int)$counts['archived'] ?> archived<?php endif; ?>
    <?php if (empty($gap['error']) && $gap['accounts'] > 0): ?>
      &middot; <?= (int)$gap['contacts_withaccount'] ?> of <?= (int)$gap['accounts'] ?> login accounts linked
      <?php if (!empty($gap['unlinked'])): ?>
        <?php // Repairs the link in both directions: adds a missing one, and
              // clears one whose addresses no longer match. member_id is
              // derived from the address, so a link that disagrees with it is
              // wrong by definition. Otherwise this only ran from the reconcile
              // banner, which appears only when someone has no record at all. ?>
        <form method="POST" style="display:inline;">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="link_accounts">
          <button class="act-btn" style="padding:.1rem .45rem;font-size:.74rem;">Fix links</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:10px;">
      <p class="empty">Nobody matches those filters.</p>
    </div>
  <?php else: ?>
  <div id="bulkbar" style="display:none;background:#fff;border:1px solid var(--gray-200);
       border-radius:10px;padding:.6rem .9rem;margin-bottom:.6rem;
       align-items:center;gap:.6rem;flex-wrap:wrap;">
    <span id="bulkcount" style="font-size:.85rem;font-weight:700;color:var(--gray-700);"></span>
    <form method="POST" style="display:inline;" onsubmit="return bulkGo(this,'archive');">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="archive">
      <button class="act-btn warn">Archive selected</button>
    </form>
    <form method="POST" style="display:inline;" onsubmit="return bulkGo(this,'restore');">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="restore">
      <button class="act-btn">Restore selected</button>
    </form>
    <span style="font-size:.78rem;color:var(--gray-400);">
      Archiving hides someone from this list, exports and Mailchimp sync. Nothing is deleted.
    </span>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:2rem;"><input type="checkbox" onclick="pAll(this)"></th>
        <th><?= sortLink('name','Name',$filters) ?>
            <span style="font-weight:400;text-transform:none;letter-spacing:0;opacity:.75;">
              / <?= sortLink('surname','surname',$filters) ?></span></th>
        <th><?= sortLink('email','Email',$filters) ?></th>
        <th><?= sortLink('school','School',$filters) ?></th>
        <th>Portal</th>
        <th><?= sortLink('mc','Mailchimp',$filters) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $c): ?>
      <?php
        $acct  = $c['_account'];
        $inv   = $c['_invite'];
        $state = $c['_state'];
        $isSelf = $acct && strtolower($acct['email']) === strtolower($member['email']);
      ?>
      <tr class="<?= $c['status'] === 'archived' ? 'arch' : '' ?>">
        <td><input type="checkbox" class="ppick" value="<?= (int)$c['id'] ?>" onclick="pCount()"></td>
        <td>
          <a class="nm" href="person.php?id=<?= (int)$c['id'] ?>"
             style="text-decoration:none;"><?= htmlspecialchars(contactDisplayName($c)) ?></a>
          <?php if ($acct && !(int)$acct['active']): ?>
            <span class="badge" style="background:#fef2f2;color:#991b1b;">Deactivated</span>
          <?php endif; ?>
          <?php if ($c['role']): ?>
            <span style="display:block;font-size:.76rem;color:var(--gray-400);">
              <?= htmlspecialchars($c['role']) ?></span>
          <?php endif; ?>
        </td>
        <td class="em"><?= htmlspecialchars($c['email']) ?></td>
        <td><?= htmlspecialchars($c['school_id']
                  ? ($schoolName[(int)$c['school_id']] ?? '') : $c['school_other']) ?></td>
        <td>
          <?= stateBadge($state) ?>
          <?php if ($state === 'sent' && !empty($inv['sent_at'])): ?>
            <span style="display:block;font-size:.74rem;color:var(--gray-400);">
              sent <?= date('M j', strtotime($inv['sent_at'])) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <?php list($mcL) = contactMcLabel($c); ?>
          <?= mcBadge($c) ?>
          <?php // Only when the badge is saying something else, or the cell
                // would read "Sync failed" twice. ?>
          <?php if ($c['mailchimp_sync_status'] === 'error' && $mcL !== 'Sync failed'): ?>
            <span class="syncerr">&#9888; sync failed</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="rowacts">
            <a class="act-btn" href="contact-edit.php?id=<?= (int)$c['id'] ?>">Edit</a>

            <?php if ($c['status'] === 'archived'): ?>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="act-btn">Restore</button>
              </form>
            <?php else: ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Archive <?= htmlspecialchars(addslashes(contactDisplayName($c))) ?>? They stay in the database and can be restored.');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="archive">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="act-btn warn">Archive</button>
              </form>
            <?php endif; ?>

            <?php if ($inv && in_array($state, ['listed','sent','expired'], true)): ?>
              <form method="POST" action="member-manage.php" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="resend_invite">
                <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                <input type="hidden" name="redirect" value="people.php">
                <button class="act-btn"><?= $state === 'listed' ? 'Send link' : 'Re-send link' ?></button>
              </form>
            <?php elseif ($state === 'none'): ?>
              <form method="POST" action="member-manage.php" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="send_invites">
                <input type="hidden" name="redirect" value="people.php">
                <input type="hidden" name="invite_list"
                       value="<?= htmlspecialchars(contactDisplayName($c) . ', ' . $c['email']) ?>">
                <button class="act-btn">Add to invite list</button>
              </form>
            <?php endif; ?>

            <?php if ($acct && !$isSelf): ?>
              <form method="POST" action="member-manage.php" style="display:inline;"
                    onsubmit="return confirm('<?= (int)$acct['active'] ? 'Deactivate' : 'Reactivate' ?> this login?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="member_id" value="<?= (int)$acct['id'] ?>">
                <input type="hidden" name="set_active" value="<?= (int)$acct['active'] ? 0 : 1 ?>">
                <input type="hidden" name="redirect" value="people.php">
                <button class="act-btn <?= (int)$acct['active'] ? 'warn' : '' ?>">
                  <?= (int)$acct['active'] ? 'Deactivate' : 'Reactivate' ?></button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
      <?php if ($p === $page): ?>
        <span class="cur"><?= $p ?></span>
      <?php else: ?>
        <a href="?<?= htmlspecialchars(qs(['page' => $p])) ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <script>
  function pBoxes() { return document.querySelectorAll('.ppick'); }
  function pChecked() { return document.querySelectorAll('.ppick:checked'); }

  function pAll(box) {
    var l = pBoxes();
    for (var i = 0; i < l.length; i++) l[i].checked = box.checked;
    pCount();
  }

  function pCount() {
    var n = pChecked().length;
    var bar = document.getElementById('bulkbar');
    bar.style.display = n ? 'flex' : 'none';
    document.getElementById('bulkcount').textContent =
      n + (n === 1 ? ' person selected' : ' people selected');
  }

  // Checkboxes live outside these forms, because each row already contains its
  // own forms and forms cannot nest. Collect the ids at submit instead.
  function bulkGo(form, what) {
    var picked = pChecked();
    if (!picked.length) return false;
    if (what === 'archive' &&
        !confirm('Archive ' + picked.length + ' ' + (picked.length === 1 ? 'person' : 'people') +
                 '? They stay in the database and can be restored.')) return false;

    for (var i = 0; i < picked.length; i++) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.name = 'ids[]'; h.value = picked[i].value;
      form.appendChild(h);
    }
    return true;
  }
  </script>

  <p style="font-size:.8rem;color:var(--gray-400);margin-top:2rem;text-align:center;">
    Account and invite actions are handled by the original
    <a href="member-manage.php" style="color:var(--gray-500);">Member Management</a> page,
    kept during the changeover.
  </p>

</div>
</body>
</html>
