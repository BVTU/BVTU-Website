<?php
/**
 * bctf-forms.php — collect new members' BCTF paperwork and send it in one email.
 *
 * Scan the QR, photograph each form on the phone, then send the batch to
 * membership@bctf.ca. Replaces photographing forms, transferring them to a
 * computer, renaming them and attaching them by hand.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/bctf-db.php';

requireLogin();
$member = getMember();

if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

bctfEnsureTables();
$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'rename') {
        bctfRenameForm((int)($_POST['id'] ?? 0), trim($_POST['last_name'] ?? ''));
        $notice = 'Name updated.';
    }

    if ($action === 'delete') {
        bctfDeleteForm((int)($_POST['id'] ?? 0));
        $notice = 'Form removed.';
    }

    if ($action === 'send') {
        $forms = bctfGetPending();
        $unnamed = array_filter($forms, function ($f) { return trim($f['last_name']) === ''; });

        if (!$forms) {
            $error = 'There are no forms to send.';
        } elseif ($unnamed) {
            $error = 'Every form needs a surname before sending — ' . count($unnamed) . ' still blank.';
        } else {
            $names = bctfAttachmentNames($forms);
            $attachments = [];
            $missing = [];
            foreach ($forms as $f) {
                $path = BCTF_FORMS_DIR . basename($f['saved_path']);
                if (file_exists($path)) {
                    $attachments[] = ['path' => $path, 'name' => $names[(int)$f['id']]];
                } else {
                    $missing[] = $names[(int)$f['id']];
                }
            }

            if (!$attachments) {
                $error = 'The photos for these forms are missing from the server.';
            } else {
                $lines = [];
                foreach ($attachments as $a) $lines[] = '  ' . $a['name'];
                $body = count($attachments) . ' membership form'
                      . (count($attachments) === 1 ? '' : 's') . " attached.\n\n"
                      . implode("\n", $lines) . "\n\n"
                      . "Sent from the BVTU member portal on behalf of "
                      . $member['name'] . ", Local 54 President.\n"
                      . "Please reply to " . BCTF_REPLY_TO . " with any questions.";

                require_once __DIR__ . '/smtp.php';
                $sent = siteMailWithAttachments(
                    BCTF_TO_ADDRESS,
                    BCTF_SUBJECT,
                    $body,
                    $attachments,
                    BCTF_REPLY_TO,
                    'BVTU Local 54 President',
                    'BVTU Local 54'
                );

                if ($sent) {
                    bctfMarkSent(array_column($forms, 'id'));
                    $notice = count($attachments) . ' form'
                            . (count($attachments) === 1 ? '' : 's') . ' sent to ' . BCTF_TO_ADDRESS . '.'
                            . ($missing ? ' ' . count($missing) . ' could not be attached (photo missing).' : '');
                } else {
                    $error = 'The email could not be sent. Nothing was cleared — check the Email Log and try again.';
                }
            }
        }
    }

    if (!$error) {
        header('Location: bctf-forms.php' . ($notice ? '?notice=' . urlencode($notice) : ''));
        exit;
    }
}

$notice = $notice ?: htmlspecialchars($_GET['notice'] ?? '');
$forms  = bctfGetPending();
$names  = bctfAttachmentNames($forms);
$sentBatches = bctfGetSentBatches();

$token = bctfCreateUploadToken($member['email']);
$host  = $_SERVER['HTTP_HOST'] ?? 'bvtu.ca';
$mobileUrl = 'https://' . $host . '/members/bctf-mobile.php?token=' . $token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Membership Forms — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:840px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .page-header h1 { font-size:1.35rem; font-weight:800; color:var(--gray-800); margin:.3rem 0 0; }
    .back-link { font-size:.85rem; color:var(--primary); text-decoration:none; }
    .back-link:hover { text-decoration:underline; }
    .notice { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem 1rem;
              font-size:.88rem;color:#166534;margin:1rem 0; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.75rem 1rem;
              font-size:.88rem;color:#991b1b;margin:1rem 0; }
    h2.sec { font-size:1rem;font-weight:800;color:var(--gray-800);margin:2rem 0 .75rem;
             padding-bottom:.4rem;border-bottom:2px solid var(--accent); }

    .qr-row { display:flex; gap:1.5rem; align-items:center; background:#fff;
              border:1px solid var(--gray-200); border-radius:12px; padding:1.25rem; flex-wrap:wrap; }
    .qr-row img { border-radius:8px; }
    .qr-row .txt { flex:1; min-width:220px; }
    .qr-row h3 { margin:0 0 .3rem; font-size:.95rem; font-weight:800; color:var(--gray-800); }
    .qr-row p { margin:0; font-size:.85rem; color:var(--gray-500); line-height:1.6; }

    table { width:100%; border-collapse:collapse; background:#fff;
            border:1px solid var(--gray-200); border-radius:12px; overflow:hidden; font-size:.86rem; }
    thead tr { background:#1a2e1a; }
    th { padding:.6rem .85rem; text-align:left; font-size:.71rem; font-weight:700;
         text-transform:uppercase; letter-spacing:.05em; color:#fff; }
    td { padding:.55rem .85rem; border-bottom:1px solid var(--gray-100); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    td img { width:44px;height:44px;object-fit:cover;border-radius:5px;display:block; }
    .fname { font-family:monospace; font-size:.8rem; color:var(--gray-500); }
    .needs { color:#b45309; font-weight:700; font-size:.8rem; }
    .auto-tag { display:inline-block;background:#e0e7ff;color:#3730a3;font-size:.62rem;
                font-weight:800;border-radius:100px;padding:.05rem .4rem;margin-left:.3rem;
                text-transform:uppercase;letter-spacing:.03em; }
    input.nm { border:1px solid var(--gray-300);border-radius:6px;padding:.3rem .5rem;
               font-size:.85rem;font-family:inherit;width:150px; }
    .act-btn { background:none;border:1px solid var(--gray-200);border-radius:6px;
               padding:.25rem .55rem;font-size:.75rem;cursor:pointer;color:var(--gray-600); }
    .act-btn:hover { background:var(--accent);border-color:var(--primary);color:var(--primary); }
    .act-btn.danger:hover { background:#fef2f2;border-color:#fecaca;color:#dc2626; }

    .send-bar { display:flex;align-items:center;gap:1rem;margin-top:1rem;background:#fff;
                border:1px solid var(--gray-200);border-radius:12px;padding:1rem 1.25rem;flex-wrap:wrap; }
    .send-bar .to { font-size:.85rem;color:var(--gray-500);flex:1;min-width:220px; }
    .send-bar .to strong { color:var(--gray-800); }
    .empty { font-size:.88rem;color:var(--gray-400);font-style:italic;padding:1rem 0; }
    .hist { font-size:.82rem;color:var(--gray-500); }
    .hist li { margin-bottom:.25rem; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
    <h1>Membership Forms</h1>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <h2 class="sec">1. Photograph the forms</h2>
  <div class="qr-row">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=1a2e1a&bgcolor=ffffff&data=<?= urlencode($mobileUrl) ?>"
         width="160" height="160" alt="QR code to open the capture page on your phone">
    <div class="txt">
      <h3>Scan with your phone</h3>
      <p>
        Opens a camera page — no sign-in needed. Photograph each new member's form and it
        appears in the list below. The surname is read off the form automatically, so the
        attachment is named <span class="fname">lastname_bctf.jpg</span>; check it before sending.
      </p>
    </div>
  </div>

  <h2 class="sec">2. Check the batch<?php if ($forms): ?> <span style="font-weight:600;color:var(--gray-400);font-size:.8rem;">(<?= count($forms) ?>)</span><?php endif; ?></h2>

  <?php if (!$forms): ?>
    <p class="empty">No forms photographed yet.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr><th style="width:56px;"></th><th>Surname</th><th>Attachment name</th><th style="width:80px;"></th></tr>
    </thead>
    <tbody>
      <?php foreach ($forms as $f): ?>
      <tr>
        <td><img src="bctf-image.php?id=<?= (int)$f['id'] ?>" alt=""></td>
        <td>
          <form method="POST" style="display:flex;gap:.35rem;align-items:center;">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <input class="nm" name="last_name" value="<?= htmlspecialchars($f['last_name']) ?>"
                   placeholder="Surname" onchange="this.form.submit()">
            <?php if ($f['auto_named']): ?><span class="auto-tag">auto</span><?php endif; ?>
          </form>
          <?php if (trim($f['last_name']) === ''): ?>
            <div class="needs">Needs a surname</div>
          <?php endif; ?>
        </td>
        <td class="fname"><?= htmlspecialchars($names[(int)$f['id']]) ?></td>
        <td>
          <form method="POST" onsubmit="return confirm('Remove this form from the batch?')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button class="act-btn danger">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <h2 class="sec">3. Send to the BCTF</h2>
  <div class="send-bar">
    <div class="to">
      To <strong><?= htmlspecialchars(BCTF_TO_ADDRESS) ?></strong>,
      subject &ldquo;<?= htmlspecialchars(BCTF_SUBJECT) ?>&rdquo;.<br>
      Replies come back to <strong><?= htmlspecialchars(BCTF_REPLY_TO) ?></strong>.
    </div>
    <form method="POST"
          onsubmit="return confirm('Send <?= count($forms) ?> form(s) to <?= htmlspecialchars(BCTF_TO_ADDRESS) ?>?')">
      <input type="hidden" name="action" value="send">
      <button type="submit" class="btn btn-primary"
              style="padding:.55rem 1.2rem;font-size:.92rem;<?= $forms ? '' : 'opacity:.5;' ?>"
              <?= $forms ? '' : 'disabled' ?>>
        &#x2709; Send <?= count($forms) ?> form<?= count($forms) === 1 ? '' : 's' ?>
      </button>
    </form>
  </div>

  <?php if ($sentBatches): ?>
  <h2 class="sec">Previously sent</h2>
  <ul class="hist">
    <?php foreach ($sentBatches as $b): ?>
    <li><?= date('M j, Y \a\t g:ia', strtotime($b['sent_at'])) ?> &mdash;
        <?= (int)$b['n'] ?> form<?= (int)$b['n'] === 1 ? '' : 's' ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

</div>
</body>
</html>
