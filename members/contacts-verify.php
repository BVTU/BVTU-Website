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
/* For cgNameKey()/cgNearestPerson(): the same name matching the collaboration
 * grant uses to catch "Mcyntire" for McIntyre. Borrowed rather than rewritten,
 * because two sets of rules for comparing a teacher's name is how the two
 * start disagreeing about who somebody is. */
require_once __DIR__ . '/collab-grant-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

sendPrivateHeaders();
contactsEnsureTables();

$notice = (string)($_GET['notice'] ?? '');
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

/* Archived contacts are read too, and the extra addresses with them.
 *
 * Both were missing, and both made the comparison accuse itself of gaps that
 * were not there:
 *
 *   - Only contacts.email was matched. A contact who is in the audience under
 *     one of their OTHER addresses appeared twice over — once as "in Mailchimp
 *     but not in the portal" under that address, and once as "in the portal but
 *     not in Mailchimp" under their main one. One person, two false findings.
 *   - Archived contacts were excluded from the portal side entirely, so anyone
 *     who has left and is still in the audience read as missing from the
 *     portal, where they are plainly on file.
 */
$contacts = $allContacts = [];
if ($ran && !$err) {
    $allContacts = getDB()->query(
        "SELECT id, first_name, last_name, preferred_name, email, email_normalized,
                status, mailchimp_status, mailchimp_last_synced_at
         FROM contacts ORDER BY last_name, first_name"
    )->fetchAll();
    $contacts = array_values(array_filter($allContacts,
        fn($c) => (string)$c['status'] !== 'archived'));
}

/**
 * Which portal contact an audience entry is probably the same person as.
 *
 * A SUGGESTION, never a match. Mailchimp's identity is the address itself —
 * the subscriber key is md5 of it — so pairing two records by name would let
 * "Apply statuses" write one person's consent onto another. This only offers
 * the admin a name to look at, and the only thing the button does is add the
 * address to that contact, which is the fix in every case it gets right.
 *
 * Exact name first, because that is the common case here: the same teacher,
 * in the audience under an address the portal does not hold. cgNearestPerson()
 * deliberately returns nothing on an exact match, it being a typo-catcher.
 *
 * Nothing is suggested for an ambiguous name, one belonging to somebody who
 * has left, or a blank one — Mailchimp's FNAME/LNAME are often empty.
 */
function verifySuggestContact(string $audienceName): ?array {
    $name = trim($audienceName);
    if ($name === '') return null;

    $byName = cgContactsByName();
    $key    = cgNameKey($name);
    $hit    = null;

    if ($key !== '' && isset($byName[$key])) {
        if (!empty($byName[$key]['ambiguous']) || !empty($byName[$key]['archived'])) return null;
        $hit = $byName[$key] + ['exact' => true];
    } else {
        $near = cgNearestPerson($name);
        if ($near) $hit = $near + ['exact' => false];
    }
    if (!$hit) return null;

    $owner = contactEmailOwner((string)$hit['email']);
    if (!$owner) return null;
    return ['id' => $owner['id'], 'name' => $hit['name'], 'exact' => !empty($hit['exact'])];
}

// Three buckets: agreed, disagreed, and present on only one side.
$agree = $differ = $portalOnly = [];
$mcOnly = [];

if ($ran && !$err) {
    // Every address every contact holds, archived ones included, so an address
    // in the audience is looked for against all of them rather than only the
    // one that happens to be their main.
    $extra = contactEmailsFor(array_map(fn($c) => (int)$c['id'], $allContacts));
    $seen  = [];
    $addrs = [];
    foreach ($allContacts as $c) {
        $list = [contactNormalizeEmail((string)$c['email'])];
        foreach ($extra[(int)$c['id']] ?? [] as $x) $list[] = contactNormalizeEmail($x);
        foreach (array_unique(array_filter($list)) as $a) {
            $addrs[(int)$c['id']][] = $a;
            $seen[$a] = true;
        }
    }

    foreach ($contacts as $c) {
        // Whichever of their addresses the audience knows them by. Their main
        // one first, so a contact who is in Mailchimp under both is compared
        // against the address the portal would push to.
        $hit = null;
        foreach ($addrs[(int)$c['id']] ?? [] as $a) {
            if (isset($audience[$a])) { $hit = $a; break; }
        }
        if ($hit === null) { $portalOnly[] = $c; continue; }

        // Said on the row rather than left to be puzzled over: this contact is
        // in the audience under an address that is not their main one, which
        // is worth knowing before anybody edits either side.
        if ($hit !== contactNormalizeEmail((string)$c['email'])) $c['_as'] = $hit;

        $live   = $audience[$hit]['status'];
        $stored = $c['mailchimp_status'];
        if ($live === $stored) { $agree[] = $c; }
        else { $c['_live'] = $live; $differ[] = $c; }
    }
    foreach ($audience as $e => $m) {
        if (!isset($seen[$e])) $mcOnly[] = ['email' => $e] + $m;
    }
}

/* Put an audience address onto the contact it belongs to.
 *
 * The other half of the suggestion. Importing would make a second record for
 * somebody already on file; this adds the address to the contact they already
 * are, which is what makes the comparison match them next time.
 *
 * The suggestion is worked out again here rather than trusted from the form:
 * the posted id decides which contact gains an address, and an address is how
 * union mail gets routed.
 */
if (($_POST['action'] ?? '') === 'link_mc_address' && !$err) {
    /* id and address in one value: these buttons live inside the import form
     * and target this one through form="…", and a button carries a single
     * name/value pair. The id is only ever compared against the suggestion
     * worked out again below, so nothing rests on what was posted. */
    [$want, $addr] = array_pad(explode('|', (string)($_POST['mc_link'] ?? ''), 2), 2, '');
    $want = (int)$want;
    $addr = contactNormalizeEmail($addr);

    if (!isset($audience[$addr])) {
        $notice = 'That address is not in the audience we just read.';
    } elseif ($owner = contactEmailOwner($addr)) {
        $notice = $addr . ' is already on ' . $owner['name'] . "'s record.";
    } else {
        $sug = verifySuggestContact((string)($audience[$addr]['name'] ?? ''));
        if (!$sug || $sug['id'] !== $want) {
            $notice = 'That suggestion no longer matches. Reload and look again.';
        } else {
            $c   = contactGet($sug['id']);
            $chk = $c ? contactEmailsCheck([$addr], $sug['id'], (string)$c['email'])
                      : 'Could not find that contact.';
            if ($chk !== '') {
                $notice = $chk;
            } else {
                $r = contactEmailsSync($sug['id'],
                                       array_merge(contactEmails($sug['id']), [$addr]),
                                       $member['email']);
                $notice = $r['added']
                    ? $addr . ' added to ' . $sug['name'] . "'s contact. Run the comparison "
                      . 'again and they will match.'
                    : 'That address was not added — somebody claimed it just now.';
            }
        }
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
                                    $audience[$c['_as'] ?? $c['email_normalized']]['id'] ?? null,
                                    $member['email'], empty($c['_as']));
        $n++;
    }
    // Record that the ones that already agreed were checked just now, so the
    // list can say when it last verified them rather than implying never.
    foreach ($agree as $c) {
        contactApplyMailchimpStatus((int)$c['id'], $c['mailchimp_status'],
                                    $audience[$c['_as'] ?? $c['email_normalized']]['id'] ?? null,
                                    $member['email'], empty($c['_as']));
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
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
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

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
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
        <td class="em"><?= htmlspecialchars($c['email']) ?>
          <?php // Matched on one of their other addresses, which is worth
                // knowing before anybody edits either side. ?>
          <?php if (!empty($c['_as'])): ?>
          <div style="color:var(--gray-500);">in Mailchimp as <?= htmlspecialchars($c['_as']) ?></div>
          <?php endif; ?>
        </td>
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
  <?php // Targeted by the per-row buttons below, which sit inside the import
        // form: a form cannot be nested in another, so they reach this one by
        // id instead. ?>
  <form method="POST" id="mclink">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="link_mc_address">
  </form>
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
        <?php $sug = verifySuggestContact((string)($m['name'] ?? '')); ?>
        <tr>
          <td><input type="checkbox" class="mcpick" name="emails[]"
                     value="<?= htmlspecialchars($m['email']) ?>"></td>
          <td><?= htmlspecialchars($m['name'] ?: '—') ?>
            <?php // A name we already hold. Adding the address to that contact
                  // is the fix; importing would make a second record of them. ?>
            <?php if ($sug): ?>
            <div style="font-size:.8rem;color:var(--gray-500);margin-top:.15rem;">
              <?= $sug['exact'] ? 'Already on file as' : 'Possibly' ?>
              <strong><?= htmlspecialchars($sug['name']) ?></strong>
              <button type="submit" form="mclink" name="mc_link" data-busy="Linking…"
                      value="<?= (int)$sug['id'] ?>|<?= htmlspecialchars($m['email']) ?>"
                      style="background:none;border:0;padding:0 0 0 .3rem;font:inherit;
                             font-size:.8rem;color:var(--primary);text-decoration:underline;
                             cursor:pointer;">add this address to them</button>
            </div>
            <?php endif; ?>
          </td>
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
<script src="../js/pending.js?v=<?= @filemtime(__DIR__ . '/../js/pending.js') ?>"></script>
</body>
</html>
