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

$notice = htmlspecialchars(reqStr('notice'));
$error  = htmlspecialchars(reqStr('error'));

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
        $back = peopleBackParams((string)($_POST['back'] ?? ''));
        $back['notice'] = $msg;
        header('Location: people.php?' . http_build_query($back));
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
        // Keep their filters and sort: the row menu is now the only place these
        // live, and being thrown back to the unfiltered list after archiving one
        // person means finding your place again every time.
        $back = peopleBackParams((string)($_POST['back'] ?? ''));
        $back['notice'] = $msg;
        header('Location: people.php?' . http_build_query($back));
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
        $back = peopleBackParams((string)($_POST['back'] ?? ''));
        $back['notice'] = $parts ? ucfirst(implode(', ', $parts)) . '.' : 'Nothing left to link.';
        header('Location: people.php?' . http_build_query($back));
        exit;
    }
    if ($act === 'import_roster') {
        $n = contactsMigrateFromInvitations($member['email']);
        $back = peopleBackParams((string)($_POST['back'] ?? ''));
        $back['notice'] = $n
            ? "Brought {$n} people across from the membership roster."
            : 'Nothing new to bring across.';
        header('Location: people.php?' . http_build_query($back));
        exit;
    }

    if ($act === 'save_view') {
        // The view stores the filters; the redirect returns to the screen,
        // page included. They are not the same thing.
        $params = peopleViewParams((string)($_POST['view_query'] ?? ''));
        $err    = peopleViewSave($member['email'], (string)($_POST['view_name'] ?? ''), $params);
        $params = peopleBackParams((string)($_POST['back'] ?? ''));
        $params[$err ? 'error' : 'notice'] = $err ?: 'View saved.';
        header('Location: people.php?' . http_build_query($params));
        exit;
    }
    if ($act === 'delete_view') {
        $gone = peopleViewDelete((int)($_POST['view_id'] ?? 0), $member['email']);
        // Keep the list the admin was looking at; deleting a saved view is not
        // a reason to throw away their current filters and sort.
        $back = peopleBackParams((string)($_POST['back'] ?? ''));
        $back[$gone ? 'notice' : 'error'] = $gone
            ? 'View deleted.'
            : 'That view could not be deleted — reload and try again.';
        header('Location: people.php?' . http_build_query($back));
        exit;
    }
    header('Location: people.php');
    exit;
}

$fAccount = in_array(reqStr('account'), ['yes','no'], true) ? reqStr('account') : '';
$fState   = isset(PEOPLE_INVITE_LABELS[reqStr('state')]) ? reqStr('state') : '';

$filters = [
    'q'                => reqStr('q'),
    'school_id'        => reqStr('school_id'),
    'role'             => reqStr('role'),
    'status'           => reqStr('status'),
    'mc'               => reqStr('mc'),
    'include_archived' => !empty($_GET['include_archived']),
    'sort'             => reqStr('sort') ?: 'name',
    'dir'              => reqStr('dir')  ?: 'asc',
];

// Account and invite filters are resolved to a set of addresses in PHP, then
// handed to the query — see people-db.php for why they are not a SQL join.
$whitelist = peopleFilterEmails($fAccount, $fState);
if ($whitelist !== null) $filters['email_whitelist'] = $whitelist;

// Filtering TO archived implies including them. Otherwise contactSearch()
// builds "status = 'archived' AND status <> 'archived'", the list is
// permanently empty, and the pill that would undo it is hidden in exactly
// that case — leaving Clear all as the only way out.
if ($filters['status'] === 'archived') $filters['include_archived'] = true;

// Normalised here so the arrow, the link and the query can never disagree, and
// so a bad value cannot be saved into a view.
$filters['dir'] = (strtolower($filters['dir']) === 'desc') ? 'desc' : 'asc';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

list($rows, $total) = contactSearch($filters, $page, $perPage);
$pages = max(1, (int)ceil($total / $perPage));

// Archiving the last row on the last page leaves the cursor past the end:
// no rows, and the pager that would get you back is inside the "we have rows"
// branch. Fall back to the last real page and fetch again.
if (!$rows && $page > $pages) {
    $page = $pages;
    list($rows, $total) = contactSearch($filters, $page, $perPage);
}
$rows    = peopleDecorate($rows);
$counts  = contactCounts();
$gap     = contactsAccountGap();
$pc      = peopleCounts();
$schools = contactSchools();
$roles   = contactDistinctRoles();

$schoolName = [];
foreach ($schools as $s) $schoolName[(int)$s['id']] = $s['name'];

$views = peopleViewsList($member['email']);

/**
 * Everything currently narrowing the list, as [key => readable]. Shown as
 * removable pills: with chips and a filter form both in play, there was no one
 * place that said what was being filtered, and Reset was the only way out.
 */
$active = [];
if ($filters['q'] !== '')        $active['q'] = 'Matching "' . $filters['q'] . '"';   // q honours "0"; see contactSearch()
if (!empty($filters['school_id'])) {
    // contactSchools() lists active schools only, so a saved view pinned to a
    // school since deactivated would show its raw id. Look the name up rather
    // than printing a number at the admin.
    $sid  = (int)$filters['school_id'];
    $sLbl = $schoolName[$sid] ?? null;
    if ($sLbl === null) {
        try {
            $q = getDB()->prepare("SELECT name FROM prod_schools WHERE id=?");
            $q->execute([$sid]);
            $sLbl = $q->fetchColumn() ?: null;
        } catch (Exception $e) { $sLbl = null; }
    }
    $active['school_id'] = 'School: ' . ($sLbl ?? ('#' . $sid));
}
if (!empty($filters['role']))     $active['role']   = 'Role: ' . $filters['role'];
if (!empty($filters['mc']))       $active['mc']     = 'Mailchimp: ' . (MC_STATUSES[$filters['mc']] ?? $filters['mc']);
if ($fAccount !== '')            $active['account'] = $fAccount === 'yes' ? 'Has a login' : 'No login';
if ($fState !== '')              $active['state']  = 'Portal: ' . (PEOPLE_INVITE_LABELS[$fState] ?? $fState);
if (!empty($filters['status']))  $active['status'] = 'Status: ' . (CONTACT_STATUSES[$filters['status']] ?? $filters['status']);
if ($filters['include_archived'] && $filters['status'] !== 'archived') {
    $active['include_archived'] = 'Including archived';
}

// Base for every sort link, pill and export link on the page.
$QS_BASE = array_filter([
    'q'                => $filters['q'],
    'school_id'        => $filters['school_id'],
    'role'             => $filters['role'],
    'status'           => $filters['status'],
    'mc'               => $filters['mc'],
    'account'          => $fAccount,
    'state'            => $fState,
    'include_archived' => $filters['include_archived'] ? '1' : '',
    'sort'             => $filters['sort'],
    'dir'              => $filters['dir'],
    'page'             => $page > 1 ? (string)$page : '',
], function ($v) { return $v !== ''; });

// Where a "back" field should return to: the view plus the page you were on.
$backQuery = http_build_query($QS_BASE);

// The query string that a saved view would capture — what is on screen now.
// Deliberately without `page`: a view is a filter, not a position in a list.
$currentQuery = http_build_query(array_filter([
    'q'                => $filters['q'],
    'school_id'        => $filters['school_id'],
    'role'             => $filters['role'],
    'status'           => $filters['status'],
    'mc'               => $filters['mc'],
    'account'          => $fAccount,
    'state'            => $fState,
    'include_archived' => $filters['include_archived'] ? '1' : '',
    'sort'             => $filters['sort'],
    'dir'              => $filters['dir'],
], function ($v) { return $v !== ''; }));

function qs(array $over = []): string {
    // From $QS_BASE (the sanitised filters), never from $_GET: these links feed
    // contacts-export.php, and an array-typed parameter passed straight through
    // would fatal there the moment someone hit ?q[]=x.
    global $QS_BASE;
    return http_build_query(array_merge($QS_BASE ?: [], $over));
}
function sortLink(string $key, string $label, array $f): string {
    // A date column opens newest-first; A-Z is the right opening for a name,
    // but "oldest activity first" is nobody's first question.
    $first = ($key === 'updated') ? 'desc' : 'asc';
    $dir = (($f['sort'] ?? '') === $key)
         ? ((($f['dir'] ?? '') === 'asc') ? 'desc' : 'asc')
         : $first;
    $arr = ($f['sort'] ?? '') === $key ? (($f['dir'] ?? '') === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="?' . htmlspecialchars(qs(['sort' => $key, 'dir' => $dir, 'page' => 1]))
         . '">' . htmlspecialchars($label) . $arr . '</a>';
}
/**
 * Colour marks a row that needs someone to act on THAT row. Everything else is
 * quiet muted text.
 *
 * The distinction is deliberate and narrower than "not the happy path". States
 * that legitimately apply to most of the list at once — Not checked after an
 * import, Not invited before a mailout, Registered, Subscribed — stay quiet
 * even though two of those are things to get round to. Colouring 164 rows amber
 * conveys nothing and undoes the point of the column. The chips above carry the
 * counts for those, and are the place to work through them in bulk.
 */
function toneSpan(string $label, string $tone, string $title = ''): string {
    $tones = [
        'green' => ['#f0fdf4', '#166534'],
        'amber' => ['#fffbeb', '#b45309'],
        'red'   => ['#fef2f2', '#991b1b'],
        'blue'  => ['#eff6ff', '#1e40af'],
        'slate' => ['#f1f5f9', '#475569'],
    ];
    $t = $title ? ' title="' . htmlspecialchars($title) . '"' : '';
    if (!isset($tones[$tone])) {
        // Ordinary state: plain muted text, no pill.
        return '<span class="plain"' . $t . '>' . htmlspecialchars($label) . '</span>';
    }
    $c = $tones[$tone];
    return '<span class="badge"' . $t . ' style="background:' . $c[0] . ';color:' . $c[1] . ';">'
         . htmlspecialchars($label) . '</span>';
}

function mcBadge(array $c, ?array $info = null): string {
    // The caller usually needs the label too; let it pass what it already has
    // rather than working it out twice per row.
    list($label, $tone, $detail) = $info ?: contactMcLabel($c);
    // contactMcLabel() already decided how serious this is; keep only the tones
    // that should shout. Subscribed and Transactional are the expected states
    // and stay quiet, so an exception is the only coloured thing in the column.
    // Unsubscribed and Cleaned are about one person and want noticing, so slate
    // and red are kept. Not checked (grey) stays quiet on purpose: straight
    // after an import that is every row, and the Mailchimp page reports it as a
    // total, which is the useful form.
    $loud = ['red' => 'red', 'amber' => 'amber', 'slate' => 'slate'];
    return toneSpan($label, $loud[$tone] ?? 'plain', $detail);
}

function stateBadge(string $s): string {
    $label = PEOPLE_INVITE_LABELS[$s] ?? $s;
    // Expired needs a new link, and Not yet emailed needs sending — both are
    // per-row jobs. Registered is the destination. Not invited stays quiet
    // because before a mailout it is most of the list, and the "Never invited"
    // chip is how you act on it.
    $tone = [
        'expired' => 'red',
        'listed'  => 'amber',
        'sent'    => 'blue',
    ][$s] ?? 'plain';
    return toneSpan($label, $tone);
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

    /* No overflow:hidden — it clipped the row action menu, which made the last
       row's actions unreachable entirely. The header cells round themselves. */
    table { width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--gray-200);
            border-radius:10px;font-size:.85rem; }
    thead tr th:first-child { border-top-left-radius:9px; }
    thead tr th:last-child  { border-top-right-radius:9px; }
    thead tr { background:#1a2e1a; }
    th { padding:.55rem .8rem;text-align:left;font-size:.7rem;font-weight:700;
         text-transform:uppercase;letter-spacing:.05em;color:#fff;white-space:nowrap; }
    th a { color:#fff;text-decoration:none; }
    td { padding:.5rem .8rem;border-bottom:1px solid var(--gray-100);vertical-align:top; }
    /* Muted with colour, not opacity. Any opacity below 1 creates a stacking
       context on the cell, which trapped the row's action menu inside it: on
       the Archived view every item, including Restore, hit-tested to the row
       below and did nothing. Same family as the overflow:hidden clipping. */
    tr.arch td { background:#fafbfa;color:var(--gray-400); }
    tr.arch .nm { color:var(--gray-500);font-weight:600; }
    tr.arch .em2, tr.arch .plain { color:var(--gray-400); }
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

    /* Ordinary state: readable, but not competing with the exceptions. */
    .plain { color:var(--gray-500);font-size:.8rem; }
    .em2 { display:block;font-size:.8rem;color:var(--gray-500);margin-top:.1rem; }

    /* One place that says what is narrowing the list, and undoes it. */
    .pills { display:flex;gap:.4rem;flex-wrap:wrap;align-items:center;margin:0 0 .9rem; }
    .pl-label { font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;
                color:var(--gray-500);font-weight:700; }
    .pill { display:inline-flex;align-items:center;gap:.35rem;font-size:.79rem;
            background:var(--accent);color:var(--primary);border-radius:100px;
            padding:.2rem .6rem;text-decoration:none;font-weight:600; }
    .pill:hover { background:#cfe9da; }
    .pill .x { font-size:.95rem;line-height:1;opacity:.6; }
    .pl-clear { font-size:.79rem;color:var(--gray-500);text-decoration:underline; }
    .saveview { display:flex;gap:.3rem;margin-left:auto; }
    .saveview input { border:1px solid var(--gray-300);border-radius:6px;
                      padding:.25rem .5rem;font-size:.78rem;font-family:inherit;min-width:170px; }

    details.morefilters > summary { font-size:.82rem;color:var(--gray-600);cursor:pointer;
                                    list-style:none;padding:.3rem .6rem;border:1px solid var(--gray-200);
                                    border-radius:7px;background:#fff; }
    details.morefilters > summary::-webkit-details-marker { display:none; }
    details.morefilters[open] > summary { border-color:var(--primary);color:var(--primary); }
    .mf-body { display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;padding:.7rem 0 .1rem; }
    .mf-body select { border:1px solid var(--gray-300);border-radius:7px;padding:.4rem .6rem;
                      font-size:.86rem;font-family:inherit; }
    .mf-body label { font-size:.82rem;color:var(--gray-500);display:flex;align-items:center;gap:.3rem; }
    .viewrow { display:flex;justify-content:space-between;align-items:center;gap:.6rem;
               padding:.3rem 0;border-bottom:1px solid var(--gray-100);font-size:.86rem; }
    .viewrow:last-child { border-bottom:none; }
    .viewrow a { color:var(--primary);font-weight:600;text-decoration:none; }

    /* Row overflow: one target per row, the rest a click away. */
    details.rowmenu { position:relative;display:inline-block; }
    details.rowmenu > summary { list-style:none;cursor:pointer;padding:.1rem .5rem;
                                border-radius:6px;color:var(--gray-500);font-weight:800;
                                letter-spacing:.06em;border:1px solid transparent; }
    details.rowmenu > summary::-webkit-details-marker { display:none; }
    details.rowmenu > summary:hover { background:var(--gray-100);color:var(--primary); }
    details.rowmenu[open] > summary { border-color:var(--gray-200);background:#fff; }
    .rowmenu .menu { position:absolute;right:0;top:100%;z-index:20;min-width:210px;
                     background:#fff;border:1px solid var(--gray-200);border-radius:9px;
                     box-shadow:0 8px 24px rgba(0,0,0,.12);padding:.3rem;text-align:left; }
    .rowmenu .menu a, .rowmenu .menu button {
        display:block;width:100%;text-align:left;background:none;border:none;
        padding:.4rem .6rem;font-size:.83rem;color:var(--gray-700);text-decoration:none;
        border-radius:6px;cursor:pointer;font-family:inherit; }
    .rowmenu .menu a:hover, .rowmenu .menu button:hover { background:var(--gray-100);color:var(--primary); }
    .rowmenu .menu button.warn { color:#991b1b; }
    .rowmenu .menu form { margin:0; }

    .panel .hint { font-size:.85rem;color:var(--gray-600);line-height:1.7;margin:0 0 .7rem; }
    .panel textarea { width:100%;margin-top:.5rem;border:1px solid var(--gray-300);
                      border-radius:7px;padding:.45rem .6rem;font-size:.86rem;font-family:inherit; }
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
          <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
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
    <?php // Highlighted only when nothing at all narrows the list — including
          // the search box and the More filters selects, since the count beside
          // it is the unfiltered total. ?>
    <a class="chip <?= (!$active && $fAccount === '' && $fState === '') ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'','status'=>'',
                                       'include_archived'=>'','q'=>'','school_id'=>'',
                                       'role'=>'','mc'=>'','page'=>1])) ?>">
       Everyone <b><?= (int)$pc['people'] ?></b></a>
    <a class="chip <?= $fAccount === 'yes' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'yes','state'=>'','status'=>'','include_archived'=>'','page'=>1])) ?>">
       Has a login <b><?= (int)$pc['with_account'] ?></b></a>
    <a class="chip <?= $fState === 'sent' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'sent','status'=>'','include_archived'=>'','page'=>1])) ?>">
       Invited, not registered <b><?= (int)$pc['sent'] ?></b></a>
    <a class="chip <?= $fState === 'listed' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'listed','status'=>'','include_archived'=>'','page'=>1])) ?>">
       Not yet emailed <b><?= (int)$pc['listed'] ?></b></a>
    <a class="chip <?= $fState === 'expired' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'expired','status'=>'','include_archived'=>'','page'=>1])) ?>">
       Link expired <b><?= (int)$pc['expired'] ?></b></a>
    <a class="chip <?= $fState === 'none' ? 'on' : '' ?>"
       href="?<?= htmlspecialchars(qs(['account'=>'','state'=>'none','status'=>'','include_archived'=>'','page'=>1])) ?>">
       Never invited <b><?= (int)$pc['none'] ?></b></a>
    <?php if ($counts['archived']): ?>
    <?php // Toggles: pressing it while already on the Archived view leaves it,
          // which is what a selected chip should do. ?>
    <?php $inArchived = ($filters['status'] === 'archived'); ?>
    <a class="chip <?= $inArchived ? 'on' : '' ?>"
       href="?<?= htmlspecialchars($inArchived
         ? qs(['status'=>'','include_archived'=>'','page'=>1])
         : qs(['account'=>'','state'=>'','status'=>'archived','include_archived'=>1,'page'=>1])) ?>">
       Archived <b><?= (int)$counts['archived'] ?></b></a>
    <?php endif; ?>
  </div>

  <form class="filters" method="GET" id="filterForm">
    <input type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>"
           placeholder="Search name or email&hellip;" style="flex:1;min-width:220px;">
    <input type="hidden" name="account" value="<?= htmlspecialchars($fAccount) ?>">
    <input type="hidden" name="state"   value="<?= htmlspecialchars($fState) ?>">
    <input type="hidden" name="status"  value="<?= htmlspecialchars($filters['status']) ?>">
    <?php // Carried through, or pressing Search would silently undo a sort the
          // saved-view bar has just told the admin is in effect. ?>
    <input type="hidden" name="sort" value="<?= htmlspecialchars($filters['sort']) ?>">
    <input type="hidden" name="dir"  value="<?= htmlspecialchars($filters['dir']) ?>">
    <button class="btn btn-primary" style="padding:.4rem .9rem;font-size:.85rem;">Search</button>
  </form>

  <div class="filters" style="margin-top:-.5rem;">
    <details class="morefilters">
      <summary>More filters</summary>
      <div class="mf-body">
        <select name="school_id" form="filterForm">
          <option value="">All schools</option>
          <?php foreach ($schools as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (string)$filters['school_id'] === (string)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="role" form="filterForm">
          <option value="">All roles</option>
          <?php foreach ($roles as $r): ?>
          <option value="<?= htmlspecialchars($r) ?>" <?= $filters['role'] === $r ? 'selected' : '' ?>>
            <?= htmlspecialchars($r) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="mc" form="filterForm">
          <option value="">Any Mailchimp status</option>
          <?php foreach (MC_STATUSES as $k => $lbl): ?>
          <option value="<?= htmlspecialchars($k) ?>" <?= $filters['mc'] === $k ? 'selected' : '' ?>>
            <?= htmlspecialchars($lbl) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($filters['status'] === 'archived'): ?>
          <?php // Filtering TO archived forces this on, so a checkbox here
                // would flip straight back and look broken. Say so instead. ?>
          <span style="font-size:.82rem;color:var(--gray-500);">Showing archived only.</span>
        <?php else: ?>
        <label><input type="checkbox" name="include_archived" value="1" form="filterForm"
                      <?= $filters['include_archived'] ? 'checked' : '' ?>> Include archived</label>
        <?php endif; ?>
        <button class="btn btn-primary" style="padding:.35rem .8rem;font-size:.82rem;" form="filterForm">Apply</button>
      </div>
    </details>

    <?php if ($views): ?>
    <details class="morefilters">
      <summary>Saved views (<?= count($views) ?>)</summary>
      <div class="mf-body" style="flex-direction:column;align-items:stretch;">
        <?php foreach ($views as $v): ?>
        <div class="viewrow">
          <a href="people.php?<?= htmlspecialchars($v['query']) ?>"><?= htmlspecialchars($v['name']) ?></a>
          <form method="POST" style="display:inline;"
                onsubmit="return confirm('Delete the saved view &quot;<?= htmlspecialchars(addslashes($v['name'])) ?>&quot;?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_view">
            <input type="hidden" name="view_id" value="<?= (int)$v['id'] ?>">
            <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
            <button class="act-btn" style="padding:.1rem .4rem;font-size:.72rem;">Delete</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endif; ?>
  </div>

  <?php
    // Shown when anything distinguishes this view from the default, which
    // includes a sort order — "everyone by last activity" is a view worth
    // saving even though it filters nothing out.
    // Must cover every key contactSearch() accepts, not just the ones with a
    // column heading: role and status are sortable by URL, and leaving them out
    // made the bar report "Sorted by Name" over a list ordered by role. An
    // unrecognised key claims nothing, matching contactSearch()'s fallback.
    $sortLabels = ['name' => 'Name', 'surname' => 'surname', 'email' => 'Email',
                   'school' => 'School', 'role' => 'Role', 'status' => 'Status',
                   'mc' => 'Mailchimp', 'updated' => 'Last activity'];
    $sortKey = isset($sortLabels[$filters['sort']]) ? $filters['sort'] : 'name';
    $sorted  = ($sortKey !== 'name' || $filters['dir'] !== 'asc');
    $showBar = $active || $sorted;
  ?>
  <?php if ($showBar): ?>
  <div class="pills">
    <?php if ($active): ?><span class="pl-label">Filtered by</span><?php endif; ?>
    <?php foreach ($active as $key => $label): ?>
      <?php
        // Removing a pill drops only that parameter; the rest of the view stays.
        $drop = [$key => '', 'page' => 1];
        // Turning off the archived FILTER also turns off "include archived",
        // since leaving it on would silently widen the list. Any other status
        // leaves the toggle alone.
        if ($key === 'status' && $filters['status'] === 'archived') $drop['include_archived'] = '';
      ?>
      <a class="pill" href="?<?= htmlspecialchars(qs($drop)) ?>">
        <?= htmlspecialchars($label) ?> <span class="x">&times;</span></a>
    <?php endforeach; ?>
    <a class="pl-clear" href="people.php">Clear all</a>
    <?php if ($sorted): ?>
      <span class="pl-label">Sorted by <?= htmlspecialchars($sortLabels[$sortKey]) ?><?php
        if ($filters['dir'] === 'desc'):
          // Only a date column is chronological; everything else just reverses.
          echo $sortKey === 'updated' ? ', newest first' : ', reversed';
        endif; ?></span>
    <?php endif; ?>

    <form method="POST" class="saveview">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_view">
      <input type="hidden" name="view_query" value="<?= htmlspecialchars($currentQuery) ?>">
      <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
      <input type="text" name="view_name" placeholder="Save this view as&hellip;" maxlength="120">
      <button class="act-btn">Save</button>
    </form>
  </div>
  <?php endif; ?>

  <?php // Both admin panels merged into one collapsed row: they are occasional
        // tasks and were pushing the list itself off the first screen. ?>
  <details class="panel">
    <summary>Add people &amp; send invitations</summary>
    <div class="body">
      <p class="hint">
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
        <textarea name="invite_list" rows="2" placeholder="Or paste: Jane Smith, jane@example.com"></textarea>
      </form>

      <form method="POST" action="member-manage.php" style="margin-bottom:1.1rem;"
            onsubmit="return confirm('Email a registration link to everyone on the list who has never been sent one?');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="send_all_invites">
        <input type="hidden" name="redirect" value="people.php">
        <button class="act-btn">Send links to everyone not yet emailed (<?= (int)$pc['listed'] ?>)</button>
      </form>

      <div style="border-top:1px solid var(--gray-100);padding-top:.9rem;">
        <p class="hint">
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
          <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
          <button class="act-btn" style="padding:.1rem .45rem;font-size:.74rem;">Fix links</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div style="background:#fff;border:1px solid var(--gray-200);border-radius:10px;">
      <p class="empty"><?= ($counts['total'] === 0 && $counts['archived'] === 0)
        ? 'No people yet.'
        : 'Nobody matches those filters.' ?></p>
      <?php // Archived rows are excluded from ['total'], so both must be zero
            // before this is genuinely an empty database rather than a filter
            // hiding everyone — the import would otherwise find nothing new. ?>
      <?php if ($counts['total'] === 0 && $counts['archived'] === 0): ?>
      <div style="padding:0 1.2rem 1.2rem;text-align:center;">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="import_roster">
          <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
          <button class="act-btn">Bring the membership roster across</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <div id="bulkbar" style="display:none;background:#fff;border:1px solid var(--gray-200);
       border-radius:10px;padding:.6rem .9rem;margin-bottom:.6rem;
       align-items:center;gap:.6rem;flex-wrap:wrap;">
    <span id="bulkcount" style="font-size:.85rem;font-weight:700;color:var(--gray-700);"></span>
    <form method="POST" style="display:inline;" onsubmit="return bulkGo(this,'archive');">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="archive">
      <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
      <button class="act-btn warn">Archive selected</button>
    </form>
    <form method="POST" style="display:inline;" onsubmit="return bulkGo(this,'restore');">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="restore">
      <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
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
        <th><?= sortLink('school','School',$filters) ?></th>
        <th>Portal</th>
        <th><?= sortLink('mc','Mailchimp',$filters) ?></th>
        <th><?= sortLink('updated','Last activity',$filters) ?></th>
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
          <a class="nm" href="person.php?id=<?= (int)$c['id'] ?>&amp;back=<?= urlencode($backQuery) ?>"
             style="text-decoration:none;"><?= htmlspecialchars(contactDisplayName($c)) ?></a>
          <?php if ($acct && !(int)$acct['active']): ?>
            <span class="badge" style="background:#fef2f2;color:#991b1b;">Deactivated</span>
          <?php endif; ?>
          <span class="em2"><?= htmlspecialchars($c['email']) ?><?php
            if ($c['role']): ?> &middot; <?= htmlspecialchars($c['role']) ?><?php endif; ?></span>
        </td>
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
          <?php $mcInfo = contactMcLabel($c); $mcL = $mcInfo[0]; ?>
          <?= mcBadge($c, $mcInfo) ?>
          <?php // Only when the badge is saying something else, or the cell
                // would read "Sync failed" twice. ?>
          <?php if ($c['mailchimp_sync_status'] === 'error' && $mcL !== 'Sync failed'): ?>
            <span class="syncerr">&#9888; sync failed</span>
          <?php endif; ?>
        </td>
        <td class="em" style="white-space:nowrap;">
          <?= $c['updated_at'] ? date('M j, Y', strtotime($c['updated_at'])) : '' ?>
        </td>
        <td>
          <?php
            // One visible action per row. The name is the primary target and
            // everything else lives behind the menu, so a dense list is not a
            // wall of equally weighted buttons. <details> keeps it working
            // without JavaScript.
          ?>
          <div class="rowacts">
            <details class="rowmenu">
              <summary title="Actions">&#8943;</summary>
              <div class="menu">
                <a href="person.php?id=<?= (int)$c['id'] ?>&amp;back=<?= urlencode($backQuery) ?>">Open record</a>
                <a href="contact-edit.php?id=<?= (int)$c['id'] ?>&amp;back=<?= urlencode($backQuery) ?>">Edit details</a>

                <?php if ($inv && in_array($state, ['listed','sent','expired'], true)): ?>
                  <form method="POST" action="member-manage.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="resend_invite">
                    <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                    <input type="hidden" name="redirect" value="people.php?<?= htmlspecialchars($backQuery) ?>">
                    <button><?= $state === 'listed' ? 'Send registration link' : 'Re-send registration link' ?></button>
                  </form>
                <?php elseif ($state === 'none'): ?>
                  <form method="POST" action="member-manage.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="send_invites">
                    <input type="hidden" name="redirect" value="people.php?<?= htmlspecialchars($backQuery) ?>">
                    <input type="hidden" name="invite_list"
                           value="<?= htmlspecialchars(contactDisplayName($c) . ', ' . $c['email']) ?>">
                    <button>Add to invite list</button>
                  </form>
                <?php endif; ?>

                <?php if ($acct && !$isSelf): ?>
                  <form method="POST" action="member-manage.php"
                        onsubmit="return confirm('<?= (int)$acct['active'] ? 'Deactivate' : 'Reactivate' ?> this login?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="member_id" value="<?= (int)$acct['id'] ?>">
                    <input type="hidden" name="set_active" value="<?= (int)$acct['active'] ? 0 : 1 ?>">
                    <input type="hidden" name="redirect" value="people.php?<?= htmlspecialchars($backQuery) ?>">
                    <button class="<?= (int)$acct['active'] ? 'warn' : '' ?>">
                      <?= (int)$acct['active'] ? 'Deactivate login' : 'Reactivate login' ?></button>
                  </form>
                <?php endif; ?>

                <?php if ($c['status'] === 'archived'): ?>
                  <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
                    <button>Restore</button>
                  </form>
                <?php else: ?>
                  <form method="POST"
                        onsubmit="return confirm('Archive <?= htmlspecialchars(addslashes(contactDisplayName($c))) ?>? They stay in the database and can be restored.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="back" value="<?= htmlspecialchars($backQuery) ?>">
                    <button class="warn">Archive</button>
                  </form>
                <?php endif; ?>
              </div>
            </details>
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
