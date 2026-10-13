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
