<?php
// The test-site banner, hooked in here because every public page goes through
// this file. One rule in one place beats the same rule missing from eleven
// page headers.
require_once __DIR__ . '/env.php';
/**
 * nav.php — the site's one header and navigation, and the one definition of
 * how the site is organised.
 *
 * It used to be copied by hand into forty-five pages, and had drifted into five
 * different versions. It then grew to eight top-level sections that mixed
 * format (Documents), audience (Members), topic and organisation, so a teacher
 * had to know which kind of thing a question was before they could find it.
 *
 * Five primary items now, in this order and no others (Cody's decision,
 * November 2026): Contract & Benefits, Professional Development, Teaching
 * Resources, News & Updates, and My Dashboard. Search and the logo are
 * utilities, not sections.
 *
 * The same array drives the header, the footer, the section landing pages and
 * the Member Resources overview — so a link added here appears everywhere it
 * should, and a landing page can no longer drift from its menu (which is what
 * the old documents.php had done).
 *
 * Call it once per page:
 *
 *     require_once __DIR__ . '/inc/nav.php';          // or '/../inc/nav.php'
 *     bvtuNav();                                      // from the site root
 *     bvtuNav(['base' => '../']);                     // from members/
 *     bvtuNav(['hero' => true]);                      // the homepage only
 *
 * The current section is worked out from the script path; pass 'active' only to
 * override it.
 */

/**
 * The four content sections, in order.
 *
 * Each item:
 *   label   menu text (HTML-safe as written: entities, no tags)
 *   url     site-relative, or absolute for another site
 *   desc    one short line under the label — several of these names are ours
 *           rather than the member's ("LOU", "remedy", "Atrieve"), and a menu
 *           that needs insider knowledge sends people to search instead
 *   ext     opens in a new tab (another site, or a PDF)
 *   access  'union'    needs a BVTU portal login
 *           'district' needs an SD54 login — not ours to give
 *   files   other scripts that belong to this item, for the active state
 *
 * A section has either 'items' (one list) or 'groups' (topics, shown as the
 * subject directory on the section page). 'priority' names the three urls its
 * dropdown shows; everything else is one click away through "Explore all".
 * The dropdown carries labels only — descriptions belong on the section page,
 * where there is room for them (Cody, 9 Nov 2026: the menus were too wordy).
 */
function bvtuNavSections(): array {
    return [
        'contract' => [
            'label' => 'Contract &amp; Benefits',
            'url'   => 'contract-benefits.php',
            'intro' => 'Your agreement, pay, benefits, leave and safety at work.',
            'priority' => ['collective-agreement.php', 'salary.php', 'benefits.php'],
            'groups' => [
                ['label' => 'Your contract', 'items' => [
                    ['label' => 'Collective Agreement', 'url' => 'collective-agreement.php',
                     'desc'  => 'Your contract with School District 54'],
                    ['label' => 'LOUs &amp; settlements', 'url' => 'lous.php',
                     'desc'  => 'What has been agreed since the contract was signed'],
                    ['label' => 'Contract Assistant', 'url' => 'ca-assistant.php',
                     'desc'  => 'Ask a question, get the article that answers it'],
                    ['label' => 'Documents &amp; Forms', 'url' => 'documents.php',
                     'desc'  => 'Every agreement, grid, policy and form we publish'],
                ]],
                ['label' => 'Pay &amp; benefits', 'items' => [
                    ['label' => 'Salary Grids', 'url' => 'salary.php',
                     'desc'  => 'What you are paid, and how you move up'],
                    ['label' => 'Health &amp; Dental', 'url' => 'benefits.php',
                     'desc'  => 'Pacific Blue Cross — what is covered, and how to claim'],
                    ['label' => 'Life Insurance', 'url' => 'life-insurance.php',
                     'desc'  => 'Your coverage, beneficiaries and conversion rights'],
                    ['label' => 'Student Loan Forgiveness', 'url' => 'loan-forgiveness.php',
                     'desc'  => 'Up to $30,000 forgiven for teaching in the north'],
                ]],
                ['label' => 'Working conditions', 'items' => [
                    ['label' => 'Class size &amp; remedy', 'url' => 'remedy-tracker.php',
                     'desc'  => 'When your class is over size — work out what you are owed'],
                    ['label' => 'Health &amp; Safety', 'url' => 'health-safety.php',
                     'desc'  => 'Committees, reporting, and your right to refuse unsafe work'],
                    ['label' => 'TTOC Resources', 'url' => 'ttoc.php',
                     'desc'  => 'Pay, seniority and call-out for teachers on call'],
                ]],
                ['label' => 'Leave', 'items' => [
                    ['label' => 'Booking time off', 'url' => 'atrieve.php',
                     'desc'  => 'Release time and absences, and how to enter them in Atrieve'],
                    ['label' => 'Deferred Salary Leave Plan', 'url' => 'deferred-salary.php',
                     'desc'  => 'Save part of your pay now and take a year off later'],
                ]],
            ],
        ],

        'prod' => [
            'label' => 'Professional Development',
            'url'   => 'prod.php',
            'intro' => 'Funding, workshops and calendars for your professional learning.',
            'priority' => ['collab-grant.php', 'events.php', 'calendars.php'],
            'items' => [
                ['label' => 'Collaboration Grant', 'url' => 'collab-grant.php',
                 'desc'  => 'Release days to plan with a colleague',
                 'files' => ['collab-grant-edit.php', 'collab-not-me.php']],
                ['label' => 'Training &amp; workshops', 'url' => 'events.php',
                 'desc'  => 'SURTs and other sessions you can sign up for',
                 'files' => ['event.php', 'event-cancel.php']],
                ['label' => 'School Calendars', 'url' => 'calendars.php',
                 'desc'  => 'SD54 instructional days and Pro-D days'],
                // The same SharePoint page prod.php has always linked. A 401
                // without an SD54 session (checked 6 Nov 2026), so it says so.
                ['label' => 'District Pro-D calendar',
                 'url'   => 'https://bvsd54.sharepoint.com/:u:/r/sites/SD54ProDCalendar/SitePages/Home.aspx?csf=1&web=1&e=noZNN1',
                 'desc'  => 'SD54\'s Pro-D day schedule on SharePoint',
                 'ext'   => true, 'access' => 'district'],
            ],
        ],

        'teaching' => [
            'label' => 'Teaching Resources',
            'url'   => 'library.php',
            'intro' => 'Classroom material shared by members and picked out by the BVTU.',
            'priority' => ['library.php', 'curated.php', 'library-saved.php'],
            'items' => [
                ['label' => 'Resource Library', 'url' => 'library.php',
                 'desc'  => 'Lesson plans, units and rubrics shared by BVTU members',
                 'files' => ['library-resource.php']],
                ['label' => 'Curated Resources', 'url' => 'curated.php',
                 'desc'  => 'Outside resources picked out by the BVTU'],
                ['label' => 'Saved resources', 'url' => 'library-saved.php',
                 'desc'  => 'The library resources you have saved',
                 'access' => 'union'],
                ['label' => 'Share a resource', 'url' => 'library-upload.php',
                 'desc'  => 'Add your own lesson plan or unit to the library',
                 'access' => 'union', 'files' => ['library-edit.php']],
            ],
        ],

        'news' => [
            'label' => 'News &amp; Updates',
            'url'   => 'news-updates.php',
            'intro' => 'What is coming up, what we have said, and how the union works.',
            'priority' => ['whats-on.php', 'newsletter-archive.php', 'contact.php'],
            'groups' => [
                ['label' => 'Updates', 'items' => [
                    ['label' => "What's On", 'url' => 'whats-on.php',
                     'desc'  => 'Union events, meetings and deadlines on one calendar'],
                    ['label' => 'Newsletters', 'url' => 'newsletter-archive.php',
                     'desc'  => 'Every BVTU newsletter, searchable',
                     'access' => 'union', 'files' => ['newsletter.php']],
                    ['label' => '2026 trustee election', 'url' => 'news-updates.php#election-2026',
                     'desc'  => 'Forum recording, candidate answers and the zone map',
                     'files' => ['forum.php', 'trustee-zones.php', 'trustee-candidates.php']],
                ]],
                ['label' => 'Your union', 'items' => [
                    ['label' => 'About the BVTU', 'url' => 'about.php',
                     'desc'  => 'Leadership, meetings, committees and bargaining'],
                    ['label' => 'Constitution &amp; Bylaws', 'url' => 'documents/BVTU-Constitution-and-Bylaws-2026.pdf',
                     'desc'  => 'How the local is run (PDF)', 'ext' => true],
                    ['label' => 'BCTF', 'url' => 'bctf.php',
                     'desc'  => 'What the provincial federation offers members'],
                    ['label' => 'Contact', 'url' => 'contact.php',
                     'desc'  => 'Get in touch with the union office'],
                ]],
            ],
        ],
    ];
}

/** Every item of a section, flattened, whichever shape it was written in. */
function bvtuNavSectionItems(array $section): array {
    if (isset($section['items'])) return $section['items'];
    $out = [];
    foreach ($section['groups'] ?? [] as $g) {
        foreach ($g['items'] as $it) $out[] = $it;
    }
    return $out;
}

/** A section as headed groups; a flat section is one group with no heading. */
function bvtuNavSectionGroups(array $section): array {
    if (isset($section['groups'])) return $section['groups'];
    return [['label' => '', 'items' => $section['items'] ?? []]];
}

/** The script name a site-relative url points at; '' for another site or a file. */
function bvtuNavFileOf(string $url): string {
    if ($url === '' || preg_match('~^(https?:)?//~', $url) || $url[0] === '/') return '';
    $path = preg_replace('~[?#].*$~', '', $url);
    if (substr($path, -4) !== '.php' || strpos($path, '/') !== false) return '';
    return $path;
}

/**
 * Which section the root page being rendered belongs to.
 *
 * Derived from the definition above rather than kept as a second list: the old
 * hand-written map had to be remembered every time a page was added, and twice
 * it was not.
 */
function bvtuNavSection(string $file): string {
    foreach (bvtuNavSections() as $key => $section) {
        if (bvtuNavFileOf($section['url']) === $file) return $key;
        foreach (bvtuNavSectionItems($section) as $it) {
            if (bvtuNavFileOf($it['url']) === $file) return $key;
            if (in_array($file, $it['files'] ?? [], true)) return $key;
        }
    }
    return '';
}

/**
 * Whether an item is the page being shown. An in-page link (#…) is never the
 * current page itself; only the pages it stands for are.
 */
function bvtuNavItemIsCurrent(array $item, string $file): bool {
    if (in_array($file, $item['files'] ?? [], true)) return true;
    if (strpos($item['url'], '#') !== false) return false;
    return $file !== '' && bvtuNavFileOf($item['url']) === $file;
}

/** A nav link's href, left alone when it already points somewhere absolute. */
function bvtuNavHref(string $url, string $base): string {
    if (preg_match('~^(https?:)?//~', $url) || $url[0] === '/') return $url;
    return $base . $url;
}

/** The short tag that says a link needs a login, or '' when it does not. */
function bvtuNavAccessTag(array $item): string {
    $a = $item['access'] ?? '';
    if ($a === 'union')    return ' <span class="nav-access"><span class="sr-only">(needs a </span>Login<span class="sr-only"> to the BVTU portal)</span></span>';
    if ($a === 'district') return ' <span class="nav-access"><span class="sr-only">(needs an </span>SD54 login<span class="sr-only">)</span></span>';
    return '';
}

/**
 * The items a section's dropdown shows, in the order 'priority' lists them.
 * A url in 'priority' that matches no item is ignored rather than invented.
 */
function bvtuNavPriorityItems(array $section): array {
    $byUrl = [];
    foreach (bvtuNavSectionItems($section) as $it) $byUrl[$it['url']] = $it;
    $out = [];
    foreach ($section['priority'] ?? [] as $u) {
        if (isset($byUrl[$u])) $out[] = $byUrl[$u];
    }
    return $out;
}

function bvtuNav(array $o = []): void {
    /*
     * The test banner, printed here on the public side.
     *
     * env.php also injects it into the output buffer, but a public page calls
     * this function AFTER it has already emitted <body> — so that buffer never
     * contains the tag it is looking for and the whole public side of the test
     * copy looked exactly like live. Member pages load auth.php before any
     * output, so the buffer works for them. siteTestBannerHtml() returns the
     * bar once per request, so whichever gets there first wins and it is never
     * printed twice.
     */
    if (function_exists('siteTestBannerHtml')) echo siteTestBannerHtml();

    $base = $o['base'] ?? '';
    $hero = !empty($o['hero']);

    // Whether the dashboard button goes to the dashboard or to sign-in.
    // auth.php is loaded here when a page has not already done so —
    // isLoggedIn() only reads the session and auth.php touches no database, so
    // this costs a public page nothing and does not make it depend on the
    // database being up.
    if (!array_key_exists('loggedIn', $o) && !function_exists('isLoggedIn') && !headers_sent()) {
        // Only before output: isLoggedIn() starts the session, and starting one
        // after the headers have gone prints warnings into the page itself.
        $auth = __DIR__ . '/../members/auth.php';
        if (is_file($auth)) require_once $auth;
    }
    $loggedIn = array_key_exists('loggedIn', $o)
        ? (bool)$o['loggedIn']
        : (function_exists('isLoggedIn') && isLoggedIn());

    $script   = (string)($o['script'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
    $file     = basename($script);
    // Everything under members/ is the portal, whatever its filename.
    $inPortal = strpos($script, '/members/') !== false;
    $section  = $o['active'] ?? ($inPortal ? 'dashboard' : bvtuNavSection($file));
    $sections = bvtuNavSections();
    $h        = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    ?>
  <header class="site-header<?= $hero ? ' hero-mode' : '' ?>">
    <div class="header-inner container">
      <a href="<?= $h($base) ?>index.php" class="logo">
        <img src="<?= $h($base) ?>bvtu-logo.png" alt="BVTU Logo">
        <div class="logo-text">
          <span class="logo-name">Bulkley Valley Teachers' Union</span>
          <span class="logo-sub">Local of the BC Teachers' Federation</span>
        </div>
      </a>
      <button type="button" class="search-btn" data-search-open aria-label="Search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button type="button" class="nav-toggle" aria-label="Menu" aria-expanded="false" aria-controls="main-nav">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="main-nav" aria-label="Main">
        <ul class="nav-top">
          <?php foreach ($sections as $key => $sec): ?>
            <?php
              $isActive = ($section === $key);
              $menuId   = 'nav-menu-' . $key;
              // The section link is "this page" only when no menu item already
              // is — Teaching Resources and Resource Library are both library.php,
              // and one page should be announced as current once.
              $isHere   = !$inPortal && $file === bvtuNavFileOf($sec['url']);
              foreach (bvtuNavSectionItems($sec) as $it) {
                  if ($isHere && bvtuNavItemIsCurrent($it, $file)) { $isHere = false; break; }
              }
            ?>
            <li class="has-dropdown" data-nav-section="<?= $h($key) ?>">
              <a href="<?= $h(bvtuNavHref($sec['url'], $base)) ?>" class="nav-parent<?= $isActive ? ' active' : '' ?>"<?= $isHere ? ' aria-current="page"' : '' ?>><?= $sec['label'] ?></a>
              <?php // A real button, separate from the link: the label still goes to the
                    // section page, and the button is what opens its menu — by mouse,
                    // keyboard or touch, at every width. ?>
              <button type="button" class="nav-sub-toggle" aria-expanded="false" aria-controls="<?= $h($menuId) ?>">
                <span class="sr-only"><?= $sec['label'] ?> menu</span>
                <svg viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false"><path d="M2.5 4.5 6 8l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
              <div class="dropdown" id="<?= $h($menuId) ?>" hidden>
                <ul>
                  <?php foreach (bvtuNavPriorityItems($sec) as $it): ?>
                    <?php
                      $ext     = !empty($it['ext']);
                      $current = !$inPortal && bvtuNavItemIsCurrent($it, $file);
                    ?>
                  <li><a href="<?= $h(bvtuNavHref($it['url'], $base)) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?><?= $current ? ' class="active" aria-current="page"' : '' ?>><?= $it['label'] ?><?= bvtuNavAccessTag($it) ?><?= $ext ? '<span class="sr-only"> (opens in a new tab)</span>' : '' ?></a></li>
                  <?php endforeach; ?>
                  <li class="dropdown-more"><a href="<?= $h(bvtuNavHref($sec['url'], $base)) ?>">Explore all<span class="sr-only"> <?= $sec['label'] ?></span> <span aria-hidden="true">&rarr;</span></a></li>
                </ul>
              </div>
            </li>
          <?php endforeach; ?>
          <li class="nav-dashboard-item">
            <?php // Same label signed in or out. Signed out it goes to sign-in, and says so to a
                  // screen reader; login.php's own redirect rules are untouched. ?>
            <a href="<?= $h($base) ?><?= $loggedIn ? 'members/dashboard.php' : 'members/login.php' ?>"
               class="btn btn-primary nav-dashboard<?= $loggedIn ? ' is-signed-in' : '' ?><?= $section === 'dashboard' ? ' active' : '' ?>">My Dashboard<?php if (!$loggedIn): ?><span class="sr-only"> (sign in with your union account)</span><?php endif; ?></a>
          </li>
        </ul>
      </nav>
    </div>
  </header>
<?php
}

/**
 * The cards for one section, built from the same definition as its menu.
 *
 * Used by the section landing pages and by Member Resources. Links that point
 * back into the page being shown (an in-page #anchor) are left out — the
 * content is already below.
 *
 * $o:
 *   'base'     path prefix, as for bvtuNav()
 *   'headings' show group headings (default true)
 *   'heading'  'h2' or 'h3' for those headings (default 'h2')
 *   'skip'     urls to leave out
 */
function bvtuSectionCards(string $key, array $o = []): void {
    $sections = bvtuNavSections();
    if (!isset($sections[$key])) return;
    $sec      = $sections[$key];
    $base     = $o['base'] ?? '';
    $headings = $o['headings'] ?? true;
    $tag      = (($o['heading'] ?? 'h2') === 'h3') ? 'h3' : 'h2';
    $skip     = $o['skip'] ?? [];
    $self     = bvtuNavFileOf($sec['url']);
    $h        = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

    foreach (bvtuNavSectionGroups($sec) as $group) {
        $items = array_values(array_filter($group['items'], function ($it) use ($self, $skip) {
            if (in_array($it['url'], $skip, true)) return false;
            return !(strpos($it['url'], '#') !== false && bvtuNavFileOf($it['url']) === $self);
        }));
        if (!$items) continue;
        echo '<div class="section-group">';
        if ($headings && $group['label'] !== '') {
            echo '<' . $tag . ' class="section-group-title">' . $group['label'] . '</' . $tag . '>';
        }
        echo '<ul class="nav-cards">';
        foreach ($items as $it) {
            $ext = !empty($it['ext']);
            echo '<li><a class="nav-card" href="' . $h(bvtuNavHref($it['url'], $base)) . '"'
               . ($ext ? ' target="_blank" rel="noopener"' : '') . '>'
               . '<span class="nav-card-title">' . $it['label'] . bvtuNavAccessTag($it)
               . ($ext ? '<span class="sr-only"> (opens in a new tab)</span>' : '') . '</span>'
               . (($it['desc'] ?? '') !== '' ? '<span class="nav-card-desc">' . $h($it['desc']) . '</span>' : '')
               . '</a></li>';
        }
        echo '</ul></div>';
    }
}

/**
 * An always-visible subject directory. Topic headings group direct links;
 * choosing a destination never expands, hides or moves another group.
 * The section definition still supplies every label, destination and access tag.
 * 'heading' can be h3 when embedded under a section's h2 on Member Resources.
 */
function bvtuTopicDirectory(array $keys, array $o = []): void {
    $sections = bvtuNavSections();
    $base = $o['base'] ?? '';
    $tag = ($o['heading'] ?? 'h2') === 'h3' ? 'h3' : 'h2';
    $h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    echo '<div class="topic-directory">';
    foreach ($keys as $key) {
        if (!isset($sections[$key])) continue;
        $sec = $sections[$key];
        $self = bvtuNavFileOf($sec['url']);
        foreach (bvtuNavSectionGroups($sec) as $i => $group) {
            $items = array_values(array_filter($group['items'], function ($it) use ($self) {
                return !(strpos($it['url'], '#') !== false && bvtuNavFileOf($it['url']) === $self);
            }));
            if (!$items) continue;
            $title = $group['label'] !== '' ? $group['label'] : $sec['label'];
            $id = 'directory-' . $key . '-' . $i;
            $showHeading = $group['label'] !== '' || empty($o['skipFlatHeading']);
            echo '<div class="topic-group">';
            if ($showHeading) {
                echo '<' . $tag . ' class="topic-heading" id="' . $h($id) . '">' . $title . '</' . $tag . '>';
            }
            echo '<ul class="topic-links">';
            foreach ($items as $it) {
                $ext = !empty($it['ext']);
                echo '<li><a class="topic-link" href="' . $h(bvtuNavHref($it['url'], $base)) . '"'
                   . ($ext ? ' target="_blank" rel="noopener"' : '') . '>'
                   . '<span>' . $it['label'] . bvtuNavAccessTag($it)
                   . ($ext ? '<span class="sr-only"> (opens in a new tab)</span>' : '') . '</span>'
                   . '<span class="topic-arrow" aria-hidden="true">&rarr;</span></a></li>';
            }
            echo '</ul></div>';
        }
    }
    echo '</div>';
}
