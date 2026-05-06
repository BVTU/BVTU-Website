<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/prod-db.php';
requireLogin();

$member = getMember();
prodEnsureTables();

$db = getDB();
$s  = $db->prepare("SELECT * FROM prod_requests WHERE user_email=? ORDER BY created_at DESC");
$s->execute([$member['email']]);
$requests = $s->fetchAll();

$catLabels = [
    'conference' => 'Conference / Workshop',
    'course'     => 'Course / Training',
    'materials'  => 'Materials / Resources',
    'travel'     => 'Travel',
    'other'      => 'Other',
];

$statusLabel = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
$statusColor = ['pending' => '#d97706', 'approved' => '#166534', 'rejected' => '#991b1b'];
$statusBg    = ['pending' => '#fffbeb', 'approved' => '#f0fdf4', 'rejected' => '#fef2f2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Requests — BVTU Pro-D</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .portal-wrap { max-width: 780px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .portal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem; }
    .portal-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }

    .new-btn { display: inline-flex; align-items: center; gap: .4rem; background: var(--primary); color: #fff; font-size: .85rem; font-weight: 700; border-radius: 8px; padding: .5rem 1rem; text-decoration: none; transition: background .15s; }
    .new-btn:hover { background: var(--primary-dk); color: #fff; }

    .req-list { display: flex; flex-direction: column; gap: 1rem; }

    .req-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; overflow: hidden; }
    .req-card-head { padding: 1.1rem 1.35rem; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .req-card-title { font-weight: 700; font-size: .97rem; color: var(--gray-800); margin-bottom: .25rem; }
    .req-card-meta { font-size: .78rem; color: var(--gray-500); }
    .req-card-meta span + span::before { content: '·'; margin: 0 .4rem; }

    .status-badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: .22rem .65rem; border-radius: 100px; white-space: nowrap; }

    /* Phase timeline */
    .phase-strip { border-top: 1px solid var(--gray-100); padding: .9rem 1.35rem; display: flex; align-items: stretch; gap: 0; background: #fafafa; }
    .phase { flex: 1; position: relative; }
    .phase + .phase { padding-left: 1.25rem; }
    .phase + .phase::before { content: '›'; position: absolute; left: .25rem; top: 50%; transform: translateY(-50%); color: var(--gray-300); font-size: 1.1rem; }
    .phase-label { font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-400); margin-bottom: .2rem; }
    .phase-val { font-size: .82rem; font-weight: 700; }
    .phase-val.done { color: #166534; }
    .phase-val.pending { color: #d97706; }
    .phase-val.rejected { color: #991b1b; }
    .phase-val.waiting { color: var(--gray-400); }

    /* Final claim CTA */
    .final-cta { border-top: 2px solid #bbf7d0; background: #f0fdf4; padding: .85rem 1.35rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .final-cta p { font-size: .85rem; color: #166534; font-weight: 600; margin: 0; }
    .final-cta a { display: inline-flex; align-items: center; gap: .35rem; background: #166534; color: #fff; font-size: .83rem; font-weight: 700; border-radius: 7px; padding: .45rem .9rem; text-decoration: none; white-space: nowrap; }
    .final-cta a:hover { background: #14532d; }

    /* Rejected / note */
    .reviewer-note { border-top: 1px solid var(--gray-100); padding: .75rem 1.35rem; font-size: .82rem; color: var(--gray-600); background: #f8f9fa; }
    .reviewer-note strong { color: var(--gray-700); }

    .empty-state { text-align: center; padding: 3rem 1rem; color: var(--gray-400); background: #fff; border: 1px solid var(--gray-200); border-radius: 12px; }
    .empty-state p { margin: .5rem 0; }
    .empty-state a { display: inline-block; margin-top: 1rem; }
  </style>
</head>
<body>
<div class="portal-wrap">

  <div class="portal-header">
    <h1>My Requests</h1>
    <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <a href="prod-request-new.php" class="new-btn">+ New Request</a>
      <a class="back-link" href="prod-dashboard.php">← Pro-D Portal</a>
    </div>
  </div>

  <?php if (!$requests): ?>
  <div class="empty-state">
    <p style="font-size:1rem;font-weight:700;color:var(--gray-600);">No requests yet</p>
    <p>Submit your first Pro-D request to get started.</p>
    <a href="prod-request-new.php" class="btn btn-primary">Submit a Request</a>
  </div>
  <?php else: ?>
  <div class="req-list">
    <?php foreach ($requests as $r):
      $dates    = json_decode($r['request_dates'], true) ?: [];
      $st       = $r['status'];
      $fst      = $r['final_status'] ?? '';
      $sCol     = $statusColor[$st]  ?? '#555';
      $sBg      = $statusBg[$st]     ?? '#f8f9fa';

      // Determine if the "Submit Final Claim" CTA should show:
      // request approved + event presumably happened + no final submitted yet
      $canFinal = ($st === 'approved' && !$r['final_submitted']);

      // Phase 1 display
      $p1label = $statusLabel[$st] ?? ucfirst($st);
      $p1class = $st === 'approved' ? 'done' : ($st === 'rejected' ? 'rejected' : 'pending');

      // Phase 2 display
      if (!$r['final_submitted']) {
          $p2label = $st === 'approved' ? 'Ready to submit' : 'Awaiting approval';
          $p2class = $st === 'approved' ? 'pending' : 'waiting';
      } else {
          $p2label = $fst === 'approved' ? 'Approved' : ($fst === 'rejected' ? 'Rejected' : 'Under review');
          $p2class = $fst === 'approved' ? 'done'     : ($fst === 'rejected' ? 'rejected'  : 'pending');
      }
    ?>
    <div class="req-card">
      <div class="req-card-head">
        <div style="min-width:0;">
          <div class="req-card-title"><?= htmlspecialchars($r['activity_description']) ?></div>
          <div class="req-card-meta">
            <span><?= htmlspecialchars($r['school'] ?? '—') ?></span>
            <span><?= $catLabels[$r['category'] ?? ''] ?? ($r['category'] ?? '—') ?></span>
            <span>Submitted <?= date('M j, Y', strtotime($r['created_at'])) ?></span>
          </div>
        </div>
        <div style="text-align:right;flex-shrink:0;">
          <span class="status-badge" style="background:<?= $sBg ?>;color:<?= $sCol ?>;"><?= $p1label ?></span>
          <div style="font-size:.75rem;color:var(--gray-400);margin-top:.3rem;">
            <?= number_format($r['num_days'], 1) ?> day<?= $r['num_days'] != 1 ? 's' : '' ?>
            <?php if ((float)$r['tentative_amount'] > 0): ?>
              · ~$<?= number_format($r['tentative_amount'], 2) ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Phase strip -->
      <div class="phase-strip">
        <div class="phase">
          <div class="phase-label">① Initial Approval</div>
          <div class="phase-val <?= $p1class ?>"><?= $p1label ?></div>
          <?php if ($r['reviewed_at']): ?>
            <div style="font-size:.7rem;color:var(--gray-400);"><?= date('M j', strtotime($r['reviewed_at'])) ?></div>
          <?php endif; ?>
        </div>
        <div class="phase">
          <div class="phase-label">② Final Claim</div>
          <div class="phase-val <?= $p2class ?>"><?= $p2label ?></div>
          <?php if ($r['final_reviewed_at']): ?>
            <div style="font-size:.7rem;color:var(--gray-400);"><?= date('M j', strtotime($r['final_reviewed_at'])) ?></div>
          <?php endif; ?>
        </div>
        <?php if (count($dates) > 0): ?>
        <div class="phase">
          <div class="phase-label">Dates</div>
          <div style="font-size:.78rem;color:var(--gray-600);">
            <?php
              $fmt   = array_map(fn($d) => date('M j', strtotime($d)), $dates);
              $extra = count($fmt) > 3 ? count($fmt) - 3 : 0;
              $shown = $extra ? array_slice($fmt, 0, 3) : $fmt;
              echo htmlspecialchars(implode(', ', $shown));
              if ($extra) echo ' <span style="color:var(--gray-400)">+' . $extra . ' more</span>';
            ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($canFinal): ?>
      <div class="final-cta">
        <p>Event approved — submit your final claim with receipt when ready.</p>
        <a href="prod-request-final.php?id=<?= $r['id'] ?>">Submit Final Claim →</a>
      </div>
      <?php endif; ?>

      <?php if ($r['reviewer_note'] && $st !== 'pending'): ?>
      <div class="reviewer-note"><strong>Reviewer note:</strong> <?= htmlspecialchars($r['reviewer_note']) ?></div>
      <?php endif; ?>

      <?php if ($r['final_reviewer_note'] && $fst && $fst !== 'pending'): ?>
      <div class="reviewer-note"><strong>Financial reviewer note:</strong> <?= htmlspecialchars($r['final_reviewer_note']) ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
