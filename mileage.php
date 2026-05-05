<?php
/**
 * mileage.php — BVTU EC Mileage Tracker (public, no login required)
 *
 * Handles:
 *   GET  /mileage.php               — blank submission form
 *   POST /mileage.php action=submit — process new claim
 *   POST /mileage.php action=lookup — view personal history by name
 */

require_once __DIR__ . '/members/config.php';
require_once __DIR__ . '/members/db.php';

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
             . "https://new.bvtu.ca/members/mileage-admin.php\n";

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li></ul></li>
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
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
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
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($lookupClaims as $c): ?>
                      <tr>
                        <td style="white-space:nowrap;"><?= date('M j, Y', strtotime($c['date_traveled'])) ?></td>
                        <td><?= htmlspecialchars($c['event']) ?></td>
                        <td class="num"><?= number_format($c['kilometers'], 1) ?></td>
                        <td class="num amount-cell">$<?= number_format($c['amount'], 2) ?></td>
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
          <li><a href="about.php">About</a></li>
          <li><a href="documents.php">Documents</a></li>
          <li><a href="members.php">Members</a></li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
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

  <script src="js/site.js"></script>
  <script>
  // Live km → dollar calculation
  const kmInput   = document.getElementById('km');
  const calcTotal = document.getElementById('calc-total');
  const RATE      = <?= MILEAGE_RATE ?>;

  function updateCalc() {
    const km = parseFloat(kmInput.value);
    if (isNaN(km) || km <= 0) {
      calcTotal.textContent = '—';
    } else {
      calcTotal.textContent = '$' + (km * RATE).toFixed(2);
    }
  }

  if (kmInput) {
    kmInput.addEventListener('input', updateCalc);
    updateCalc();
  }
  </script>
</body>
</html>
