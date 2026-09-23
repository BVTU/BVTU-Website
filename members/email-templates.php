<?php
/**
 * email-templates.php — edit the wording of the automated emails.
 *
 * Prose only. Detail tables, amounts, reference codes and the links someone
 * needs in order to approve something are generated and are not on this page,
 * so no edit here can produce an email that has lost the thing it was sent for.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/email-templates-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { header('Location: dashboard.php'); exit; }

sendPrivateHeaders();
emailTplEnsure();

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $act = $_POST['action'] ?? '';
    $key = (string)($_POST['key'] ?? '');

    if (!isset(EMAIL_TEMPLATES[$key])) {
        $error = 'Unknown email.';
    } elseif ($act === 'save') {
        $ok = emailTplSave($key, (string)($_POST['subject'] ?? ''),
                           (array)($_POST['blocks'] ?? []), $member['email']);
        $notice = $ok ? 'Wording saved.' : '';
        if (!$ok) $error = 'Could not save. The problem has been logged.';
    } elseif ($act === 'reset') {
        emailTplReset($key);
        $notice = 'Reset to the original wording.';
    }
    header('Location: email-templates.php?open=' . urlencode($key)
         . ($error ? '&error=' . urlencode($error) : '&notice=' . urlencode($notice)));
    exit;
}

$notice = htmlspecialchars(reqStr('notice'));
$error  = htmlspecialchars(reqStr('error'));
$open   = reqStr('open');

/** Stand-in values so the preview reads like a real email. */
function tplSample(string $key): array {
    $t = EMAIL_TEMPLATES[$key];
    $samples = [
        '{{name}}'        => 'Heather McKenzie',
        '{{email}}'       => 'heather@example.com',
        '{{link}}'        => 'https://bvtu.ca/members/…',
        '{{portal_url}}'  => 'https://bvtu.ca/members/dashboard.php',
        '{{ref}}'         => 'EXP-2026-014',
        '{{submitter}}'   => 'Cody Lind',
        '{{days}}'        => '2',
        '{{day_word}}'    => 'days',
        '{{collab_line}}' => 'Please also give Sam a heads-up so they can submit their own absence.',
    ];
    $out = [];
    foreach (array_keys($t['vars']) as $v) $out[$v] = $samples[$v] ?? $v;
    return $out;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Email Wording — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:900px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 .6rem; }
    .lede { font-size:.88rem;color:var(--gray-600);line-height:1.7;margin:0 0 1.4rem;max-width:64ch; }
    .notice { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#166534;margin-bottom:1rem; }
    .error-box { background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
                 font-size:.88rem;color:#991b1b;margin-bottom:1rem; }
    details.tpl { background:#fff;border:1px solid var(--gray-200);border-radius:12px;
                  padding:.9rem 1.15rem;margin-bottom:.7rem; }
    details.tpl summary { cursor:pointer;font-weight:700;color:var(--gray-800);font-size:.95rem;
                          display:flex;align-items:center;gap:.5rem;flex-wrap:wrap; }
    details.tpl .when { font-weight:400;font-size:.82rem;color:var(--gray-500); }
    .badge { font-size:.7rem;font-weight:700;border-radius:100px;padding:.1rem .5rem; }
    .badge.edited { background:#eff6ff;color:#1e40af; }
    .body { padding-top:1rem; }
    label.f { display:block;font-size:.72rem;font-weight:800;text-transform:uppercase;
              letter-spacing:.05em;color:var(--gray-500);margin:.9rem 0 .3rem; }
    input[type=text], textarea { width:100%;border:1px solid var(--gray-300);border-radius:8px;
        padding:.5rem .7rem;font-size:.9rem;font-family:inherit;box-sizing:border-box;line-height:1.6; }
    textarea { min-height:7rem;resize:vertical; }
    .vars { font-size:.8rem;color:var(--gray-600);background:var(--gray-50);
            border:1px solid var(--gray-200);border-radius:8px;padding:.6rem .8rem;margin:.9rem 0;line-height:1.9; }
    .vars code { background:#fff;border:1px solid var(--gray-200);border-radius:4px;
                 padding:.05rem .35rem;font-size:.82rem; }
    .preview { background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;
               padding:.8rem 1rem;margin:.9rem 0;font-size:.88rem;color:var(--gray-700);line-height:1.7; }
    .preview .sub { font-weight:700;color:var(--gray-800);margin-bottom:.5rem; }
    .preview pre { white-space:pre-wrap;font-family:inherit;margin:0; }
    .row { display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-top:1rem; }
    .btn-save { background:var(--primary);border:1px solid var(--primary);color:#fff;font-weight:700;
                border-radius:7px;padding:.5rem 1.1rem;font-size:.88rem;cursor:pointer;font-family:inherit; }
    .btn-reset { background:none;border:1px solid var(--gray-200);border-radius:7px;padding:.45rem .9rem;
                 font-size:.84rem;color:var(--gray-600);cursor:pointer;font-family:inherit; }
  </style>
</head>
<body>
<div class="wrap">

  <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
  <h1>Email Wording</h1>
  <p class="lede">
    The wording of the emails the site sends on its own. Everything else in each
    email &mdash; claim details, amounts, reference codes, and the links people need
    to approve or view something &mdash; is filled in automatically and is not editable
    here, so nothing you change can produce an email that has lost the thing it was
    sent for. Every email can be put back to its original wording at any time.
  </p>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= $error ?></div><?php endif; ?>

  <?php foreach (EMAIL_TEMPLATES as $key => $_def): $t = emailTpl($key); $s = tplSample($key); ?>
  <details class="tpl" <?= $open === $key ? 'open' : '' ?>>
    <summary>
      <?= htmlspecialchars($t['label']) ?>
      <?php if (!empty($t['edited'])): ?><span class="badge edited">edited</span><?php endif; ?>
      <span class="when"><?= htmlspecialchars($t['when']) ?></span>
    </summary>
    <div class="body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">

        <label class="f" for="s-<?= htmlspecialchars($key) ?>">Subject</label>
        <input type="text" id="s-<?= htmlspecialchars($key) ?>" name="subject"
               value="<?= htmlspecialchars($t['subject_live']) ?>">

        <?php foreach ($t['blocks'] as $bk => $b): ?>
        <label class="f"><?= htmlspecialchars($b['label']) ?></label>
        <textarea name="blocks[<?= htmlspecialchars($bk) ?>]"><?= htmlspecialchars($b['live']) ?></textarea>
        <?php endforeach; ?>

        <?php if ($t['vars']): ?>
        <div class="vars">
          <strong>You can use:</strong><br>
          <?php foreach ($t['vars'] as $v => $desc): ?>
            <code><?= htmlspecialchars($v) ?></code> — <?= htmlspecialchars($desc) ?><br>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="preview">
          <div class="sub">Preview &mdash; Subject: <?= htmlspecialchars(emailTplSubject($key, $s)) ?></div>
          <?php foreach (array_keys($t['blocks']) as $bk): ?>
            <?php if (($t['format'] ?? 'text') === 'html'): ?>
              <?= emailTplBlock($key, $bk, $s) ?>
            <?php else: ?>
              <pre><?= htmlspecialchars(emailTplBlock($key, $bk, $s)) ?></pre>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (($t['format'] ?? 'text') === 'html'): ?>
          <p style="font-size:.8rem;color:var(--gray-500);margin:.6rem 0 0;">
            The claim details and buttons sit between these paragraphs in the real email.
          </p>
          <?php endif; ?>
        </div>

        <div class="row">
          <button class="btn-save">Save wording</button>
        </div>
      </form>

      <?php if (!empty($t['edited'])): ?>
      <form method="POST" style="margin-top:.5rem;"
            onsubmit="return confirm('Put this email back to its original wording?');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reset">
        <input type="hidden" name="key" value="<?= htmlspecialchars($key) ?>">
        <button class="btn-reset">Reset to original</button>
      </form>
      <?php endif; ?>
    </div>
  </details>
  <?php endforeach; ?>

</div>
</body>
</html>
