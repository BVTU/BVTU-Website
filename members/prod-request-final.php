<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: prod-requests.php'); exit; }

$db = getDB();
$s  = $db->prepare("SELECT * FROM prod_requests WHERE id=? AND user_email=?");
$s->execute([$id, $member['email']]);
$req = $s->fetch();

if (!$req) { header('Location: prod-requests.php'); exit; }
if ($req['status'] !== 'approved') { header('Location: prod-requests.php'); exit; }
if ($req['final_submitted']) { header('Location: prod-requests.php'); exit; }

$catLabels = [
    'conference' => 'Conference / Workshop',
    'course'     => 'Course / Training',
    'materials'  => 'Materials / Resources',
    'travel'     => 'Travel',
    'other'      => 'Other',
];

$errors = [];
$saved  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $finalAmount = trim($_POST['final_amount']   ?? '');
    $finalDesc   = trim($_POST['final_description'] ?? '');
    $savedPath   = trim($_POST['saved_path']     ?? '');
    $origName    = trim($_POST['original_name']  ?? '');
    $exVendor    = trim($_POST['ext_vendor']     ?? '');
    $exDate      = trim($_POST['ext_date']       ?? '');
    $exAmount    = trim($_POST['ext_amount']     ?? '');
    $exFlag      = trim($_POST['ext_flag']       ?? '');
    $exConcerns  = trim($_POST['ext_concerns']   ?? '');
    $noReceipt   = isset($_POST['no_receipt']) ? 1 : 0;

    if (!is_numeric($finalAmount) || (float)$finalAmount < 0)
        $errors['amount'] = 'Please enter the actual amount (enter 0 if days only, no expenses).';
    if (!$finalDesc)
        $errors['description'] = 'Please provide a brief description of what you attended.';
    if (!$noReceipt && !$savedPath)
        $errors['receipt'] = 'Please upload a receipt, or check the box if there is nothing to claim.';

    if (!$errors) {
        // Validate saved path is just a filename (no directory traversal)
        $safePath = $savedPath ? basename($savedPath) : null;

        $db->prepare("UPDATE prod_requests SET
            final_submitted    = 1,
            final_amount       = ?,
            final_description  = ?,
            receipt_path       = ?,
            receipt_filename   = ?,
            extracted_vendor   = ?,
            extracted_date     = ?,
            extracted_amount   = ?,
            extraction_flag    = ?,
            extraction_concerns= ?,
            final_status       = 'pending'
            WHERE id=? AND user_email=?")
           ->execute([
               round((float)$finalAmount, 2),
               $finalDesc,
               $safePath,
               $safePath ? $origName : null,
               $exVendor  ?: null,
               $exDate    ?: null,
               is_numeric($exAmount) ? round((float)$exAmount, 2) : null,
               $exFlag    ?: null,
               $exConcerns ?: null,
               $id, $member['email'],
           ]);
        $saved = true;
    }
}

$dates = json_decode($req['request_dates'], true) ?: [];

// Generate phone QR upload token
$uploadToken = prodCreateUploadToken($id, $member['email']);
$protocol    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host        = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'bvtu.ca';
$mobileUrl   = "{$protocol}://{$host}/members/prod-mobile-receipt.php?token={$uploadToken}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Final Claim — BVTU Pro-D</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 720px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    /* Flow steps */
    .flow-steps { display: flex; gap: 0; margin-bottom: 2rem; }
    .flow-step { flex: 1; padding: .6rem .5rem; text-align: center; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
    .flow-step.done     { background: #f0fdf4; color: #166534; }
    .flow-step.active   { background: var(--primary); color: #fff; border-radius: 0; }
    .flow-step.upcoming { background: var(--gray-100); color: var(--gray-400); border-radius: 0 8px 8px 0; }
    .flow-step:first-child { border-radius: 8px 0 0 8px; }
    .flow-step:last-child  { border-radius: 0 8px 8px 0; }
    .flow-arrow { display: flex; align-items: center; color: var(--gray-300); font-size: 1rem; }

    /* Request summary card */
    .summary-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.75rem; }
    .summary-card h3 { font-size: .85rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: #166534; margin: 0 0 .6rem; }
    .summary-row { display: flex; gap: 1.5rem; flex-wrap: wrap; }
    .summary-item .lbl { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #4ade80; margin-bottom: .1rem; }
    .summary-item .val { font-size: .88rem; color: #14532d; font-weight: 600; }

    .form-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.75rem; }
    .section-label { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--primary); margin: 1.25rem 0 .75rem; padding-bottom: .4rem; border-bottom: 2px solid var(--accent); }
    .section-label:first-child { margin-top: 0; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .3rem; }
    .field input, .field select, .field textarea { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .6rem .8rem; font-size: .92rem; font-family: inherit; box-sizing: border-box; transition: border-color .15s; }
    .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .field input.err, .field textarea.err { border-color: #dc2626; }
    .field-err { font-size: .78rem; color: #dc2626; margin-top: .28rem; }
    .field-hint { font-size: .75rem; color: var(--gray-400); margin-top: .28rem; }

    /* Receipt uploader */
    .upload-zone { border: 2px dashed var(--gray-300); border-radius: 10px; padding: 1.75rem; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; position: relative; }
    .upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: var(--accent); }
    .upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .upload-zone .upload-icon { font-size: 2rem; margin-bottom: .5rem; }
    .upload-zone p { font-size: .85rem; color: var(--gray-500); margin: 0; }
    .upload-zone .btn-sm { display: inline-block; margin-top: .75rem; padding: .4rem .9rem; font-size: .8rem; }

    .scan-result { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 9px; padding: 1rem 1.15rem; margin-top: .75rem; display: none; }
    .scan-result h4 { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: #166534; margin: 0 0 .6rem; display: flex; align-items: center; gap: .4rem; }
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

    .success-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 2rem; text-align: center; }
    .success-card h2 { color: var(--primary); font-size: 1.2rem; margin: 0 0 .5rem; }
    .success-card p { color: var(--gray-600); font-size: .9rem; margin: .4rem 0; line-height: 1.6; }

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
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>Submit Final Claim</h1>
    <a class="back-link" href="prod-requests.php">← My Requests</a>
  </div>

  <!-- Flow indicator -->
  <div class="flow-steps">
    <div class="flow-step done">① Submit Request</div>
    <div class="flow-arrow">›</div>
    <div class="flow-step done">② Approval</div>
    <div class="flow-arrow">›</div>
    <div class="flow-step done">③ Attend Event</div>
    <div class="flow-arrow">›</div>
    <div class="flow-step active">④ Submit Final Claim</div>
  </div>

  <?php if ($saved): ?>
  <div class="success-card">
    <h2>✓ Final claim submitted</h2>
    <p>Your claim is now under financial review. You'll be notified once it's been processed.</p>
    <p style="font-size:.8rem;color:var(--gray-400);">The approved amount will be deducted from your balance once the claim is financially approved.</p>
    <div style="display:flex;gap:.75rem;justify-content:center;margin-top:1.25rem;flex-wrap:wrap;">
      <a href="prod-request-new.php" class="btn btn-primary"  style="padding:.55rem 1.1rem;font-size:.9rem;">New Request</a>
      <a href="prod-requests.php"    class="btn btn-outline"  style="padding:.55rem 1.1rem;font-size:.9rem;">View My Requests</a>
    </div>
  </div>

  <?php else: ?>

  <!-- Summary of approved request -->
  <div class="summary-card">
    <h3>Approved Request</h3>
    <div class="summary-row">
      <div class="summary-item">
        <div class="lbl">Activity</div>
        <div class="val"><?= htmlspecialchars($req['activity_description']) ?></div>
      </div>
      <div class="summary-item">
        <div class="lbl">Dates</div>
        <div class="val"><?php $dateFmt = array_map(function($d) { return date('M j, Y', strtotime($d)); }, $dates); echo implode(', ', $dateFmt); ?></div>
      </div>
      <div class="summary-item">
        <div class="lbl">Days</div>
        <div class="val"><?= number_format($req['num_days'], 1) ?></div>
      </div>
      <?php if ((float)$req['tentative_amount'] > 0): ?>
      <div class="summary-item">
        <div class="lbl">Estimated</div>
        <div class="val">$<?= number_format($req['tentative_amount'], 2) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-card">
    <form method="POST" id="finalForm">

      <p class="section-label">Receipt Upload</p>

      <div class="field">
        <!-- Phone QR upload button -->
        <button type="button" class="phone-upload-btn" onclick="toggleQR()">📱 Upload from phone</button>

        <!-- QR panel -->
        <div class="qr-panel" id="qrPanel">
          <div class="qr-box">
            <img id="qrImg" src="" width="160" height="160" alt="QR code" style="border-radius:8px;display:block;">
          </div>
          <div class="qr-instructions">
            <h3>Scan with your phone</h3>
            <p>Open your camera app and point it at the QR code. Take a photo of your receipt and it'll appear here automatically.</p>
            <div class="qr-url" id="qrUrlText"></div>
          </div>
        </div>

        <div class="or-divider">— or upload from this device —</div>

        <div class="upload-zone" id="uploadZone">
          <input type="file" id="receiptFile" accept="image/*,.pdf" onchange="handleFile(this.files[0])">
          <div class="upload-icon">📄</div>
          <p><strong>Upload your receipt</strong></p>
          <p>JPG, PNG, WebP or PDF · max 10 MB</p>
          <p class="btn btn-outline btn-sm" style="margin-top:.5rem;">Choose file</p>
        </div>

        <div class="scanning-indicator" id="scanningIndicator">
          <div class="spinner"></div>
          <span>Scanning receipt with AI…</span>
        </div>

        <div class="scan-result" id="scanResult">
          <h4>✓ Receipt scanned</h4>
          <div class="scan-grid">
            <div class="scan-field"><div class="lbl">Vendor</div><div class="val" id="sr_vendor">—</div></div>
            <div class="scan-field"><div class="lbl">Date</div><div class="val" id="sr_date">—</div></div>
            <div class="scan-field"><div class="lbl">Amount</div><div class="val" id="sr_amount">—</div></div>
            <div class="scan-field"><div class="lbl">Category</div><div class="val" id="sr_category">—</div></div>
          </div>
        </div>

        <div class="flag-banner" id="flagBanner"></div>

        <?php if (isset($errors['receipt'])): ?>
        <div class="field-err"><?= $errors['receipt'] ?></div>
        <?php endif; ?>

        <div class="no-receipt-row">
          <input type="checkbox" name="no_receipt" id="no_receipt" value="1"
            onchange="toggleNoReceipt(this)"
            <?= !empty($_POST['no_receipt']) ? 'checked' : '' ?>>
          <label for="no_receipt">I attended the event but have no expenses to claim (days only)</label>
        </div>
      </div>

      <!-- Hidden receipt data fields populated by JS -->
      <input type="hidden" name="saved_path"    id="saved_path"    value="<?= htmlspecialchars($_POST['saved_path'] ?? '') ?>">
      <input type="hidden" name="original_name" id="original_name" value="<?= htmlspecialchars($_POST['original_name'] ?? '') ?>">
      <input type="hidden" name="ext_vendor"    id="ext_vendor"    value="<?= htmlspecialchars($_POST['ext_vendor'] ?? '') ?>">
      <input type="hidden" name="ext_date"      id="ext_date"      value="<?= htmlspecialchars($_POST['ext_date'] ?? '') ?>">
      <input type="hidden" name="ext_amount"    id="ext_amount"    value="<?= htmlspecialchars($_POST['ext_amount'] ?? '') ?>">
      <input type="hidden" name="ext_flag"      id="ext_flag"      value="<?= htmlspecialchars($_POST['ext_flag'] ?? '') ?>">
      <input type="hidden" name="ext_concerns"  id="ext_concerns"  value="<?= htmlspecialchars($_POST['ext_concerns'] ?? '') ?>">

      <p class="section-label">Final Details</p>

      <div class="field" style="max-width:220px;">
        <label for="final_amount">Actual Amount Claimed ($) *</label>
        <input type="number" name="final_amount" id="final_amount" min="0" step="0.01" placeholder="0.00"
          value="<?= htmlspecialchars($_POST['final_amount'] ?? '') ?>"
          class="<?= isset($errors['amount']) ? 'err' : '' ?>">
        <div class="field-hint">Enter 0 if you only used release days with no funding.</div>
        <?php if (isset($errors['amount'])): ?><div class="field-err"><?= $errors['amount'] ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label for="final_description">What did you attend / purchase? *</label>
        <textarea name="final_description" id="final_description" rows="3"
          placeholder="Brief summary of the professional development activity and what you gained from it."
          class="<?= isset($errors['description']) ? 'err' : '' ?>"><?= htmlspecialchars($_POST['final_description'] ?? '') ?></textarea>
        <?php if (isset($errors['description'])): ?><div class="field-err"><?= $errors['description'] ?></div><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:.75rem;font-size:.95rem;margin-top:.25rem;">
        Submit Final Claim for Review
      </button>
    </form>
  </div>

  <?php endif; ?>
</div>

<div id="receiptToast"></div>

<script>
const REQUEST_ID = <?= $id ?>;
const MOBILE_URL = <?= json_encode($mobileUrl) ?>;
var qrGenerated  = false;
var qrPanelOpen  = false;
var pollInterval = null;
var toastTimer   = null;

function toggleQR() {
    qrPanelOpen = !qrPanelOpen;
    var panel = document.getElementById('qrPanel');
    panel.classList.toggle('open', qrPanelOpen);
    if (qrPanelOpen && !qrGenerated) {
        var img = document.getElementById('qrImg');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&color=1a2e1a&bgcolor=ffffff&data=' + encodeURIComponent(MOBILE_URL);
        document.getElementById('qrUrlText').textContent = MOBILE_URL;
        qrGenerated = true;
        if (!pollInterval) {
            pollReceipt();
            pollInterval = setInterval(pollReceipt, 5000);
        }
    }
}

function pollReceipt() {
    fetch('prod-poll-receipt.php?request_id=' + REQUEST_ID)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.receipt) return;
            clearInterval(pollInterval);
            pollInterval = null;
            applyPhoneReceipt(d.receipt);
            // Mark as claimed
            var fd = new FormData();
            fd.append('pending_id', d.receipt.id);
            fetch('prod-claim-receipt.php', { method: 'POST', body: fd });
        })
        .catch(function() {});
}

function applyPhoneReceipt(receipt) {
    var sd = receipt.scan_data || {};
    // Fill hidden fields
    document.getElementById('saved_path').value    = receipt.saved_path || '';
    document.getElementById('original_name').value = receipt.original_name || '';
    document.getElementById('ext_vendor').value    = sd.vendor        || '';
    document.getElementById('ext_date').value      = sd.date          || '';
    document.getElementById('ext_amount').value    = sd.total_amount  || sd.amount || '';
    document.getElementById('ext_flag').value      = sd.flag          || '';
    document.getElementById('ext_concerns').value  = sd.concerns      || '';
    // Auto-fill visible fields if empty
    var amt = sd.total_amount || sd.amount;
    if (amt && !document.getElementById('final_amount').value) {
        document.getElementById('final_amount').value = parseFloat(amt).toFixed(2);
    }
    // Show scan result card
    document.getElementById('sr_vendor').textContent   = sd.vendor          || '—';
    document.getElementById('sr_date').textContent     = sd.date            || '—';
    document.getElementById('sr_amount').textContent   = amt ? '$' + parseFloat(amt).toFixed(2) : '—';
    document.getElementById('sr_category').textContent = sd.likely_category || sd.category || '—';
    document.getElementById('scanResult').style.display = 'block';
    if (sd.concerns || sd.flag) {
        var msg = sd.concerns ? '⚠ Reviewer flag: ' + sd.concerns : '⚠ This receipt has been flagged for review.';
        document.getElementById('flagBanner').textContent = msg;
        document.getElementById('flagBanner').style.display = 'block';
    }
    // Close QR panel and show preview
    document.getElementById('qrPanel').classList.remove('open');
    qrPanelOpen = false;
    showToast('✅ Receipt received from phone — form auto-filled');
    // Show thumbnail in upload zone
    var zone = document.getElementById('uploadZone');
    zone.innerHTML = '<div style="font-size:2rem;margin-bottom:.5rem;">✅</div><p><strong>Receipt from phone</strong></p><p style="font-size:.8rem;color:var(--gray-400);">' + (receipt.original_name || receipt.saved_path) + '</p>';
}

function showToast(msg) {
    var t = document.getElementById('receiptToast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function() { t.classList.remove('show'); }, 4000);
}

function handleFile(file) {
  if (!file) return;
  const zone = document.getElementById('uploadZone');
  zone.querySelector('p:last-of-type').textContent = file.name;

  document.getElementById('scanningIndicator').style.display = 'flex';
  document.getElementById('scanResult').style.display        = 'none';
  document.getElementById('flagBanner').style.display        = 'none';

  const fd = new FormData();
  fd.append('receipt', file);

  fetch('prod-scan.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      document.getElementById('scanningIndicator').style.display = 'none';

      if (data.error && !data.saved_path) {
        document.getElementById('flagBanner').textContent = '⚠ ' + data.error;
        document.getElementById('flagBanner').style.display = 'block';
        return;
      }

      // Store hidden fields
      document.getElementById('saved_path').value    = data.saved_path    || '';
      document.getElementById('original_name').value = data.original_name || '';
      document.getElementById('ext_vendor').value    = data.vendor        || '';
      document.getElementById('ext_date').value      = data.date          || '';
      document.getElementById('ext_amount').value    = data.amount        || '';
      document.getElementById('ext_flag').value      = data.flag          || '';
      document.getElementById('ext_concerns').value  = data.concerns      || '';

      // Populate form fields if empty
      if (data.amount && !document.getElementById('final_amount').value) {
        document.getElementById('final_amount').value = data.amount;
      }

      // Show scan result card
      document.getElementById('sr_vendor').textContent   = data.vendor   || '—';
      document.getElementById('sr_date').textContent     = data.date     || '—';
      document.getElementById('sr_amount').textContent   = data.amount   ? '$' + parseFloat(data.amount).toFixed(2) : '—';
      document.getElementById('sr_category').textContent = data.category || '—';
      document.getElementById('scanResult').style.display = 'block';

      // Show flag banner if needed
      if (data.flag || data.concerns) {
        const msg = data.concerns
          ? '⚠ Reviewer flag: ' + data.concerns
          : '⚠ This receipt has been flagged for manual review.';
        document.getElementById('flagBanner').textContent = msg;
        document.getElementById('flagBanner').style.display = 'block';
      }

      if (data.error) {
        document.getElementById('flagBanner').textContent = '⚠ ' + data.error + ' (file saved — you can submit manually)';
        document.getElementById('flagBanner').style.display = 'block';
      }
    })
    .catch(() => {
      document.getElementById('scanningIndicator').style.display = 'none';
      document.getElementById('flagBanner').textContent = '⚠ Scan failed. Please fill in the form manually.';
      document.getElementById('flagBanner').style.display = 'block';
    });
}

function toggleNoReceipt(cb) {
  const zone = document.getElementById('uploadZone');
  zone.style.opacity = cb.checked ? '.4' : '1';
  zone.style.pointerEvents = cb.checked ? 'none' : '';
  if (cb.checked) {
    document.getElementById('final_amount').value = document.getElementById('final_amount').value || '0';
  }
}

// Drag-and-drop
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('dragover');
  const f = e.dataTransfer.files[0];
  if (f) handleFile(f);
});

// Restore scan result on PHP error re-render
<?php if (!empty($_POST['saved_path']) && !$saved): ?>
document.getElementById('saved_path').value    = <?= json_encode($_POST['saved_path']    ?? '') ?>;
document.getElementById('original_name').value = <?= json_encode($_POST['original_name'] ?? '') ?>;
if (<?= json_encode($_POST['saved_path'] ?? '') ?>) {
  document.getElementById('scanResult').style.display = 'block';
  document.getElementById('sr_vendor').textContent   = <?= json_encode($_POST['ext_vendor'] ?? '—') ?>;
  document.getElementById('sr_date').textContent     = <?= json_encode($_POST['ext_date']   ?? '—') ?>;
  document.getElementById('sr_amount').textContent   = <?= isset($_POST['ext_amount']) && $_POST['ext_amount'] !== '' ? "'$'+parseFloat(" . json_encode($_POST['ext_amount']) . ").toFixed(2)" : "'—'" ?>;
  document.getElementById('sr_category').textContent = '—';
}
<?php endif; ?>
</script>
</body>
</html>
