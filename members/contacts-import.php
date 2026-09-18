<?php
/**
 * contacts-import.php — CSV/XLSX import, preview first.
 *
 * Nothing is written until the preview has been confirmed. Matching is on the
 * normalised email, so re-importing a corrected spreadsheet updates people
 * rather than duplicating them.
 *
 * Imported contacts are NOT subscribed to marketing email. Being in the union's
 * contact list is not consent; the push sets status_if_new=transactional, and
 * anyone already in Mailchimp keeps whatever status they chose.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/contacts-db.php';
require_once __DIR__ . '/invite-db.php';   // reuses the spreadsheet reader

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

sendPrivateHeaders();
contactsEnsureTables();

$error = '';
$notice = '';
$preview = null;

/** Header text -> our field. Covers the spellings spreadsheets actually use. */
function importGuessField(string $h): string {
    $h = strtolower(trim($h));
    $map = [
        'first_name'      => ['first name','firstname','first','given name','given'],
        'last_name'       => ['last name','lastname','last','surname','family name'],
        'preferred_name'  => ['preferred name','preferred','goes by','nickname'],
        'email'           => ['email','email address','e-mail','emailaddress','primary email'],
        'secondary_email' => ['secondary email','alt email','personal email','other email'],
        'phone'           => ['phone','telephone','mobile','cell'],
        'school_other'    => ['school','worksite','site','location'],
        'position'        => ['position','job title','title'],
        'role'            => ['role','union role'],
        'status'          => ['status','member status'],
    ];
    foreach ($map as $field => $names) {
        if (in_array($h, $names, true)) return $field;
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    // ── Step 1: read the file and show what would happen ──
    if ($action === 'preview' && !empty($_FILES['file']['tmp_name'])) {
        $rows = inviteReadSheet($_FILES['file']['tmp_name']);
        if (!$rows) {
            $error = 'Could not read that file. Save it as .csv or .xlsx and try again.';
        } else {
            $header = array_shift($rows);
            $cols = [];
            foreach ($header as $i => $h) {
                $f = importGuessField((string)$h);
                if ($f !== '') $cols[$f] = $i;
            }
            if (!isset($cols['email'])) {
                $error = 'No email column found. The file needs a column headed "Email".';
            } else {
                $add = $upd = $skip = 0;
                $items = [];
                $seen  = [];
                foreach ($rows as $r) {
                    $email = trim((string)($r[$cols['email']] ?? ''));
                    $norm  = contactNormalizeEmail($email);
                    $rec = ['email' => $email];
                    foreach ($cols as $f => $i) {
                        if ($f !== 'email') $rec[$f] = trim((string)($r[$i] ?? ''));
                    }

                    if (!contactValidEmail($email))      { $rec['_v'] = 'skip'; $rec['_why'] = 'Not a valid email'; $skip++; }
                    elseif (isset($seen[$norm]))         { $rec['_v'] = 'skip'; $rec['_why'] = 'Repeated in this file'; $skip++; }
                    elseif (contactFindByEmail($email))  { $rec['_v'] = 'update'; $upd++; $seen[$norm] = 1; }
                    else                                 { $rec['_v'] = 'add';    $add++; $seen[$norm] = 1; }
                    $items[] = $rec;
                }
                $preview = ['items' => $items, 'add' => $add, 'update' => $upd, 'skip' => $skip];
                // Held in the session, not a hidden field, so the browser never
                // carries everyone's details back and forth.
                startSession();
                $_SESSION['contact_import'] = $items;
            }
        }
    }

    // ── Step 2: write it ──
    if ($action === 'confirm') {
        startSession();
        $items = $_SESSION['contact_import'] ?? [];
        if (!$items) {
            $error = 'That import expired. Upload the file again.';
        } else {
            $added = $updated = 0;
            foreach ($items as $rec) {
                if (($rec['_v'] ?? '') === 'skip') continue;
                $data = array_intersect_key($rec, array_flip(CONTACT_EDITABLE));
                if (empty($data['status'])) $data['status'] = 'active';

                if (($rec['_v'] ?? '') === 'update') {
                    $existing = contactFindByEmail($rec['email']);
                    if ($existing) {
                        // Only fill fields the file actually supplied, so an
                        // import cannot blank details it had no column for.
                        $merged = $existing;
                        foreach ($data as $k => $v) if ($v !== '') $merged[$k] = $v;
                        contactUpdate((int)$existing['id'], $merged, $member['email']);
                        $updated++;
                    }
                } else {
                    $res = contactCreate($data, $member['email']);
                    if (empty($res['error'])) $added++;
                }
            }
            unset($_SESSION['contact_import']);
            contactAudit(null, 'import', $member['email'], '', "{$added} added, {$updated} updated");
            header('Location: contacts.php?notice=' . urlencode(
                "Import finished — {$added} added, {$updated} updated. Nobody was subscribed to email."));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Import contacts — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:900px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .page-header h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 0; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#991b1b;margin:1rem 0; }
    .card { background:#fff;border:1px solid var(--gray-200);border-radius:12px;padding:1.5rem;margin-bottom:1.25rem; }
    .field label { display:block;font-size:.74rem;font-weight:700;text-transform:uppercase;
                   letter-spacing:.04em;color:var(--gray-500);margin-bottom:.25rem; }
    .hint { font-size:.8rem;color:var(--gray-500);line-height:1.6; }
    .tally { display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem; }
    .tally div { background:#fff;border:1px solid var(--gray-200);border-radius:10px;
                 padding:.7rem 1.1rem;text-align:center;flex:1;min-width:90px; }
    .tally .n { font-size:1.6rem;font-weight:800;line-height:1; }
    .tally .l { font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;
                color:var(--gray-400);margin-top:.2rem; }
    table { width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--gray-200);
            border-radius:10px;overflow:hidden;font-size:.83rem; }
    thead tr { background:#1a2e1a; }
    th { padding:.5rem .8rem;text-align:left;font-size:.68rem;font-weight:700;color:#fff;
         text-transform:uppercase;letter-spacing:.05em; }
    td { padding:.4rem .8rem;border-bottom:1px solid var(--gray-100); }
    tr:last-child td { border-bottom:none; }
    .tag { display:inline-block;font-size:.64rem;font-weight:800;text-transform:uppercase;
           padding:.1rem .45rem;border-radius:100px; }
    .tag.add { background:#f0fdf4;color:#166534; }
    .tag.update { background:#eff6ff;color:#1e40af; }
    .tag.skip { background:#fef2f2;color:#991b1b; }
    .consent { background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.8rem 1rem;
               font-size:.85rem;color:#92400e;line-height:1.6;margin:1rem 0; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="contacts.php">&#x2190; Contacts</a>
    <h1>Import contacts</h1>
  </div>

  <?php if ($error): ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <?php if (!$preview): ?>
  <div class="card">
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="preview">
      <div class="field">
        <label>Spreadsheet</label>
        <input type="file" name="file" accept=".csv,.xlsx,text/csv" required
               style="display:block;border:1px solid var(--gray-300);border-radius:7px;
                      padding:.5rem .7rem;font-size:.88rem;width:100%;box-sizing:border-box;background:#fff;">
        <p class="hint" style="margin-top:.4rem;">
          Needs an <strong>Email</strong> column. First name, last name, school, position,
          role and phone are picked up when present — column headings are matched
          automatically. <strong>.csv</strong> and <strong>.xlsx</strong> both work.
        </p>
      </div>
      <button class="btn btn-primary" style="padding:.55rem 1.2rem;font-size:.92rem;">Preview import</button>
    </form>
  </div>

  <div class="consent">
    Importing someone here does not subscribe them to email. They are added to Mailchimp as
    a contact only, and anyone already there keeps the subscription choice they made.
  </div>

  <?php else: ?>
  <div class="tally">
    <div><div class="n" style="color:#166534;"><?= $preview['add'] ?></div><div class="l">To add</div></div>
    <div><div class="n" style="color:#1e40af;"><?= $preview['update'] ?></div><div class="l">To update</div></div>
    <div><div class="n" style="color:#991b1b;"><?= $preview['skip'] ?></div><div class="l">Skipped</div></div>
  </div>

  <table>
    <thead><tr><th></th><th>Name</th><th>Email</th><th>School</th><th>Note</th></tr></thead>
    <tbody>
      <?php foreach (array_slice($preview['items'], 0, 100) as $r): ?>
      <tr>
        <td><span class="tag <?= $r['_v'] ?>"><?= $r['_v'] ?></span></td>
        <td><?= htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?></td>
        <td style="color:var(--gray-500);"><?= htmlspecialchars($r['email']) ?></td>
        <td><?= htmlspecialchars($r['school_other'] ?? '') ?></td>
        <td style="color:#b45309;font-size:.78rem;"><?= htmlspecialchars($r['_why'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (count($preview['items']) > 100): ?>
    <p class="hint" style="margin-top:.5rem;">Showing the first 100 of <?= count($preview['items']) ?> rows.
       All of them will be imported.</p>
  <?php endif; ?>

  <div class="consent">
    Nobody in this file will be subscribed to marketing email. Existing Mailchimp
    subscribers keep the status they already have.
  </div>

  <form method="POST" style="display:flex;gap:.6rem;align-items:center;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="confirm">
    <button class="btn btn-primary" style="padding:.55rem 1.2rem;font-size:.92rem;">
      Import <?= $preview['add'] + $preview['update'] ?> contacts
    </button>
    <a class="back-link" href="contacts-import.php">Choose a different file</a>
  </form>
  <?php endif; ?>

</div>
</body>
</html>
