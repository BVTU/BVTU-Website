<?php
/**
 * Token usage monitor — accessible to logged-in members only.
 * Shows Claude API usage from the api_usage table.
 */
require_once __DIR__ . '/auth.php';
requireLogin();

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) require_once $configPath;

$error = null;
$rows  = [];
$monthTotal = 0;
$todayTotal = 0;
$allTimeTotal = 0;
$threshold = defined('TOKEN_ALERT_THRESHOLD') ? (int)TOKEN_ALERT_THRESHOLD : 500000;

if (defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );

        // Check if table exists
        $tableExists = $pdo->query("SHOW TABLES LIKE 'api_usage'")->rowCount() > 0;

        if ($tableExists) {
            // Daily breakdown for current month
            $rows = $pdo->query("
                SELECT
                    DATE(created_at)     AS day,
                    COUNT(*)             AS requests,
                    SUM(input_tokens)    AS input_tokens,
                    SUM(output_tokens)   AS output_tokens,
                    SUM(total_tokens)    AS total_tokens
                FROM api_usage
                WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
                GROUP BY DATE(created_at)
                ORDER BY day DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $monthTotal = (int)$pdo->query("
                SELECT COALESCE(SUM(total_tokens), 0)
                FROM api_usage
                WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
            ")->fetchColumn();

            $todayTotal = (int)$pdo->query("
                SELECT COALESCE(SUM(total_tokens), 0)
                FROM api_usage
                WHERE DATE(created_at) = CURDATE()
            ")->fetchColumn();

            $allTimeTotal = (int)$pdo->query("
                SELECT COALESCE(SUM(total_tokens), 0) FROM api_usage
            ")->fetchColumn();

            $totalRequests = (int)$pdo->query("
                SELECT COUNT(*) FROM api_usage
                WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
            ")->fetchColumn();
        } else {
            $error = 'No usage data yet — the api_usage table will be created automatically after the first Contract Assistant query.';
        }
    } catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
} else {
    $error = 'Database not configured. Add DB_HOST, DB_NAME, DB_USER, DB_PASS to members/config.php.';
}

$pct = $threshold > 0 ? min(100, round($monthTotal / $threshold * 100, 1)) : 0;
$barColor = $pct >= 90 ? '#dc2626' : ($pct >= 75 ? '#d97706' : '#1a6b35');

// Rough cost estimate: Haiku input = $0.80/M, output = $4.00/M
function estimateCost($inputTokens, $outputTokens) {
    return ($inputTokens / 1_000_000 * 0.80) + ($outputTokens / 1_000_000 * 4.00);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Token Usage — BVTU Dashboard</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .dash-wrap { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .dash-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
    .dash-header h1 { font-size: 1.4rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: .3rem; }
    .back-link:hover { text-decoration: underline; }

    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.25rem 1.4rem; }
    .stat-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-400); margin-bottom: .4rem; }
    .stat-value { font-size: 1.6rem; font-weight: 800; color: var(--gray-800); line-height: 1.1; }
    .stat-sub { font-size: .78rem; color: var(--gray-400); margin-top: .25rem; }

    .usage-bar-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.4rem 1.6rem; margin-bottom: 2rem; }
    .usage-bar-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .75rem; }
    .usage-bar-title { font-weight: 700; font-size: .95rem; color: var(--gray-700); }
    .usage-bar-pct { font-size: 1.1rem; font-weight: 800; color: <?= $barColor ?>; }
    .usage-bar-track { background: var(--gray-100); border-radius: 100px; height: 12px; overflow: hidden; margin-bottom: .5rem; }
    .usage-bar-fill { height: 100%; border-radius: 100px; background: <?= $barColor ?>; width: <?= $pct ?>%; transition: width .4s; }
    .usage-bar-legend { display: flex; justify-content: space-between; font-size: .78rem; color: var(--gray-400); }

    .section-title { font-size: 1rem; font-weight: 700; color: var(--gray-700); margin: 0 0 1rem; }
    .usage-table-wrap { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    thead tr { background: #f8f9fa; }
    th { padding: .65rem 1rem; text-align: left; font-weight: 700; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); }
    th.num, td.num { text-align: right; }
    td { padding: .6rem 1rem; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }
    .cost-badge { font-size: .75rem; font-weight: 600; color: var(--primary); }

    .empty-state { text-align: center; padding: 3rem 2rem; color: var(--gray-400); }
    .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem 1.25rem; color: #991b1b; font-size: .9rem; margin-bottom: 1.5rem; }

    .note-box { background: #f0f7f2; border: 1px solid #b8ddc5; border-radius: 8px; padding: .85rem 1rem; font-size: .82rem; color: #1a5c2e; margin-top: 1.5rem; }
  </style>
</head>
<body>
<div class="dash-wrap">

  <div class="dash-header">
    <h1>Claude API Token Usage</h1>
    <a class="back-link" href="dashboard.php">
      ← Back to Dashboard
    </a>
  </div>

  <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php else: ?>

  <!-- Stats -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">This Month</div>
      <div class="stat-value"><?= number_format($monthTotal) ?></div>
      <div class="stat-sub">tokens · <?= date('F Y') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Today</div>
      <div class="stat-value"><?= number_format($todayTotal) ?></div>
      <div class="stat-sub">tokens · <?= date('M j') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Requests This Month</div>
      <div class="stat-value"><?= number_format($totalRequests ?? 0) ?></div>
      <div class="stat-sub">CA Assistant queries</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Est. Monthly Cost</div>
      <div class="stat-value">$<?= number_format(estimateCost(
          (int)array_sum(array_column($rows, 'input_tokens')),
          (int)array_sum(array_column($rows, 'output_tokens'))
      ), 2) ?></div>
      <div class="stat-sub">Haiku pricing (approx.)</div>
    </div>
  </div>

  <!-- Progress bar -->
  <div class="usage-bar-wrap">
    <div class="usage-bar-header">
      <span class="usage-bar-title">Monthly Budget Usage</span>
      <span class="usage-bar-pct"><?= $pct ?>%</span>
    </div>
    <div class="usage-bar-track">
      <div class="usage-bar-fill"></div>
    </div>
    <div class="usage-bar-legend">
      <span><?= number_format($monthTotal) ?> used</span>
      <span>Budget: <?= number_format($threshold) ?> tokens
        <?php if (!defined('TOKEN_ALERT_THRESHOLD')): ?>
          <em>(default — set TOKEN_ALERT_THRESHOLD in config.php to customise)</em>
        <?php endif; ?>
      </span>
    </div>
  </div>

  <!-- Daily breakdown table -->
  <p class="section-title">Daily Breakdown — <?= date('F Y') ?></p>

  <?php if (empty($rows)): ?>
    <div class="empty-state">No queries yet this month.</div>
  <?php else: ?>
    <div class="usage-table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th class="num">Requests</th>
            <th class="num">Input Tokens</th>
            <th class="num">Output Tokens</th>
            <th class="num">Total Tokens</th>
            <th class="num">Est. Cost</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= date('D M j', strtotime($row['day'])) ?></td>
            <td class="num"><?= number_format($row['requests']) ?></td>
            <td class="num"><?= number_format($row['input_tokens']) ?></td>
            <td class="num"><?= number_format($row['output_tokens']) ?></td>
            <td class="num"><strong><?= number_format($row['total_tokens']) ?></strong></td>
            <td class="num"><span class="cost-badge">$<?= number_format(estimateCost((int)$row['input_tokens'], (int)$row['output_tokens']), 3) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#f8f9fa;">
            <td><strong>Month total</strong></td>
            <td class="num"><strong><?= number_format(array_sum(array_column($rows, 'requests'))) ?></strong></td>
            <td class="num"><strong><?= number_format(array_sum(array_column($rows, 'input_tokens'))) ?></strong></td>
            <td class="num"><strong><?= number_format(array_sum(array_column($rows, 'output_tokens'))) ?></strong></td>
            <td class="num"><strong><?= number_format($monthTotal) ?></strong></td>
            <td class="num"><span class="cost-badge">$<?= number_format(estimateCost(
                (int)array_sum(array_column($rows, 'input_tokens')),
                (int)array_sum(array_column($rows, 'output_tokens'))
            ), 3) ?></span></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>

  <div class="note-box">
    Cost estimates use Claude Haiku pricing: $0.80/M input tokens, $4.00/M output tokens. Actual charges appear on your Anthropic account. All-time total: <?= number_format($allTimeTotal) ?> tokens across all queries.
  </div>

  <?php endif; ?>

</div>
</body>
</html>
