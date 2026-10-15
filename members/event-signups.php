<?php
/**
 * event-signups.php — who is coming, and the CSV of it.
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

$id    = (int)($_GET['id'] ?? 0);
$event = $id ? evGet($id) : null;
if (!$event) {
    header('Location: event-admin.php');
    exit;
}

$notice    = '';
$errors    = [];
$failedTo  = [];

// ── Send the handouts ─────────────────────────────────────────────────────────
// A real send to real people, so it is a POST, it is confirmed in the browser,
// what happened is written down, and the result is read back after a redirect
// rather than rendered straight from the POST — otherwise a refresh mails
// everybody a second time.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    if (($_POST['action'] ?? '') === 'send-materials') {
        $live = evRegistrations($id);

        if (($event['status'] ?? '') === 'draft') {
            // Links in the email resolve against the public page, and a draft
            // is 404 to everyone but an organiser. Every recipient would get a
            // dead link.
            $errors[] = 'This seminar is back in draft, so the links in the email would not '
                      . 'work for anyone. Publish it first.';
        } elseif (!$live) {
            $errors[] = 'Nobody is registered yet, so there is nobody to send to.';
        } else {
            $res = evSendMaterials($event, $member['email'], evStr($_POST['message'] ?? ''));

            $bits = ['Sent to ' . $res['sent'] . ' ' . ($res['sent'] === 1 ? 'person' : 'people')];
            if ($res['docs'] > 0) {
                $bits[] = $res['attached']
                    ? $res['docs'] . ' handout' . ($res['docs'] === 1 ? '' : 's') . ' attached'
                    : 'handouts linked rather than attached — too large to send';
            }

            startSession();
            $_SESSION['ev_sent'] = [
                'notice'    => implode(' · ', $bits) . '.',
                'failed_to' => $res['failed_to'],
            ];
            header('Location: event-signups.php?id=' . $id . '&sent=1');
            exit;
        }
    }
}

// The outcome of a send that has just redirected back here.
if (($_GET['sent'] ?? '') === '1') {
    startSession();
    if (!empty($_SESSION['ev_sent'])) {
        $notice   = (string)$_SESSION['ev_sent']['notice'];
        $failedTo = (array)($_SESSION['ev_sent']['failed_to'] ?? []);
        unset($_SESSION['ev_sent']);
    }
}

$showCancelled = ($_GET['all'] ?? '') === '1';
$questions     = evQuestions($id);

// Removing a question keeps the answers already given — the editor says so.
// Without these columns that promise is kept in the database and nowhere a
// person can see, which is the same as not keeping them.
$orphans = evOrphanAnswers($questions, evRegistrations($id, true));

// ── CSV ───────────────────────────────────────────────────────────────────────
// One column per question, in the order they are asked. Answers are read back
// by question id, so a question renamed since someone answered still lines up.
if (($_GET['csv'] ?? '') === '1') {
    $rows = evRegistrations($id, $showCancelled);

    $name = preg_replace('/[^a-z0-9]+/', '-', strtolower($event['title']));
    $name = trim((string)$name, '-') ?: 'seminar';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $name . '-registrations-'
           . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    // Excel opens a CSV as the system's legacy codepage unless it sees this.
    fwrite($out, "\xEF\xBB\xBF");

    $head = ['Registered', 'Name', 'Email', 'School or worksite', 'Status'];
    foreach ($questions as $q) $head[] = $q['label'];
    foreach ($orphans as $label)  $head[] = $label . ' (removed)';
    fputcsv($out, array_map('evCsvCell', $head));

    foreach ($rows as $r) {
        $byQid = [];
        foreach (evAnswersOf($r) as $a) $byQid[(int)$a['qid']] = $a['value'];

        $line = [
            $r['created_at'],
            $r['name'],
            $r['email'],
            $r['school'],
            $r['status'],
        ];
        foreach ($questions as $q)    $line[] = $byQid[(int)$q['id']] ?? '';
        foreach ($orphans as $qid => $_) $line[] = $byQid[$qid] ?? '';
        fputcsv($out, array_map('evCsvCell', $line));
    }
    fclose($out);
    exit;
}

$rows      = evRegistrations($id, $showCancelled);
$liveCount = count(evRegistrations($id));
$docs      = evDocuments($id);
$attach    = evAttachableDocs($id);
$attBytes  = evDocBytes($attach);
$sends     = evSends($id);
$allCount  = count(evRegistrations($id, true));
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Who's coming: <?= $h($event['title']) ?> — BVTU</title>
  <link rel="stylesheet" href="../css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?>">
  <link rel="icon" href="../favicon.ico">
  <style>
    .adm-wrap   { max-width: 1100px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
    .adm-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: .75rem; margin-bottom: .4rem; }
    .adm-header h1 { font-size: 1.3rem; font-weight: 800; color: var(--primary); margin: 0; }
    .adm-sub { font-size: .88rem; color: var(--gray-500); margin: 0 0 1.75rem; }

    .pcard { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 1.4rem; margin-bottom: 1.5rem; }
    .btn-sm { font-size: .8rem; padding: .32rem .7rem; }

    .reg-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    .reg-table th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); border-bottom: 2px solid var(--border); padding: .5rem .6rem; white-space: nowrap; }
    .reg-table td { padding: .6rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; }
    .reg-table tr.off td { opacity: .5; }
    .reg-table .nm { font-weight: 700; }
    .reg-table .em { font-size: .82rem; color: var(--gray-500); }
    .scroller { overflow-x: auto; }
    .ans { white-space: pre-wrap; max-width: 22rem; }
    .pill-off { font-size: .68rem; font-weight: 800; text-transform: uppercase; color: #8a6d3b; background: #fff8e1; border: 1px solid #f0d58c; border-radius: 4px; padding: .1rem .4rem; }
    .empty { color: var(--gray-400); text-align: center; padding: 2.5rem 0; font-size: .95rem; }
    .mailto-note { font-size: .82rem; color: var(--gray-500); margin-top: .9rem; line-height: 1.5; }
    .alert-ok  { background:#eef7f1; border:1px solid #b8ddc5; border-radius:8px; padding:.85rem 1.1rem; margin-bottom:1.25rem; font-size:.92rem; }
    .alert-bad { background:#fdecea; border:1px solid #f5c6c2; border-radius:8px; padding:.85rem 1.1rem; margin-bottom:1.25rem; font-size:.92rem; }
    .send-docs { list-style:none; margin:0 0 1rem; padding:0; }
    .send-docs li { display:flex; gap:.6rem; align-items:center; font-size:.9rem; padding:.4rem 0;
                    border-bottom:1px solid var(--gray-100); }
    .send-docs li:last-child { border-bottom:0; }
    .send-ext  { font-size:.62rem; font-weight:800; letter-spacing:.05em; background:var(--primary);
                 color:#fff; border-radius:3px; padding:.15rem .35rem; }
    .send-size { margin-left:auto; font-size:.8rem; color:var(--gray-500); white-space:nowrap; }
    .send-warn { background:#fff8e1; border:1px solid #f0d58c; border-radius:7px; padding:.7rem .9rem;
                 font-size:.85rem; color:#6b4a00; line-height:1.55; margin:0 0 1rem; }
    .send-last { font-size:.83rem; color:var(--gray-600); background:var(--off-white);
                 border-radius:6px; padding:.6rem .8rem; margin:0 0 1rem; line-height:1.55; }
  </style>
</head>
<body style="background:var(--off-white);min-height:100vh;">

  <div class="adm-wrap">
    <div class="adm-header">
      <div>
        <h1><?= $h($event['title']) ?></h1>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a href="event-edit.php?id=<?= $id ?>" class="btn btn-outline btn-sm">Edit seminar</a>
        <a href="?id=<?= $id ?>&csv=1<?= $showCancelled ? '&all=1' : '' ?>" class="btn btn-outline btn-sm">Download CSV</a>
        <a href="event-admin.php" class="btn btn-primary btn-sm">All seminars</a>
      </div>
    </div>
    <p class="adm-sub">
      <?= $h(evWhen($event)) ?><?php if ($event['location']): ?> · <?= $h($event['location']) ?><?php endif; ?>
    </p>

    <?php if ($notice): ?>
    <div class="alert-ok">&#10003; <?= $h($notice) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
    <div class="alert-bad"><?= $h($e) ?></div>
    <?php endforeach; ?>

    <?php if ($failedTo): ?>
    <div class="alert-bad">
      <strong><?= count($failedTo) ?> message<?= count($failedTo) === 1 ? '' : 's' ?> could not be
      delivered.</strong> Sending again goes to everyone, so for one or two it is usually quicker
      to email them yourself:
      <ul style="margin:.5rem 0 0;padding-left:1.2rem;">
        <?php foreach ($failedTo as $addr): ?>
        <li><a href="mailto:<?= $h($addr) ?>"><?= $h($addr) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- ── Reminder with the handouts ────────────────────────────────────── -->
    <div class="pcard">
      <h2 style="font-size:1rem;margin:0 0 .3rem;color:var(--primary);">Send everyone the handouts</h2>
      <p style="font-size:.86rem;color:var(--gray-600);margin:0 0 1rem;line-height:1.6;">
        One email each &mdash; separately, so nobody sees anyone else's address, and
        everyone keeps their own link to cancel.
      </p>

      <?php if (!$docs): ?>
        <p style="font-size:.9rem;color:var(--gray-500);margin:0 0 1rem;">
          No handouts uploaded yet. You can still send a reminder, or
          <a href="event-edit.php?id=<?= $id ?>">add handouts first</a>.
        </p>
      <?php elseif (!$attach): ?>
        <p style="font-size:.9rem;color:var(--gray-500);margin:0 0 1rem;">
          <?= count($docs) ?> handout<?= count($docs) === 1 ? ' is' : 's are' ?> uploaded but
          marked "Not emailed", so the reminder will carry none of them.
        </p>
      <?php else: ?>
        <ul class="send-docs">
          <?php foreach ($attach as $d): ?>
          <li>
            <span class="send-ext"><?= $h(strtoupper($d['ext'])) ?></span>
            <?= $h(evDocLabel($d)) ?>
            <span class="send-size"><?= $h(evHumanBytes((int)$d['bytes'])) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($attBytes > EV_MAIL_MAX_BYTES): ?>
        <p class="send-warn">
          Together these are <?= $h(evHumanBytes($attBytes)) ?>, over the
          <?= $h(evHumanBytes(EV_MAIL_MAX_BYTES)) ?> an email will reliably carry.
          Everyone will get a <strong>link</strong> to the handouts rather than the files
          themselves &mdash; so the message arrives for everybody instead of bouncing for some.
        </p>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($sends): $last = $sends[0]; ?>
      <p class="send-last">
        Last sent <?= $h(date('M j \a\t g:i a', strtotime($last['sent_at']))) ?>
        to <?= (int)$last['recipients'] ?>
        <?= (int)$last['recipients'] === 1 ? 'person' : 'people' ?><?php
          if ((int)$last['failures'] > 0): ?>, <?= (int)$last['failures'] ?> failed<?php endif; ?>.
        Sending again sends to everyone registered now, including anyone who has signed up since.
      </p>
      <?php endif; ?>

      <form method="POST" onsubmit="return confirm('Send this to <?= (int)$liveCount ?> <?= $liveCount === 1 ? 'person' : 'people' ?> now? This cannot be undone.');">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="send-materials">
        <div style="margin-bottom:.8rem;">
          <label for="sendmsg" style="display:block;font-weight:700;font-size:.84rem;margin-bottom:.3rem;">
            Anything to add?
          </label>
          <p style="font-size:.79rem;color:var(--gray-500);margin:-.1rem 0 .35rem;">
            Optional. Goes near the top, above the handouts. Parking, what to bring, a change of room.
          </p>
          <textarea id="sendmsg" name="message" maxlength="400"
                    style="width:100%;min-height:4.5rem;padding:.55rem .65rem;border:1px solid var(--border);border-radius:6px;font:inherit;font-size:.92rem;resize:vertical;"></textarea>
        </div>
        <?php $isDraft = ($event['status'] ?? '') === 'draft'; ?>
        <button type="submit" class="btn btn-primary"<?= ($liveCount === 0 || $isDraft) ? ' disabled' : '' ?>>
          Send to <?= (int)$liveCount ?> registered
        </button>
        <?php if ($isDraft): ?>
        <span style="font-size:.82rem;color:var(--gray-500);margin-left:.6rem;">
          Publish the seminar first — the links would not work.
        </span>
        <?php elseif ($liveCount === 0): ?>
        <span style="font-size:.82rem;color:var(--gray-500);margin-left:.6rem;">Nobody registered yet.</span>
        <?php endif; ?>
      </form>
    </div>

    <div class="pcard">
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.6rem;margin-bottom:1rem;">
        <h2 style="font-size:1rem;margin:0;color:var(--primary);">
          <?= $liveCount ?> registered<?php if ($allCount > $liveCount): ?>,
          <?= $allCount - $liveCount ?> cancelled<?php endif; ?>
        </h2>
        <?php if ($allCount > $liveCount): ?>
        <a href="?id=<?= $id ?><?= $showCancelled ? '' : '&all=1' ?>" class="btn btn-outline btn-sm">
          <?= $showCancelled ? 'Hide cancelled' : 'Show cancelled' ?>
        </a>
        <?php endif; ?>
      </div>

      <?php if (!$rows): ?>
      <p class="empty">Nobody has registered yet.</p>
      <?php else: ?>
      <div class="scroller">
        <table class="reg-table">
          <thead>
            <tr>
              <th>Who</th>
              <th>Worksite</th>
              <?php foreach ($questions as $q): ?>
              <th><?= $h($q['label']) ?></th>
              <?php endforeach; ?>
              <?php foreach ($orphans as $label): ?>
              <th style="color:var(--gray-400);"><?= $h($label) ?><br><span style="font-weight:400;text-transform:none;">removed</span></th>
              <?php endforeach; ?>
              <th>Registered</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
                  $byQid = [];
                  foreach (evAnswersOf($r) as $a) $byQid[(int)$a['qid']] = $a['value'];
                  $off = $r['status'] !== 'registered'; ?>
            <tr<?= $off ? ' class="off"' : '' ?>>
              <td>
                <div class="nm">
                  <?= $h($r['name']) ?>
                  <?php if ($off): ?> <span class="pill-off">cancelled</span><?php endif; ?>
                </div>
                <div class="em"><a href="mailto:<?= $h($r['email']) ?>"><?= $h($r['email']) ?></a></div>
              </td>
              <td><?= trim((string)$r['school']) === '' ? '—' : $h($r['school']) ?></td>
              <?php foreach ($questions as $q):
                    $v = (string)($byQid[(int)$q['id']] ?? ''); ?>
              <td class="ans"><?= $v === '' ? '—' : $h($v) ?></td>
              <?php endforeach; ?>
              <?php foreach ($orphans as $qid => $_):
                    $v = (string)($byQid[$qid] ?? ''); ?>
              <td class="ans" style="color:var(--gray-500);"><?= $v === '' ? '—' : $h($v) ?></td>
              <?php endforeach; ?>
              <td style="white-space:nowrap;font-size:.82rem;color:var(--gray-500);">
                <?= $r['created_at'] ? date('M j, g:i a', strtotime($r['created_at'])) : '' ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php
        // A mailto with everyone in it, for a reminder the day before. BCC so
        // sixty people do not get each other's addresses.
        $live = array_filter($rows, function ($r) { return $r['status'] === 'registered'; });
        $bcc  = implode(',', array_map(function ($r) { return $r['email']; }, $live));
      ?>
      <?php if ($bcc !== ''): ?>
      <p class="mailto-note">
        <a href="mailto:?bcc=<?= $h(rawurlencode($bcc)) ?>&subject=<?= $h(rawurlencode($event['title'])) ?>">
          Email everyone registered
        </a>
        — opens your mail program with all <?= count($live) ?> addresses in BCC, so nobody
        sees anyone else's. Long lists can trip up some mail programs; the CSV is the
        reliable fallback.
      </p>
      <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</body>
</html>
