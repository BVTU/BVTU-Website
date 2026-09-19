<?php
/**
 * contact-edit.php — add or edit one contact.
 *
 * The local save and the Mailchimp push are separate steps on purpose. The
 * record is committed first; if Mailchimp then fails, the edit still stands and
 * the failure is queued for retry. An outage must never lose someone's work.
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

$id      = (int)($_GET['id'] ?? 0);
$contact = $id ? contactGet($id) : null;
if ($id && !$contact) { header('Location: people.php?error=' . urlencode('Contact not found.')); exit; }

$error  = '';
$notice = '';
$form   = $contact ?: array_fill_keys(CONTACT_EDITABLE, '');
if (!$contact) $form['status'] = 'active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'archive' && $contact) {
        contactArchive($id, $member['email'], true);
        header('Location: people.php?notice=' . urlencode('Contact archived.'));
        exit;
    }
    if ($action === 'restore' && $contact) {
        contactArchive($id, $member['email'], false);
        header('Location: contact-edit.php?id=' . $id . '&restored=1');
        exit;
    }
    if ($action === 'resubscribe' && $contact) {
        $r = mcResubscribe($id, $member['email']);
        header('Location: contact-edit.php?id=' . $id . ($r['ok']
            ? '&notice=' . urlencode('Confirmation email requested from Mailchimp.')
            : '&error=' . urlencode($r['error'])));
        exit;
    }

    // Keep what was typed so a validation error doesn't empty the form
    foreach (CONTACT_EDITABLE as $f) $form[$f] = trim((string)($_POST[$f] ?? ''));

    $res = $contact
        ? contactUpdate($id, $_POST, $member['email'])
        : contactCreate($_POST, $member['email']);

    if (!empty($res['error'])) {
        $error = $res['error'];
    } else {
        $savedId = (int)$res['id'];
        $msg = $contact ? 'Contact saved.' : 'Contact added.';

        // Push only when something Mailchimp mirrors actually changed, or the
        // contact is new. Avoids an API call on every unrelated edit.
        $mcFields = ['first_name','last_name','preferred_name','email','school_id','school_other','role'];
        $touched  = !$contact || array_intersect($mcFields, $res['changed'] ?? []);

        if ($touched && mcConfigured()) {
            $sync = mcSyncContact($savedId, $member['email']);
            $msg .= $sync['ok']
                ? ' Mailchimp updated.'
                : ' Mailchimp sync failed and can be retried — the contact is saved.';
        }
        header('Location: contact-edit.php?id=' . $savedId . '&notice=' . urlencode($msg));
        exit;
    }
}

if (!empty($_GET['notice']))   $notice = htmlspecialchars($_GET['notice']);
if (!empty($_GET['error']))    $error  = htmlspecialchars($_GET['error']);
if (!empty($_GET['restored'])) $notice = 'Contact restored.';
if ($contact && !$error) $contact = contactGet($id) ?: $contact;

$schools = contactSchools();
$history = [];
if ($contact) {
    $h = getDB()->prepare(
        "SELECT * FROM contact_audit WHERE contact_id=? ORDER BY created_at DESC LIMIT 12");
    $h->execute([$id]);
    $history = $h->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $contact ? 'Edit contact' : 'Add contact' ?> — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:760px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .page-header h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 0; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    .notice { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#166534;margin:1rem 0; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#991b1b;margin:1rem 0; }
    .card { background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:1.5rem;margin-bottom:1.25rem; }
    h2.sec { font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.07em;
             color:var(--gray-400);margin:0 0 .9rem; }
    .row { display:grid;grid-template-columns:1fr 1fr;gap:1rem; }
    @media(max-width:600px){ .row { grid-template-columns:1fr; } }
    .field { margin-bottom:.9rem; }
    .field label { display:block;font-size:.74rem;font-weight:700;text-transform:uppercase;
                   letter-spacing:.04em;color:var(--gray-500);margin-bottom:.25rem; }
    .field input, .field select, .field textarea {
        width:100%;border:1px solid var(--gray-300);border-radius:7px;padding:.5rem .7rem;
        font-size:.9rem;font-family:inherit;box-sizing:border-box; }
    .field textarea { min-height:70px;resize:vertical; }
    .hint { font-size:.76rem;color:var(--gray-400);margin-top:.2rem; }
    .mc-box { background:#f8fafc;border:1px solid var(--gray-200);border-radius:10px;padding:1rem; }
    .mc-row { display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;font-size:.87rem; }
    .badge { display:inline-block;font-size:.66rem;font-weight:800;text-transform:uppercase;
             letter-spacing:.03em;padding:.12rem .45rem;border-radius:100px; }
    .act-btn { background:none;border:1px solid var(--gray-200);border-radius:6px;padding:.3rem .65rem;
               font-size:.78rem;cursor:pointer;color:var(--gray-600);text-decoration:none; }
    .act-btn:hover { background:var(--accent);border-color:var(--primary);color:var(--primary); }
    .act-btn.danger:hover { background:#fef2f2;border-color:#fecaca;color:#dc2626; }
    .bar { display:flex;gap:.6rem;align-items:center;flex-wrap:wrap; }
    .hist { font-size:.78rem;color:var(--gray-500);line-height:1.7; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="people.php">&#x2190; People</a>
    <h1><?= $contact ? htmlspecialchars(contactDisplayName($contact)) : 'Add contact' ?></h1>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">

    <div class="card">
      <h2 class="sec">Person</h2>
      <div class="row">
        <div class="field"><label>First name</label>
          <input name="first_name" value="<?= htmlspecialchars($form['first_name']) ?>"></div>
        <div class="field"><label>Last name</label>
          <input name="last_name" value="<?= htmlspecialchars($form['last_name']) ?>"></div>
      </div>
      <div class="field"><label>Preferred name</label>
        <input name="preferred_name" value="<?= htmlspecialchars($form['preferred_name']) ?>">
        <div class="hint">Used instead of the first name where one is set.</div></div>
      <div class="row">
        <div class="field"><label>Email *</label>
          <input type="email" name="email" required value="<?= htmlspecialchars($form['email']) ?>">
          <div class="hint">Identifies the contact — matching ignores capitalisation.</div></div>
        <div class="field"><label>Secondary email</label>
          <input type="email" name="secondary_email" value="<?= htmlspecialchars($form['secondary_email']) ?>"></div>
      </div>
      <div class="field" style="max-width:260px;"><label>Phone</label>
        <input name="phone" value="<?= htmlspecialchars($form['phone']) ?>"></div>
    </div>

    <div class="card">
      <h2 class="sec">Union</h2>
      <div class="row">
        <div class="field"><label>School</label>
          <select name="school_id">
            <option value="">—</option>
            <?php foreach ($schools as $s): ?>
            <option value="<?= (int)$s['id'] ?>"
              <?= (string)$form['school_id'] === (string)$s['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="field"><label>Other school</label>
          <input name="school_other" value="<?= htmlspecialchars($form['school_other']) ?>">
          <div class="hint">Only if it isn't in the list.</div></div>
      </div>
      <div class="row">
        <div class="field"><label>Position</label>
          <input name="position" value="<?= htmlspecialchars($form['position']) ?>"></div>
        <div class="field"><label>Role</label>
          <input name="role" value="<?= htmlspecialchars($form['role']) ?>"
                 list="rolelist"><datalist id="rolelist">
            <?php foreach (contactDistinctRoles() as $r): ?>
            <option value="<?= htmlspecialchars($r) ?>"><?php endforeach; ?></datalist></div>
      </div>
      <div class="field" style="max-width:260px;"><label>Status</label>
        <select name="status">
          <?php foreach (CONTACT_STATUSES as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= $form['status'] === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label>Notes</label>
        <textarea name="notes"><?= htmlspecialchars($form['notes']) ?></textarea></div>
    </div>

    <div class="bar">
      <button class="btn btn-primary" style="padding:.55rem 1.2rem;font-size:.92rem;">
        <?= $contact ? 'Save changes' : 'Add contact' ?></button>
      <a class="act-btn" href="people.php">Cancel</a>
    </div>
  </form>

  <?php if ($contact): ?>
  <div class="card" style="margin-top:1.5rem;">
    <h2 class="sec">Mailchimp</h2>
    <div class="mc-box">
      <div class="mc-row">
        <strong><?= htmlspecialchars(MC_STATUSES[$contact['mailchimp_status']] ?? 'Unknown') ?></strong>
        <?php if ($contact['mailchimp_last_synced_at']): ?>
          <span style="color:var(--gray-400);font-size:.8rem;">
            last checked <?= date('M j, Y g:ia', strtotime($contact['mailchimp_last_synced_at'])) ?></span>
        <?php endif; ?>
      </div>
      <?php if ($contact['mailchimp_sync_status'] === 'error'): ?>
        <p style="font-size:.82rem;color:#b45309;margin:.5rem 0 0;">
          &#9888; <?= htmlspecialchars($contact['mailchimp_sync_error'] ?: 'Last sync failed.') ?>
        </p>
      <?php endif; ?>
      <p style="font-size:.8rem;color:var(--gray-500);margin:.6rem 0 0;">
        Subscription state belongs to Mailchimp. Editing this contact never changes it —
        someone who unsubscribed stays unsubscribed until they choose otherwise.
      </p>
      <?php if (in_array($contact['mailchimp_status'], ['unsubscribed','unknown','transactional'], true)): ?>
      <form method="POST" style="margin-top:.7rem;"
            onsubmit="return confirm('Ask Mailchimp to send this person a confirmation email inviting them to subscribe?')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="resubscribe">
        <button class="act-btn">Send subscribe confirmation</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2 class="sec">History</h2>
    <?php if (!$history): ?>
      <p style="font-size:.85rem;color:var(--gray-400);font-style:italic;margin:0;">No changes recorded yet.</p>
    <?php else: ?>
      <div class="hist">
        <?php foreach ($history as $h): ?>
        <div>
          <?= date('M j, Y g:ia', strtotime($h['created_at'])) ?> &mdash;
          <strong><?= htmlspecialchars(str_replace('_', ' ', $h['action'])) ?></strong>
          <?= $h['changed'] ? '(' . htmlspecialchars($h['changed']) . ')' : '' ?>
          <?= $h['detail'] ? '&middot; ' . htmlspecialchars($h['detail']) : '' ?>
          <span style="color:var(--gray-400);">by <?= htmlspecialchars($h['actor']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <form method="POST" style="margin-top:.5rem;"
        onsubmit="return confirm('<?= $contact['status'] === 'archived' ? 'Restore' : 'Archive' ?> this contact?')">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="<?= $contact['status'] === 'archived' ? 'restore' : 'archive' ?>">
    <button class="act-btn <?= $contact['status'] === 'archived' ? '' : 'danger' ?>">
      <?= $contact['status'] === 'archived' ? 'Restore contact' : 'Archive contact' ?>
    </button>
    <span style="font-size:.78rem;color:var(--gray-400);margin-left:.4rem;">
      Archiving hides them from the list without deleting anything.
    </span>
  </form>
  <?php endif; ?>

</div>
</body>
</html>
