<?php
/**
 * event-edit.php — the seminar's details and its custom questions.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/event-db.php';

requireLogin();
$member = getMember();

if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

evEnsureTables();

$id    = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$event = $id ? evGet($id) : null;
if (!$event) {
    header('Location: event-admin.php');
    exit;
}

$notice = ($_GET['new'] ?? '') === '1'
    ? 'Draft created. Fill in the details, add your questions, then publish.'
    : '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = evStr($_POST['action'] ?? '');

    if ($action === 'details') {
        $title = evStr($_POST['title'] ?? '');
        if ($title === '') $errors[] = 'A seminar needs a title.';

        // datetime-local gives "2026-11-12T16:00"; MySQL wants a space. The
        // field is only a hint — a browser that does not support it sends free
        // text, and anything MySQL cannot parse is a fatal error on a strict
        // server rather than a quiet null.
        $toSql = function (string $v, string $what) use (&$errors): string {
            $v = trim($v);
            if ($v === '') return '';
            $v = str_replace('T', ' ', $v);
            if (strlen($v) === 16) $v .= ':00';
            $d = DateTime::createFromFormat('Y-m-d H:i:s', $v);
            if (!$d || $d->format('Y-m-d H:i:s') !== $v) {
                $errors[] = 'The ' . $what . ' time was not a date we could read. '
                          . 'Please pick it again.';
                return '';
            }
            return $v;
        };
        $starts = $toSql(evStr($_POST['starts_at'] ?? ''), 'start');
        $ends   = $toSql(evStr($_POST['ends_at'] ?? ''), 'end');
        if ($starts && $ends && strtotime($ends) < strtotime($starts)) {
            $errors[] = 'The end time is before the start time.';
        }
        $notify = evStr($_POST['notify_email'] ?? '');
        if ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'That notification address does not look like an email address.';
        }

        if (!$errors) {
            evUpdate($id, [
                'title'           => $title,
                'summary'         => evStr($_POST['summary'] ?? ''),
                'description'     => evStr($_POST['description'] ?? ''),
                'starts_at'       => $starts,
                'ends_at'         => $ends,
                'location'        => evStr($_POST['location'] ?? ''),
                'confirm_message' => evStr($_POST['confirm_message'] ?? ''),
                'notify_email'    => $notify,
            ]);
            $notice = 'Details saved.';
            $event  = evGet($id);
        }
    } elseif ($action === 'doc-add') {
        $res = evDocAdd($id, $_FILES['doc'] ?? [], evStr($_POST['doc_title'] ?? ''),
                        evStr($_POST['doc_note'] ?? ''), $member['email']);
        if ($res['ok']) $notice = 'Handout uploaded.';
        else            $errors[] = $res['error'];
    } elseif ($action === 'doc-del') {
        evDocDelete((int)($_POST['doc_id'] ?? 0), $id);
        $notice = 'Handout removed.';
    } elseif ($action === 'doc-attach') {
        evDocSetAttach((int)($_POST['doc_id'] ?? 0), $id, ($_POST['on'] ?? '') === '1');
        $notice = 'Saved.';
    } elseif ($action === 'q-add') {
        $label = evStr($_POST['label'] ?? '');
        $type  = evStr($_POST['qtype'] ?? '') ?: 'short';
        if ($label === '') {
            $errors[] = 'Give the question some wording.';
        } elseif (in_array($type, EV_TYPES_WITH_OPTIONS, true)
                  && count(evQuestionOptions(['options' => evStr($_POST['options'] ?? '')])) < 2) {
            $errors[] = 'A choice question needs at least two options, one per line.';
        } else {
            evQuestionAdd($id, [
                'label'     => $label,
                'help_text' => evStr($_POST['help_text'] ?? ''),
                'qtype'     => $type,
                'options'   => evStr($_POST['options'] ?? ''),
                'required'  => !empty($_POST['required']),
            ]);
            $notice = 'Question added.';
        }
    } elseif ($action === 'q-save') {
        $qid   = (int)($_POST['qid'] ?? 0);
        $label = evStr($_POST['label'] ?? '');
        $type  = evStr($_POST['qtype'] ?? '') ?: 'short';
        if ($label === '') {
            $errors[] = 'Give the question some wording.';
        } elseif (in_array($type, EV_TYPES_WITH_OPTIONS, true)
                  && count(evQuestionOptions(['options' => evStr($_POST['options'] ?? '')])) < 2) {
            $errors[] = 'A choice question needs at least two options, one per line.';
        } else {
            evQuestionUpdate($qid, $id, [
                'label'     => $label,
                'help_text' => evStr($_POST['help_text'] ?? ''),
                'qtype'     => $type,
                'options'   => evStr($_POST['options'] ?? ''),
                'required'  => !empty($_POST['required']),
            ]);
            $notice = 'Question updated.';
        }
    } elseif ($action === 'q-del') {
        evQuestionDelete((int)($_POST['qid'] ?? 0), $id);
        $notice = 'Question removed. Answers already given are kept on the registrations.';
    } elseif ($action === 'q-up' || $action === 'q-down') {
        evQuestionMove((int)($_POST['qid'] ?? 0), $id, $action === 'q-up' ? -1 : 1);
    }
}

$questions = evQuestions($id);
$documents = evDocuments($id);
$signups   = count(evRegistrations($id));
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

/** DATETIME back into the value a datetime-local input wants. */
$forInput = function ($v) {
    return $v ? str_replace(' ', 'T', substr((string)$v, 0, 16)) : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit: <?= $h($event['title']) ?> — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    .adm-wrap   { max-width: 860px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
    .adm-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.5rem; }
    .adm-header h1 { font-size: 1.3rem; font-weight: 800; color: var(--primary); margin: 0; }

    .pcard { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .pcard h2 { font-size: 1rem; margin: 0 0 .35rem; color: var(--primary); }
    .pcard .sub { font-size: .84rem; color: var(--gray-500); margin: 0 0 1.25rem; line-height: 1.5; }

    .fld { margin-bottom: 1.1rem; }
    .fld label.lbl { display: block; font-weight: 700; font-size: .84rem; margin-bottom: .3rem; }
    .fld .hint { font-size: .79rem; color: var(--gray-500); margin: -.1rem 0 .35rem; }
    .fld input[type=text], .fld input[type=email], .fld input[type=datetime-local], .fld textarea, .fld select {
      width: 100%; padding: .55rem .65rem; border: 1px solid var(--border); border-radius: 6px; font: inherit; font-size: .94rem; background: #fff;
    }
    .fld textarea { min-height: 6rem; resize: vertical; }
    .two { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 620px) { .two { grid-template-columns: 1fr; gap: 0; } }
    .chk { display: flex; gap: .5rem; align-items: center; font-size: .9rem; }

    .q-item { border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-bottom: .85rem; background: var(--off-white); }
    .q-head { display: flex; gap: .75rem; align-items: flex-start; flex-wrap: wrap; }
    .q-head .q-label { flex: 1 1 14rem; font-weight: 700; font-size: .95rem; min-width: 0; }
    .q-head .q-acts { display: flex; gap: .3rem; align-items: center; }
    .q-type { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); }
    .q-req  { font-size: .72rem; font-weight: 800; color: #b3261e; }
    .q-opts { font-size: .84rem; color: var(--gray-600); margin: .5rem 0 0; }
    .q-edit { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
    .btn-sm  { font-size: .8rem; padding: .3rem .65rem; }
    .btn-icon { background:#fff; border:1px solid var(--border); border-radius:5px; width:1.8rem; height:1.8rem; cursor:pointer; font-size:.8rem; line-height:1; color:var(--gray-600); }
    .btn-icon[disabled] { opacity:.35; cursor:default; }
    .lnk-danger { background: none; border: 0; color: #b3261e; font-size: .8rem; cursor: pointer; text-decoration: underline; }

    .alert-ok  { background:#eef7f1; border:1px solid #b8ddc5; border-radius:8px; padding:.85rem 1.1rem; margin-bottom:1.25rem; font-size:.92rem; }
    .alert-bad { background:#fdecea; border:1px solid #f5c6c2; border-radius:8px; padding:.85rem 1.1rem; margin-bottom:1.25rem; font-size:.92rem; }
    .privacy { font-size:.82rem; color:var(--gray-600); background:#fff8e1; border:1px solid #f0d58c; border-radius:6px; padding:.7rem .9rem; line-height:1.55; }
    .empty { color: var(--gray-400); font-size: .92rem; padding: .5rem 0 1rem; }
  </style>
</head>
<body style="background:var(--off-white);min-height:100vh;">

  <div class="adm-wrap">
    <div class="adm-header">
      <h1><?= $h($event['title']) ?></h1>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="../<?= $h(evPublicUrl($event, false)) ?>" target="_blank" class="btn btn-outline btn-sm">
          <?= $event['status'] === 'draft' ? 'Preview' : 'View page' ?>
        </a>
        <a href="event-signups.php?id=<?= $id ?>" class="btn btn-outline btn-sm"><?= $signups ?> registered</a>
        <a href="event-admin.php" class="btn btn-primary btn-sm">All seminars</a>
      </div>
    </div>

    <?php if ($notice): ?><div class="alert-ok"><?= $h($notice) ?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert-bad"><?= $h($e) ?></div><?php endforeach; ?>

    <!-- ── Details ───────────────────────────────────────────────────────── -->
    <div class="pcard">
      <h2>Details</h2>
      <p class="sub">What people see on the seminar page.</p>

      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="details">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="fld">
          <label class="lbl" for="f-title">Title</label>
          <input type="text" id="f-title" name="title" value="<?= $h($event['title']) ?>" required>
        </div>

        <div class="fld">
          <label class="lbl" for="f-summary">One-line summary</label>
          <p class="hint">Sits under the title, and is what search engines show.</p>
          <input type="text" id="f-summary" name="summary" maxlength="400" value="<?= $h($event['summary']) ?>">
        </div>

        <div class="two">
          <div class="fld">
            <label class="lbl" for="f-starts">Starts</label>
            <input type="datetime-local" id="f-starts" name="starts_at" value="<?= $h($forInput($event['starts_at'])) ?>">
          </div>
          <div class="fld">
            <label class="lbl" for="f-ends">Ends</label>
            <input type="datetime-local" id="f-ends" name="ends_at" value="<?= $h($forInput($event['ends_at'])) ?>">
          </div>
        </div>

        <div class="fld">
          <label class="lbl" for="f-location">Location</label>
          <input type="text" id="f-location" name="location" value="<?= $h($event['location']) ?>"
                 placeholder="e.g. BVTU Office, 3764 Fourth Ave, Smithers">
        </div>

        <div class="fld">
          <label class="lbl" for="f-desc">Description</label>
          <p class="hint">Leave a blank line between paragraphs.</p>
          <textarea id="f-desc" name="description" style="min-height:9rem;"><?= $h($event['description']) ?></textarea>
        </div>

        <div class="fld">
          <label class="lbl" for="f-confirm">Extra wording for the confirmation email</label>
          <p class="hint">Optional. Parking, what to bring, a cancellation deadline — whatever they
             need that is not in the details above.</p>
          <textarea id="f-confirm" name="confirm_message" style="min-height:4.5rem;"><?= $h($event['confirm_message']) ?></textarea>
        </div>

        <div class="fld">
          <label class="lbl" for="f-notify">Send me an email for each registration</label>
          <p class="hint">Leave blank to stop the notifications.</p>
          <input type="email" id="f-notify" name="notify_email" value="<?= $h($event['notify_email']) ?>">
        </div>

        <button type="submit" class="btn btn-primary">Save details</button>
      </form>
    </div>

    <!-- ── Questions ─────────────────────────────────────────────────────── -->
    <div class="pcard">
      <h2>Your questions</h2>
      <p class="sub">
        Everyone is asked their name, email and worksite already. These are the
        questions on top of that.
      </p>

      <?php if (!$questions): ?>
      <p class="empty">No questions yet. People can still register — they will just
         give their name, email and worksite.</p>
      <?php endif; ?>

      <?php foreach ($questions as $i => $q):
            $editing = (int)($_GET['q'] ?? 0) === (int)$q['id'];
            $opts    = evQuestionOptions($q); ?>
      <div class="q-item">
        <div class="q-head">
          <div class="q-label">
            <?= $h($q['label']) ?>
            <?php if ($q['required']): ?> <span class="q-req">required</span><?php endif; ?>
            <div class="q-type"><?= $h(EV_QUESTION_TYPES[$q['qtype']] ?? $q['qtype']) ?></div>
          </div>
          <div class="q-acts">
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="q-up">
              <input type="hidden" name="id"  value="<?= $id ?>">
              <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
              <button type="submit" class="btn-icon" title="Move up"<?= $i === 0 ? ' disabled' : '' ?>>&uarr;</button>
            </form>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="q-down">
              <input type="hidden" name="id"  value="<?= $id ?>">
              <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
              <button type="submit" class="btn-icon" title="Move down"<?= $i === count($questions) - 1 ? ' disabled' : '' ?>>&darr;</button>
            </form>
            <a href="?id=<?= $id ?><?= $editing ? '' : '&q=' . (int)$q['id'] ?>" class="btn btn-outline btn-sm">
              <?= $editing ? 'Close' : 'Edit' ?>
            </a>
          </div>
        </div>

        <?php if ($q['help_text']): ?>
        <p class="q-opts"><em><?= $h($q['help_text']) ?></em></p>
        <?php endif; ?>
        <?php if ($opts): ?>
        <p class="q-opts"><?= $h(implode('  ·  ', $opts)) ?></p>
        <?php endif; ?>

        <?php if ($editing): ?>
        <div class="q-edit">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="q-save">
            <input type="hidden" name="id"  value="<?= $id ?>">
            <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">

            <div class="fld">
              <label class="lbl">Question</label>
              <input type="text" name="label" value="<?= $h($q['label']) ?>" required>
            </div>
            <div class="fld">
              <label class="lbl">Helper text</label>
              <input type="text" name="help_text" value="<?= $h($q['help_text']) ?>">
            </div>
            <div class="two">
              <div class="fld">
                <label class="lbl">Answer type</label>
                <select name="qtype" class="q-type-pick">
                  <?php foreach (EV_QUESTION_TYPES as $k => $lbl): ?>
                  <option value="<?= $h($k) ?>"<?= $q['qtype'] === $k ? ' selected' : '' ?>><?= $h($lbl) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="fld" style="padding-top:1.5rem;">
                <label class="chk">
                  <input type="checkbox" name="required" value="1"<?= $q['required'] ? ' checked' : '' ?>>
                  Must be answered
                </label>
              </div>
            </div>
            <div class="fld q-opts-fld">
              <label class="lbl">Options</label>
              <p class="hint">One per line.</p>
              <textarea name="options" style="min-height:5rem;"><?= $h($q['options']) ?></textarea>
            </div>

            <div style="display:flex;gap:.75rem;align-items:center;">
              <button type="submit" class="btn btn-primary btn-sm">Save question</button>
              <a href="?id=<?= $id ?>" class="btn btn-outline btn-sm">Cancel</a>
            </div>
          </form>

          <form method="POST" style="margin-top:1rem;"
                onsubmit="return confirm('Remove this question? Answers already given stay on the registrations.');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="q-del">
            <input type="hidden" name="id"  value="<?= $id ?>">
            <input type="hidden" name="qid" value="<?= (int)$q['id'] ?>">
            <button type="submit" class="lnk-danger">Remove this question</button>
          </form>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <!-- Add a question -->
      <div class="q-item" style="background:#fff;border-style:dashed;">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="q-add">
          <input type="hidden" name="id" value="<?= $id ?>">

          <div class="fld">
            <label class="lbl" for="n-label">Add a question</label>
            <input type="text" id="n-label" name="label" required
                   placeholder="e.g. Any dietary restrictions we should know about?">
          </div>
          <div class="fld">
            <label class="lbl" for="n-help">Helper text</label>
            <p class="hint">Optional. Sits under the question in smaller type.</p>
            <input type="text" id="n-help" name="help_text">
          </div>
          <div class="two">
            <div class="fld">
              <label class="lbl" for="n-type">Answer type</label>
              <select id="n-type" name="qtype" class="q-type-pick">
                <?php foreach (EV_QUESTION_TYPES as $k => $lbl): ?>
                <option value="<?= $h($k) ?>"><?= $h($lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fld" style="padding-top:1.5rem;">
              <label class="chk"><input type="checkbox" name="required" value="1"> Must be answered</label>
            </div>
          </div>
          <div class="fld q-opts-fld" style="display:none;">
            <label class="lbl" for="n-opts">Options</label>
            <p class="hint">One per line.</p>
            <textarea id="n-opts" name="options" style="min-height:5rem;"></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-sm">Add question</button>
        </form>
      </div>

      <p class="privacy">
        Whatever you ask here, you are then holding. Answers are visible only to
        exec admins and are never shown on a public page — but if a question invites
        health or accessibility details, collect it because you will act on it, not
        just because it is interesting.
      </p>
    </div>

    <!-- ── Handouts ──────────────────────────────────────────────────────── -->
    <div class="pcard">
      <h2>Handouts</h2>
      <p class="sub">
        Shown on the seminar page so people can read them beforehand, and sent
        with the reminder email from the registrations page.
      </p>

      <?php if (!$documents): ?>
      <p class="empty">Nothing uploaded yet.</p>
      <?php endif; ?>

      <?php foreach ($documents as $d):
            $missing = !is_file(EV_DOCS_DIR . $d['stored_name']); ?>
      <div class="q-item" style="<?= $missing ? 'border-color:#f5c6c2;' : '' ?>">
        <div class="q-head">
          <div class="q-label">
            <?= $h(evDocLabel($d)) ?>
            <div class="q-type">
              <?= $h(strtoupper($d['ext'])) ?> · <?= $h(evHumanBytes((int)$d['bytes'])) ?>
              <?php if ($missing): ?> · <span style="color:#b3261e;">file missing on server</span><?php endif; ?>
            </div>
          </div>
          <div class="q-acts">
            <?php if (!$missing): ?>
            <a href="../event-doc.php?s=<?= $h(urlencode($event['slug'])) ?>&amp;d=<?= (int)$d['id'] ?>"
               target="_blank" rel="noopener" class="btn btn-outline btn-sm">Open</a>
            <?php endif; ?>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="doc-attach">
              <input type="hidden" name="id"     value="<?= $id ?>">
              <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
              <input type="hidden" name="on"     value="<?= $d['attach'] ? '0' : '1' ?>">
              <button type="submit" class="btn btn-outline btn-sm">
                <?= $d['attach'] ? 'Attached to email' : 'Not emailed' ?>
              </button>
            </form>
          </div>
        </div>
        <?php if (trim((string)$d['note']) !== ''): ?>
        <p class="q-opts"><em><?= $h($d['note']) ?></em></p>
        <?php endif; ?>
        <form method="POST" style="margin-top:.6rem;"
              onsubmit="return confirm('Remove this handout? The file is deleted from the server.');">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="doc-del">
          <input type="hidden" name="id"     value="<?= $id ?>">
          <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
          <button type="submit" class="lnk-danger">Remove</button>
        </form>
      </div>
      <?php endforeach; ?>

      <div class="q-item" style="background:#fff;border-style:dashed;">
        <form method="POST" enctype="multipart/form-data">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="doc-add">
          <input type="hidden" name="id" value="<?= $id ?>">

          <div class="fld">
            <label class="lbl" for="d-file">Add a handout</label>
            <p class="hint">
              PDF reads on any phone without downloading, so it is the one to use
              if you have a choice. Up to <?= $h(evHumanBytes(EV_DOC_MAX_BYTES)) ?> each.
            </p>
            <input type="file" id="d-file" name="doc" required
                   accept="<?= $h('.' . implode(',.', array_keys(EV_DOC_TYPES))) ?>">
          </div>
          <div class="fld">
            <label class="lbl" for="d-title">Call it</label>
            <p class="hint">Optional. Defaults to the file's own name.</p>
            <input type="text" id="d-title" name="doc_title" maxlength="255"
                   placeholder="e.g. Pre-reading: Article E.2">
          </div>
          <div class="fld">
            <label class="lbl" for="d-note">Note</label>
            <p class="hint">Optional. Shown under the handout on the seminar page.</p>
            <input type="text" id="d-note" name="doc_note" maxlength="400">
          </div>

          <button type="submit" class="btn btn-primary btn-sm">Upload</button>
        </form>
      </div>

      <?php
        $att   = evAttachableDocs($id);
        $bytes = evDocBytes($att);
      ?>
      <?php if ($att): ?>
      <p class="privacy" style="<?= $bytes > EV_MAIL_MAX_BYTES ? '' : 'background:var(--off-white);border-color:var(--border);color:var(--gray-600);' ?>">
        <?php if ($bytes > EV_MAIL_MAX_BYTES): ?>
          <strong>Too much to attach.</strong> <?= count($att) ?> handouts come to
          <?= $h(evHumanBytes($bytes)) ?>, over the <?= $h(evHumanBytes(EV_MAIL_MAX_BYTES)) ?>
          an email will reliably carry. The reminder will link to them instead of
          attaching them — switch some to "Not emailed" to get back under.
        <?php else: ?>
          <?= count($att) ?> handout<?= count($att) === 1 ? '' : 's' ?> will be attached to
          the reminder email, <?= $h(evHumanBytes($bytes)) ?> in total.
        <?php endif; ?>
      </p>
      <?php endif; ?>
    </div>

  </div>

  <script>
  // Options only matter for the two types that offer a list. Hide the box for
  // the others so nobody types options that will never be shown.
  (function () {
    var needsOptions = <?= json_encode(EV_TYPES_WITH_OPTIONS) ?>;
    function sync(select) {
      var form = select.closest('form');
      if (!form) return;
      var box = form.querySelector('.q-opts-fld');
      if (!box) return;
      box.style.display = needsOptions.indexOf(select.value) >= 0 ? '' : 'none';
    }
    var picks = document.querySelectorAll('.q-type-pick');
    for (var i = 0; i < picks.length; i++) {
      sync(picks[i]);
      picks[i].addEventListener('change', function () { sync(this); });
    }
  })();
  </script>
</body>
</html>
