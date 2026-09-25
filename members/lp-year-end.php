<?php
/**
 * lp-year-end.php — close a school year and set up the next one.
 *
 * Nothing here moves or deletes anything. Closing a year writes a flag;
 * carrying forward copies names and amounts into the new year's own rows. The
 * old year's grants, budget lines, vouchers and expenses stay exactly where
 * they are and stay readable from the dashboard's year picker.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) {
    header('Location: lp-dashboard.php');
    exit;
}
lpEnsureTables();
lpYearStatusEnsure();

$notice = '';
$error  = '';

$years   = lpYearsWithData();
$current = lpCurrentYear();

// The year being closed: the one before the current one by default, since that
// is what a year-end is usually about.
$wanted  = isset($_GET['year']) && is_scalar($_GET['year']) ? (int)$_GET['year'] : 0;
$closing = in_array($wanted, $years, true) ? $wanted : $current;
$next    = $closing + 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $act = $_POST['action'] ?? '';
    $yr  = (int)($_POST['year'] ?? 0);
    if (!in_array($yr, $years, true) && $yr !== $current) $yr = $current;

    if ($act === 'carry_forward') {
        $to  = $yr + 1;
        $res = lpCarryForward($yr, $to);
        $bits = [];
        foreach (['grants' => 'grant', 'lines' => 'budget line'] as $k => $word) {
            list($added, $updated) = $res[$k];
            if ($added)   $bits[] = "{$added} {$word}" . ($added === 1 ? '' : 's') . ' added';
            if ($updated) $bits[] = "{$updated} {$word}" . ($updated === 1 ? '' : 's') . ' updated';
        }
        if ($res['error']) {
            $error = $res['error'];
        } else {
            $notice = $bits
                ? ucfirst(implode(', ', $bits)) . ' in ' . lpYearLabel($to) . '.'
                : 'Nothing to carry forward — ' . lpYearLabel($yr) . ' has no active grants or budget lines.';
        }
    }

    if ($act === 'close_year') {
        lpCloseYear($yr, $member['email'])
            ? $notice = lpYearLabel($yr) . ' marked closed.'
            : $error  = 'Could not close the year. The problem has been logged.';
    }
    if ($act === 'reopen_year') {
        lpReopenYear($yr)
            ? $notice = lpYearLabel($yr) . ' reopened.'
            : $error  = 'Could not reopen the year. The problem has been logged.';
    }

    $qs = 'year=' . $yr . ($error ? '&error=' . urlencode($error) : '&notice=' . urlencode($notice));
    header('Location: lp-year-end.php?' . $qs);
    exit;
}

$notice      = htmlspecialchars(isset($_GET['notice']) && is_scalar($_GET['notice']) ? (string)$_GET['notice'] : '');
$error       = htmlspecialchars(isset($_GET['error'])  && is_scalar($_GET['error'])  ? (string)$_GET['error']  : '');
$outstanding = lpYearOutstanding($closing);
$isClosed    = lpYearIsClosed($closing);
$grants      = lpGetGrants($closing);
$lines       = lpGetBudgetLines($closing);
$nextGrants  = lpGetGrants($next);
$nextLines   = lpGetBudgetLines($next);
$openCount   = count($outstanding['vouchers']) + count($outstanding['claims']) + $outstanding['collab'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Year End — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background:#f4f6f8; }
    .wrap { max-width:880px; margin:0 auto; padding:2rem 1.5rem 4rem; }
    .back-link { font-size:.85rem;color:var(--primary);text-decoration:none; }
    h1 { font-size:1.35rem;font-weight:800;color:var(--gray-800);margin:.3rem 0 1.2rem; }
    .pcard { background:#fff;border:1px solid var(--gray-200);border-radius:12px;
             padding:1.15rem 1.3rem;margin-bottom:1rem; }
    .pcard h2 { font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;
                color:var(--gray-500);margin:0 0 .75rem; }
    .notice { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#166534;margin-bottom:1rem; }
    .warn { background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.7rem 1rem;
            font-size:.88rem;color:#92400e;margin-bottom:1rem;line-height:1.7; }
    .muted { font-size:.85rem;color:var(--gray-600);line-height:1.7; }
    .yearbar { display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem; }
    .yearbar select { border:1px solid var(--gray-300);border-radius:7px;padding:.35rem .6rem;
                      font-size:.88rem;font-family:inherit; }
    .btn-act { background:var(--primary);border:1px solid var(--primary);color:#fff;font-weight:700;
               border-radius:7px;padding:.5rem 1rem;font-size:.88rem;cursor:pointer;font-family:inherit; }
    .btn-plain { background:none;border:1px solid var(--gray-200);border-radius:7px;padding:.45rem .9rem;
                 font-size:.85rem;color:var(--gray-600);cursor:pointer;font-family:inherit; }
    ul.items { margin:.4rem 0 0;padding-left:1.1rem;font-size:.86rem;color:var(--gray-700);line-height:1.8; }
    ul.items a { color:var(--primary);font-weight:600;text-decoration:none; }
    .tag { display:inline-block;padding:.12rem .5rem;border-radius:100px;font-size:.72rem;font-weight:700; }
    .tag.closed { background:#f1f5f9;color:#475569; }
    .tag.open { background:#f0fdf4;color:#166534; }
  </style>
</head>
<body>
<div class="wrap">

  <a class="back-link" href="lp-dashboard.php">&#x2190; Expenses &amp; Grants</a>
  <h1>Year End</h1>
  <p class="muted" style="margin:-.6rem 0 1rem;">
    Everything in a closed year stays readable and exportable from the
    <a href="lp-archive.php?year=<?= (int)$closing ?>" style="color:var(--primary);font-weight:600;">archive</a>,
    which can also hand you the
    <a href="lp-archive-bundle.php?year=<?= (int)$closing ?>" style="color:var(--primary);font-weight:600;">whole
    year as one file</a> &mdash; records and receipt images &mdash; to keep off the web host.
  </p>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= $notice ?></div><?php endif; ?>
  <?php if ($error): ?>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.7rem 1rem;
              font-size:.88rem;color:#991b1b;margin-bottom:1rem;">&#x26A0; <?= $error ?></div>
  <?php endif; ?>

  <form method="GET" class="yearbar">
    <label for="y" class="muted"><strong>School year</strong></label>
    <select name="year" id="y" onchange="this.form.submit()">
      <?php foreach ($years as $y): ?>
      <option value="<?= (int)$y ?>" <?= $y === $closing ? 'selected' : '' ?>>
        <?= htmlspecialchars(lpYearLabel($y)) ?><?= $y === $current ? ' (current)' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button type="submit" class="btn-plain">Show</button></noscript>
    <span class="tag <?= $isClosed ? 'closed' : 'open' ?>"><?= $isClosed ? 'Closed' : 'Open' ?></span>
  </form>

  <div class="pcard">
    <h2>Still open in <?= htmlspecialchars(lpYearLabel($closing)) ?></h2>
    <?php if (!$openCount): ?>
      <p class="muted" style="margin:0;">Nothing outstanding. Every voucher and claim is paid or rejected,
         and no collaboration grant is awaiting a decision.</p>
    <?php else: ?>
      <div class="warn" style="margin:0 0 .6rem;">
        <?= (int)$openCount ?> item<?= $openCount === 1 ? '' : 's' ?> still need<?= $openCount === 1 ? 's' : '' ?>
        a decision. Closing the year does not block them &mdash; it is a marker, not a lock &mdash; but they
        are easier to settle now than to explain later.
      </div>
      <?php if ($outstanding['vouchers']): ?>
        <strong class="muted">President's vouchers</strong>
        <ul class="items">
          <?php foreach ($outstanding['vouchers'] as $v): ?>
          <li><a href="lp-voucher-view.php?id=<?= (int)$v['id'] ?>">
            <?= htmlspecialchars($v['voucher_number'] ?: ('#' . $v['id'])) ?></a>
            &mdash; <?= htmlspecialchars($v['name']) ?>
            (<?= htmlspecialchars(str_replace('_', ' ', $v['status'])) ?>)</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($outstanding['claims']): ?>
        <strong class="muted">Member claims</strong>
        <ul class="items">
          <?php foreach ($outstanding['claims'] as $b): ?>
          <li><a href="exp-claim-view.php?id=<?= (int)$b['id'] ?>">
            <?= htmlspecialchars($b['ref_code']) ?></a>
            &mdash; <?= htmlspecialchars($b['user_name']) ?>
            (<?= htmlspecialchars(str_replace('_', ' ', $b['status'])) ?>)</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($outstanding['collab']): ?>
        <p class="muted" style="margin:.5rem 0 0;">
          <a href="collab-grant-admin.php?year=<?= (int)$closing ?>" style="color:var(--primary);font-weight:600;">
            <?= (int)$outstanding['collab'] ?> collaboration grant application<?= $outstanding['collab'] === 1 ? '' : 's' ?>
            awaiting a decision</a>.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="pcard">
    <h2>Carry budgets into <?= htmlspecialchars(lpYearLabel($next)) ?></h2>
    <p class="muted" style="margin:0 0 .8rem;">
      Copies the names and amounts from <?= htmlspecialchars(lpYearLabel($closing)) ?>
      into <?= htmlspecialchars(lpYearLabel($next)) ?>. A name already there is updated to
      last year's amount; one that is missing is added; one you removed from the new
      year stays removed. Spending is never touched &mdash; each
      year has its own grant and budget-line rows, which is why past years keep reporting correctly.
    </p>
    <p class="muted" style="margin:0 0 .8rem;">
      <?= count($grants) ?> grant<?= count($grants) === 1 ? '' : 's' ?> and
      <?= count($lines) ?> budget line<?= count($lines) === 1 ? '' : 's' ?> in
      <?= htmlspecialchars(lpYearLabel($closing)) ?>;
      <?= count($nextGrants) ?> and <?= count($nextLines) ?> already in
      <?= htmlspecialchars(lpYearLabel($next)) ?>.
    </p>
    <form method="POST"
          onsubmit="return confirm('Copy <?= count($grants) + count($lines) ?> budget figures into <?= htmlspecialchars(addslashes(lpYearLabel($next))) ?>? Amounts already set for that year will be overwritten with last year's.');">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="carry_forward">
      <input type="hidden" name="year" value="<?= (int)$closing ?>">
      <button class="btn-act">Carry forward to <?= htmlspecialchars(lpYearLabel($next)) ?></button>
    </form>
  </div>

  <div class="pcard">
    <h2><?= $isClosed ? 'Reopen' : 'Close' ?> <?= htmlspecialchars(lpYearLabel($closing)) ?></h2>
    <p class="muted" style="margin:0 0 .8rem;">
      Closing records who closed the year and when. It hides nothing and deletes nothing:
      the year stays readable from the dashboard's year picker, and can be reopened.
    </p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="<?= $isClosed ? 'reopen_year' : 'close_year' ?>">
      <input type="hidden" name="year" value="<?= (int)$closing ?>">
      <button class="<?= $isClosed ? 'btn-plain' : 'btn-act' ?>">
        <?= $isClosed ? 'Reopen this year' : 'Mark this year closed' ?></button>
    </form>
  </div>

</div>
</body>
</html>
