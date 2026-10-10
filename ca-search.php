<?php
/**
 * ca-search.php — finding the right part of the collective agreement.
 *
 * Shared because it was not: ca-ask.php (the Contract Assistant) and ask.php
 * (the site-wide Search & Ask AI) each had their own copy of the same keyword
 * search over the same ca-content.json, and the same defects in it. Fixing one
 * left the other answering badly from the same file.
 */

/**
 * The words worth searching for in a member's question.
 *
 * Split on anything that is not a letter or a digit, keeping internal dots so
 * an article reference survives whole.
 *
 * Splitting on whitespace alone left the punctuation attached, and in a
 * question the decisive word is nearly always the last one — the one carrying
 * the question mark. "grievance?" matches none of the 188 documents;
 * "grievance" matches eleven, Article A.6 among them. Asking how to file a
 * grievance returned Harassment, Expedited Arbitration and a schedule, and
 * never the grievance procedure.
 */
function caSearchTokens(string $question): array {
    $stopWords = ['the','and','for','are','was','that','this','with','have','from',
                  'they','will','been','has','its','not','but','can','you','your',
                  'our','their','what','how','much','many','who','when','where',
                  'why','does','did','get','tell','about','also','into','more',
                  'per','than','just','then','some','any'];

    $searchable = preg_replace(
        '/\b(how much|how many|how do i|how can i|what is|what are|what does|can i|do i|am i|when can|when do|where is|who is|is there|is it|tell me about|explain|what about|does the|should i)\b/i',
        ' ', $question
    );
    preg_match_all('/[a-z0-9]+(?:\.[a-z0-9]+)*/u', strtolower($searchable), $m);
    return array_values(array_filter(
        $m[0],
        // Two characters is too short to mean anything on its own, unless it is
        // a clause reference like "d.4" that the member has typed out.
        fn($w) => (strlen($w) >= 3 || strpos($w, '.') !== false) && !in_array($w, $stopWords, true)
    ));
}

/**
 * The alphabetical index at the back of the PDF, which was indexed as content.
 *
 * Ten entries titled "A", "B", "C" … each a list of headings with their page
 * numbers glued on ("SALARY24 Salary No Cut39"). They match almost any question
 * and pushed real articles out of the top four: asking what happens after a
 * layoff returned documents called "P" and "L" while Article C.21 never
 * appeared. They are gone from ca-content.json; this keeps them out if whatever
 * built that file is ever run again.
 */
function caIsIndexPage(array $doc): bool {
    $t = trim((string)($doc['title'] ?? ''));
    return mb_strlen($t) === 1 && preg_match('/^[a-z]$/i', $t) === 1;
}

/**
 * The parts of an article that answer the question, rather than its first
 * 1800 characters.
 *
 * Blind truncation threw away 82% of the harassment article, 76% of the
 * grievance procedure and 61% of salary placement — and the clause numbers
 * live in that discarded text. The prompt then forbids any claim without a
 * clause number, so the assistant said it could not find one and told the
 * member to ask the president. The vagueness was manufactured here.
 *
 * Splits on clause markers, which is how this contract is written and the unit
 * a teacher wants cited: "2.Step One", "a.The local or", "(iii) …". Keeps the
 * opening for context, then the highest-scoring clauses, reassembled in the
 * order they appear with a marker where something was left out.
 */
function caRelevantExtract(string $content, array $words, int $budget = 2600): string {
    /* Accepts either a plain list of words or the term => weight map the
     * ranking uses. Flattening the map with array_keys() threw the weights
     * away, so a word carried over from an earlier question picked clauses
     * out of THIS article as hard as the question just asked. */
    $weights = [];
    foreach ($words as $k => $v) {
        if (is_string($k)) { $weights[$k] = (float)$v; } else { $weights[(string)$v] = 1.0; }
    }
    $content = trim(preg_replace('/\s+/', ' ', $content));
    if (mb_strlen($content) <= $budget) return $content;

    // Before a clause marker: a number, a letter, or a roman numeral, then a
    // dot, then something that starts a sentence.
    $parts = preg_split(
        '/(?=(?:\s|^)(?:\d{1,2}|[a-z]|[ivx]{1,4})\.(?=[A-Z0-9"\x{201C}]))/u',
        $content, -1, PREG_SPLIT_NO_EMPTY
    );
    if (!$parts || count($parts) < 2) {
        // Not clause-numbered — fall back to sentences.
        $parts = preg_split('/(?<=\.)\s+(?=[A-Z])/u', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [$content];
    }

    // Very short fragments are headings; glue them to what follows so a heading
    // is never kept on its own, and never separated from its clause.
    $merged = [];
    foreach ($parts as $piece) {
        if ($merged && mb_strlen(end($merged)) < 90) {
            $merged[count($merged) - 1] .= ' ' . $piece;
        } else {
            $merged[] = $piece;
        }
    }

    /* Anything still enormous gets broken into sentences.
     *
     * Not every article is clause-numbered. Harassment (E.2) splits into two
     * pieces for ten thousand characters, so the opening alone overran the
     * lead cap and the definitions section — the part someone asking about
     * harassment actually needs — fell in the gap between the cap and the
     * second piece. A piece that cannot be chosen between is not a choice. */
    $fine = [];
    foreach ($merged as $piece) {
        if (mb_strlen($piece) <= 1200) { $fine[] = $piece; continue; }
        $sentences = preg_split('/(?<=\.)\s+(?=[A-Z])/u', $piece, -1, PREG_SPLIT_NO_EMPTY) ?: [$piece];
        $buf = '';
        foreach ($sentences as $sentence) {
            if (mb_strlen($buf) + mb_strlen($sentence) > 600 && $buf !== '') {
                $fine[] = $buf;
                $buf = '';
            }
            $buf = $buf === '' ? $sentence : $buf . ' ' . $sentence;
        }
        if ($buf !== '') $fine[] = $buf;
    }
    $merged = $fine;

    $scored = [];
    foreach ($merged as $i => $piece) {
        $low = strtolower($piece);
        $score = 0.0;
        foreach ($weights as $w => $weight) $score += $weight * substr_count($low, $w);
        $scored[] = ['i' => $i, 'score' => $score, 'len' => mb_strlen($piece)];
    }
    usort($scored, function ($a, $b) {
        if ($a['score'] != $b['score']) return $b['score'] <=> $a['score'];
        return $a['i'] - $b['i'];          // a tie goes to whichever comes first
    });

    /* The opening always, so the extract still reads as part of this article —
     * but capped. An article with few clause markers can come back as one
     * enormous piece, and keeping it whole blew the budget before a single
     * relevant clause had been looked at. */
    $lead = $merged[0];
    $leadCap = (int)min(700, $budget);
    if (mb_strlen($lead) > $leadCap) $lead = mb_substr($lead, 0, $leadCap) . ' […]';

    $pieces = [0 => $lead];
    $used   = mb_strlen($lead);
    foreach ($scored as $cand) {
        if (isset($pieces[$cand['i']]) || $cand['score'] <= 0) continue;
        $room = $budget - $used;
        if ($room < 200) break;                      // no room left worth using
        $piece = $merged[$cand['i']];
        /* A clause longer than the room left is trimmed rather than dropped:
         * the best-matching clause in the article is the last thing that should
         * be thrown away for being long. */
        if (mb_strlen($piece) > $room) $piece = mb_substr($piece, 0, $room) . ' […]';
        $pieces[$cand['i']] = $piece;
        $used += mb_strlen($piece);
    }

    ksort($pieces);
    $out  = [];
    $prev = -1;
    foreach ($pieces as $i => $piece) {
        if ($prev >= 0 && $i !== $prev + 1) $out[] = '[…]';
        $out[] = trim($piece);
        $prev = $i;
    }
    if ($prev < count($merged) - 1) $out[] = '[…]';
    return implode(' ', $out);
}

/*
 * BM25 parameters, swept against tests/ca-questions.json with
 * tools/ca-eval.php rather than chosen by taste.
 *
 * Taken from the middle of a broad plateau, not the best cell: with 26
 * questions the best cell is mostly luck. Every neighbour of these scores 26
 * answerable and 19+ first. The best cell was b = 0.2 at 26/23, and it was
 * not taken on purpose — a low b means almost no discount for length, which
 * is the parameter most likely to misbehave on a question nobody has thought
 * of yet. Two more first-places on a set of 26 is not worth that.
 *
 * k1 is high, meaning repetition keeps counting. In web search that would be
 * wrong — repetition is spam. Here the corpus is topical legal articles, and
 * the article that says "grievance" thirty times IS the grievance article.
 */
define('CA_BM25_K1',    8.0);
define('CA_BM25_B',     0.35);
define('CA_BM25_TITLE', 8.0);

/* ── Ranking ────────────────────────────────────────────────────────────────
 *
 * Counting keyword hits and adding five per title match put
 * "ARTICLE C.25: PART TIME TEACHERS' RIGHTS" above
 * "ARTICLE D.4: PREPARATION TIME" for the question "how much prep time do I
 * have", because "time" appears in 55 of 188 articles and was worth exactly
 * as much as "prep", which appears in 14 — and because a long article wins a
 * counting contest by being long.
 *
 * BM25 fixes both: a term is worth more the rarer it is, and a count is
 * discounted by how much text it had to appear in.
 */

/** How many documents contain this term. Cached: the corpus does not change. */
function caDocFreq(array $docs, string $term): int {
    static $cache = [];
    /* Keyed by what the corpus IS, not how big it is. Two corpora are ranked
     * per request — the agreement and the letters — and treating them as the
     * same one the day they are the same length would return the wrong rarity
     * for every term. Size plus the first and last headings, which is O(1)
     * and cannot collide between these two. */
    $first = $docs[array_key_first($docs)]['title'] ?? '';
    $last  = $docs[array_key_last($docs)]['title']  ?? '';
    $key   = count($docs) . ':' . crc32($first . "\0" . $last) . '|' . $term;
    if (isset($cache[$key])) return $cache[$key];
    $n = 0;
    foreach ($docs as $d) {
        if (stripos(($d['title'] ?? '') . ' ' . ($d['content'] ?? ''), $term) !== false) $n++;
    }
    return $cache[$key] = $n;
}

/**
 * The words to search for, each with what it is worth.
 *
 * The member's words first, then what the contract calls the same thing.
 * Nobody types "teacher teaching on call" and the agreement never says
 * "TTOC" in a heading; "prep" and "preparation" are the same question.
 *
 * Expansions are worth less than what was actually typed, so a document that
 * matches the member's own words still wins.
 */
function caExpandTerms(array $words): array {
    $groups = [
        ['prep', 'preparation'],
        ['ttoc', 'teacher teaching on call', 'teacher on call', 'on call', 'substitute'],
        ['sick', 'illness', 'ill health'],
        ['layoff', 'laid off', 'redundan', 'severance', 're-engagement'],
        ['maternity', 'pregnancy', 'parental', 'adoption'],
        ['grievance', 'dispute', 'arbitration'],
        ['harass', 'bully', 'discriminat'],
        ['seniority', 'years of service', 'service credit'],
        ['professional development', 'pro-d', 'prod'],
        ['collaboration', 'collaborative', 'collab'],
        ['salary', 'pay', 'wage', 'remuneration'],
        ['class size', 'class composition'],
        ['release time', 'released time'],
        ['leave of absence', 'leave'],
        ['benefit', 'extended health', 'dental', 'medical'],
        ['evaluation', 'appraisal', 'report on teacher'],
        ['transfer', 'posting', 'vacancy'],
        ['hour', 'workday', 'work day', 'instructional time'],
    ];

    $terms = [];
    foreach ($words as $w) {
        $terms[$w] = max($terms[$w] ?? 0, 1.0);

        /* A crude stem, which works here because matching is by substring:
         * "harassed" -> "harass" finds "harassment", "days" -> "day" finds
         * "day". Only when enough is left to still mean something. */
        foreach (['ies' => 'y', 'es' => '', 's' => '', 'ing' => '', 'ed' => ''] as $suffix => $repl) {
            $len = strlen($suffix);
            // What is LEFT must still be a word: "days" -> "day" is the
            // example in the docblock, and > $len + 3 excluded it.
            if (strlen($w) - $len >= 3 && substr($w, -$len) === $suffix) {
                $stem = substr($w, 0, -$len) . $repl;
                $terms[$stem] = max($terms[$stem] ?? 0, 0.9);
                break;
            }
        }
    }

    foreach ($groups as $group) {
        $hit = false;
        foreach ($group as $g) {
            foreach (array_keys($terms) as $t) {
                if ($t === $g || strpos($g, $t) === 0 || strpos($t, $g) === 0) { $hit = true; break 2; }
            }
        }
        if (!$hit) continue;
        foreach ($group as $g) {
            if (!isset($terms[$g])) $terms[$g] = 0.65;
        }
    }
    return $terms;
}

/**
 * Rank documents for a set of weighted terms, BM25 style.
 *
 * $titleBoost counts a hit in the heading as several hits in the body, which
 * is right — but as part of the frequency, so it is still discounted by
 * rarity and by length rather than being a flat bonus a long article can
 * out-shout.
 */
function caRankDocuments(array $docs, array $terms, int $topN = 4, ?float $titleBoost = null,
                         ?float $k1 = null, ?float $b = null): array {
    if (!$docs || !$terms) return [];

    $N = count($docs);
    $lens = [];
    $total = 0;
    foreach ($docs as $i => $d) {
        $lens[$i] = max(1, mb_strlen((string)($d['content'] ?? '')));
        $total += $lens[$i];
    }
    $avg = $total / $N;

    /* Tuned against tests/ca-questions.json with tools/ca-eval.php, not
     * guessed: see the sweep note above CA_BM25_K1. */
    $titleBoost = $titleBoost ?? CA_BM25_TITLE;
    $k1 = $k1 ?? CA_BM25_K1;   // how fast repeated hits stop helping
    $b  = $b  ?? CA_BM25_B;    // how hard length is discounted

    $idf = [];
    foreach ($terms as $term => $w) {
        $df = caDocFreq($docs, $term);
        // Never negative: a term in every document is worth nothing, not less.
        $idf[$term] = max(0.0, log(1 + ($N - $df + 0.5) / ($df + 0.5)));
    }

    /* How much of the question a document covers, counted over the words the
     * member actually typed — not the expansions. Sorting by the number of
     * terms matched instead made a document that brushes several expanded
     * synonyms beat the one that answers the question, and cost six places
     * across the test set. It belongs in the score, not above it. */
    $typedTotal = 0.0;
    foreach ($terms as $term => $w) {
        // A term in no document at all — a typo, or a word the contract never
        // uses — has a high IDF and can never be matched. Counting it in the
        // total pinned coverage below 1 for every document and flattened the
        // ranking on any question containing one.
        if ($w >= 1.0 && $idf[$term] > 0 && caDocFreq($docs, $term) > 0) $typedTotal += $idf[$term];
    }

    $scored = [];
    foreach ($docs as $i => $d) {
        $title = strtolower((string)($d['title'] ?? ''));
        $body  = strtolower((string)($d['content'] ?? ''));
        $score = 0.0;
        $typedHit = 0.0;
        $matched  = 0;
        foreach ($terms as $term => $weight) {
            if ($idf[$term] <= 0) continue;
            $tf = substr_count($body, $term) + $titleBoost * substr_count($title, $term);
            if ($tf <= 0) continue;
            $matched++;
            if ($weight >= 1.0) $typedHit += $idf[$term];
            $norm   = $tf * ($k1 + 1) / ($tf + $k1 * (1 - $b + $b * $lens[$i] / $avg));
            $score += $weight * $idf[$term] * $norm;
        }
        if ($score <= 0) continue;
        $coverage = $typedTotal > 0 ? $typedHit / $typedTotal : 1.0;
        $score *= 0.4 + 0.6 * $coverage;
        $scored[] = ['i' => $i, 'score' => $score, 'matched' => $matched];
    }

    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($scored, 0, $topN);
}

/**
 * The terms for this turn, including what the conversation was already about.
 *
 * "What about for part-time?" has nothing in it to search for. Tokenising the
 * latest message alone made every follow-up a fresh, contextless query, and
 * the answer wandered off the subject the member was still asking about.
 * Earlier turns count for less than the question just asked.
 */
function caTermsForTurn(string $question, array $history = []): array {
    $terms = caExpandTerms(caSearchTokens($question));

    $earlier = [];
    foreach (array_reverse($history) as $h) {
        if (($h['role'] ?? '') !== 'user') continue;
        $earlier[] = (string)($h['content'] ?? '');
        if (count($earlier) >= 2) break;      // two turns back is plenty
    }
    foreach ($earlier as $depth => $text) {
        $fade = $depth === 0 ? 0.5 : 0.3;
        foreach (caExpandTerms(caSearchTokens($text)) as $t => $w) {
            $terms[$t] = max($terms[$t] ?? 0, $w * $fade);
        }
    }
    return $terms;
}
