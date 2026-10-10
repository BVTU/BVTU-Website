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
        $score = 0;
        foreach ($words as $w) $score += substr_count($low, $w);
        $scored[] = ['i' => $i, 'score' => $score, 'len' => mb_strlen($piece)];
    }
    usort($scored, function ($a, $b) {
        if ($a['score'] !== $b['score']) return $b['score'] - $a['score'];
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
        if (isset($pieces[$cand['i']]) || $cand['score'] === 0) continue;
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
