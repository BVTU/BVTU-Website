<?php
require_once __DIR__ . '/inc/ttoc-page.php';
ttocPageStart('Copier Login',
    'How SD54 TTOCs log in to the school copiers with PaperCut — your SD54 login, or an 8-digit number and PIN.',
    'Use your SD54 login, or set up an 8-digit number and PIN.');
?>

      <!-- ── Copier Login ───────────────────────────────────────── -->
      <div class="ttoc-section">
        <p>Copying and scanning now need a login. Your SD54 email and computer password already work.</p>
        <p>To use an 8-digit number and PIN instead:</p>
        <ol style="font-size:.93rem;line-height:1.9;padding-left:1.3rem;color:var(--text);">
          <li>On an SD54 desk computer plugged into the school network, open <a href="http://trc-pcmf.sd54.bc.ca:9191/user" target="_blank" rel="noopener">PaperCut</a> and log in.</li>
          <li>Choose <strong>Change Details</strong>.</li>
          <li>Choose <strong>Generate Number</strong>.</li>
          <li>Write down the 8-digit number under <strong>Current card/ID number</strong>.</li>
        </ol>
        <div class="info-box" style="font-size:.9rem;margin-top:.5rem;">
          PaperCut only opens on the SD54 network — not from home or on your phone.
        </div>
      </div>

<?php ttocPageEnd(); ?>
