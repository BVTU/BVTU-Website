<?php
/*
 * The TTOC home page: one card per topic, from the 'ttoc' section in
 * inc/nav.php. Each topic is its own page — see inc/ttoc-page.php.
 */
require_once __DIR__ . '/inc/ttoc-page.php';
ttocPageStart('TTOC Resources',
    'Resources for Teachers Teaching on Call in SD54 — copier login, EI, call-out pay, experience credit, sick leave and benefits.',
    'For Teachers Teaching on Call in SD54.',
    true);
?>

      <div style="margin-bottom:2.5rem;">
        <?php bvtuSectionCards('ttoc', ['headings' => false]); ?>
      </div>

      <!-- ── Key Resource ───────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
          Key Resource
        </div>
        <p>The BCTF publishes <em>The Practice of Teaching: A Handbook for New Teachers and TTOCs</em> — an excellent overview of your rights, responsibilities, and professional practice as a substitute educator in BC.</p>
        <a href="https://www.bctf.ca" target="_blank" rel="noopener" class="btn btn-outline" style="margin-top:.5rem;display:inline-block;font-size:.9rem;">Visit BCTF for Resources →</a>
      </div>

<?php ttocPageEnd(); ?>
