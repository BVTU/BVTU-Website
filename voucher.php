<?php
/**
 * voucher.php — one expense voucher, read-only, for someone with a share link.
 *
 * Laid out the way a reviewer works through paper: a checklist of the lines
 * first, then one page per line with that line's own details printed above its
 * receipt. Nothing to click, nothing to remember between pages, and a gap
 * where a receipt should be is a page of its own rather than silence.
 */
require_once __DIR__ . '/members/db.php';
require_once __DIR__ . '/members/lp-db.php';
require_once __DIR__ . '/members/lp-share.php';

$share = lpShareByToken((string)($_GET['t'] ?? ''));
$state = lpShareState($share);

$voucher = $state['ok'] ? lpGetVoucher((int)$share['voucher_id']) : null;
if ($state['ok'] && !$voucher) $state = ['ok' => false, 'why' => 'notfound'];

$expenses = [];
if ($state['ok']) {
    $expenses = lpGetExpenses((int)$voucher['id']);
    lpShareTouch((int)$share['id']);
}

header('X-Robots-Tag: noindex, nofollow');
// Never cached: the dialog promises that removing access stops the link
// working, and a cached copy would keep answering after it was withdrawn.
header('Cache-Control: private, no-store, max-age=0');

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$money = function ($v) { return '$' . number_format((float)$v, 2); };

// Receipts the browser can show. HEIC comes off an iPhone that did not convert
// on upload, and renders in Safari and almost nowhere else, so it is offered
// as a file to open rather than shown as a broken image.
$inlineExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$extOf = function (?string $f): string {
    return strtolower(pathinfo((string)$f, PATHINFO_EXTENSION));
};

$total      = 0.0;
$categories = ['travel_amt' => 'Travel', 'meals' => 'Meals', 'gifts' => 'Gifts',
               'misc' => 'Misc', 'office' => 'Office', 'phone' => 'Phone'];
$catTotals  = array_fill_keys(array_keys($categories), 0.0);
foreach ($expenses as $e) {
    $total += lpRowTotal($e);
    foreach ($categories as $col => $_) $catTotals[$col] += (float)$e[$col];
}

// Only the lines that have something to show get a page of their own.
$pages = [];
foreach ($expenses as $i => $e) {
    $pages[] = ['n' => $i + 1, 'e' => $e];
}
$pageCount = count($pages) + 2;   // checklist + lines + sign-off
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= $state['ok'] ? $h(($voucher['voucher_number'] ? 'Voucher ' . $voucher['voucher_number'] . ' — ' : '') . $voucher['name']) : 'Review link' ?> — BVTU</title>
<style>
  :root {
    --ink:#1a1a1a; --soft:#5a5a5a; --line:#d6d6d2; --bg:#f4f4f1;
    --flag:#8a4b00; --flagbg:#fff6e8;
  }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--ink);
         font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
         font-size:11pt; line-height:1.5; }

  /* A slim bar that stays put, like any document viewer. */
  .bar { position:sticky; top:0; z-index:10; background:#fff; border-bottom:1px solid var(--line);
         display:flex; align-items:center; gap:1rem; padding:.6rem 1.1rem; flex-wrap:wrap; }
  .bar .ttl { font-weight:700; font-size:.95rem; flex:1 1 12rem; min-width:0;
              overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .bar .ro  { font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
              color:var(--soft); border:1px solid var(--line); border-radius:4px; padding:.15rem .45rem; }
  .bar button { font:inherit; font-size:.85rem; font-weight:600; background:#1a6b35; color:#fff;
                border:0; border-radius:6px; padding:.4rem .9rem; cursor:pointer; }

  .sheet { width:8.5in; min-height:11in; background:#fff; margin:1.5rem auto; padding:.8in .75in;
           box-shadow:0 1px 5px rgba(0,0,0,.18); position:relative; }
  .sheet .foot { position:absolute; left:.75in; right:.75in; bottom:.45in;
                 border-top:1px solid var(--line); padding-top:.3rem;
                 font-size:8.5pt; color:var(--soft); display:flex; justify-content:space-between; }

  h1 { font-size:17pt; margin:0 0 .15rem; letter-spacing:-.01em; }
  .sub { color:var(--soft); font-size:10pt; margin:0 0 1.4rem; }
  h2 { font-size:12pt; margin:0 0 .8rem; }

  .meta { width:100%; border-collapse:collapse; margin-bottom:1.6rem; font-size:10pt; }
  .meta td { padding:.3rem 0; vertical-align:top; }
  .meta td.k { width:8.5rem; color:var(--soft); }

  table.lines { width:100%; border-collapse:collapse; font-size:10pt; }
  table.lines th { text-align:left; font-size:8.5pt; text-transform:uppercase; letter-spacing:.05em;
                   color:var(--soft); border-bottom:1.5px solid var(--ink); padding:.35rem .4rem; }
  table.lines td { padding:.45rem .4rem; border-bottom:1px solid var(--line); vertical-align:top; }
  table.lines td.n   { width:1.6rem; color:var(--soft); }
  table.lines td.tik { width:1.3rem; }
  table.lines td.amt { text-align:right; white-space:nowrap; font-variant-numeric:tabular-nums; }
  table.lines tr.sum td { border-bottom:0; border-top:1.5px solid var(--ink); font-weight:700;
                          padding-top:.6rem; }
  .box { display:inline-block; width:.62rem; height:.62rem; border:1.2px solid #777; border-radius:2px; }
  .nore { color:var(--flag); font-weight:600; }
  .nore.ok { color:var(--soft); font-weight:400; }

  /* One line, one page. */
  .lineno { font-size:8.5pt; text-transform:uppercase; letter-spacing:.08em; color:var(--soft); }
  .lined  { font-size:14pt; font-weight:700; margin:.1rem 0 .1rem; }
  .linew  { color:var(--soft); font-size:10pt; margin:0 0 1rem; }
  .claim  { display:flex; gap:2.2rem; align-items:baseline; flex-wrap:wrap;
            border-top:1px solid var(--line); border-bottom:1px solid var(--line);
            padding:.7rem 0; margin-bottom:1rem; }
  .claim .k { font-size:8.5pt; text-transform:uppercase; letter-spacing:.06em; color:var(--soft);
              display:block; }
  .claim .v { font-size:15pt; font-weight:700; font-variant-numeric:tabular-nums; }
  .claim .v.sm { font-size:11pt; font-weight:600; }
  .agree  { font-size:9.5pt; color:var(--soft); }
  .differ { background:var(--flagbg); border:1px solid #e8c89a; color:var(--flag);
            border-radius:5px; padding:.5rem .7rem; font-size:9.5pt; margin-bottom:1rem; }

  .shot { text-align:center; }
  .shot img { max-width:100%; max-height:6.4in; border:1px solid var(--line); }
  .nofile { border:1.5px dashed var(--line); border-radius:7px; padding:2.2rem 1.2rem;
            text-align:center; color:var(--soft); font-size:10pt; }
  .nofile strong { display:block; color:var(--ink); font-size:11pt; margin-bottom:.3rem; }
  .nofile a { color:#1a6b35; }

  .sign { display:flex; gap:2.5rem; margin-top:2.5rem; }
  .sign div { flex:1; }
  .sign .rule { border-bottom:1px solid var(--ink); height:2.2rem; }
  .sign .cap  { font-size:9pt; color:var(--soft); padding-top:.3rem; }

  .gone { max-width:34rem; margin:4rem auto; background:#fff; border:1px solid var(--line);
          border-radius:10px; padding:2rem; }
  .gone h1 { font-size:14pt; }

  @media (max-width: 9in) {
    .sheet { width:auto; min-height:0; margin:.8rem; padding:1.3rem 1.1rem 3rem; }
    .sheet .foot { left:1.1rem; right:1.1rem; bottom:1.1rem; }
    .shot img { max-height:none; }
  }

  @media print {
    body { background:#fff; }
    .bar { display:none; }
    .sheet { width:auto; min-height:0; margin:0; padding:0; box-shadow:none;
             page-break-after:always; }
    .sheet:last-child { page-break-after:auto; }
    .sheet .foot { position:fixed; bottom:.3in; left:0; right:0; }
    .shot img { max-height:6in; }
    @page { size: letter portrait; margin:.65in; }
  }
</style>
</head>
<body>

<?php if (!$state['ok']): ?>

  <div class="gone">
    <h1>
      <?php if ($state['why'] === 'expired'): ?>This review link has expired
      <?php elseif ($state['why'] === 'revoked'): ?>This review link has been withdrawn
      <?php else: ?>We could not find that review link<?php endif; ?>
    </h1>
    <p style="color:var(--soft);">
      <?php if ($state['why'] === 'expired' || $state['why'] === 'revoked'): ?>
        Review links are time-limited. Ask whoever sent it to share it again.
      <?php else: ?>
        The link may be incomplete — check that you copied the whole address from the email.
      <?php endif; ?>
    </p>
  </div>

<?php else: ?>

  <div class="bar">
    <span class="ttl"><?= $h($voucher['name']) ?></span>
    <span class="ro">Read only</span>
    <button onclick="window.print()">Save as PDF</button>
  </div>

  <!-- ── Sheet 1: the checklist ─────────────────────────────────────────── -->
  <div class="sheet">
    <h1>Expense Voucher<?= $voucher['voucher_number'] ? ' ' . $h($voucher['voucher_number']) : '' ?></h1>
    <p class="sub">Bulkley Valley Teachers' Union</p>

    <table class="meta">
      <tr><td class="k">Voucher</td><td><?= $h($voucher['name']) ?></td></tr>
      <tr><td class="k">Submitted by</td><td><?= $h($voucher['submitted_by']) ?></td></tr>
      <tr><td class="k">Submitted</td>
          <td><?= $voucher['submitted_at'] ? $h(date('F j, Y', strtotime($voucher['submitted_at']))) : '—' ?></td></tr>
      <tr><td class="k">Line items</td><td><?= count($expenses) ?></td></tr>
      <?php if (trim((string)$voucher['notes']) !== ''): ?>
      <tr><td class="k">Notes</td><td><?= nl2br($h($voucher['notes'])) ?></td></tr>
      <?php endif; ?>
    </table>

    <h2>Lines to check</h2>
    <table class="lines">
      <thead>
        <tr><th></th><th>#</th><th>Date</th><th>Description</th><th>Receipt</th><th style="text-align:right;">Amount</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $p): $e = $p['e'];
              $hasFile = trim((string)$e['receipt_path']) !== '';
              $rowKm   = (float)$e['travel_km'] > 0 ? (float)$e['travel_amt'] : 0.0;
              $isKm    = !$hasFile && (float)$e['travel_km'] > 0
                         && abs(lpRowTotal($e) - $rowKm) < 0.01; ?>
        <tr>
          <td class="tik"><span class="box"></span></td>
          <td class="n"><?= $p['n'] ?></td>
          <td style="white-space:nowrap;"><?= $e['expense_date'] ? $h(date('M j', strtotime($e['expense_date']))) : '—' ?></td>
          <td><?= $h($e['description']) ?></td>
          <td>
            <?php if ($hasFile): ?>attached
            <?php elseif ($isKm): ?><span class="nore ok">mileage — none needed</span>
            <?php else: ?><span class="nore">none attached</span><?php endif; ?>
          </td>
          <td class="amt"><?= $money(lpRowTotal($e)) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="sum">
          <td colspan="5">Total claimed</td>
          <td class="amt"><?= $money($total) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="foot"><span><?= $h($voucher['name']) ?></span><span>Page 1 of <?= $pageCount ?></span></div>
  </div>

  <!-- ── One sheet per line ─────────────────────────────────────────────── -->
  <?php foreach ($pages as $idx => $p): $e = $p['e'];
        $rowTotal = lpRowTotal($e);
        $file     = trim((string)$e['receipt_path']);
        $ext      = $extOf($file);
        // Mileage is claimed by distance, not against a receipt, so it is taken
        // out before the two totals are compared — otherwise every row that
        // mixes a drive with a purchase looks like a discrepancy.
        $kmPortion   = (float)$e['travel_km'] > 0 ? (float)$e['travel_amt'] : 0.0;
        $receiptPart = $rowTotal - $kmPortion;
        $scanTot  = isset($e['scan_total']) && $e['scan_total'] !== null ? (float)$e['scan_total'] : null;
        $differs  = $scanTot !== null && abs($scanTot - $receiptPart) >= 0.01;
        // "Mileage, no receipt needed" is only true when mileage is all there
        // is on the row. Anything else on it genuinely has no receipt.
        $isKm     = $file === '' && (float)$e['travel_km'] > 0 && abs($receiptPart) < 0.01; ?>
  <div class="sheet">
    <div class="lineno">Line <?= $p['n'] ?> of <?= count($pages) ?></div>
    <div class="lined"><?= $e['expense_date'] ? $h(date('F j, Y', strtotime($e['expense_date']))) : 'Date not given' ?></div>
    <p class="linew">
      <?= $h($e['description']) ?>
      <?php if ($e['grant_name']): ?> · <?= $h($e['grant_name']) ?><?php endif; ?>
      <?php if ($e['budget_line_name']): ?> · <?= $h($e['budget_line_name']) ?><?php endif; ?>
    </p>

    <div class="claim">
      <div><span class="k">Claimed</span><span class="v"><?= $money($rowTotal) ?></span></div>
      <?php if ($scanTot !== null && $kmPortion > 0): ?>
      <div><span class="k">Of that, non-mileage</span><span class="v sm"><?= $money($receiptPart) ?></span></div>
      <?php endif; ?>
      <?php if ($scanTot !== null): ?>
      <div><span class="k">Receipt reads</span><span class="v sm"><?= $money($scanTot) ?></span></div>
      <?php endif; ?>
      <?php if ((float)$e['travel_km'] > 0): ?>
      <div><span class="k">Distance</span><span class="v sm"><?= number_format((float)$e['travel_km'], 1) ?> km</span></div>
      <?php endif; ?>
    </div>

    <?php if ($differs): ?>
    <div class="differ">
      <strong>Worth a look.</strong> The amount claimed and the total read off the receipt
      are not the same. That is often fine — a split bill, a tip added by hand, or one
      receipt covering two lines — but it is the kind of thing worth confirming.
    </div>
    <?php elseif ($scanTot !== null): ?>
    <p class="agree">
      The total read off the receipt matches the amount claimed<?php
        if (!empty($e['scan_vendor'])) echo ', and the receipt is from ' . $h($e['scan_vendor']); ?>.
    </p>
    <?php endif; ?>

    <div class="shot">
      <?php if ($file !== '' && in_array($ext, $inlineExt, true)): ?>
        <img src="voucher-receipt.php?t=<?= $h($_GET['t'] ?? '') ?>&amp;f=<?= $h(rawurlencode($file)) ?>"
             alt="Receipt for line <?= $p['n'] ?>">
      <?php elseif ($file !== ''): ?>
        <div class="nofile">
          <strong>Receipt is a <?= $h(strtoupper($ext ?: 'file')) ?></strong>
          This kind of file cannot be shown on the page.
          <a href="voucher-receipt.php?t=<?= $h($_GET['t'] ?? '') ?>&amp;f=<?= $h(rawurlencode($file)) ?>"
             target="_blank" rel="noopener">Open the receipt</a>
          <?php if (!empty($e['receipt_filename'])): ?>
          <div style="margin-top:.5rem;font-size:9pt;"><?= $h($e['receipt_filename']) ?></div>
          <?php endif; ?>
        </div>
      <?php elseif ($isKm): ?>
        <div class="nofile">
          <strong>Mileage — no receipt expected</strong>
          <?= number_format((float)$e['travel_km'], 1) ?> km at
          <?= $money($voucher['mileage_rate']) ?> per km.
        </div>
      <?php else: ?>
        <div class="nofile">
          <strong>No receipt attached to this line</strong>
          Nothing was uploaded for it.
        </div>
      <?php endif; ?>
    </div>

    <div class="foot"><span><?= $h($voucher['name']) ?></span><span>Page <?= $idx + 2 ?> of <?= $pageCount ?></span></div>
  </div>
  <?php endforeach; ?>

  <!-- ── Final sheet: totals and signatures ─────────────────────────────── -->
  <div class="sheet">
    <h1>Summary</h1>
    <p class="sub"><?= $h($voucher['name']) ?></p>

    <table class="lines" style="max-width:4.6in;">
      <tbody>
        <?php foreach ($categories as $col => $label): if (abs($catTotals[$col]) < 0.005) continue; ?>
        <tr><td><?= $h($label) ?></td><td class="amt"><?= $money($catTotals[$col]) ?></td></tr>
        <?php endforeach; ?>
        <tr class="sum"><td>Total claimed</td><td class="amt"><?= $money($total) ?></td></tr>
      </tbody>
    </table>

    <div class="sign">
      <div><div class="rule"></div><div class="cap">Reviewed by</div></div>
      <div><div class="rule"></div><div class="cap">Date</div></div>
    </div>

    <p style="margin-top:2.2rem;color:var(--soft);font-size:9.5pt;">
      This is a read-only copy shared for review. Signatures of record are given in
      the BVTU members' portal by the Treasurer and Vice-President.
    </p>

    <div class="foot"><span><?= $h($voucher['name']) ?></span><span>Page <?= $pageCount ?> of <?= $pageCount ?></span></div>
  </div>

<?php endif; ?>

</body>
</html>
