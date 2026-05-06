<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();

// Role checks
$isExec      = prodIsExec($member['email']);
$isTreasurer = prodIsTreasurer($member['email']); // exec also passes
$isSiteRep   = prodIsSiteRep($member['email']);
$siteRepSchoolId   = $isSiteRep ? prodSiteRepSchoolId($member['email']) : null;
$siteRepSchoolName = $isSiteRep ? prodSiteRepSchoolName($member['email']) : null;

// Must have at least one elevated role
if (!$isExec && !$isTreasurer && !$isSiteRep) {
    header('Location: prod-dashboard.php');
    exit;
}

// What queues to show
// Phase 1 (initial approval of days + tentative amount): exec + site_rep
// Phase 2 (final financial claim review): exec + treasurer
$showPhase1 = $isExec || $isSiteRep;
$showPhase2 = $isExec || $isTreasurer;

$notice = null;

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action'] ?? '';
    $id    = (int)($_POST['id'] ?? 0);
    $phase = $_POST['phase']  ?? '1';   // '1' = initial, '2' = final
    $note  = trim($_POST['note'] ?? '');

    if ($id > 0 && in_array($act, ['approve', 'reject'])) {
        $status = $act === 'approve' ? 'approved' : 'rejected';
        $now    = date('Y-m-d H:i:s');

        if ($phase === '2' && $showPhase2) {
            // Final financial review
            getDB()->prepare("UPDATE prod_requests SET
                final_status=?, final_reviewed_by=?, final_reviewed_at=?, final_reviewer_note=?
                WHERE id=?")
               ->execute([$status, $member['email'], $now, $note ?: null, $id]);
            $notice = $act === 'approve' ? '✓ Final claim approved.' : '✓ Final claim rejected.';
        } elseif ($phase === '1' && $showPhase1) {
            // Initial approval
            getDB()->prepare("UPDATE prod_requests SET
                status=?, reviewed_by=?, reviewed_at=?, reviewer_note=?
                WHERE id=?")
               ->execute([$status, $member['email'], $now, $note ?: null, $id]);
            $notice = $act === 'approve' ? '✓ Request approved.' : '✓ Request rejected.';
        }
    }
}

// ── Load Phase 1 queue ────────────────────────────────────────────────────────
$phase1Requests = [];
if ($showPhase1) {
    if ($isSiteRep && !$isExec && $siteRepSchoolId) {
        $s = getDB()->prepare(
            "SELECT * FROM prod_requests WHERE school_id=?
             ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC"
        );
        $s->execute([$siteRepSchoolId]);
        $phase1Requests = $s->fetchAll();
    } else {
        $phase1Requests = getDB()->query(
            "SELECT * FROM prod_requests
             ORDER BY FIELD(status,'pending','approved','rejected'), created_at DESC"
        )->fetchAll();
    }
}

// ── Load Phase 2 queue ────────────────────────────────────────────────────────
$phase2Requests = [];
if ($showPhase2) {
    $phase2Requests = getDB()->query(
        "SELECT * FROM prod_requests
         WHERE status='approved' AND final_submitted=1
         ORDER BY FIELD(final_status,'pending','approved','rejected'), created_at DESC"
    )->fetchAll();
}

$catLabel = [
    'conference' => 'Conference / Workshop',
    'course'     => 'Course / Training',
    'materials'  => 'Materials / Resources',
    'travel'     => 'Travel',
    'other'      => 'Other',
];
$statusColor = ['pending' => '#d97706', 'approved' => '#166534', 'rejected' => '#991b1b'];
$statusBg    = ['pending' => '#fffbeb', 'approved' => '#f0fdf4', 'rejected' => '#fef2f2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pro-D Review Queue — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .admin-wrap { max-width: 1000px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .section-title { font-size: .9rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; letter-spacing: .06em; margin: 0 0 .85rem; display: flex; align-items: center; gap: .6rem; }
    .section-badge { background: #dc2626; color: #fff; font-size: .65rem; padding: .15rem .45rem; border-radius: 100px; font-weight: 800; }
    .section-desc { font-size: .78rem; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--gray-400); }
    .notice { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: .75rem 1rem; font-size: .88rem; color: #166534; margin-bottom: 1.25rem; }

    /* Cards */
    .req-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; margin-bottom: .85rem; overflow: hidden; }
    .req-card.flagged { border-color: #fde68a; }
    .req-card-body { padding: 1.25rem; }
    .req-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: .6rem; }
    .req-who { font-weight: 700; color: var(--gray-800); font-size: .95rem; }
    .req-email { font-weight: 400; color: var(--gray-400); font-size: .82rem; }
    .req-meta { font-size: .78rem; color: var(--gray-500); margin-top: .15rem; line-height: 1.6; }
    .req-desc { font-size: .85rem; color: var(--gray-700); margin-top: .4rem; }
    .req-amount { font-size: 1.2rem; font-weight: 800; color: var(--primary); white-space: nowrap; }
    .req-days   { font-size: 1rem; font-weight: 800; color: var(--primary); white-space: nowrap; }
    .status-badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .2rem .6rem; border-radius: 100px; }

    .flag-banner { background: #fffbeb; border: 1px solid #fde68a; border-radius: 7px; padding: .55rem .85rem; font-size: .8rem; color: #92400e; margin: .5rem 0; }
    .action-row { display: flex; gap: .6rem; align-items: flex-end; flex-wrap: wrap; padding: .9rem 1.25rem; border-top: 1px solid var(--gray-100); background: #fafafa; }
    .note-input { flex: 1; min-width: 180px; border: 1px solid var(--gray-300); border-radius: 7px; padding: .45rem .75rem; font-size: .85rem; font-family: inherit; }
    .note-input:focus { outline: none; border-color: var(--primary); }
    .btn-approve { background: #166534; color: #fff; border: none; border-radius: 7px; padding: .45rem .95rem; font-size: .85rem; font-weight: 700; cursor: pointer; }
    .btn-approve:hover { background: #14532d; }
    .btn-reject { background: #fff; color: #dc2626; border: 1px solid #fecaca; border-radius: 7px; padding: .45rem .95rem; font-size: .85rem; font-weight: 700; cursor: pointer; }
    .btn-reject:hover { background: #fef2f2; }
    .receipt-link { font-size: .8rem; color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: .25rem; }
    .receipt-link:hover { text-decoration: underline; }
    .reviewer-note { font-size: .8rem; color: var(--gray-500); margin-top: .4rem; }

    .empty-state { text-align: center; padding: 2.5rem; color: var(--gray-400); background: #fff; border: 1px solid var(--gray-200); border-radius: 10px; margin-bottom: 2rem; }
    .divider { border: none; border-top: 2px solid var(--gray-200); margin: 2.5rem 0; }

    /* Phase tab label */
    .phase-header { background: var(--gray-100); border-radius: 9px 9px 0 0; padding: .55rem 1rem; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: var(--gray-500); border: 1px solid var(--gray-200); border-bottom: none; }
    .phase-header.phase2 { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
  </style>
</head>
<body>
<div class="admin-wrap">

  <div class="portal-header">
    <div>
      <h1><?php
        if ($isExec)           echo 'Pro-D Review Queue';
        elseif ($isTreasurer)  echo 'Financial Claims — Review Queue';
        elseif ($isSiteRep)    echo 'Day Requests — ' . htmlspecialchars($siteRepSchoolName ?? 'My School');
      ?></h1>
      <div style="font-size:.82rem;color:var(--gray-500);margin-top:.2rem;">
        <?php
          $tags = [];
          if ($isExec)                    $tags[] = '<span style="color:#1e40af;font-weight:600;">Exec</span>';
          if ($isTreasurer && !$isExec)   $tags[] = '<span style="color:#166534;font-weight:600;">Treasurer</span>';
          if ($isSiteRep)                 $tags[] = '<span style="color:#7c3aed;font-weight:600;">Site Rep · ' . htmlspecialchars($siteRepSchoolName ?? '') . '</span>';
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

  <?php if ($showPhase1): ?>
  <!-- ══ Phase 1: Initial Request Approval ══════════════════════════════════ -->
  <?php
    $pendingCount = count(array_filter($phase1Requests, fn($r) => $r['status'] === 'pending'));
  ?>
  <p class="section-title" id="phase1">
    Phase 1 — Request Approvals
    <?php if ($pendingCount > 0): ?><span class="section-badge"><?= $pendingCount ?></span><?php endif; ?>
    <span class="section-desc">Days &amp; tentative funding · approve before the event</span>
  </p>

  <?php if (!$phase1Requests): ?>
  <div class="empty-state">No requests submitted yet.</div>
  <?php else: ?>
  <?php foreach ($phase1Requests as $r):
    $dates = json_decode($r['request_dates'], true) ?: [];
    $st    = $r['status'];
    $col   = $statusColor[$st] ?? '#555';
    $bg    = $statusBg[$st]    ?? '#f8f9fa';
    $isPending = ($st === 'pending');
  ?>
  <div class="req-card">
    <div class="req-card-body">
      <div class="req-top">
        <div style="min-width:0;">
          <div class="req-who"><?= htmlspecialchars($r['user_name']) ?> <span class="req-email">&lt;<?= htmlspecialchars($r['user_email']) ?>&gt;</span></div>
          <div class="req-meta">
            <?= htmlspecialchars($r['school'] ?? '—') ?>
            · <?= $catLabel[$r['category'] ?? ''] ?? ($r['category'] ?? '—') ?>
            · <?= count($dates) ?> date<?= count($dates) !== 1 ? 's' : '' ?>
              (<?= implode(', ', array_map(fn($d) => date('M j', strtotime($d)), $dates)) ?>)
            · <?= number_format($r['num_days'], 1) ?> day<?= $r['num_days'] != 1 ? 's' : '' ?>
            <?php if ($r['toc_needed']): ?> · <strong style="color:#d97706;">TOC needed</strong><?php endif; ?>
            · Submitted <?= date('M j, Y', strtotime($r['created_at'])) ?>
          </div>
          <div class="req-desc"><?= htmlspecialchars($r['activity_description']) ?></div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div class="req-days"><?= number_format($r['num_days'], 1) ?>d</div>
          <?php if ((float)$r['tentative_amount'] > 0): ?>
          <div class="req-amount">~$<?= number_format($r['tentative_amount'], 2) ?></div>
          <?php endif; ?>
          <span class="status-badge" style="background:<?= $bg ?>;color:<?= $col ?>;margin-top:.3rem;display:inline-block;"><?= ucfirst($st) ?></span>
        </div>
      </div>

      <?php if ($r['reviewer_note'] && !$isPending): ?>
      <div class="reviewer-note">Note: <?= htmlspecialchars($r['reviewer_note']) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($isPending): ?>
    <form method="POST" class="action-row">
      <input type="hidden" name="id"    value="<?= $r['id'] ?>">
      <input type="hidden" name="phase" value="1">
      <input type="text" name="note" class="note-input" placeholder="Optional note to teacher…">
      <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
      <button type="submit" name="action" value="reject"  class="btn-reject"
        onclick="return confirm('Reject this request from <?= htmlspecialchars(addslashes($r['user_name'])) ?>?')">Reject</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <?php endif; // showPhase1 ?>

  <?php if ($showPhase1 && $showPhase2): ?><hr class="divider" id="phase2"><?php endif; ?>

  <?php if ($showPhase2): ?>
  <!-- ══ Phase 2: Final Financial Claims ════════════════════════════════════ -->
  <?php
    $finalPendingCount = count(array_filter($phase2Requests, fn($r) => $r['final_status'] === 'pending'));
  ?>
  <p class="section-title" <?php if (!($showPhase1 && $showPhase2)): ?>id="phase2"<?php endif; ?>>
    Phase 2 — Final Claims
    <?php if ($finalPendingCount > 0): ?><span class="section-badge"><?= $finalPendingCount ?></span><?php endif; ?>
    <span class="section-desc">Receipts &amp; actual amounts · submitted after the event</span>
  </p>

  <?php if (!$phase2Requests): ?>
  <div class="empty-state">No final claims submitted yet.</div>
  <?php else: ?>
  <?php foreach ($phase2Requests as $r):
    $fst    = $r['final_status'] ?? 'pending';
    $col    = $statusColor[$fst] ?? '#555';
    $bg     = $statusBg[$fst]    ?? '#f8f9fa';
    $isPending = ($fst === 'pending');
    $dates  = json_decode($r['request_dates'], true) ?: [];
    $amtMatch = !$r['extracted_amount'] || abs((float)$r['extracted_amount'] - (float)$r['final_amount']) < 0.02;
  ?>
  <div class="req-card <?= ($r['extraction_flag'] || !$amtMatch) ? 'flagged' : '' ?>">
    <div class="req-card-body">
      <div class="req-top">
        <div style="min-width:0;">
          <div class="req-who"><?= htmlspecialchars($r['user_name']) ?> <span class="req-email">&lt;<?= htmlspecialchars($r['user_email']) ?>&gt;</span></div>
          <div class="req-meta">
            <?= htmlspecialchars($r['school'] ?? '—') ?>
            · <?= $catLabel[$r['category'] ?? ''] ?? ($r['category'] ?? '—') ?>
            · <?= implode(', ', array_map(fn($d) => date('M j', strtotime($d)), $dates)) ?>
            <?php if ($r['receipt_path']): ?>
            ·
            <a class="receipt-link" href="prod-receipt.php?table=request&id=<?= $r['id'] ?>" target="_blank">
              <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              Receipt
            </a>
            <?php endif; ?>
          </div>
          <?php if ($r['final_description']): ?>
          <div class="req-desc"><?= htmlspecialchars($r['final_description']) ?></div>
          <?php endif; ?>
          <?php if ($r['extracted_vendor']): ?>
          <div style="font-size:.75rem;color:var(--gray-400);margin-top:.2rem;">
            AI: <?= htmlspecialchars($r['extracted_vendor']) ?>
            <?php if ($r['extracted_date']): ?> · <?= date('M j, Y', strtotime($r['extracted_date'])) ?><?php endif; ?>
            <?php if ($r['extracted_amount']): ?> · $<?= number_format($r['extracted_amount'], 2) ?><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <div class="req-amount">$<?= number_format($r['final_amount'], 2) ?></div>
          <?php if ((float)$r['tentative_amount'] > 0 && abs((float)$r['tentative_amount'] - (float)$r['final_amount']) > 0.01): ?>
          <div style="font-size:.75rem;color:var(--gray-400);">est. $<?= number_format($r['tentative_amount'], 2) ?></div>
          <?php endif; ?>
          <span class="status-badge" style="background:<?= $bg ?>;color:<?= $col ?>;margin-top:.3rem;display:inline-block;"><?= ucfirst($fst) ?></span>
        </div>
      </div>

      <?php if ($r['extraction_flag'] || $r['extraction_concerns'] || !$amtMatch): ?>
      <div class="flag-banner">
        ⚠ <strong>Review flag:</strong>
        <?php if (!$amtMatch): ?>
          Receipt total ($<?= number_format($r['extracted_amount'], 2) ?>) differs from claimed ($<?= number_format($r['final_amount'], 2) ?>).
        <?php endif; ?>
        <?php if ($r['extraction_concerns']): ?>
          <?= htmlspecialchars($r['extraction_concerns']) ?>
        <?php endif; ?>
        <?php if ($r['extraction_flag'] && $amtMatch && !$r['extraction_concerns']): ?>
          Flagged: <?= htmlspecialchars($r['extraction_flag']) ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($r['final_reviewer_note'] && !$isPending): ?>
      <div class="reviewer-note">Note: <?= htmlspecialchars($r['final_reviewer_note']) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($isPending): ?>
    <form method="POST" class="action-row">
      <input type="hidden" name="id"    value="<?= $r['id'] ?>">
      <input type="hidden" name="phase" value="2">
      <input type="text" name="note" class="note-input" placeholder="Optional note to teacher…">
      <button type="submit" name="action" value="approve" class="btn-approve">Approve &amp; Pay</button>
      <button type="submit" name="action" value="reject"  class="btn-reject"
        onclick="return confirm('Reject this claim from <?= htmlspecialchars(addslashes($r['user_name'])) ?>?')">Reject</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <?php endif; // showPhase2 ?>

</div>
</body>
</html>
