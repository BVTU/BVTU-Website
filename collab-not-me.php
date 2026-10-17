<?php
/**
 * collab-not-me.php — the link at the foot of a collaborator's email.
 *
 * Somebody has been named on an application they did not fill in. This is how
 * they say that is wrong, without an account and without having to find the
 * right person to email.
 *
 * It records and reports. It changes nothing about the application: whether
 * the days still stand is a decision for a person, not for a link.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/collab-grant-db.php';

csrfToken();   // session established before any output, so the form can be checked

// The token arrives in the link, and again in the form when they submit.
$token = reqStr('t') !== '' ? reqStr('t') : reqPostStr('t');
$app   = $token !== '' ? cgAppByCollabToken($token) : null;

$done   = false;
$errors = [];

if ($app && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $errors[] = 'This page was open too long to submit safely. Please reload and try again.';
    } elseif (!empty($app['collab_disputed_at'])) {
        $done = true;   // already told us; saying it twice changes nothing
    } else {
        cgCollabDispute((int)$app['id'], reqPostStr('note'));
        $done = true;
    }
}

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Collaboration grant — BVTU</title>
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .nm-wrap { max-width: 36rem; }
    .nm-card { background:#fff; border:1px solid var(--border); border-radius:10px; padding:1.75rem; }
    .nm-card h1 { font-size:1.25rem; margin:0 0 .6rem; }
    .nm-facts { list-style:none; margin:0 0 1.4rem; padding:0; font-size:.95rem; }
    .nm-facts li { padding:.4rem 0; border-bottom:1px solid var(--gray-100); }
    .nm-facts li:last-child { border-bottom:0; }
    .nm-field { margin-bottom:1.1rem; }
    .nm-field label { display:block; font-weight:700; font-size:.86rem; margin-bottom:.3rem; }
    .nm-field textarea { width:100%; min-height:5rem; padding:.55rem .65rem; border:1px solid var(--border);
                         border-radius:6px; font:inherit; font-size:.94rem; resize:vertical; }
    .nm-ok  { background:#eef7f1; border:1px solid #b8ddc5; border-radius:8px; padding:1rem 1.2rem; }
    .nm-bad { background:#fdecea; border:1px solid #f5c6c2; border-radius:8px; padding:.85rem 1.1rem; margin-bottom:1rem; font-size:.9rem; }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <main class="page-content">
    <div class="container nm-wrap">

<?php if (!$app): ?>
      <div class="nm-card">
        <h1>We could not find that link</h1>
        <p>It may be incomplete — check you copied the whole address from the email.
           If you would rather just tell us directly,
           <a href="contact.php">get in touch</a>.</p>
      </div>

<?php elseif ($done): ?>
      <div class="nm-card">
        <h1>Thank you — that is noted</h1>
        <div class="nm-ok">
          <p style="margin:0 0 .6rem;">The BVTU President has been told that you were named on
             this application and that it is not right.</p>
          <p style="margin:0;">Nothing will be booked in your name without someone speaking
             to you first.</p>
        </div>
      </div>

<?php else: ?>
      <div class="nm-card">
        <h1>Not you?</h1>
        <p>
          <strong><?= $h($app['applicant_name']) ?></strong> named
          <strong><?= $h($app['collaborator_name']) ?></strong> as their collaborator on a BVTU
          Collaboration Grant, and it has been approved.
        </p>

        <ul class="nm-facts">
          <?php if (trim((string)$app['collaboration_desc']) !== ''): ?>
          <li><?= $h(mb_substr($app['collaboration_desc'], 0, 300)) ?></li>
          <?php endif; ?>
          <li><?= (int)cgEffectiveDays($app) ?> release
              <?= cgEffectiveDays($app) === 1 ? 'day' : 'days' ?> each</li>
        </ul>

        <p>If that is not you, or it is not something you agreed to, tell us here and we will
           sort it out. You do not need an account and nothing happens to you either way.</p>

        <?php foreach ($errors as $e): ?>
        <div class="nm-bad"><?= $h($e) ?></div>
        <?php endforeach; ?>

        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="t" value="<?= $h($token) ?>">
          <div class="nm-field">
            <label for="nm-note">Anything you want to add?</label>
            <textarea id="nm-note" name="note" maxlength="2000"
                      placeholder="Optional — e.g. &quot;I think they meant a different Sam&quot;"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">This is not me</button>
        </form>
      </div>
<?php endif; ?>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

</body>
</html>
