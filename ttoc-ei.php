<?php
require_once __DIR__ . '/inc/ttoc-page.php';
ttocPageStart('EI Hours Calculator',
    'Work out how many teaching days you need to qualify for EI as a TTOC in SD54.',
    'How many days you need to work to qualify for EI.');
?>

      <!-- ── EI Hours Calculator ────────────────────────────────── -->
      <div class="ei-calculator">
        <p class="sub">Teachers and TTOCs receive <strong>9.1 insurable hours per day</strong> worked for EI purposes — confirmed by the BCTF. Use this calculator to find out how many days you need to qualify.</p>

        <div class="ei-calc-grid">
          <div class="ei-calc-field">
            <label>Hours required</label>
            <input type="number" id="ei-hours" placeholder="e.g. 600" min="1" max="2000" value="600">
          </div>
          <div class="ei-calc-field">
            <label>Hours per day</label>
            <select id="ei-hpd">
              <option value="9.1" selected>9.1 hrs/day (SD54 standard)</option>
              <option value="9.1">9.1 hrs/day (confirmed by BCTF)</option>
            </select>
          </div>
          <div class="ei-calc-field">
            <div class="ei-result-label">Days you need to work</div>
            <div class="ei-result">
              <span class="ei-result-val" id="ei-result">66</span>
            </div>
          </div>
        </div>

        <div class="ei-fact-row">
          <div class="ei-fact"><strong>9.1 hours</strong> = 1 teaching day (EI)</div>
          <div class="ei-fact"><strong>600 hours</strong> = standard mat leave threshold → <strong>66 days</strong></div>
          <div class="ei-fact"><strong>420 hours</strong> = new entrant threshold → <strong>47 days</strong></div>
        </div>
      </div>

<?php ttocPageEnd(<<<'JS'
    // EI Calculator
    function calcEI() {
      const hours = parseFloat(document.getElementById('ei-hours').value) || 0;
      const hpd   = parseFloat(document.getElementById('ei-hpd').value)   || 9.1;
      const days  = hours > 0 ? Math.ceil(hours / hpd) : '—';
      document.getElementById('ei-result').textContent = days;
    }
    document.getElementById('ei-hours').addEventListener('input', calcEI);
    document.getElementById('ei-hpd').addEventListener('change', calcEI);
    calcEI();
JS); ?>
