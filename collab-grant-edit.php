<?php
/**
 * collab-grant-edit.php — change an already-submitted Collaboration Grant
 *
 * Until now a submitted application was out of reach: a teacher who wanted to
 * add a release day had to email the president, who had no edit screen either
 * and would retype it into the database. This page is that screen, for both.
 *
 * Reaching it takes one of three things:
 *   • ?t=<token>  — the link in the confirmation email. Applications are not
 *     tied to member accounts (the public form does not require a login and
 *     takes the email as free text), so for most applicants this is the only
 *     way in.
 *   • a signed-in member whose account email matches the application's.
 *   • the president, who may also edit what applicants no longer can.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/collab-grant-db.php';

sendPrivateHeaders();   // the token is in the URL: keep it out of caches and referrers

$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
$isAdmin  = $loggedIn && cgIsAdmin($member['email']);

// ── Who is asking, and about which application ────────────────────────────
$app   = null;
$tRaw  = $_GET['t'] ?? $_POST['t'] ?? '';
$token = is_scalar($tRaw) ? trim((string)$tRaw) : '';

if ($token !== '') {
    $app = cgAppByToken($token);
} elseif ($loggedIn) {
    $id = (int)($_GET['id'] ?? $_POST['app_id'] ?? 0);
    if ($id) {
        $candidate = cgGetApplication($id);
        // A signed-in member reaches an application by id only when it carries
        // their own address — otherwise the id alone would be the whole key.
        if ($candidate && ($isAdmin || strtolower(trim($candidate['applicant_email'] ?? ''))
                                       === strtolower(trim($member['email'])))) {
            $app = $candidate;
        }
    }
}

$denied = ($app === null);
$actor  = $loggedIn ? $member['email'] : ($app['applicant_email'] ?? 'applicant');

// What this visitor may change.
$locked   = $app ? cgIsLockedToApplicant($app) : false;       // district paperwork exists
$declined = $app ? ($app['status'] === 'declined') : false;
$canEdit  = $app && ($isAdmin || (!$locked && !$declined));

$notice = '';
$error  = '';
$justSaved = [];

if (!$denied && $canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cg_save'])) {
    if (!csrfValid()) {
        $error = 'Your session expired. Please try saving again.';
    } else {
        $f = function (string $k): string { return trim($_POST[$k] ?? ''); };

        // Dates: up to three, one per box. An existing date is kept even once it
        // has passed — a teacher adding a third day in December must not silently
        // lose the October day they already took.
        $was = json_decode($app['proposed_dates'] ?? '[]', true);
        $was = is_array($was) ? $was : [];
        $today = date('Y-m-d');
        $dates = [];
        foreach ([0, 1, 2] as $i) {
            $d = trim($_POST['date' . $i] ?? '');
            if ($d === '') continue;
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)
                || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                $error = 'Please enter each date as a real calendar date.';
                break;
            }
            $dow = (int)date('N', strtotime($d));
            if ($dow > 5) {
                $error = 'Release days have to fall on a weekday — ' . date('D M j', strtotime($d))
                       . ' is a ' . date('l', strtotime($d)) . '.';
                break;
            }
            if ($d < $today && !in_array($d, $was, true)) {
                $error = 'New dates need to be in the future — ' . date('D M j, Y', strtotime($d))
                       . ' has already passed.';
                break;
            }
            if (in_array($d, $dates, true)) {
                $error = 'The same date is listed twice.';
                break;
            }
            $dates[] = $d;
        }
        sort($dates);

        if (!$error && !$dates)      $error = 'Please keep at least one release day.';
        if (!$error && count($dates) > 3) $error = 'The grant covers up to three release days.';
        if (!$error && !$f('name'))  $error = 'Please enter your name.';
        if (!$error && !$f('school')) $error = 'Please enter your school.';
        if (!$error && !$f('position')) $error = 'Please enter your current position.';
        if (!$error && !$f('collaboration_desc')) $error = 'Please describe your collaboration.';
        if (!$error && !$f('goals')) $error = 'Please describe your goals.';

        if (!$error) {
            $values = [
                'applicant_name'      => $f('name'),
                'school'              => $f('school'),
                'position'            => $f('position'),
                'years_in_role'       => $f('years_in_role'),
                'has_collaborator'    => ($f('has_collaborator') === 'yes'),
                'collaborator_name'   => $f('collaborator_name'),
                'collaborator_school' => $f('collaborator_school'),
                'needs_partner'       => isset($_POST['needs_partner']),
                'collaboration_desc'  => $f('collaboration_desc'),
                'goals'               => $f('goals'),
                'proposed_dates'      => json_encode($dates),
                'days_requested'      => count($dates),
            ];
            // proposed_dates is JSON rather than prose, so it is compared and
            // recorded on its own terms by cgApplyEdit like any other column.
            try {
                $res = cgApplyEdit((int)$app['id'], $values, $actor, $isAdmin);
                $app = cgGetApplication((int)$app['id']);
                if (!$res['changed']) {
                    $notice = 'Nothing had changed, so nothing was saved.';
                } else {
                    $justSaved = $res['changed'];
                    $notice = 'Your changes are saved.';
                    if ($isAdmin) {
                        if (!empty($_POST['tell_applicant'])) {
                            cgSendApplicantEditNotice($app, $res['changed']);
                            $notice = 'Saved, and the applicant has been emailed what changed.';
                        } else {
                            $notice = 'Saved. The applicant was not emailed.';
                        }
                    } elseif ($app['status'] === 'approved' && $res['material']) {
                        cgSendEditNotification($app, $res['changed'], $actor);
                        $notice = 'Your changes are saved. Your grant is still approved — '
                                . 'the BVTU president has been told what changed.';
                    } else {
                        cgSendEditNotification($app, $res['changed'], $actor);
                    }
                }
            } catch (Exception $e) {
                $error = 'Something went wrong saving your changes. '
                       . 'Please email lp54@bctf.ca and we will fix it by hand.';
            }
        }
    }
    // Re-evaluate: an edit can move a grant into or out of the locked state.
    if ($app) {
        $locked  = cgIsLockedToApplicant($app);
        $canEdit = $isAdmin || (!$locked && $app['status'] !== 'declined');
    }
}

$dates = [];
if ($app) {
    $d = json_decode($app['proposed_dates'] ?? '[]', true);
    $dates = is_array($d) ? array_values($d) : [];
}
$history = $app ? cgEditHistory((int)$app['id']) : [];

// Their standing for the year, so "can I add a day?" has an answer on the page
// they'd add it on. Only ever their own — this page is reached with their token
// or their login, never by typing someone else's address.
$standing = $app ? cgPersonDays((int)$app['school_year'], $app['applicant_email'], $app['applicant_name']) : null;

$statusLabel = [
    'pending'    => ['Pending review', '#fffbeb', '#fde68a', '#92400e'],
    'approved'   => ['Approved',       '#f0f9f3', '#b3d9bf', '#1a5c2e'],
    'declined'   => ['Declined',       '#fef2f2', '#fecaca', '#991b1b'],
    'waitlisted' => ['Waitlisted',     '#eff6ff', '#bfdbfe', '#1e40af'],
];
$st = $statusLabel[$app['status'] ?? 'pending'] ?? $statusLabel['pending'];

/** The form keeps what was typed when validation fails, not the stored row. */
function cgOld(string $key, $fallback) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return $fallback;
    return $_POST[$key] ?? $fallback;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Change Your Collaboration Grant — BVTU</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .cge-wrap { max-width: 760px; margin: 0 auto; padding: calc(var(--hdr-h) + 2.5rem) 1.5rem 4rem; }
    .cge-title { font-size: 1.7rem; font-weight: 800; color: var(--primary); margin-bottom: .3rem; }
    .cge-sub { font-size: .94rem; color: var(--gray-500); margin-bottom: 1.75rem; }

    .pcard { background: var(--white); border: 1.5px solid var(--border); border-radius: 12px;
             padding: 1.5rem; margin-bottom: 1.5rem; }

    .cge-status { display: inline-block; padding: .3rem .85rem; border-radius: 999px;
                  font-size: .8rem; font-weight: 700; border: 1.5px solid; }

    .cge-msg { border-radius: 10px; padding: .9rem 1.1rem; margin-bottom: 1.5rem;
               font-size: .92rem; line-height: 1.6; border: 1.5px solid; }
    .cge-msg.ok  { background: #f0f9f3; border-color: #b3d9bf; color: #1a5c2e; }
    .cge-msg.bad { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .cge-msg.note{ background: #fffbeb; border-color: #fde68a; color: #78350f; }

    .cge-group { margin-bottom: 1.15rem; }
    .cge-group label { display: block; font-size: .88rem; font-weight: 600;
                       color: var(--gray-700); margin-bottom: .35rem; }
    .cge-group .hint { display: block; font-weight: 400; font-size: .82rem;
                       color: var(--gray-500); margin-top: .15rem; }
    .cge-group input[type=text], .cge-group input[type=email], .cge-group input[type=date],
    .cge-group select, .cge-group textarea {
      width: 100%; padding: .6rem .75rem; border: 1.5px solid var(--border);
      border-radius: 8px; font: inherit; font-size: .94rem; background: var(--white);
      color: var(--gray-800);
    }
    .cge-group input:focus, .cge-group select:focus, .cge-group textarea:focus {
      outline: none; border-color: var(--primary);
    }
    .cge-group input[readonly] { background: var(--off-white); color: var(--gray-500); }
    .cge-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .cge-dates { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; }
    .cge-dates .n { font-size: .78rem; color: var(--gray-500); font-weight: 600; }
    .cge-inline { display: flex; gap: 1.25rem; flex-wrap: wrap; font-weight: 400; }
    .cge-inline label { display: flex; align-items: center; gap: .4rem; font-weight: 400;
                        margin: 0; cursor: pointer; }
    .cge-section { font-size: .78rem; font-weight: 800; letter-spacing: .06em;
                   text-transform: uppercase; color: var(--primary); margin: 1.75rem 0 .9rem; }

    .cge-read dt { font-size: .78rem; font-weight: 700; color: var(--gray-500);
                   text-transform: uppercase; letter-spacing: .04em; margin-top: 1rem; }
    .cge-read dd { margin: .2rem 0 0; font-size: .95rem; color: var(--gray-800);
                   white-space: pre-wrap; }

    .cge-hist { list-style: none; padding: 0; margin: 0; font-size: .88rem; }
    .cge-hist li { padding: .7rem 0; border-top: 1px solid var(--border); color: var(--gray-700); }
    .cge-hist li:first-child { border-top: none; }
    .cge-hist .who { font-weight: 600; color: var(--gray-800); }
    .cge-hist .chg { color: var(--gray-500); margin-top: .2rem; }

    @media (max-width: 620px) {
      .cge-row, .cge-dates { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <header class="site-header">
    <div class="header-inner container">
      <a href="index.php" class="logo">
        <img src="bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <button class="search-btn" data-search-open aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav">
        <ul>
          
          <li class="has-dropdown">
            <a href="documents.php">Documents</a>
            <ul class="dropdown">
              <li><a href="documents.php">All Documents</a></li>
              <li><a href="collective-agreement.php">Collective Agreement</a></li>
            </ul>
          </li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php" class="active">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main class="cge-wrap">

<?php if ($denied): ?>
    <h1 class="cge-title">We couldn't open that application</h1>
    <p class="cge-sub">The link may have expired, or it may belong to a different
      email address than the one you're signed in with.</p>
    <div class="pcard">
      <p style="margin:0 0 1rem;font-size:.95rem;line-height:1.7;">
        Use the "Change your application" link in the confirmation email we sent
        when you applied. If you can't find it, email
        <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a> and we'll send you a new one.
      </p>
      <a href="collab-grant.php" class="btn btn-primary">Back to the Collaboration Grant</a>
    </div>

<?php else: ?>

    <h1 class="cge-title">Your Collaboration Grant</h1>
    <p class="cge-sub">
      Submitted <?= date('F j, Y', strtotime($app['submitted_at'])) ?>
      for <?= (int)$app['school_year'] ?>–<?= ((int)$app['school_year'] + 1) % 100 ?>
      · <span class="cge-status" style="background:<?= $st[1] ?>;border-color:<?= $st[2] ?>;color:<?= $st[3] ?>;"><?= $st[0] ?></span>
    </p>

  <?php if ($notice): ?>
    <div class="cge-msg ok">
      <?= htmlspecialchars($notice) ?>
      <?php if ($justSaved): ?>
        <div style="margin-top:.4rem;font-size:.88rem;opacity:.85;"><?= htmlspecialchars(cgDescribeChanges($justSaved)) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="cge-msg bad"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if (!empty($app['changed_after_ok'])): ?>
    <div class="cge-msg note">
      This application was changed after it was approved. The grant still stands —
      the BVTU president has the details.
    </div>
  <?php endif; ?>

  <?php if (!$canEdit): ?>
    <div class="cge-msg note">
      <?php if ($declined): ?>
        This application was declined, so it can no longer be changed. You're very
        welcome to <a href="collab-grant.php">submit a new application</a>.
      <?php else: ?>
        Your release days are now booked with the district, so this application is
        locked — it has to match the invoice the district sends us. If something
        needs to change, email <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a> and
        we'll sort it out with you.
      <?php endif; ?>
    </div>
  <?php elseif ($isAdmin && ($locked || $declined)): ?>
    <div class="cge-msg note">
      <?= $declined ? 'This application is declined' : 'Release days are booked with the district' ?>,
      so the applicant can't change it — you still can.
    </div>
  <?php elseif ($app['status'] === 'approved'): ?>
    <div class="cge-msg note">
      Your grant is already approved. You can still change it — the approval stands,
      and we'll be told what moved so the booking stays right.
    </div>
  <?php endif; ?>

  <?php if ($canEdit): ?>
    <form method="post" class="pcard" novalidate>
      <?= csrfField() ?>
      <input type="hidden" name="cg_save" value="1">
      <?php if ($token !== ''): ?>
        <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
      <?php else: ?>
        <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
      <?php endif; ?>

      <div class="cge-section" style="margin-top:0;">About you</div>

      <div class="cge-row">
        <div class="cge-group">
          <label for="e-name">Your name</label>
          <input type="text" id="e-name" name="name"
                 value="<?= htmlspecialchars(cgOld('name', $app['applicant_name'])) ?>">
        </div>
        <div class="cge-group">
          <label for="e-email">Email</label>
          <input type="email" id="e-email" value="<?= htmlspecialchars($app['applicant_email']) ?>" readonly>
          <span class="hint">We use this to identify your application. Email
            <a href="mailto:lp54@bctf.ca">lp54@bctf.ca</a> to change it.</span>
        </div>
      </div>

      <div class="cge-row">
        <div class="cge-group">
          <label for="e-school">School</label>
          <input type="text" id="e-school" name="school"
                 value="<?= htmlspecialchars(cgOld('school', $app['school'])) ?>">
        </div>
        <div class="cge-group">
          <label for="e-position">Current position</label>
          <input type="text" id="e-position" name="position"
                 value="<?= htmlspecialchars(cgOld('position', $app['position'])) ?>">
        </div>
      </div>

      <div class="cge-group" style="max-width:320px;">
        <label for="e-years">How long have you been in this role?</label>
        <?php $yrs = cgOld('years_in_role', $app['years_in_role']); ?>
        <select id="e-years" name="years_in_role">
          <option value=""            <?= $yrs === ''            ? 'selected' : '' ?>>Select…</option>
          <option value="First year"  <?= $yrs === 'First year'  ? 'selected' : '' ?>>This is my first year</option>
          <option value="Second year" <?= $yrs === 'Second year' ? 'selected' : '' ?>>This is my second year</option>
          <option value="3+ years"    <?= $yrs === '3+ years'    ? 'selected' : '' ?>>Three or more years</option>
        </select>
      </div>

      <div class="cge-section">Your collaboration</div>

      <?php $hasCollab = (string)cgOld('has_collaborator', !empty($app['has_collaborator']) ? 'yes' : 'no'); ?>
      <div class="cge-group">
        <label>Do you have a collaborator in mind?</label>
        <div class="cge-inline">
          <label><input type="radio" name="has_collaborator" value="yes" <?= $hasCollab === 'yes' ? 'checked' : '' ?>> Yes, I have someone in mind</label>
          <label><input type="radio" name="has_collaborator" value="no"  <?= $hasCollab !== 'yes' ? 'checked' : '' ?>> Not yet</label>
        </div>
      </div>

      <div class="cge-row">
        <div class="cge-group">
          <label for="e-cname">Collaborator's name</label>
          <input type="text" id="e-cname" name="collaborator_name"
                 value="<?= htmlspecialchars(cgOld('collaborator_name', $app['collaborator_name'])) ?>">
        </div>
        <div class="cge-group">
          <label for="e-cschool">Collaborator's school</label>
          <input type="text" id="e-cschool" name="collaborator_school"
                 value="<?= htmlspecialchars(cgOld('collaborator_school', $app['collaborator_school'])) ?>">
        </div>
      </div>

      <div class="cge-group">
        <label class="cge-inline" style="font-weight:400;">
          <input type="checkbox" name="needs_partner" value="1"
            <?= ($_SERVER['REQUEST_METHOD'] === 'POST'
                   ? isset($_POST['needs_partner'])
                   : !empty($app['needs_partner'])) ? 'checked' : '' ?>>
          Please help me find a collaborator
        </label>
      </div>

      <div class="cge-group">
        <label for="e-desc">Describe your collaboration</label>
        <textarea id="e-desc" name="collaboration_desc" rows="5"><?= htmlspecialchars(cgOld('collaboration_desc', $app['collaboration_desc'])) ?></textarea>
      </div>

      <div class="cge-group">
        <label for="e-goals">Your goals</label>
        <textarea id="e-goals" name="goals" rows="5"><?= htmlspecialchars(cgOld('goals', $app['goals'])) ?></textarea>
      </div>

      <div class="cge-section">Release days</div>

      <?php if ($standing): ?>
        <p style="font-size:.88rem;color:var(--gray-600);background:var(--off-white);
                  border:1px solid var(--border);border-radius:8px;padding:.7rem .85rem;
                  margin:0 0 1rem;line-height:1.7;">
          <?php if (empty($standing['capped'])): ?>
            You've taken part in <strong><?= (int)$standing['used'] ?>
            <?= (int)$standing['used'] === 1 ? 'day' : 'days' ?></strong> of collaboration this
            school year<?= $standing['pending'] ? ', counting ' . (int)$standing['pending'] . ' still awaiting a decision' : '' ?>.
            The <?= CG_DAY_CAP ?>-day yearly limit doesn't apply to you — though any one
            application still covers up to <?= CG_DAY_CAP ?> days.
          <?php else: ?>
            You've used <strong><?= (int)$standing['used'] ?> of your <?= CG_DAY_CAP ?> days</strong>
            this school year<?= $standing['pending'] ? ', counting ' . (int)$standing['pending'] . ' still awaiting a decision' : '' ?>.
            <?php if ($standing['left'] > 0): ?>
              You have <?= (int)$standing['left'] ?> <?= (int)$standing['left'] === 1 ? 'day' : 'days' ?> left.
            <?php else: ?>
              Adding another day would put you over the limit — you can still ask, and
              the BVTU Executive will decide.
            <?php endif; ?>
          <?php endif; ?>
          <?php if (trim((string)$app['collaborator_name']) !== '' && !empty($app['has_collaborator'])): ?>
            Days on this grant count for both you and
            <?= htmlspecialchars($app['collaborator_name']) ?>, since you're both
            released on the same days.
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <div class="cge-group">
        <label>Your release day(s)
          <span class="hint">Up to three weekdays. Leave a box empty to drop that day,
            or fill an empty one to add a day. Days you've already taken stay as they are.</span>
        </label>
        <div class="cge-dates">
          <?php foreach ([0, 1, 2] as $i): ?>
            <div>
              <div class="n">Day <?= $i + 1 ?></div>
              <input type="date" name="date<?= $i ?>"
                     value="<?= htmlspecialchars((string)cgOld('date' . $i, $dates[$i] ?? '')) ?>">
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($isAdmin): ?>
        <label class="cge-inline" style="margin-top:1.25rem;font-weight:400;font-size:.9rem;">
          <input type="checkbox" name="tell_applicant" value="1" checked>
          Email <?= htmlspecialchars($app['applicant_name']) ?> what changed
        </label>
      <?php endif; ?>

      <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin-top:1.75rem;">
        <button type="submit" class="btn btn-primary" style="padding:.65rem 1.5rem;">Save changes</button>
        <?php if ($isAdmin): ?>
          <a href="members/collab-grant-admin.php" style="font-size:.88rem;">Back to the review panel</a>
        <?php endif; ?>
      </div>
    </form>
  <?php else: ?>
    <div class="pcard cge-read">
      <dl style="margin:0;">
        <dt>Name</dt><dd><?= htmlspecialchars($app['applicant_name']) ?></dd>
        <dt>School</dt><dd><?= htmlspecialchars($app['school']) ?></dd>
        <dt>Position</dt><dd><?= htmlspecialchars($app['position']) ?></dd>
        <?php if ($app['collaborator_name']): ?>
          <dt>Collaborator</dt>
          <dd><?= htmlspecialchars($app['collaborator_name']) ?><?= $app['collaborator_school'] ? ' — ' . htmlspecialchars($app['collaborator_school']) : '' ?></dd>
        <?php endif; ?>
        <dt>Release days</dt>
        <dd><?= $dates ? htmlspecialchars(implode(' · ', array_map(fn($d) => date('D M j, Y', strtotime($d)), $dates))) : '—' ?></dd>
        <dt>Your collaboration</dt><dd><?= htmlspecialchars($app['collaboration_desc']) ?></dd>
        <dt>Your goals</dt><dd><?= htmlspecialchars($app['goals']) ?></dd>
      </dl>
    </div>
  <?php endif; ?>

  <?php if ($history): ?>
    <div class="pcard">
      <div class="cge-section" style="margin-top:0;">What's changed</div>
      <ul class="cge-hist">
        <?php foreach ($history as $h): ?>
          <li>
            <span class="who"><?= $h['by_admin'] ? 'BVTU' : htmlspecialchars($h['changed_by']) ?></span>
            — <?= date('M j, Y \a\t g:ia', strtotime($h['changed_at'])) ?>
            <div class="chg"><?= htmlspecialchars(cgDescribeChanges(json_decode($h['changes'] ?? '[]', true) ?: [])) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

<?php endif; ?>

  </main>

  <footer class="site-footer">
    <div class="footer-grid container">
      <div>
        <h4>Bulkley Valley Teachers' Union</h4>
        <p>Local of the BC Teachers' Federation<br>School District 54 — Smithers, BC</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <ul class="footer-nav-list">
          
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Member Resources</a></li>
          <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
          <li><a href="prod.php">PRO-D</a></li>
        </ul>
      </div>
      <div>
        <h4>Resources</h4>
        <ul class="footer-nav-list">
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="members/login.php">Member Login</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Smithers, BC · <a href="privacy.php" style="color:inherit;text-decoration:underline;">Privacy</a></p>
      </div>
    </div>
  </footer>

  <script src="js/site.js?v=<?= @filemtime(__DIR__ . '/js/site.js') ?>"></script>
</body>
</html>
