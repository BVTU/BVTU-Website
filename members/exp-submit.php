<?php
/**
 * exp-submit.php — Member expense submission form
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();

$errors    = [];
$submitted = false;
$newId     = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expenseDate  = trim($_POST['expense_date']      ?? '');
    $category     = trim($_POST['category']          ?? '');
    $amount       = trim($_POST['amount']            ?? '');
    $description  = trim($_POST['description']       ?? '');
    $savedPath    = basename(trim($_POST['saved_path']    ?? ''));
    $origName     = trim($_POST['original_name']     ?? '');
    $exVendor     = trim($_POST['ext_vendor']        ?? '');
    $exDate       = trim($_POST['ext_date']          ?? '');
    $exAmount     = trim($_POST['ext_amount']        ?? '');
    $exFlag       = trim($_POST['ext_flag']          ?? '');
    $exConcerns   = trim($_POST['ext_concerns']      ?? '');
    $noReceipt    = isset($_POST['no_receipt']);
    $draftId      = (int)($_POST['draft_expense_id'] ?? 0);

    // Validate
    if (!$expenseDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expenseDate)) {
        $errors['expense_date'] = 'Please enter a valid expense date.';
    }
    if (!$category || !in_array($category, EXP_CATEGORIES)) {
        $errors['category'] = 'Please select a valid category.';
    }
    if (!is_numeric($amount) || (float)$amount <= 0) {
        $errors['amount'] = 'Please enter a valid amount greater than zero.';
    }
    if (!$description) {
        $errors['description'] = 'Please describe the expense.';
    }
    if (!$noReceipt && !$savedPath) {
        $errors['receipt'] = 'Please upload a receipt, or check the box below if you have none.';
    }

    if (!$errors) {
        $scanData = [
            'vendor'   => $exVendor   ?: null,
            'date'     => $exDate     ?: null,
            'amount'   => is_numeric($exAmount) ? (float)$exAmount : null,
            'flag'     => $exFlag     ?: null,
            'concerns' => $exConcerns ?: null,
        ];

        // If draft exists and belongs to this user, UPDATE it; otherwise INSERT new row
        if ($draftId > 0) {
            $checkStmt = getDB()->prepare(
                "SELECT id FROM exp_expenses WHERE id=? AND user_email=? AND status='draft'"
            );
            $checkStmt->execute([$draftId, strtolower($member['email'])]);
            $draftExists = $checkStmt->fetchColumn();
        } else {
            $draftExists = false;
        }

        if ($draftExists) {
            $refCode = expGenerateRefCode();
            getDB()->prepare(
                "UPDATE exp_expenses SET
                    ref_code            = ?,
                    expense_date        = ?,
                    category            = ?,
                    amount              = ?,
                    description         = ?,
                    receipt_path        = ?,
                    receipt_filename    = ?,
                    extracted_vendor    = ?,
                    extracted_date      = ?,
                    extracted_amount    = ?,
                    extraction_flag     = ?,
                    extraction_concerns = ?,
                    status              = 'pending'
                 WHERE id = ? AND user_email = ? AND status = 'draft'"
            )->execute([
                $refCode,
                $expenseDate,
                $category,
                round((float)$amount, 2),
                $description,
                $savedPath ?: null,
                $savedPath ? ($origName ?: null) : null,
                $scanData['vendor'],
                $scanData['date'],
                $scanData['amount'] !== null ? round($scanData['amount'], 2) : null,
                $scanData['flag'],
                $scanData['concerns'],
                $draftId,
                strtolower($member['email']),
            ]);
            $newId = $draftId;
        } else {
            $newId = expCreate(
                $member['email'],
                $member['name'],
                $expenseDate,
                $category,
                (float)$amount,
                $description,
                $savedPath,
                $savedPath ? ($origName ?: '') : '',
                $scanData
            );
        }

        $exp = expGet($newId);
        if ($exp) {
            expEmailSubmitted($exp);
        }

        header('Location: exp-view.php?id=' . $newId . '&submitted=1');
        exit;
    }
}

$catLabels = [
    'meals'         => 'Meals',
    'travel'        => 'Travel',
    'supplies'      => 'Supplies',
    'conference'    => 'Conference / Workshop',
    'accommodation' => 'Accommodation',
    'other'         => 'Other',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Expense — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.75rem; margin-bottom: 1.5rem; }
    .section-label { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--primary); margin: 1.25rem 0 .75rem; padding-bottom: .4rem; border-bottom: 2px solid var(--accent); }
    .section-label:first-child { margin-top: 0; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    .field input, .field select, .field textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .8rem; font-size: .92rem; font-family: inherit; box-sizing: border-box; transition: border-color .15s; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field input.err, .field textarea.err, .field select.err { border-color: #dc2626; }
    .field-err { font-size: .78rem; color: #dc2626; margin-top: .28rem; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .28rem; }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 600px) { .field-row { grid-template-columns: 1fr; } }

    /* Receipt uploader */
    .upload-zone { border: 2px dashed var(--gray-300); border-radius: 10px; padding: 1.75rem; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; position: relative; }
    .upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: var(--accent); }
    .upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .upload-zone .upload-icon { font-size: 2rem; margin-bottom: .5rem; }
    .upload-zone p { font-size: .85rem; color: var(--gray-500); margin: 0; }

    .scan-result { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 9px; padding: 1rem 1.15rem; margin-top: .75rem; display: none; }
    .scan-result h4 { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #166534; margin: 0 0 .6rem; }
    .scan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px,1fr)); gap: .6rem; }
    .scan-field { font-size: .82rem; }
    .scan-field .lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; color: #4ade80; margin-bottom: .1rem; letter-spacing: .05em; }
    .scan-field .val { color: #14532d; font-weight: 600; }

    .flag-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: .75rem 1rem; font-size: .82rem; color: #92400e; margin-top: .6rem; display: none; }

    .no-receipt-row { display: flex; align-items: center; gap: .65rem; padding: .8rem 1rem; background: #f8f9fa; border-radius: 8px; margin-top: .75rem; }
    .no-receipt-row input[type=checkbox] { width: 18px; height: 18px; accent-color: var(--primary); flex-shrink: 0; }
    .no-receipt-row label { font-size: .88rem; color: var(--gray-700); font-weight: 500; cursor: pointer; }

    .scanning-indicator { display: none; align-items: center; gap: .6rem; font-size: .85rem; color: var(--gray-500); margin-top: .6rem; }
    .spinner { width: 18px; height: 18px; border: 2px solid var(--gray-200); border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; flex-shrink: 0; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Phone QR panel */
    .phone-upload-btn { background:#f0fdf4; color:var(--primary); border:1.5px solid #86efac; border-radius:8px; padding:.5rem .9rem; font-size:.85rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; margin-bottom:.75rem; }
    .phone-upload-btn:hover { background:#dcfce7; }
    .qr-panel { display:none; background:#f0fdf4; border:1.5px solid #86efac; border-radius:12px; padding:1.25rem 1.5rem; margin-bottom:1rem; }
    .qr-panel.open { display:flex; gap:1.5rem; align-items:flex-start; flex-wrap:wrap; }
    .qr-box { flex-shrink:0; }
    .qr-instructions h3 { font-size:.9rem; font-weight:800; color:var(--primary); margin:0 0 .4rem; }
    .qr-instructions p { font-size:.82rem; color:var(--gray-500); line-height:1.5; margin-bottom:.4rem; }
    .qr-url { font-size:.7rem; color:var(--gray-400); word-break:break-all; background:#fff; padding:.4rem .6rem; border-radius:5px; }
    #receiptToast { position:fixed; bottom:4rem; left:50%; transform:translateX(-50%) translateY(20px); background:#1a6b35; color:#fff; font-size:.9rem; font-weight:700; padding:.75rem 1.25rem; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,.2); opacity:0; transition:opacity .3s, transform .3s; pointer-events:none; z-index:9999; white-space:nowrap; }
    #receiptToast.show { opacity:1; transform:translateX(-50%) translateY(0); }
    .or-divider { text-align:center; font-size:.78rem; color:var(--gray-400); font-weight:600; margin:.75rem 0; letter-spacing:.04em; }

    .error-summary { background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:.8rem 1rem; margin-bottom:1.25rem; font-size:.88rem; color:#991b1b; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>Submit Expense</h1>
    <a class="back-link" href="exp-dashboard.php">&#x2190; My Expenses</a>
  </div>

  <?php if ($errors): ?>
  <div class="error-summary">
    &#x26A0; Please fix the errors below before submitting.
  </div>
  <?php endif; ?>

  <div class="form-card">
    <form method="POST" id="expForm">

      <p class="section-label">Receipt</p>

      <div class="field">
        <!-- Phone QR upload button -->
        <button type="button" class="phone-upload-btn" id="phoneQrBtn" onclick="initPhoneQR()">
          &#x1F4F1; Upload from phone
        </button>

        <!-- QR panel -->
        <div class="qr-panel" id="qrPanel">
          <div class="qr-box">
            <img id="qrImg" src="" width="160" height="160" alt="QR code" style="border-radius:8px;display:block;">
          </div>
          <div class="qr-instructions">
            <h3>Scan with your phone</h3>
            <p>Open your camera app and point it at the QR code. Take a photo of your receipt and it will appear here automatically.</p>
            <div class="qr-url" id="qrUrlText"></div>
          </div>
        </div>

        <div class="or-divider">&#x2014; or upload from this device &#x2014;</div>

        <div class="upload-zone" id="uploadZone">
          <input type="file" id="receiptFile" accept="image/*,.pdf" onchange="handleFile(this.files[0])">
          <div class="upload-icon">&#x1F4C4;</div>
          <p><strong>Upload your receipt</strong></p>
          <p>JPG, PNG, WebP or PDF &middot; max 10 MB</p>
          <p class="btn btn-outline" style="display:inline-block;margin-top:.5rem;padding:.35rem .8rem;font-size:.8rem;">Choose file</p>
        </div>

        <div class="scanning-indicator" id="scanningIndicator">
          <div class="spinner"></div>
          <span>Scanning receipt with AI&hellip;</span>
        </div>

        <div class="scan-result" id="scanResult">
          <h4>&#x2713; Receipt scanned</h4>
          <div class="scan-grid">
            <div class="scan-field"><div class="lbl">Vendor</div><div class="val" id="sr_vendor">&#x2014;</div></div>
            <div class="scan-field"><div class="lbl">Date</div><div class="val" id="sr_date">&#x2014;</div></div>
            <div class="scan-field"><div class="lbl">Amount</div><div class="val" id="sr_amount">&#x2014;</div></div>
            <div class="scan-field"><div class="lbl">Category</div><div class="val" id="sr_category">&#x2014;</div></div>
          </div>
        </div>

        <div class="flag-banner" id="flagBanner"></div>

        <?php if (isset($errors['receipt'])): ?>
        <div class="field-err"><?= htmlspecialchars($errors['receipt']) ?></div>
        <?php endif; ?>

        <div class="no-receipt-row">
          <input type="checkbox" name="no_receipt" id="no_receipt" value="1"
            onchange="toggleNoReceipt(this)"
            <?= !empty($_POST['no_receipt']) ? 'checked' : '' ?>>
          <label for="no_receipt">I have no receipt for this expense</label>
        </div>
      </div>

      <!-- Hidden receipt fields populated by JS -->
      <input type="hidden" name="saved_path"       id="saved_path"       value="<?= htmlspecialchars($_POST['saved_path']    ?? '') ?>">
      <input type="hidden" name="original_name"    id="original_name"    value="<?= htmlspecialchars($_POST['original_name'] ?? '') ?>">
      <input type="hidden" name="ext_vendor"       id="ext_vendor"       value="<?= htmlspecialchars($_POST['ext_vendor']    ?? '') ?>">
      <input type="hidden" name="ext_date"         id="ext_date"         value="<?= htmlspecialchars($_POST['ext_date']      ?? '') ?>">
      <input type="hidden" name="ext_amount"       id="ext_amount"       value="<?= htmlspecialchars($_POST['ext_amount']    ?? '') ?>">
      <input type="hidden" name="ext_flag"         id="ext_flag"         value="<?= htmlspecialchars($_POST['ext_flag']      ?? '') ?>">
      <input type="hidden" name="ext_concerns"     id="ext_concerns"     value="<?= htmlspecialchars($_POST['ext_concerns']  ?? '') ?>">
      <input type="hidden" name="draft_expense_id" id="draft_expense_id" value="<?= (int)($_POST['draft_expense_id'] ?? 0) ?>">

      <p class="section-label">Expense Details</p>

      <div class="field-row">
        <div class="field">
          <label for="expense_date">Expense Date *</label>
          <input type="date" name="expense_date" id="expense_date"
            value="<?= htmlspecialchars($_POST['expense_date'] ?? date('Y-m-d')) ?>"
            class="<?= isset($errors['expense_date']) ? 'err' : '' ?>"
            max="<?= date('Y-m-d') ?>" required>
          <?php if (isset($errors['expense_date'])): ?>
          <div class="field-err"><?= htmlspecialchars($errors['expense_date']) ?></div>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="category">Category *</label>
          <select name="category" id="category"
            class="<?= isset($errors['category']) ? 'err' : '' ?>" required>
            <option value="">Choose category&hellip;</option>
            <?php foreach ($catLabels as $val => $label): ?>
            <option value="<?= $val ?>"
              <?= (($_POST['category'] ?? '') === $val) ? 'selected' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['category'])): ?>
          <div class="field-err"><?= htmlspecialchars($errors['category']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="field" style="max-width:220px;">
        <label for="amount">Amount ($) *</label>
        <input type="number" name="amount" id="amount" min="0.01" step="0.01" placeholder="0.00"
          value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
          class="<?= isset($errors['amount']) ? 'err' : '' ?>" required>
        <?php if (isset($errors['amount'])): ?>
        <div class="field-err"><?= htmlspecialchars($errors['amount']) ?></div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="description">Description *</label>
        <textarea name="description" id="description" rows="3"
          placeholder="Brief description of the expense and its purpose."
          class="<?= isset($errors['description']) ? 'err' : '' ?>"
          ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        <?php if (isset($errors['description'])): ?>
        <div class="field-err"><?= htmlspecialchars($errors['description']) ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:.75rem;font-size:.95rem;margin-top:.25rem;">
        Submit Expense for Review
      </button>
    </form>
  </div>

</div>

<div id="receiptToast"></div>

<script>
var draftExpenseId = <?= (int)($_POST['draft_expense_id'] ?? 0) ?>;
var draftMobileUrl = null;
var qrGenerated    = false;
var qrPanelOpen    = false;
var pollInterval   = null;
var toastTimer     = null;

// ── Phone QR ──────────────────────────────────────────────────────────────────
function initPhoneQR() {
    // If no draft created yet, create one first
    if (!draftExpenseId) {
        fetch('exp-create-draft.php', { method: 'POST' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) { showToast('Could not prepare phone upload.'); return; }
                draftExpenseId = d.expense_id;
                draftMobileUrl = d.mobile_url;
                document.getElementById('draft_expense_id').value = draftExpenseId;
                openQRPanel(draftMobileUrl);
            })
            .catch(function() { showToast('Network error.'); });
    } else {
        // Token already exists — re-use URL if we have it
        if (draftMobileUrl) {
            openQRPanel(draftMobileUrl);
        } else {
            // Regenerate token
            fetch('exp-create-draft.php', { method: 'POST' })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (!d.ok) { showToast('Could not prepare phone upload.'); return; }
                    draftMobileUrl = d.mobile_url;
                    openQRPanel(draftMobileUrl);
                });
        }
    }
}

function openQRPanel(url) {
    qrPanelOpen = true;
    var panel = document.getElementById('qrPanel');
    panel.classList.add('open');
    if (!qrGenerated) {
        var img = document.getElementById('qrImg');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=1a2e1a&bgcolor=ffffff&data=' + encodeURIComponent(url);
        document.getElementById('qrUrlText').textContent = url;
        qrGenerated = true;
    }
    if (!pollInterval) {
        pollReceipt();
        pollInterval = setInterval(pollReceipt, 5000);
    }
}

function pollReceipt() {
    if (!draftExpenseId) return;
    fetch('exp-poll-receipt.php?expense_id=' + draftExpenseId)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.receipt) return;
            clearInterval(pollInterval);
            pollInterval = null;
            applyPhoneReceipt(d.receipt);
            var fd = new FormData();
            fd.append('pending_id', d.receipt.id);
            fetch('exp-claim-receipt.php', { method: 'POST', body: fd });
        })
        .catch(function() {});
}

function applyPhoneReceipt(receipt) {
    var sd = receipt.scan_data || {};
    document.getElementById('saved_path').value    = receipt.saved_path    || '';
    document.getElementById('original_name').value = receipt.original_name || '';
    document.getElementById('ext_vendor').value    = sd.vendor   || '';
    document.getElementById('ext_date').value      = sd.date     || '';
    document.getElementById('ext_amount').value    = sd.amount   || '';
    document.getElementById('ext_flag').value      = sd.flag     || '';
    document.getElementById('ext_concerns').value  = sd.concerns || '';

    // Auto-fill visible fields if empty
    var amt = sd.amount;
    if (amt && !document.getElementById('amount').value) {
        document.getElementById('amount').value = parseFloat(amt).toFixed(2);
    }
    if (sd.date && !document.getElementById('expense_date').value) {
        document.getElementById('expense_date').value = sd.date;
    }
    if (sd.category) {
        var sel = document.getElementById('category');
        if (!sel.value) {
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === sd.category) {
                    sel.selectedIndex = i;
                    break;
                }
            }
        }
    }

    // Show scan result card
    document.getElementById('sr_vendor').textContent   = sd.vendor   || '—';
    document.getElementById('sr_date').textContent     = sd.date     || '—';
    document.getElementById('sr_amount').textContent   = amt ? '$' + parseFloat(amt).toFixed(2) : '—';
    document.getElementById('sr_category').textContent = sd.category || '—';
    document.getElementById('scanResult').style.display = 'block';

    if (sd.concerns || sd.flag) {
        var msg = sd.concerns ? '⚠ Reviewer flag: ' + sd.concerns : '⚠ This receipt has been flagged for review.';
        document.getElementById('flagBanner').textContent = msg;
        document.getElementById('flagBanner').style.display = 'block';
    }

    document.getElementById('qrPanel').classList.remove('open');
    qrPanelOpen = false;
    showToast('✅ Receipt received from phone — form auto-filled');

    var zone = document.getElementById('uploadZone');
    zone.innerHTML = '<div style="font-size:2rem;margin-bottom:.5rem;">&#x2705;</div>'
                   + '<p><strong>Receipt from phone</strong></p>'
                   + '<p style="font-size:.8rem;color:var(--gray-400);">' + (receipt.original_name || receipt.saved_path) + '</p>';
}

// ── Desktop file handler ───────────────────────────────────────────────────────
function handleFile(file) {
    if (!file) return;

    document.getElementById('scanningIndicator').style.display = 'flex';
    document.getElementById('scanResult').style.display        = 'none';
    document.getElementById('flagBanner').style.display        = 'none';

    var fd = new FormData();
    fd.append('receipt', file);

    fetch('exp-scan.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('scanningIndicator').style.display = 'none';

            if (data.error && !data.saved_path) {
                document.getElementById('flagBanner').textContent = '⚠ ' + data.error;
                document.getElementById('flagBanner').style.display = 'block';
                return;
            }

            document.getElementById('saved_path').value    = data.saved_path    || '';
            document.getElementById('original_name').value = data.original_name || '';
            document.getElementById('ext_vendor').value    = data.vendor        || '';
            document.getElementById('ext_date').value      = data.date          || '';
            document.getElementById('ext_amount').value    = data.amount        || '';
            document.getElementById('ext_flag').value      = data.flag          || '';
            document.getElementById('ext_concerns').value  = data.concerns      || '';

            // Auto-fill visible fields if empty
            if (data.amount && !document.getElementById('amount').value) {
                document.getElementById('amount').value = parseFloat(data.amount).toFixed(2);
            }
            if (data.date && !document.getElementById('expense_date').value) {
                document.getElementById('expense_date').value = data.date;
            }
            if (data.category) {
                var sel = document.getElementById('category');
                if (!sel.value) {
                    for (var i = 0; i < sel.options.length; i++) {
                        if (sel.options[i].value === data.category) { sel.selectedIndex = i; break; }
                    }
                }
            }

            document.getElementById('sr_vendor').textContent   = data.vendor   || '—';
            document.getElementById('sr_date').textContent     = data.date     || '—';
            document.getElementById('sr_amount').textContent   = data.amount   ? '$' + parseFloat(data.amount).toFixed(2) : '—';
            document.getElementById('sr_category').textContent = data.category || '—';
            document.getElementById('scanResult').style.display = 'block';

            if (data.flag || data.concerns) {
                var msg = data.concerns
                    ? '⚠ Reviewer flag: ' + data.concerns
                    : '⚠ This receipt has been flagged for manual review.';
                document.getElementById('flagBanner').textContent = msg;
                document.getElementById('flagBanner').style.display = 'block';
            }
            if (data.error) {
                document.getElementById('flagBanner').textContent = '⚠ ' + data.error + ' (file saved — fill form manually)';
                document.getElementById('flagBanner').style.display = 'block';
            }

            var zone = document.getElementById('uploadZone');
            zone.querySelector('p').textContent = file.name;
        })
        .catch(function() {
            document.getElementById('scanningIndicator').style.display = 'none';
            document.getElementById('flagBanner').textContent = '⚠ Scan failed. Please fill in the form manually.';
            document.getElementById('flagBanner').style.display = 'block';
        });
}

function toggleNoReceipt(cb) {
    var zone = document.getElementById('uploadZone');
    zone.style.opacity       = cb.checked ? '.4' : '1';
    zone.style.pointerEvents = cb.checked ? 'none' : '';
}

function showToast(msg) {
    var t = document.getElementById('receiptToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function() { t.classList.remove('show'); }, 4000);
}

// Drag-and-drop
var zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', function() { zone.classList.remove('dragover'); });
zone.addEventListener('drop', function(e) {
    e.preventDefault();
    zone.classList.remove('dragover');
    var f = e.dataTransfer.files[0];
    if (f) handleFile(f);
});

// Restore scan results on PHP error re-render
<?php if (!empty($_POST['saved_path'])): ?>
(function() {
    var sp = <?= json_encode($_POST['saved_path'] ?? '') ?>;
    if (sp) {
        document.getElementById('scanResult').style.display = 'block';
        document.getElementById('sr_vendor').textContent   = <?= json_encode($_POST['ext_vendor'] ?? '—') ?>;
        document.getElementById('sr_date').textContent     = <?= json_encode($_POST['ext_date']   ?? '—') ?>;
        var ea = <?= json_encode($_POST['ext_amount'] ?? '') ?>;
        document.getElementById('sr_amount').textContent   = ea ? '$' + parseFloat(ea).toFixed(2) : '—';
        document.getElementById('sr_category').textContent = '—';
    }
})();
<?php endif; ?>
</script>
</body>
</html>
