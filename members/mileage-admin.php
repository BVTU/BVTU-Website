<?php
/**
 * mileage-admin.php — Full mileage claim manager (member login required)
 *
 * GET  /members/mileage-admin.php           — view all claims
 * GET  /members/mileage-admin.php?export=1  — download CSV
 * POST /members/mileage-admin.php           — delete a claim (action=delete&id=N)
 */
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/db.php';

// ── Ensure table exists (mirrors mileage.php) ─────────────────────────────────
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

// ── CSV Export ────────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    $month = isset($_GET['month']) ? (int)$_GET['month'] : 0;

    $sql    = "SELECT * FROM mileage_claims WHERE 1=1";
    $params = [];
    if ($month > 0) {
        $sql .= " AND YEAR(date_traveled) = ? AND MONTH(date_traveled) = ?";
        $params = [$year, $month];
        $label  = date('F_Y', mktime(0, 0, 0, $month, 1, $year));
    } else {
        $sql .= " AND YEAR(date_traveled) = ?";
        $params = [$year];
        $label  = "Full_{$year}";
    }
    $sql .= " ORDER BY name, date_traveled";

    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Per-person totals for summary section
    $totals = [];
    foreach ($rows as $r) {
        $n = $r['name'];
        if (!isset($totals[$n])) $totals[$n] = ['km' => 0, 'amount' => 0, 'count' => 0];
        $totals[$n]['km']     += $r['kilometers'];
        $totals[$n]['amount'] += $r['amount'];
        $totals[$n]['count']++;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="BVTU_Mileage_' . $label . '.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");

    // Header row
    fputcsv($out, ['BVTU EC Mileage Claims — Export: ' . str_replace('_', ' ', $label)]);
    fputcsv($out, ['Generated', date('F j, Y g:i A')]);
    fputcsv($out, []);

    // Column headers
    fputcsv($out, ['#', 'Name', 'Date Traveled', 'Event / Purpose', 'Kilometres', 'Rate ($/km)', 'Amount ($)', 'Submitted']);

    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['name'],
            date('Y-m-d', strtotime($r['date_traveled'])),
            $r['event'],
            number_format($r['kilometers'], 1),
            number_format($r['rate'], 2),
            number_format($r['amount'], 2),
            date('Y-m-d H:i', strtotime($r['submitted_at'])),
        ]);
    }

    // Summary section
    fputcsv($out, []);
    fputcsv($out, ['SUMMARY BY PERSON']);
    fputcsv($out, ['Name', '', 'Claims', 'Total km', 'Total ($)', '', '', '']);
    foreach ($totals as $name => $t) {
        fputcsv($out, [
            $name, '',
            $t['count'],
            number_format($t['km'], 1),
            number_format($t['amount'], 2),
            '', '', ''
        ]);
    }

    // Grand total
    $grandKm  = array_sum(array_column($totals, 'km'));
    $grandAmt = array_sum(array_column($totals, 'amount'));
    $grandCnt = array_sum(array_column($totals, 'count'));
    fputcsv($out, []);
    fputcsv($out, ['GRAND TOTAL', '', $grandCnt, number_format($grandKm, 1), number_format($grandAmt, 2)]);
    fclose($out);
    exit;
}

// ── Delete claim ──────────────────────────────────────────────────────────────
$deleted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $delId = (int)($_POST['id'] ?? 0);
    if ($delId > 0) {
        getDB()->prepare("DELETE FROM mileage_claims WHERE id = ?")->execute([$delId]);
        $deleted = true;
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$filterYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$filterName  = trim($_GET['name'] ?? '');

$sql    = "SELECT * FROM mileage_claims WHERE 1=1";
$params = [];
if ($filterYear) {
    $sql    .= " AND YEAR(date_traveled) = ?";
    $params[] = $filterYear;
}
if ($filterMonth > 0) {
    $sql    .= " AND MONTH(date_traveled) = ?";
    $params[] = $filterMonth;
}
if ($filterName !== '') {
    $sql    .= " AND name = ?";
    $params[] = $filterName;
}
$sql .= " ORDER BY date_traveled DESC, submitted_at DESC";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll();

// Per-person summary for current filter
$summary = [];
foreach ($claims as $c) {
    $n = $c['name'];
    if (!isset($summary[$n])) $summary[$n] = ['km' => 0, 'amount' => 0, 'count' => 0];
    $summary[$n]['km']     += $c['kilometers'];
    $summary[$n]['amount'] += $c['amount'];
    $summary[$n]['count']++;
}

// All distinct names (for filter dropdown)
$names = getDB()->query("SELECT DISTINCT name FROM mileage_claims ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Available years
$years = getDB()->query("SELECT DISTINCT YEAR(date_traveled) AS yr FROM mileage_claims ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array((int)date('Y'), $years)) array_unshift($years, (int)date('Y'));

$months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
           7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];

$grandTotal = array_sum(array_column($claims, 'amount'));
$grandKm    = array_sum(array_column($claims, 'kilometers'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mileage Claims — BVTU Admin</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .admin-wrap { max-width: 1100px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

    .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .admin-header h1 { font-size: 1.4rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    /* Stats bar */
    .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    .stat-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.1rem 1.25rem; }
    .stat-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-400); margin-bottom: .3rem; }
    .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--gray-800); }
    .stat-sub { font-size: .75rem; color: var(--gray-400); margin-top: .2rem; }

    /* Filter bar */
    .filter-bar { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; gap: .75rem; flex-wrap: wrap; align-items: flex-end; }
    .filter-bar label { font-size: .75rem; font-weight: 700; color: var(--gray-500); display: block; margin-bottom: .3rem; text-transform: uppercase; letter-spacing: .04em; }
    .filter-bar select, .filter-bar input { border: 1px solid var(--gray-300); border-radius: 6px; padding: .5rem .7rem; font-size: .88rem; }
    .filter-bar select:focus, .filter-bar input:focus { outline: none; border-color: var(--primary); }
    .filter-bar .filter-actions { display: flex; gap: .5rem; margin-top: 1.3rem; }

    /* Summary cards per person */
    .summary-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: .85rem; margin-bottom: 1.75rem; }
    .summary-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 9px; padding: .9rem 1rem; }
    .summary-name { font-weight: 700; font-size: .9rem; color: var(--gray-800); margin-bottom: .35rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .summary-detail { font-size: .78rem; color: var(--gray-500); margin: .15rem 0; }
    .summary-amount { font-size: 1.15rem; font-weight: 800; color: var(--primary); margin-top: .4rem; }

    /* Claims table */
    .table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: .86rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .6rem 1rem; text-align: left; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    th.num, td.num { text-align: right; }
    td { padding: .6rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); }
    tr:last-child td { border-bottom: none; }
    .name-cell { font-weight: 600; color: var(--gray-800); }
    .amount-cell { font-weight: 700; color: var(--primary); }
    .delete-btn { background: none; border: none; cursor: pointer; color: var(--gray-300); font-size: .8rem; padding: .15rem .4rem; border-radius: 4px; transition: all .15s; }
    .delete-btn:hover { color: #dc2626; background: #fef2f2; }
    .empty-row td { text-align: center; color: var(--gray-400); padding: 3rem; }

    .export-btn { display: inline-flex; align-items: center; gap: .4rem; }
    .export-btn svg { width: 15px; height: 15px; }

    .deleted-notice { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .85rem; color: #166534; margin-bottom: 1rem; }
  </style>
</head>
<body>
<div class="admin-wrap">

  <div class="admin-header">
    <h1>EC Mileage Claims</h1>
    <a class="back-link" href="dashboard.php">← Back to Dashboard</a>
  </div>

  <?php if ($deleted): ?>
  <div class="deleted-notice">Claim deleted.</div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="stat-label">Total Shown</div>
      <div class="stat-value">$<?= number_format($grandTotal, 2) ?></div>
      <div class="stat-sub"><?= count($claims) ?> claim<?= count($claims) !== 1 ? 's' : '' ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total km</div>
      <div class="stat-value"><?= number_format($grandKm, 1) ?></div>
      <div class="stat-sub">kilometres driven</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Claimants</div>
      <div class="stat-value"><?= count($summary) ?></div>
      <div class="stat-sub">individual<?= count($summary) !== 1 ? 's' : '' ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Avg per Claim</div>
      <div class="stat-value"><?= count($claims) ? '$' . number_format($grandTotal / count($claims), 2) : '—' ?></div>
      <div class="stat-sub"><?= count($claims) ? number_format($grandKm / count($claims), 1) . ' km avg' : '' ?></div>
    </div>
  </div>

  <!-- Filter & Export bar -->
  <form method="GET" action="mileage-admin.php" class="filter-bar">
    <div>
      <label>Year</label>
      <select name="year">
        <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $filterYear === (int)$y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Month</label>
      <select name="month">
        <option value="0">All months</option>
        <?php foreach ($months as $num => $label): ?>
          <option value="<?= $num ?>" <?= $filterMonth === $num ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Person</label>
      <select name="name">
        <option value="">All people</option>
        <?php foreach ($names as $n): ?>
          <option value="<?= htmlspecialchars($n) ?>" <?= $filterName === $n ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filter-actions">
      <button type="submit" class="btn btn-primary" style="padding:.5rem 1rem;font-size:.88rem;">Filter</button>
      <a href="mileage-admin.php" class="btn btn-outline" style="padding:.5rem 1rem;font-size:.88rem;">Reset</a>
    </div>
    <div style="margin-left:auto;margin-top:1.3rem;">
      <a href="mileage-admin.php?export=1&year=<?= $filterYear ?>&month=<?= $filterMonth ?>&name=<?= urlencode($filterName) ?>"
         class="btn btn-primary export-btn" style="padding:.5rem 1rem;font-size:.88rem;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
      </a>
    </div>
  </form>

  <!-- Per-person summary -->
  <?php if ($summary): ?>
  <div class="summary-grid">
    <?php foreach ($summary as $name => $s): ?>
    <div class="summary-card">
      <div class="summary-name" title="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></div>
      <div class="summary-detail"><?= $s['count'] ?> claim<?= $s['count'] !== 1 ? 's' : '' ?> · <?= number_format($s['km'], 1) ?> km</div>
      <div class="summary-amount">$<?= number_format($s['amount'], 2) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Full claims table -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Date</th>
          <th>Event / Purpose</th>
          <th class="num">km</th>
          <th class="num">Rate</th>
          <th class="num">Amount</th>
          <th>Submitted</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$claims): ?>
        <tr class="empty-row"><td colspan="9">No claims found for the selected filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($claims as $c): ?>
        <tr>
          <td style="color:var(--gray-400);font-size:.78rem;"><?= $c['id'] ?></td>
          <td class="name-cell"><?= htmlspecialchars($c['name']) ?></td>
          <td style="white-space:nowrap;"><?= date('M j, Y', strtotime($c['date_traveled'])) ?></td>
          <td><?= htmlspecialchars($c['event']) ?></td>
          <td class="num"><?= number_format($c['kilometers'], 1) ?></td>
          <td class="num" style="color:var(--gray-400);">$<?= number_format($c['rate'], 2) ?></td>
          <td class="num amount-cell">$<?= number_format($c['amount'], 2) ?></td>
          <td style="white-space:nowrap;font-size:.78rem;color:var(--gray-400);"><?= date('M j g:ia', strtotime($c['submitted_at'])) ?></td>
          <td>
            <form method="POST" onsubmit="return confirm('Delete this claim from <?= htmlspecialchars($c['name']) ?> on <?= date('M j', strtotime($c['date_traveled'])) ?>?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="delete-btn" title="Delete claim">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <?php if ($claims): ?>
      <tfoot>
        <tr style="background:#f8f9fa;">
          <td colspan="4"><strong>Totals</strong></td>
          <td class="num"><strong><?= number_format($grandKm, 1) ?></strong></td>
          <td></td>
          <td class="num amount-cell"><strong>$<?= number_format($grandTotal, 2) ?></strong></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

</div>
</body>
</html>
