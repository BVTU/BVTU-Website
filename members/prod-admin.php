<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();

// Determine what this user is allowed to see
$isExec      = prodIsExec($member['email']);
$isTreasurer = prodIsTreasurer($member['email']); // exec also passes this
$isSiteRep   = prodIsSiteRep($member['email']);
$siteRepSchoolId   = $isSiteRep ? prodSiteRepSchoolId($member['email']) : null;
$siteRepSchoolName = $isSiteRep ? prodSiteRepSchoolName($member['email']) : null;

// Must have at least one elevated role to access this page
if (!$isExec && !$isTreasurer && !$isSiteRep) {
    header('Location: prod-dashboard.php');
    exit;

}

$showClaims  = $isExec || $isTreasurer;
$showDays    = $isExec || $isSiteRep;

$notice = null;

// ── Handle approve / reject (claims) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act    = $_POST['action']  ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $note   = trim($_POST['note'] ?? '');
    $type   = $_POST['type']    ?? 'claim';  // 'claim' | 'day'

    if ($id > 0 && in_array($act, ['approve', 'reject'])) {
        $status = $act === 'approve' ? 'approved' : 'rejected';
        $now    = date('Y-m-d H:i:s');

        if ($type === 'claim') {
            getDB()->prepare("UPDATE prod_claims SET status=?, reviewed_by=?, reviewed_at=?, reviewer_note=? WHERE id=?")
                   ->execute([$status, $member['email'], $now, $note ?: null, $id]);
            $notice = $act === 'approve' ? '✓ Claim approved.' : '✓ Claim rejected.';
        } else {
            getDB()->prepare("UPDATE prod_day_requests SET status=?, reviewed_by=?, reviewed_at=?, reviewer_note=? WHERE id=?")
                   ->execute([$status, $member['email'], $now, $note ?: null, $id]);
            $notice = $act === 'approve' ? '✓ Day request approved.' : '✓ Day request rejected.';
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$pendingClaims = [];
if ($showClaims) {
    $pendingClaims = getDB()->query(
        "SELECT * FROM prod_claims ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC"
    )->fetchAll();
}

$pendingDays = [];
if ($showDays) {
    if ($isSiteRep && !$isExec && $siteRepSchoolId) {
        // Site rep: only their school's requests
        $s = getDB()->prepare(
            "SELECT * FROM prod_day_requests WHERE school_id=?
             ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC"
        );
        $s->execute([$siteRepSchoolId]);
        $pendingDays = $s->fetchAll();
    } else {
        $pendingDays = getDB()->query(
            "SELECT * FROM prod_day_requests ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC"
        )->fetchAll();
    }
}

$catLabel = ['conference'=>'Conference','course'=>'Course','materials'=>'Materials','travel'=>'Travel','other'=>'Other'];
$statusColor = ['pending'=>'#d97706','approved'=>'#166534','rejected'=>'#991b1b','flagged'=>'#7c3aed'];
$statusBg    = ['pending'=>'#fffbeb','approved'=>'#f0fdf4','rejected'=>'#fef2f2','flagged'=>'#f5f3ff'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pro-D Admin — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .admin-wrap { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .section-title { font-size: .9rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .06em; margin: 0 0 .85rem; }
    .notice { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }

    /* Claim cards */
    .claim-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; padding: 1.25rem; margin-bottom: .85rem; }
    .claim-card.flagged { border-color: #fde68a; }
    .claim-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: .75rem; }
    .claim-who { font-weight: 700; color: var(--gray-800); font-size: .95rem; }
    .claim-meta { font-size: .78rem; color: var(--gray-500); margin-top: .15rem; }
    .claim-amount { font-size: 1.25rem; font-weight: 800; color: var(--primary); white-space: nowrap; }
    .status-badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .2rem .6rem; border-radius: 100px; }
    .flag-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: 7px; padding: .55rem .85rem; font-size: .8rem; color: #92400e; margin-bottom: .75rem; }
    .action-row { display: flex; gap: .6rem; align-items: flex-end; flex-wrap: wrap; }
    .note-input { flex: 1; min-width: 200px; border: 1px solid var(--gray-300); border-radius: 7px; padding: .45rem .75rem; font-size: .85rem; font-family: inherit; }
    .note-input:focus { outline: none; border-color: var(--primary); }
    .btn-approve { background: #166534; color: #fff; border: none; border-radius: 7px; padding: .45rem .95rem; font-size: .85rem; font-weight: 700; cursor: pointer; }
    .btn-approve:hover { background: #14532d; }
    .btn-reject { background: #fff; color: #dc2626; border: 1px solid #fecaca; border-radius: 7px; padding: .45rem .95rem; font-size: .85rem; font-weight: 700; cursor: pointer; }
    .btn-reject:hover { background: #fef2f2; }
    .receipt-link { font-size: .8rem; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: .25rem; }
    .receipt-link:hover { text-decoration: underline; }
    .empty-state { text-align: center; padding: 2.5rem; color: var(--gray-400); background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; margin-bottom: 2rem; }
    .divider { border: none; border-top: 2px solid var(--gray-200); margin: 2.5rem 0; }
  </style>
</head>
<body>
<div class="admin-wrap">

  <div class="portal-header">
    <div>
      <h1><?php
        if ($isExec)           echo 'Pro-D Administration';
        elseif ($isTreasurer)  echo 'Financial Claims — Review Queue';
        elseif ($isSiteRep)    echo 'Day Requests — ' . htmlspecialchars($siteRepSchoolName ?? 'My School');
      ?></h1>
      <div style="font-size:.82rem;color:var(--gray-500);margin-top:.2rem;">
        <?php
          $tags = [];
          if ($isExec)      $tags[] = '<span style="color:#1e40af;font-weight:600;">Exec</span>';
          if ($isTreasurer && !$isExec) $tags[] = '<span style="color:#166534;font-weight:600;">Treasurer</span>';
          if ($isSiteRep)   $tags[] = '<span style="color:#7c3aed;font-weight:600;">Site Rep</span>';
          echo implode(' · ', $tags);
        ?>
      </div>
    </div>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <?php if ($isExec): ?>
      <a href="prod-manage.php" class="btn btn-outline" style="padding:.45rem .9rem;font-size:.85rem;">⚙ Manage Schools &amp; Roles</a>
      <?php endif; ?>
      <a class="back-link" href="prod-dashboard.php">← Pro-D Portal</a>
    </div>
  </div>

  <?php if ($notice): ?><div class="notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

  <?php if ($showClaims): ?>
  <!-- ── Financial Claims ──────────────────────────────────────────────────── -->
  <p class="section-title" id="claims">Financial Claims</p>

  <?php if (!$pendingClaims): ?>
  <div class="empty-state">No claims submitted yet.</div>
  <?php else: ?>
  <?php foreach ($pendingClaims as $c):
    $st  = $c['status'];
    $col = $statusColor[$st] ?? '#555';
    $bg  = $statusBg[$st]    ?? '#f8f9fa';
    $isPending = ($st === 'pending');
  ?>
  <div class="claim-card <?= $c['extraction_flag'] ? 'flagged' : '' ?>">
    <div class="claim-top">
      <div>
        <div class="claim-who"><?= htmlspecialchars($c['user_name']) ?> <span style="font-weight:400;color:var(--gray-400);font-size:.85rem;">&lt;<?= htmlspecialchars($c['user_email']) ?>&gt;</span></div>
        <div class="claim-meta">
          <?= htmlspecialchars($catLabel[$c['category']] ?? $c['category']) ?>
          &nbsp;·&nbsp; <?= date('M j, Y', strtotime($c['expense_date'])) ?>
          &nbsp;·&nbsp; Submitted <?= date('M j, g:ia', strtotime($c['created_at'])) ?>
          <?php if ($c['receipt_path']): ?>
          &nbsp;·&nbsp;
          <a class="receipt-link" href="prod-receipt.php?id=<?= $c['id'] ?>" target="_blank">
            <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Receipt
          </a>
          <?php endif; ?>
        </div>
        <?php if ($c['description']): ?>
        <div style="font-size:.83rem;color:var(--gray-600);margin-top:.3rem;"><?= htmlspecialchars($c['description']) ?></div>
        <?php endif; ?>
      </div>
      <div style="text-align:right;">
        <div class="claim-amount">$<?= number_format($c['amount_claimed'], 2) ?></div>
        <span class="status-badge" style="background:<?= $bg ?>;color:<?= $col ?>;margin-top:.3rem;display:inline-block;"><?= ucfirst($st) ?></span>
      </div>
    </div>

    <?php if ($c['extraction_flag']): ?>
    <div class="flag-banner">
      ⚠ <strong>AI flag:</strong> <?= htmlspecialchars($c['extraction_flag']) ?>
      <?php if ($c['extracted_amount'] && abs($c['extracted_amount'] - $c['amount_claimed']) > 0.01): ?>
        — Receipt shows <strong>$<?= number_format($c['extracted_amount'], 2) ?></strong>, claimed <strong>$<?= number_format($c['amount_claimed'], 2) ?></strong>
      <?php endif; ?>
      <?php if ($c['extraction_concerns']): ?>
        — <?= htmlspecialchars($c['extraction_concerns']) ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($c['reviewer_note'] && !$isPending): ?>
    <div style="font-size:.8rem;color:var(--gray-500);margin-bottom:.6rem;">Note: <?= htmlspecialchars($c['reviewer_note']) ?></div>
    <?php endif; ?>

    <?php if ($isPending): ?>
    <form method="POST" class="action-row">
      <input type="hidden" name="id"   value="<?= $c['id'] ?>">
      <input type="hidden" name="type" value="claim">
      <input type="text" name="note" class="note-input" placeholder="Optional reviewer note…">
      <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
      <button type="submit" name="action" value="reject"  class="btn-reject"
        onclick="return confirm('Reject this claim from <?= htmlspecialchars($c['user_name']) ?>?')">Reject</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php endif; // showClaims ?>

  <?php if ($showClaims && $showDays): ?><hr class="divider" id="day-requests"><?php endif; ?>

  <?php if ($showDays): ?>
  <!-- ── Day Requests ──────────────────────────────────────────────────────── -->
  <p class="section-title">
    Release Day Requests
    <?php if ($isSiteRep && !$isExec): ?>— <?= htmlspecialchars($siteRepSchoolName ?? '') ?><?php endif; ?>
  </p>

  <?php if (!$pendingDays): ?>
  <div class="empty-state">No day requests submitted yet.</div>
  <?php else: ?>
  <?php foreach ($pendingDays as $r):
    $dates = json_decode($r['request_dates'], true) ?: [];
    $st  = $r['status'];
    $col = $statusColor[$st] ?? '#555';
    $bg  = $statusBg[$st]    ?? '#f8f9fa';
    $isPending = ($st === 'pending');
  ?>
  <div class="claim-card">
    <div class="claim-top">
      <div>
        <div class="claim-who"><?= htmlspecialchars($r['user_name']) ?></div>
        <div class="claim-meta">
          <?= htmlspecialchars($r['school'] ?? '—') ?>
          &nbsp;·&nbsp; <?= implode(', ', array_map(fn($d) => date('M j', strtotime($d)), $dates)) ?>
          &nbsp;·&nbsp; Submitted <?= date('M j', strtotime($r['created_at'])) ?>
          <?php if ($r['toc_needed']): ?>&nbsp;·&nbsp; <strong style="color:#d97706;">TOC needed</strong><?php endif; ?>
        </div>
        <div style="font-size:.83rem;color:var(--gray-600);margin-top:.3rem;"><?= htmlspecialchars($r['activity_description']) ?></div>
      </div>
      <div style="text-align:right;">
        <div class="claim-amount"><?= number_format($r['num_days'], 1) ?> day<?= $r['num_days'] != 1 ? 's' : '' ?></div>
        <span class="status-badge" style="background:<?= $bg ?>;color:<?= $col ?>;margin-top:.3rem;display:inline-block;"><?= ucfirst($st) ?></span>
      </div>
    </div>

    <?php if ($isPending): ?>
    <form method="POST" class="action-row">
      <input type="hidden" name="id"   value="<?= $r['id'] ?>">
      <input type="hidden" name="type" value="day">
      <input type="text" name="note" class="note-input" placeholder="Optional reviewer note…">
      <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
      <button type="submit" name="action" value="reject"  class="btn-reject"
        onclick="return confirm('Reject this day request from <?= htmlspecialchars($r['user_name']) ?>?')">Reject</button>
    </form>
    <?php endif; ?>

    <?php if ($r['reviewer_note'] && !$isPending): ?>
    <div style="font-size:.8rem;color:var(--gray-500);margin-top:.4rem;">Note: <?= htmlspecialchars($r['reviewer_note']) ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; // showDays ?>

</div>
</body>
</html>
