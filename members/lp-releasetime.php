<?php
/**
 * lp-releasetime.php — BCTF Local Support Grant: Release Time.
 *
 * Stage 1: the log that replaces "RELEASE TIME GRANT Who When_26_27.docx".
 * Invoices, attachments and the export package come next; until then the
 * Invoice column reads "not invoiced" for everything.
 *
 * President only — the same gate as the rest of the LP tools.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/lp-releasetime-db.php';

requireLogin();
$member = getMember();
if (!lpCanView($member['email'])) {
    header('Location: dashboard.php');
    exit;
}
lpRtEnsureTables();

$notice = '';
$error  = '';

// Year comes from the URL so past years stay readable. Anything unparseable
// falls back to the current school year rather than showing an empty page.
$year = (int)($_GET['year'] ?? 0);
if ($year < 2000 || $year > 2100) $year = lpCurrentYear();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck();
    $action = $_POST['action'] ?? '';
    $year   = (int)($_POST['year'] ?? $year);
    if ($year < 2000 || $year > 2100) $year = lpCurrentYear();

    if ($action === 'add') {
        $date   = trim($_POST['release_date'] ?? '');
        $name   = trim($_POST['member_name']  ?? '');
        $days   = (float)($_POST['days'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if ($date === '' || $name === '') {
            $error = 'A date and a name are required.';
        } elseif (!rtIsoDate($date)) {
            $error = 'That date could not be read. Use the date picker.';
        } elseif ($days <= 0 || $days > 20) {
            $error = 'Days must be more than 0 and no more than 20.';
        } elseif (lpRtYearOfDate($date) !== $year) {
            // Otherwise a mistyped year is filed here and counted against this
            // year's cap while belonging to another.
            // Plain text: $error is escaped once where it is printed.
            $error = date('M j, Y', strtotime($date)) . ' is in '
                   . lpYearLabel((int)lpRtYearOfDate($date))
                   . ', not ' . lpYearLabel($year)
                   . '. Switch year at the top, or correct the date.';
        } else {
            lpRtAddEntry($year, $date, $name, $days, $reason, $member['email']);
            $notice = 'Release day logged.';
        }
    }

    if ($action === 'update') {
        $id     = (int)($_POST['entry_id'] ?? 0);
        $date   = trim($_POST['release_date'] ?? '');
        $name   = trim($_POST['member_name']  ?? '');
        $days   = (float)($_POST['days'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        $row    = $id ? lpRtEntry($id) : null;
        if (!$row || (int)$row['year'] !== $year) {
            $error = 'That entry could not be found.';
        } elseif ($date === '' || $name === '' || $days <= 0 || $days > 20) {
            $error = 'A date, a name and a sensible number of days are required.';
        } elseif (lpRtYearOfDate($date) !== $year) {
            $error = 'That date is not in ' . lpYearLabel($year)
                   . '. Entries stay in the year they belong to.';
        } else {
            lpRtUpdateEntry($id, $date, $name, $days, $reason);
            $notice = 'Entry updated.';
        }
    }

    if ($action === 'exclude') {
        $id   = (int)($_POST['entry_id'] ?? 0);
        $on   = !empty($_POST['excluded']);
        $why  = trim($_POST['excluded_reason'] ?? '');
        $row  = $id ? lpRtEntry($id) : null;
        if (!$row || (int)$row['year'] !== $year) {
            $error = 'That entry could not be found.';
        } elseif ($on && $why === '') {
            $error = 'Say why the day is not being claimed — a year from now the reason is the useful part.';
        } else {
            lpRtSetExcluded($id, $on, $why);
            $notice = $on ? 'Marked as not claimed.' : 'Back in the claim.';
        }
    }

    if ($action === 'delete') {
        $id  = (int)($_POST['entry_id'] ?? 0);
        $row = $id ? lpRtEntry($id) : null;
        if ($row && (int)$row['year'] === $year) {
            lpRtDeleteEntry($id);
            $notice = 'Entry deleted.';
        } else {
            $error = 'That entry could not be found.';
        }
    }

    if ($action === 'add_invoice' || $action === 'update_invoice') {
        $id     = (int)($_POST['invoice_id'] ?? 0);
        $number = trim($_POST['invoice_number'] ?? '');
        $idate  = trim($_POST['invoice_date'] ?? '');
        $cost   = trim($_POST['total_cost'] ?? '');
        $notes  = trim($_POST['notes'] ?? '');

        // Same test as the entry path: a real ISO date. strtotime alone accepts
        // "12/5" and hands MySQL something it stores as 0000-00-00, and its
        // return of 0 for 1970-01-01 is falsy, so a valid date would fail too.
        if ($idate !== '' && !rtIsoDate($idate)) {
            $error = 'That invoice date could not be read. Use the date picker.';
        } elseif ($cost !== '' && (!is_numeric($cost) || (float)$cost < 0)) {
            $error = 'The cost must be a number, or left blank until you know it.';
        } elseif ($number === '') {
            $error = 'The invoice number is what the BCTF matches against — it is required.';
        } else {
            if ($action === 'add_invoice') {
                $id = lpRtAddInvoice($year, $number, $idate, $cost === '' ? null : $cost,
                                     $notes, $member['email']);
                $notice = 'Invoice ' . $number . ' added.';
            } else {
                $inv = $id ? lpRtInvoice($id) : null;
                if (!$inv || (int)$inv['year'] !== $year) {
                    $error = 'That invoice could not be found.';
                    $id = 0;
                } else {
                    lpRtUpdateInvoice($id, $number, $idate, $cost === '' ? null : $cost, $notes);
                    $notice = 'Invoice updated.';
                }
            }

            // The file is optional on both paths: the invoice number and total
            // are often known before the PDF has been scanned.
            if ($id && !empty($_FILES['invoice_file']['name'])) {
                $up = rtStoreUpload($_FILES['invoice_file']);
                if ($up['error'] !== '') {
                    // Keep the notice: the invoice row exists either way, and
                    // showing only the file error reads as "nothing happened",
                    // which gets the same invoice added a second time.
                    $error = $up['error'] . ' The invoice itself was saved — use Edit to attach the file.';
                } else {
                    $old = lpRtInvoice($id);
                    lpRtSetInvoiceFile($id, $up['path'], $up['name']);
                    if ($old && $old['file_path'] !== '' && $old['file_path'] !== $up['path']) {
                        $f = LP_RT_DIR . basename($old['file_path']);
                        if (is_file($f)) @unlink($f);
                    }
                    $notice = trim($notice . ' File attached.');
                }
            }
        }
    }

    if ($action === 'delete_invoice') {
        $id  = (int)($_POST['invoice_id'] ?? 0);
        $inv = $id ? lpRtInvoice($id) : null;
        if ($inv && (int)$inv['year'] === $year) {
            lpRtDeleteInvoice($id);
            $notice = 'Invoice removed. Its release days are back in the log, unattached.';
        } else {
            $error = 'That invoice could not be found.';
        }
    }

    if ($action === 'attach') {
        $id  = (int)($_POST['invoice_id'] ?? 0);
        $inv = $id ? lpRtInvoice($id) : null;
        if (!$inv || (int)$inv['year'] !== $year) {
            $error = 'That invoice could not be found.';
        } else {
            $want = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['entry_ids'] ?? [])))));
            $got  = lpRtAttachEntries($id, $year, $want);
            $label = $inv['invoice_number'] !== '' ? $inv['invoice_number'] : ('#' . $id);
            $notice = 'Days on invoice ' . $label . ' updated.';
            if ($got < count($want)) {
                // Only possible from a stale page, and silence would leave the
                // invoice quietly short of the days it was meant to carry.
                $error  = (count($want) - $got) . ' of those days are now on another invoice and '
                        . 'were left there. Reload and check this invoice.';
                $notice = '';
            }
        }
    }

    if ($action === 'save_year') {
        $fte    = trim($_POST['fte'] ?? '');
        $capRaw = trim($_POST['day_cap'] ?? '');
        $yr     = lpRtYear($year);
        // Blank or silly keeps the stored cap; lpRtSaveYear enforces the range.
        $cap    = $capRaw === '' ? (int)$yr['day_cap'] : (int)$capRaw;
        lpRtSaveYear($year, $fte === '' ? null : $fte, $cap, (string)($yr['report'] ?? ''));
        $saved  = lpRtYear($year);
        $notice = ((int)$saved['day_cap'] === $cap)
                ? 'Year settings saved.'
                : 'Saved, but the day cap must be between 1 and 200 — kept ' . (int)$saved['day_cap'] . '.';
    }

    header('Location: lp-releasetime.php?year=' . $year
           . ($notice ? '&notice=' . urlencode($notice) : ($error ? '&error=' . urlencode($error) : '')));
    exit;
}

$notice = $notice ?: (string)($_GET['notice'] ?? '');
$error  = $error  ?: (string)($_GET['error']  ?? '');

$yr      = lpRtYear($year);
$entries  = lpRtEntries($year);
$invoices = lpRtInvoices($year);
$totals   = lpRtTotals($year);
$cap     = (int)$yr['day_cap'];
$suggest = $yr['fte'] !== null ? lpRtCapForFte((float)$yr['fte']) : 0;

// Every year with entries or settings, plus the one being viewed and the
// current one, so a year can never become unreachable.
$years = lpRtYears();
if (!in_array($year, $years, true))            $years[] = $year;
if (!in_array(lpCurrentYear(), $years, true))  $years[] = lpCurrentYear();
rsort($years);

/**
 * Store an uploaded invoice. Returns ['path','name','error'].
 *
 * Same file rules as the receipt uploads: images or PDF, 15 MB. The type is
 * read from the file rather than trusted from the name, with an extension
 * fallback because iPhone HEIC often arrives as octet-stream.
 */
function rtStoreUpload(array $file): array {
    $out = ['path' => '', 'name' => '', 'error' => ''];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $out['error'] = ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'That file is too large for the server to accept.'
            : 'The file did not upload. Try again.';
        return $out;
    }
    if (!is_uploaded_file($file['tmp_name'])) { $out['error'] = 'Unexpected upload.'; return $out; }
    if ($file['size'] > 15 * 1024 * 1024)     { $out['error'] = 'File too large. Maximum 15 MB.'; return $out; }

    $origName = basename($file['name']);
    $mime     = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : '';
    $byMime   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                 'image/gif' => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heif',
                 'application/pdf' => 'pdf'];
    $okExt    = ['jpg','jpeg','png','webp','gif','heic','heif','pdf'];
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    /*
     * The extension that gets SAVED is chosen here, never carried over from the
     * name the browser sent. A real JPEG called "shell.php" passes a check on
     * content alone, and writing it back as .php would drop an executable file
     * into the web root with only an .htaccess between it and being run — and
     * .htaccess is inert under nginx or with AllowOverride off.
     *
     * Content decides when it can. Extension is the fallback only for the case
     * that made a fallback necessary: iPhone HEIC often arrives as
     * application/octet-stream.
     */
    if (isset($byMime[$mime])) {
        $ext = $byMime[$mime];
    } elseif (!in_array($ext, $okExt, true)) {
        $out['error'] = 'Unsupported file type. Upload a PDF or a photo.';
        return $out;
    }

    lpEnsureReceiptsDir();   // invoices share the receipts directory and its deny rule
    $saved = 'rt-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], LP_RT_DIR . $saved)) {
        $out['error'] = 'Could not save the file. Check server write permissions.';
        return $out;
    }
    $out['path'] = $saved;
    $out['name'] = $origName;
    return $out;
}

/** A real calendar date in YYYY-MM-DD — not merely something strtotime parses. */
function rtIsoDate(string $d): bool {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return false;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

function rtDate(string $d): string { return $d ? date('M j, Y', strtotime($d)) : ''; }
function rtMoney($v): string { return $v === null ? '' : '$' . number_format((float)$v, 2); }
function rtDays(float $d): string  { return rtrim(rtrim(number_format($d, 1), '0'), '.'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Release Time Grant — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 980px; margin: 0 auto; padding: 2rem 1.5rem 5rem; }
    .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem; }
    .page-header h1 { font-size:1.25rem; font-weight:800; color:var(--gray-800); margin:.3rem 0 0; }
    .back-link { font-size:.85rem; color:var(--primary); text-decoration:none; }
    .back-link:hover { text-decoration:underline; }
    .notice    { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:.75rem 1rem; font-size:.88rem; color:#166534; margin-bottom:1.25rem; }
    .error-box { background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:.75rem 1rem; font-size:.88rem; color:#991b1b; margin-bottom:1.25rem; }
    .pcard { background:#fff; border:1px solid var(--gray-200); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; }
    .pcard h2 { font-size:.75rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:var(--gray-500); margin:0 0 1.1rem; }

    .stat-row { display:flex; gap:.75rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .stat { background:#fff; border:1px solid var(--gray-200); border-radius:10px; padding:.8rem 1rem; flex:1; min-width:120px; }
    .stat .n { font-size:1.6rem; font-weight:800; color:var(--gray-800); line-height:1; }
    .stat .l { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--gray-400); margin-top:.3rem; }
    .stat .s { font-size:.72rem; color:var(--gray-500); margin-top:.25rem; }
    .stat.warn { border-color:#fcd34d; background:#fffbeb; }

    .f-row { display:grid; grid-template-columns:150px 1fr 90px 1fr auto; gap:.6rem; align-items:end; }
    @media(max-width:820px){ .f-row { grid-template-columns:1fr 1fr; } }
    .f label { display:block; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--gray-500); margin-bottom:.25rem; }
    .f input, .f select, .f textarea { width:100%; border:1px solid var(--gray-300); border-radius:7px; padding:.5rem .7rem; font-size:.9rem; font-family:inherit; box-sizing:border-box; }
    .f input:focus, .f select:focus, .f textarea:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(26,107,53,.1); }
    .hintline { font-size:.74rem; color:var(--gray-400); margin-top:.4rem; }

    table { width:100%; border-collapse:collapse; font-size:.85rem; }
    .table-wrap { background:#fff; border:1px solid var(--gray-200); border-radius:12px; overflow:hidden; }
    thead tr { background:#1a2e1a; }
    th { padding:.6rem .8rem; text-align:left; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#fff; white-space:nowrap; }
    td { padding:.6rem .8rem; border-bottom:1px solid var(--gray-100); color:var(--gray-700); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr.excluded td { opacity:.55; }
    .num { text-align:right; font-weight:700; white-space:nowrap; }
    .why { font-size:.78rem; color:var(--gray-500); }
    .pill { display:inline-block; font-size:.68rem; font-weight:700; border-radius:100px; padding:.1rem .5rem; white-space:nowrap; }
    .pill.none { background:var(--gray-100); color:var(--gray-500); }
    .pill.no   { background:#fee2e2; color:#991b1b; }
    .acts { display:flex; gap:.35rem; flex-wrap:wrap; }
    .act-btn { background:none; border:1px solid var(--gray-200); border-radius:6px; padding:.25rem .55rem; font-size:.75rem; cursor:pointer; color:var(--gray-600); white-space:nowrap; }
    .act-btn:hover { background:var(--accent); border-color:var(--primary); color:var(--primary); }
    .act-btn.danger:hover { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
    .edit-row { display:none; background:#f8fafc; }
    .edit-row.open { display:table-row; }
    .empty-row td { text-align:center; color:var(--gray-400); padding:2.5rem; }
    .stage-note { font-size:.78rem; color:var(--gray-500); background:var(--gray-50,#f9fafb); border:1px solid var(--gray-200); border-radius:8px; padding:.7rem .9rem; margin-bottom:1.5rem; line-height:1.55; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <div>
      <a class="back-link" href="lp-dashboard.php">&#x2190; Expenses &amp; Grants</a>
      <h1>Release Time Grant</h1>
    </div>
    <form method="GET" class="f" style="display:flex;gap:.5rem;align-items:center;">
      <label for="year" style="margin:0;">School year</label>
      <select id="year" name="year" onchange="this.form.submit()">
        <?php foreach ($years as $y): ?>
        <option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= htmlspecialchars(lpYearLabel($y)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if ($notice): ?><div class="notice">&#x2713; <?= htmlspecialchars($notice) ?></div><?php endif; ?>
  <?php if ($error):  ?><div class="error-box">&#x26A0; <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="stage-note">
    Log each release day as it happens. When the district invoice arrives, add it below, attach the
    PDF, and tick the days it covers — the BCTF reimbursement table is then built from that, one row
    per invoice with dates and names grouped and totals added up. The export package is the next
    stage; until then the table below is the thing you copy from.
  </div>

  <div class="stat-row">
    <div class="stat">
      <div class="n"><?= rtDays($totals['claimable']) ?></div>
      <div class="l">Days to claim</div>
      <div class="s">of <?= $cap ?> allowed</div>
    </div>
    <div class="stat">
      <div class="n"><?= rtDays($totals['invoiced']) ?></div>
      <div class="l">On an invoice</div>
    </div>
    <div class="stat<?= $totals['unbilled'] > 0 ? ' warn' : '' ?>">
      <div class="n"><?= rtDays($totals['unbilled']) ?></div>
      <div class="l">Not yet invoiced</div>
      <div class="s"><?= $totals['unbilled'] > 0 ? 'Still owed to you' : 'Nothing outstanding' ?></div>
    </div>
    <div class="stat">
      <div class="n"><?= rtDays($totals['logged'] - $totals['claimable']) ?></div>
      <div class="l">Not claimed</div>
      <div class="s">Set aside on purpose</div>
    </div>
    <div class="stat<?= $totals['cost_missing'] > 0 ? ' warn' : '' ?>">
      <div class="n" style="font-size:1.25rem;"><?= htmlspecialchars(rtMoney($totals['cost'])) ?></div>
      <div class="l">Invoiced cost</div>
      <div class="s">
        <?php if ($totals['cost_missing'] > 0): ?>
          <?= (int)$totals['cost_missing'] ?> invoice<?= $totals['cost_missing'] === 1 ? '' : 's' ?> with no cost yet
        <?php else: ?>
          All invoices costed
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Log a release day -->
  <div class="pcard">
    <h2>Log a release day</h2>
    <form method="POST" class="f">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="year"   value="<?= $year ?>">
      <div class="f-row">
        <div><label for="d">Date</label><input type="date" id="d" name="release_date" required></div>
        <div><label for="n">Member released</label><input type="text" id="n" name="member_name" required placeholder="e.g. Amanda Forstbaurer-Bourie"></div>
        <div><label for="dy">Days</label><input type="number" id="dy" name="days" value="1" step="0.5" min="0.5" max="20" required></div>
        <div><label for="r">Reason</label><input type="text" id="r" name="reason" placeholder="e.g. Treasurer transition"></div>
        <div><button type="submit" class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;">Add</button></div>
      </div>
      <div class="hintline">
        One row per person per day — if three people were released on the same day, that is three rows.
        Half days are fine.
      </div>
    </form>
  </div>

  <!-- The log -->
  <div class="table-wrap" style="margin-bottom:1.5rem;">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Member</th>
          <th style="text-align:right;">Days</th>
          <th>Reason</th>
          <th>Invoice</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$entries): ?>
        <tr class="empty-row"><td colspan="6">No release days logged for <?= htmlspecialchars(lpYearLabel($year)) ?> yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($entries as $e): $eid = (int)$e['id']; ?>
        <tr class="<?= (int)$e['excluded'] ? 'excluded' : '' ?>">
          <td style="white-space:nowrap;"><?= htmlspecialchars(rtDate($e['release_date'])) ?></td>
          <td><?= htmlspecialchars($e['member_name']) ?></td>
          <td class="num"><?= rtDays((float)$e['days']) ?></td>
          <td>
            <?= htmlspecialchars($e['reason']) ?>
            <?php if ((int)$e['excluded']): ?>
              <div class="why">Not claimed — <?= htmlspecialchars($e['excluded_reason']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($e['invoice_id']): ?>
              <?php $inum = (string)($e['invoice_number'] ?? ''); ?>
              <?= htmlspecialchars($inum !== '' ? $inum : '#' . (int)$e['invoice_id']) ?>
            <?php elseif ((int)$e['excluded']): ?>
              <span class="pill no">not claimed</span>
            <?php else: ?>
              <span class="pill none">not invoiced</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="acts">
              <button class="act-btn" onclick="tog(<?= $eid ?>)">&#x270F; Edit</button>
              <?php if ((int)$e['excluded']): ?>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action"   value="exclude">
                <input type="hidden" name="year"     value="<?= $year ?>">
                <input type="hidden" name="entry_id" value="<?= $eid ?>">
                <input type="hidden" name="excluded" value="">
                <button type="submit" class="act-btn">&#x21BA; Claim it</button>
              </form>
              <?php else: ?>
              <button class="act-btn" onclick="tog('x<?= $eid ?>')">&#8856; Don't claim</button>
              <?php endif; ?>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Delete this release day? This cannot be undone.')">
                <?= csrfField() ?>
                <input type="hidden" name="action"   value="delete">
                <input type="hidden" name="year"     value="<?= $year ?>">
                <input type="hidden" name="entry_id" value="<?= $eid ?>">
                <button type="submit" class="act-btn danger">&#128465;</button>
              </form>
            </div>
          </td>
        </tr>
        <tr class="edit-row" id="row-<?= $eid ?>">
          <td colspan="6">
            <form method="POST" class="f">
              <?= csrfField() ?>
              <input type="hidden" name="action"   value="update">
              <input type="hidden" name="year"     value="<?= $year ?>">
              <input type="hidden" name="entry_id" value="<?= $eid ?>">
              <div class="f-row">
                <div><label>Date</label><input type="date" name="release_date" value="<?= htmlspecialchars($e['release_date']) ?>" required></div>
                <div><label>Member released</label><input type="text" name="member_name" value="<?= htmlspecialchars($e['member_name']) ?>" required></div>
                <div><label>Days</label><input type="number" name="days" value="<?= htmlspecialchars(rtDays((float)$e['days'])) ?>" step="0.5" min="0.5" max="20" required></div>
                <div><label>Reason</label><input type="text" name="reason" value="<?= htmlspecialchars($e['reason']) ?>"></div>
                <div style="display:flex;gap:.35rem;">
                  <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem;font-size:.85rem;">Save</button>
                  <button type="button" class="act-btn" onclick="tog(<?= $eid ?>)">Cancel</button>
                </div>
              </div>
            </form>
          </td>
        </tr>
        <tr class="edit-row" id="row-x<?= $eid ?>">
          <td colspan="6">
            <form method="POST" class="f">
              <?= csrfField() ?>
              <input type="hidden" name="action"   value="exclude">
              <input type="hidden" name="year"     value="<?= $year ?>">
              <input type="hidden" name="entry_id" value="<?= $eid ?>">
              <input type="hidden" name="excluded" value="1">
              <label>Why is this day not being claimed?</label>
              <div style="display:flex;gap:.5rem;align-items:center;">
                <input type="text" name="excluded_reason" required style="flex:1;"
                       placeholder="e.g. covered by the Collaboration Grant / ongoing released officer time / support staff">
                <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem;font-size:.85rem;">Save</button>
                <button type="button" class="act-btn" onclick="tog('x<?= $eid ?>')">Cancel</button>
              </div>
              <div class="hintline">
                The grant does not cover contractor or office-staff salaries, the ongoing cost of a
                regularly released officer, or release another BCTF grant already pays for. The day
                stays in the log either way — this records that you considered it.
              </div>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Invoices -->
  <div class="pcard">
    <h2>Add a district invoice</h2>
    <form method="POST" class="f" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add_invoice">
      <input type="hidden" name="year"   value="<?= $year ?>">
      <div style="display:grid;grid-template-columns:140px 150px 140px 1fr auto;gap:.6rem;align-items:end;">
        <div><label for="inum">Invoice number</label><input type="text" id="inum" name="invoice_number" required placeholder="e.g. 556"></div>
        <div><label for="idate">Invoice date</label><input type="date" id="idate" name="invoice_date"></div>
        <div><label for="icost">Total cost</label><input type="number" id="icost" name="total_cost" step="0.01" min="0" placeholder="leave blank"></div>
        <div><label for="ifile">Invoice PDF or photo</label><input type="file" id="ifile" name="invoice_file" accept=".pdf,image/*"></div>
        <div><button type="submit" class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;">Add</button></div>
      </div>
      <div class="hintline">
        Leave the cost blank until the district tells you — blank reads as
        <em>not known yet</em>, where 0 would read as free.
      </div>
    </form>
  </div>

  <div class="table-wrap" style="margin-bottom:1.5rem;">
    <table>
      <thead>
        <tr>
          <th>Invoice</th>
          <th>Dates released</th>
          <th>Members</th>
          <th style="text-align:right;">Days</th>
          <th style="text-align:right;">Cost</th>
          <th>File</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$invoices): ?>
        <tr class="empty-row"><td colspan="7">No invoices yet. Add one above when the district bills you.</td></tr>
        <?php endif; ?>
        <?php foreach ($invoices as $inv): $iid = (int)$inv['id']; ?>
        <tr>
          <td style="white-space:nowrap;">
            <strong><?= htmlspecialchars($inv['invoice_number'] !== '' ? $inv['invoice_number'] : '#' . $iid) ?></strong>
            <?php if ($inv['invoice_date']): ?>
              <div class="why"><?= htmlspecialchars(rtDate($inv['invoice_date'])) ?></div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($inv['dates_text'] !== '' ? $inv['dates_text'] : '—') ?></td>
          <td><?= htmlspecialchars($inv['names_text'] !== '' ? $inv['names_text'] : '—') ?></td>
          <td class="num"><?= rtDays((float)$inv['days']) ?></td>
          <td class="num">
            <?php if ($inv['total_cost'] === null): ?>
              <span class="pill none">not known</span>
            <?php else: ?>
              <?= htmlspecialchars(rtMoney($inv['total_cost'])) ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($inv['file_path'] !== ''): ?>
              <a href="lp-releasetime-file.php?f=<?= urlencode($inv['file_path']) ?>" target="_blank"
                 style="font-size:.78rem;color:var(--primary);">View</a>
            <?php else: ?>
              <span class="pill none">none</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="acts">
              <button class="act-btn" onclick="tog('a<?= $iid ?>')">&#9745; Days (<?= count($inv['entries']) ?>)</button>
              <button class="act-btn" onclick="tog('i<?= $iid ?>')">&#x270F; Edit</button>
              <form method="POST" style="display:inline;"
                    onsubmit="return confirm('Remove this invoice? Its release days stay in the log and go back to not invoiced.')">
                <?= csrfField() ?>
                <input type="hidden" name="action"     value="delete_invoice">
                <input type="hidden" name="year"       value="<?= $year ?>">
                <input type="hidden" name="invoice_id" value="<?= $iid ?>">
                <button type="submit" class="act-btn danger">&#128465;</button>
              </form>
            </div>
          </td>
        </tr>

        <!-- attach days -->
        <tr class="edit-row" id="row-a<?= $iid ?>">
          <td colspan="7">
            <form method="POST" class="f">
              <?= csrfField() ?>
              <input type="hidden" name="action"     value="attach">
              <input type="hidden" name="year"       value="<?= $year ?>">
              <input type="hidden" name="invoice_id" value="<?= $iid ?>">
              <label>Which release days does this invoice cover?</label>
              <?php
                // Days already on this invoice, plus every day not on any other —
                // a day can only belong to one invoice, so the rest are not offered.
                $choices = array_filter($entries, function ($e) use ($iid) {
                    return (int)$e['invoice_id'] === $iid || $e['invoice_id'] === null;
                });
              ?>
              <?php if (!$choices): ?>
                <p class="hintline">Every logged day is already on another invoice.</p>
              <?php else: ?>
              <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:.35rem;margin:.5rem 0 .75rem;">
                <?php foreach ($choices as $e): ?>
                <label style="display:flex;gap:.5rem;align-items:flex-start;font-size:.84rem;
                              border:1px solid var(--gray-200);border-radius:7px;padding:.45rem .6rem;">
                  <input type="checkbox" name="entry_ids[]" value="<?= (int)$e['id'] ?>"
                         <?= (int)$e['invoice_id'] === $iid ? 'checked' : '' ?> style="margin-top:.15rem;">
                  <span>
                    <?= htmlspecialchars(rtDate($e['release_date'])) ?> &middot;
                    <?= htmlspecialchars($e['member_name']) ?>
                    &middot; <?= rtDays((float)$e['days']) ?>d
                    <?php if ((int)$e['excluded']): ?>
                      <span class="pill no" style="margin-left:.2rem;">not claimed</span>
                    <?php endif; ?>
                  </span>
                </label>
                <?php endforeach; ?>
              </div>
              <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem;font-size:.85rem;">Save days</button>
              <button type="button" class="act-btn" onclick="tog('a<?= $iid ?>')">Cancel</button>
              <div class="hintline">
                Unticking is as real as ticking — a day removed here goes back to
                <em>not invoiced</em> rather than staying attached. A day marked not claimed can sit
                on the invoice without being billed to the BCTF.
              </div>
              <?php endif; ?>
            </form>
          </td>
        </tr>

        <!-- edit invoice -->
        <tr class="edit-row" id="row-i<?= $iid ?>">
          <td colspan="7">
            <form method="POST" class="f" enctype="multipart/form-data">
              <?= csrfField() ?>
              <input type="hidden" name="action"     value="update_invoice">
              <input type="hidden" name="year"       value="<?= $year ?>">
              <input type="hidden" name="invoice_id" value="<?= $iid ?>">
              <div style="display:grid;grid-template-columns:140px 150px 140px 1fr auto;gap:.6rem;align-items:end;">
                <div><label>Invoice number</label><input type="text" name="invoice_number" value="<?= htmlspecialchars($inv['invoice_number']) ?>" required></div>
                <div><label>Invoice date</label><input type="date" name="invoice_date" value="<?= htmlspecialchars((string)$inv['invoice_date']) ?>"></div>
                <div><label>Total cost</label><input type="number" name="total_cost" step="0.01" min="0" value="<?= $inv['total_cost'] === null ? '' : htmlspecialchars($inv['total_cost']) ?>"></div>
                <div>
                  <label><?= $inv['file_path'] !== '' ? 'Replace file' : 'Attach file' ?></label>
                  <input type="file" name="invoice_file" accept=".pdf,image/*">
                </div>
                <div style="display:flex;gap:.35rem;">
                  <button type="submit" class="btn btn-primary" style="padding:.45rem .9rem;font-size:.85rem;">Save</button>
                  <button type="button" class="act-btn" onclick="tog('i<?= $iid ?>')">Cancel</button>
                </div>
              </div>
              <div style="margin-top:.6rem;">
                <label>Notes</label>
                <input type="text" name="notes" value="<?= htmlspecialchars((string)$inv['notes']) ?>" placeholder="optional">
              </div>
              <?php if ($inv['file_path'] !== ''): ?>
                <div class="hintline">
                  Attached: <?= htmlspecialchars($inv['original_name'] ?: $inv['file_path']) ?>.
                  Uploading another replaces it.
                </div>
              <?php endif; ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Year settings -->
  <div class="pcard">
    <h2>Year settings</h2>
    <form method="POST" class="f">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="save_year">
      <input type="hidden" name="year"   value="<?= $year ?>">
      <div style="display:grid;grid-template-columns:200px 200px auto;gap:.8rem;align-items:end;">
        <div>
          <label for="fte">FTE as of September 30</label>
          <input type="number" id="fte" name="fte" step="0.01" min="0"
                 value="<?= $yr['fte'] !== null ? htmlspecialchars(rtrim(rtrim($yr['fte'], '0'), '.')) : '' ?>">
        </div>
        <div>
          <label for="cap">Days allowed this year</label>
          <input type="number" id="cap" name="day_cap" min="0" max="200" value="<?= $cap ?>">
        </div>
        <div><button type="submit" class="btn btn-primary" style="padding:.5rem 1.1rem;font-size:.9rem;">Save</button></div>
      </div>
      <div class="hintline">
        The BCTF sets the cap by local FTE — up to 175 FTE is 40 days, 176–510 is 60, and so on.
        <?php if ($suggest && $suggest !== $cap): ?>
          At <?= htmlspecialchars(rtrim(rtrim($yr['fte'], '0'), '.')) ?> FTE that table gives
          <strong><?= $suggest ?> days</strong>, which is not what is saved here — worth a second look.
        <?php elseif ($suggest): ?>
          At <?= htmlspecialchars(rtrim(rtrim($yr['fte'], '0'), '.')) ?> FTE that is <?= $suggest ?> days, which matches.
        <?php endif; ?>
        The number is editable because a BCTF ruling, not this page, has the last word.
      </div>
    </form>
  </div>

</div>
<script>
function tog(id){
  var r = document.getElementById('row-' + id);
  if (!r) return;
  var open = r.classList.toggle('open');
  if (open) r.scrollIntoView({ behavior:'smooth', block:'nearest' });
}
</script>
</body>
</html>
