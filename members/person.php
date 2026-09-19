<?php
/**
 * person.php — the record for one person: read first, edit as a mode.
 *
 * Before this, opening someone dropped you straight into a form, and the form
 * knew only what had been typed into it. None of what the union actually holds
 * about a person — their executive roles, their expense claims, the invitations
 * sent to them, their portal access — appeared anywhere. All of it already
 * existed in exec_roles, exp_batches, lp_vouchers and member_invitations; none
 * of it was ever read back.
 *
 * Editing still lives in contact-edit.php. This page does not write.
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

$id = (int)($_GET['id'] ?? 0);
$c  = $id ? contactGet($id) : null;
if (!$c) { header('Location: people.php?error=' . urlencode('Person not found.')); exit; }

$email    = $c['email'];
$accounts = peopleAccountsByEmail();
$acct     = $accounts[$c['email_normalized']] ?? null;
$invites  = personInvites($email);
$invite   = $invites ? $invites[count($invites) - 1] : null;
$state    = peopleInviteState($acct, $invite);

$roles    = personRoles($email);
$claims   = personClaims($email);
$vouchers = personVouchers($email);
$timeline = personTimeline($id, $email);

$schoolName = '';
foreach (contactSchools() as $s) {
    if ((int)$s['id'] === (int)$c['school_id']) $schoolName = $s['name'];
}
if (!$schoolName) $schoolName = $c['school_other'];

list($mcLabel, $mcTone, $mcDetail) = contactMcLabel($c);

function pv(string $v): string { return htmlspecialchars($v); }
function when($v, string $fmt = 'M j, Y'): string {
    return $v ? date($fmt, strtotime($v)) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= pv(contactDisplayName($c)) ?> — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:960px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }

    .rec-head { background:#fff;border:1px solid var(--gray-200);border-radius:12px;
            padding:1.25rem 1.4rem;margin:.6rem 0 1.2rem; }
    .rec-head .top { display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:flex-start; }
    .rec-head h1 { font-size:1.5rem;font-weight:800;color:var(--gray-800);margin:0;line-height:1.2; }
    .rec-head .sub { font-size:.9rem;color:var(--gray-500);margin-top:.25rem; }
    .rec-head .sub a { color:var(--primary);text-decoration:none; }
    .facts { display:flex;gap:1.6rem;flex-wrap:wrap;margin-top:1rem;
             padding-top:1rem;border-top:1px solid var(--gray-100); }
    .fact .k { font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;
               color:var(--gray-400);font-weight:700; }
    .fact .v { font-size:.9rem;color:var(--gray-800);margin-top:.15rem; }

    .badge { display:inline-block;padding:.15rem .55rem;border-radius:100px;
             font-size:.74rem;font-weight:700; }
    .b-green{background:#f0fdf4;color:#166534;} .b-red{background:#fef2f2;color:#991b1b;}
    .b-amber{background:#fffbeb;color:#b45309;} .b-blue{background:#eff6ff;color:#1e40af;}
    .b-slate{background:#f1f5f9;color:#475569;} .b-grey{background:#f8fafc;color:#94a3b8;}

    .cols { display:flex;gap:1.2rem;align-items:flex-start;flex-wrap:wrap; }
    .col { flex:1;min-width:290px; }
    .card { background:#fff;border:1px solid var(--gray-200);border-radius:12px;
            padding:1.1rem 1.25rem;margin-bottom:1rem; }
    .card h2 { font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;
               color:var(--gray-500);margin:0 0 .75rem; }
    .kv { display:flex;justify-content:space-between;gap:1rem;font-size:.87rem;
          padding:.35rem 0;border-bottom:1px solid var(--gray-100); }
    .kv:last-child { border-bottom:none; }
    .kv .k { color:var(--gray-500); }
    .kv .v { color:var(--gray-800);text-align:right; }
    .empty { font-size:.85rem;color:var(--gray-400);font-style:italic;margin:0; }
    .act-btn { background:none;border:1px solid var(--gray-200);border-radius:6px;padding:.3rem .7rem;
               font-size:.8rem;color:var(--gray-600);text-decoration:none;display:inline-block;
               cursor:pointer;font-family:inherit; }
    .act-btn:hover { border-color:var(--primary);color:var(--primary); }
    .act-btn.primary { background:var(--primary);border-color:var(--primary);color:#fff;font-weight:700; }
    .rowlist a { color:var(--primary);text-decoration:none;font-weight:600; }
    .rowlist div.item { font-size:.86rem;padding:.4rem 0;border-bottom:1px solid var(--gray-100); }
    .rowlist div.item:last-child { border-bottom:none; }
    .rowlist .meta { color:var(--gray-400);font-size:.78rem; }

    .tl { list-style:none;margin:0;padding:0; }
    .tl li { position:relative;padding:0 0 .85rem 1.1rem;font-size:.85rem;color:var(--gray-700); }
    .tl li::before { content:'';position:absolute;left:0;top:.42rem;width:7px;height:7px;
                     border-radius:50%;background:var(--gray-300); }
    .tl li.k-invite::before { background:#60a5fa; }
    .tl li.k-claim::before  { background:#34d399; }
    .tl li.k-role::before   { background:#fbbf24; }
    .tl .when { display:block;font-size:.76rem;color:var(--gray-400);margin-top:.1rem; }
  </style>
</head>
<body>
<div class="wrap">

  <a class="back-link" href="people.php">&#x2190; People</a>

  <?php if ($notice): ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#166534;margin-top:.8rem;">&#x2713; <?= $notice ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#991b1b;margin-top:.8rem;">&#x26A0; <?= $error ?></div>
  <?php endif; ?>

  <div class="rec-head">
    <div class="top">
      <div>
        <h1><?= pv(contactDisplayName($c)) ?></h1>
        <div class="sub">
          <a href="mailto:<?= pv($c['email']) ?>"><?= pv($c['email']) ?></a>
          <?php if ($c['phone']): ?> &middot; <?= pv($c['phone']) ?><?php endif; ?>
        </div>
        <?php if ($c['status'] === 'archived'): ?>
          <div style="margin-top:.5rem;">
            <span class="badge b-slate">Archived</span>
            <span style="font-size:.8rem;color:var(--gray-400);">
              Hidden from the list, exports and Mailchimp sync. Their login is unaffected.</span>
          </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
        <a class="act-btn primary" href="contact-edit.php?id=<?= (int)$c['id'] ?>">Edit</a>
        <a class="act-btn" href="people.php">Back to list</a>
      </div>
    </div>

    <div class="facts">
      <div class="fact"><div class="k">School</div>
        <div class="v"><?= $schoolName ? pv($schoolName) : '—' ?></div></div>
      <div class="fact"><div class="k">Position</div>
        <div class="v"><?= $c['position'] ? pv($c['position']) : '—' ?></div></div>
      <div class="fact"><div class="k">Status</div>
        <div class="v"><?= pv(CONTACT_STATUSES[$c['status']] ?? $c['status']) ?></div></div>
      <div class="fact"><div class="k">Portal</div>
        <div class="v"><?= pv(PEOPLE_INVITE_LABELS[$state] ?? $state) ?></div></div>
      <div class="fact"><div class="k">Mailchimp</div>
        <div class="v" title="<?= pv($mcDetail) ?>"><?= pv($mcLabel) ?></div></div>
    </div>
  </div>

  <div class="cols">
    <div class="col">

      <div class="card">
        <h2>Portal access</h2>
        <?php if ($acct): ?>
          <div class="kv"><span class="k">Account</span>
            <span class="v"><?= (int)$acct['active']
              ? '<span class="badge b-green">Active</span>'
              : '<span class="badge b-red">Deactivated</span>' ?></span></div>
          <div class="kv"><span class="k">Name on account</span>
            <span class="v"><?= pv($acct['name']) ?></span></div>
          <div class="kv"><span class="k">Created</span>
            <span class="v"><?= when($acct['created_at']) ?></span></div>
          <?php if (!empty($acct['must_change_password'])): ?>
          <div class="kv"><span class="k">Password</span>
            <span class="v">Must be changed at next login</span></div>
          <?php endif; ?>
        <?php else: ?>
          <p class="empty" style="margin-bottom:.6rem;">No login account.</p>
        <?php endif; ?>

        <?php if ($invites): ?>
          <div style="margin-top:.7rem;padding-top:.7rem;border-top:1px solid var(--gray-100);">
            <div style="font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;
                        color:var(--gray-400);font-weight:700;margin-bottom:.4rem;">Invitations</div>
            <div class="rowlist">
              <?php foreach (array_reverse($invites) as $i): ?>
              <div class="item">
                <?= $i['sent_at'] ? 'Emailed ' . when($i['sent_at']) : 'Added, never emailed' ?>
                <span class="meta">
                  <?php if ($i['accepted_at']): ?>
                    &middot; registered <?= when($i['accepted_at']) ?>
                  <?php elseif ($i['sent_at'] && strtotime($i['expires_at']) < time()): ?>
                    &middot; expired <?= when($i['expires_at']) ?>
                  <?php elseif ($i['sent_at']): ?>
                    &middot; valid until <?= when($i['expires_at']) ?>
                  <?php endif; ?>
                </span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Executive roles</h2>
        <?php if (!$roles): ?>
          <p class="empty">Holds no executive position.</p>
        <?php else: ?>
          <div class="rowlist">
            <?php foreach ($roles as $r): ?>
            <div class="item">
              <strong><?= pv(EXEC_ROLES[$r['role']] ?? $r['role']) ?></strong>
              <span class="meta">since <?= when($r['created_at']) ?><?php
                if ($r['assigned_by']): ?> &middot; assigned by <?= pv($r['assigned_by']) ?><?php endif; ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <p style="margin:.7rem 0 0;">
            <a class="act-btn" href="roles-overview.php">Roles &amp; Directory</a></p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>Mailchimp</h2>
        <div class="kv"><span class="k">Status</span>
          <span class="v"><span class="badge b-<?= pv($mcTone) ?>"><?= pv($mcLabel) ?></span></span></div>
        <div class="kv"><span class="k">Last checked</span>
          <span class="v"><?= when($c['mailchimp_last_synced_at']) ?></span></div>
        <?php if ($c['mailchimp_sync_status'] === 'error'): ?>
        <div class="kv"><span class="k">Last sync</span>
          <span class="v" style="color:#b45309;"><?= pv($c['mailchimp_sync_error'] ?: 'Failed') ?></span></div>
        <?php endif; ?>
        <p style="font-size:.78rem;color:var(--gray-400);margin:.6rem 0 0;line-height:1.6;">
          Subscription state belongs to Mailchimp. Nothing on this page changes it.
        </p>
      </div>

    </div>
    <div class="col">

      <div class="card">
        <h2>Expense claims</h2>
        <?php if (!$claims): ?>
          <p class="empty">No claims on record.</p>
        <?php else: ?>
          <div class="rowlist">
            <?php foreach ($claims as $b): ?>
            <div class="item">
              <a href="exp-claim-view.php?id=<?= (int)$b['id'] ?>"><?= pv($b['ref_code'] ?: '#' . $b['id']) ?></a>
              <?= $b['title'] ? ' — ' . pv($b['title']) : '' ?>
              <span class="meta">
                $<?= number_format((float)$b['total'], 2) ?> &middot;
                <?= pv(str_replace('_', ' ', $b['status'])) ?> &middot;
                <?= when($b['created_at']) ?>
                <?php if (strcasecmp($b['user_email'], $email) !== 0): ?>
                  &middot; submitted on behalf of someone else
                <?php endif; ?>
              </span>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($vouchers): ?>
      <div class="card">
        <h2>President's expense vouchers</h2>
        <div class="rowlist">
          <?php foreach ($vouchers as $v): ?>
          <div class="item">
            <a href="lp-voucher-view.php?id=<?= (int)$v['id'] ?>">
              <?= pv($v['voucher_number'] ?: '#' . $v['id']) ?></a>
            <?= $v['name'] ? ' — ' . pv($v['name']) : '' ?>
            <span class="meta">
              <?= pv(str_replace('_', ' ', $v['status'])) ?> &middot; <?= when($v['created_at']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="card">
        <h2>Timeline</h2>
        <?php if (!$timeline): ?>
          <p class="empty">Nothing recorded yet.</p>
        <?php else: ?>
          <ul class="tl">
            <?php foreach ($timeline as $t): ?>
            <li class="k-<?= pv($t['kind']) ?>">
              <?= pv($t['what']) ?>
              <span class="when"><?= date('M j, Y', $t['at']) ?><?php
                if ($t['who']): ?> &middot; <?= pv($t['who']) ?><?php endif; ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <p style="font-size:.78rem;color:var(--gray-400);margin:.6rem 0 0;line-height:1.6;">
          Drawn from the contact log, invitations, claims and role assignments.
        </p>
      </div>

    </div>
  </div>

</div>
</body>
</html>
