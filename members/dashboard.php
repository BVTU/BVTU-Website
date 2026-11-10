<?php
require_once 'auth.php';
require_once 'exp-db.php';
require_once 'exec-db.php';
require_once 'prod-db.php';
require_once 'lp-db.php';   // lpCanReview/lpCanSign*/lpCountByStatus for the approvals entry
require_once 'collab-grant-db.php';  // cgAppsForEmail() for "my grant applications"
requireLogin();
$member  = getMember();
$welcome = isset($_GET['welcome']);
expEnsureTables();
execEnsureTables();
prodEnsureTables();

$myEmail = strtolower(trim($member['email']));

// Collaboration Grant applications carrying this member's address. The public
// form takes the email as free text and needs no login, so a member only sees
// the ones they happened to apply with — the emailed link is the general way in.
$myGrants = [];
try { $myGrants = cgAppsForEmail($myEmail); } catch (Exception $e) { $myGrants = []; }

// ── Gather this member's roles for the dashboard badge display ────────────────
// EC roles (official positions)
$s = getDB()->prepare("SELECT role FROM exec_roles WHERE user_email=? ORDER BY created_at");
$s->execute([$myEmail]);
$myExecRoleSlugs = $s->fetchAll(PDO::FETCH_COLUMN);

// Expense system roles
$s = getDB()->prepare("SELECT role FROM exp_roles WHERE user_email=?");
$s->execute([$myEmail]);
$myExpRoleSlugs = $s->fetchAll(PDO::FETCH_COLUMN);

// Pro-D roles
$s = getDB()->prepare("SELECT role, school_id FROM prod_roles WHERE user_email=?");
$s->execute([$myEmail]);
$myProdRoleRows = $s->fetchAll();

// Build display labels for EC roles
$allExecRoles = execGetAllRoles();
$myRoleLabels = [];
foreach ($myExecRoleSlugs as $slug) {
    if (isset($allExecRoles[$slug])) $myRoleLabels[] = $allExecRoles[$slug];
}
// Constant admin badge
if (execIsAdmin($myEmail) && empty($myExecRoleSlugs)) {
    $myRoleLabels[] = 'President';
}
/* ── What this member may do, worked out once ─────────────────────────────────
 *
 * One list, built from the SAME guards the old stacked sections used, so the
 * question "may I see this?" is asked in exactly one place per action and the
 * search, the groups and the quick cards cannot disagree about the answer.
 *
 * Each entry: label, href, group, icon, keywords (what somebody would type
 * looking for it), and an optional badge. 'quick' marks the three Cody reaches
 * for daily; a quick action still appears in its group, because a card at the
 * top is a shortcut, not a different destination.
 *
 * Guards are copied verbatim from the sections they replace. Nothing here
 * decides permission on its own.
 */
$isAdmin   = execIsAdmin($myEmail);
$isProdEx  = prodIsExec($myEmail);
$isExpAdm  = expIsAdmin($myEmail);

/* Counted before rendering rather than inside the markup, because the search
 * and the group both want the same number and computing it twice would mean
 * two chances to compute it differently. The rule is unchanged from 0d61baf:
 * your own claims are not work waiting on you, signing bars the submitter and
 * paying bars only the payee. */
$waiting = 0;
if (expCanReview($member['email']) || lpCanReview($member['email'])) {
    $mineOut = function (array $rows) use ($member) {
        return count(array_filter($rows, fn($r) => !expWouldSelfSign(
            $member['email'], $r['user_email'] ?? null, $r['submitted_by_email'] ?? null)));
    };
    if (expIsEligibleSigner1($member['email'])) $waiting += $mineOut(expBatchGetAll('pending'));
    if (expIsEligibleSigner2($member['email'])) $waiting += $mineOut(expBatchGetAll('signer1_approved'));
    if (expCanMarkPaid($member['email']))       $waiting += $mineOut(expBatchGetAll('signer2_approved'));

    // One pass: lpGetVouchersByStatuses runs a GROUP BY aggregate, and calling
    // it per status ran the same join three times. The statuses come from the
    // role to keep that one query, and eligibility is then re-asked per
    // voucher — on a vendor payment the President is a signer and a
    // page-level answer cannot know that.
    $mayFor = ['submitted'           => lpCanSign1($member['email']) || expIsPresident($member['email']),
               'vp_approved'         => lpCanSign1($member['email']) || expIsPresident($member['email']),
               'treasurer_approved'  => lpCanSign2($member['email']) || expIsPresident($member['email'])];
    $wanted = array_keys(array_filter($mayFor));
    if ($wanted) {
        $waiting += count(array_filter(
            lpGetVouchersByStatuses($wanted),
            function ($v) use ($member) {
                /* Which bar applies depends on what is being waited for.
                 * Signing bars the submitter; paying bars only the payee,
                 * because sending the money is not a third signature. */
                $blocked = $v['status'] === 'vp_approved'
                    ? lpPayBlockedClaimant($v)
                    : lpSignBlockedClaimant($v);
                if (expWouldSelfSign($member['email'], lpPayTo($v)['email'] ?: null,
                                     $blocked)) return false;
                if ($v['status'] === 'treasurer_approved') return lpCanSign2For($v, $member['email']);
                if ($v['status'] === 'vp_approved')        return lpCanRecordPayment($v, $member['email']);
                return lpCanSign1For($v, $member['email']);
            }
        ));
    }
}

// Only the President has this page, and only they pay for the lookup.
$bctfPending = 0;
if ($isAdmin) {
    require_once 'bctf-db.php';
    $bctfPending = count(bctfGetPending());
}

/** The inline icons the old list used, kept so nothing changes visually. */
function dashIcon(string $k): string {
    $p = [
      'screen'   => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
      'file-add' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>',
      'check'    => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
      'mail'     => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
      'chat'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
      'book'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
      'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
      'doc'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
      'pencil'   => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
      'people'   => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
      'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      'lock'     => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
      'user-add' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
      'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
      'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
      'video'    => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>',
      'chart'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
      'wallet'   => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/>',
      'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$k] ?? $p['doc']) . '</svg>';
}

$actions = [];
/* $quick is a rank, not a flag: the three cards have an agreed order — the
 * grant review first — and that order is not the order the groups want. */
$add = function (bool $show, string $label, string $href, string $group, string $icon,
                 string $keywords, string $badge = '', int $quick = 0,
                 string $desc = '', string $cta = '') use (&$actions) {
    if (!$show) return;
    $actions[] = ['label' => $label, 'href' => $href, 'group' => $group, 'icon' => $icon,
                  'keywords' => $keywords, 'badge' => $badge, 'quick' => $quick,
                  'desc' => $desc, 'cta' => $cta];
};

// ── Finance & approvals ──────────────────────────────────────────────────────
$add(lpCanView($myEmail), 'Expenses & Grants', 'lp-dashboard.php', 'finance', 'wallet',
     'budget grants vouchers lp release time treasurer money spending', '', 2,
     'Access expenses, grants, approvals and payments.', 'Open workspace');
$add(lpCanView($myEmail) && lpCanCreate($myEmail), 'New expense voucher', 'lp-voucher-new.php',
     'finance', 'plus', 'voucher claim receipt lp new create expense', '', 3,
     'Start a new reimbursement or expense submission.', 'Create voucher');
// The President claims through LP vouchers, so these two are for everybody else.
$add(!$isAdmin, 'My Expense Submissions', 'exp-dashboard.php', 'finance', 'screen',
     'my claims expenses reimbursement submitted status');
$add(!$isAdmin, 'Submit New Expense', 'exp-submit.php', 'finance', 'file-add',
     'claim reimbursement receipt mileage submit new expense');
$add(expCanReview($member['email']) || lpCanReview($member['email']),
     'Approvals & Payments', 'approvals.php', 'finance', 'check',
     'approve sign queue pay etransfer treasurer vp president waiting',
     $waiting > 0 ? $waiting . ' waiting on you' : '');
$add($isAdmin || $isProdEx, 'Collaboration Grant', 'collab-grant-admin.php',
     'finance', 'people', 'collaboration grant applications review csv export release days',
     '', 1, 'Review applications and export submissions.', 'Review applications');

// ── Content & resources ──────────────────────────────────────────────────────
$add(true, 'Browse & Search Newsletters', '../newsletter-archive.php', 'content', 'mail',
     'newsletter archive issues read past bulletin');
$add($isAdmin || $isProdEx, 'Sync from Mailchimp (Admin)', 'newsletter-admin.php', 'content',
     'chat', 'mailchimp sync newsletter import admin');
$add(true, 'Browse & Download Resources', '../library.php', 'content', 'book',
     'resource library lesson plans units rubrics download search');
$add(true, 'Upload a Resource', '../library-upload.php', 'content', 'upload',
     'share upload resource lesson plan contribute library');
$add($isAdmin || $isProdEx, 'Library Admin Panel', 'library-admin.php', 'content', 'doc',
     'library admin moderate resources flags');

// ── Administration ───────────────────────────────────────────────────────────
$add($isAdmin, 'People', 'people.php', 'admin', 'user-add',
     'contacts people directory members accounts invitations');
$add($isAdmin || $isProdEx || $isExpAdm, 'Roles & Directory', 'roles-overview.php', 'admin',
     'people', 'roles directory executive who does what positions');
$add($isAdmin, 'Email', 'email-templates.php', 'admin', 'mail',
     'email templates wording automated messages log');
$add($isAdmin, 'BCTF Membership Forms', 'bctf-forms.php', 'admin', 'doc',
     'bctf membership forms provincial send',
     $bctfPending > 0 ? $bctfPending . ' ready to send' : '');
$add($isAdmin, 'OneDrive Doc Upload', 'onedrive.php', 'admin', 'upload',
     'onedrive documents upload sharepoint files');
$add($isAdmin, 'Link Shortener & QR Codes', 'links-manage.php', 'admin', 'link',
     'short links qr codes go redirect');
$add($isAdmin, 'Events & Registration', 'event-admin.php', 'admin', 'calendar',
     'events workshops surt registration signups calendar');
$add($isAdmin, 'Forum Recording', 'forum-admin.php', 'admin', 'video',
     'forum recording video chapters candidates');
$add($isAdmin, 'Site Analytics', 'analytics.php', 'admin', 'chart',
     'analytics traffic views pages visitors usage');
// Below analytics, where it has always been.
$add($isAdmin, 'Claude API Token Usage', 'token-usage.php', 'admin', 'doc',
     'claude api tokens ai spend cost usage');

// ── My account ───────────────────────────────────────────────────────────────
$add(true, 'My Profile', 'my-account.php', 'account', 'user',
     'profile my account details name email');
$add(true, 'Change Password', 'change-password.php', 'account', 'lock',
     'password change security login');
$add(true, 'Sign Out', 'logout.php', 'account', 'lock', 'sign out log out leave');

$groups = [
    'finance' => ['Finance & approvals', 'Expense review and reimbursements',       'wallet'],
    'content' => ['Content & resources', 'Newsletters, resource library and publishing', 'book'],
    'admin'   => ['Administration',      'People, settings and site tools',          'people'],
    'account' => ['My account',          'Your profile and signing out',             'user'],
];
$quickActions = array_values(array_filter($actions, fn($a) => $a['quick'] > 0));
usort($quickActions, fn($a, $b) => $a['quick'] <=> $b['quick']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="../">
  <title>Member Dashboard — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    /* Colours come from css/style.css. The few literals here are the
       prototype's neutrals, which the brand kit has no token for. */
    body { background:#f6f9f7; }
    .dash-shell { max-width:1060px; margin:0 auto; padding:calc(var(--hdr-h) + 2.25rem) 1.4rem 5rem; }

    /* ── Welcome: a header in the column, not a full-width band ───────── */
    .dash-shell .dash-welcome { display:flex; align-items:flex-end; justify-content:space-between;
                    gap:1rem; flex-wrap:wrap; margin-bottom:1.3rem; }
    /* Every muted text colour below is var(--gray-600) (#44574c): 7.30:1 on
       this canvas and 7.74:1 on a white panel, where the greens they replaced
       measured between 2.81 and 4.35 and failed AA for normal text. Icons and
       arrows stay lighter — they are decoration and carry no words. */
    .dash-shell .dash-eyebrow { font-size:.63rem; font-weight:800; letter-spacing:.14em;
                    color:var(--gray-600); text-transform:uppercase; margin-bottom:.45rem; }
    .dash-shell .dash-welcome h1 { font-size:1.75rem; font-weight:800; letter-spacing:-.02em;
                       color:var(--gray-800); margin:0 0 .3rem; }
    .dash-shell .dash-welcome .subtitle { font-size:.86rem; color:var(--gray-600); margin:0; }
    .dash-shell .role-chips { display:flex; flex-wrap:wrap; gap:.4rem; }
    .dash-shell .role-chip { font-size:.68rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase;
                 background:#eef6ef; color:#2d6a47; border:1px solid #d6e7dc;
                 border-radius:100px; padding:.28rem .7rem; }

    .dash-shell .welcome-banner { background:#dcfce7; border:1px solid #86efac; color:#166534;
                      border-radius:var(--radius-s); padding:.85rem 1.1rem;
                      margin-bottom:1.5rem; font-size:.93rem; font-weight:500; }

    /* ── Search ───────────────────────────────────────────────────────── */
    .dash-shell .dash-find { display:flex; align-items:center; gap:.7rem; background:#fff;
                 border:1px solid #dbe7df; border-radius:11px; height:48px;
                 padding:0 .95rem; margin-bottom:.6rem;
                 box-shadow:0 2px 8px rgba(25,55,37,.02); }
    .dash-shell .dash-find:focus-within { border-color:var(--primary);
                              outline:3px solid var(--primary); outline-offset:2px; }
    .dash-shell .dash-find > svg { width:18px; height:18px; color:#769083; flex-shrink:0;
                       fill:none; stroke:currentColor; stroke-width:2; }
    /* Room on the right for the browser's own clear button, which a
       type="search" field draws inside the input: without it the X and the
       shortcut hint sit on top of one another. */
    .dash-shell .dash-find input { flex:1; min-width:0; border:0; outline:0; background:transparent;
                       font:inherit; font-size:.92rem; color:var(--text);
                       padding-right:.55rem; }
    .dash-shell .dash-find input::-webkit-search-cancel-button { margin-left:.35rem; }
    .dash-shell .dash-find kbd { font:inherit; font-size:.69rem; color:var(--gray-500); border:1px solid #e0e6e0;
                     border-radius:5px; padding:.15rem .4rem; flex-shrink:0; }
    .dash-shell .dash-count { font-size:.78rem; color:var(--gray-600); margin:0 0 1.6rem; min-height:1.1em; }

    /* ── Section heads ────────────────────────────────────────────────── */
    .dash-shell .dash-sechead { margin:0 0 1rem; }
    .dash-shell .dash-sechead h2 { font-size:1.19rem; font-weight:800; letter-spacing:-.02em;
                       color:var(--gray-800); margin:0; }
    .dash-shell .dash-sechead p { font-size:.76rem; color:var(--gray-600); margin:.35rem 0 0; }

    /* ── Quick access ─────────────────────────────────────────────────── */
    .dash-shell .dash-quick { display:grid; grid-template-columns:repeat(3,minmax(0,1fr));
                  gap:.9rem; margin-bottom:2.3rem; }
    .dash-shell .quickcard { background:#fff; border:1px solid #dbe7df; border-radius:14px; padding:1.3rem;
                 display:flex; flex-direction:column; align-items:flex-start; min-height:192px;
                 color:var(--text); text-decoration:none;
                 box-shadow:0 2px 10px rgba(25,55,37,.03);
                 transition:transform .15s, border-color .15s, box-shadow .15s; }
    .dash-shell .quickcard:hover { transform:translateY(-2px); border-color:#91bea0;
                       box-shadow:0 12px 32px rgba(21,64,39,.1); text-decoration:none; color:var(--text); }
    .dash-shell .quickcard:focus-visible { outline:3px solid var(--primary); outline-offset:3px; }
    .dash-shell .quickcard.primary { background:var(--primary-dk); border-color:var(--primary-dk); color:#fff; }
    .dash-shell .quickcard.primary:hover { color:#fff; }
    .dash-shell .quickcard.primary .q-desc { color:#cce0d3; }
    .dash-shell .quickcard.primary .q-arrow { color:#fff; }
    .dash-shell .q-iconbox { height:39px; width:39px; border-radius:11px; background:#e7f3eb;
                 color:var(--primary); display:grid; place-items:center; margin-bottom:1.35rem; }
    .dash-shell .quickcard.primary .q-iconbox { background:#2c7550; color:#fff; }
    .dash-shell .q-iconbox svg { width:20px; height:20px; fill:none; stroke:currentColor;
                     stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .dash-shell .quickcard h3 { font-size:.94rem; font-weight:700; letter-spacing:-.01em; margin:0 0 .5rem; }
    .dash-shell .q-desc { font-size:.76rem; line-height:1.6; color:var(--gray-600); margin:0; flex:1; }
    .dash-shell .q-foot { margin-top:1rem; width:100%; display:flex; align-items:center;
              justify-content:space-between; font-size:.75rem; font-weight:750; }
    .dash-shell .q-arrow { font-size:1.2rem; color:var(--primary); line-height:1; }
    .dash-shell .q-badge { font-size:.7rem; font-weight:700; background:rgba(255,255,255,.22);
               border-radius:100px; padding:.12rem .5rem; }
    .dash-shell .quickcard:not(.primary) .q-badge { background:var(--gray-100); color:var(--gray-600); }

    /* ── Grouped panels: one outer border, rows inside it ─────────────── */
    .dash-shell .dash-panel { border:1px solid #dbe7df; background:#fff; border-radius:14px;
                  box-shadow:0 2px 10px rgba(25,55,37,.03); overflow:hidden; margin-bottom:.9rem; }
    .dash-shell .dash-panelhead { display:flex; align-items:center; gap:.7rem; padding:.7rem 1rem; }
    .dash-shell .dash-panelhead .heading-icon { width:30px; height:30px; border-radius:8px; background:#eef6ef;
                                    display:grid; place-items:center; color:var(--primary); flex-shrink:0; }
    .dash-shell .dash-panelhead .heading-icon svg { width:16px; height:16px; fill:none; stroke:currentColor;
                                        stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .dash-shell .dash-panelhead h3 { font-size:.85rem; font-weight:700; margin:0 0 .12rem; color:var(--gray-800); }
    .dash-shell .dash-panelhead p { font-size:.73rem; color:var(--gray-600); margin:0; }

    /* Server-rendered so it can be tested, and carrying `hidden` until the
       script takes it over: with scripting off there is no button to press
       and nothing is collapsed, so every link stays reachable. */
    .dash-shell .dash-toggle { margin-left:auto; display:flex; align-items:center; gap:.3rem;
                   background:none; border:0; cursor:pointer; font:inherit;
                   font-size:.73rem; font-weight:750; color:var(--primary);
                   padding:.5rem .4rem; border-radius:7px; }
    /* display:flex above overrides the browser's own [hidden]{display:none},
       so without this the control would be visible on a page with no
       scripting — where it does nothing at all. */
    .dash-shell .dash-toggle[hidden] { display:none; }
    .dash-shell .dash-toggle:hover { background:#eef6ef; }
    .dash-shell .dash-toggle:focus-visible { outline:3px solid var(--primary); outline-offset:2px; }
    .dash-shell .dash-toggle .chev { display:inline-block; transition:transform .15s; font-size:.9em; }
    .dash-shell .dash-toggle[aria-expanded="false"] .chev { transform:rotate(-90deg); }
    .dash-shell .dash-panelbody[hidden] { display:none; }
    .dash-shell .dash-linkgrid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr));
                     gap:0 .8rem; padding:0 1rem .55rem; }
    .dash-shell .tool-link { min-height:42px; display:flex; align-items:center; gap:.6rem;
                 flex-wrap:wrap; padding:.45rem .65rem; border-radius:8px; color:#2d493a;
                 font-size:.82rem; font-weight:600; text-decoration:none; }
    .dash-shell .tool-link:hover { background:#f0f7f2; text-decoration:none; color:#2d493a; }
    .dash-shell .tool-link:focus-visible { outline:3px solid var(--primary); outline-offset:-2px; }
    .dash-shell .tool-link > svg { width:17px; height:17px; color:#2c8760; flex-shrink:0;
                       fill:none; stroke:currentColor; stroke-width:2;
                       stroke-linecap:round; stroke-linejoin:round; }
    .dash-shell .tool-link .end { margin-left:auto; color:#abc3b1; font-size:1.05rem; line-height:1; }
    .dash-shell .lock-badge { margin-left:auto; font-size:.7rem; font-weight:700; color:var(--gray-600);
                  background:var(--gray-100); padding:.15rem .5rem; border-radius:100px;
                  white-space:nowrap; }
    .dash-shell .lock-badge + .end { margin-left:.4rem; }

    .dash-shell .dash-standing { font-size:.8rem; color:var(--gray-600); margin:0; padding:0 1rem .5rem; line-height:1.55; }
    .dash-shell .dash-empty { display:none; text-align:center; padding:2.4rem; color:var(--gray-600); font-size:.88rem; }
    .dash-shell .dash-empty strong { display:block; color:var(--text); margin-bottom:.4rem; }

    @media (max-width:860px) {
      .dash-shell .dash-quick { grid-template-columns:1fr; gap:.65rem; }
      /* Compact rows on a phone: icon, text, arrow — not three tall cards. */
      .dash-shell .quickcard { min-height:0; padding:1.05rem; display:grid;
                   grid-template-columns:43px 1fr auto; column-gap:.75rem; align-items:center; }
      .dash-shell .q-iconbox { margin:0; grid-row:span 3; }
      .dash-shell .quickcard h3 { grid-column:2; margin:0 0 .25rem; }
      .dash-shell .q-desc { grid-column:2; font-size:.72rem; }
      .dash-shell .q-foot { grid-column:3; grid-row:1/span 3; width:auto; margin:0; }
      .dash-shell .q-foot .q-cta { display:none; }
      .dash-shell .dash-linkgrid { grid-template-columns:1fr; padding:0 .7rem .6rem; }
      .dash-shell .dash-panelhead { padding:.7rem .8rem; }
      /* Comfortably tappable on a phone, where 42px is tight. */
      .dash-shell .tool-link { min-height:46px; }
      .dash-shell .dash-toggle { min-height:44px; padding:.5rem .55rem; }
      .dash-shell { padding-left:1rem; padding-right:1rem; }
    }
    @media (prefers-reduced-motion:reduce) {
      .dash-shell .quickcard { transition:none; }
      .dash-shell .quickcard:hover { transform:none; }
      .dash-shell .dash-toggle .chev { transition:none; }
    }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/../inc/nav.php'; bvtuNav(['base' => '../']); ?>

  <main class="dash-shell">

    <div class="dash-welcome">
      <div>
        <div class="dash-eyebrow">My dashboard</div>
        <h1>Welcome, <?= htmlspecialchars($member['name']) ?></h1>
        <p class="subtitle">Your claims, applications, resources and account.</p>
      </div>
      <?php if (!empty($myRoleLabels)): ?>
      <div class="role-chips">
        <?php foreach ($myRoleLabels as $label): ?>
        <span class="role-chip"><?= htmlspecialchars($label) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($welcome): ?>
      <div class="welcome-banner">
        Your account has been created successfully. Welcome to the BVTU members portal.
      </div>
    <?php endif; ?>

    <?php // Labelled for a screen reader; the magnifier is decoration. ?>
    <label class="dash-find" for="dashfind">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <span class="sr-only">Search everything you can do here</span>
      <input type="search" id="dashfind" autocomplete="off"
             placeholder="Find a dashboard tool or action…" aria-describedby="dashcount">
      <kbd aria-hidden="true">&#8984; K</kbd>
    </label>
    <p class="dash-count" id="dashcount" role="status" aria-live="polite"></p>

    <?php if ($quickActions): ?>
    <section data-section>
      <div class="dash-sechead">
        <h2>Quick access</h2>
        <p>The work you do most often, one click away.</p>
      </div>
      <div class="dash-quick" id="dashquick">
        <?php foreach ($quickActions as $i => $a): ?>
        <a href="<?= htmlspecialchars($a['href']) ?>"
           class="quickcard<?= $i === 0 ? ' primary' : '' ?>"
           data-find="<?= htmlspecialchars(strtolower($a['label'] . ' ' . $a['keywords'])) ?>"
           data-dest="<?= htmlspecialchars($a['href']) ?>">
          <span class="q-iconbox"><?= dashIcon($a['icon']) ?></span>
          <h3><?= htmlspecialchars($a['label']) ?></h3>
          <p class="q-desc"><?= htmlspecialchars($a['desc']) ?></p>
          <span class="q-foot">
            <span class="q-cta"><?= htmlspecialchars($a['cta']) ?></span>
            <?php if ($a['badge'] !== ''): ?><span class="q-badge"><?= htmlspecialchars($a['badge']) ?></span><?php endif; ?>
            <span class="q-arrow" aria-hidden="true">&#8599;</span>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <div class="dash-sechead">
      <h2>Tools &amp; resources</h2>
      <p>Everything else, organized by what you're trying to do.</p>
    </div>

    <?php if ($myGrants): ?>
    <?php /* Personally owned, so it keeps its own panel: the standing line is a
             status readout rather than a link, and carries no data-find — it is
             not an action and must not be counted as one. */ ?>
    <div class="dash-panel" data-panel>
      <div class="dash-panelhead">
        <span class="heading-icon"><?= dashIcon('pencil') ?></span>
        <div>
          <h3>My Collaboration Grant<?= count($myGrants) > 1 ? 's' : '' ?></h3>
          <p>What you have applied for this school year</p>
        </div>
        <?php /* hidden until the script claims it — see the note on
                 .dash-toggle. The accessible name carries the category, so a
                 screen reader hears "Minimize My Collaboration Grants" rather
                 than the fifth identical "Minimize" on the page. */
              $gTitle = 'My Collaboration Grant' . (count($myGrants) > 1 ? 's' : ''); ?>
        <button type="button" class="dash-toggle" hidden
                aria-expanded="true" aria-controls="dashbody-grants"
                data-label="<?= htmlspecialchars($gTitle) ?>"
                aria-label="Minimize <?= htmlspecialchars($gTitle) ?>">
          <span class="dash-toggle-text">Minimize</span><span class="chev" aria-hidden="true">&#9662;</span>
        </button>
      </div>
      <div class="dash-panelbody" id="dashbody-grants">
      <?php $cgStanding = cgPersonDays(cgCurrentYear(), $myEmail, $member['name']); ?>
      <p class="dash-standing">
        <?php if (empty($cgStanding['capped'])): ?>
          <?= (int)$cgStanding['used'] ?> <?= (int)$cgStanding['used'] === 1 ? 'day' : 'days' ?>
          of collaboration this school year<?php
            ?><?= $cgStanding['pending'] ? ', including ' . (int)$cgStanding['pending'] . ' awaiting a decision' : '' ?>
          — the <?= CG_DAY_CAP ?>-day yearly limit doesn't apply to you.
        <?php else: ?>
          <?= (int)$cgStanding['used'] ?> of <?= CG_DAY_CAP ?> release days used this school year<?php
            ?><?= $cgStanding['pending'] ? ', including ' . (int)$cgStanding['pending'] . ' awaiting a decision' : '' ?>.
          <?php if ($cgStanding['left'] > 0): ?>
            <?= (int)$cgStanding['left'] ?> left.
          <?php endif; ?>
        <?php endif; ?>
      </p>
      <div class="dash-linkgrid">
        <?php foreach ($myGrants as $g):
          $gLabel = ['pending' => 'Pending review', 'approved' => 'Approved',
                     'declined' => 'Declined', 'waitlisted' => 'Waitlisted'][$g['status']] ?? ucfirst($g['status']);
          $gDates = json_decode($g['proposed_dates'] ?? '[]', true);
          $gDates = is_array($gDates) ? $gDates : [];
          $gYear  = (int)$g['school_year'] . '–' . (((int)$g['school_year'] + 1) % 100);
          $gDays  = (int)$g['days_requested'] . ' ' . ((int)$g['days_requested'] === 1 ? 'day' : 'days');
          $gWhen  = $gDates ? implode(', ', array_map(fn($d) => date('M j', strtotime($d)), $gDates)) : '';
          // Searchable by what is actually printed on the row: the year, the
          // decision, the day count and the dates.
          $gFind  = strtolower(trim('my collaboration grant application '
                    . $gYear . ' ' . $gLabel . ' ' . $gDays . ' ' . $gWhen));
        ?>
        <a href="../collab-grant-edit.php?id=<?= (int)$g['id'] ?>" class="tool-link"
           data-find="<?= htmlspecialchars($gFind) ?>"
           data-dest="collab-grant-edit-<?= (int)$g['id'] ?>">
          <?= dashIcon('pencil') ?>
          <span><?= htmlspecialchars($gYear) ?> · <?= htmlspecialchars($gLabel) ?> · <?= htmlspecialchars($gDays) ?><?php
            if ($gWhen): ?> (<?= htmlspecialchars($gWhen) ?>)<?php endif; ?></span>
          <span class="end" aria-hidden="true">&rsaquo;</span>
        </a>
        <?php endforeach; ?>
      </div>
      </div>
    </div>
    <?php endif; ?>

    <?php foreach ($groups as $key => [$title, $blurb, $gicon]): ?>
      <?php $items = array_values(array_filter($actions, fn($a) => $a['group'] === $key)); ?>
      <?php if (!$items) continue; ?>
      <div class="dash-panel" data-panel>
        <div class="dash-panelhead">
          <span class="heading-icon"><?= dashIcon($gicon) ?></span>
          <div>
            <h3><?= htmlspecialchars($title) ?></h3>
            <p><?= htmlspecialchars($blurb) ?></p>
          </div>
          <button type="button" class="dash-toggle" hidden
                  aria-expanded="true" aria-controls="dashbody-<?= htmlspecialchars($key) ?>"
                  data-label="<?= htmlspecialchars($title) ?>"
                  aria-label="Minimize <?= htmlspecialchars($title) ?>">
            <span class="dash-toggle-text">Minimize</span><span class="chev" aria-hidden="true">&#9662;</span>
          </button>
        </div>
        <div class="dash-panelbody" id="dashbody-<?= htmlspecialchars($key) ?>">
        <div class="dash-linkgrid">
          <?php foreach ($items as $a): ?>
          <a href="<?= htmlspecialchars($a['href']) ?>" class="tool-link"
             data-find="<?= htmlspecialchars(strtolower($a['label'] . ' ' . $a['keywords'])) ?>"
             data-dest="<?= htmlspecialchars($a['href']) ?>">
            <?= dashIcon($a['icon']) ?>
            <?= htmlspecialchars($a['label']) ?>
            <?php if ($a['badge'] !== ''): ?><span class="lock-badge"><?= htmlspecialchars($a['badge']) ?></span><?php endif; ?>
            <span class="end" aria-hidden="true">&rsaquo;</span>
          </a>
          <?php endforeach; ?>
        </div>
        </div>
      </div>
    <?php endforeach; ?>

    <p class="dash-empty" id="dashempty" role="status">
      <strong>Nothing here matches that.</strong>
      Try a different word, or clear the box to see everything again.
    </p>

  </main>

  <footer class="site-footer">
    <div class="footer-bottom" style="border-top: none;">
      <div class="container">
        <p style="padding: 1.5rem 0;">© 2026 Bulkley Valley Teachers' Union · <a href="logout.php" style="color:rgba(255,255,255,.5)">Sign out</a></p>
      </div>
    </div>
  </footer>

  <script src="../js/site.js?v=<?= @filemtime(__DIR__ . '/../js/site.js') ?>"></script>
  <script src="../js/search.js"></script>
  <script src="../js/dashboard-find.js?v=<?= @filemtime(__DIR__ . '/../js/dashboard-find.js') ?>"></script>
</body>
</html>
