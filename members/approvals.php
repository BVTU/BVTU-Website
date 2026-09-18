<?php
/**
 * approvals.php — one place for anyone who signs or pays.
 *
 * The two review queues and the payment ledger were only reachable sideways
 * from each other, several of those links led to pages the visitor could not
 * open, and nothing explained what each was for. This answers two questions:
 * what needs me, and what did we pay.
 *
 * Counts are per role, since the two expense systems sign in opposite orders:
 *   Member claim  — President signs first, then Treasurer, either one pays.
 *   LP voucher    — Treasurer signs first, then VP, Treasurer pays.
 *                   (The President is the claimant, so cannot sign.)
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exp-db.php';
require_once __DIR__ . '/lp-db.php';

requireLogin();
$member = getMember();
expEnsureTables();
expBatchEnsureTables();
lpEnsureTables();
lpEnsureApprovalColumns();

$email = $member['email'];

if (!expCanReview($email) && !lpCanReview($email)) {
    header('Location: dashboard.php');
    exit;
}

// What this person may act on, by role
$claimSign1 = expIsEligibleSigner1($email);   // President
$claimSign2 = expIsEligibleSigner2($email);   // Treasurer
$claimPay   = expCanMarkPaid($email);         // either
$lpSign1    = lpCanSign1($email);             // Treasurer
$lpSign2    = lpCanSign2($email);             // VP

// Each entry: label, count, where it goes, and why it is waiting on you
$actions = [];

if ($claimSign1) $actions[] = [
    'n'     => expBatchPendingCount('pending'),
    'label' => 'Member claims awaiting your approval',
    'sub'   => 'You authorise the spend; the Treasurer then verifies and pays.',
    'href'  => 'exp-claim-review.php',
];
if ($claimSign2) $actions[] = [
    'n'     => expBatchPendingCount('signer1_approved'),
    'label' => 'Member claims awaiting your signature',
    'sub'   => 'The President has approved these. Yours is the second signature.',
    'href'  => 'exp-claim-review.php',
];
if ($lpSign1) $actions[] = [
    'n'     => lpCountByStatus('submitted'),
    'label' => 'LP vouchers awaiting your approval',
    'sub'   => 'First signature on the President&rsquo;s own expenses.',
    'href'  => 'lp-review.php',
];
if ($lpSign2) $actions[] = [
    'n'     => lpCountByStatus('treasurer_approved'),
    'label' => 'LP vouchers awaiting your signature',
    'sub'   => 'The Treasurer has approved these. Yours is the second signature.',
    'href'  => 'lp-review.php',
];
if ($claimPay) $actions[] = [
    'n'     => expBatchPendingCount('signer2_approved'),
    'label' => 'Member claims ready for e-transfer',
    'sub'   => 'Both signatures are in. Send the transfer, then record it.',
    'href'  => 'exp-claim-review.php',
];
if ($lpSign1) $actions[] = [
    'n'     => lpCountByStatus('vp_approved'),
    'label' => 'LP vouchers ready for e-transfer',
    'sub'   => 'Both signatures are in. Send the transfer, then record it.',
    'href'  => 'lp-review.php',
];

$totalWaiting = array_sum(array_column($actions, 'n'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Approvals &amp; Payments — BVTU</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="icon" href="../favicon.ico">
  <style>
    body { background: #f4f6f8; }
    .wrap { max-width: 820px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
    .page-header { margin-bottom: 1.5rem; }
    .page-header h1 { font-size: 1.35rem; font-weight: 800; color: var(--gray-800); margin: .3rem 0 0; }
    .back-link { font-size: .85rem; color: var(--primary); text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
    .sec-head { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em;
                color: var(--gray-400); margin: 2rem 0 .75rem; }
    .all-clear { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
                 padding: 1rem 1.25rem; font-size: .9rem; color: #166534; }
    .row { display: flex; align-items: center; gap: 1rem; background: #fff;
           border: 1px solid var(--gray-200); border-radius: 10px; padding: .9rem 1.1rem;
           margin-bottom: .6rem; text-decoration: none; transition: border-color .12s, transform .12s; }
    .row:hover { border-color: var(--primary); transform: translateX(3px); text-decoration: none; }
    .row .count { font-size: 1.5rem; font-weight: 800; min-width: 2.2rem; text-align: center; }
    .row .count.zero { color: var(--gray-300); }
    .row .count.due  { color: var(--primary); }
    .row .label { font-weight: 700; color: var(--gray-800); font-size: .93rem; }
    .row .sub   { font-size: .8rem; color: var(--gray-500); margin-top: .15rem; }
    .row .go    { margin-left: auto; color: var(--gray-300); font-size: 1.1rem; }
    .row:hover .go { color: var(--primary); }
    .ref-card { background: #fff; border: 1px solid var(--gray-200); border-radius: 10px;
                padding: .9rem 1.1rem; margin-bottom: .6rem; }
    .ref-card .label { font-weight: 700; color: var(--gray-800); font-size: .93rem; }
    .ref-card .sub   { font-size: .8rem; color: var(--gray-500); margin-top: .15rem; }
    .ref-card a { font-size: .85rem; font-weight: 700; color: var(--primary); text-decoration: none; }
    .ref-card a:hover { text-decoration: underline; }
    .muted-note { font-size: .8rem; color: var(--gray-400); font-style: italic; }
  </style>
</head>
<body>
<div class="wrap">

  <div class="page-header">
    <a class="back-link" href="dashboard.php">&#x2190; Dashboard</a>
    <h1>Approvals &amp; Payments</h1>
  </div>

  <div class="sec-head">
    <?= $totalWaiting > 0 ? 'Waiting on you (' . $totalWaiting . ')' : 'Waiting on you' ?>
  </div>

  <?php if (!$actions): ?>
    <div class="all-clear">You don&rsquo;t hold a signing role, so nothing routes to you here.</div>
  <?php elseif ($totalWaiting === 0): ?>
    <div class="all-clear">&#x2713; Nothing needs your signature right now.</div>
  <?php endif; ?>

  <?php foreach ($actions as $a): if ($a['n'] === 0) continue; ?>
  <a class="row" href="<?= htmlspecialchars($a['href']) ?>">
    <span class="count due"><?= $a['n'] ?></span>
    <span>
      <span class="label"><?= $a['label'] ?></span>
      <span class="sub"><?= $a['sub'] ?></span>
    </span>
    <span class="go">&rarr;</span>
  </a>
  <?php endforeach; ?>

  <!-- Queues are listed again in full so it is clear what exists and what each
       one is for, even when a queue happens to be empty. -->
  <div class="sec-head">The queues</div>

  <?php if (expCanReview($email)): ?>
  <div class="ref-card">
    <div class="label">Expense Claim Review</div>
    <div class="sub">
      Reimbursements claimed by members. Signed by the President, then the Treasurer,
      and paid by either.
    </div>
    <a href="exp-claim-review.php">Open &rarr;</a>
  </div>
  <?php endif; ?>

  <?php if (lpCanReview($email)): ?>
  <div class="ref-card">
    <div class="label">LP Voucher Review</div>
    <div class="sub">
      The President&rsquo;s own expenses. Signed by the Treasurer, then the Vice-President,
      and paid by the Treasurer &mdash; the President cannot sign their own.
    </div>
    <a href="lp-review.php">Open &rarr;</a>
  </div>
  <?php endif; ?>

  <div class="sec-head">Payment history</div>
  <div class="ref-card">
    <div class="label">Payment Ledger</div>
    <div class="sub">
      Every paid claim and voucher in one list, for reconciling against e-transfers.
    </div>
    <span class="muted-note">Paused while these tools are reorganised.</span>
  </div>

</div>
</body>
</html>
