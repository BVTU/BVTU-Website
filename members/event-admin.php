<?php
/**
 * event-admin.php — the organiser's list of seminars.
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

$notice = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            $errors[] = 'Give the event a title to start with — you can change it later.';
        } else {
            $newId = evCreate($title, $member['email']);
            header('Location: event-edit.php?id=' . $newId . '&new=1');
            exit;
        }
    } elseif ($action === 'publish' && $id) {
        evSetStatus($id, 'open');
        $notice = 'Published. The link is live.';
    } elseif ($action === 'unpublish' && $id) {
        evSetStatus($id, 'draft');
        $notice = 'Back to draft. The link no longer works for anyone else.';
    } elseif ($action === 'close' && $id) {
        evSetStatus($id, 'closed');
        $notice = 'Registration closed. The details stay visible.';
    } elseif ($action === 'delete' && $id) {
        $ev = evGet($id);
        // Counting live signups only would let a delete take cancelled
        // records with it, which is exactly what we promised to keep.
        $signups = $ev ? count(evRegistrations($id, true)) : 0;
        if ($signups > 0) {
            $errors[] = 'That event has ' . $signups . ' registration'
                      . ($signups === 1 ? '' : 's') . ' on record, including any that were '
                      . 'cancelled. Close it instead — deleting it would take their answers '
                      . 'with it.';
        } else {
            evDelete($id);
            $notice = 'Event deleted.';
        }
    }
}

$events = evAll();
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

$badge = [
    'draft'  => ['Draft',  '#8a6d3b', '#fff8e1', '#f0d58c'],
    'open'   => ['Open',   '#1a6b35', '#eef7f1', '#b8ddc5'],
    'closed' => ['Closed', '#555',    '#f2f2f2', '#ddd'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Events &amp; Registration — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    .adm-wrap   { max-width: 980px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
    .adm-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-bottom: 1.75rem; }
    .adm-header h1 { font-size: 1.4rem; font-weight: 800; color: var(--primary); margin: 0; }

    .pcard { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 1.4rem; margin-bottom: 1.5rem; }
    .pcard h2 { font-size: 1rem; margin: 0 0 1rem; color: var(--primary); }

    .ev-row { display: flex; gap: 1rem; align-items: flex-start; padding: 1.1rem 0; border-bottom: 1px solid var(--gray-100); flex-wrap: wrap; }
    .ev-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .ev-row .main { flex: 1 1 18rem; min-width: 0; }
    .ev-row .title { font-weight: 700; font-size: 1rem; }
    .ev-row .meta  { font-size: .84rem; color: var(--gray-500); margin-top: .2rem; }
    .ev-row .acts  { display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; }
    .ev-pill { display: inline-block; font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; padding: .15rem .5rem; border-radius: 4px; border: 1px solid; }
    .ev-count { font-size: .84rem; font-weight: 700; color: var(--primary); }
    .btn-sm { font-size: .8rem; padding: .32rem .7rem; }
    .lnk-danger { background: none; border: 0; color: #b3261e; font-size: .8rem; cursor: pointer; text-decoration: underline; padding: .32rem .2rem; }

    .new-form { display: flex; gap: .6rem; flex-wrap: wrap; }
    .new-form input[type=text] { flex: 1 1 18rem; padding: .55rem .7rem; border: 1px solid var(--border); border-radius: 6px; font: inherit; }

    .alert-ok   { background:#eef7f1; border:1px solid #b8ddc5; border-radius:8px; padding:.85rem 1.1rem; margin-bottom:1.25rem; font-size:.92rem; }
    .alert-bad  { background:#fdecea; border:1px solid #f5c6c2; border-radius:8px; padding:.85rem 1.1rem; margin-bottom:1.25rem; font-size:.92rem; }
    .empty { color: var(--gray-400); text-align: center; padding: 2rem 0; font-size: .95rem; }
  </style>
</head>
<body style="background:var(--off-white);min-height:100vh;">

  <div class="adm-wrap">
    <div class="adm-header">
      <h1>Events &amp; Registration</h1>
      <a href="dashboard.php" class="btn btn-primary btn-sm">Dashboard</a>
    </div>

    <?php if ($notice): ?><div class="alert-ok"><?= $h($notice) ?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert-bad"><?= $h($e) ?></div><?php endforeach; ?>

    <div class="pcard">
      <h2>Start a new event</h2>
      <form method="POST" class="new-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <input type="text" name="title" placeholder="e.g. Mental Health First Aid, or Exec meeting" required>
        <button type="submit" class="btn btn-primary">Create draft</button>
      </form>
      <p style="font-size:.82rem;color:var(--gray-500);margin:.8rem 0 0;">
        It starts as a draft. Nobody can reach it until you publish.
      </p>
    </div>

    <div class="pcard">
      <h2><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?></h2>

      <?php if (!$events): ?>
      <p class="empty">Nothing here yet. Create your first event above.</p>
      <?php endif; ?>

      <?php foreach ($events as $ev):
            [$lbl, $fg, $bg, $bd] = $badge[$ev['status']] ?? $badge['draft']; ?>
      <div class="ev-row">
        <div class="main">
          <div class="title"><?= $h($ev['title']) ?></div>
          <div class="meta">
            <span class="ev-pill" style="color:<?= $fg ?>;background:<?= $bg ?>;border-color:<?= $bd ?>;"><?= $lbl ?></span>
            &nbsp;<?= $h(evWhen($ev)) ?>
            <?php if ($ev['location']): ?> · <?= $h($ev['location']) ?><?php endif; ?>
            <?php if (evIsPast($ev)): ?> · <em>past</em><?php endif; ?>
          </div>
        </div>

        <div class="acts">
          <span class="ev-count"><?= (int)$ev['signups'] ?> registered</span>
          <a href="event-signups.php?id=<?= (int)$ev['id'] ?>" class="btn btn-outline btn-sm">Who's coming</a>
          <a href="event-edit.php?id=<?= (int)$ev['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
          <a href="../<?= $h(evPublicUrl($ev, false)) ?>" target="_blank" class="btn btn-outline btn-sm">
            <?= $ev['status'] === 'draft' ? 'Preview' : 'View' ?>
          </a>

          <?php if ($ev['status'] === 'draft'): ?>
          <form method="POST" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="publish">
            <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
            <button type="submit" class="btn btn-primary btn-sm">Publish</button>
          </form>
          <?php elseif ($ev['status'] === 'open'): ?>
          <?php /* "Close signups" on a deadline or a meeting is a button for
                   something that was never open. Taking it off the calendar is
                   the only thing that means anything there. */ ?>
          <form method="POST" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="<?= evTakesSignups($ev) ? 'close' : 'unpublish' ?>">
            <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">
              <?= evTakesSignups($ev) ? 'Close signups' : 'Unpublish' ?>
            </button>
          </form>
          <?php else: ?>
          <form method="POST" style="display:inline;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="publish">
            <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
            <button type="submit" class="btn btn-outline btn-sm">
              <?= evTakesSignups($ev) ? 'Reopen' : 'Publish again' ?>
            </button>
          </form>
          <?php endif; ?>

          <?php if ((int)$ev['signups'] === 0 && count(evRegistrations((int)$ev['id'], true)) === 0): ?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this event? It has no registrations.');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
            <button type="submit" class="lnk-danger">Delete</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</body>
</html>
