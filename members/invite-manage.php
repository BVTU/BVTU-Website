<?php
/**
 * invite-manage.php — Admin: send and manage member invitations
 */
require_once 'auth.php';
require_once 'db.php';
require_once 'exec-db.php';
require_once 'invite-db.php';

requireLogin();
$member = getMember();

if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

inviteEnsureTable();

$notice = '';
$error  = '';

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Send one or more invites
    if ($action === 'send_invites') {
        $lines  = [];
        $errors = [];

        // ── CSV upload ────────────────────────────────────────────────────────
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $fh = fopen($_FILES['csv_file']['tmp_name'], 'r');
            if ($fh) {
                $header     = null;
                $emailCol   = null;
                $nameCol    = null;
                while (($row = fgetcsv($fh)) !== false) {
                    if (!$header) {
                        // Auto-detect header row
                        $header = array_map('strtolower', array_map('trim', $row));
                        foreach ($header as $i => $h) {
                            if (in_array($h, ['email', 'email address', 'e-mail', 'emailaddress'])) $emailCol = $i;
                            if (in_array($h, ['name', 'full name', 'fullname', 'first name', 'firstname', 'display name'])) $nameCol = $i;
                        }
                        if ($emailCol === null) {
                            // No recognisable header — treat first column as email, second as name
                            $emailCol = 0;
                            $nameCol  = isset($row[1]) ? 1 : null;
                            // Re-process this row as data
                            $header = ['auto'];
                            $email  = strtolower(trim($row[$emailCol] ?? ''));
                            $name   = $nameCol !== null ? trim($row[$nameCol]) : '';
                            if ($email) $lines[] = $name ? "{$name}, {$email}" : $email;
                        }
                        continue;
                    }
                    $email = strtolower(trim($row[$emailCol] ?? ''));
                    $name  = $nameCol !== null ? trim($row[$nameCol] ?? '') : '';
                    if ($email) $lines[] = $name ? "{$name}, {$email}" : $email;
                }
                fclose($fh);
            }
        }

        // ── Manual paste ──────────────────────────────────────────────────────
        $raw = trim($_POST['invite_list'] ?? '');
        if ($raw) {
            $lines = array_merge($lines, array_filter(array_map('trim', explode("\n", $raw))));
        }

        $sent  = 0;
        $skip  = 0;
        $fail  = 0;

        foreach ($lines as $line) {
            // Accept "Name, email@example.com" or just "email@example.com"
            if (strpos($line, ',') !== false) {
                [$name, $email] = array_map('trim', explode(',', $line, 2));
            } else {
                $name  = '';
                $email = trim($line);
            }
            $email = strtolower($email);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Skipped invalid email: {$line}";
                $skip++;
                continue;
            }

            // Skip if already a member
            $s = getDB()->prepare("SELECT id FROM members WHERE email=?");
            $s->execute([$email]);
            if ($s->fetch()) {
                $errors[] = "{$email} already has an account — skipped.";
                $skip++;
                continue;
            }

            $token = inviteCreate($email, $name, $member['email']);
            $ok    = inviteSendEmail($email, $name ?: $email, $token);
            if ($ok) { $sent++; } else { $fail++; $errors[] = "Failed to send to {$email}."; }

            // Avoid Hostinger rate limits — small pause every 20 emails
            if ($sent % 20 === 0 && $sent > 0) usleep(500000);
        }

        $parts = [];
        if ($sent)  $parts[] = "{$sent} invite" . ($sent !== 1 ? 's' : '') . " sent";
        if ($skip)  $parts[] = "{$skip} skipped";
        if ($fail)  $parts[] = "{$fail} failed";
        $notice = implode(', ', $parts) . '.';
        if ($errors) $notice .= ' Details: ' . implode(' | ', $errors);
    }

    // Resend a single invite
    if ($action === 'resend') {
        $id = (int)($_POST['invite_id'] ?? 0);
        $s  = getDB()->prepare("SELECT * FROM member_invitations WHERE id=?");
        $s->execute([$id]);
        $inv = $s->fetch();
        if ($inv && !$inv['accepted_at']) {
            $token = inviteCreate($inv['email'], $inv['name'] ?? '', $member['email']);
            $ok    = inviteSendEmail($inv['email'], $inv['name'] ?: $inv['email'], $token);
            $notice = $ok ? 'Invite re-sent to ' . htmlspecialchars($inv['email']) . '.' : 'Failed to send email.';
        }
    }

    // Revoke
    if ($action === 'revoke') {
        $id = (int)($_POST['invite_id'] ?? 0);
        inviteRevoke($id);
        $notice = 'Invite revoked.';
    }

    header('Location: invite-manage.php' . ($notice ? '?notice=' . urlencode($notice) : ($error ? '?error=' . urlencode($error) : '')));
    exit;
}

$notice = $notice ?: htmlspecialchars($_GET['notice'] ?? '');
$error  = $error  ?: htmlspecialchars($_GET['error']  ?? '');

$invites = inviteGetAll();

$counts = ['pending' => 0, 'accepted' => 0, 'expired' => 0];
foreach ($invites as $i) $counts[$i['invite_status']]++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Invitations — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between;
                   margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice    { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px;
                 padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;
                 padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
                color: var(--gray-400); margin: 2rem 0 .75rem; }
    .card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px;
            padding: 1.5rem; margin-bottom: 1.75rem; }
    .card h2 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0 0 .5rem; }
    .card .sub { font-size: .83rem; color: var(--gray-500); margin-bottom: 1rem; }
    .field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase;
                   letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .75rem;
               font-size: .88rem; font-family: monospace; box-sizing: border-box; resize: vertical; min-height: 120px; }
    textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }

    /* Stats row */
    .stat-row { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .stat { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
            padding: .75rem 1.1rem; flex: 1; min-width: 100px; text-align: center; }
    .stat .n { font-size: 1.8rem; font-weight: 800; color: var(--gray-800); line-height: 1; }
    .stat .l { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
               color: var(--gray-400); margin-top: .2rem; }

    /* Table */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: .84rem; }
    thead tr { background: #1a2e1a; }
    th { padding: .6rem .85rem; text-align: left; font-size: .71rem; font-weight: 700;
         text-transform: uppercase; letter-spacing: .05em; color: #fff; white-space: nowrap; }
    td { padding: .6rem .85rem; border-bottom: 1px solid var(--gray-100);
         color: var(--gray-700); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    .badge-pending  { display:inline-block;background:#fef3c7;color:#d97706;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .badge-accepted { display:inline-block;background:#dcfce7;color:#166534;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .badge-expired  { display:inline-block;background:#f1f5f9;color:#64748b;font-size:.68rem;font-weight:700;border-radius:100px;padding:.15rem .5rem; }
    .act-btn { background: none; border: 1px solid var(--gray-200); border-radius: 6px;
               padding: .25rem .55rem; font-size: .75rem; cursor: pointer; color: var(--gray-600); }
    .act-btn:hover { background: var(--accent); border-color: var(--primary); color: var(--primary); }
    .act-btn.danger:hover { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
    .hint-text { font-size: .75rem; color: var(--gray-400); margin-top: .5rem; }
    .empty-row td { text-align: center; color: var(--gray-400); padding: 2rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1 style="margin-top:.3rem;">Member Invitations</h1>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <!-- Stats -->
  <div class="stat-row">
    <div class="stat"><div class="n"><?= $counts['pending'] ?></div><div class="l">Pending</div></div>
    <div class="stat"><div class="n"><?= $counts['accepted'] ?></div><div class="l">Accepted</div></div>
    <div class="stat"><div class="n"><?= $counts['expired'] ?></div><div class="l">Expired</div></div>
    <div class="stat"><div class="n"><?= count($invites) ?></div><div class="l">Total sent</div></div>
  </div>

  <!-- Send invites -->
  <div class="sec-head">Send Invitations</div>
  <div class="card">
    <h2>Invite Members</h2>
    <p class="sub">
      Each person receives a personal, one-time link valid for 72 hours.
      Sending to someone who already has a pending invite will replace their old link with a fresh one.
    </p>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="send_invites">
      <div class="field" style="margin-bottom:1rem;">
        <label>Upload a CSV file</label>
        <input type="file" name="csv_file" accept=".csv,text/csv"
               style="display:block;border:1px solid var(--gray-300);border-radius:7px;padding:.5rem .75rem;font-size:.88rem;width:100%;box-sizing:border-box;background:#fff;">
        <p class="hint-text" style="margin-top:.35rem;">
          CSV must have an <code>email</code> column. A <code>name</code> column is optional but recommended.
          Column headers are auto-detected — exports from Google Contacts, Excel, or a plain spreadsheet all work.
        </p>
      </div>
      <div class="field" style="margin-bottom:.5rem;">
        <label>Or paste emails manually</label>
        <textarea name="invite_list" placeholder="One per line. Optionally include a name:&#10;&#10;Jane Smith, jane@example.com&#10;john@example.com&#10;Alex Johnson, alex@gmail.com"></textarea>
      </div>
      <p class="hint-text">
        You can use both at once — CSV upload and paste are merged before sending.<br>
        Members who already have accounts are automatically skipped.
        Large lists are sent at a controlled rate to avoid spam filters.
      </p>
      <div style="margin-top:1rem;">
        <button type="submit" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">
          Send Invites
        </button>
      </div>
    </form>
  </div>

  <!-- Invite list -->
  <div class="sec-head">All Invitations (<?= count($invites) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Email</th>
          <th>Name</th>
          <th>Status</th>
          <th>Sent</th>
          <th>Expires / Accepted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$invites): ?>
        <tr class="empty-row"><td colspan="6">No invitations sent yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($invites as $inv):
          $status = $inv['invite_status'];
        ?>
        <tr>
          <td><?= htmlspecialchars($inv['email']) ?></td>
          <td style="color:var(--gray-500);"><?= htmlspecialchars($inv['name'] ?? '—') ?></td>
          <td>
            <?php if ($status === 'pending'): ?>
              <span class="badge-pending">&#x23F3; Pending</span>
            <?php elseif ($status === 'accepted'): ?>
              <span class="badge-accepted">&#x2713; Accepted</span>
            <?php else: ?>
              <span class="badge-expired">Expired</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;">
            <?= date('M j, Y', strtotime($inv['created_at'])) ?>
          </td>
          <td style="font-size:.78rem;color:var(--gray-400);white-space:nowrap;">
            <?php if ($inv['accepted_at']): ?>
              Accepted <?= date('M j, Y', strtotime($inv['accepted_at'])) ?>
            <?php elseif ($status === 'expired'): ?>
              Expired <?= date('M j, Y', strtotime($inv['expires_at'])) ?>
            <?php else: ?>
              Expires <?= date('M j g:ia', strtotime($inv['expires_at'])) ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($status !== 'accepted'): ?>
            <div style="display:flex;gap:.35rem;">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action"    value="resend">
                <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                <button type="submit" class="act-btn">&#x21BA; Resend</button>
              </form>
              <?php if ($status === 'pending'): ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Revoke this invite for <?= htmlspecialchars(addslashes($inv['email'])) ?>?')">
                <input type="hidden" name="action"    value="revoke">
                <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                <button type="submit" class="act-btn danger">Revoke</button>
              </form>
              <?php endif; ?>
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

</div>
</body>
</html>
