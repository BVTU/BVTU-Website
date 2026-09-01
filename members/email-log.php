<?php
/**
 * email-log.php — Admin view of all outgoing email attempts
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';

requireLogin();
$member = getMember();
if (!expIsAdmin($member['email'])) {
    header('Location: dashboard.php');
    exit;
}

$db = getDB();

// Ensure table exists (first visit before any email has been sent)
$db->exec("CREATE TABLE IF NOT EXISTS site_email_log (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    sent_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    to_email  VARCHAR(255) NOT NULL,
    subject   VARCHAR(500) NOT NULL,
    status    VARCHAR(10)  NOT NULL,
    error_msg VARCHAR(500),
    INDEX idx_sent (sent_at),
    INDEX idx_to   (to_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$filter = trim($_GET['filter'] ?? '');
$page   = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

if ($filter) {
    $total = (int)$db->prepare("SELECT COUNT(*) FROM site_email_log WHERE to_email LIKE ? OR subject LIKE ?")
                     ->execute(['%'.$filter.'%', '%'.$filter.'%']) ? 0 : 0;
    $s = $db->prepare("SELECT COUNT(*) FROM site_email_log WHERE to_email LIKE ? OR subject LIKE ?");
    $s->execute(['%'.$filter.'%', '%'.$filter.'%']);
    $total = (int)$s->fetchColumn();

    $s2 = $db->prepare("SELECT * FROM site_email_log WHERE to_email LIKE ? OR subject LIKE ? ORDER BY sent_at DESC LIMIT $perPage OFFSET $offset");
    $s2->execute(['%'.$filter.'%', '%'.$filter.'%']);
    $rows = $s2->fetchAll();
} else {
    $total = (int)$db->query("SELECT COUNT(*) FROM site_email_log")->fetchColumn();
    $s = $db->prepare("SELECT * FROM site_email_log ORDER BY sent_at DESC LIMIT $perPage OFFSET $offset");
    $s->execute();
    $rows = $s->fetchAll();
}

$pages = max(1, (int)ceil($total / $perPage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Log — BVTU Admin</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem 5rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .page-header h1 { font-size: 1.2rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .meta-row { font-size: .82rem; color: var(--gray-500); margin-bottom: 1.25rem; }

    .filter-row { display: flex; gap: .6rem; align-items: center; margin-bottom: 1.25rem; }
    .filter-row input { border: 1px solid var(--gray-300); border-radius: 7px; padding: .5rem .75rem; font-size: .9rem; font-family: inherit; min-width: 240px; }
    .filter-row input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,107,53,.1); }
    .filter-row button { padding: .5rem .9rem; font-size: .88rem; }
    .filter-row a { font-size: .82rem; color: var(--primary); }

    .log-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; font-size: .84rem; }
    .log-table thead th { background: #1a2e1a; color: #fff; padding: .6rem .85rem; text-align: left; font-size: .71rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
    .log-table tbody tr { border-bottom: 1px solid var(--gray-100); }
    .log-table tbody tr:last-child { border-bottom: none; }
    .log-table tbody tr:hover { background: #fafafa; }
    .log-table td { padding: .55rem .85rem; vertical-align: top; }
    .ts { color: var(--gray-400); white-space: nowrap; font-size: .78rem; }
    .to-email { font-weight: 600; color: var(--gray-800); }
    .subject-cell { color: var(--gray-600); max-width: 340px; }
    .badge { display: inline-block; font-size: .68rem; font-weight: 800; border-radius: 100px; padding: .15rem .55rem; white-space: nowrap; }
    .badge.sent   { background: #dcfce7; color: #166534; }
    .badge.failed { background: #fef2f2; color: #991b1b; }
    .err-msg { font-size: .74rem; color: #991b1b; margin-top: .2rem; }

    .empty { text-align: center; padding: 3rem; color: var(--gray-400); font-size: .9rem; }
    .pagination { display: flex; gap: .4rem; align-items: center; margin-top: 1.25rem; flex-wrap: wrap; }
    .pagination a, .pagination span { display: inline-block; padding: .35rem .7rem; border: 1px solid var(--gray-200); border-radius: 6px; font-size: .82rem; text-decoration: none; color: var(--gray-600); }
    .pagination a:hover { border-color: var(--primary); color: var(--primary); }
    .pagination .cur { background: var(--primary); color: #fff; border-color: var(--primary); font-weight: 700; }
    .summary-chips { display: flex; gap: .75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .chip { background: #fff; border: 1px solid var(--gray-200); border-radius: 8px; padding: .5rem .85rem; font-size: .82rem; }
    .chip strong { font-size: 1rem; display: block; }
    .chip.ok   strong { color: #166534; }
    .chip.fail strong { color: #991b1b; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <h1>Email Log</h1>
    <div style="display:flex;gap:.85rem;flex-wrap:wrap;align-items:center;">
      <a href="test-email.php" class="back-link">← Test Email</a>
      <a href="dashboard.php" class="back-link">Dashboard</a>
    </div>
  </div>

  <?php
  // Summary chips
  $sentCount  = (int)$db->query("SELECT COUNT(*) FROM site_email_log WHERE status='sent'")->fetchColumn();
  $failCount  = (int)$db->query("SELECT COUNT(*) FROM site_email_log WHERE status='failed'")->fetchColumn();
  $todayCount = (int)$db->query("SELECT COUNT(*) FROM site_email_log WHERE DATE(sent_at)=CURDATE()")->fetchColumn();
  ?>
  <div class="summary-chips">
    <div class="chip ok"><strong><?= $sentCount ?></strong>Total sent</div>
    <div class="chip fail"><strong><?= $failCount ?></strong>Failed</div>
    <div class="chip"><strong><?= $todayCount ?></strong>Today</div>
    <div class="chip"><strong><?= $total ?></strong><?= $filter ? 'Matching filter' : 'Total records' ?></div>
  </div>

  <form method="GET" class="filter-row">
    <input type="text" name="filter" placeholder="Filter by email or subject…" value="<?= htmlspecialchars($filter) ?>">
    <button type="submit" class="btn btn-primary" style="padding:.5rem .9rem;font-size:.88rem;">Search</button>
    <?php if ($filter): ?><a href="email-log.php">Clear</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
  <div class="empty">
    <?= $filter ? 'No emails matching "' . htmlspecialchars($filter) . '".' : 'No emails have been logged yet. Logs are recorded from this point forward.' ?>
  </div>
  <?php else: ?>
  <table class="log-table">
    <thead>
      <tr>
        <th>Time</th>
        <th>To</th>
        <th>Subject</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="ts"><?= date('M j, Y', strtotime($r['sent_at'])) ?><br><?= date('g:i a', strtotime($r['sent_at'])) ?></td>
        <td class="to-email"><?= htmlspecialchars($r['to_email']) ?></td>
        <td class="subject-cell"><?= htmlspecialchars($r['subject']) ?>
          <?php if ($r['error_msg']): ?>
          <div class="err-msg">⚠ <?= htmlspecialchars($r['error_msg']) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="badge <?= $r['status'] ?>"><?= $r['status'] === 'sent' ? '✓ Sent' : '✗ Failed' ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++):
      $href = '?p=' . $i . ($filter ? '&filter=' . urlencode($filter) : '');
    ?>
      <?php if ($i === $page): ?>
      <span class="cur"><?= $i ?></span>
      <?php else: ?>
      <a href="<?= $href ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>
</body>
</html>
