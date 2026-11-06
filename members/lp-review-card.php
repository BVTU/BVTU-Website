<?php
/**
 * lp-review-card.php — "Before you submit", wherever submitting happens.
 *
 * This lived on the view page, which is where the Submit to Treasurer button
 * is. The edit page has a Submit for Approval button of its own and did not
 * show any of this, so the quicker of the two routes to filing a voucher was
 * the one that never mentioned a missing receipt or a total that does not
 * match the paperwork. One card, both pages.
 *
 * Expects, from the including page:
 *   $review    lpReviewVoucher($voucher, $expenses)
 *   $voucher   the voucher row
 *   $id        its id
 *   $isOwner   whether the reader is the submitter
 *   $member    the reader
 *   $revFix    optional; false on a page that is already the fixing page
 *
 * Renders nothing at all when there is nothing to say, so an including page
 * can include it unconditionally.
 */
if (!isset($review['findings'])) return;
$revFix = !isset($revFix) || $revFix;
?>
      <?php if ($review['findings'] && in_array($voucher['status'], ['draft', 'rejected'], true)
            && ($isOwner || prodIsExec($member['email']))): ?>
  <div class="rev-card no-print">
    <div class="rev-head">
      <span class="rev-ico" aria-hidden="true">&#9906;</span>
      <div>
        <h2>Before you submit</h2>
        <p>
          <?php
            $bits = [];
            if ($review['missing']) $bits[] = $review['missing'] . ' thing' . ($review['missing'] === 1 ? '' : 's') . ' missing';
            if ($review['check'])   $bits[] = $review['check']   . ' worth checking';
            echo htmlspecialchars(implode(' · ', $bits));
          ?>.
          None of it stops you submitting — plenty of these turn out to be fine.
        </p>
      </div>
      <button type="button" class="rev-toggle" onclick="revToggle(this)" aria-expanded="true">Hide</button>
    </div>

    <ul class="rev-list" id="revList">
      <?php foreach ($review['findings'] as $f): ?>
      <li class="rev-item <?= $f['level'] === 'missing' ? 'is-missing' : 'is-check' ?>">
        <span class="rev-line">
          Line <?= (int)$f['line'] ?>
          <?php if (!empty($f['where'])): ?><em><?= htmlspecialchars($f['where']) ?></em><?php endif; ?>
        </span>
        <div>
          <span class="rev-what"><?= htmlspecialchars($f['what']) ?></span>
          <span class="rev-detail"><?= htmlspecialchars($f['detail']) ?></span>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($revFix && $voucher['status'] === 'draft'): ?>
    <div class="rev-foot">
      <a href="lp-voucher-edit.php?id=<?= $id ?>" class="btn btn-outline" style="padding:.4rem .9rem;font-size:.83rem;">Fix these</a>
    </div>
    <?php endif; ?>
  </div>
  <style>
    .rev-card { background:#fffdf7; border:1px solid #ecd9a8; border-radius:10px;
                padding:1.1rem 1.25rem; margin-bottom:1.25rem; }
    .rev-head { display:flex; gap:.85rem; align-items:flex-start; }
    .rev-ico  { font-size:1.15rem; color:#9a6b00; line-height:1.3; }
    .rev-head h2 { font-size:.98rem; margin:0 0 .2rem; color:#6b4a00; }
    .rev-head p  { margin:0; font-size:.83rem; color:#7a6333; line-height:1.5; }
    .rev-toggle { margin-left:auto; background:none; border:0; color:#7a6333;
                  font:inherit; font-size:.8rem; text-decoration:underline; cursor:pointer; }
    .rev-list { list-style:none; margin:1rem 0 0; padding:0; }
    .rev-list.hid { display:none; }
    .rev-item { display:flex; gap:.7rem; align-items:baseline; padding:.5rem 0;
                border-top:1px solid #f0e3c4; font-size:.86rem; line-height:1.5; }
    .rev-line { flex:0 0 9.5rem; font-weight:700; font-size:.76rem; color:#8a6d3b; }
    .rev-line em { display:block; font-weight:400; font-style:normal; color:#9a8862; }
    @media (max-width:640px) { .rev-item { display:block; } .rev-line { margin-bottom:.15rem; } }
    .rev-what { font-weight:700; color:#5c4a1a; }
    .rev-item.is-missing .rev-what { color:#9c3a1e; }
    .rev-detail { color:#6f6246; }
    .rev-foot { margin-top:.9rem; }
  </style>
  <script>
    function revToggle(btn) {
      var list = document.getElementById('revList');
      var hid  = list.classList.toggle('hid');
      btn.textContent = hid ? 'Show' : 'Hide';
      btn.setAttribute('aria-expanded', hid ? 'false' : 'true');
    }
  </script>
  <?php endif; ?>
