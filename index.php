<?php
require_once __DIR__ . '/members/auth.php';
// Filesystem only — no database. See members/forum-state.php.
require_once __DIR__ . '/members/forum-state.php';
// Same reason, same shape: what is coming up, without a database.
require_once __DIR__ . '/members/event-state.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Bulkley Valley Teachers' Union — Local of the BC Teachers' Federation, representing educators in Houston, Telkwa, and Smithers.">
  <link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
  <link rel="icon" href="favicon.ico">
</head>
<body>

  <!-- Header -->
  <?php require_once __DIR__ . '/inc/nav.php'; bvtuNav(['hero' => true]); ?>

  <!-- Hero -->
  <section class="hero">
    <h1>Supporting Educators<br>in the Bulkley Valley</h1>
    <p>Your local union representing teachers in Houston, Telkwa, and Smithers — SD54, Local of the BCTF.</p>
    <div style="display:flex;gap:.85rem;justify-content:center;flex-wrap:wrap;">
      <a href="members.php" class="btn btn-primary">Member Resources</a>

    </div>
  </section>

  <?php
  /*
   * Trustee election promotion — takes itself down.
   *
   * General voting day is Saturday 17 October 2026. This block stops rendering
   * the day after, so nobody has to remember to remove it: a "vote on the 17th"
   * banner still up in November is worse than never having run one. The pages
   * it points at stay where they are as a record of what was said.
   *
   * The matching strip on every other page is injected by js/site.js, which
   * carries the same date. Styles for both are at the end of css/style.css.
   */
  /*
   * Worked out once and used by both this block and the forum band below, so
   * the two cannot drift apart or quietly depend on each other's variables.
   *
   * Explicit timezone: an anonymous homepage request never loads members/db.php,
   * which is where this site sets one. On a UTC server the promotion would
   * vanish at 5pm on voting day, while people are still voting.
   */
  $electionPromoOn =
      (new DateTime('now', new DateTimeZone('America/Vancouver')))->format('Y-m-d') <= '2026-10-17';

  if ($electionPromoOn):
  ?>

  <?php
  /*
   * Four questions from the forum, ahead of the election block.
   *
   * The full recording runs two hours eleven. These are four questions from
   * it, about a minute each, so somebody deciding how to vote on the way out
   * the door has a way in that is not a two-hour commitment. A clip does not
   * necessarily carry every candidate, so nothing here says it does — the full
   * forum sits directly below for the complete answer.
   *
   * Thumbnails are served from this site, not from Google. Four YouTube
   * iframes would load about a megabyte of their JavaScript and set their
   * cookies on every visit to the front page, before anyone asked for a video
   * — the same reason forum-promo below shows a picture and leaves playback to
   * forum.php. Pressing play is what loads a player, and nothing earlier.
   *
   * These four pictures are committed rather than fetched on demand, which is
   * why they are the exception to the images/forum-thumb-* ignore rule. The
   * rule exists so the editor can paste a new forum link and have its picture
   * appear without a deploy; these ids are written here in the source, so
   * changing one is a deploy anyway and there is nothing to gain. What there
   * is to lose: forumEnsureThumb() would make index.php's first ever outbound
   * request, and three sizes at a six-second timeout is a long time to hold
   * the front page when ytimg is unreachable.
   *
   * Gated with the election block: the whole promotion comes down together on
   * 18 October. These stay on forum.php, which remains as the record.
   */
  $forumClips = [
      ['id' => 'zFxRE1fccIo', 'len' => '1:00',
       'q'  => 'How will you speak for teachers, SEAs and students?'],
      ['id' => 'xhao8Un1UhY', 'len' => '0:58',
       'q'  => 'What is one strength of SD54 you would want the board to protect?'],
      ['id' => 'Efx9oFighQE', 'len' => '1:08',
       'q'  => 'What would you do to make sure 2SLGBTQ+ students feel safe at school?'],
      ['id' => 'Yfsxe-oDLVU', 'len' => '0:56',
       'q'  => 'After four years, what would you want students, staff and families to say about you?'],
  ];
  ?>
  <section class="clips">
    <div class="container">
      <span class="clips-eyebrow">In short</span>
      <h2>Four questions from the forum</h2>
      <p class="clips-lead">
        About a minute each.<?php
          /* $fs is read below for the forum band; on a fresh deploy the state
             file may not exist yet, and then there is no "below" to point at. */
          if (forumState()): ?> The full forum is below if you want all of it.<?php endif; ?>
      </p>

      <ul class="clips-grid">
        <?php foreach ($forumClips as $c): $thumb = forumThumbUrl($c['id']); ?>
        <li class="clip">
          <?php /* A real link, so it works with JavaScript off and can be opened
                   in a new tab. The script turns it into an in-page player. */ ?>
          <a class="clip-link" href="https://www.youtube.com/watch?v=<?= htmlspecialchars($c['id']) ?>"
             data-clip="<?= htmlspecialchars($c['id']) ?>"
             target="_blank" rel="noopener">
            <span class="clip-shot">
              <?php if ($thumb !== ''): ?>
              <img src="<?= htmlspecialchars($thumb) ?>" alt="" width="640" height="360" loading="lazy" decoding="async">
              <?php endif; ?>
              <span class="clip-play" aria-hidden="true"></span>
              <span class="clip-len"><?= htmlspecialchars($c['len']) ?></span>
            </span>
            <span class="clip-q"><?= htmlspecialchars($c['q']) ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>

    </div>
  </section>

  <script>
  /* Swap the picture for a player in place. Nothing contacts YouTube until
     this runs, and it only runs on a click. */
  (function () {
    var links = document.querySelectorAll('.clip-link[data-clip]');
    for (var i = 0; i < links.length; i++) {
      links[i].addEventListener('click', function (ev) {
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button) return; // let new-tab work
        ev.preventDefault();

        var li = this.closest('li.clip');
        if (!li || li.querySelector('iframe')) return;
        var question = this.querySelector('.clip-q').textContent;

        var f = document.createElement('iframe');
        f.src = 'https://www.youtube-nocookie.com/embed/' + this.getAttribute('data-clip')
              + '?autoplay=1&rel=0&modestbranding=1';
        f.title = question;
        f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; picture-in-picture';
        f.setAttribute('allowfullscreen', '');
        f.setAttribute('frameborder', '0');

        /*
         * The whole link is replaced rather than emptied and reused. Stripping
         * href off an <a> leaves something that is no longer focusable, so the
         * keyboard user who just pressed Enter on it is dropped back to the top
         * of the document — and an <iframe> inside an <a> is not valid anyway.
         */
        var shot = document.createElement('span');
        shot.className = 'clip-shot';
        shot.appendChild(f);

        var cap = document.createElement('span');
        cap.className = 'clip-q';
        cap.textContent = question;

        var box = document.createElement('div');
        box.className = 'clip-playing';
        box.appendChild(shot);
        box.appendChild(cap);

        li.replaceChild(box, this);
        f.focus();
      });
    }
  })();
  </script>

  <?php
  /*
   * The forum, under the clips and above the map. The clips are the way in;
   * this is the whole thing, for anyone a clip did not answer. Shown only
   * once there is a recording to watch —
   * read from a small file rather than the database, because this page has
   * never needed one and a database outage should not take the front page down.
   */
  $fs = forumState();
  // Gated on the same date as the election block below, so the whole promotion
  // comes down together on 18 October. The forum page itself stays up as a
  // record; what ends is advertising a finished election from the front page.
  if ($fs && $electionPromoOn):
  ?>
  <section class="forum-promo">
    <div class="container">
      <?php
      // Same 2 KB floor forumThumbUrl() uses: a truncated file passes is_file()
      // and then renders as a broken image instead of falling back cleanly.
      $thumbFile = __DIR__ . '/' . $fs['thumb'];
      if ($fs['thumb'] !== '' && is_file($thumbFile) && filesize($thumbFile) > 2048): ?>
        <?php // Served from this site, not from Google — nothing here contacts
              // YouTube until someone presses play on the forum page. ?>
        <a class="forum-promo-thumb" href="forum.php" aria-hidden="true" tabindex="-1">
          <img src="<?= htmlspecialchars($fs['thumb']) ?>" alt="" width="320" height="180" loading="lazy">
          <span class="forum-promo-play-sm"><span></span></span>
        </a>
      <?php else: ?>
        <div class="forum-promo-play" aria-hidden="true"><span></span></div>
      <?php endif; ?>
      <div class="forum-promo-text">
        <span class="forum-promo-eyebrow">Watch</span>
        <h2><?= htmlspecialchars($fs['title'] !== '' ? $fs['title'] : 'All-Candidates Forum') ?></h2>
        <p>
          Every candidate, in their own words.
          <?php if ($fs['event_date'] !== ''): ?>
            Recorded <?= htmlspecialchars(date('j F', strtotime($fs['event_date']))) ?>.
          <?php endif; ?>
          <?php if ($fs['chapters'] > 0): ?>
            It runs about three hours, so it is split into
            <?= (int)$fs['chapters'] ?> parts &mdash; jump straight to the candidate or the
            question you care about.
          <?php endif; ?>
        </p>
      </div>
      <a href="forum.php" class="btn btn-primary forum-promo-btn">Watch the forum</a>
    </div>
  </section>
  <?php endif; ?>

  <section class="vote-promo">
    <div class="container">
      <a class="vote-promo-map" href="trustee-zones.php"
         aria-label="Open the interactive trustee zone map">
        <img src="images/trustee-zones-map.svg"
             alt="Map of the four School District 54 trustee voting zones" width="760" height="943">
      </a>
      <div>
        <span class="vote-promo-when">General voting day &middot; Saturday 17 October</span>
        <h2>School trustee election</h2>
        <p>
          Seven trustees govern School District 54, and you vote only for the ones in your zone.
          Enter your address on the map and it will tell you which zone you are in and how many
          trustees you elect there.
        </p>
        <p>The BVTU endorses:</p>
        <?php
        /*
         * By zone, with the seat count, because that is how the ballot works —
         * and because Zone 4 endorses three candidates for two seats. The rule
         * set on trustee-zones.php is that the "any two of these three" caveat
         * appears wherever the names appear: three names above a two-seat zone
         * invites a Houston voter to mark all three and spoil their ballot.
         */
        $endorsed = [
            1 => ['seats' => 1, 'names' => ['Jessica Michell']],
            2 => ['seats' => 3, 'names' => ['Phil Brienesse', 'Diane Mackay', 'Casda Thomas']],
            3 => ['seats' => 1, 'names' => ['Kim Unger']],
            4 => ['seats' => 2, 'names' => ['Matt Williamson', 'Sharon Redford', 'Kellie Dondale']],
        ];
        ?>
        <ul class="vote-promo-list">
          <?php foreach ($endorsed as $z => $e): ?>
          <li>
            <span class="z">Zone <?= $z ?></span>
            <span class="names"><?= htmlspecialchars(implode(', ', $e['names'])) ?></span>
            <span class="how"><?= count($e['names']) > $e['seats']
                ? 'vote for any ' . $e['seats'] . ' of these ' . count($e['names'])
                : 'vote for up to ' . $e['seats'] ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <p>
          Thirteen candidates were invited to answer a BVTU survey. Eleven answered, one replied
          declining to answer, and one did not reply. Every answer is published as it was written.
        </p>
        <div class="vote-promo-btns">
          <a href="trustee-zones.php" class="btn btn-primary">Find your zone &amp; who we endorse</a>
          <a href="trustee-candidates.php" class="btn btn-outline">Read candidate responses</a>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>


  <!-- Quick Access Cards -->
  <section class="cards-section">
    <div class="container">
      <h2 class="section-title">What are you looking for?</h2>
      <p class="section-sub">Resources, documents, and information for BVTU members.</p>
      <div class="cards">

<a href="documents.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          </div>
          <h3>Documents</h3>
          <p>Collective agreements, settlements, provincial regulations, and more.</p>
          <span class="card-arrow">View documents →</span>
        </a>

        <a href="members.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <h3>Members</h3>
          <p>Release time, salary grids, certification fees, grants, and TTOC resources.</p>
          <span class="card-arrow">Member info →</span>
        </a>

        <a href="prod.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
          </div>
          <h3>PRO-D</h3>
          <p>Professional development policies, committee info, and training opportunities.</p>
          <span class="card-arrow">PRO-D info →</span>
        </a>

        <a href="health-safety.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          </div>
          <h3>Health &amp; Safety</h3>
          <p>Workplace safety committees, WorkSafe forms, and employee assistance programs.</p>
          <span class="card-arrow">H&amp;S resources →</span>
        </a>

        <a href="bctf.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          </div>
          <h3>BCTF</h3>
          <p>Provincial collective agreements, member discounts, and bargaining updates.</p>
          <span class="card-arrow">BCTF info →</span>
        </a>

        <a href="library.php" class="card">
          <div class="card-icon">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          </div>
          <h3>Resource Library</h3>
          <p>Lesson plans, unit plans, rubrics, and activities shared by BVTU teachers.</p>
          <span class="card-arrow">Browse resources →</span>
        </a>

      </div>
    </div>
  </section>

  <?php
  /*
   * What is on, under the member cards. Read from a small file the seminar
   * tools write, never from the database — see members/event-state.php.
   *
   * Nothing is shown when nothing is open, rather than an empty heading
   * announcing that the union is running nothing.
   */
  $surts = evStateUpcoming(3);
  if ($surts):
  ?>
  <section class="surts">
    <div class="container">
      <h2>Upcoming SURTs</h2>
      <p class="surts-lead">Union rep training and seminars you can sign up for.</p>

      <ul class="surt-list">
        <?php foreach ($surts as $e): ?>
        <li>
          <a href="event.php?s=<?= htmlspecialchars(urlencode($e['slug']), ENT_QUOTES, 'UTF-8') ?>">
            <span class="surt-when"><?= htmlspecialchars(evStateWhen($e), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="surt-title"><?= htmlspecialchars((string)($e['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (trim((string)($e['summary'] ?? '')) !== ''): ?>
            <span class="surt-sum"><?= htmlspecialchars((string)($e['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <?php if (trim((string)($e['location'] ?? '')) !== ''): ?>
            <span class="surt-where"><?= htmlspecialchars((string)($e['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <span class="surt-go">Details &amp; sign up &rarr;</span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- Quick External Links -->
  <section class="quick-links">
    <div class="container">
      <h2>Quick Links</h2>
      <div class="quick-links-row">
        <a href="https://bctf.ca" target="_blank" rel="noopener">BCTF</a>
        <a href="https://www.sd54.bc.ca" target="_blank" rel="noopener">School District 54</a>
        <a href="https://www.bctp.ca" target="_blank" rel="noopener">Teachers' Pension Plan</a>
        <a href="https://www2.gov.bc.ca/gov/content/education-training/k-12" target="_blank" rel="noopener">Ministry of Education</a>
        <a href="https://www.bcteacherregulation.ca" target="_blank" rel="noopener">Teacher Certification</a>
        <a href="https://www.tqs.ca" target="_blank" rel="noopener">Teacher Qualification Services</a>
      </div>
    </div>
  </section>

  <!-- Footer -->
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
          
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li><li><a href="trustee-zones.php">Trustee Zone Map</a></li><li><a href="trustee-candidates.php">Trustee Candidate Responses</a></li></ul></li>
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
