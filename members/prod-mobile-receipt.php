<?php
/**
 * prod-mobile-receipt.php — Phone receipt uploader (token-gated, no login required)
 * URL: /members/prod-mobile-receipt.php?token=XXXX
 */
require_once __DIR__ . '/prod-db.php';

prodEnsureTables();

$token    = trim($_GET['token'] ?? '');
$tokenRow = $token ? prodValidateUploadToken($token) : null;

// Load the prod_request so we can show the activity description
$req = null;
if ($tokenRow) {
    $s = getDB()->prepare("SELECT id, activity_description FROM prod_requests WHERE id=? LIMIT 1");
    $s->execute([(int)$tokenRow['request_id']]);
    $req = $s->fetch() ?: null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#1a6b35">
  <title>Upload Receipt — BVTU Pro-D</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      background: #f0f4f1;
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem 1.25rem env(safe-area-inset-bottom, 1rem);
      color: #1a2e1a;
    }

    .card {
      background: #fff;
      border-radius: 20px;
      padding: 2rem 1.75rem 2.5rem;
      width: 100%;
      max-width: 380px;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
      text-align: center;
    }

    .logo {
      width: 48px; height: 48px;
      background: #1a6b35;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.25rem;
      font-size: 1.5rem; color: #fff; font-weight: 900;
    }

    .activity-label {
      font-size: .72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .08em; color: #6b7280; margin-bottom: .3rem;
    }
    .activity-name {
      font-size: 1.05rem; font-weight: 800; color: #1a2e1a;
      margin-bottom: 1.75rem;
    }

    .upload-btn {
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; gap: .5rem;
      width: 100%; padding: 1.75rem 1rem;
      background: #1a6b35; color: #fff;
      border: none; border-radius: 14px;
      font-size: 1rem; font-weight: 700;
      cursor: pointer; transition: background .15s;
      -webkit-tap-highlight-color: transparent;
    }
    .upload-btn:active { background: #155529; }
    .upload-btn .icon { font-size: 2.2rem; line-height: 1; }

    .upload-btn-another {
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; gap: .5rem;
      width: 100%; padding: 1.25rem 1rem;
      background: #f0fdf4; color: #1a6b35;
      border: 2px solid #86efac; border-radius: 14px;
      font-size: .95rem; font-weight: 700;
      cursor: pointer; transition: background .15s;
      margin-top: .75rem;
      -webkit-tap-highlight-color: transparent;
    }
    .upload-btn-another:active { background: #dcfce7; }

    #fileInput { display: none; }

    /* Loading state */
    .loading { display: none; flex-direction: column; align-items: center; gap: 1rem; padding: 1rem 0; }
    .spinner {
      width: 48px; height: 48px;
      border: 4px solid #d1fae5;
      border-top-color: #1a6b35;
      border-radius: 50%;
      animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-text { font-size: .9rem; color: #6b7280; font-weight: 600; }

    /* Success state */
    .success { display: none; }
    .success-icon {
      width: 64px; height: 64px;
      background: #f0fdf4;
      border: 3px solid #86efac;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.8rem;
      margin: 0 auto 1rem;
    }
    .success h2 { font-size: 1.2rem; font-weight: 800; color: #1a6b35; margin-bottom: .35rem; }
    .success-details {
      background: #f9fafb; border-radius: 10px; padding: .85rem 1rem;
      font-size: .85rem; color: #374151; text-align: left;
      margin: 1rem 0;
      border: 1px solid #e5e7eb;
    }
    .success-details .row { display: flex; justify-content: space-between; padding: .2rem 0; }
    .success-details .label { color: #9ca3af; font-size: .78rem; }
    .success-details .value { font-weight: 700; }
    .concerns-flag {
      background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;
      padding: .6rem .85rem; font-size: .8rem; color: #92400e; margin-top: .75rem;
      text-align: left;
    }

    /* Error state */
    .error-card { display: none; }
    .error-icon { font-size: 2.5rem; margin-bottom: .75rem; }
    .error-card h2 { font-size: 1.1rem; font-weight: 800; color: #dc2626; margin-bottom: .5rem; }
    .error-card p  { font-size: .88rem; color: #6b7280; line-height: 1.5; }

    .hint { font-size: .75rem; color: #9ca3af; margin-top: 1.25rem; line-height: 1.5; }
  </style>
</head>
<body>

<div class="card">
  <div class="logo">B</div>

  <?php if (!$req): ?>
    <!-- Invalid token -->
    <div class="error-card" style="display:block;">
      <div class="error-icon">🔒</div>
      <h2>Invalid Link</h2>
      <p>This receipt upload link is not valid. Make sure you scanned the QR code from the final claim form.</p>
    </div>

  <?php else: ?>
    <!-- Upload UI -->
    <div class="activity-label">Uploading receipt for</div>
    <div class="activity-name"><?= htmlspecialchars($req['activity_description']) ?></div>

    <!-- Default: camera button -->
    <div id="uploadState">
      <button class="upload-btn" onclick="document.getElementById('fileInput').click()">
        <span class="icon">📷</span>
        Take Photo or Choose File
      </button>
      <input type="file" id="fileInput" accept="image/*,.pdf" capture="environment"
             onchange="handleFile(this)">
      <p class="hint">Photo goes straight to your desktop claim form — no AirDrop needed.</p>
    </div>

    <!-- Loading -->
    <div class="loading" id="loadingState">
      <div class="spinner"></div>
      <div class="loading-text" id="loadingText">Uploading…</div>
    </div>

    <!-- Success -->
    <div class="success" id="successState">
      <div class="success-icon">✓</div>
      <h2>Receipt uploaded!</h2>
      <p style="font-size:.85rem;color:#6b7280;">It's waiting in your desktop claim form — look for the green notification.</p>
      <div class="success-details" id="scanDetails" style="display:none;">
        <div class="row"><span class="label">Vendor</span><span class="value" id="detailVendor">—</span></div>
        <div class="row"><span class="label">Amount</span><span class="value" id="detailAmt">—</span></div>
        <div class="row"><span class="label">Date</span><span class="value" id="detailDate">—</span></div>
      </div>
      <div class="concerns-flag" id="concernsFlag" style="display:none;">
        ⚠️ <span id="concernsText"></span>
      </div>
    </div>

    <!-- Error -->
    <div class="error-card" id="errorState">
      <div class="error-icon">⚠️</div>
      <h2>Upload failed</h2>
      <p id="errorMsg">Something went wrong. Please try again.</p>
      <button class="upload-btn-another" onclick="resetForAnother()" style="margin-top:1rem;">
        Try Again
      </button>
    </div>

  <?php endif; ?>
</div>

<?php if ($req): ?>
<script>
const TOKEN      = <?= json_encode($token) ?>;
const REQUEST_ID = <?= (int)$req['id'] ?>;

function show(id) {
    var states = ['uploadState','loadingState','successState','errorState'];
    for (var i = 0; i < states.length; i++) {
        var el = document.getElementById(states[i]);
        if (el) el.style.display = (states[i] === id) ? (states[i] === 'loadingState' ? 'flex' : 'block') : 'none';
    }
}

function handleFile(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];

    show('loadingState');
    document.getElementById('loadingText').textContent = 'Uploading…';

    var fd = new FormData();
    fd.append('token', TOKEN);
    fd.append('receipt', file);

    fetch('prod-mobile-scan.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) {
                document.getElementById('errorMsg').textContent = d.error || 'Upload failed.';
                show('errorState');
                return;
            }

            // Show scan details if available
            if (d.vendor || d.amount || d.date) {
                document.getElementById('detailVendor').textContent = d.vendor || '—';
                document.getElementById('detailAmt').textContent    = d.amount ? '$' + parseFloat(d.amount).toFixed(2) : '—';
                document.getElementById('detailDate').textContent   = d.date   || '—';
                document.getElementById('scanDetails').style.display = 'block';
            }

            if (d.concerns) {
                document.getElementById('concernsText').textContent = d.concerns;
                document.getElementById('concernsFlag').style.display = 'block';
            } else {
                document.getElementById('concernsFlag').style.display = 'none';
            }

            show('successState');
            input.value = '';
        })
        .catch(function() {
            document.getElementById('errorMsg').textContent = 'Network error. Check your connection and try again.';
            show('errorState');
        });
}

function resetForAnother() {
    document.getElementById('scanDetails').style.display  = 'none';
    document.getElementById('concernsFlag').style.display = 'none';
    show('uploadState');
}
</script>
<?php endif; ?>
</body>
</html>
