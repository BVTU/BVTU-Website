<?php
/**
 * mileage.php — BVTU EC Mileage Tracker (members only)
 *
 * Handles:
 *   GET  /mileage.php               — blank submission form
 *   POST /mileage.php action=submit — process new claim
 *   POST /mileage.php action=lookup — view personal history by name
 */

require_once __DIR__ . '/members/auth.php';
requireLogin();

require_once __DIR__ . '/members/config.php';
require_once __DIR__ . '/members/db.php';

date_default_timezone_set('America/Vancouver');

define('MILEAGE_RATE',  0.70);   // $/km — change here if rate changes
define('NOTIFY_EMAIL',  'lp54@bctf.ca');
define('NOTIFY_NAME',   'Cody Lind');
define('SCHOOL_YEAR_START', '09-01'); // Sep 1 — used for YTD calculation

// ── Ensure table exists ───────────────────────────────────────────────────────
function ensureTable(): void {
    getDB()->exec("CREATE TABLE IF NOT EXISTS mileage_claims (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(120) NOT NULL,
        date_traveled DATE        NOT NULL,
        event        VARCHAR(300) NOT NULL,
        kilometers   DECIMAL(8,1) NOT NULL,
        rate         DECIMAL(4,2) NOT NULL DEFAULT 0.70,
        amount       DECIMAL(10,2) NOT NULL,
        submitted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_date (date_traveled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function schoolYearStart(): string {
    // Returns the start of the current school year (Sep 1)
    $sep = date('Y') . '-' . SCHOOL_YEAR_START;
    return (date('Y-m-d') >= $sep) ? $sep : (date('Y') - 1) . '-' . SCHOOL_YEAR_START;
}

function ytdTotal(string $name): float {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM mileage_claims
         WHERE name = ? AND date_traveled >= ?"
    );
    $stmt->execute([$name, schoolYearStart()]);
    return (float)$stmt->fetchColumn();
}

function ytdKm(string $name): float {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(kilometers),0) FROM mileage_claims
         WHERE name = ? AND date_traveled >= ?"
    );
    $stmt->execute([$name, schoolYearStart()]);
    return (float)$stmt->fetchColumn();
}

function personClaims(string $name): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT * FROM mileage_claims
         WHERE name = ?
         ORDER BY date_traveled DESC, submitted_at DESC
         LIMIT 100"
    );
    $stmt->execute([$name]);
    return $stmt->fetchAll();
}

function sendNotification(array $claim): void {
    $d   = date('M j, Y', strtotime($claim['date_traveled']));
    $amt = number_format($claim['amount'], 2);
    $km  = number_format($claim['kilometers'], 1);

    $subject = "New Mileage Claim — {$claim['name']} ({$d})";
    $body    = "A new mileage claim has been submitted.\n\n"
             . "Name:       {$claim['name']}\n"
             . "Date:       {$d}\n"
             . "Event:      {$claim['event']}\n"
             . "Kilometres: {$km} km\n"
             . "Rate:       \$" . number_format($claim['rate'], 2) . "/km\n"
             . "Amount:     \${$amt}\n\n"
             . "View all claims and export CSV:\n"
             . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . "/members/mileage-admin.php\n";

    mail(NOTIFY_EMAIL, $subject, $body,
         "From: noreply@bvtu.ca\r\nReply-To: noreply@bvtu.ca\r\nContent-Type: text/plain; charset=UTF-8\r\n");
}

// ── Process requests ──────────────────────────────────────────────────────────
ensureTable();

$action   = $_POST['action'] ?? '';
$errors   = [];
$success  = null;  // holds the submitted claim row
$lookupName   = '';
$lookupClaims = [];
$lookupDone   = false;

// ── SUBMIT ────────────────────────────────────────────────────────────────────
if ($action === 'submit') {
    $name  = trim($_POST['name']   ?? '');
    $date  = trim($_POST['date']   ?? '');
    $event = trim($_POST['event']  ?? '');
    $km    = trim($_POST['km']     ?? '');

    if (!$name)                    $errors['name']  = 'Please enter your name.';
    if (!$date)                    $errors['date']  = 'Please enter the date of travel.';
    elseif ($date > date('Y-m-d')) $errors['date']  = 'Date cannot be in the future.';
    if (!$event)                   $errors['event'] = 'Please describe the event or purpose.';
    if (!is_numeric($km) || (float)$km <= 0)
                                   $errors['km']    = 'Please enter a valid number of kilometres (greater than 0).';

    if (!$errors) {
        $km     = round((float)$km, 1);
        $amount = round($km * MILEAGE_RATE, 2);

        $stmt = getDB()->prepare(
            "INSERT INTO mileage_claims (name, date_traveled, event, kilometers, rate, amount)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $date, $event, $km, MILEAGE_RATE, $amount]);

        $success = [
            'name'          => $name,
            'date_traveled' => $date,
            'event'         => $event,
            'kilometers'    => $km,
            'rate'          => MILEAGE_RATE,
            'amount'        => $amount,
        ];

        sendNotification($success);

        // Auto-load their history after submit
        $lookupName   = $name;
        $lookupClaims = personClaims($name);
        $lookupDone   = true;
    }
}

// ── LOOKUP ────────────────────────────────────────────────────────────────────
if ($action === 'lookup') {
    $lookupName = trim($_POST['lookup_name'] ?? '');
    if ($lookupName) {
        $lookupClaims = personClaims($lookupName);
        $lookupDone   = true;
    }
}

// ── DELETE (member self-service) ──────────────────────────────────────────────
$deleteSuccess = false;
$deleteError   = null;
if ($action === 'delete') {
    $delId   = (int)($_POST['del_id']   ?? 0);
    $delName = trim($_POST['del_name']  ?? '');

    $ownerCheck = getDB()->prepare("SELECT name FROM mileage_claims WHERE id = ?");
    $ownerCheck->execute([$delId]);
    $ownerRow = $ownerCheck->fetch();

    if ($delId <= 0 || !$ownerRow) {
        $deleteError = 'Claim not found.';
    } elseif (strtolower(trim($ownerRow['name'])) !== strtolower($delName)) {
        $deleteError = 'You can only delete your own claims.';
    } else {
        getDB()->prepare("DELETE FROM mileage_claims WHERE id = ?")->execute([$delId]);
        $deleteSuccess = true;

        // Notify president
        $body = "A mileage claim has been deleted by the member.\n\n"
              . "Claim ID: #{$delId}\n"
              . "Name:     {$delName}\n\n"
              . "View all claims:\n" . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . "/members/mileage-admin.php\n";
        mail(NOTIFY_EMAIL, "Mileage Claim Deleted — {$delName} (#{$delId})", $body,
             "From: noreply@bvtu.ca\r\nReply-To: noreply@bvtu.ca\r\nContent-Type: text/plain; charset=UTF-8\r\n");

        $lookupName   = $delName;
        $lookupClaims = personClaims($lookupName);
        $lookupDone   = true;
    }
}

// ── EDIT ──────────────────────────────────────────────────────────────────────
$editSuccess = false;
$editError   = null;
if ($action === 'edit') {
    $editId      = (int)($_POST['edit_id']       ?? 0);
    $editName    = trim($_POST['edit_name']       ?? '');
    $editDate    = trim($_POST['edit_date']       ?? '');
    $editEvent   = trim($_POST['edit_event']      ?? '');
    $editKm      = (float)($_POST['edit_km']      ?? 0);
    $editAmount  = round($editKm * MILEAGE_RATE,  2);

    // Verify this claim actually belongs to the stated name (security check)
    $ownerCheck = getDB()->prepare("SELECT name FROM mileage_claims WHERE id = ?");
    $ownerCheck->execute([$editId]);
    $ownerRow = $ownerCheck->fetch();

    if ($editId <= 0 || !$ownerRow) {
        $editError = 'Claim not found.';
    } elseif (strtolower(trim($ownerRow['name'])) !== strtolower($editName)) {
        $editError = 'You can only edit your own claims.';
    } elseif (!$editDate || $editDate > date('Y-m-d')) {
        $editError = 'Please enter a valid date (not in the future).';
    } elseif (!$editEvent) {
        $editError = 'Event / purpose is required.';
    } elseif ($editKm <= 0) {
        $editError = 'Kilometres must be greater than zero.';
    } else {
        getDB()->prepare("
            UPDATE mileage_claims
            SET date_traveled=?, event=?, kilometers=?, rate=?, amount=?
            WHERE id=?
        ")->execute([$editDate, $editEvent, $editKm, MILEAGE_RATE, $editAmount, $editId]);
        $editSuccess = true;

        // Notify president of the correction
        $d    = date('M j, Y', strtotime($editDate));
        $body = "A mileage claim has been edited by the member.\n\n"
              . "Claim ID:   #{$editId}\n"
              . "Name:       {$editName}\n"
              . "Date:       {$d}\n"
              . "Event:      {$editEvent}\n"
              . "Kilometres: " . number_format($editKm, 1) . " km\n"
              . "Amount:     \$" . number_format($editAmount, 2) . "\n\n"
              . "View all claims:\n" . (defined('SITE_URL') ? SITE_URL : 'https://bvtu.ca') . "/members/mileage-admin.php\n";
        mail(NOTIFY_EMAIL, "Mileage Claim Edited — {$editName} (#{$editId})", $body,
             "From: noreply@bvtu.ca\r\nReply-To: noreply@bvtu.ca\r\nContent-Type: text/plain; charset=UTF-8\r\n");

        // Reload their claims so the table refreshes
        $lookupName   = $editName;
        $lookupClaims = personClaims($lookupName);
        $lookupDone   = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Mileage Claims — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Submit your BVTU EC mileage claim for reimbursement.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .mileage-layout {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
      align-items: start;
    }
    @media (max-width: 820px) {
      .mileage-layout { grid-template-columns: 1fr; }
    }

    .mileage-card {
      background: #fff;
      border: 1px solid var(--gray-200);
      border-radius: 12px;
      overflow: hidden;
    }
    .mileage-card-header {
      background: var(--primary);
      color: #fff;
      padding: 1rem 1.4rem;
      font-size: .95rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: .6rem;
    }
    .mileage-card-header svg { width: 18px; height: 18px; opacity: .85; }
    .mileage-card-body { padding: 1.5rem 1.4rem; }

    /* Form fields */
    .field { margin-bottom: 1.1rem; }
    .field label {
      display: block;
      font-size: .82rem;
      font-weight: 700;
      color: var(--gray-600);
      margin-bottom: .35rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .field input, .field textarea {
      width: 100%;
      border: 1px solid var(--gray-300);
      border-radius: 7px;
      padding: .65rem .85rem;
      font-size: .93rem;
      font-family: inherit;
      color: var(--gray-800);
      background: #fff;
      transition: border-color .2s;
      box-sizing: border-box;
    }
    .field input:focus, .field textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(26,107,53,.1);
    }
    .field input.field-error { border-color: #dc2626; }
    .field-error-msg { font-size: .78rem; color: #dc2626; margin-top: .3rem; }
    .field input[readonly] {
      background: #f8f9fa;
      color: var(--gray-500);
      cursor: not-allowed;
    }
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: .85rem; }

    /* Rate / total display */
    .calc-row {
      background: #f0f7f2;
      border: 1px solid #b8ddc5;
      border-radius: 8px;
      padding: .9rem 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.1rem;
    }
    .calc-row .calc-label { font-size: .83rem; color: var(--gray-500); }
    .calc-row .calc-total { font-size: 1.3rem; font-weight: 800; color: var(--primary); }

    /* Success banner */
    .success-banner {
      background: #f0f7f2;
      border: 1px solid #b8ddc5;
      border-radius: 10px;
      padding: 1.2rem 1.4rem;
      margin-bottom: 1.5rem;
    }
    .success-banner h3 { color: var(--primary); font-size: 1rem; margin: 0 0 .5rem; }
    .success-detail { font-size: .88rem; color: var(--gray-600); line-height: 1.7; margin: 0; }
    .success-detail strong { color: var(--gray-800); }

    .ytd-box {
      background: var(--primary);
      color: #fff;
      border-radius: 10px;
      padding: 1rem 1.25rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    .ytd-box .ytd-label { font-size: .82rem; opacity: .85; }
    .ytd-box .ytd-amount { font-size: 1.5rem; font-weight: 800; }

    /* Claims history table */
    .claims-table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .55rem .8rem; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    th.num, td.num { text-align: right; }
    td { padding: .55rem .8rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); }
    tr:last-child td { border-bottom: none; }
    .amount-cell { font-weight: 700; color: var(--primary); }

    /* Lookup form */
    .lookup-form { display: flex; gap: .75rem; }
    .lookup-form input { flex: 1; }
    .lookup-form button { white-space: nowrap; padding: .65rem 1.1rem; }
    .empty-msg { text-align: center; padding: 2rem; color: var(--gray-400); font-size: .9rem; }

    /* Edit button in history table */
    .edit-claim-btn { background: none; border: none; cursor: pointer; color: var(--gray-300); padding: .15rem .3rem; border-radius: 4px; transition: all .15s; vertical-align: middle; }
    .edit-claim-btn:hover { color: var(--primary); background: var(--accent); }
    .edited-banner  { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: .85rem 1.1rem; margin-bottom: 1rem; font-size: .88rem; color: #1e40af; font-weight: 500; }
    .deleted-banner { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: .85rem 1.1rem; margin-bottom: 1rem; font-size: .88rem; color: #166534; font-weight: 500; }
    .error-banner   { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: .85rem 1.1rem; margin-bottom: 1rem; font-size: .88rem; color: #991b1b; }
    .del-btn { background: none; border: none; cursor: pointer; color: var(--gray-300); padding: .15rem .3rem; border-radius: 4px; transition: all .15s; vertical-align: middle; }
    .del-btn:hover { color: #dc2626; background: #fef2f2; }

    /* Edit modal */
    .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
    .modal-backdrop.open { display: flex; }
    .modal-box { background: #fff; border-radius: 12px; padding: 1.75rem; width: 100%; max-width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
    .modal-box h2 { font-size: 1.05rem; font-weight: 800; color: var(--gray-800); margin: 0 0 1.1rem; }
    .modal-field { margin-bottom: .9rem; }
    .modal-field label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray-500); margin-bottom: .28rem; }
    .modal-field input { width: 100%; border: 1px solid var(--gray-300); border-radius: 7px; padding: .55rem .75rem; font-size: .92rem; box-sizing: border-box; }
    .modal-field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .modal-field input[readonly] { background: #f8f9fa; color: var(--gray-500); cursor: not-allowed; }
    .modal-calc { background: #f0f7f2; border: 1px solid #b8ddc5; border-radius: 7px; padding: .6rem .85rem; font-size: .9rem; color: var(--primary); font-weight: 600; margin-bottom: 1.1rem; }
    .modal-actions { display: flex; gap: .6rem; justify-content: flex-end; }
    .modal-actions .btn { padding: .5rem 1rem; font-size: .88rem; }

    /* School year label */
    .syr-label {
      font-size: .75rem;
      color: var(--gray-400);
      text-align: right;
      margin-bottom: .75rem;
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="header-inner container">
      <a href="index.php" class="logo">
        <img src="bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <button class="search-btn" data-search-open aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav">
        <ul>
          
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li></ul></li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li>
              <li><a href="life-insurance.php">Life Insurance</a></li>
              <li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li>
              <li><a href="ttoc.php">TTOC Resources</a></li>
              <li><a href="atrieve.php">Release Time / Atrieve</a></li>
              <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="members/login.php" class="btn btn-primary" style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;">Member Login</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>EC Mileage Claims</h1>
      <p>Submit your driving reimbursement claim. The current rate is $<?= number_format(MILEAGE_RATE, 2) ?>/km.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <?php if ($success): ?>
      <!-- ── Confirmation ───────────────────────────────────────────────────── -->
      <div class="success-banner">
        <h3>✓ Claim submitted successfully</h3>
        <p class="success-detail">
          <strong><?= htmlspecialchars($success['name']) ?></strong> &nbsp;·&nbsp;
          <?= date('F j, Y', strtotime($success['date_traveled'])) ?> &nbsp;·&nbsp;
          <?= htmlspecialchars($success['event']) ?><br>
          <?= number_format($success['kilometers'], 1) ?> km &nbsp;×&nbsp;
          $<?= number_format($success['rate'], 2) ?>/km &nbsp;=&nbsp;
          <strong>$<?= number_format($success['amount'], 2) ?></strong>
        </p>
      </div>
      <?php endif; ?>

      <div class="mileage-layout">

        <!-- ── Submit Form ─────────────────────────────────────────────────── -->
        <div>
          <div class="mileage-card">
            <div class="mileage-card-header">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14l-4-4 4-4"/><path d="M5 10h11a4 4 0 010 8h-1"/></svg>
              Submit a Claim
            </div>
            <div class="mileage-card-body">
              <form method="POST" action="mileage.php" id="mileage-form" novalidate>
                <input type="hidden" name="action" value="submit">

                <div class="field">
                  <label for="name">Your Full Name</label>
                  <input type="text" id="name" name="name" required
                    placeholder="e.g. Jane Smith"
                    value="<?= htmlspecialchars($success ? $success['name'] : ($_POST['name'] ?? '')) ?>"
                    class="<?= isset($errors['name']) ? 'field-error' : '' ?>">
                  <?php if (isset($errors['name'])): ?>
                    <div class="field-error-msg"><?= $errors['name'] ?></div>
                  <?php endif; ?>
                </div>

                <div class="field-row">
                  <div class="field">
                    <label for="date">Date of Travel</label>
                    <input type="date" id="date" name="date" required
                      max="<?= date('Y-m-d') ?>"
                      value="<?= htmlspecialchars($success ? '' : ($_POST['date'] ?? date('Y-m-d'))) ?>"
                      class="<?= isset($errors['date']) ? 'field-error' : '' ?>">
                    <?php if (isset($errors['date'])): ?>
                      <div class="field-error-msg"><?= $errors['date'] ?></div>
                    <?php endif; ?>
                  </div>

                  <div class="field">
                    <label for="km">Kilometres Driven</label>
                    <input type="number" id="km" name="km" required
                      min="0.1" step="0.1" placeholder="e.g. 124.5"
                      value="<?= htmlspecialchars($success ? '' : ($_POST['km'] ?? '')) ?>"
                      class="<?= isset($errors['km']) ? 'field-error' : '' ?>">
                    <?php if (isset($errors['km'])): ?>
                      <div class="field-error-msg"><?= $errors['km'] ?></div>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="field">
                  <label for="event">Event / Purpose</label>
                  <input type="text" id="event" name="event" required
                    placeholder="e.g. BVTU Executive Meeting — Smithers"
                    value="<?= htmlspecialchars($success ? '' : ($_POST['event'] ?? '')) ?>"
                    class="<?= isset($errors['event']) ? 'field-error' : '' ?>">
                  <?php if (isset($errors['event'])): ?>
                    <div class="field-error-msg"><?= $errors['event'] ?></div>
                  <?php endif; ?>
                </div>

                <div class="calc-row">
                  <div>
                    <div class="calc-label">Rate</div>
                    <div style="font-size:.88rem;font-weight:600;color:var(--gray-600);">$<?= number_format(MILEAGE_RATE, 2) ?>/km</div>
                  </div>
                  <div style="text-align:right;">
                    <div class="calc-label">Estimated Total</div>
                    <div class="calc-total" id="calc-total">—</div>
                  </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;padding:.75rem;font-size:.95rem;">
                  Submit Claim
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- ── My Claims History ───────────────────────────────────────────── -->
        <div>
          <div class="mileage-card">
            <div class="mileage-card-header">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              My Claims History
            </div>
            <div class="mileage-card-body">

              <?php if ($editSuccess): ?>
                <div class="edited-banner">✓ Claim updated successfully.</div>
              <?php elseif ($deleteSuccess): ?>
                <div class="deleted-banner">✓ Claim deleted.</div>
              <?php elseif ($editError): ?>
                <div class="error-banner">⚠ <?= htmlspecialchars($editError) ?></div>
              <?php elseif ($deleteError): ?>
                <div class="error-banner">⚠ <?= htmlspecialchars($deleteError) ?></div>
              <?php endif; ?>

              <?php if ($lookupDone && $lookupName): ?>

                <!-- YTD total -->
                <?php
                  $ytd   = ytdTotal($lookupName);
                  $ytdkm = ytdKm($lookupName);
                  $syStart = date('M j, Y', strtotime(schoolYearStart()));
                  $syEnd   = date('M j, Y', strtotime(date('Y') . '-08-31' > date('Y-m-d')
                    ? date('Y') . '-08-31'
                    : (date('Y') + 1) . '-08-31'));
                ?>
                <div class="ytd-box">
                  <div>
                    <div class="ytd-label">School Year Total (<?= $syStart ?> – present)</div>
                    <div style="font-size:.8rem;opacity:.7;margin-top:.15rem;"><?= number_format($ytdkm, 1) ?> km claimed</div>
                  </div>
                  <div class="ytd-amount">$<?= number_format($ytd, 2) ?></div>
                </div>

                <p style="font-size:.82rem;color:var(--gray-500);margin-bottom:.85rem;">
                  Showing claims for <strong><?= htmlspecialchars($lookupName) ?></strong>.
                  Not you? <a href="mileage.php" style="color:var(--primary);">Clear</a>
                </p>

                <?php if ($lookupClaims): ?>
                <div class="claims-table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Event</th>
                        <th class="num">km</th>
                        <th class="num">Amount</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($lookupClaims as $c): ?>
                      <tr>
                        <td style="white-space:nowrap;"><?= date('M j, Y', strtotime($c['date_traveled'])) ?></td>
                        <td><?= htmlspecialchars($c['event']) ?></td>
                        <td class="num"><?= number_format($c['kilometers'], 1) ?></td>
                        <td class="num amount-cell">$<?= number_format($c['amount'], 2) ?></td>
                        <td style="white-space:nowrap;">
                          <button type="button" class="edit-claim-btn" title="Edit this claim"
                            onclick="openClaimEdit(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['name'])) ?>, '<?= $c['date_traveled'] ?>', <?= htmlspecialchars(json_encode($c['event'])) ?>, <?= (float)$c['kilometers'] ?>)">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          </button>
                          <form method="POST" action="mileage.php" style="display:inline;"
                            onsubmit="return confirm('Delete this claim (<?= date('M j', strtotime($c['date_traveled'])) ?> — <?= htmlspecialchars(addslashes($c['event'])) ?>)?\nThis cannot be undone.');">
                            <input type="hidden" name="action"   value="delete">
                            <input type="hidden" name="del_id"   value="<?= $c['id'] ?>">
                            <input type="hidden" name="del_name" value="<?= htmlspecialchars($c['name']) ?>">
                            <button type="submit" class="del-btn" title="Delete this claim">
                              <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                            </button>
                          </form>
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php else: ?>
                  <div class="empty-msg">No claims found for this name.</div>
                <?php endif; ?>

              <?php else: ?>

                <!-- Lookup form -->
                <p style="font-size:.88rem;color:var(--gray-500);margin-bottom:1.1rem;line-height:1.6;">
                  Enter your name to view your claim history and year-to-date total.
                </p>
                <form method="POST" action="mileage.php" class="lookup-form">
                  <input type="hidden" name="action" value="lookup">
                  <input type="text" name="lookup_name" placeholder="Your full name"
                    value="<?= htmlspecialchars($lookupName) ?>" required>
                  <button type="submit" class="btn btn-primary">Look Up</button>
                </form>

                <?php if ($lookupDone && !$lookupName): ?>
                  <div class="field-error-msg" style="margin-top:.5rem;">Please enter your name.</div>
                <?php endif; ?>

              <?php endif; ?>

            </div>
          </div>
        </div>

      </div><!-- /.mileage-layout -->

    </div>
  </main>

  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <h3>Bulkley Valley Teachers' Union</h3>
        <p>Local of the BC Teachers' Federation</p>
        <p>Representing educators in<br>Houston, Telkwa, and Smithers</p>
      </div>
      <div>
        <h3>Contact</h3>
        <p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p>
        <p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p>
        <p><a href="contact.php">Contact Us</a></p>
      </div>
      <div>
        <h3>Navigate</h3>
        <ul class="footer-nav-list">
          
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Members</a></li>
          <li class="has-dropdown"><a href="prod.php">PRO-D</a><ul class="dropdown"><li><a href="prod.php">PRO-D Info</a></li><li><a href="members/prod-dashboard.php">Pro-D Portal</a></li></ul></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
        </ul>
      </div>
      <div>
        <h3>Connect</h3>
        <a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p>
      </div>
    </div>
  </footer>

  <!-- Edit Claim Modal -->
  <div class="modal-backdrop" id="claimEditModal" onclick="if(event.target===this)closeClaimEdit()">
    <div class="modal-box">
      <h2>Edit Claim</h2>
      <form method="POST" action="mileage.php">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="edit_id"   id="cedit-id">
        <input type="hidden" name="edit_name" id="cedit-name">

        <div class="modal-field">
          <label>Name</label>
          <input type="text" id="cedit-name-display" readonly>
        </div>
        <div class="modal-field">
          <label>Date of Travel</label>
          <input type="date" name="edit_date" id="cedit-date" required max="<?= date('Y-m-d') ?>">
        </div>
        <div class="modal-field">
          <label>Event / Purpose</label>
          <input type="text" name="edit_event" id="cedit-event" required maxlength="300">
        </div>
        <div class="modal-field">
          <label>Kilometres</label>
          <input type="number" name="edit_km" id="cedit-km" required min="0.1" step="0.1" oninput="updateClaimCalc()">
        </div>
        <div class="modal-field">
          <label>Rate ($/km)</label>
          <input type="text" value="$<?= number_format(MILEAGE_RATE, 2) ?>/km — fixed" readonly>
        </div>
        <div class="modal-calc" id="cedit-calc">Total: $0.00</div>
        <div class="modal-actions">
          <button type="button" class="btn btn-outline" onclick="closeClaimEdit()">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script src="js/site.js"></script>
  <script>
  // Live km → dollar calculation (submit form)
  const kmInput   = document.getElementById('km');
  const calcTotal = document.getElementById('calc-total');
  const RATE      = <?= MILEAGE_RATE ?>;

  function updateCalc() {
    const km = parseFloat(kmInput.value);
    calcTotal.textContent = (!isNaN(km) && km > 0) ? '$' + (km * RATE).toFixed(2) : '—';
  }
  if (kmInput) { kmInput.addEventListener('input', updateCalc); updateCalc(); }

  // Edit modal
  function openClaimEdit(id, name, date, event, km) {
    document.getElementById('cedit-id').value           = id;
    document.getElementById('cedit-name').value         = name;
    document.getElementById('cedit-name-display').value = name;
    document.getElementById('cedit-date').value         = date;
    document.getElementById('cedit-event').value        = event;
    document.getElementById('cedit-km').value           = km;
    updateClaimCalc();
    document.getElementById('claimEditModal').classList.add('open');
    document.getElementById('cedit-date').focus();
  }
  function closeClaimEdit() {
    document.getElementById('claimEditModal').classList.remove('open');
  }
  function updateClaimCalc() {
    const km  = parseFloat(document.getElementById('cedit-km').value) || 0;
    document.getElementById('cedit-calc').textContent = 'Total: $' + (km * RATE).toFixed(2);
  }
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeClaimEdit();
  });
  </script>
</body>
</html>
