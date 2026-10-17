<?php
/**
 * event-cancel.php — let a registrant change their answers or cancel their
 * spot from the link in their confirmation email, with no login.
 *
 * The token in the link is the credential, so it is the only thing that
 * identifies the registration.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/event-db.php';

evEnsureTables();

// Establish the session now, while headers can still be sent. The CSRF field
// is emitted far down the page, and a session started after output has begun
// never sets its cookie — which would silently fail every submission from a
// visitor who is not logged in. That is most of them here.
csrfToken();

$token = evStr($_GET['t'] ?? $_POST['t'] ?? '');
$reg   = evRegistrationByToken($token);
$event = $reg ? evGet((int)$reg['event_id']) : null;

$notice = '';
$errors = [];
$gone   = false;   // they cancelled just now

if ($reg && $event && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = evStr($_POST['action'] ?? '');

    if (!csrfValid()) {
        $errors[] = 'This page was open too long to submit safely. Please reload and try again.';
    } elseif (($reg['status'] ?? '') !== 'registered') {
        // The page below already renders the cancelled state; without this the
        // POST handler would run first and a 'save' would quietly reinstate a
        // spot somebody had given up.
        $errors[] = 'This registration has already been cancelled.';
    } elseif (evIsPast($event)) {
        $errors[] = 'This event has already taken place, so it can no longer be changed.';
    } elseif ($action === 'cancel') {
        evCancel((int)$reg['id']);
        evSendCancelNotice($event, $reg);
        $gone   = true;
        $reg    = evRegistrationByToken($token);   // re-read for the new status
    } elseif ($action === 'save') {
        $questions = evQuestions((int)$event['id']);
        $name      = evStr($_POST['name'] ?? '');
        if ($name === '') $errors[] = 'Please give your name.';

        [$answers, $answerErrors] = evCollectAnswers($questions, $_POST);
        $errors = array_merge($errors, $answerErrors);

        if (!$errors) {
            evUpdateRegistration((int)$reg['id'], [
                'name'   => $name,
                'school' => evStr($_POST['school'] ?? ''),
            ], $answers);
            $reg    = evRegistrationByToken($token);
            $notice = 'Your answers have been updated.';
        }
    }
}

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$questions = ($reg && $event) ? evQuestions((int)$event['id']) : [];
$saved     = $reg ? evAnswersOf($reg) : [];
$byAns     = [];
foreach ($saved as $a) { $byAns[(int)$a['qid']] = $a; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Your registration — BVTU</title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .evc-wrap { max-width: 620px; }
    .evc-card { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 1.75rem; }
    .evc-facts { list-style: none; margin: 0 0 1.5rem; padding: 0; font-size: .95rem; }
    .evc-facts li { padding: .45rem 0; border-bottom: 1px solid var(--gray-100); }
    .evc-facts li:last-child { border-bottom: 0; }
    .evc-field { margin-bottom: 1.1rem; }
    .evc-field label.lbl { display: block; font-weight: 700; font-size: .86rem; margin-bottom: .3rem; }
    .evc-field .req { color: #b3261e; }
    .evc-field .help { font-size: .8rem; color: var(--gray-500); margin: -.1rem 0 .35rem; }
    .evc-field input[type=text], .evc-field input[type=number], .evc-field textarea {
      width: 100%; padding: .55rem .65rem; border: 1px solid var(--border); border-radius: 6px; font: inherit; font-size: .95rem;
    }
    .evc-field textarea { min-height: 5.5rem; resize: vertical; }
    .evc-opt { display: flex; gap: .55rem; align-items: flex-start; padding: .3rem 0; font-size: .93rem; }
    .evc-opt input { margin-top: .25rem; }
    .evc-ok   { background: #eef7f1; border: 1px solid #b8ddc5; border-radius: 8px; padding: .9rem 1.1rem; margin-bottom: 1.25rem; font-size: .92rem; }
    .evc-errs { background: #fdecea; border: 1px solid #f5c6c2; border-radius: 8px; padding: .9rem 1.1rem; margin-bottom: 1.25rem; font-size: .9rem; }
    .evc-errs ul { margin: .4rem 0 0; padding-left: 1.2rem; }
    .evc-danger { border-top: 1px solid var(--gray-100); margin-top: 1.75rem; padding-top: 1.25rem; }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <main class="page-content">
    <div class="container evc-wrap">

<?php if (!$reg || !$event): ?>

      <h1 style="font-size:1.3rem;">We could not find that registration</h1>
      <p>The link may be incomplete, or the registration may already have been removed.
         Check that you copied the whole link from your confirmation email.</p>
      <p><a href="contact.php">Get in touch</a> if you would like a hand.</p>

<?php elseif (($reg['status'] ?? '') === 'cancelled'): ?>

      <h1 style="font-size:1.3rem;">Your spot is cancelled</h1>
      <p><?= $gone ? 'You have cancelled your spot at' : 'This registration was cancelled for' ?>
         <strong><?= $h($event['title']) ?></strong>.</p>
      <p>Changed your mind? You can register again from
         <a href="<?= $h(evPublicUrl($event, false)) ?>">the event page</a>.</p>

<?php else: ?>

      <h1 style="font-size:1.3rem;">Your registration</h1>

      <div class="evc-card">
        <ul class="evc-facts">
          <li><strong><?= $h($event['title']) ?></strong></li>
          <li><?= $h(evWhen($event)) ?></li>
          <?php if ($event['location']): ?><li><?= $h($event['location']) ?></li><?php endif; ?>
          <li>Registered as <?= $h($reg['email']) ?></li>
        </ul>

        <?php if ($notice): ?><div class="evc-ok"><?= $h($notice) ?></div><?php endif; ?>
        <?php if ($errors): ?>
        <div class="evc-errs">
          Please check the following:
          <ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <?php if (evIsPast($event)): ?>
          <p style="color:var(--gray-600);font-size:.95rem;">This event has already taken
             place, so there is nothing left to change.</p>
        <?php else: ?>

        <form method="POST" novalidate>
          <?= csrfField() ?>
          <input type="hidden" name="t" value="<?= $h($token) ?>">

          <div class="evc-field">
            <label class="lbl" for="evc-name">Your name <span class="req">*</span></label>
            <input type="text" id="evc-name" name="name" value="<?= $h($_POST['name'] ?? $reg['name']) ?>" required>
          </div>

          <div class="evc-field">
            <label class="lbl" for="evc-school">School or worksite</label>
            <input type="text" id="evc-school" name="school" value="<?= $h($_POST['school'] ?? $reg['school']) ?>">
          </div>

          <?php foreach ($questions as $q):
                $key  = 'q' . (int)$q['id'];
                $opts = evQuestionOptions($q);
                // What they answered before, unless this submit changed it.
                if (array_key_exists($key, $_POST)) {
                    $was = $_POST[$key];
                } else {
                    $prevA = $byAns[(int)$q['id']] ?? null;
                    $was   = $q['qtype'] === 'checks'
                        ? (array)($prevA['values'] ?? [])
                        : (string)($prevA['value'] ?? '');
                }
          ?>
          <div class="evc-field">
            <label class="lbl"<?= in_array($q['qtype'], EV_TYPES_WITH_OPTIONS, true) ? '' : ' for="' . $h($key) . '"' ?>>
              <?= $h($q['label']) ?><?= $q['required'] ? ' <span class="req">*</span>' : '' ?>
            </label>
            <?php if ($q['help_text']): ?><p class="help"><?= $h($q['help_text']) ?></p><?php endif; ?>

            <?php if ($q['qtype'] === 'long'): ?>
              <textarea id="<?= $h($key) ?>" name="<?= $h($key) ?>"><?= $h(is_array($was) ? '' : $was) ?></textarea>
            <?php elseif ($q['qtype'] === 'number'): ?>
              <input type="number" step="any" id="<?= $h($key) ?>" name="<?= $h($key) ?>" value="<?= $h(is_array($was) ? '' : $was) ?>">
            <?php elseif ($q['qtype'] === 'choice'): ?>
              <?php foreach ($opts as $opt): ?>
              <label class="evc-opt">
                <input type="radio" name="<?= $h($key) ?>" value="<?= $h($opt) ?>"<?= (!is_array($was) && $was === $opt) ? ' checked' : '' ?>>
                <span><?= $h($opt) ?></span>
              </label>
              <?php endforeach; ?>
            <?php elseif ($q['qtype'] === 'checks'): ?>
              <?php $picked = is_array($was) ? $was : []; ?>
              <?php foreach ($opts as $opt): ?>
              <label class="evc-opt">
                <input type="checkbox" name="<?= $h($key) ?>[]" value="<?= $h($opt) ?>"<?= in_array($opt, $picked, true) ? ' checked' : '' ?>>
                <span><?= $h($opt) ?></span>
              </label>
              <?php endforeach; ?>
            <?php else: ?>
              <input type="text" id="<?= $h($key) ?>" name="<?= $h($key) ?>" value="<?= $h(is_array($was) ? '' : $was) ?>">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <button type="submit" name="action" value="save" class="btn btn-primary">Save changes</button>
        </form>

        <div class="evc-danger">
          <form method="POST" onsubmit="return confirm('Cancel your spot at this event?');">
            <?= csrfField() ?>
            <input type="hidden" name="t" value="<?= $h($token) ?>">
            <p style="font-size:.88rem;color:var(--gray-600);margin:0 0 .6rem;">
              Can no longer make it? Letting us know helps us plan.
            </p>
            <button type="submit" name="action" value="cancel" class="btn btn-outline" style="font-size:.88rem;">
              Cancel my spot
            </button>
          </form>
        </div>

        <?php endif; ?>
      </div>

<?php endif; ?>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

</body>
</html>
