<?php
/**
 * trustee-candidates.php — public page: SD54 trustee candidates' survey responses.
 *
 * Display only. Every question, name, answer and status lives in
 * trustee-candidates-data.php, so responses are published by editing that file
 * and never this one.
 *
 * Nothing is shown until the survey wording has been pasted in. A page that
 * rendered its own shorthand labels would be publishing words BVTU never sent.
 */
require_once __DIR__ . '/members/auth.php';
require_once __DIR__ . '/trustee-candidates-data.php';

$loggedIn = isLoggedIn();
$member   = $loggedIn ? getMember() : null;

/** Ready only when every prompt has real wording. */
function tcReady(): bool {
    foreach (TC_SURVEYS as $s) {
        foreach ($s['questions'] as $q) {
            if (trim($q['prompt']) === '') return false;
        }
    }
    return true;
}
$ready = tcReady();

/** Surname last, for a consistent alphabetical order within each group. */
function tcSortKey(array $c): string {
    $parts = preg_split('/\s+/', trim($c['name']));
    $last  = array_pop($parts);
    return mb_strtolower($last . ' ' . implode(' ', $parts));
}

$byGroup = ['new' => [], 'incumbent' => []];
foreach (TC_CANDIDATES as $c) {
    $g = ($c['group'] ?? 'new') === 'incumbent' ? 'incumbent' : 'new';
    $byGroup[$g][] = $c;
}
foreach ($byGroup as &$list) {
    usort($list, function ($a, $b) { return strcmp(tcSortKey($a), tcSortKey($b)); });
}
unset($list);

/** The questions a candidate is shown against, with combined ones merged. */
function tcQuestionsFor(array $c): array {
    $survey = TC_SURVEYS[($c['group'] ?? 'new') === 'incumbent' ? 'incumbent' : 'new'];
    $ans    = $c['answers'] ?? [];
    $out    = [];
    $eaten  = [];

    foreach ($survey['questions'] as $q) {
        if (in_array($q['id'], $eaten, true)) continue;

        // A combined answer covers this question and the next: show both
        // prompts above the candidate's single answer rather than repeating
        // or splitting their words.
        $combinedId = null;
        foreach (TC_COMBINED as $cid => $members) {
            if ($members[0] === $q['id'] && isset($ans[$cid]) && trim((string)$ans[$cid]) !== '') {
                $combinedId = $cid;
                break;
            }
        }
        if ($combinedId) {
            $prompts = [];
            foreach (TC_COMBINED[$combinedId] as $mid) {
                foreach ($survey['questions'] as $qq) {
                    if ($qq['id'] === $mid) $prompts[] = $qq['prompt'];
                }
                $eaten[] = $mid;
            }
            $out[] = ['id' => $combinedId, 'prompts' => $prompts, 'answer' => (string)$ans[$combinedId]];
            continue;
        }

        $out[] = [
            'id'      => $q['id'],
            'prompts' => array_values(array_filter([$q['prompt'], $q['follow'] ?? null])),
            'answer'  => (string)($ans[$q['id']] ?? ''),
        ];
    }
    return $out;
}

/**
 * Candidates with something to show under one question, for the by-question view.
 *
 * A combined answer is attached to the FIRST question it covers. Under the
 * later one the candidate gets a pointer instead, because reprinting the same
 * words under two questions would misrepresent what they wrote as two answers.
 */
function tcAnswersTo(string $qid, array $candidates): array {
    $rows = [];
    foreach ($candidates as $c) {
        if (($c['status'] ?? '') !== 'responded') continue;
        foreach (tcQuestionsFor($c) as $q) {
            if ($q['id'] === $qid) {
                $rows[] = ['candidate' => $c, 'q' => $q, 'seeAbove' => null];
                break;
            }
            if (array_key_exists($q['id'], TC_COMBINED)
                && in_array($qid, TC_COMBINED[$q['id']], true)) {
                $first = TC_COMBINED[$q['id']][0];
                if ($first === $qid) {
                    $rows[] = ['candidate' => $c, 'q' => $q, 'seeAbove' => null];
                } else {
                    $rows[] = ['candidate' => $c, 'q' => $q, 'seeAbove' => $first];
                }
                break;
            }
        }
    }
    return $rows;
}

/** Verbatim: blank lines are paragraphs, "- " lines are bullets, nothing else. */
function tcRender(string $text): string {
    $text  = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') return '';
    $out   = '';
    foreach (preg_split('/\n\s*\n/', $text) as $block) {
        $lines  = preg_split('/\n/', trim($block));
        $bullet = true;
        foreach ($lines as $l) { if (!preg_match('/^\s*[-•]\s+/', $l)) { $bullet = false; break; } }
        if ($bullet) {
            $out .= '<ul>';
            foreach ($lines as $l) {
                $out .= '<li>' . htmlspecialchars(preg_replace('/^\s*[-•]\s+/', '', $l)) . '</li>';
            }
            $out .= '</ul>';
        } else {
            $out .= '<p>' . nl2br(htmlspecialchars(trim($block))) . '</p>';
        }
    }
    return $out;
}

/** What we can say about a candidate who has not answered. */
function tcStatusNote(array $c): string {
    if (($c['status'] ?? '') === 'declined') {
        return 'Candidate declined to provide responses to the survey questions.';
    }
    if (TC_RECHECKED_AFTER_DEADLINE) {
        return 'No response received by the ' . date('F j', strtotime(TC_DEADLINE)) . ' deadline.';
    }
    $when = TC_LAST_CHECKED !== '' ? date('F j, Y', strtotime(TC_LAST_CHECKED)) : 'the date last checked';
    return 'No response received as of ' . $when . '.';
}

function tcDate(string $d): string { return $d !== '' ? date('F j, Y', strtotime($d)) : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="site-root" content="">
  <title>School Trustee Candidate Responses — Bulkley Valley Teachers' Union</title>
  <meta name="description" content="Responses from SD54 school trustee candidates to the BVTU survey, published as submitted.">
  <link rel="stylesheet" href="css/style.css">
  <link rel="icon" href="favicon.ico">
  <style>
    .tc-wrap { max-width: var(--max-w, 1120px); margin: 0 auto; padding: 2.25rem 1.5rem 4rem; }
    .tc-lede { font-size: 1rem; line-height: 1.8; color: var(--gray-700); max-width: 78ch; }
    .tc-meta { font-size: .85rem; color: var(--gray-500); margin: .6rem 0 1.5rem; }
    .tc-pdf { display: inline-flex; align-items: center; gap: .5rem; background: var(--primary);
              color: #fff; font-weight: 700; border-radius: 8px; padding: .65rem 1.1rem;
              text-decoration: none; font-size: .92rem; margin-bottom: 1.75rem; }
    .tc-pdf:hover { background: var(--primary-dk); color: #fff; }

    .tc-controls { background: #fff; border: 1px solid var(--border); border-radius: 10px;
                   padding: 1rem 1.15rem; margin-bottom: 1.75rem; }
    .tc-controls fieldset { border: 0; padding: 0; margin: 0 0 .9rem; }
    .tc-controls legend { font-size: .72rem; font-weight: 800; text-transform: uppercase;
                          letter-spacing: .05em; color: var(--gray-500); padding: 0; margin-bottom: .45rem; }
    .tc-tabs { display: flex; gap: .4rem; flex-wrap: wrap; }
    .tc-tab { font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer;
              border: 1px solid var(--border); background: #fff; color: var(--gray-700);
              border-radius: 8px; padding: .5rem 1rem; }
    .tc-tab[aria-selected="true"] { background: var(--primary); border-color: var(--primary); color: #fff; }
    .tc-row { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
    .tc-row label { font-size: .85rem; color: var(--gray-700); display: flex; align-items: center; gap: .35rem; }
    .tc-row select, .tc-row input[type=search] {
        font: inherit; font-size: .9rem; border: 1px solid var(--border); border-radius: 8px;
        padding: .45rem .6rem; min-width: 12rem; }
    .tc-reset { font: inherit; font-size: .85rem; background: none; border: 1px solid var(--border);
                border-radius: 8px; padding: .45rem .8rem; cursor: pointer; color: var(--gray-600); }

    .tc-q { border: 1px solid var(--border); border-radius: 10px; background: #fff;
            margin-bottom: .75rem; overflow: hidden; }
    .tc-q > summary { cursor: pointer; padding: .95rem 1.15rem; font-weight: 700; color: var(--ink, #14281d);
                      display: flex; gap: .7rem; align-items: flex-start; list-style: none; }
    .tc-q > summary::-webkit-details-marker { display: none; }
    .tc-q > summary::after { content: '+'; margin-left: auto; font-size: 1.2rem; line-height: 1;
                             color: var(--gray-500); flex-shrink: 0; }
    .tc-q[open] > summary::after { content: '\2212'; }
    .tc-q .tc-qnum { color: var(--primary); font-weight: 800; flex-shrink: 0; }
    .tc-qbody { padding: 0 1.15rem 1.15rem; }

    .tc-ans { border-top: 1px solid var(--gray-100); padding-top: .9rem; margin-top: .9rem; }
    .tc-ans:first-child { border-top: 0; margin-top: 0; }
    .tc-who { font-weight: 800; color: var(--primary); font-size: .95rem; margin: 0 0 .1rem; }
    .tc-grp { font-size: .75rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase;
              letter-spacing: .04em; }
    .tc-ans p { line-height: 1.75; margin: .55rem 0; }
    /* The global stylesheet resets ul to list-style:none; padding-left:0, which
       silently flattened candidates' bulleted answers into bare lines. */
    .tc-ans ul { margin: .55rem 0; padding-left: 1.4rem; line-height: 1.75;
                 list-style: disc outside; }
    .tc-ans li { margin: .25rem 0; }
    .tc-none { color: var(--gray-500); font-style: italic; }
    .tc-upd { font-size: .78rem; color: var(--gray-500); margin-top: .5rem; }
    .tc-prompt { background: var(--off-white, #f6faf7); border-left: 3px solid var(--border);
                 padding: .6rem .85rem; margin: 0 0 .75rem; font-size: .93rem; line-height: 1.7;
                 color: var(--gray-700); }
    .tc-cand { border: 1px solid var(--border); border-radius: 10px; background: #fff;
               padding: 1.15rem 1.25rem; margin-bottom: 1rem; }
    .tc-cand h3 { margin: 0 0 .15rem; font-size: 1.1rem; }
    .tc-empty { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;
                padding: 1.1rem 1.25rem; color: #92400e; line-height: 1.8; }
    .tc-skip { position: absolute; left: -9999px; }
    .tc-skip:focus { position: static; display: inline-block; margin-bottom: 1rem; }
    :is(.tc-tab, .tc-reset, .tc-q > summary, .tc-pdf, .tc-skip):focus-visible {
        outline: 3px solid var(--blue, #1565c0); outline-offset: 2px; }
    @media (max-width: 640px) {
      .tc-wrap { padding: 1.5rem 1rem 3rem; }
      .tc-row select, .tc-row input[type=search] { min-width: 0; width: 100%; }
      .tc-tab { flex: 1 1 auto; text-align: center; }
    }
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
          <li class="has-dropdown"><a href="documents.php" class="active">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li><li><a href="trustee-zones.html">Trustee Zone Map</a></li></ul></li>
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
      <h1>School Trustee Candidate Responses</h1>
      <p>Ahead of the School District 54 trustee election, the BVTU sent a short survey to
         candidates. Their responses are published here as submitted.</p>
    </div>
  </section>

  <main class="tc-wrap">
    <a class="tc-skip" href="#tc-content">Skip to responses</a>

    <p class="tc-lede">
      Responses appear <strong>as submitted</strong> &mdash; we have not corrected or edited them.
    </p>
    <p class="tc-lede">
      New candidates and incumbent trustees received slightly different questions, so their
      answers are kept separate. An incumbent's answer is never shown under a new candidate's
      question, even where the topics overlap.
    </p>
    <p class="tc-lede">
      Publishing these responses is not an endorsement of any candidate. The BVTU does not
      rank or score the answers.
    </p>

    <?php if (TC_LAST_UPDATED !== ''): ?>
    <p class="tc-meta">Last updated <?= htmlspecialchars(tcDate(TC_LAST_UPDATED)) ?>.</p>
    <?php endif; ?>

    <?php if (TC_PDF_URL !== ''): ?>
    <a class="tc-pdf" href="<?= htmlspecialchars(TC_PDF_URL) ?>" target="_blank" rel="noopener">
      &#11015; Download all responses (PDF, by candidate)
    </a>
    <?php endif; ?>

    <div id="tc-content">
    <?php if (!$ready || !TC_CANDIDATES): ?>
      <div class="tc-empty">
        <strong>Responses are not published yet.</strong><br>
        The survey has been sent and answers will appear here once they have been received
        and checked. Please check back closer to the election.
      </div>
    <?php else: ?>

      <div class="tc-controls">
        <fieldset>
          <legend>View</legend>
          <div class="tc-tabs" role="tablist" aria-label="How to read the responses">
            <button class="tc-tab" id="tab-q" role="tab" aria-selected="true" aria-controls="panel-q">
              Compare by question</button>
            <button class="tc-tab" id="tab-c" role="tab" aria-selected="false" aria-controls="panel-c">
              Read by candidate</button>
          </div>
        </fieldset>

        <fieldset>
          <legend>Filter</legend>
          <div class="tc-row">
            <label>Candidate
              <select id="f-cand">
                <option value="">All candidates</option>
                <?php foreach (['new' => 'New candidate', 'incumbent' => 'Incumbent'] as $g => $gl): ?>
                  <?php foreach ($byGroup[$g] as $c): ?>
                  <option value="<?= htmlspecialchars($c['slug']) ?>">
                    <?= htmlspecialchars($c['name']) ?> (<?= $gl ?>)</option>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Group
              <select id="f-group">
                <option value="">Both groups</option>
                <option value="new">New candidates</option>
                <option value="incumbent">Incumbent trustees</option>
              </select>
            </label>
            <label>Search name
              <input type="search" id="f-name" placeholder="Type a name&hellip;" autocomplete="off">
            </label>
            <button type="button" class="tc-reset" id="f-reset">Show all</button>
          </div>
        </fieldset>
      </div>

      <!-- ── Compare by question ───────────────────────────────────────── -->
      <section id="panel-q" role="tabpanel" aria-labelledby="tab-q">
        <?php foreach (TC_SURVEYS as $gkey => $survey): ?>
          <?php if (!$byGroup[$gkey]) continue; ?>
          <section class="tc-group" data-group="<?= $gkey ?>">
            <h2 id="group-<?= $gkey ?>"><?= htmlspecialchars($survey['name']) ?></h2>
            <?php foreach ($survey['questions'] as $i => $q): ?>
              <?php $rows = tcAnswersTo($q['id'], $byGroup[$gkey]); ?>
              <details class="tc-q" id="q-<?= htmlspecialchars($q['id']) ?>">
                <summary>
                  <span class="tc-qnum"><?= $i + 1 ?>.</span>
                  <span><?= htmlspecialchars($q['prompt']) ?></span>
                </summary>
                <div class="tc-qbody">
                  <?php if (!$rows): ?>
                    <p class="tc-none">No candidate in this group has answered this question yet.</p>
                  <?php endif; ?>
                  <?php foreach ($rows as $r): $c = $r['candidate']; ?>
                  <div class="tc-ans" data-cand="<?= htmlspecialchars($c['slug']) ?>"
                       data-group="<?= $gkey ?>" data-name="<?= htmlspecialchars(mb_strtolower($c['name'])) ?>">
                    <p class="tc-who">
                      <a href="#candidate-<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></a>
                    </p>
                    <?php if ($r['seeAbove']): ?>
                      <?php // Their words stay where they wrote them: one answer, shown once. ?>
                      <p class="tc-none">This candidate answered this question together with the
                        previous one. <a href="#q-<?= htmlspecialchars($r['seeAbove']) ?>">Read their
                        answer under that question.</a></p>
                    <?php else: ?>
                      <?php if (count($r['q']['prompts']) > 1): ?>
                        <p class="tc-prompt">Answered together with the related follow-up prompt;
                          their single answer is shown in full.</p>
                      <?php endif; ?>
                      <?php $a = tcRender($r['q']['answer']); ?>
                      <?= $a !== '' ? $a : '<p class="tc-none">No answer provided to this question.</p>' ?>
                    <?php endif; ?>
                    <?php if (!empty($c['updated'])): ?>
                      <p class="tc-upd">Updated <?= htmlspecialchars(tcDate($c['updated'])) ?>.</p>
                    <?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
              </details>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </section>

      <!-- ── Read by candidate ─────────────────────────────────────────── -->
      <section id="panel-c" role="tabpanel" aria-labelledby="tab-c" hidden>
        <?php foreach (TC_SURVEYS as $gkey => $survey): ?>
          <?php if (!$byGroup[$gkey]) continue; ?>
          <section class="tc-group" data-group="<?= $gkey ?>">
            <h2><?= htmlspecialchars($survey['name']) ?></h2>
            <?php foreach ($byGroup[$gkey] as $c): ?>
            <article class="tc-cand" id="candidate-<?= htmlspecialchars($c['slug']) ?>"
                     data-cand="<?= htmlspecialchars($c['slug']) ?>" data-group="<?= $gkey ?>"
                     data-name="<?= htmlspecialchars(mb_strtolower($c['name'])) ?>">
              <h3><?= htmlspecialchars($c['name']) ?></h3>
              <p class="tc-grp"><?= $gkey === 'incumbent' ? 'Incumbent trustee' : 'New candidate' ?></p>

              <?php if (($c['status'] ?? '') !== 'responded'): ?>
                <p class="tc-none" style="margin-top:.8rem;"><?= htmlspecialchars(tcStatusNote($c)) ?></p>
              <?php else: ?>
                <?php if (!empty($c['updated'])): ?>
                  <p class="tc-upd">Updated <?= htmlspecialchars(tcDate($c['updated'])) ?>.</p>
                <?php endif; ?>
                <?php foreach (tcQuestionsFor($c) as $n => $q): ?>
                <div class="tc-ans">
                  <?php foreach ($q['prompts'] as $p): ?>
                    <p class="tc-prompt"><?= htmlspecialchars($p) ?></p>
                  <?php endforeach; ?>
                  <?php $a = tcRender($q['answer']); ?>
                  <?= $a !== '' ? $a : '<p class="tc-none">No answer provided to this question.</p>' ?>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
          </section>
        <?php endforeach; ?>
      </section>

    <?php endif; ?>
    </div>
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
          <li class="has-dropdown"><a href="documents.php">Documents</a><ul class="dropdown"><li><a href="documents.php">All Documents</a></li><li><a href="collective-agreement.php">Collective Agreement</a></li><li><a href="lous.php">Letters of Understanding</a></li><li><a href="ca-assistant.php">Contract Assistant</a></li><li><a href="documents/BVTU-Constitution-and-Bylaws-2026.pdf" target="_blank">Constitution &amp; Bylaws</a></li><li><a href="calendars.php">School Calendars</a></li><li><a href="trustee-zones.html">Trustee Zone Map</a></li></ul></li>
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
        <p>© 2026 Bulkley Valley Teachers' Union · Local of the BC Teachers' Federation</p>
      </div>
    </div>
  </footer>

  <script src="js/site.js"></script>
  <script src="js/search.js"></script>

  <script>
  (function () {
    var tabQ = document.getElementById('tab-q'), tabC = document.getElementById('tab-c');
    if (!tabQ) return;                       // responses not published yet
    var panQ = document.getElementById('panel-q'), panC = document.getElementById('panel-c');

    function show(which) {
      var q = which === 'q';
      tabQ.setAttribute('aria-selected', q); tabC.setAttribute('aria-selected', !q);
      panQ.hidden = !q; panC.hidden = q;
    }
    tabQ.addEventListener('click', function () { show('q'); });
    tabC.addEventListener('click', function () { show('c'); });
    // Left/right arrows move between tabs, as a tablist should.
    [tabQ, tabC].forEach(function (t, i, all) {
      t.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
        e.preventDefault();
        var next = all[(i + (e.key === 'ArrowRight' ? 1 : all.length - 1)) % all.length];
        next.focus(); next.click();
      });
    });

    var fc = document.getElementById('f-cand'), fg = document.getElementById('f-group'),
        fn = document.getElementById('f-name');

    function apply() {
      var cand = fc.value, grp = fg.value, name = fn.value.trim().toLowerCase();
      document.querySelectorAll('[data-cand]').forEach(function (el) {
        var ok = (!cand || el.dataset.cand === cand)
              && (!grp  || el.dataset.group === grp)
              && (!name || (el.dataset.name || '').indexOf(name) !== -1);
        el.hidden = !ok;
      });
      // A group heading with nothing left under it is noise.
      document.querySelectorAll('.tc-group').forEach(function (sec) {
        sec.hidden = !!grp && sec.dataset.group !== grp;
      });
      // Say so when a question has nothing left to show, rather than looking broken.
      document.querySelectorAll('#panel-q .tc-q').forEach(function (d) {
        var any = d.querySelector('.tc-ans:not([hidden])');
        var msg = d.querySelector('.tc-filtered');
        if (!any && !msg) {
          msg = document.createElement('p');
          msg.className = 'tc-none tc-filtered';
          msg.textContent = 'No answers match the current filter.';
          d.querySelector('.tc-qbody').appendChild(msg);
        }
        if (msg) msg.hidden = !!any;
      });
    }
    [fc, fg].forEach(function (el) { el.addEventListener('change', apply); });
    fn.addEventListener('input', apply);
    document.getElementById('f-reset').addEventListener('click', function () {
      fc.value = ''; fg.value = ''; fn.value = ''; apply();
    });

    // A shared link should land on the thing it names, open and in the right view.
    function openTarget() {
      var h = location.hash;
      if (!h || h.length < 2) return;
      var el = document.querySelector(h);
      if (!el) return;
      if (h.indexOf('#candidate-') === 0) show('c');
      else { show('q'); if (el.tagName === 'DETAILS') el.open = true; }
      el.scrollIntoView();
    }
    window.addEventListener('hashchange', openTarget);
    openTarget();
  })();
  </script>
</body>
</html>
