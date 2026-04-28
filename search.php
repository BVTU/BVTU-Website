<?php
/**
 * search.php — Algolia full-results page
 * Handles ?q= queries, runs server-side Algolia query, renders results.
 */
require_once __DIR__ . '/members/auth.php';
$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

$ALGOLIA_APP_ID    = 'IUEMJN3YMB';
$ALGOLIA_SEARCH_KEY = 'f743d9e8113b01fbb593d0d5ea592854';
$ALGOLIA_INDEX     = 'bvtu_content';

$query = trim($_GET['q'] ?? '');
$hits  = [];
$error = false;

if ($query !== '') {
    $url = "https://{$ALGOLIA_APP_ID}-dsn.algolia.net/1/indexes/{$ALGOLIA_INDEX}/query";
    $payload = json_encode([
        'query'                => $query,
        'hitsPerPage'          => 20,
        'attributesToHighlight'=> ['title', 'content'],
        'attributesToSnippet'  => ['content:40'],
        'snippetEllipsisText'  => '…',
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", [
                'Content-Type: application/json',
                "X-Algolia-Application-Id: {$ALGOLIA_APP_ID}",
                "X-Algolia-API-Key: {$ALGOLIA_SEARCH_KEY}",
            ]),
            'content' => $payload,
            'timeout' => 5,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        $error = true;
    } else {
        $data = json_decode($resp, true);
        $hits = $data['hits'] ?? [];
    }
}

function highlightValue(array $hit, string $attr): string {
    return $hit['_highlightResult'][$attr]['value']
        ?? htmlspecialchars($hit[$attr] ?? '', ENT_QUOTES);
}
function snippetValue(array $hit, string $attr): string {
    return $hit['_snippetResult'][$attr]['value'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="site-root" content="">
  <title><?= $query ? 'Search: ' . htmlspecialchars($query) . ' — BVTU' : 'Search — BVTU' ?></title>
  <meta name="robots" content="noindex">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .search-page-hero {
      background: linear-gradient(140deg, var(--primary-dk) 0%, var(--primary) 100%);
      padding: 2.25rem 1.5rem;
      color: var(--white);
    }
    .search-page-hero h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: 1rem; }

    .search-form {
      display: flex;
      gap: .5rem;
      max-width: 600px;
    }
    .search-form input[type="search"] {
      flex: 1;
      padding: .72rem 1rem;
      font-size: 1rem;
      font-family: var(--font);
      border: none;
      border-radius: var(--radius-s);
      outline: none;
      background: rgba(255,255,255,.95);
      color: var(--text);
      min-width: 0;
    }
    .search-form button {
      padding: .72rem 1.3rem;
      background: var(--blue);
      color: var(--white);
      border: none;
      border-radius: var(--radius-s);
      font-size: .95rem;
      font-weight: 600;
      cursor: pointer;
      transition: background .16s;
      white-space: nowrap;
    }
    .search-form button:hover { background: var(--blue-lt); }

    .search-meta {
      font-size: .88rem;
      color: var(--gray-500);
      margin-bottom: 1.5rem;
    }
    .search-meta strong { color: var(--text); }

    .search-result {
      display: flex;
      gap: 1rem;
      padding: 1.1rem 0;
      border-bottom: 1px solid var(--border);
      align-items: flex-start;
    }
    .search-result:last-child { border-bottom: none; }
    .sr-icon {
      flex-shrink: 0;
      margin-top: .2rem;
      color: var(--blue);
      display: flex;
    }
    .sr-icon--document { color: var(--primary); }
    .sr-body { display: flex; flex-direction: column; gap: .3rem; flex: 1; min-width: 0; }
    .sr-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--primary);
      text-decoration: none;
      line-height: 1.3;
    }
    .sr-title:hover { text-decoration: underline; }
    .sr-title mark { background: transparent; color: var(--blue); font-weight: 800; }
    .sr-url {
      font-size: .78rem;
      color: var(--gray-500);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .sr-snippet {
      font-size: .9rem;
      color: var(--gray-700);
      line-height: 1.55;
    }
    .sr-snippet mark { background: #fff3cd; color: var(--text); border-radius: 2px; padding: 0 .15rem; }
    .sr-badges { display: flex; gap: .5rem; align-items: center; margin-top: .2rem; }
    .sr-badge {
      font-size: .72rem;
      font-weight: 600;
      padding: .18rem .52rem;
      border-radius: 100px;
    }
    .sr-badge--type {
      background: var(--gray-100);
      color: var(--gray-700);
      border: 1px solid var(--gray-200);
      text-transform: capitalize;
    }
    .sr-badge--members {
      background: var(--accent);
      color: var(--primary);
      border: 1px solid var(--border);
    }

    .search-empty {
      padding: 3rem 0;
      text-align: center;
      color: var(--gray-500);
    }
    .search-empty svg { display: block; margin: 0 auto 1rem; color: var(--gray-200); }
    .search-empty h2 { font-size: 1.2rem; color: var(--text); margin-bottom: .5rem; }
    .search-empty p { font-size: .93rem; }

    .search-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: var(--radius-s);
      padding: 1rem 1.25rem;
      color: #991b1b;
      font-size: .93rem;
      margin-bottom: 1.5rem;
    }

    .search-powered {
      display: flex;
      align-items: center;
      gap: .5rem;
      margin-top: 2rem;
      font-size: .8rem;
      color: var(--gray-500);
    }
    .search-powered a { color: var(--gray-500); }
    .search-powered a:hover { color: var(--text); }
  </style>
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
          <li><a href="about.php">About</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
          <li class="has-dropdown">
            <a href="members.php">Members</a>
            <ul class="dropdown">
              <li><a href="members.php">Member Resources</a></li>
              <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
            </ul>
          </li>
          <li><a href="prod.php">PRO-D</a></li>
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="<?= $loggedIn ? '/members/dashboard.php' : 'members/login.php' ?>"
              class="btn btn-primary"
              style="padding:.4rem .9rem;font-size:.88rem;margin-left:.5rem;<?= $loggedIn ? 'background:#1a6b35;border-color:#1a6b35;' : '' ?>">
            <?= $loggedIn ? 'My Dashboard' : 'Member Login' ?>
          </a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="search-page-hero">
    <div class="container">
      <h1>Search BVTU</h1>
      <form class="search-form" method="get" action="search.php" role="search">
        <input type="search"
               name="q"
               value="<?= htmlspecialchars($query) ?>"
               placeholder="Search pages, documents, resources…"
               aria-label="Search"
               autofocus>
        <button type="submit">Search</button>
      </form>
    </div>
  </section>

  <main class="page-content">
    <div class="container">

      <?php if ($error): ?>
        <div class="search-error">
          Search is temporarily unavailable. Please try again in a moment.
        </div>
      <?php endif; ?>

      <?php if ($query && !$error): ?>
        <p class="search-meta">
          <?= count($hits) ?> result<?= count($hits) !== 1 ? 's' : '' ?> for
          <strong>"<?= htmlspecialchars($query) ?>"</strong>
        </p>
      <?php endif; ?>

      <?php if ($query && !$error && empty($hits)): ?>
        <div class="search-empty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <h2>No results found</h2>
          <p>Try different keywords, or browse the site navigation above.</p>
        </div>
      <?php endif; ?>

      <?php if ($hits): ?>
        <div class="search-results">
          <?php foreach ($hits as $hit):
            $type    = htmlspecialchars($hit['type'] ?? 'page');
            $url     = htmlspecialchars($hit['url'] ?? '#');
            $members = !empty($hit['members_only']);
            $title   = highlightValue($hit, 'title');
            $snippet = snippetValue($hit, 'content');
            $iconPage = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>';
            $iconDoc  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
            $icon     = ($type === 'document') ? $iconDoc : $iconPage;
            $iconClass = "sr-icon sr-icon--{$type}";
          ?>
            <div class="search-result">
              <span class="<?= $iconClass ?>"><?= $icon ?></span>
              <div class="sr-body">
                <a href="<?= $url ?>" class="sr-title"><?= $title ?></a>
                <span class="sr-url"><?= $url ?></span>
                <?php if ($snippet): ?>
                  <p class="sr-snippet"><?= $snippet ?></p>
                <?php endif; ?>
                <div class="sr-badges">
                  <span class="sr-badge sr-badge--type"><?= $type ?></span>
                  <?php if ($members): ?>
                    <span class="sr-badge sr-badge--members">Members only</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$query && !$error): ?>
        <div class="search-empty" style="padding:4rem 0;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="52" height="52">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg>
          <h2>What are you looking for?</h2>
          <p>Type a keyword above to search all BVTU pages and documents.</p>
        </div>
      <?php endif; ?>

      <div class="search-powered">
        <span>Search powered by</span>
        <a href="https://www.algolia.com" target="_blank" rel="noopener">Algolia</a>
      </div>

    </div>
  </main>

  <footer class="site-footer">
    <div class="footer-grid container">
      <div>
        <h4>Bulkley Valley Teachers' Union</h4>
        <p>Local of the BC Teachers' Federation<br>School District 54 — Smithers, BC</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <ul class="footer-nav-list">
          <li><a href="about.php">About BVTU</a></li>
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li></ul></li>
          <li><a href="members.php">Member Resources</a></li>
          <li><a href="remedy-tracker.php">Remedy Tracker</a></li>
              <li><a href="collab-grant.php">Collaboration Grant</a></li>
          <li><a href="prod.php">PRO-D</a></li>
        </ul>
      </div>
      <div>
        <h4>Resources</h4>
        <ul class="footer-nav-list">
          <li><a href="health-safety.php">Health &amp; Safety</a></li>
          <li><a href="bctf.php">BCTF</a></li>
          <li><a href="members/login.php">Member Login</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="container">
        <p>© 2026 Bulkley Valley Teachers' Union · Smithers, BC</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>
</body>
</html>
