<?php
/**
 * member-manage.php — Admin: manage accounts (Members tab) + send invitations (Invitations tab)
 */
require_once __DIR__ . '/contacts-db.php';
require_once 'auth.php';
require_once 'db.php';
require_once 'exec-db.php';
require_once 'invite-db.php';

requireLogin();
ensureMembersColumns();

$member = getMember();

if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$notice = null;
$error  = null;
$db     = getDB();

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Every form on this page and on people.php carries csrfField().
    csrfCheck();
    $action = $_POST['action'] ?? '';

    // Add new member
    if ($action === 'add_member') {
        $name    = trim($_POST['name']     ?? '');
        $email   = strtolower(trim($_POST['email']    ?? ''));
        $empNum  = trim($_POST['employee_number'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$name || !$email || !$empNum || strlen($password) < 8) {
            $error = 'Please fill in all required fields. Password must be at least 8 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $s = $db->prepare("SELECT employee_number FROM valid_employee_numbers WHERE employee_number=?");
            $s->execute([$empNum]);
            if (!$s->fetch()) {
                $error = 'That employee number was not found. Please check it and try again.';
            } else {
                $s = $db->prepare("SELECT id FROM members WHERE email=? OR employee_number=?");
                $s->execute([$email, $empNum]);
                if ($s->fetch()) {
                    $error = 'An account with that email or employee number already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->prepare(
                        "INSERT INTO members (name, email, password_hash, must_change_password, employee_number)
                         VALUES (?,?,?,1,?)"
                    )->execute([$name, $email, $hash, $empNum]);
                    contactEnsureForAccount((int)$db->lastInsertId(), $name, $email, $member['email']);
                    $notice = htmlspecialchars($name) . ' (' . htmlspecialchars($email) . ') added.'
                            . ' They will be prompted to set a new password on first login.';
                }
            }
        }
    }

    // Edit member name / email
    if ($action === 'edit_member') {
        $id       = (int)($_POST['member_id'] ?? 0);
        $newName  = trim($_POST['new_name']  ?? '');
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));

        if (!$id || !$newName || !$newEmail) {
            $error = 'Name and email are required.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            // Fetch current values
            $s = $db->prepare("SELECT name, email FROM members WHERE id=?");
            $s->execute([$id]);
            $old = $s->fetch();

            if (!$old) {
                $error = 'Member not found.';
            } else {
                $oldEmail = strtolower(trim($old['email']));
                $oldName  = $old['name'];

                // Check new email not already taken by someone else
                if ($newEmail !== $oldEmail) {
                    $s = $db->prepare("SELECT id FROM members WHERE email=? AND id != ?");
                    $s->execute([$newEmail, $id]);
                    if ($s->fetch()) {
                        $error = 'That email is already used by another account.';
                    }
                }

                if (!$error) {
                    $db->prepare("UPDATE members SET name=?, email=? WHERE id=?")
                       ->execute([$newName, $newEmail, $id]);

                    // Cascade to role tables
                    foreach ([
                        "UPDATE exec_roles SET user_email=?, user_name=? WHERE user_email=?",
                        "UPDATE exp_roles  SET user_email=?, user_name=? WHERE user_email=?",
                        "UPDATE prod_roles SET user_email=?, user_name=? WHERE user_email=?",
                    ] as $sql) {
                        $db->prepare($sql)->execute([$newEmail, $newName, $oldEmail]);
                    }
                    // Cascade name to denormalized columns in expense/batch tables
                    $db->prepare("UPDATE exp_expenses SET user_email=?, user_name=? WHERE user_email=?")
                       ->execute([$newEmail, $newName, $oldEmail]);
                    $db->prepare("UPDATE exp_batches SET user_email=?, user_name=? WHERE user_email=?")
                       ->execute([$newEmail, $newName, $oldEmail]);
                    $db->prepare("UPDATE lp_vouchers SET submitted_by_email=?, submitted_by=? WHERE submitted_by_email=?")
                       ->execute([$newEmail, $newName, $oldEmail]);

                    $notice = 'Account updated'
                        . ($newEmail !== $oldEmail ? ' — email changed from ' . htmlspecialchars($oldEmail) . ' to ' . htmlspecialchars($newEmail) . ', all role records updated' : '')
                        . '.';
                }
            }
        }
    }

    // Deactivate / reactivate
    if ($action === 'toggle_active') {
        $id     = (int)($_POST['member_id'] ?? 0);
        $active = (int)($_POST['set_active'] ?? 1);
        $selfId = (int)($member['id'] ?? 0);
        if ($id && $id !== $selfId) {
            $db->prepare("UPDATE members SET active=? WHERE id=?")->execute([$active, $id]);
            $notice = $active ? 'Account reactivated.' : 'Account deactivated — member can no longer log in.';
        }
    }

    // Reset password
    if ($action === 'reset_password') {
        $id       = (int)($_POST['member_id'] ?? 0);
        $password = $_POST['new_password'] ?? '';
        if ($id > 0 && strlen($password) >= 8) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE members SET password_hash=?, must_change_password=1 WHERE id=?")
               ->execute([$hash, $id]);
            $notice = 'Password reset. They will be prompted to choose a new password on next login.';
        } else {
            $error = 'Password must be at least 8 characters.';
        }
    }

    // ── Invite actions ────────────────────────────────────────────────────────
    if ($action === 'send_invites') {
        $entries = [];
        $iErrors = [];

        // Builds a display name from whichever columns the sheet provides:
        // a single full-name column, or separate first/last columns.
        $buildName = function (array $row, $nameCol, $firstCol, $lastCol): string {
            if ($nameCol !== null && trim($row[$nameCol] ?? '') !== '') {
                return trim($row[$nameCol]);
            }
            $first = $firstCol !== null ? trim($row[$firstCol] ?? '') : '';
            $last  = $lastCol  !== null ? trim($row[$lastCol]  ?? '') : '';
            return trim($first . ' ' . $last);
        };

        if (!empty($_FILES['csv_file']['tmp_name'])) {
            // Handles .xlsx and .csv alike; detects by content, not extension.
            $sheetRows = inviteReadSheet($_FILES['csv_file']['tmp_name']);
            if (!$sheetRows) {
                $iErrors[] = 'Could not read that file. Save it as .csv or .xlsx and try again.';
            }
            {
                $header = null;
                $emailCol = $nameCol = $firstCol = $lastCol = null;
                foreach ($sheetRows as $row) {
                    if (!$header) {
                        $header = array_map('strtolower', array_map('trim', $row));
                        foreach ($header as $i => $h) {
                            if (in_array($h, ['email','email address','e-mail','emailaddress','email 1 - value'])) $emailCol = $i;
                            if (in_array($h, ['name','full name','fullname','display name'])) $nameCol  = $i;
                            if (in_array($h, ['first name','firstname','first','given name'])) $firstCol = $i;
                            if (in_array($h, ['last name','lastname','last','surname','family name'])) $lastCol = $i;
                        }
                        if ($emailCol === null) {
                            // No recognisable header row — assume col 0 is the
                            // email and col 1 the name, and treat this as data.
                            $emailCol = 0; $nameCol = isset($row[1]) ? 1 : null;
                            $header = ['auto'];
                            $e = strtolower(trim($row[0] ?? ''));
                            $n = $nameCol !== null ? trim($row[1] ?? '') : '';
                            if ($e) $entries[] = ['email' => $e, 'name' => $n];
                        }
                        continue;
                    }
                    $e = strtolower(trim($row[$emailCol] ?? ''));
                    $n = $buildName($row, $nameCol, $firstCol, $lastCol);
                    if ($e) $entries[] = ['email' => $e, 'name' => $n];
                }
                if ($sheetRows && $emailCol === null) {
                    $iErrors[] = 'No email column found — expected a header named "Email".';
                }
            }
        }

        // Pasted lines: "Jane Smith, jane@example.com" or a bare address.
        // Split on the LAST comma — an email never contains one, but a name
        // written "Smith, Jane" does.
        $raw = trim($_POST['invite_list'] ?? '');
        if ($raw) {
            foreach (array_filter(array_map('trim', explode("\n", $raw))) as $line) {
                $cut = strrpos($line, ',');
                if ($cut === false) {
                    $entries[] = ['email' => strtolower($line), 'name' => ''];
                } else {
                    $entries[] = [
                        'email' => strtolower(trim(substr($line, $cut + 1))),
                        'name'  => trim(substr($line, 0, $cut)),
                    ];
                }
            }
        }
        // Import only — nothing is emailed here. Sending is a separate,
        // deliberate step so a roster upload can never blast the membership.
        $added = $updated = $dupe = $iskip = 0;
        foreach ($entries as $entry) {
            $iname  = $entry['name'];
            $iemail = strtolower(trim($entry['email']));
            if (!filter_var($iemail, FILTER_VALIDATE_EMAIL)) {
                $iErrors[] = 'Invalid: ' . htmlspecialchars($entry['email'] ?: '(blank)');
                $iskip++; continue;
            }
            $s = $db->prepare("SELECT id FROM members WHERE email=?"); $s->execute([$iemail]);
            if ($s->fetch()) { $iErrors[] = "{$iemail} already has an account."; $iskip++; continue; }
            $res = inviteImport($iemail, $iname, $member['email']);
            if ($res === 'added') $added++;
            elseif ($res === 'updated') $updated++;
            else $dupe++;
        }
        $parts = [];
        if ($added)   $parts[] = "{$added} added";
        if ($updated) $parts[] = "{$updated} updated";
        if ($dupe)    $parts[] = "{$dupe} already on the list";
        if ($iskip)   $parts[] = "{$iskip} skipped";
        $notice = ($parts ? implode(', ', $parts) : 'Nothing imported') . '.'
                . ' No emails were sent — use “Send to everyone not yet emailed” or the per-member Send button.';
        if ($iErrors) $notice .= ' — ' . implode(' | ', $iErrors);
    }

    // Email everyone on the list who has never been sent a link
    if ($action === 'send_all_invites') {
        $unsent = inviteGetUnsent();
        $sent = $fail = 0;
        foreach ($unsent as $i => $inv) {
            if (inviteIssue((int)$inv['id'])) $sent++; else $fail++;
            // Pace the batch so Hostinger doesn't throttle or spam-flag it
            if (($i + 1) % 20 === 0) usleep(500000);
        }
        if (!$unsent) {
            $notice = 'Everyone on the list has already been emailed.';
        } else {
            $notice = "{$sent} registration link" . ($sent !== 1 ? 's' : '') . ' sent.'
                    . ($fail ? " {$fail} failed — check the Email Log." : '');
        }
    }

    // Email (or re-email) one member
    if ($action === 'resend_invite') {
        $iid = (int)($_POST['invite_id'] ?? 0);
        $s   = $db->prepare("SELECT email, sent_at FROM member_invitations WHERE id=?"); $s->execute([$iid]);
        $inv = $s->fetch();
        if ($inv) {
            $wasSent = !empty($inv['sent_at']);
            $ok = inviteIssue($iid);
            $verb = $wasSent ? 're-sent' : 'sent';
            $notice = $ok
                ? "Registration link {$verb} to " . htmlspecialchars($inv['email']) . '.'
                : 'Send failed — check the Email Log.';
        }
    }

    // Bulk actions on checkbox-selected rows
    if ($action === 'send_selected' || $action === 'revoke_selected') {
        $ids = array_filter(array_map('intval', (array)($_POST['invite_ids'] ?? [])));
        if (!$ids) {
            $error = 'No rows were selected.';
        } elseif ($action === 'send_selected') {
            $sent = $fail = 0;
            foreach (array_values($ids) as $n => $iid) {
                if (inviteIssue($iid)) $sent++; else $fail++;
                if (($n + 1) % 20 === 0) usleep(500000);
            }
            $notice = "{$sent} registration link" . ($sent !== 1 ? 's' : '') . ' sent.'
                    . ($fail ? " {$fail} could not be sent — check the Email Log." : '');
        } else {
            $gone = 0;
            foreach ($ids as $iid) { inviteRevoke($iid); $gone++; }
            $notice = "{$gone} entr" . ($gone !== 1 ? 'ies' : 'y') . ' removed from the invitation list.';
        }
    }

    if ($action === 'revoke_invite') {
        $iid = (int)($_POST['invite_id'] ?? 0);
        inviteRevoke($iid);
        $notice = 'Removed from the invitation list.';
    }

    // These handlers are the single implementation of every account and invite
    // action; people.php posts here rather than keeping a second copy.
    // Whitelisted so the parameter can never become an open redirect.
    $back = $_POST['redirect'] ?? '';
    if ($back !== '' && preg_match('#^people\.php(\?[A-Za-z0-9_=&%.+-]*)?$#', $back)) {
        $sep = strpos($back, '?') === false ? '?' : '&';
        $qs  = $notice ? 'notice=' . urlencode($notice) : ($error ? 'error=' . urlencode($error) : '');
        header('Location: ' . $back . ($qs ? $sep . $qs : ''));
        exit;
    }

    $tab = in_array($action, ['send_invites','send_all_invites','resend_invite','revoke_invite',
                              'send_selected','revoke_selected']) ? '&tab=invitations' : '';
    header('Location: member-manage.php' . ($notice ? '?notice=' . urlencode($notice) . $tab : ($error ? '?error=' . urlencode($error) . $tab : ($tab ? '?'.ltrim($tab,'&') : ''))));
    exit;
}

$notice  = $notice ?: htmlspecialchars($_GET['notice'] ?? '');
$error   = $error  ?: htmlspecialchars($_GET['error']  ?? '');
$activeTab = ($_GET['tab'] ?? '') === 'invitations' ? 'invitations' : 'members';

// ── Load all members ──────────────────────────────────────────────────────────
$members = $db->query(
    "SELECT id, name, email, must_change_password, created_at, COALESCE(active,1) AS active
     FROM members ORDER BY name"
)->fetchAll();

// Quick role lookup: exec + exp roles per email
$roleMap = [];
$execRows = $db->query("SELECT user_email, role FROM exec_roles")->fetchAll();
foreach ($execRows as $r) $roleMap[strtolower($r['user_email'])][] = $r['role'];
$expRows  = $db->query("SELECT user_email, role FROM exp_roles")->fetchAll();
foreach ($expRows  as $r) $roleMap[strtolower($r['user_email'])][] = $r['role'];

// ── Load invitations ──────────────────────────────────────────────────────────
inviteEnsureTable();
$invites = inviteGetAll();
$invCounts = ['not_sent' => 0, 'pending' => 0, 'accepted' => 0, 'expired' => 0];
foreach ($invites as $i) $invCounts[$i['invite_status']]++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Management — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 1020px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between;
                   margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice   { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box{ background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
                padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase;
                letter-spacing: .08em; color: var(--gray-400); margin: 2rem 0 .75rem; }
    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
                 padding: 1.5rem; margin-bottom: 1.75rem; }
    .form-card h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1rem; }
    .field { margin-bottom: .9rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase;
                   letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px;
                   padding: .55rem .75rem; font-size: .9rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .25rem; }
    @media(max-width:600px) { .field-row { grid-template-columns: 1fr; } }

    /* Members table */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
                  overflow: hidden; margin-bottom: 1.5rem; }
    table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    thead tr { background: #1a2e1a; }
    th { padding: .6rem .85rem; text-align: left; font-size: .71rem; font-weight: 700;
         text-transform: uppercase; letter-spacing: .05em; color: #fff; white-space: nowrap; }
    td { padding: .6rem .85rem; border-bottom: 1px solid var(--gray-100);
         color: var(--gray-700); vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    tr.inactive-row td { opacity: .55; }
    tr.inactive-row { background: #fafafa; }

    .badge-pending  { display: inline-block; background: #fef3c7; color: #d97706; font-size: .68rem; font-weight: 700; border-radius: 100px; padding: .15rem .5rem; }
    .badge-inactive { display: inline-block; background: #fee2e2; color: #991b1b; font-size: .68rem; font-weight: 700; border-radius: 100px; padding: .15rem .5rem; }
    .badge-active   { display: inline-block; background: #dcfce7; color: #166534; font-size: .68rem; font-weight: 700; border-radius: 100px; padding: .15rem .5rem; }
    .badge-you      { display: inline-block; background: #eff6ff; color: #1d4ed8; font-size: .68rem; font-weight: 700; border-radius: 100px; padding: .15rem .5rem; margin-left: .3rem; }
    .role-tag { display: inline-block; background: var(--accent); color: var(--primary); font-size: .65rem; font-weight: 700; border-radius: 4px; padding: .1rem .4rem; margin: .1rem .1rem 0 0; }

    /* Inline action buttons */
    .act-btn { background: none; border: 1px solid var(--gray-200); border-radius: 6px; padding: .28rem .6rem; font-size: .76rem; cursor: pointer; color: var(--gray-600); white-space: nowrap; }
    .act-btn:hover { background: var(--accent); border-color: var(--primary); color: var(--primary); }
    .act-btn.danger:hover { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
    .act-btn.go:hover { background: #f0fdf4; border-color: #86efac; color: #166534; }
    .acts { display: flex; gap: .35rem; flex-wrap: wrap; }

    /* Inline edit row */
    .edit-row { display: none; background: #f8fafc; border-top: 1px solid var(--gray-100); }
    .edit-row.open { display: table-row; }
    .edit-row td { padding: .75rem .85rem; }
    .edit-inner { display: flex; gap: .6rem; align-items: flex-end; flex-wrap: wrap; }
    .edit-inner .ef { display: flex; flex-direction: column; gap: .2rem; }
    .edit-inner .ef label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-400); }
    .edit-inner input { border: 1px solid var(--gray-300); border-radius: 6px; padding: .4rem .6rem; font-size: .86rem; font-family: inherit; min-width: 180px; }
    .edit-inner input:focus { outline: none; border-color: var(--primary); }

    /* Reset password row */
    .reset-form { display: flex; gap: .35rem; align-items: center; }
    .reset-form input { width: 130px; border: 1px solid var(--gray-300); border-radius: 6px; padding: .3rem .5rem; font-size: .8rem; }

    .pw-field { position: relative; }
    .pw-field input { padding-right: 2.5rem; }
    .pw-toggle { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%);
                 background: none; border: none; cursor: pointer; color: var(--gray-400); padding: .2rem; }

    .info-note { font-size: .78rem; color: var(--gray-500); background: #f8f9fa; border: 1px solid var(--gray-200); border-radius: 8px; padding: .6rem .85rem; margin-bottom: 1rem; }

    /* Tabs */
    .tabs { display: flex; gap: 0; border-bottom: 2px solid var(--gray-200); margin-bottom: 1.75rem; }
    .tab-btn { background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px;
               padding: .6rem 1.1rem; font-size: .9rem; font-weight: 700; color: var(--gray-400);
               cursor: pointer; font-family: inherit; }
    .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
    .tab-btn .badge { display: inline-block; background: var(--accent); color: var(--primary);
                      font-size: .65rem; font-weight: 800; border-radius: 100px;
                      padding: .1rem .45rem; margin-left: .35rem; vertical-align: middle; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* Invite table */
    /* Bulk action bar */
    .bulk-bar { display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem;
                padding: .5rem .75rem; background: #fff; border: 1px solid var(--gray-200);
                border-radius: 10px; }
    .bulk-count { font-size: .8rem; font-weight: 700; color: var(--gray-500); margin-right: auto; }
    .bulk-bar button[disabled] { opacity: .4; cursor: not-allowed; }
    .bulk-bar button[disabled]:hover { background: none; border-color: var(--gray-200); color: var(--gray-600); }
    .inv-check, #invSelectAll { width: 15px; height: 15px; cursor: pointer; accent-color: #1a6b35; }

    /* Sortable column headers */
    th.sortable { cursor: pointer; user-select: none; position: relative; padding-right: 1.5rem; }
    th.sortable:hover { background: #24422a; }
    th.sortable::after { content: '\2195'; position: absolute; right: .5rem; top: 50%;
                         transform: translateY(-50%); opacity: .35; font-size: .8rem; }
    th.sortable.asc::after  { content: '\25B2'; opacity: 1; font-size: .6rem; }
    th.sortable.desc::after { content: '\25BC'; opacity: 1; font-size: .6rem; }

    .badge-notsent-inv  { display:inline-block;background:#e0e7ff;color:#3730a3;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .badge-pending-inv  { display:inline-block;background:#fef3c7;color:#d97706;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .badge-accepted-inv { display:inline-block;background:#dcfce7;color:#166534;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .badge-expired-inv  { display:inline-block;background:#f1f5f9;color:#64748b;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .stat-row { display:flex;gap:1rem;margin-bottom:1.5rem;flex-wrap:wrap; }
    .stat { background:#fff;border:1px solid var(--gray-200);border-radius:10px;padding:.75rem 1.1rem;flex:1;min-width:90px;text-align:center; }
    .stat .n { font-size:1.8rem;font-weight:800;color:var(--gray-800);line-height:1; }
    .stat .l { font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-400);margin-top:.2rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Member Management</h1>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <div class="tabs">
    <button class="tab-btn <?= $activeTab === 'members' ? 'active' : '' ?>"
            onclick="switchTab('members')">
      Members
      <span class="badge"><?= count($members) ?></span>
    </button>
    <button class="tab-btn <?= $activeTab === 'invitations' ? 'active' : '' ?>"
            onclick="switchTab('invitations')">
      Invitations
      <?php if ($invCounts['not_sent']): ?>
      <span class="badge"><?= $invCounts['not_sent'] ?> to send</span>
      <?php elseif ($invCounts['pending']): ?>
      <span class="badge"><?= $invCounts['pending'] ?> pending</span>
      <?php endif; ?>
    </button>
  </div>

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <!-- TAB: Members                                                          -->
  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <div id="tab-members" class="tab-panel <?= $activeTab === 'members' ? 'active' : '' ?>">

  <div class="info-note">
    &#x1F4CB; This list and the <a href="roles-overview.php">Roles &amp; Directory</a> both read from the same member database.
    Editing a name or email here automatically updates all role tables.
  </div>

  <!-- ── Add member ─────────────────────────────────────────────────────────── -->
  <div class="sec-head">Add a New Member</div>
  <div class="form-card">
    <h2>Create Account</h2>
    <p style="font-size:.83rem;color:var(--gray-500);margin:-.25rem 0 1rem;">
      The member will be asked to set their own password the first time they log in.
    </p>
    <form method="POST" autocomplete="off">
        <?= csrfField() ?>
      <input type="hidden" name="action" value="add_member">
      <div class="field-row">
        <div class="field">
          <label>Full Name *</label>
          <input type="text" name="name" required placeholder="e.g. Jane Smith" autocomplete="off">
        </div>
        <div class="field">
          <label>Email Address *</label>
          <input type="email" name="email" required placeholder="e.g. jane@bctf.ca" autocomplete="off">
        </div>
      </div>
      <div class="field" style="max-width:200px;">
        <label>Employee Number *</label>
        <input type="text" name="employee_number" required placeholder="e.g. 12345" autocomplete="off">
        <div class="field-hint">Must be on the approved employee list.</div>
      </div>
      <div class="field" style="max-width:300px;">
        <label>Temporary Password *</label>
        <div class="pw-field">
          <input type="password" id="addPw" name="password" required minlength="8"
                 placeholder="Min. 8 characters" autocomplete="new-password">
          <button type="button" class="pw-toggle" onclick="togglePw('addPw')" title="Show/hide">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="field-hint">They will be prompted to change this on first login.</div>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">
        Create Account
      </button>
    </form>
  </div>

  <!-- ── Member list ────────────────────────────────────────────────────────── -->
  <div class="sec-head">All Members (<?= count($members) ?>)</div>
  <div class="table-wrap">
    <table id="membersTable">
      <thead>
        <tr>
          <th class="sortable" data-col="0">Name</th>
          <th class="sortable" data-col="1">Email</th>
          <th class="sortable" data-col="2">Status</th>
          <th>Roles</th>
          <th class="sortable" data-col="4">Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m):
          $isYou    = (strtolower($m['email']) === strtolower($member['email']));
          $isActive = (int)($m['active'] ?? 1);
          $roles    = $roleMap[strtolower($m['email'])] ?? [];
        ?>
        <tr id="main-<?= $m['id'] ?>" class="<?= !$isActive ? 'inactive-row' : '' ?>">
          <td data-sort="<?= htmlspecialchars($m['name']) ?>">
            <strong><?= htmlspecialchars($m['name']) ?></strong>
            <?php if ($isYou): ?><span class="badge-you">you</span><?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--gray-500);"><?= htmlspecialchars($m['email']) ?></td>
          <td>
            <?php if (!$isActive): ?>
              <span class="badge-inactive">&#x2715; Deactivated</span>
            <?php elseif ($m['must_change_password']): ?>
              <span class="badge-pending">&#x23F3; Pending first login</span>
            <?php else: ?>
              <span class="badge-active">&#x2713; Active</span>
            <?php endif; ?>
          </td>
          <td>
            <?php foreach ($roles as $r): ?>
            <span class="role-tag"><?= htmlspecialchars($r) ?></span>
            <?php endforeach; ?>
            <?php if (!$roles): ?><span style="color:var(--gray-300);font-size:.78rem;">—</span><?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;"
              data-sort="<?= $m['created_at'] ? (int)strtotime($m['created_at']) : 0 ?>">
            <?= $m['created_at'] ? date('M j, Y', strtotime($m['created_at'])) : '—' ?>
          </td>
          <td>
            <div class="acts">
              <?php if (!$isYou): ?>
              <button type="button" class="act-btn" onclick="toggleEdit(<?= $m['id'] ?>)">✏ Edit</button>
              <?php if ($isActive): ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Deactivate <?= htmlspecialchars(addslashes($m['name'])) ?>? They will not be able to log in.')">
        <?= csrfField() ?>
                <input type="hidden" name="action"     value="toggle_active">
                <input type="hidden" name="member_id"  value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="set_active" value="0">
                <button type="submit" class="act-btn danger">⊘ Deactivate</button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline;">
        <?= csrfField() ?>
                <input type="hidden" name="action"     value="toggle_active">
                <input type="hidden" name="member_id"  value="<?= (int)$m['id'] ?>">
                <input type="hidden" name="set_active" value="1">
                <button type="submit" class="act-btn go">&#x21BA; Reactivate</button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>

        <!-- Inline edit row -->
        <tr id="edit-<?= $m['id'] ?>" class="edit-row">
          <td colspan="6">
            <form method="POST" class="edit-inner">
        <?= csrfField() ?>
              <input type="hidden" name="action"    value="edit_member">
              <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
              <div class="ef">
                <label>Full Name</label>
                <input type="text" name="new_name" value="<?= htmlspecialchars($m['name']) ?>" required>
              </div>
              <div class="ef">
                <label>Email</label>
                <input type="email" name="new_email" value="<?= htmlspecialchars($m['email']) ?>" required>
              </div>
              <div style="display:flex;gap:.35rem;align-self:flex-end;">
                <button type="submit" class="btn btn-primary" style="padding:.4rem .85rem;font-size:.82rem;">Save</button>
                <button type="button" class="act-btn" onclick="toggleEdit(<?= $m['id'] ?>)">Cancel</button>
              </div>
            </form>
            <div style="margin-top:.5rem;">
              <form method="POST" class="reset-form"
                    onsubmit="return confirm('Reset password for <?= htmlspecialchars(addslashes($m['name'])) ?>?')">
        <?= csrfField() ?>
                <input type="hidden" name="action"    value="reset_password">
                <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
                <span style="font-size:.74rem;color:var(--gray-400);margin-right:.25rem;">Reset password:</span>
                <input type="password" name="new_password" placeholder="New temp password" minlength="8" required>
                <button type="submit" class="act-btn">Reset</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  </div><!-- /tab-members -->

  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <!-- TAB: Invitations                                                      -->
  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <div id="tab-invitations" class="tab-panel <?= $activeTab === 'invitations' ? 'active' : '' ?>">

    <div class="stat-row">
      <div class="stat"><div class="n"><?= $invCounts['not_sent'] ?></div><div class="l">Not yet sent</div></div>
      <div class="stat"><div class="n"><?= $invCounts['pending'] ?></div><div class="l">Awaiting signup</div></div>
      <div class="stat"><div class="n"><?= $invCounts['accepted'] ?></div><div class="l">Registered</div></div>
      <div class="stat"><div class="n"><?= $invCounts['expired'] ?></div><div class="l">Expired</div></div>
      <div class="stat"><div class="n"><?= count($invites) ?></div><div class="l">Total</div></div>
    </div>

    <div class="sec-head">Step 1 — Import the Membership List</div>
    <div class="form-card">
      <h2>Import Members</h2>
      <p style="font-size:.83rem;color:var(--gray-500);margin:-.25rem 0 1rem;">
        This only builds the list — <strong>no emails are sent</strong>.
        Anyone who already has an account or is already on the list is skipped.
        You choose when to send in Step 2.
      </p>
      <form method="POST" enctype="multipart/form-data" autocomplete="off">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="send_invites">
        <div class="field">
          <label>Upload a CSV file</label>
          <input type="file" name="csv_file" accept=".csv,text/csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                 style="display:block;border:1px solid var(--gray-300);border-radius:7px;padding:.5rem .75rem;font-size:.88rem;width:100%;box-sizing:border-box;background:#fff;">
          <div class="field-hint">Must have an <code>email</code> column. For names, either a single
            <code>name</code> column or separate <code>first name</code> / <code>last name</code> columns —
            both are combined into the full name. Google Contacts / Outlook / Excel exports work as-is.
            Excel <strong>.xlsx</strong> and <strong>.csv</strong> both work — no need to convert.</div>
        </div>
        <div class="field">
          <label>Or paste emails manually</label>
          <textarea name="invite_list" rows="5"
                    style="width:100%;border:1px solid var(--gray-300);border-radius:7px;padding:.6rem .75rem;font-size:.88rem;font-family:monospace;box-sizing:border-box;resize:vertical;"
                    placeholder="One per line — optionally with a name:&#10;&#10;Jane Smith, jane@example.com&#10;john@example.com"></textarea>
          <div class="field-hint">CSV and paste are merged on import.</div>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">Import to List</button>
      </form>
    </div>

    <div class="sec-head">Step 2 — Send Registration Links</div>
    <div class="form-card">
      <h2>Send to Everyone Not Yet Emailed</h2>
      <p style="font-size:.83rem;color:var(--gray-500);margin:-.25rem 0 1rem;">
        <?php if ($invCounts['not_sent']): ?>
          <strong><?= $invCounts['not_sent'] ?></strong> member<?= $invCounts['not_sent'] !== 1 ? 's have' : ' has' ?>
          not been emailed yet. Each gets a personal one-time link valid for 72 hours
          from the moment it is sent.
        <?php else: ?>
          Everyone currently on the list has already been emailed.
          Import more members above, or use the Send button on an individual row below.
        <?php endif; ?>
      </p>
      <form method="POST"
            onsubmit="return confirm('Send a registration link to <?= (int)$invCounts['not_sent'] ?> member(s)? This emails them right now.')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="send_all_invites">
        <button type="submit" class="btn btn-primary"
                style="padding:.55rem 1.1rem;font-size:.9rem;<?= $invCounts['not_sent'] ? '' : 'opacity:.5;' ?>"
                <?= $invCounts['not_sent'] ? '' : 'disabled' ?>>
          ✉ Send to <?= (int)$invCounts['not_sent'] ?> Member<?= $invCounts['not_sent'] !== 1 ? 's' : '' ?>
        </button>
      </form>
    </div>

    <div class="sec-head">All Invitations (<?= count($invites) ?>)</div>

    <!-- Bulk actions. Sits outside the table because each row already contains
         its own form, and forms cannot nest. Checkboxes below opt in via the
         HTML5 form="bulkInviteForm" attribute. -->
    <form method="POST" id="bulkInviteForm" class="bulk-bar" onsubmit="return bulkConfirm(event);">
        <?= csrfField() ?>
      <input type="hidden" name="action" id="bulkAction" value="">
      <span class="bulk-count" id="bulkCount">None selected</span>
      <button type="submit" class="act-btn go"     id="bulkSendBtn"   disabled
              onclick="document.getElementById('bulkAction').value='send_selected';">
        &#x2709; Send to selected
      </button>
      <button type="submit" class="act-btn danger" id="bulkRemoveBtn" disabled
              onclick="document.getElementById('bulkAction').value='revoke_selected';">
        Remove selected
      </button>
    </form>

    <div class="table-wrap">
      <table id="invitesTable">
        <thead>
          <tr>
            <th style="width:34px;"><input type="checkbox" id="invSelectAll" title="Select all"></th>
            <th class="sortable" data-col="1">Email</th>
            <th class="sortable" data-col="2">Name</th>
            <th class="sortable" data-col="3">Status</th>
            <th class="sortable" data-col="4">Sent</th>
            <th>Expires / Accepted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$invites): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:2rem;">Nobody on the list yet — import a membership list above.</td></tr>
          <?php endif; ?>
          <?php foreach ($invites as $inv):
            $istatus = $inv['invite_status'];
          ?>
          <tr>
            <td><input type="checkbox" class="inv-check" form="bulkInviteForm"
                       name="invite_ids[]" value="<?= (int)$inv['id'] ?>"
                       data-status="<?= htmlspecialchars($istatus) ?>"></td>
            <td><?= htmlspecialchars($inv['email']) ?></td>
            <td style="color:var(--gray-500);"><?= htmlspecialchars($inv['name'] ?? '—') ?></td>
            <td>
              <?php if ($istatus === 'not_sent'): ?>
                <span class="badge-notsent-inv">&#x2709; Not sent</span>
              <?php elseif ($istatus === 'pending'): ?>
                <span class="badge-pending-inv">&#x23F3; Awaiting signup</span>
              <?php elseif ($istatus === 'accepted'): ?>
                <span class="badge-accepted-inv">&#x2713; Registered</span>
              <?php else: ?>
                <span class="badge-expired-inv">Expired</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;"
                data-sort="<?= $inv['sent_at'] ? (int)strtotime($inv['sent_at']) : 0 ?>">
              <?= $inv['sent_at'] ? date('M j, Y', strtotime($inv['sent_at'])) : '—' ?>
            </td>
            <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;">
              <?php if ($inv['accepted_at']): ?>
                Registered <?= date('M j, Y', strtotime($inv['accepted_at'])) ?>
              <?php elseif ($istatus === 'not_sent'): ?>
                <span style="color:var(--gray-300);">Not sent yet</span>
              <?php elseif ($istatus === 'expired'): ?>
                Expired <?= date('M j, Y', strtotime($inv['expires_at'])) ?>
              <?php else: ?>
                Expires <?= date('M j g:ia', strtotime($inv['expires_at'])) ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($istatus !== 'accepted'): ?>
              <div style="display:flex;gap:.35rem;">
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Email a registration link to <?= htmlspecialchars(addslashes($inv['email'])) ?> now?')">
        <?= csrfField() ?>
                  <input type="hidden" name="action"    value="resend_invite">
                  <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                  <button type="submit" class="act-btn<?= $istatus === 'not_sent' ? ' go' : '' ?>">
                    <?= $istatus === 'not_sent' ? '&#x2709; Send' : '&#x21BA; Resend' ?>
                  </button>
                </form>
                <form method="POST" style="display:inline;"
                      onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($inv['email'])) ?> from the invitation list?')">
        <?= csrfField() ?>
                  <input type="hidden" name="action"    value="revoke_invite">
                  <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                  <button type="submit" class="act-btn danger">Remove</button>
                </form>
              </div>
              <?php else: ?>
              <span style="font-size:.75rem;color:var(--gray-300);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /tab-invitations -->

</div><!-- /wrap -->
<script>
function togglePw(id) {
    var el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}
function toggleEdit(id) {
    var row = document.getElementById('edit-' + id);
    var isOpen = row.classList.toggle('open');
    if (isOpen) row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
/**
 * Excel-style column sorting.
 * `paired` tables (Members) have a hidden .edit-row after each member row that
 * has to travel with it, so rows are grouped into units before sorting.
 */
function makeSortable(tableId, paired) {
    var table = document.getElementById(tableId);
    if (!table || !table.tBodies.length) return;
    var tbody   = table.tBodies[0];
    var headers = table.querySelectorAll('th.sortable');

    function keyFor(row, col) {
        var cell = row.cells[col];
        if (!cell) return '';
        var explicit = cell.getAttribute('data-sort');
        if (explicit !== null) return explicit;
        return cell.textContent.trim();
    }

    Array.prototype.forEach.call(headers, function (th) {
        th.addEventListener('click', function () {
            var col = parseInt(th.getAttribute('data-col'), 10);
            var asc = !th.classList.contains('asc');

            Array.prototype.forEach.call(headers, function (o) {
                o.classList.remove('asc', 'desc');
            });
            th.classList.add(asc ? 'asc' : 'desc');

            // Group rows into sortable units, keeping edit rows with their owner
            var units = [];
            Array.prototype.forEach.call(tbody.rows, function (row) {
                if (paired && row.classList.contains('edit-row')) {
                    if (units.length) units[units.length - 1].rows.push(row);
                    return;
                }
                // Skip placeholder rows like "No members yet" (single wide cell)
                if (row.cells.length < 2) return;
                units.push({ rows: [row], key: keyFor(row, col) });
            });

            units.sort(function (a, b) {
                var x = a.key, y = b.key;
                var nx = parseFloat(x), ny = parseFloat(y);
                var bothNumeric = !isNaN(nx) && !isNaN(ny) && x !== '' && y !== '';
                var res = bothNumeric ? nx - ny
                                      : x.localeCompare(y, undefined, { sensitivity: 'base' });
                return asc ? res : -res;
            });

            units.forEach(function (u) {
                u.rows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    });
}

makeSortable('membersTable', true);
makeSortable('invitesTable', false);

/* ── Bulk selection on the Invitations table ─────────────────────────────── */
(function () {
    var selectAll = document.getElementById('invSelectAll');
    var countEl   = document.getElementById('bulkCount');
    var sendBtn   = document.getElementById('bulkSendBtn');
    var removeBtn = document.getElementById('bulkRemoveBtn');
    if (!countEl) return;

    function checks() {
        return Array.prototype.slice.call(document.querySelectorAll('.inv-check'));
    }
    function selected() {
        return checks().filter(function (c) { return c.checked; });
    }

    function refresh() {
        var sel = selected();
        var n   = sel.length;
        countEl.textContent = n ? n + ' selected' : 'None selected';
        sendBtn.disabled = removeBtn.disabled = (n === 0);

        if (selectAll) {
            var all = checks();
            selectAll.checked = all.length > 0 && n === all.length;
            selectAll.indeterminate = n > 0 && n < all.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checks().forEach(function (c) { c.checked = selectAll.checked; });
            refresh();
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('inv-check')) refresh();
    });

    window.bulkConfirm = function (ev) {
        var sel = selected();
        if (!sel.length) return false;
        var action = document.getElementById('bulkAction').value;

        if (action === 'revoke_selected') {
            return confirm('Remove ' + sel.length + ' entr' + (sel.length === 1 ? 'y' : 'ies') +
                           ' from the invitation list? This does not affect anyone who has already registered.');
        }
        // Warn when a resend would invalidate a link someone may still be holding
        var resends = sel.filter(function (c) { return c.dataset.status !== 'not_sent'; }).length;
        var msg = 'Email a registration link to ' + sel.length + ' member' +
                  (sel.length === 1 ? '' : 's') + ' right now?';
        if (resends) {
            msg += '\n\n' + resends + ' of them ' + (resends === 1 ? 'has' : 'have') +
                   ' already been emailed. Resending replaces their previous link, so any' +
                   ' older one still in their inbox will stop working.';
        }
        return confirm(msg);
    };

    refresh();
})();

function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    document.querySelectorAll('.tab-panel').forEach(function(p) { p.classList.remove('active'); });
    document.querySelector('.tab-btn[onclick*="' + name + '"]').classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
    history.replaceState(null, '', '?tab=' + name);
}
</script>
</body>
</html>
