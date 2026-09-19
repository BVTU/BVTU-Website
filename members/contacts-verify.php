<?php
/**
 * contacts-verify.php — compare the portal against the Mailchimp audience.
 *
 * Read-only by default. It reads both lists in full, shows exactly where they
 * disagree, and changes nothing unless you press one of the buttons. The point
 * is to be able to trust what the People page says about someone: a stored
 * status is only as good as the last time it was checked, and before this page
 * there was no way to check it.
 *
 * Why this exists: mailchimp_status defaults to 'unknown', which the list used
 * to render as "Not in Mailchimp". Someone plainly in the audience could
 * therefore appear to be missing, with no way to tell a stale record from a
 * real absence.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/contacts-db.php';
require_once __DIR__ . '/mailchimp.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

sendPrivateHeaders();
contactsEnsureTables();

$notice = htmlspecialchars($_GET['notice'] ?? '');
$ran    = false;
$err    = '';
$audience = [];

// Applying is a separate, explicit action: a comparison that silently wrote
// would be no more trustworthy than the state it set out to verify.
$apply = ($_SERVER['REQUEST_METHOD'] === 'POST') && (($_POST['action'] ?? '') === 'apply_statuses');
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfCheck();

if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_GET['run'] ?? '') === '1') {
    $res = mcFetchAudience();
    $ran = true;
    if (!$res['ok']) {
        $err = $res['error'];
    } else {
        $audience = $res['members'];
    }
}

$contacts = [];
if ($ran && !$err) {
    $contacts = getDB()->query(
        "SELECT id, first_name, last_name, preferred_name, email, email_normalized,
                status, mailchimp_status, mailchimp_last_synced_at
         FROM contacts WHERE status <> 'archived' ORDER BY last_name, first_name"
    )->fetchAll();
}

// Three buckets: agreed, disagreed, and present on only one side.
$agree = $differ = $portalOnly = [];
$mcOnly = [];

if ($ran && !$err) {
    $seen = [];
    foreach ($contacts as $c) {
        $e = $c['email_normalized'];
        $seen[$e] = true;
        if (!isset($audience[$e])) {
            $portalOnly[] = $c;
            continue;
        }
        $live   = $audience[$e]['status'];
        $stored = $c['mailchimp_status'];
        if ($live === $stored) { $agree[] = $c; }
        else { $c['_live'] = $live; $differ[] = $c; }
    }
    foreach ($audience as $e => $m) {
        if (!isset($seen[$e])) $mcOnly[] = ['email' => $e] + $m;
    }
}

// Bring selected Mailchimp-only addresses into the portal. Selected, never all:
// an address in the audience is not automatically a member, and that judgement
// is the admin's. Their live status is stored as-is, so the new record starts
// out verified rather than "not checked".
if (($_POST['action'] ?? '') === 'import_mc' && !$err) {
    $wanted = array_filter(array_map('strval', (array)($_POST['emails'] ?? [])));
    $added = $skipped = 0;
    foreach ($wanted as $raw) {
        $e = contactNormalizeEmail($raw);
        // Only addresses this comparison actually saw — never a value posted
        // in that we have not just read back from Mailchimp.
        if (!isset($audience[$e]))   { $skipped++; continue; }
        if (contactFindByEmail($e))  { $skipped++; continue; }

        list($first, $last) = contactSplitName($audience[$e]['name'] ?? '');
        $res = contactCreate([
            'email'      => $e,
            'first_name' => $first,
            'last_name'  => $last,
            // contactCreate writes every editable column, so an omitted status
            // becomes '' rather than the column default — invisible to the
            // Status filter and blank in the edit form.
            'status'     => 'active',
        ], $member['email']);

        if (!empty($res['id'])) {
            contactApplyMailchimpStatus((int)$res['id'], $audience[$e]['status'],
                                        $audience[$e]['id'] ?? null, 'verify');
            $added++;
        } else {
            $skipped++;
        }
    }
    contactAudit(null, 'import', $member['email'], '', "Added {$added} from the Mailchimp audience");
    header('Location: contacts-verify.php?run=1&notice=' . urlencode(
        "Added {$added} " . ($added === 1 ? 'person' : 'people') . ' from Mailchimp.'
        . ($skipped ? " {$skipped} skipped (already here or no longer in the audience)." : '')));
    exit;
}

// Write back only the statuses that disagree, and only on request.
if ($apply && !$err) {
    $n = 0;
    foreach ($differ as $c) {
        contactApplyMailchimpStatus((int)$c['id'], $c['_live'],
                                    $audience[$c['email_normalized']]['id'] ?? null, $member['email']);
        $n++;
    }
    // Record that the ones that already agreed were checked just now, so the
    // list can say when it last verified them rather than implying never.
    foreach ($agree as $c) {
        contactApplyMailchimpStatus((int)$c['id'], $c['mailchimp_status'],
                                    $audience[$c['email_normalized']]['id'] ?? null, $member['email']);
    }
    contactAudit(null, 'verify', $member['email'], '', "Applied {$n} status corrections from Mailchimp");
    header('Location: contacts-verify.php?run=1&notice=' . urlencode(
        $n ? "Corrected {$n} status" . ($n === 1 ? '' : 'es') . ' from Mailchimp.'
           : 'Everything already matched — timestamps refreshed.'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Verify against Mailchimp — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:1000px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .page-header h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 0; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    .notice { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#166534;margin:1rem 0; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#991b1b;margin:1rem 0; }
    .pcard { background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:1.25rem;margin-bottom:1rem; }
    h2.sec { font-size:1rem;font-weight:800;color:var(--gray-800);margin:1.8rem 0 .6rem;
             padding-bottom:.4rem;border-bottom:2px solid var(--accent); }
    .tiles { display:flex;gap:.6rem;flex-wrap:wrap;margin:1rem 0; }
    .tile { flex:1;min-width:150px;background:#fff;border:1px solid var(--gray-200);
            border-radius:10px;padding:.9rem 1rem; }
    .tile .n { font-size:1.5rem;font-weight:800;color:var(--gray-800);line-height:1; }
    .tile .l { font-size:.78rem;color:var(--gray-500);margin-top:.25rem; }
    table { width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--gray-200);
            border-radius:10px;overflow:hidden;font-size:.85rem; }
    thead tr { background:#1a2e1a; }
    th { padding:.5rem .8rem;text-align:left;font-size:.7rem;font-weight:700;color:#fff;
         text-transform:uppercase;letter-spacing:.05em; }
    td { padding:.45rem .8rem;border-bottom:1px solid var(--gray-100); }
    tr:last-child td { border-bottom:none; }
    .em { color:var(--gray-500);font-size:.82rem; }
    .act-btn { background:none;border:1px solid var(--gray-200);border-radius:6px;padding:.3rem .65rem;
               font-size:.78rem;cursor:pointer;color:var(--gray-600);text-decoration:none;
               display:inline-block;font-family:inherit; }
    .empty { font-size:.86rem;color:var(--gray-400);font-style:italic;padding:.6rem 0;margin:0; }
    .muted { font-size:.85rem;color:var(--gray-600);line-height:1.7; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="people.php">&#x2190; People</a>
    <h1>Verify against Mailchimp</h1>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($err): ?><div class="error-box">&#x26A0; <?= htmlspecialchars($err) ?></div><?php endif; ?>

  <?php if (!$ran): ?>
  <div class="pcard">
    <p class="muted" style="margin:0 0 .9rem;">
      Reads your whole Mailchimp audience and compares it with the portal, name by
      name. Nothing is written &mdash; you see the differences first and decide
      whether to apply them.
    </p>
    <a class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;text-decoration:none;"
       href="contacts-verify.php?run=1">Compare now</a>
  </div>
  <?php elseif (!$err): ?>

  <div class="tiles">
    <div class="tile"><div class="n"><?= count($audience) ?></div><div class="l">In Mailchimp</div></div>
    <div class="tile"><div class="n"><?= count($contacts) ?></div><div class="l">In the portal</div></div>
    <div class="tile"><div class="n" style="color:#166534;"><?= count($agree) ?></div><div class="l">Agree</div></div>
    <div class="tile"><div class="n" style="color:#b45309;"><?= count($differ) ?></div><div class="l">Status differs</div></div>
    <div class="tile"><div class="n" style="color:#991b1b;"><?= count($mcOnly) ?></div><div class="l">Mailchimp only</div></div>
    <div class="tile"><div class="n" style="color:#1e40af;"><?= count($portalOnly) ?></div><div class="l">Portal only</div></div>
  </div>

  <?php if ($differ): ?>
  <h2 class="sec">Status differs (<?= count($differ) ?>)</h2>
  <p class="muted" style="margin:0 0 .6rem;">
    Mailchimp is the authority on subscription status, so applying these copies
    Mailchimp's value over ours. It never changes anyone's subscription.
  </p>
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Portal says</th><th>Mailchimp says</th></tr></thead>
    <tbody>
      <?php foreach (array_slice($differ, 0, 100) as $c): ?>
      <tr>
        <td><a href="contact-edit.php?id=<?= (int)$c['id'] ?>" style="color:var(--primary);font-weight:600;">
          <?= htmlspecialchars(contactDisplayName($c)) ?></a></td>
        <td class="em"><?= htmlspecialchars($c['email']) ?></td>
        <td style="color:#991b1b;"><?= htmlspecialchars(MC_STATUSES[$c['mailchimp_status']] ?? $c['mailchimp_status']) ?></td>
        <td style="color:#166534;font-weight:700;"><?= htmlspecialchars(MC_STATUSES[$c['_live']] ?? $c['_live']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <form method="POST" style="margin:1rem 0;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="apply_statuses">
    <button class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;">
      Apply Mailchimp's statuses<?= $differ ? ' (' . count($differ) . ')' : '' ?>
    </button>
    <span class="muted" style="margin-left:.6rem;">
      Also stamps everyone that already matched as checked today.
    </span>
  </form>

  <?php if ($mcOnly): ?>
  <h2 class="sec">In Mailchimp but not in the portal (<?= count($mcOnly) ?>)</h2>
  <p class="muted" style="margin:0 0 .6rem;">
    These are the difference between your two totals. Add anyone who belongs on the
    member list through Import CSV; leave the rest &mdash; an address in your audience
    is not automatically a member.
  </p>
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="import_mc">
    <table>
      <thead><tr>
        <th style="width:2rem;"><input type="checkbox" id="allmc" onclick="mcAll(this)"></th>
        <th>Name</th><th>Email</th><th>Mailchimp status</th>
      </tr></thead>
      <tbody>
        <?php foreach (array_slice($mcOnly, 0, 200) as $m): ?>
        <tr>
          <td><input type="checkbox" class="mcpick" name="emails[]"
                     value="<?= htmlspecialchars($m['email']) ?>"></td>
          <td><?= htmlspecialchars($m['name'] ?: '—') ?></td>
          <td class="em"><?= htmlspecialchars($m['email']) ?></td>
          <td><?= htmlspecialchars(MC_STATUSES[$m['status']] ?? $m['status']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="margin:.8rem 0 0;">
      <button class="btn btn-primary" style="padding:.45rem 1rem;font-size:.88rem;"
              onclick="return mcConfirm();">Add selected to the portal</button>
      <span class="muted" style="margin-left:.6rem;">
        Creates a record with the name Mailchimp holds. Nothing is emailed, no account
        is created, and their subscription is untouched &mdash; only read.
      </span>
    </p>
  </form>
  <script>
    function mcAll(box) {
      var list = document.querySelectorAll('.mcpick');
      for (var i = 0; i < list.length; i++) list[i].checked = box.checked;
    }
    function mcConfirm() {
      var n = document.querySelectorAll('.mcpick:checked').length;
      if (!n) { alert('Tick the people you want to bring across first.'); return false; }
      return confirm('Add ' + n + ' ' + (n === 1 ? 'person' : 'people') + ' to the portal?');
    }
  </script>
  <?php endif; ?>

  <?php if ($portalOnly): ?>
  <h2 class="sec">In the portal but not in Mailchimp (<?= count($portalOnly) ?>)</h2>
  <p class="muted" style="margin:0 0 .6rem;">
    Genuinely absent from the audience &mdash; checked just now, not assumed.
    A sync from the People page will add them as transactional contacts.
  </p>
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Last checked</th></tr></thead>
    <tbody>
      <?php foreach (array_slice($portalOnly, 0, 200) as $c): ?>
      <tr>
        <td><a href="contact-edit.php?id=<?= (int)$c['id'] ?>" style="color:var(--primary);font-weight:600;">
          <?= htmlspecialchars(contactDisplayName($c)) ?></a></td>
        <td class="em"><?= htmlspecialchars($c['email']) ?></td>
        <td class="em"><?= $c['mailchimp_last_synced_at']
              ? htmlspecialchars(date('M j, Y', strtotime($c['mailchimp_last_synced_at'])))
              : 'never' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <p class="muted" style="margin-top:1.5rem;">
    <a class="act-btn" href="contacts-verify.php?run=1">Compare again</a>
  </p>

  <?php endif; ?>

</div>
</body>
</html>
