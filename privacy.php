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
  <title>Privacy — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="What the BVTU website records about visits, what it deliberately does not, and how long anything is kept.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <header class="site-header">
    <div class="header-inner container">
      <a href="index.php" class="logo">
        <img src="bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <button class="search-btn" data-search-open aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav">
        <ul>
          <li><a href="about.php" class="active">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li><li><a href="trustee-zones.html">Trustee Zone Map</a></li><li><a href="trustee-candidates.php">Trustee Candidate Responses</a></li></ul></li>
<li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="benefits.php">Health &amp; Dental</a></li><li><a href="life-insurance.php">Life Insurance</a></li><li><a href="loan-forgiveness.php">Student Loan Forgiveness</a></li><li><a href="salary.php">Salary Grids</a></li><li><a href="ttoc.php">TTOC Resources</a></li><li><a href="atrieve.php">Release Time / Atrieve</a></li><li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="page-hero">
    <div class="container">
      <h1>Privacy</h1>
      <p>What this website records, what it deliberately does not, and how long anything is kept.</p>
    </div>
  </section>

  <main class="container" style="max-width:800px;padding:2.5rem 1.5rem 4rem;">

    <p style="font-size:1.05rem;line-height:1.8;color:var(--gray-700);">
      We count how the public pages of this site are used, so we know which
      information people actually come here for. We do it ourselves, on our own
      server, and we have built it to answer that question and no other.
    </p>

    <h2 style="font-size:1.1rem;margin:2rem 0 .7rem;">What is recorded</h2>
    <ul style="line-height:1.85;color:var(--gray-700);">
      <li>The address of the page that was opened.</li>
      <li>Whether you arrived from a search engine, from social media, from another
          page on this site, or by typing the address &mdash; and the name of the
          website you came from, never the search you typed.</li>
      <li>Whether the device is a phone, a tablet or a computer.</li>
      <li>Which links are followed from a page &mdash; documents opened, short links
          used, and links leaving the site.</li>
      <li>The date and the hour, not the minute.</li>
    </ul>

    <h2 style="font-size:1.1rem;margin:2rem 0 .7rem;">What is not recorded</h2>
    <ul style="line-height:1.85;color:var(--gray-700);">
      <li><strong>No IP addresses.</strong> Your address is used for a moment to
          work out a visitor count and is never written down.</li>
      <li><strong>No names, accounts or member records.</strong> Nothing recorded
          here is connected to who you are.</li>
      <li><strong>Nothing from the members-only area.</strong> What a signed-in
          member reads is not counted at all.</li>
      <li><strong>No advertising or third-party trackers.</strong> Nothing on this
          site reports to Google, Meta or anyone else. No tracking cookies.</li>
    </ul>

    <h2 style="font-size:1.1rem;margin:2rem 0 .7rem;">How visitors are counted</h2>
    <p style="line-height:1.8;color:var(--gray-700);">
      To tell twenty visits by one person from one visit by twenty people, each
      visit is given a code worked out from your connection. That code is built
      with a secret that is thrown away and replaced every day, so the same
      person tomorrow produces a different code and there is no way to work
      backwards from a code to a person. It tells us how many people came. It
      cannot tell us who, or follow anyone from one day to the next.
    </p>

    <h2 style="font-size:1.1rem;margin:2rem 0 .7rem;">How long it is kept</h2>
    <p style="line-height:1.8;color:var(--gray-700);">
      Individual records are deleted after 90 days. What remains is a daily count
      of views per page &mdash; no visitor codes, nothing about any single visit.
    </p>

    <h2 style="font-size:1.1rem;margin:2rem 0 .7rem;">Asking not to be counted</h2>
    <p style="line-height:1.8;color:var(--gray-700);">
      If your browser sends a &ldquo;Do Not Track&rdquo; or
      &ldquo;Global Privacy Control&rdquo; signal, this site records nothing about
      your visit. Both are settings in your browser&rsquo;s privacy preferences.
    </p>

    <h2 style="font-size:1.1rem;margin:2rem 0 .7rem;">Who can see it</h2>
    <p style="line-height:1.8;color:var(--gray-700);">
      Only the Local President, through the members area. The figures are counts
      of pages and links &mdash; there is no per-person view to look at, because
      the data to build one is not collected.
    </p>

    <h2 style="font-size:1.1rem;margin:2rem 0 .7rem;">Other information you give us</h2>
    <p style="line-height:1.8;color:var(--gray-700);">
      This note covers visit counting. Information you deliberately send us &mdash;
      a contact form, a grant application, a member account &mdash; is held because
      the union needs it to do its work, is seen only by the officers who need it,
      and is not part of anything described above.
    </p>

    <p style="line-height:1.8;color:var(--gray-600);margin-top:2rem;font-size:.92rem;">
      Questions about any of this go to the Local President &mdash;
      <a href="contact.php">contact us</a>.
    </p>

  </main>

  <footer class="site-footer">
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
        <p><a href="contact.php">Contact Us</a></p>
      </div>
      <div>
        <h3>Navigate</h3>
        <ul class="footer-nav-list">
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li><li><a href="trustee-zones.html">Trustee Zone Map</a></li><li><a href="trustee-candidates.php">Trustee Candidate Responses</a></li></ul></li>
          <li><a href="members.php">Members</a></li>
          <li><a href="prod.php">PRO-D</a></li>
          <li class="has-dropdown"><a href="health-safety.php">Health &amp; Safety</a><ul class="dropdown"><li><a href="health-safety.php">H&amp;S Resources</a></li><li><a href="https://www.worksafebc.com" target="_blank" rel="noopener">WorkSafe BC</a></li><li><a href="https://sd54.lifeworks.com/" target="_blank" rel="noopener">EFAP</a></li></ul></li>
          <li class="has-dropdown"><a href="bctf.php">BCTF</a><ul class="dropdown"><li><a href="bctf.php">BCTF Resources</a></li><li><a href="https://bctf.ca" target="_blank" rel="noopener">BCTF Website</a></li><li><a href="https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage" target="_blank" rel="noopener">Benefits &amp; Discounts</a></li></ul></li>
          <li class="has-dropdown"><a href="library.php">Resources</a><ul class="dropdown"><li><a href="library.php">Resource Library</a></li><li><a href="curated.php">Curated Resources</a></li></ul></li><li><a href="newsletter-archive.php">Newsletters</a></li>
        </ul>
      </div>
      <div>
        <h3>Connect</h3>
        <a href="#" target="_blank" rel="noopener" class="btn btn-outline-white">Facebook Group</a>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation
           · <a href="privacy.php" style="color:inherit;text-decoration:underline;">Privacy</a></p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
