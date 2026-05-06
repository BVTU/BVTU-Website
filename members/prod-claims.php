<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();

$filter = $_GET['status'] ?? 'all';
$sql    = "SELECT * FROM prod_claims WHERE user_email=?";
$params = [$member['email']];
if ($filter !== 'all') { $sql .= " AND status=?"; $params[] = $filter; }
$sql .= " ORDER BY created_at DESC";
$stmt = getDB()->prepare($sql); $stmt->execute($params);
$claims = $stmt->fetchAll();

$statusLabel = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$statusColor = ['pending' => '#d97706', 'approved' => '#166534', 'rejected' => '#991b1b'];
$statusBg    = ['pending' => '#fffbeb', 'approved' => '#f0fdf4', 'rejected' => '#fef2f2'];
$catLabel    = ['conference' => 'Conference', 'course' => 'Course', 'materials' => 'Materials', 'travel' => 'Travel', 'other' => 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Claims — Pro-D Portal</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 960px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .filter-tabs { display: flex; gap: .4rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
    .tab { padding: .4rem .9rem; border-radius: 100px; font-size: .82rem; font-weight: 600; text-decoration: none; border: 1px solid var(--gray-200); background: #fff; color: var(--gray-600); transition: all .15s; }
    .tab:hover { border-color: var(--primary); color: var(--primary); }
    .tab.active { background: var(--primary); color: #fff; border-color: var(--primary); }

    .claims-list { display: flex; flex-direction: column; gap: .85rem; }
    .claim-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.1rem 1.25rem; }
    .claim-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: .6rem; }
    .claim-title { font-weight: 700; color: var(--gray-800); font-size: .95rem; }
    .claim-meta { font-size: .78rem; color: var(--gray-400); margin-top: .15rem; }
    .claim-amount { font-size: 1.25rem; font-weight: 800; color: var(--primary); text-align: right; white-space: nowrap; }
    .status-badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .2rem .6rem; border-radius: 100px; }
    .claim-note { font-size: .82rem; color: var(--gray-600); background: #f8f9fa; border-radius: 6px; padding: .55rem .75rem; margin-top: .6rem; border-left: 3px solid var(--gray-300); }
    .flag-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #f59e0b; margin-right: .35rem; vertical-align: middle; title: 'Flagged'; }
    .receipt-link { font-size: .78rem; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: .25rem; margin-top: .4rem; }
    .receipt-link:hover { text-decoration: underline; }
    .empty-state { text-align: center; padding: 3rem; color: var(--gray-400); background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; }
    .new-btn { padding: .5rem 1rem; font-size: .88rem; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>My Claims</h1>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <a href="prod-claim-new.php" class="btn btn-primary new-btn">+ New Claim</a>
      <a class="back-link" href="prod-dashboard.php">← Pro-D Portal</a>
    </div>
  </div>

  <div class="filter-tabs">
    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $lbl): ?>
    <a href="prod-claims.php?status=<?= $val ?>" class="tab <?= $filter === $val ? 'active' : '' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$claims): ?>
  <div class="empty-state">
    <p>No <?= $filter !== 'all' ? $filter . ' ' : '' ?>claims yet.</p>
    <?php if ($filter === 'all'): ?>
    <a href="prod-claim-new.php" class="btn btn-primary" style="margin-top:.75rem;padding:.5rem 1rem;font-size:.88rem;">Submit your first claim</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="claims-list">
    <?php foreach ($claims as $c): ?>
    <?php
      $st  = $c['status'];
      $col = $statusColor[$st] ?? '#555';
      $bg  = $statusBg[$st]    ?? '#f8f9fa';
    ?>
    <div class="claim-card">
      <div class="claim-top">
        <div>
          <div class="claim-title">
            <?php if ($c['extraction_flag']): ?><span class="flag-dot" title="AI flagged: <?= htmlspecialchars($c['extraction_flag']) ?>"></span><?php endif; ?>
            <?= htmlspecialchars($catLabel[$c['category']] ?? $c['category']) ?>
            <?php if ($c['extracted_vendor']): ?> — <?= htmlspecialchars($c['extracted_vendor']) ?><?php endif; ?>
          </div>
          <div class="claim-meta">
            <?= date('M j, Y', strtotime($c['expense_date'])) ?>
            &nbsp;·&nbsp; Submitted <?= date('M j', strtotime($c['created_at'])) ?>
            <?php if ($c['extraction_flag']): ?>&nbsp;·&nbsp; <span style="color:#d97706;font-weight:600;">AI flag: <?= htmlspecialchars($c['extraction_flag']) ?></span><?php endif; ?>
          </div>
        </div>
        <div style="text-align:right;">
          <div class="claim-amount">$<?= number_format($c['amount_claimed'], 2) ?></div>
          <span class="status-badge" style="background:<?= $bg ?>;color:<?= $col ?>;margin-top:.3rem;display:inline-block;">
            <?= $statusLabel[$st] ?? $st ?>
          </span>
        </div>
      </div>

      <?php if ($c['description']): ?>
      <div style="font-size:.83rem;color:var(--gray-600);"><?= htmlspecialchars($c['description']) ?></div>
      <?php endif; ?>

      <?php if ($c['reviewer_note']): ?>
      <div class="claim-note"><strong>Reviewer note:</strong> <?= htmlspecialchars($c['reviewer_note']) ?></div>
      <?php endif; ?>

      <?php if ($c['receipt_path']): ?>
      <a class="receipt-link" href="prod-receipt.php?id=<?= $c['id'] ?>" target="_blank">
        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        View receipt
      </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
