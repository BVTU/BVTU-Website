<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();
prodSeedTrialAllocation($member['email'], $member['name']);

$bal     = prodGetBalance($member['email']);
$schools = prodGetSchools();
$errors  = [];
$saved   = false;

$catLabels = [
    'conference' => 'Conference / Workshop',
    'course'     => 'Course / Training',
    'materials'  => 'Materials / Resources',
    'travel'     => 'Travel',
    'other'      => 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dates    = array_filter(array_map('trim', explode(',', $_POST['request_dates'] ?? '')));
    $numDays  = (float)($_POST['num_days']   ?? 0);
    $schoolId = (int)($_POST['school_id']    ?? 0);
    $activity = trim($_POST['activity']      ?? '');
    $category = trim($_POST['category']      ?? '');
    $amount   = trim($_POST['tentative_amount'] ?? '');
    $toc      = isset($_POST['toc_needed']) ? 1 : 0;

    $schoolName = '';
    foreach ($schools as $s) {
        if ((int)$s['id'] === $schoolId) { $schoolName = $s['name']; break; }
    }

    if (!$dates)                              $errors['dates']    = 'Please add at least one date.';
    if ($numDays <= 0)                        $errors['num_days'] = 'Please enter the number of days.';
    if (!$schoolId)                           $errors['school']   = 'Please select your school.';
    if (!$activity)                           $errors['activity'] = 'Please describe the activity.';
    if (!in_array($category, PROD_CATEGORIES)) $errors['category'] = 'Please choose a category.';
    if (!is_numeric($amount) || (float)$amount < 0)
                                              $errors['amount']   = 'Please enter an estimated amount (enter 0 if no funds needed).';
    elseif ((float)$amount > $bal['balance'])
                                              $errors['amount']   = 'Estimated amount exceeds your available balance ($' . number_format($bal['balance'], 2) . ').';

    if (!$errors) {
        getDB()->prepare("INSERT INTO prod_requests
            (user_email, user_name, school, school_id, request_dates, num_days, toc_needed,
             activity_description, category, tentative_amount)
            VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([
               $member['email'], $member['name'],
               $schoolName, $schoolId,
               json_encode(array_values($dates)),
               $numDays, $toc,
               $activity, $category,
               round((float)$amount, 2),
           ]);
        $saved = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>New Pro-D Request — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .balance-chip { background: var(--accent); border: 1px solid #b8ddc5; border-radius: 100px; padding: .35rem .9rem; font-size: .83rem; font-weight: 700; color: var(--primary); display: inline-flex; align-items: center; gap: .4rem; margin-bottom: 1.5rem; }

    /* Flow steps */
    .flow-steps { display: flex; gap: 0; margin-bottom: 2rem; }
    .flow-step { flex: 1; padding: .6rem .5rem; text-align: center; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .flow-step.active { background: var(--primary); color: #fff; border-radius: 8px 0 0 8px; }
    .flow-step.upcoming { background: var(--gray-100); color: var(--gray-400); border-radius: 0 8px 8px 0; }
    .flow-arrow { display: flex; align-items: center; color: var(--gray-300); font-size: 1rem; }

    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.75rem; }
    .section-label { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--primary); margin: 1.25rem 0 .75rem; padding-bottom: .4rem; border-bottom: 2px solid var(--accent); }
    .section-label:first-child { margin-top: 0; }

    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    .field input, .field select, .field textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .8rem; font-size: .92rem; font-family: inherit; box-sizing: border-box; transition: border-color .15s; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field input.err, .field select.err { border-color: #dc2626; }
    .field-err { font-size: .78rem; color: #dc2626; margin-top: .28rem; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .28rem; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 540px) { .field-row { grid-template-columns: 1fr; } }

    .date-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .5rem; min-height: 32px; }
    .date-chip { background: var(--accent); border: 1px solid #b8ddc5; border-radius: 100px; padding: .2rem .65rem; font-size: .8rem; font-weight: 600; color: var(--primary); display: flex; align-items: center; gap: .35rem; }
    .date-chip button { background: none; border: none; cursor: pointer; color: var(--primary); font-size: .85rem; padding: 0; line-height: 1; }

    .toc-row { display: flex; align-items: center; gap: .65rem; padding: .8rem 1rem; background: #f8f9fa; border-radius: 8px; margin-bottom: 1rem; }
    .toc-row input[type=checkbox] { width: 18px; height: 18px; accent-color: var(--primary); flex-shrink: 0; }
    .toc-row label { font-size: .88rem; color: var(--gray-700); font-weight: 500; cursor: pointer; }

    .amount-note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: .75rem 1rem; font-size: .82rem; color: #92400e; margin-top: .5rem; }

    .success-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 2rem; text-align: center; }
    .success-card h2 { color: var(--primary); font-size: 1.2rem; margin: 0 0 .5rem; }
    .success-card p { color: var(--gray-600); font-size: .9rem; margin: .4rem 0; line-height: 1.6; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>New Pro-D Request</h1>
    <a class="back-link" href="prod-dashboard.php">← Pro-D Portal</a>
  </div>

  <!-- Flow indicator -->
  <div class="flow-steps">
    <div class="flow-step active">① Submit Request</div>
    <div class="flow-arrow">›</div>
    <div class="flow-step upcoming">② Approval</div>
    <div class="flow-arrow">›</div>
    <div class="flow-step upcoming">③ Attend Event</div>
    <div class="flow-arrow">›</div>
    <div class="flow-step upcoming">④ Submit Final Claim</div>
  </div>

  <?php if ($saved): ?>
  <div class="success-card">
    <h2>✓ Request submitted</h2>
    <p>Your request has been sent for approval. Once approved, attend your event and then return here to submit your final claim with receipt.</p>
    <p style="font-size:.8rem;color:var(--gray-400);">The estimated amount is reserved against your balance while the request is open.</p>
    <div style="display:flex;gap:.75rem;justify-content:center;margin-top:1.25rem;flex-wrap:wrap;">
      <a href="prod-request-new.php" class="btn btn-primary"  style="padding:.55rem 1.1rem;font-size:.9rem;">New Request</a>
      <a href="prod-requests.php"    class="btn btn-outline"  style="padding:.55rem 1.1rem;font-size:.9rem;">View My Requests</a>
    </div>
  </div>

  <?php else: ?>

  <div class="balance-chip">
    Available balance: <strong>$<?= number_format($bal['balance'], 2) ?></strong>
    <?php if ($bal['reserved'] > 0): ?>
      &nbsp;·&nbsp; <span style="color:#d97706;">$<?= number_format($bal['reserved'], 2) ?> reserved</span>
    <?php endif; ?>
  </div>

  <div class="form-card">
    <form method="POST" id="reqForm">

      <p class="section-label">Activity Details</p>

      <div class="field">
        <label for="activity">Activity / Event Name *</label>
        <textarea name="activity" id="activity" rows="2" required
          placeholder="e.g. BCTF Reading Instruction Conference — Prince George, Feb 2026"
          class="<?= isset($errors['activity']) ? 'err' : '' ?>"><?= htmlspecialchars($_POST['activity'] ?? '') ?></textarea>
        <?php if (isset($errors['activity'])): ?><div class="field-err"><?= $errors['activity'] ?></div><?php endif; ?>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="category">Category *</label>
          <select name="category" id="category" required class="<?= isset($errors['category']) ? 'err' : '' ?>">
            <option value="">Choose…</option>
            <?php foreach ($catLabels as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($_POST['category'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category'])): ?><div class="field-err"><?= $errors['category'] ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label for="school_id">Your School *</label>
          <select name="school_id" id="school_id" required class="<?= isset($errors['school']) ? 'err' : '' ?>">
            <option value="">Select school…</option>
            <?php foreach ($schools as $s): ?>
            <option value="<?= $s['id'] ?>" <?= (int)($_POST['school_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['school'])): ?><div class="field-err"><?= $errors['school'] ?></div><?php endif; ?>
        </div>
      </div>

      <p class="section-label">Release Days</p>

      <div class="field">
        <label>Date(s) of Absence *</label>
        <div class="date-chips" id="chips"></div>
        <input type="date" id="datePicker" min="<?= date('Y-m-d') ?>">
        <input type="hidden" name="request_dates" id="hiddenDates" value="">
        <div class="field-hint" style="margin-top:.4rem;">Click dates to add them — you can select multiple.</div>
        <?php if (isset($errors['dates'])): ?><div class="field-err"><?= $errors['dates'] ?></div><?php endif; ?>
      </div>

      <div class="field" style="max-width:180px;">
        <label for="num_days">Number of Days *</label>
        <input type="number" name="num_days" id="num_days" min="0.5" step="0.5" placeholder="1.0"
          value="<?= htmlspecialchars($_POST['num_days'] ?? '') ?>"
          class="<?= isset($errors['num_days']) ? 'err' : '' ?>">
        <div class="field-hint">Use 0.5 for a half day</div>
        <?php if (isset($errors['num_days'])): ?><div class="field-err"><?= $errors['num_days'] ?></div><?php endif; ?>
      </div>

      <div class="toc-row">
        <input type="checkbox" name="toc_needed" id="toc_needed" value="1" <?= !empty($_POST['toc_needed']) ? 'checked' : '' ?>>
        <label for="toc_needed">A TOC (teacher-on-call) will be needed to cover my class</label>
      </div>

      <p class="section-label">Estimated Funding</p>

      <div class="field">
        <label for="tentative_amount">Estimated Amount ($) *</label>
        <input type="number" name="tentative_amount" id="tentative_amount" min="0" step="0.01" placeholder="0.00"
          value="<?= htmlspecialchars($_POST['tentative_amount'] ?? '') ?>"
          class="<?= isset($errors['amount']) ? 'err' : '' ?>">
        <?php if (isset($errors['amount'])): ?><div class="field-err"><?= $errors['amount'] ?></div><?php endif; ?>
        <div class="amount-note" style="margin-top:.6rem;">
          This is your best estimate — you'll submit your actual receipt and final amount after the event.
          Enter <strong>0</strong> if you need days only with no funding.
        </div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:.75rem;font-size:.95rem;margin-top:.5rem;">
        Submit Request for Approval
      </button>
    </form>
  </div>

  <?php endif; ?>
</div>

<script>
const selectedDates = new Set();
document.getElementById('datePicker').addEventListener('change', function() {
  if (this.value && !selectedDates.has(this.value)) {
    selectedDates.add(this.value);
    renderChips();
  }
  this.value = '';
});
function renderChips() {
  const c = document.getElementById('chips');
  c.innerHTML = '';
  [...selectedDates].sort().forEach(d => {
    const chip = document.createElement('div');
    chip.className = 'date-chip';
    const label = new Date(d + 'T00:00:00').toLocaleDateString('en-CA', {month:'short', day:'numeric', year:'numeric'});
    chip.innerHTML = `${label} <button type="button" onclick="removeDate('${d}')">×</button>`;
    c.appendChild(chip);
  });
  document.getElementById('hiddenDates').value = [...selectedDates].sort().join(',');
}
function removeDate(d) { selectedDates.delete(d); renderChips(); }
</script>
</body>
</html>
