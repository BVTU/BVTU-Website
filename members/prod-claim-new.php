<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();
prodSeedTrialAllocation($member['email'], $member['name']);

$bal    = prodGetBalance($member['email']);
$errors = [];
$saved  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date     = trim($_POST['expense_date']  ?? '');
    $category = trim($_POST['category']      ?? '');
    $amount   = trim($_POST['amount_claimed'] ?? '');
    $desc     = trim($_POST['description']   ?? '');
    $receiptPath = trim($_POST['receipt_path']     ?? '');
    $receiptOrig = trim($_POST['receipt_filename'] ?? '');

    // Extracted fields (from scan)
    $exVendor   = trim($_POST['extracted_vendor']   ?? '') ?: null;
    $exDate     = trim($_POST['extracted_date']     ?? '') ?: null;
    $exAmount   = trim($_POST['extracted_amount']   ?? '') ?: null;
    $exFlag     = trim($_POST['extraction_flag']    ?? '') ?: null;
    $exConcerns = trim($_POST['extraction_concerns'] ?? '') ?: null;

    if (!$date)                            $errors['date']     = 'Please enter the expense date.';
    elseif ($date > date('Y-m-d'))         $errors['date']     = 'Date cannot be in the future.';
    if (!in_array($category, PROD_CATEGORIES)) $errors['category'] = 'Please choose a category.';
    if (!is_numeric($amount) || (float)$amount <= 0) $errors['amount'] = 'Please enter a valid amount.';
    elseif ((float)$amount > $bal['balance'])
                                           $errors['amount']   = 'Amount exceeds your available balance ($' . number_format($bal['balance'], 2) . ').';
    if (!$desc)                            $errors['desc']     = 'Please describe the expense.';

    // Amount mismatch flag
    if (!$errors && $exAmount !== null && abs((float)$amount - (float)$exAmount) > 1.00) {
        $exFlag = 'amount_mismatch';
    }

    if (!$errors) {
        $db = getDB();
        $db->prepare("INSERT INTO prod_claims
            (user_email, user_name, expense_date, category, amount_claimed, description,
             receipt_path, receipt_filename,
             extracted_vendor, extracted_date, extracted_amount,
             extraction_flag, extraction_concerns)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([
               $member['email'], $member['name'],
               $date, $category, (float)$amount, $desc,
               $receiptPath ?: null, $receiptOrig ?: null,
               $exVendor, $exDate ?: null, $exAmount ? (float)$exAmount : null,
               $exFlag, $exConcerns,
           ]);
        $saved = true;
    }
}

$catLabels = ['conference' => 'Conference / Workshop', 'course' => 'Course / Training',
              'materials' => 'Materials / Resources', 'travel' => 'Travel', 'other' => 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Claim — Pro-D Portal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    /* Balance chip */
    .balance-chip { background: var(--accent); border: 1px solid #b8ddc5; border-radius: 100px; padding: .35rem .9rem; font-size: .83rem; font-weight: 700; color: var(--primary); display: inline-flex; align-items: center; gap: .4rem; margin-bottom: 1.5rem; }

    /* Upload area */
    .upload-area { border: 2px dashed var(--gray-300); border-radius: 12px; padding: 2.25rem 1.5rem; text-align: center; cursor: pointer; transition: border-color .2s, background .2s; background: #fff; margin-bottom: 1.5rem; position: relative; }
    .upload-area:hover, .upload-area.drag-over { border-color: var(--primary); background: var(--accent); }
    .upload-area.has-file { border-color: var(--primary); background: #f0f7f2; border-style: solid; }
    .upload-area input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
    .upload-icon { margin: 0 auto .75rem; width: 44px; height: 44px; background: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .upload-icon svg { width: 22px; height: 22px; stroke: var(--primary); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    .upload-title { font-weight: 700; font-size: .95rem; color: var(--gray-700); margin-bottom: .25rem; }
    .upload-sub { font-size: .8rem; color: var(--gray-400); }

    /* Scanning states */
    .scan-state { display: none; align-items: center; gap: .6rem; font-size: .85rem; margin-bottom: 1rem; padding: .7rem 1rem; border-radius: 8px; }
    .scan-state.scanning { display: flex; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .scan-state.success  { display: flex; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .scan-state.error    { display: flex; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .spin { animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Flag banner */
    .flag-banner { display: none; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: .75rem 1rem; font-size: .83rem; color: #92400e; margin-bottom: 1rem; }
    .flag-banner.visible { display: block; }

    /* Form */
    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.75rem; }
    .field { margin-bottom: 1.1rem; }
    .field label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    .field input, .field select, .field textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .8rem; font-size: .92rem; font-family: inherit; box-sizing: border-box; transition: border-color .15s; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field input.err, .field select.err, .field textarea.err { border-color: #dc2626; }
    .field-err { font-size: .78rem; color: #dc2626; margin-top: .28rem; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .28rem; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 540px) { .field-row { grid-template-columns: 1fr; } }

    /* Pre-filled highlight */
    .prefilled { border-color: var(--primary) !important; background: #f0f7f2 !important; }
    .prefill-label { font-size: .72rem; color: var(--primary); font-weight: 600; margin-top: .25rem; display: none; }
    .prefill-label.visible { display: block; }

    .success-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 1.75rem; text-align: center; }
    .success-card h2 { color: var(--primary); font-size: 1.2rem; margin: 0 0 .5rem; }
    .success-card p { color: var(--gray-600); font-size: .9rem; margin: .5rem 0; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>Submit a Claim</h1>
    <a class="back-link" href="prod-dashboard.php">← Pro-D Portal</a>
  </div>

  <?php if ($saved): ?>
  <div class="success-card">
    <h2>✓ Claim submitted</h2>
    <p>Your claim has been sent for review. You'll be notified once it's approved or if any corrections are needed.</p>
    <div style="display:flex;gap:.75rem;justify-content:center;margin-top:1.25rem;flex-wrap:wrap;">
      <a href="prod-claim-new.php" class="btn btn-primary" style="padding:.55rem 1.1rem;font-size:.9rem;">Submit Another</a>
      <a href="prod-claims.php"    class="btn btn-outline"  style="padding:.55rem 1.1rem;font-size:.9rem;">View My Claims</a>
    </div>
  </div>

  <?php else: ?>

  <div class="balance-chip">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
    Available balance: <strong>$<?= number_format($bal['balance'], 2) ?></strong>
  </div>

  <!-- Receipt upload -->
  <div class="upload-area" id="uploadArea">
    <input type="file" id="receiptFile" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" onchange="handleFileSelect(this)">
    <div id="uploadPrompt">
      <div class="upload-icon">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      </div>
      <div class="upload-title">Upload your receipt <span style="font-weight:400;color:var(--gray-400)">(optional)</span></div>
      <div class="upload-sub">JPG, PNG, WebP, or PDF — AI will extract the details for you</div>
    </div>
    <div id="uploadFile" style="display:none;">
      <div class="upload-icon" style="background:#dcfce7;">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      </div>
      <div class="upload-title" id="uploadFileName"></div>
      <div class="upload-sub">Click to change</div>
    </div>
  </div>

  <div class="scan-state scanning" id="scanScanning">
    <svg class="spin" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
    Scanning receipt with AI…
  </div>
  <div class="scan-state success" id="scanSuccess">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <span id="scanSuccessMsg">Receipt scanned — fields pre-filled below. Review before submitting.</span>
  </div>
  <div class="scan-state error" id="scanError">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    <span id="scanErrorMsg"></span>
  </div>

  <div class="flag-banner" id="flagBanner"></div>

  <!-- Claim form -->
  <form method="POST" id="claimForm">
    <input type="hidden" name="receipt_path"     id="hiddenReceiptPath">
    <input type="hidden" name="receipt_filename" id="hiddenReceiptFilename">
    <input type="hidden" name="extracted_vendor"   id="hiddenVendor">
    <input type="hidden" name="extracted_date"     id="hiddenExDate">
    <input type="hidden" name="extracted_amount"   id="hiddenExAmount">
    <input type="hidden" name="extraction_flag"    id="hiddenFlag">
    <input type="hidden" name="extraction_concerns" id="hiddenConcerns">

    <div class="form-card">

      <div class="field-row">
        <div class="field">
          <label for="expense_date">Expense Date</label>
          <input type="date" name="expense_date" id="expense_date"
            max="<?= date('Y-m-d') ?>"
            value="<?= htmlspecialchars($_POST['expense_date'] ?? '') ?>"
            class="<?= isset($errors['date']) ? 'err' : '' ?>">
          <div class="prefill-label" id="datePrefillLabel">✦ Filled from receipt</div>
          <?php if (isset($errors['date'])): ?><div class="field-err"><?= $errors['date'] ?></div><?php endif; ?>
        </div>

        <div class="field">
          <label for="category">Category</label>
          <select name="category" id="category" class="<?= isset($errors['category']) ? 'err' : '' ?>">
            <option value="">Choose…</option>
            <?php foreach ($catLabels as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($_POST['category'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <div class="prefill-label" id="catPrefillLabel">✦ Filled from receipt</div>
          <?php if (isset($errors['category'])): ?><div class="field-err"><?= $errors['category'] ?></div><?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label for="amount_claimed">Amount Claimed ($)</label>
        <input type="number" name="amount_claimed" id="amount_claimed"
          min="0.01" step="0.01" placeholder="0.00"
          value="<?= htmlspecialchars($_POST['amount_claimed'] ?? '') ?>"
          class="<?= isset($errors['amount']) ? 'err' : '' ?>">
        <div class="prefill-label" id="amtPrefillLabel">✦ Filled from receipt — verify this matches your claim</div>
        <?php if (isset($errors['amount'])): ?><div class="field-err"><?= $errors['amount'] ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label for="description">Description / Notes</label>
        <textarea name="description" id="description" rows="3"
          placeholder="e.g. 'Reading Instruction workshop registration — BCTF, Feb 2025'"
          class="<?= isset($errors['desc']) ? 'err' : '' ?>"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        <?php if (isset($errors['desc'])): ?><div class="field-err"><?= $errors['desc'] ?></div><?php endif; ?>
        <div class="field-hint">Include the name of the course, conference, or item purchased.</div>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:.75rem;font-size:.95rem;margin-top:.25rem;">
        Submit Claim for Review
      </button>
    </div>
  </form>

  <?php endif; ?>
</div>

<script>
let scanResult = null;

function handleFileSelect(input) {
  const file = input.files[0];
  if (!file) return;

  // Show file name in upload area
  document.getElementById('uploadPrompt').style.display = 'none';
  document.getElementById('uploadFile').style.display   = 'block';
  document.getElementById('uploadFileName').textContent  = file.name;
  document.getElementById('uploadArea').classList.add('has-file');

  // Hide previous states
  hideScanStates();
  document.getElementById('scanScanning').classList.add('scanning');

  // Upload & scan
  const fd = new FormData();
  fd.append('receipt', file);

  fetch('prod-scan.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      hideScanStates();
      if (data.error) {
        document.getElementById('scanErrorMsg').textContent = data.error;
        document.getElementById('scanError').classList.add('error');
        // Still save the path if we got one
        if (data.saved_path) setReceiptHidden(data.saved_path, data.original_name);
      } else {
        scanResult = data;
        setReceiptHidden(data.saved_path, data.original_name);
        prefillForm(data);
        document.getElementById('scanSuccess').classList.add('success');
        if (data.flag) showFlag(data.flag, data.concerns);
      }
    })
    .catch(() => {
      hideScanStates();
      document.getElementById('scanErrorMsg').textContent = 'Scan failed — fill in the form manually. The file is still attached.';
      document.getElementById('scanError').classList.add('error');
    });
}

function hideScanStates() {
  ['scanScanning','scanSuccess','scanError'].forEach(id => {
    const el = document.getElementById(id);
    el.classList.remove('scanning','success','error');
  });
}

function setReceiptHidden(path, name) {
  document.getElementById('hiddenReceiptPath').value     = path;
  document.getElementById('hiddenReceiptFilename').value = name;
}

function prefillForm(data) {
  if (data.date) {
    document.getElementById('expense_date').value = data.date;
    document.getElementById('expense_date').classList.add('prefilled');
    document.getElementById('datePrefillLabel').classList.add('visible');
  }
  if (data.amount) {
    document.getElementById('amount_claimed').value = parseFloat(data.amount).toFixed(2);
    document.getElementById('amount_claimed').classList.add('prefilled');
    document.getElementById('amtPrefillLabel').classList.add('visible');
  }
  if (data.category) {
    const sel = document.getElementById('category');
    for (let opt of sel.options) {
      if (opt.value === data.category) { opt.selected = true; break; }
    }
    sel.classList.add('prefilled');
    document.getElementById('catPrefillLabel').classList.add('visible');
  }
  if (data.vendor) {
    const desc = document.getElementById('description');
    if (!desc.value) desc.value = data.vendor;
  }
  // Store extracted values in hidden fields
  document.getElementById('hiddenVendor').value   = data.vendor   || '';
  document.getElementById('hiddenExDate').value   = data.date     || '';
  document.getElementById('hiddenExAmount').value = data.amount   || '';
  document.getElementById('hiddenFlag').value     = data.flag     || '';
  document.getElementById('hiddenConcerns').value = data.concerns || '';
}

function showFlag(flag, concerns) {
  const msgs = {
    'suspicious_category': '⚠ The AI flagged a potential concern with this receipt: ' + (concerns || 'please review carefully before submitting.'),
    'date_mismatch':       '⚠ The date on the receipt may be outside the current claim year. Please verify.',
    'amount_mismatch':     '⚠ The amount on the receipt doesn\'t match your claimed amount. Please double-check.',
  };
  const banner = document.getElementById('flagBanner');
  banner.textContent = msgs[flag] || ('⚠ Flag: ' + flag);
  banner.classList.add('visible');
}

// Drag & drop
const area = document.getElementById('uploadArea');
area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('drag-over'); });
area.addEventListener('dragleave', () => area.classList.remove('drag-over'));
area.addEventListener('drop', e => {
  e.preventDefault();
  area.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) {
    const input = document.getElementById('receiptFile');
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    handleFileSelect(input);
  }
});
</script>
</body>
</html>
