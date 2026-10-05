<?php
/**
 * forum-admin.php — paste the YouTube link, write the chapters.
 *
 * President only. The video is not uploaded here and never touches this server;
 * see forum-db.php for why.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/forum-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}
forumEnsureTables();

/*
 * Open on whatever the public is actually seeing, not on today's school year.
 * forumCurrentYear() flips on 1 September; a forum recorded in October would
 * otherwise become uneditable overnight — the chapters would still be on the
 * live page with no screen able to reach them.
 */
$year = (int)($_GET['year'] ?? 0);
if ($year < 2000 || $year > 2100) $year = forumEditYear();
$video = forumGetForEdit($year);
$vid   = (int)$video['id'];

// What to re-show if a save is rejected, so nothing typed is lost.
$form  = ['title' => $video['title'], 'youtube' => $video['youtube_id'],
          'event_date' => (string)$video['event_date'], 'blurb' => (string)$video['blurb'],
          'published' => (int)$video['published'] === 1];

$notice = '';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        // Keep what was typed on screen whatever happens next.
        $form = ['title'      => (string)($_POST['title'] ?? ''),
                 'youtube'    => trim((string)($_POST['youtube'] ?? '')),
                 'event_date' => trim((string)($_POST['event_date'] ?? '')),
                 'blurb'      => (string)($_POST['blurb'] ?? ''),
                 'published'  => !empty($_POST['published'])];
        $raw  = trim((string)($_POST['youtube'] ?? ''));
        $ytid = $raw === '' ? '' : forumVideoId($raw);
        $date = trim((string)($_POST['event_date'] ?? ''));
        if ($raw !== '' && $ytid === '') {
            $error = 'That does not look like a YouTube link. Paste the address from the '
                   . 'browser bar, or the eleven-character video id.';
        } elseif ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $error = 'That date could not be read. Use the date picker.';
        } else {
            forumSave($vid, (string)($_POST['title'] ?? ''), $ytid, $date,
                      (string)($_POST['blurb'] ?? ''), !empty($_POST['published']));
            $notice = 'Saved.';
            if (!empty($_POST['published']) && $ytid === '') {
                $notice = 'Saved, but there is no video link yet, so the page still says '
                        . 'the recording is not published.';
            }
        }
    }

    if ($action === 'add_chapter') {
        $at = forumSeconds((string)($_POST['at'] ?? ''));
        $lb = trim((string)($_POST['label'] ?? ''));
        if ($at === null)      $error = 'Give a time like 1:04:12, or 4:12, or a number of seconds.';
        elseif ($lb === '')    $error = 'Give the chapter a label — that is the part people read.';
        else { forumAddChapter($vid, $at, $lb); $notice = 'Chapter added.'; }
    }

    if ($action === 'update_chapter') {
        $cid = (int)($_POST['chapter_id'] ?? 0);
        $at  = forumSeconds((string)($_POST['at'] ?? ''));
        $lb  = trim((string)($_POST['label'] ?? ''));
        if (!$cid || $at === null || $lb === '') {
            $error = 'A time and a label are both needed.';
        } elseif (forumUpdateChapter($cid, $vid, $at, $lb)) {
            $notice = 'Chapter updated.';
        } else {
            // Only reachable from a stale page; saying "updated" would be a lie.
            $error = 'That chapter could not be found. Reload and try again.';
        }
    }

    if ($action === 'delete_chapter') {
        $cid = (int)($_POST['chapter_id'] ?? 0);
        if ($cid && forumDeleteChapter($cid, $vid)) $notice = 'Chapter removed.';
        else $error = 'That chapter could not be found. Reload and try again.';
    }

    /*
     * Redirect after a success so a refresh does not resubmit, but stay put on
     * an error — redirecting would throw away the title, date and introduction
     * typed in the same submit, and make the president retype all of it because
     * one link was wrong.
     */
    if ($error === '') {
        header('Location: forum-admin.php?year=' . $year . ($notice ? '&notice=' . urlencode($notice) : ''));
        exit;
    }
}

$notice   = $notice ?: (string)($_GET['notice'] ?? '');
$error    = $error  ?: (string)($_GET['error']  ?? '');
$video    = forumGetForEdit($year);
$chapters = forumChapters($vid);

/*
 * Refresh the file the public pages read, on every load rather than only after
 * a save. If anyone ever edits the row directly in the database, the cache
 * repairs itself the next time this screen is opened.
 */
$liveNow = forumLatest();
forumWriteState($liveNow ?: ['published' => 0, 'youtube_id' => ''],
                $liveNow ? count(forumChapters((int)$liveNow['id'])) : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forum Recording — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 820px; margin: 0 auto; padding: 2rem 1.5rem 5rem; }
    .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
    .page-header h1 { font-size:1.25rem; font-weight:800; color:var(--gray-800); margin:.3rem 0 0; }
    .back-link { font-size:.85rem; color:var(--primary); text-decoration:none; }
    .notice    { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:.75rem 1rem; font-size:.88rem; color:#166534; margin-bottom:1.25rem; }
    .error-box { background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:.75rem 1rem; font-size:.88rem; color:#991b1b; margin-bottom:1.25rem; }
    .pcard { background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; }
    .pcard h2 { font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--gray-500); margin:0 0 1.1rem; }
    label.f { display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--gray-500); margin:0 0 .25rem; }
    input[type=text], input[type=date], textarea { width:100%; border:1px solid var(--gray-300); border-radius:7px; padding:.5rem .7rem; font-size:.9rem; font-family:inherit; box-sizing:border-box; }
    textarea { min-height:90px; resize:vertical; }
    input:focus, textarea:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(26,107,53,.1); }
    .hint { font-size:.76rem; color:var(--gray-500); margin-top:.35rem; line-height:1.55; }
    .row { margin-bottom:1rem; }
    table { width:100%; border-collapse:collapse; font-size:.88rem; }
    td { padding:.4rem .4rem .4rem 0; border-bottom:1px solid var(--gray-100); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    .at { width:110px; } .act-btn { background:none; border:1px solid var(--gray-200); border-radius:6px; padding:.25rem .55rem; font-size:.75rem; cursor:pointer; color:var(--gray-600); }
    .act-btn.danger:hover { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
    .empty { color:var(--gray-400); font-size:.88rem; padding:1rem 0; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
      <h1>Forum Recording <?= $year ?>&ndash;<?= substr((string)($year + 1), 2) ?></h1>
    </div>
    <div style="display:flex;gap:.8rem;align-items:center;">
      <?php $years = forumYears();
            if (!in_array(forumCurrentYear(), $years, true)) $years[] = forumCurrentYear();
            if (!in_array($year, $years, true)) $years[] = $year;
            rsort($years); ?>
      <?php if (count($years) > 1): ?>
      <form method="GET" style="display:flex;gap:.4rem;align-items:center;">
        <label class="f" style="margin:0;" for="yr">Year</label>
        <select id="yr" name="year" onchange="this.form.submit()"
                style="border:1px solid var(--gray-300);border-radius:7px;padding:.35rem .5rem;font-family:inherit;font-size:.85rem;">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?>&ndash;<?= substr((string)($y+1),2) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>
      <a class="back-link" href="../forum.php" target="_blank">View the public page &#8599;</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="pcard">
    <h2>The recording</h2>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save">

      <div class="row">
        <label class="f" for="t">Title</label>
        <input type="text" id="t" name="title" value="<?= htmlspecialchars($form['title']) ?>">
      </div>

      <div class="row">
        <label class="f" for="y">YouTube link</label>
        <input type="text" id="y" name="youtube"
               value="<?= htmlspecialchars($form['youtube']) ?>"
               placeholder="https://www.youtube.com/watch?v=...">
        <div class="hint">
          Paste the whole address &mdash; a watch link, a youtu.be link or an embed snippet all work.
          Upload it to YouTube as <strong>Unlisted</strong>: anyone with the link can watch and it
          embeds here, but it will not show up in YouTube search.
          <?php if ($video['youtube_id'] !== ''): ?>
            <br>Currently: <code><?= htmlspecialchars($video['youtube_id']) ?></code> &mdash;
            <a href="https://www.youtube.com/watch?v=<?= htmlspecialchars($video['youtube_id']) ?>"
               target="_blank" rel="noopener">check it plays</a>.
          <?php endif; ?>
        </div>
      </div>

      <div class="row">
        <label class="f" for="d">Date recorded</label>
        <input type="date" id="d" name="event_date" value="<?= htmlspecialchars($form['event_date']) ?>" style="max-width:220px;">
      </div>

      <div class="row">
        <label class="f" for="b">Introduction</label>
        <textarea id="b" name="blurb" placeholder="A couple of sentences above the video."><?= htmlspecialchars($form['blurb']) ?></textarea>
      </div>

      <div class="row">
        <label style="display:flex;gap:.5rem;align-items:center;font-size:.9rem;">
          <input type="checkbox" name="published" value="1" <?= $form['published'] ? 'checked' : '' ?>>
          Show this on the public page
        </label>
        <div class="hint">Until this is ticked, forum.php says the recording is not published yet.</div>
      </div>

      <button type="submit" class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;">Save</button>
    </form>
  </div>

  <div class="pcard">
    <h2>Chapters</h2>
    <p class="hint" style="margin:-.6rem 0 1rem;">
      It runs three hours. Almost nobody watches that straight through &mdash; chapters are what
      make it usable, so one line per candidate and per question is worth the time.
    </p>

    <form method="POST" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.2rem;">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_chapter">
      <div style="width:120px;">
        <label class="f" for="at">Time</label>
        <input type="text" id="at" name="at" placeholder="1:04:12" required>
      </div>
      <div style="flex:1;min-width:220px;">
        <label class="f" for="lb">Label</label>
        <input type="text" id="lb" name="label" placeholder="e.g. Opening statement — Kim Unger" required>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:.45rem 1rem;font-size:.88rem;">Add</button>
    </form>

    <?php if (!$chapters): ?>
      <p class="empty">No chapters yet.</p>
    <?php else: ?>
    <table>
      <tbody>
      <?php foreach ($chapters as $c): ?>
      <tr>
        <td colspan="3">
          <form method="POST" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_chapter">
            <input type="hidden" name="chapter_id" value="<?= (int)$c['id'] ?>">
            <input class="at" type="text" name="at" value="<?= htmlspecialchars(forumStamp((int)$c['seconds'])) ?>" style="width:110px;">
            <input type="text" name="label" value="<?= htmlspecialchars($c['label']) ?>" style="flex:1;min-width:200px;">
            <button type="submit" class="act-btn">Save</button>
          </form>
        </td>
        <td style="width:40px;text-align:right;">
          <form method="POST" onsubmit="return confirm('Remove this chapter?')">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_chapter">
            <input type="hidden" name="chapter_id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="act-btn danger">&#128465;</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
