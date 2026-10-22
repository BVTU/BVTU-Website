<?php
// The test-site banner, hooked in here because every public page goes through
// this file. One rule in one place beats the same rule missing from eleven
// page headers.
require_once __DIR__ . '/env.php';
/**
 * nav.php — the site's one header and navigation.
 *
 * It used to be copied by hand into forty-five pages, and had drifted into five
 * different versions: the members area had no dropdowns and no hamburger at all,
 * so on a phone it had no navigation whatsoever; two public pages carried a nav
 * two rewrites old; one had "My Dashboard" buried inside the BCTF menu. Adding a
 * link meant remembering every file, and twice an `active` class was copied onto
 * a page it did not belong to.
 *
 * Call it once per page:
 *
 *     require_once __DIR__ . '/inc/nav.php';          // or '/../inc/nav.php'
 *     bvtuNav();                                      // from the site root
 *     bvtuNav(['base' => '../']);                     // from members/
 *     bvtuNav(['hero' => true]);                      // the homepage only
 *
 * The current page is worked out from the filename, so nothing has to be kept in
 * step by hand; pass 'active' only to override it.
 */

/**
 * Every item, in order.
 *
 * Children are [label, url, external, description]. The description is one
 * short line of plain English shown under the label, because several of these
 * names are ours rather than the member's — "Atrieve", "remedy", "LOU", "SURT"
 * mean nothing in your first year, and a menu that needs insider knowledge to
 * read is a menu that sends people to search instead.
 *
 * Labels name the member's problem, not our tool. "Remedy Tracker" is what we
 * call the calculator; "Class size & remedy" is what they came to find out.
 */
function bvtuNavItems(): array {
    return [
        'about' => ['label' => 'About', 'url' => 'about.php', 'children' => []],

        'documents' => ['label' => 'Documents', 'url' => 'documents.php', 'children' => [
            ['All Documents', 'documents.php', false,
             'Everything we hold, in one searchable list'],
            ['Collective Agreement', 'collective-agreement.php', false,
             'Your contract with School District 54'],
            ['Side agreements (LOUs)', 'lous.php', false,
             'What has been agreed since the contract was signed'],
            ['Contract Assistant', 'ca-assistant.php', false,
             'Ask a question, get the article that answers it'],
            ['Constitution &amp; Bylaws', 'documents/BVTU-Constitution-and-Bylaws-2026.pdf', true,
             'How the local is run'],
            ['School Calendars', 'calendars.php', false,
             'SD54 instructional days and Pro-D days'],
            ['Trustee Zone Map', 'trustee-zones.php', false,
             'Which zone you vote in'],
            ['Trustee Candidate Responses', 'trustee-candidates.php', false,
             'What the candidates told us'],
        ]],

        'members' => ['label' => 'Members', 'url' => 'members.php', 'children' => [
            ['Member Resources', 'members.php', false,
             'Everything for members, in one place'],
            ['Salary Grids', 'salary.php', false,
             'What you are paid, and how you move up'],
            ['Health &amp; Dental', 'benefits.php', false,
             'Pacific Blue Cross — what is covered, and how to claim'],
            ['Life Insurance', 'life-insurance.php', false,
             'Your coverage, beneficiaries and conversion rights'],
            ['Student Loan Forgiveness', 'loan-forgiveness.php', false,
             'Up to $30,000 forgiven for teaching in the north'],
            ['Booking time off', 'atrieve.php', false,
             'Release time and absences, and how to enter them in Atrieve'],
            ['Class size &amp; remedy', 'remedy-tracker.php', false,
             'When your class is over size — work out what you are owed'],
            ['Collaboration Grant', 'collab-grant.php', false,
             'Release days to plan with a colleague'],
            ['Training &amp; workshops', 'events.php', false,
             'SURTs and other sessions you can sign up for'],
            ["What's On", 'whats-on.php', false,
             'Union events, meetings and deadlines on one calendar'],
            ['TTOC Resources', 'ttoc.php', false,
             'Pay, seniority and call-out for teachers on call'],
        ]],

        'prod' => ['label' => 'PRO-D', 'url' => 'prod.php', 'children' => []],

        'health' => ['label' => 'Health &amp; Safety', 'url' => 'health-safety.php', 'children' => [
            ['Health &amp; Safety', 'health-safety.php', false,
             'Committees, reporting, and your right to refuse unsafe work'],
            ['WorkSafe BC', 'https://www.worksafebc.com', true,
             'Claims and workplace injury'],
            ['Counselling &amp; support', 'https://sd54.lifeworks.com/', true,
             'Free and confidential, through the employee assistance plan'],
        ]],

        'bctf' => ['label' => 'BCTF', 'url' => 'bctf.php', 'children' => [
            ['BCTF Resources', 'bctf.php', false,
             'What the provincial federation offers members'],
            ['BCTF Website', 'https://bctf.ca', true,
             'bctf.ca'],
            ['Benefits &amp; Discounts', 'https://www.bctf.ca/topics/services-information/benefits/view-member-discounts-bctf-advantage', true,
             'Member discounts through BCTF Advantage'],
        ]],

        'resources' => ['label' => 'Resources', 'url' => 'library.php', 'children' => [
            ['Resource Library', 'library.php', false,
             'Lesson plans, units and rubrics shared by BVTU members'],
            ['Curated Resources', 'curated.php', false,
             'Picked out by the BVTU'],
        ]],

        'newsletters' => ['label' => 'Newsletters', 'url' => 'newsletter-archive.php', 'children' => []],
    ];
}

/**
 * Which section the page being rendered belongs to.
 *
 * Derived rather than declared: an `active` class set by hand is the thing that
 * ended up on About and was then copied onto two pages that are not About.
 */
function bvtuNavSection(string $file): string {
    $map = [
        'about'       => ['about.php', 'contact.php'],
        'documents'   => ['documents.php', 'collective-agreement.php', 'lous.php', 'ca-assistant.php',
                          'calendars.php', 'trustee-zones.php', 'trustee-candidates.php'],
        'members'     => ['members.php', 'benefits.php', 'life-insurance.php', 'loan-forgiveness.php',
                          'salary.php', 'ttoc.php', 'atrieve.php', 'remedy-tracker.php',
                          'collab-grant.php', 'collab-grant-edit.php',
                          // Added with the events work; without them neither page
                          // lights up its section in the bar.
                          'events.php', 'whats-on.php', 'event.php', 'event-cancel.php'],
        'prod'        => ['prod.php'],
        'health'      => ['health-safety.php'],
        'bctf'        => ['bctf.php', 'bctf-forms.php'],
        'resources'   => ['library.php', 'library-resource.php', 'library-saved.php',
                          'library-upload.php', 'library-edit.php', 'curated.php'],
        'newsletters' => ['newsletter-archive.php', 'newsletter.php'],
    ];
    foreach ($map as $section => $files) {
        if (in_array($file, $files, true)) return $section;
    }
    return '';
}

/** A nav link's href, left alone when it already points somewhere absolute. */
function bvtuNavHref(string $url, string $base): string {
    if (preg_match('~^(https?:)?//~', $url) || $url[0] === '/') return $url;
    return $base . $url;
}

function bvtuNav(array $o = []): void {
    $base = $o['base'] ?? '';
    $hero = !empty($o['hero']);

    // Whether to offer Member Login or My Dashboard. auth.php is loaded here when
    // a page has not already done so — isLoggedIn() only reads the session and
    // auth.php touches no database, so this costs a public page nothing and does
    // not make it depend on the database being up.
    if (!array_key_exists('loggedIn', $o) && !function_exists('isLoggedIn') && !headers_sent()) {
        // Only before output: isLoggedIn() starts the session, and starting one
        // after the headers have gone prints warnings into the page itself.
        $auth = __DIR__ . '/../members/auth.php';
        if (is_file($auth)) require_once $auth;
    }
    $loggedIn = array_key_exists('loggedIn', $o)
        ? (bool)$o['loggedIn']
        : (function_exists('isLoggedIn') && isLoggedIn());

    $file    = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $section = $o['active'] ?? bvtuNavSection($file);
    $items   = bvtuNavItems();
    $h       = fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    ?>
  <header class="site-header<?= $hero ? ' hero-mode' : '' ?>">
    <div class="header-inner container">
      <a href="<?= $base ?>index.php" class="logo">
        <img src="<?= $base ?>bvtu-logo.png" alt="BVTU Logo">
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
          <?php foreach ($items as $key => $item): ?>
            <?php
              $kids = $item['children'];
              // Signed in, the Members menu is also the way back to the portal
              // and the way out of it — the only difference the full menu makes.
              if ($key === 'members' && $loggedIn) {
                  $kids[] = ['My Dashboard', 'members/dashboard.php'];
                  $kids[] = ['Sign Out',     'members/logout.php'];
              }
              $isActive = ($section === $key);
            ?>
            <?php if (!$kids): ?>
              <li><a href="<?= $base . $item['url'] ?>"<?= $isActive ? ' class="active"' : '' ?>><?= $item['label'] ?></a></li>
            <?php else: ?>
              <li class="has-dropdown">
                <a href="<?= $base . $item['url'] ?>"<?= $isActive ? ' class="active"' : '' ?>><?= $item['label'] ?></a>
                <?php // Past six, one column runs off the bottom of a laptop screen. ?>
                <ul class="dropdown<?= count($kids) > 6 ? ' dropdown-wide' : '' ?>">
                  <?php foreach ($kids as $kid): ?>
                    <?php
                      [$label, $url] = $kid;
                      $ext  = !empty($kid[2]);
                      $desc = $kid[3] ?? '';
                    ?>
                    <li>
                      <a href="<?= $h(bvtuNavHref($url, $base)) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>>
                        <span class="nav-label"><?= $label ?></span>
                        <?php if ($desc !== ''): ?>
                        <span class="nav-desc"><?= $desc ?></span>
                        <?php endif; ?>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </li>
            <?php endif; ?>
          <?php endforeach; ?>
          <li><a href="<?= $base ?><?= $loggedIn ? 'members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>
<?php
}
