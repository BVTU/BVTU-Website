<?php
require_once __DIR__ . '/inc/ttoc-page.php';
ttocPageStart('Call-Out & Pro-D Pay',
    'TTOC call-out rates in SD54, and when TTOCs are paid for Pro-D days.',
    'Morning and afternoon call-out rates, and when a Pro-D day is paid.');
?>

      <!-- ── Call-Out Rates ─────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.65 3.18 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.54a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
          Call-Out Compensation
        </div>
        <p>When you are called out for only part of the day, your compensation is prorated as follows:</p>
        <div class="callout-cards">
          <div class="callout-card primary">
            <div class="callout-pct">0.60</div>
            <div class="callout-time">Morning Call-Out</div>
            <div class="callout-desc">Called in for the morning only — you receive 60% of your daily rate.</div>
          </div>
          <div class="callout-card">
            <div class="callout-pct">0.40</div>
            <div class="callout-time">Afternoon Call-Out</div>
            <div class="callout-desc">Called in for the afternoon only — you receive 40% of your daily rate.</div>
          </div>
        </div>
      </div>
      <!-- ── Pro-D Days ─────────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
          Professional Development Days
        </div>
        <p>TTOCs are entitled to Pro-D day pay when <strong>both</strong> of the following conditions are met:</p>
        <ul style="font-size:.93rem;line-height:1.9;padding-left:1.3rem;color:var(--text);">
          <li>You have reached <strong>Day 21</strong> in your current continuous assignment, <em>and</em></li>
          <li>The Pro-D day falls within your regular scheduled days in that assignment.</li>
        </ul>
        <p style="font-size:.9rem;color:var(--gray-600);margin-top:.75rem;">The district may consider payment in other circumstances on a case-by-case basis. Contact the SD54 payroll/HR office directly to discuss your situation.</p>
      </div>

<?php ttocPageEnd(); ?>
