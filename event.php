<?php
/**
 * event.php — public seminar details and registration.
 *
 * Open to anyone with the link. A logged-in member gets their name and email
 * filled in; everyone else types them.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/members/event-db.php';
require_once __DIR__ . '/members/exec-db.php';

evEnsureTables();
execEnsureTables();

// Establish the session now, while headers can still be sent. The CSRF field
// is emitted far down the page, and a session started after output has begun
// never sets its cookie — which would silently fail every submission from a
// visitor who is not logged in. That is most of them here.
csrfToken();

$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
$isAdmin  = $loggedIn && execIsAdmin($member['email'] ?? '');

$event = evBySlug((string)($_GET['s'] ?? ''));

// A draft is visible only to the organiser previewing it. Everyone else gets
// the same answer they would get for a slug that never existed.
if (!$event || (($event['status'] ?? '') === 'draft' && !$isAdmin)) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
}

$questions = $notFound ? [] : evQuestions((int)$event['id']);
$documents = $notFound ? [] : evDocuments((int)$event['id']);
// Only the ones actually on disk. A row whose file has gone would otherwise
// print the heading and the promise over an empty list.
$documents = array_values(array_filter($documents, function ($d) {
    return is_file(EV_DOCS_DIR . $d['stored_name']);
}));
$docsEmailed = (bool)array_filter($documents, function ($d) { return !empty($d['attach']); });
$state     = $notFound ? ['open' => false, 'why' => ''] : evRegistrationState($event);

// An organiser previewing a draft is shown the form exactly as members will
// see it — that is the point of previewing — but nothing it posts is kept.
$previewing = !$notFound && ($event['status'] ?? '') === 'draft' && $isAdmin;
if ($previewing) $state = ['open' => true, 'why' => ''];

$done      = false;      // registration accepted
$wasUpdate = false;      // ...by replacing an earlier one
$errors    = [];
$form      = [
    'name'   => $loggedIn ? ($member['name']  ?? '') : '',
    'email'  => $loggedIn ? ($member['email'] ?? '') : '',
    'school' => '',
];
$given     = [];         // what they typed, to put back on a failed submit

// A member who is already registered and opens this page again is almost
// always coming back to change an answer. Show them what they said last time,
// so submitting edits their spot instead of blanking the questions they do not
// retype. Anonymous visitors cannot be recognised until they type an email, so
// for them the change link in the confirmation email is the way back in.
$already = null;
if (!$notFound && $loggedIn && trim((string)($member['email'] ?? '')) !== '') {
    $already = evExistingRegistration((int)$event['id'], $member['email']);
    if ($already && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $form['name']   = $already['name']   ?: $form['name'];
        $form['school'] = $already['school'] ?: '';
        foreach (evAnswersOf($already) as $a) {
            $k = 'q' . (int)$a['qid'];
            $given[$k] = ($a['type'] ?? '') === 'checks'
                ? array_values((array)($a['values'] ?? []))
                : $a['value'];
        }
    }
}

if (!$notFound && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ev_submit'])) {
    $form['name']   = evStr($_POST['name'] ?? '');
    $form['email']  = evStr($_POST['email'] ?? '');
    $form['school'] = evStr($_POST['school'] ?? '');
    $given          = $_POST;

    // A field no person can see. Anything in it came from something reading
    // the HTML, so the submission is dropped — and told it succeeded, because
    // a bot that learns it failed simply tries again.
    $trapped = evStr($_POST['ev_note_2'] ?? '') !== '';

    if (!csrfValid()) {
        $errors[] = 'This page was open too long to submit safely. Please reload and try again.';
    }
    if ($previewing) {
        $errors[] = 'This is a preview of a draft. Publish the seminar before registering.';
    } elseif (!$state['open']) {
        $errors[] = $state['why'];
    }
    if ($form['name'] === '')  $errors[] = 'Please give your name.';
    if ($form['email'] === '') {
        $errors[] = 'Please give an email address, so we can send your confirmation.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email address does not look right.';
    }

    [$answers, $answerErrors] = evCollectAnswers($questions, $_POST);
    $errors = array_merge($errors, $answerErrors);

    if (!$errors && !$trapped && evRateLimited()) {
        $errors[] = 'Too many registrations from this connection in the last hour. '
                  . 'Please try again later, or email the organiser.';
    }

    if (!$errors) {
        if ($trapped) {
            $done = true;                    // say yes, record nothing
        } else {
            $existing = evExistingRegistration((int)$event['id'], $form['email']);

            // Typing an address into a public form is not proof of owning it.
            // A row is only rewritten in place for a signed-in member editing
            // their own; for anyone else the change link goes to the mailbox
            // and the registration is left untouched.
            $ownsEmail = $loggedIn
                && strtolower(trim((string)($member['email'] ?? ''))) === strtolower($form['email']);

            // $already is the row we recognised and pre-filled this form from.
            // It is theirs, so correcting the address in the box edits it
            // rather than quietly starting a second registration.
            if ($already && (!$existing || (int)$existing['id'] === (int)$already['id'])) {
                $existing  = $already;
                $ownsEmail = true;
            }

            if ($existing && !$ownsEmail) {
                evSendChangeLink($event, $existing);
                $reg = null;
            } elseif ($existing) {
                // Signing up twice is nearly always someone correcting an
                // answer, not wanting two seats.
                evUpdateRegistration((int)$existing['id'], $form, $answers);
                $reg       = evRegistrationByToken((string)$existing['cancel_token']);
                $wasUpdate = true;
            } else {
                $newId = evRegister((int)$event['id'], $form + [
                    'member_email' => $loggedIn ? ($member['email'] ?? '') : '',
                ], $answers);
                $s = getDB()->prepare("SELECT * FROM ev_registrations WHERE id = ?");
                $s->execute([$newId]);
                $reg = $s->fetch() ?: null;
            }

            if ($reg) {
                evSendConfirmation($event, $reg, $answers);
                evSendOrganiserNotice($event, $reg, $answers, $wasUpdate);
            }
            $done = true;
        }

        // Redirect after posting, so a refresh re-reads the thank-you instead
        // of registering and emailing all over again.
        startSession();
        $_SESSION['ev_done'] = ['email' => $form['email'], 'update' => $wasUpdate];
        header('Location: ' . evPublicUrl($event, false) . '&done=1');
        exit;
    }
}

// The outcome of a submit that has just redirected back here.
if (!$notFound && ($_GET['done'] ?? '') === '1') {
    startSession();
    if (!empty($_SESSION['ev_done'])) {
        $done          = true;
        $form['email'] = (string)$_SESSION['ev_done']['email'];
        $wasUpdate     = !empty($_SESSION['ev_done']['update']);
        unset($_SESSION['ev_done']);
    }
}

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title><?= $notFound ? 'Seminar not found' : $h($event['title']) ?> — BVTU</title>
  <?php if (!$notFound): ?>
  <meta name="description" content="<?= $h($event['summary'] ?: ($event['title'] . ' — register with the Bulkley Valley Teachers\' Union.')) ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
  <style>
    .ev-grid { display: grid; grid-template-columns: 1fr 340px; gap: 2.5rem; align-items: start; }
    @media (max-width: 880px) { .ev-grid { grid-template-columns: 1fr; gap: 1.75rem; } }

    .ev-facts { list-style: none; margin: 0 0 1.75rem; padding: 0; }
    .ev-facts li { display: flex; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--gray-100); font-size: .95rem; }
    .ev-facts li:last-child { border-bottom: 0; }
    .ev-facts .k { flex: 0 0 5.5rem; font-weight: 700; color: var(--gray-500); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; padding-top: .15rem; }
    .ev-facts .v { flex: 1 1 auto; }

    .ev-body { font-size: 1.02rem; line-height: 1.7; }
    .ev-body p { margin: 0 0 1rem; }

    .ev-docs { margin-top: 2rem; border-top: 1px solid var(--gray-100); padding-top: 1.5rem; }
    .ev-docs h2 { font-size: 1.05rem; margin: 0 0 .3rem; color: var(--primary); }
    .ev-docs-lead { font-size: .9rem; color: var(--gray-600); margin: 0 0 1rem; line-height: 1.6; }
    .ev-doc-list { list-style: none; margin: 0; padding: 0; }
    .ev-doc-list li { margin-bottom: .6rem; }
    .ev-doc-list a { display: flex; gap: .85rem; align-items: flex-start; text-decoration: none;
                     border: 1px solid var(--border); border-radius: 8px; padding: .8rem .9rem;
                     background: #fff; color: inherit; transition: border-color .15s ease, background .15s ease; }
    .ev-doc-list a:hover { border-color: var(--primary); background: var(--accent); text-decoration: none; }
    .ev-doc-ext { flex: 0 0 auto; font-size: .64rem; font-weight: 800; letter-spacing: .06em;
                  background: var(--primary); color: #fff; border-radius: 4px; padding: .22rem .4rem;
                  margin-top: .1rem; }
    .ev-doc-main { flex: 1 1 auto; min-width: 0; }
    .ev-doc-name { display: block; font-weight: 700; font-size: .95rem; }
    .ev-doc-note { display: block; font-size: .85rem; color: var(--gray-600); margin-top: .15rem; }
    .ev-doc-meta { display: block; font-size: .78rem; color: var(--gray-500); margin-top: .2rem; }

    .ev-form { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; position: sticky; top: 1.5rem; }
    @media (max-width: 880px) { .ev-form { position: static; } }
    .ev-form h2 { font-size: 1.05rem; margin: 0 0 1.1rem; color: var(--primary); }
    .ev-field { margin-bottom: 1.1rem; }
    .ev-field label.lbl { display: block; font-weight: 700; font-size: .86rem; margin-bottom: .3rem; }
    .ev-field .req { color: #b3261e; }
    .ev-field .help { font-size: .8rem; color: var(--gray-500); margin: -.1rem 0 .35rem; }
    .ev-field input[type=text], .ev-field input[type=email], .ev-field input[type=number], .ev-field textarea {
      width: 100%; padding: .55rem .65rem; border: 1px solid var(--border); border-radius: 6px; font: inherit; font-size: .95rem;
    }
    .ev-field textarea { min-height: 5.5rem; resize: vertical; }
    .ev-opt { display: flex; gap: .55rem; align-items: flex-start; padding: .3rem 0; font-size: .93rem; }
    .ev-opt input { margin-top: .25rem; flex: 0 0 auto; }

    /* The honeypot. Hidden from sight and from assistive technology, so no
       real person is ever asked to leave it alone. */
    .ev-trap { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

    .ev-ok-soft { background: #eef7f1; border: 1px solid #b8ddc5; border-radius: 6px; padding: .7rem .85rem; font-size: .85rem; margin-bottom: 1.1rem; line-height: 1.5; }
    .ev-note { font-size: .78rem; color: var(--gray-500); margin-top: .9rem; line-height: 1.5; }
    .ev-done { background: #eef7f1; border: 1px solid #b8ddc5; border-radius: 10px; padding: 1.5rem; }
    .ev-done h2 { color: var(--primary); font-size: 1.1rem; margin: 0 0 .6rem; }
    .ev-shut { background: var(--off-white); border: 1px solid var(--border); border-radius: 10px; padding: 1.25rem; font-size: .95rem; color: var(--gray-600); }
    .ev-draft { background: #fff8e1; border: 1px solid #f0d58c; border-radius: 8px; padding: .8rem 1rem; font-size: .88rem; margin-bottom: 1.5rem; }
    .ev-errs { background: #fdecea; border: 1px solid #f5c6c2; border-radius: 8px; padding: .9rem 1.1rem; margin-bottom: 1.25rem; font-size: .9rem; }
    .ev-errs ul { margin: .4rem 0 0; padding-left: 1.2rem; }
  </style>
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

<?php if ($notFound): ?>

  <section class="page-hero">
    <div class="container"><h1>Seminar not found</h1></div>
  </section>
  <main class="page-content">
    <div class="container">
      <p>That link does not match a seminar we have listed. It may have been taken down,
         or the address may be mistyped.</p>
      <p><a href="contact.php">Get in touch</a> and we will point you the right way.</p>
    </div>
  </main>

<?php else: ?>

  <section class="page-hero">
    <div class="container">
      <h1><?= $h($event['title']) ?></h1>
      <?php if ($event['summary']): ?><p><?= $h($event['summary']) ?></p><?php endif; ?>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <?php if (($event['status'] ?? '') === 'draft'): ?>
      <div class="ev-draft">
        <strong>Draft preview.</strong> Only you can see this. Nobody else can reach
        this link until you publish it.
      </div>
      <?php endif; ?>

      <div class="ev-grid">
        <div>
          <ul class="ev-facts">
            <li><span class="k">When</span><span class="v"><?= $h(evWhen($event)) ?></span></li>
            <?php if ($event['location']): ?>
            <li><span class="k">Where</span><span class="v"><?= $h($event['location']) ?></span></li>
            <?php endif; ?>
          </ul>

          <?php if (trim((string)$event['description']) !== ''): ?>
          <div class="ev-body">
            <?php foreach (preg_split('/\n\s*\n/', trim($event['description'])) as $para): ?>
            <p><?= nl2br($h(trim($para))) ?></p>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if ($documents): ?>
          <div class="ev-docs">
            <h2>Before you come</h2>
            <p class="ev-docs-lead">
              <?php $one = count($documents) === 1; ?>
              <?= $one ? 'This is the handout' : 'These are the handouts' ?> for this seminar.
              You can read <?= $one ? 'it' : 'them' ?> now<?php
                /* Only promised when something is actually set to go out. */
                if ($docsEmailed): ?>, and we will email
              <?= $one ? 'it' : 'them' ?> to you before the day<?php endif; ?>.
            </p>
            <ul class="ev-doc-list">
              <?php foreach ($documents as $d):
                    $isPdf = strtolower((string)$d['ext']) === 'pdf'; ?>
              <li>
                <a href="event-doc.php?s=<?= $h(urlencode($event['slug'])) ?>&amp;d=<?= (int)$d['id'] ?>"
                   <?= $isPdf ? 'target="_blank" rel="noopener"' : '' ?>>
                  <span class="ev-doc-ext"><?= $h(strtoupper($d['ext'])) ?></span>
                  <span class="ev-doc-main">
                    <span class="ev-doc-name"><?= $h(evDocLabel($d)) ?></span>
                    <?php if (trim((string)$d['note']) !== ''): ?>
                    <span class="ev-doc-note"><?= $h($d['note']) ?></span>
                    <?php endif; ?>
                    <span class="ev-doc-meta">
                      <?= $h(evHumanBytes((int)$d['bytes'])) ?><?= $isPdf ? ' · opens in your browser' : ' · downloads' ?>
                    </span>
                  </span>
                </a>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </div>

        <div>
          <?php if ($done): ?>
            <div class="ev-done">
              <?php if ($wasUpdate): ?>
                <h2>Your answers are updated</h2>
                <p style="margin:0;">
                  We have emailed <strong><?= $h($form['email']) ?></strong> a copy,
                  with a link you can use to change them again or cancel.
                </p>
              <?php else: ?>
                <h2>Check your email</h2>
                <p style="margin:0;">
                  We have sent a message to <strong><?= $h($form['email']) ?></strong> with
                  everything you need — your confirmation, and a link to change your
                  answers or cancel.
                </p>
              <?php endif; ?>
            </div>
          <?php elseif (!$state['open']): ?>
            <div class="ev-shut"><?= $h($state['why']) ?></div>
          <?php else: ?>

            <form method="POST" class="ev-form" novalidate>
              <?= csrfField() ?>
              <h2>Register</h2>

              <?php if ($previewing): ?>
              <div class="ev-draft" style="margin-bottom:1.1rem;">
                This is how the form will look. Nothing submitted here is kept.
              </div>
              <?php endif; ?>

              <?php if ($already): ?>
              <div class="ev-ok-soft">
                You registered for this on
                <?= $h(date('M j', strtotime($already['created_at']))) ?>. Your answers are
                below — change what you need and submit again to update your spot.
              </div>
              <?php endif; ?>

              <?php if ($errors): ?>
              <div class="ev-errs">
                Please check the following:
                <ul><?php foreach ($errors as $e): ?><li><?= $h($e) ?></li><?php endforeach; ?></ul>
              </div>
              <?php endif; ?>

              <div class="ev-field">
                <label class="lbl" for="ev-name">Your name <span class="req">*</span></label>
                <input type="text" id="ev-name" name="name" value="<?= $h($form['name']) ?>" required>
              </div>

              <div class="ev-field">
                <label class="lbl" for="ev-email">Email <span class="req">*</span></label>
                <input type="email" id="ev-email" name="email" value="<?= $h($form['email']) ?>" required>
              </div>

              <div class="ev-field">
                <label class="lbl" for="ev-school">School or worksite</label>
                <input type="text" id="ev-school" name="school" value="<?= $h($form['school']) ?>">
              </div>

              <?php foreach ($questions as $q):
                    $key  = 'q' . (int)$q['id'];
                    $opts = evQuestionOptions($q);
                    $was  = $given[$key] ?? ($q['qtype'] === 'checks' ? [] : '');
              ?>
              <div class="ev-field">
                <label class="lbl"<?= in_array($q['qtype'], EV_TYPES_WITH_OPTIONS, true) ? '' : ' for="' . $h($key) . '"' ?>>
                  <?= $h($q['label']) ?><?= $q['required'] ? ' <span class="req">*</span>' : '' ?>
                </label>
                <?php if ($q['help_text']): ?>
                <p class="help"><?= $h($q['help_text']) ?></p>
                <?php endif; ?>

                <?php if ($q['qtype'] === 'long'): ?>
                  <textarea id="<?= $h($key) ?>" name="<?= $h($key) ?>"><?= $h(is_array($was) ? '' : $was) ?></textarea>

                <?php elseif ($q['qtype'] === 'number'): ?>
                  <input type="number" step="any" id="<?= $h($key) ?>" name="<?= $h($key) ?>" value="<?= $h(is_array($was) ? '' : $was) ?>">

                <?php elseif ($q['qtype'] === 'choice'): ?>
                  <?php foreach ($opts as $i => $opt): ?>
                  <label class="ev-opt">
                    <input type="radio" name="<?= $h($key) ?>" value="<?= $h($opt) ?>"<?= (!is_array($was) && $was === $opt) ? ' checked' : '' ?>>
                    <span><?= $h($opt) ?></span>
                  </label>
                  <?php endforeach; ?>

                <?php elseif ($q['qtype'] === 'checks'): ?>
                  <?php $picked = is_array($was) ? $was : []; ?>
                  <?php foreach ($opts as $opt): ?>
                  <label class="ev-opt">
                    <input type="checkbox" name="<?= $h($key) ?>[]" value="<?= $h($opt) ?>"<?= in_array($opt, $picked, true) ? ' checked' : '' ?>>
                    <span><?= $h($opt) ?></span>
                  </label>
                  <?php endforeach; ?>

                <?php else: ?>
                  <input type="text" id="<?= $h($key) ?>" name="<?= $h($key) ?>" value="<?= $h(is_array($was) ? '' : $was) ?>">
                <?php endif; ?>
              </div>
              <?php endforeach; ?>

              <div class="ev-trap" aria-hidden="true">
                <label for="ev-note-2">Leave this field empty</label>
                <input type="text" id="ev-note-2" name="ev_note_2" tabindex="-1" autocomplete="off">
              </div>

              <button type="submit" name="ev_submit" value="1" class="btn btn-primary" style="width:100%;">
                Register
              </button>

              <p class="ev-note">
                We use your answers to run this seminar and nothing else. You will get a
                confirmation email with a link to change your answers or cancel your spot.
              </p>
            </form>

          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>

<?php endif; ?>

</body>
</html>
