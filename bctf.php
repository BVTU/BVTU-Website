<?php
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>BCTF — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="BC Teachers' Federation resources — provincial agreements, member discounts, and bargaining updates.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

  <section class="page-hero">
    <div class="container">
      <h1>BC Teachers' Federation</h1>
      <p>BVTU is a proud local of the BCTF — the provincial union representing all BC public school teachers.</p>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <!-- QUICK-ACCESS CARDS -->
      <style>
        .member-page-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
        .member-page-card { display: flex; flex-direction: column; background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 1.4rem 1.25rem 1.2rem; text-decoration: none; color: var(--text); transition: border-color .15s, box-shadow .15s, transform .12s; }
        .member-page-card:hover { border-color: var(--primary); box-shadow: 0 4px 18px rgba(27,107,66,.1); transform: translateY(-2px); color: var(--text); }
        .member-page-card-icon { width: 40px; height: 40px; background: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: .9rem; flex-shrink: 0; }
        .member-page-card-icon svg { width: 20px; height: 20px; stroke: var(--primary); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .member-page-card h3 { font-size: 1rem; font-weight: 800; color: var(--primary); margin: 0 0 .35rem; }
        .member-page-card p { font-size: .85rem; color: var(--gray-500); margin: 0; line-height: 1.55; flex: 1; }
        .member-page-card-arrow { font-size: .82rem; font-weight: 700; color: var(--primary); margin-top: .9rem; }
      </style>
      <div class="member-page-grid">

        <a href="https://bctf.ca" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          </div>
          <h3>BCTF Website</h3>
          <p>The official BC Teachers' Federation site — news, resources, and member services.</p>
          <div class="member-page-card-arrow">Visit bctf.ca →</div>
        </a>

        <a href="documents/provincial-collective-agreement-2025-2029.pdf" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <h3>Provincial Collective Agreement</h3>
          <p>The 2025–2029 provincial CA setting terms and conditions for all BC public school teachers.</p>
          <div class="member-page-card-arrow">Read the PCA (PDF) →</div>
        </a>

        <a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h3>Benefits &amp; Discounts</h3>
          <p>Member discounts and BCTF Advantage benefits available to all BCTF members.</p>
          <div class="member-page-card-arrow">View discounts →</div>
        </a>

        <a href="https://bctf.ca/pro-d" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
          </div>
          <h3>Professional Development</h3>
          <p>BCTF PRO-D resources, learning opportunities, and provincial programs for educators.</p>
          <div class="member-page-card-arrow">Explore PRO-D →</div>
        </a>

        <a href="https://bctf.ca/social-justice" target="_blank" rel="noopener" class="member-page-card">
          <div class="member-page-card-icon">
            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <h3>Social Justice &amp; Equity</h3>
          <p>BCTF programs and resources supporting equity, inclusion, and social justice in education.</p>
          <div class="member-page-card-arrow">Learn more →</div>
        </a>

      </div>

    </div>
  </main>

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
