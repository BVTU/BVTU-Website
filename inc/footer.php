<?php
/**
 * footer.php — the one site footer.
 *
 * It was copy-pasted into every public page, and had drifted into seventeen
 * versions: some with the full navigation, some with a flat list, some missing
 * the address, one carrying a Member Login button nothing else had. Renaming a
 * menu item meant editing eighty links across twenty-six files, and the ones
 * that got missed sat on the same page as the ones that did not.
 *
 * The Navigate list is built from bvtuNavItems() — the same source the header
 * uses — so the two can no longer disagree about what anything is called.
 */
require_once __DIR__ . '/nav.php';

/**
 * $o:
 *   'base'    path prefix for pages that are not at the site root ('../')
 *   'minimal' just the copyright bar, for a page meant to be read without
 *             a wall of links under it
 *   'spaced'  extra room above, for a page that ends in an embed rather than
 *             in prose
 */
function bvtuFooter(array $o = []): void {
    $base    = $o['base'] ?? '';
    $minimal = !empty($o['minimal']);
    $spaced  = !empty($o['spaced']);
    $h       = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
    $year    = date('Y');
?>
  <footer class="site-footer"<?= $spaced ? ' style="margin-top:3rem;"' : '' ?>>
<?php if (!$minimal): ?>
    <div class="container footer-grid">
      <div>
        <h3>Bulkley Valley Teachers' Union</h3>
        <p>Local of the BC Teachers' Federation</p>
        <p>Representing educators in<br>Houston, Telkwa, and Smithers</p>
      </div>
      <div>
        <h3>Contact</h3>
        <p><strong style="color:rgba(255,255,255,.9)">President:</strong> Cody Lind</p>
        <p>3772-C 1st Ave<br>Smithers, BC V0J 2N0</p>
        <p><a href="<?= $h($base) ?>contact.php">Contact Us</a></p>
      </div>
      <div>
        <h3>Navigate</h3>
        <?php
        /*
         * Flat, and only the sections.
         *
         * It used to mirror the header's dropdowns, which cannot be opened by
         * touch — the CSS hides them up to 1280px and the script that opens
         * them only runs below 681px, so between those widths a phone or
         * tablet could reach none of the children. A footer is a safety net,
         * not a second menu: the header carries the full tree with its
         * explanations, and each section's own page carries the detail.
         */
        ?>
        <ul class="footer-nav-list">
          <?php foreach (bvtuNavItems() as $item): ?>
          <li><a href="<?= $h($base . $item['url']) ?>"><?= $item['label'] ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h3>Connect</h3>
        <a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a>
      </div>
    </div>
<?php endif; ?>
    <div class="footer-bottom"<?= $minimal ? ' style="border-top:none;"' : '' ?>>
      <div class="container">
        <p<?= $minimal ? ' style="padding:1.5rem 0;color:rgba(255,255,255,.5);"' : '' ?>>
          &copy; <?= $h($year) ?> Bulkley Valley Teachers' Union
          &middot; Local of the BC Teachers' Federation
          &middot; <a href="<?= $h($base) ?>privacy.php" style="color:inherit;text-decoration:underline;">Privacy</a>
        </p>
      </div>
    </div>
  </footer>
<?php
}
