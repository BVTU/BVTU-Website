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
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(); ?>

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
          site reports to Google, Meta or anyone else as you browse. No tracking
          cookies.</li>
    </ul>

    <h2 style="font-size:1.1rem;margin:2rem 0 .7rem;">The forum video</h2>
    <p style="line-height:1.8;color:var(--gray-700);">
      The all-candidates forum recording is held on YouTube rather than here, because a
      three-hour video needs to adjust its quality for a slow connection and to carry
      captions, and our own server does neither. That means the player comes from Google,
      and Google will see your request for it &mdash; so <strong>nothing is requested until
      you press play</strong>. Open <a href="forum.php">the forum page</a> and nothing
      reaches Google; press play and it does, on the same terms as watching anything on
      YouTube. We use their no-cookie player address, which holds off on cookies until
      you actually watch.
    </p>

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

  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
