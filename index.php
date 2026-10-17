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


  <!-- The six things members most often arrive wanting. Destinations, not
       categories: the menu already carries the shape of the site, and a card
       that repeats a menu word tells nobody anything twice. -->
  <section class="cards-section">
    <div class="container">
      <h2 class="section-title">What do you need?</h2>
      <p class="section-sub">The things members look for most. Everything else is in the menu, or search.</p>
      <div class="cards">

        <a href="collective-agreement.php" class="card card-lead">
          <div class="card-icon"><svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
          <h3>Your Collective Agreement</h3>
          <p>Prep time, sick leave, class size, seniority, leaves — what you are entitled to, and the article that says so.</p>
          <span class="card-arrow">Read the agreement &rarr;</span>
        </a>

        <a href="collab-grant.php" class="card">
          <div class="card-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
          <h3>Collaboration Grant</h3>
          <p>Up to three release days a year to plan or work alongside a colleague. Apply online.</p>
          <span class="card-arrow">Apply now &rarr;</span>
        </a>

        <a href="salary.php" class="card">
          <div class="card-icon"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
          <h3>Salary Grids</h3>
          <p>What you are paid this year, and how experience and qualifications move you up.</p>
          <span class="card-arrow">See the grids &rarr;</span>
        </a>

        <a href="benefits.php" class="card">
          <div class="card-icon"><svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
          <h3>Health &amp; Dental</h3>
          <p>Pacific Blue Cross — dental, vision, prescriptions, paramedical, and how to claim.</p>
          <span class="card-arrow">What is covered &rarr;</span>
        </a>

        <a href="whats-on.php" class="card">
          <div class="card-icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
          <h3>What's On</h3>
          <p>Seminars, meetings and deadlines on one calendar — and a feed you can add to your phone.</p>
          <span class="card-arrow">See the calendar &rarr;</span>
        </a>

        <a href="library.php" class="card">
          <div class="card-icon"><svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg></div>
          <h3>Resource Library</h3>
          <p>Lesson plans, units, rubrics and activities shared by BVTU members. Take what you need, add what you have.</p>
          <span class="card-arrow">Browse the library &rarr;</span>
        </a>

      </div>

      <?php // The sections these cards displaced. Still reachable, no longer
            // competing with the six things people came for. ?>
      <p class="browse-row">
        Or browse:
        <a href="documents.php">Documents</a>
        <a href="members.php">Member resources</a>
        <a href="prod.php">Pro-D</a>
        <a href="health-safety.php">Health &amp; Safety</a>
        <a href="bctf.php">BCTF</a>
        <a href="about.php">About the union</a>
      </p>
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
  <?php require_once __DIR__ . '/inc/footer.php'; bvtuFooter(); ?>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
