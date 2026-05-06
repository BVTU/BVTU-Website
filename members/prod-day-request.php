<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();

$errors = [];
$saved  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dates  = array_filter(array_map('trim', explode(',', $_POST['request_dates'] ?? '')));
    $numDay = (float)($_POST['num_days'] ?? 0);
    $activity = trim($_POST['activity_description'] ?? '');
    $school   = trim($_POST['school'] ?? '');
    $toc      = isset($_POST['toc_needed']) ? 1 : 0;

    if (!$dates)     $errors['dates']    = 'Please select at least one date.';
    if ($numDay <= 0) $errors['num_days'] = 'Please enter the number of days.';
    if (!$activity)  $errors['activity'] = 'Please describe the activity.';
    if (!$school)    $errors['school']   = 'Please enter your school.';

    if (!$errors) {
        getDB()->prepare("INSERT INTO prod_day_requests
            (user_email, user_name, school, request_dates, num_days, activity_description, toc_needed)
            VALUES (?,?,?,?,?,?,?)")
           ->execute([
               $member['email'], $member['name'],
               $school,
               json_encode(array_values($dates)),
               $numDay, $activity, $toc,
           ]);
        $saved = true;
    }
}

// Past requests for this user
$stmt = getDB()->prepare("SELECT * FROM prod_day_requests WHERE user_email=? ORDER BY created_at DESC");
$stmt->execute([$member['email']]);
$requests = $stmt->fetchAll();

$statusLabel = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'flagged' => 'Flagged'];
$statusColor = ['pending' => '#d97706', 'approved' => '#166534', 'rejected' => '#991b1b', 'flagged' => '#7c3aed'];
$statusBg    = ['pending' => '#fffbeb', 'approved' => '#f0fdf4', 'rejected' => '#fef2f2', 'flagged' => '#f5f3ff'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Request Release Days — Pro-D Portal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 820px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.75rem; margin-bottom: 2rem; }
    .field { margin-bottom: 1.1rem; }
    .field label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    .field input, .field select, .field textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .8rem; font-size: .92rem; font-family: inherit; box-sizing: border-box; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field input.err, .field textarea.err { border-color: #dc2626; }
    .field-err { font-size: .78rem; color: #dc2626; margin-top: .28rem; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .28rem; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 540px) { .field-row { grid-template-columns: 1fr; } }

    /* Date picker chips */
    .date-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .5rem; min-height: 32px; }
    .date-chip { background: var(--accent); border: 1px solid #b8ddc5; border-radius: 100px; padding: .2rem .65rem; font-size: .8rem; font-weight: 600; color: var(--primary); display: flex; align-items: center; gap: .35rem; }
    .date-chip button { background: none; border: none; cursor: pointer; color: var(--primary); line-height: 1; padding: 0; font-size: .85rem; }

    .toc-row { display: flex; align-items: center; gap: .65rem; padding: .85rem 1rem; background: #f8f9fa; border-radius: 8px; margin-bottom: 1.1rem; }
    .toc-row input[type=checkbox] { width: 18px; height: 18px; accent-color: var(--primary); flex-shrink: 0; }
    .toc-row label { font-size: .88rem; color: var(--gray-700); font-weight: 500; cursor: pointer; }

    /* Success banner */
    .success-banner { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; font-size: .9rem; color: #166534; font-weight: 500; }

    /* History */
    .history-list { display: flex; flex-direction: column; gap: .85rem; }
    .req-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.1rem 1.25rem; }
    .req-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: .5rem; }
    .req-days { font-size: 1.2rem; font-weight: 800; color: var(--primary); white-space: nowrap; }
    .status-badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .2rem .6rem; border-radius: 100px; }
    .req-dates { font-size: .82rem; color: var(--gray-600); margin-bottom: .3rem; }
    .req-note { font-size: .82rem; color: var(--gray-600); background: #f8f9fa; border-radius: 6px; padding: .55rem .75rem; margin-top: .5rem; border-left: 3px solid var(--gray-300); }
    .section-title { font-size: .9rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .06em; margin: 0 0 .85rem; }
    .empty-state { text-align: center; padding: 2rem; color: var(--gray-400); background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>Request Release Day(s)</h1>
    <a class="back-link" href="prod-dashboard.php">← Pro-D Portal</a>
  </div>

  <?php if ($saved): ?>
  <div class="success-banner">✓ Your day request has been submitted and is pending approval.</div>
  <?php endif; ?>

  <!-- Request form -->
  <div class="form-card">
    <form method="POST" id="dayForm">

      <div class="field">
        <label>Dates Requested</label>
        <div class="date-chips" id="chips"></div>
        <input type="date" id="datePicker" min="<?= date('Y-m-d') ?>" style="max-width:220px;">
        <input type="hidden" name="request_dates" id="hiddenDates" value="">
        <div class="field-hint" style="margin-top:.4rem;">Select one or more dates and they'll appear as chips above.</div>
        <?php if (isset($errors['dates'])): ?><div class="field-err"><?= $errors['dates'] ?></div><?php endif; ?>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="num_days">Number of Days</label>
          <input type="number" name="num_days" id="num_days" min="0.5" step="0.5" placeholder="e.g. 1.0"
            value="<?= htmlspecialchars($_POST['num_days'] ?? '') ?>"
            class="<?= isset($errors['num_days']) ? 'err' : '' ?>">
          <div class="field-hint">Use 0.5 for a half day</div>
          <?php if (isset($errors['num_days'])): ?><div class="field-err"><?= $errors['num_days'] ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="school">Your School</label>
          <input type="text" name="school" id="school" placeholder="e.g. Smithers Secondary"
            value="<?= htmlspecialchars($_POST['school'] ?? '') ?>"
            class="<?= isset($errors['school']) ? 'err' : '' ?>">
          <?php if (isset($errors['school'])): ?><div class="field-err"><?= $errors['school'] ?></div><?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label for="activity_description">Activity / Purpose</label>
        <textarea name="activity_description" id="activity_description" rows="3"
          placeholder="e.g. 'Attending the BCTF Reading Instruction workshop in Prince George'"
          class="<?= isset($errors['activity']) ? 'err' : '' ?>"><?= htmlspecialchars($_POST['activity_description'] ?? '') ?></textarea>
        <?php if (isset($errors['activity'])): ?><div class="field-err"><?= $errors['activity'] ?></div><?php endif; ?>
      </div>

      <div class="toc-row">
        <input type="checkbox" name="toc_needed" id="toc_needed" value="1"
          <?= !empty($_POST['toc_needed']) ? 'checked' : '' ?>>
        <label for="toc_needed">A TOC (teacher-on-call) will be needed to cover my class</label>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:.75rem;font-size:.95rem;">
        Submit Day Request
      </button>
    </form>
  </div>

  <!-- History -->
  <p class="section-title">My Day Request History</p>

  <?php if (!$requests): ?>
  <div class="empty-state">No requests submitted yet.</div>
  <?php else: ?>
  <div class="history-list">
    <?php foreach ($requests as $r):
      $dates = json_decode($r['request_dates'], true) ?: [];
      $st  = $r['status'];
      $col = $statusColor[$st] ?? '#555';
      $bg  = $statusBg[$st]    ?? '#f8f9fa';
    ?>
    <div class="req-card">
      <div class="req-top">
        <div>
          <div style="font-weight:700;color:var(--gray-800);margin-bottom:.15rem;"><?= htmlspecialchars($r['school'] ?? '—') ?></div>
          <div class="req-dates">
            <?= implode(' · ', array_map(fn($d) => date('M j, Y', strtotime($d)), $dates)) ?>
          </div>
          <div style="font-size:.8rem;color:var(--gray-400);">
            Submitted <?= date('M j', strtotime($r['created_at'])) ?>
            <?= $r['toc_needed'] ? ' · <strong>TOC needed</strong>' : '' ?>
          </div>
        </div>
        <div style="text-align:right;">
          <div class="req-days"><?= number_format($r['num_days'], 1) ?> day<?= $r['num_days'] != 1 ? 's' : '' ?></div>
          <span class="status-badge" style="background:<?= $bg ?>;color:<?= $col ?>;margin-top:.3rem;display:inline-block;">
            <?= $statusLabel[$st] ?? $st ?>
          </span>
        </div>
      </div>
      <div style="font-size:.83rem;color:var(--gray-600);"><?= htmlspecialchars($r['activity_description']) ?></div>
      <?php if ($r['reviewer_note']): ?>
      <div class="req-note"><strong>Reviewer note:</strong> <?= htmlspecialchars($r['reviewer_note']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
<script>
const selectedDates = new Set();

document.getElementById('datePicker').addEventListener('change', function() {
  const v = this.value;
  if (v && !selectedDates.has(v)) {
    selectedDates.add(v);
    renderChips();
  }
  this.value = '';
});

function renderChips() {
  const container = document.getElementById('chips');
  container.innerHTML = '';
  [...selectedDates].sort().forEach(d => {
    const chip = document.createElement('div');
    chip.className = 'date-chip';
    const label = new Date(d + 'T00:00:00').toLocaleDateString('en-CA', {month:'short', day:'numeric', year:'numeric'});
    chip.innerHTML = `${label} <button type="button" onclick="removeDate('${d}')">×</button>`;
    container.appendChild(chip);
  });
  document.getElementById('hiddenDates').value = [...selectedDates].sort().join(',');
}

function removeDate(d) {
  selectedDates.delete(d);
  renderChips();
}
</script>
</body>
</html>
