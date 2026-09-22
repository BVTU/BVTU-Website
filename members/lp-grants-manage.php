<?php
/**
 * lp-grants-manage.php — Budgets for the current year: BCTF grants and the
 * local budget lines.
 *
 * Budget lines were seeded once by lpEnsureTables() and had no editor at all,
 * so a line could never be added or its amount corrected without going into the
 * database. They live here rather than on a page of their own because they are
 * the same job as grants — what money exists this year, and how much of it.
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
$db  = getDB();
$yr  = lpCurrentYear();

$notice = '';
$error  = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_grants') {
        $ids     = $_POST['grant_id']     ?? [];
        $names   = $_POST['grant_name']   ?? [];
        $budgets = $_POST['grant_budget'] ?? [];
        $upd = $db->prepare("UPDATE lp_grants SET name=?, budget=? WHERE id=? AND year=?");
        foreach ($ids as $i => $id) {
            $name   = trim($names[$i]   ?? '');
            $budget = (float)($budgets[$i] ?? 0);
            if ($name && $id) $upd->execute([$name, $budget, (int)$id, $yr]);
        }
        $notice = 'Grant budgets saved.';
    }

    if ($action === 'add_grant') {
        $name   = trim($_POST['new_name']   ?? '');
        $budget = (float)($_POST['new_budget'] ?? 0);
        if ($name) {
            $db->prepare("INSERT INTO lp_grants (name, budget, year) VALUES (?,?,?)")
               ->execute([$name, $budget, $yr]);
            $notice = 'Grant "' . htmlspecialchars($name) . '" added.';
        } else {
            $error = 'Grant name is required.';
        }
    }

    if ($action === 'deactivate_grant') {
        $id = (int)($_POST['grant_id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE lp_grants SET active=0 WHERE id=? AND year=?")
               ->execute([$id, $yr]);
            $notice = 'Grant removed from this year.';
        }
    }

    if ($action === 'copy_grants') {
        // Copy grants from prior year with their budgets to current year (if current year is empty)
        $priorYr = $yr - 1;
        $check = $db->prepare("SELECT COUNT(*) FROM lp_grants WHERE year=?");
        $check->execute([$yr]);
        if ((int)$check->fetchColumn() === 0) {
            $rows = $db->prepare("SELECT name, budget FROM lp_grants WHERE year=? AND active=1 ORDER BY name");
            $rows->execute([$priorYr]);
            $ins = $db->prepare("INSERT INTO lp_grants (name, budget, year) VALUES (?,?,?)");
            foreach ($rows->fetchAll() as $r) $ins->execute([$r['name'], $r['budget'], $yr]);
            $notice = 'Grants copied from ' . $priorYr . '–' . ($priorYr + 1) . '.';
        } else {
            $error = 'Grants for this year already exist.';
        }
    }

    // ── Budget lines ─────────────────────────────────────────────────────────
    // Same four actions as grants. Deactivating hides a line from new vouchers
    // without touching expenses already coded to it — lp_expenses keeps the id,
    // so past vouchers still report correctly.
    if ($action === 'save_lines') {
        $ids     = $_POST['line_id']     ?? [];
        $names   = $_POST['line_name']   ?? [];
        $budgets = $_POST['line_budget'] ?? [];
        $upd = $db->prepare("UPDATE lp_budget_lines SET name=?, budget=? WHERE id=? AND year=?");
        $n = 0;
        foreach ($ids as $i => $id) {
            $name   = trim($names[$i] ?? '');
            $budget = (float)($budgets[$i] ?? 0);
            if ($name && $id) { $upd->execute([$name, $budget, (int)$id, $yr]); $n++; }
        }
        $notice = $n . ' budget line' . ($n === 1 ? '' : 's') . ' saved.';
    }

    if ($action === 'add_line') {
        $name   = trim($_POST['new_line_name'] ?? '');
        $budget = (float)($_POST['new_line_budget'] ?? 0);
        if ($name) {
            $db->prepare("INSERT INTO lp_budget_lines (name, budget, year) VALUES (?,?,?)")
               ->execute([$name, $budget, $yr]);
            $notice = 'Budget line "' . htmlspecialchars($name) . '" added.';
        } else {
            $error = 'Budget line name is required.';
        }
    }

    if ($action === 'deactivate_line') {
        $id = (int)($_POST['line_id'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE lp_budget_lines SET active=0 WHERE id=? AND year=?")
               ->execute([$id, $yr]);
            $notice = 'Budget line removed from this year.';
        }
    }

    if ($action === 'copy_lines') {
        $priorYr = $yr - 1;
        // active=1 only: the button that offers this appears when the year has
        // no active lines, so counting deactivated ones would block it forever.
        $check = $db->prepare("SELECT COUNT(*) FROM lp_budget_lines WHERE year=? AND active=1");
        $check->execute([$yr]);
        if ((int)$check->fetchColumn() === 0) {
            $rows = $db->prepare("SELECT name, budget FROM lp_budget_lines WHERE year=? AND active=1 ORDER BY name");
            $rows->execute([$priorYr]);
            $ins = $db->prepare("INSERT INTO lp_budget_lines (name, budget, year) VALUES (?,?,?)");
            $n = 0;
            foreach ($rows->fetchAll() as $r) { $ins->execute([$r['name'], $r['budget'], $yr]); $n++; }
            $notice = $n ? "Copied {$n} budget lines from " . $priorYr . '–' . ($priorYr + 1) . '.'
                         : 'There were no budget lines to copy.';
        } else {
            $error = 'Budget lines for this year already exist.';
        }
    }

    if (!headers_sent()) {
        $q = $notice ? '?notice=' . urlencode($notice) : ($error ? '?error=' . urlencode($error) : '');
        header('Location: lp-grants-manage.php' . $q);
        exit;
    }
}

$notice = $notice ?: htmlspecialchars($_GET['notice'] ?? '');
$error  = $error  ?: htmlspecialchars($_GET['error']  ?? '');

// ── Load grants ───────────────────────────────────────────────────────────────
$grants = $db->prepare("SELECT * FROM lp_grants WHERE year=? AND active=1 ORDER BY name");
$grants->execute([$yr]);
$grants = $grants->fetchAll();

$lines = $db->prepare("SELECT * FROM lp_budget_lines WHERE year=? AND active=1 ORDER BY name");
$lines->execute([$yr]);
$lines = $lines->fetchAll();

// What each line has actually been spent against, so the amounts are edited
// with the year's real position in view rather than from memory.
$spentByLine = [];
try {
    $sp = $db->prepare(
        "SELECT e.budget_line_id AS bl,
                SUM(COALESCE(e.travel_amt,0) + COALESCE(e.meals,0) + COALESCE(e.gifts,0)
                  + COALESCE(e.misc,0) + COALESCE(e.office,0) + COALESCE(e.phone,0)) AS total
         FROM lp_expenses e
         JOIN lp_vouchers v ON v.id = e.voucher_id
         WHERE v.year = ? AND e.budget_line_id IS NOT NULL
         GROUP BY e.budget_line_id"
    );
    $sp->execute([$yr]);
    foreach ($sp->fetchAll() as $r) $spentByLine[(int)$r['bl']] = (float)$r['total'];
} catch (Exception $e) {
    // A reporting extra, not the point of the page: if the expense tables are
    // not there yet, edit the budgets without the spent column.
}

$yearLabel = $yr . '–' . ($yr + 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Budgets — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 680px; margin: 0 auto; padding: 2rem 1.5rem 5rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.25rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .year-badge { font-size: .75rem; font-weight: 700; background: var(--primary); color: #fff; border-radius: 100px; padding: .2rem .7rem; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .notice { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #991b1b; margin-bottom: 1.25rem; }

    .pcard { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .pcard h2 { font-size: .75rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); margin: 0 0 1.1rem; }

    .grant-row { display: flex; gap: .65rem; align-items: center; margin-bottom: .65rem; }
    .grant-row input[type="text"]   { flex: 1; border: 1px solid var(--gray-300); border-radius: 7px; padding: .5rem .7rem; font-size: .9rem; font-family: inherit; }
    .grant-row input[type="number"] { width: 110px; border: 1px solid var(--gray-300); border-radius: 7px; padding: .5rem .7rem; font-size: .9rem; font-family: inherit; text-align: right; }
    .grant-row input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .dollar-wrap { position: relative; }
    .dollar-wrap::before { content: '$'; position: absolute; left: .65rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: .88rem; pointer-events: none; }
    .dollar-wrap input { padding-left: 1.3rem !important; }
    .remove-btn { background: none; border: none; cursor: pointer; color: var(--gray-300); font-size: 1.1rem; padding: .3rem; border-radius: 5px; line-height: 1; flex-shrink: 0; }
    .remove-btn:hover { color: #dc2626; background: #fef2f2; }
    .col-labels { display: flex; gap: .65rem; margin-bottom: .35rem; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-400); }
    .col-labels .lbl-name   { flex: 1; }
    .col-labels .lbl-budget { width: 110px; text-align: right; }
    .col-labels .lbl-del    { width: 28px; }

    .save-btn { display: inline-flex; align-items: center; gap: .4rem; background: var(--primary); color: #fff; border: none; border-radius: 8px; padding: .6rem 1.2rem; font-size: .9rem; font-weight: 700; cursor: pointer; margin-top: .5rem; }
    .save-btn:hover { background: var(--primary-dk); }

    .divider { border: none; border-top: 1px solid var(--gray-100); margin: 1rem 0; }
    .add-row  { display: flex; gap: .65rem; align-items: flex-end; flex-wrap: wrap; }
    .add-row .field { display: flex; flex-direction: column; gap: .3rem; }
    .add-row label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); }
    .add-row input[type="text"]   { flex: 1; min-width: 220px; border: 1px solid var(--gray-300); border-radius: 7px; padding: .5rem .7rem; font-size: .9rem; font-family: inherit; }
    .add-row input[type="number"] { width: 110px; border: 1px solid var(--gray-300); border-radius: 7px; padding: .5rem .7rem; font-size: .9rem; font-family: inherit; text-align: right; }
    .add-row input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .add-btn { background: #fff; color: var(--primary); border: 1.5px solid var(--primary); border-radius: 8px; padding: .55rem 1rem; font-size: .88rem; font-weight: 700; cursor: pointer; }
    .add-btn:hover { background: var(--accent); }
    .total-row { display: flex; justify-content: flex-end; align-items: center; gap: .5rem; margin-top: .75rem; font-size: .85rem; }
    .total-row strong { font-size: .95rem; color: var(--primary); }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
      <h1>Budgets</h1>
      <span class="year-badge"><?= $yearLabel ?></span>
    </div>
    <a class="back-link" href="lp-dashboard.php">← LP Dashboard</a>
  </div>

  <?php if ($notice): ?><div class="notice">✓ <?= $notice ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">⚠ <?= $error ?></div><?php endif; ?>

  <!-- Edit existing grants -->
  <div class="pcard">
    <h2>Grant Amounts — <?= $yearLabel ?></h2>
    <?php if ($grants): ?>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_grants">
      <div class="col-labels">
        <span class="lbl-name">Grant name</span>
        <span class="lbl-budget">Budget</span>
        <span class="lbl-del"></span>
      </div>
      <?php foreach ($grants as $g): ?>
      <div class="grant-row">
        <input type="hidden" name="grant_id[]" value="<?= (int)$g['id'] ?>">
        <input type="text" name="grant_name[]" value="<?= htmlspecialchars($g['name']) ?>" required>
        <div class="dollar-wrap">
          <input type="number" name="grant_budget[]" value="<?= number_format((float)$g['budget'], 2, '.', '') ?>"
                 step="0.01" min="0" required>
        </div>
        <button type="button" class="remove-btn" title="Remove this grant"
          onclick="confirmRemove(<?= (int)$g['id'] ?>, <?= htmlspecialchars(json_encode($g['name']), ENT_QUOTES) ?>)">×</button>
      </div>
      <?php endforeach; ?>
      <div class="total-row">
        Total budget: <strong id="totalDisplay">$<?= number_format(array_sum(array_column($grants, 'budget')), 2) ?></strong>
      </div>
      <button type="submit" class="save-btn">💾 Save Changes</button>
    </form>
    <?php else: ?>
    <p style="color:var(--gray-400);font-size:.88rem;">No grants configured for <?= $yearLabel ?>.</p>
    <?php endif; ?>
  </div>

  <!-- Budget lines -->
  <div class="pcard">
    <h2>Local Budget Lines — <?= $yearLabel ?></h2>
    <p style="font-size:.85rem;color:var(--gray-600);line-height:1.7;margin:0 0 .8rem;">
      The lines an expense can be coded to. Removing one hides it from new vouchers;
      expenses already coded to it keep their line and still report correctly.
    </p>
    <?php if ($lines): ?>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_lines">
      <div class="col-labels">
        <span class="lbl-name">Budget line</span>
        <span class="lbl-budget">Budget</span>
        <span class="lbl-del"></span>
      </div>
      <?php foreach ($lines as $l): ?>
      <?php $spent = $spentByLine[(int)$l['id']] ?? 0; ?>
      <div class="grant-row">
        <input type="hidden" name="line_id[]" value="<?= (int)$l['id'] ?>">
        <input type="text" name="line_name[]" value="<?= htmlspecialchars($l['name']) ?>" required>
        <div class="dollar-wrap">
          <input type="number" name="line_budget[]"
                 value="<?= number_format((float)$l['budget'], 2, '.', '') ?>"
                 step="0.01" min="0" required>
        </div>
        <button type="button" class="remove-btn" title="Remove this budget line"
          onclick="confirmRemoveLine(<?= (int)$l['id'] ?>, <?= htmlspecialchars(json_encode($l['name']), ENT_QUOTES) ?>)">&times;</button>
      </div>
      <?php if ($spent > 0): ?>
        <div style="font-size:.78rem;color:<?= $spent > (float)$l['budget'] ? '#991b1b' : 'var(--gray-500)' ?>;
                    margin:-.4rem 0 .6rem .2rem;">
          $<?= number_format($spent, 2) ?> spent<?php
            if ((float)$l['budget'] > 0): ?> of $<?= number_format((float)$l['budget'], 2) ?><?php
            endif; if ($spent > (float)$l['budget']): ?> &mdash; over budget<?php endif; ?>
        </div>
      <?php endif; ?>
      <?php endforeach; ?>
      <div class="total-row">
        Total budget: <strong>$<?= number_format(array_sum(array_column($lines, 'budget')), 2) ?></strong>
      </div>
      <button type="submit" class="save-btn">&#128190; Save Changes</button>
    </form>
    <?php else: ?>
      <p style="color:var(--gray-400);font-size:.88rem;">No budget lines for <?= $yearLabel ?>.</p>
      <form method="POST" style="margin-top:.6rem;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="copy_lines">
        <button type="submit" class="add-btn">Copy last year&rsquo;s budget lines</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Add a budget line -->
  <div class="pcard">
    <h2>Add a Budget Line</h2>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_line">
      <div class="add-row">
        <div class="field" style="flex:1;">
          <label>Budget line name</label>
          <input type="text" name="new_line_name" placeholder="e.g. Professional Development" required>
        </div>
        <div class="field">
          <label>Budget</label>
          <div class="dollar-wrap">
            <input type="number" name="new_line_budget" placeholder="0.00" step="0.01" min="0" value="">
          </div>
        </div>
        <button type="submit" class="add-btn" style="margin-bottom:0;align-self:flex-end;">+ Add</button>
      </div>
    </form>
  </div>

  <!-- Add new grant -->
  <div class="pcard">
    <h2>Add a Grant</h2>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_grant">
      <div class="add-row">
        <div class="field" style="flex:1;">
          <label>Grant name</label>
          <input type="text" name="new_name" placeholder="e.g. Equity & Inclusion Grant" required>
        </div>
        <div class="field">
          <label>Budget</label>
          <div class="dollar-wrap">
            <input type="number" name="new_budget" placeholder="0.00" step="0.01" min="0" value="">
          </div>
        </div>
        <button type="submit" class="add-btn" style="margin-bottom:0;align-self:flex-end;">+ Add</button>
      </div>
    </form>
  </div>

</div>

<!-- Hidden deactivate form -->
<form method="POST" id="removeForm" style="display:none;">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="deactivate_grant">
  <input type="hidden" name="grant_id" id="removeGrantId">
</form>

<form method="POST" id="removeLineForm" style="display:none;">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="deactivate_line">
  <input type="hidden" name="line_id" id="removeLineId">
</form>

<script>
function confirmRemoveLine(id, name) {
    if (confirm('Remove "' + name + '" from ' + <?= json_encode($yearLabel) ?> +
                '? Expenses already coded to it keep their line.')) {
        document.getElementById('removeLineId').value = id;
        document.getElementById('removeLineForm').submit();
    }
}

function confirmRemove(id, name) {
    if (confirm('Remove "' + name + '" from ' + <?= json_encode($yearLabel) ?> + '? This hides it but does not delete any expense data.')) {
        document.getElementById('removeGrantId').value = id;
        document.getElementById('removeForm').submit();
    }
}

// Live total
(function() {
    function recalc() {
        let total = 0;
        document.querySelectorAll('[name="grant_budget[]"]').forEach(el => {
            total += parseFloat(el.value) || 0;
        });
        const disp = document.getElementById('totalDisplay');
        if (disp) disp.textContent = '$' + total.toLocaleString('en-CA', {minimumFractionDigits:2, maximumFractionDigits:2});
    }
    document.querySelectorAll('[name="grant_budget[]"]').forEach(el => el.addEventListener('input', recalc));
})();
</script>
</body>
</html>
