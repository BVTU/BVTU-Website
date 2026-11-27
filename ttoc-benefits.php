<?php
require_once __DIR__ . '/inc/ttoc-page.php';
ttocPageStart('Benefits & Release Time',
    'Health and dental benefits, district committees and union release time for TTOCs in SD54.',
    'Health and dental, district committees, and union release time.');
?>

      <!-- ── Benefits ───────────────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
          Benefits &amp; District Committees
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="detail-two-col">
          <div class="info-box" style="margin:0;">
            <strong>Extended Health &amp; Dental</strong><br>
            TTOCs are eligible to enroll in district Extended Health and Dental plans through Pacific Blue Cross. You pay <strong>100% of the premium</strong> (both the employer and employee portions). Enrollment must meet eligibility requirements each year.
          </div>
          <div class="info-box" style="margin:0;">
            <strong>District Committees</strong><br>
            TTOCs can serve on district committees. Union release time may also be available for union-related work. Contact the BVTU president to learn about current committee opportunities.
          </div>
        </div>
        <style>
          @media (max-width:580px) { .detail-two-col { grid-template-columns: 1fr !important; } }
        </style>
      </div>
      <!-- ── Union Release Time ─────────────────────────────────── -->
      <div class="ttoc-section">
        <div class="ttoc-section-head">
          <div class="ttoc-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
          Union Release Time
        </div>
        <p>Release time from the union is available for union-related work, including committee participation and BCTF events. For information on how to enter union release time in Atrieve, see the <a href="atrieve.php">Atrieve Release Time guide</a>.</p>
      </div>

<?php ttocPageEnd(); ?>
