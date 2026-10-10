<?php
/**
 * tools/ca-eval.php — does the Contract Assistant find the right article?
 *
 * Retrieval only: no API call, no cost. Runs the questions in
 * tests/ca-questions.json through the real search in ca-search.php and
 * reports where the expected article landed.
 *
 *   php tools/ca-eval.php            the current ranking
 *   php tools/ca-eval.php --old      the keyword counting it replaced
 *   php tools/ca-eval.php --verbose  with the top four for every question
 *
 * The number that matters is "answerable": the expected article inside the
 * top four, which is all the assistant is given. A question that fails here
 * cannot be answered well no matter how good the model is.
 */
$root = dirname(__DIR__);
require_once $root . '/ca-search.php';

$old     = in_array('--old', $argv, true);
$verbose = in_array('--verbose', $argv, true);

$ca  = array_values(array_filter(
    json_decode(file_get_contents($root . '/ca-content.json'), true) ?: [],
    fn($d) => !caIsIndexPage($d)
));
$lou = json_decode(file_get_contents($root . '/lou-content.json'), true) ?: [];

/* The LOU's hand-written keywords are ranked as part of its title, exactly as
 * ca-ask.php does — the eval has to run the path that actually ships. */
$louRank = array_map(fn($d) => [
    'title'   => trim(($d['title'] ?? '') . ' ' . implode(' ', $d['keywords'] ?? [])),
    'content' => $d['content'] ?? '',
], $lou);

/**
 * Four articles and three letters, ranked separately and then merged — the
 * same two calls the endpoint makes. Ranking one merged corpus instead gave a
 * different answer from the live path, which made the score a description of
 * the test rather than of the assistant.
 */
function caSearchBoth(array $ca, array $lou, array $louRank, array $terms, bool $old): array {
    $out = [];
    foreach (($old ? caRankOld($ca, array_keys($terms), 4) : caRankDocuments($ca, $terms, 4)) as $r) {
        $out[] = ['title' => $ca[$r['i']]['title'] ?? '', 'src' => 'CA'];
    }
    foreach (($old ? caRankOld($louRank, array_keys($terms), 3) : caRankDocuments($louRank, $terms, 3)) as $r) {
        $out[] = ['title' => $lou[$r['i']]['title'] ?? '', 'src' => 'LOU'];
    }
    return $out;
}

$set = json_decode(file_get_contents($root . '/tests/ca-questions.json'), true);

/** The ranking as it was before BM25: count hits, five per title match. */
function caRankOld(array $docs, array $words, int $topN = 4): array {
    $scored = [];
    foreach ($docs as $i => $d) {
        $t = strtolower($d['title'] ?? ''); $c = strtolower($d['content'] ?? '');
        $s = 0;
        foreach ($words as $w) { $s += substr_count($t, $w) * 5 + substr_count($c, $w); }
        if ($s > 0) $scored[] = ['i' => $i, 'score' => $s, 'matched' => 0];
    }
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($scored, 0, $topN);
}

$hitsAt1 = 0; $misses = [];
$hitsAt4 = 0;
foreach ($set['questions'] as $case) {
    $words = caSearchTokens($case['q']);
    $terms = $old ? array_fill_keys($words, 1.0) : caExpandTerms($words);
    $top   = caSearchBoth($ca, $lou, $louRank, $terms, $old);

    $rank = 0;
    foreach ($top as $k => $r) {
        foreach ($case['expect'] as $want) {
            if (stripos($r['title'], $want) !== false) { $rank = $k + 1; break 2; }
        }
    }
    if ($rank === 1) $hitsAt1++;
    if ($rank >= 1)  $hitsAt4++;
    else $misses[] = $case['q'];

    // Rank is over the seven blocks the assistant is actually given.
    printf("%-6s %s\n", $rank ? "#$rank" : 'MISS', $case['q']);
    if ($verbose || (!$rank && !$old)) {
        foreach ($top as $r) {
            printf("          %-4s %-58s\n", $r['src'], mb_substr($r['title'], 0, 58));
        }
    }
}

$n = count($set['questions']);
printf("\n%s ranking\n", $old ? 'OLD (keyword counting)' : 'CURRENT (BM25 + expansion)');
printf("  answerable (expected article among the 7 given): %d of %d  (%d%%)\n", $hitsAt4, $n, round(100 * $hitsAt4 / $n));
printf("  ranked first:                                    %d of %d  (%d%%)\n", $hitsAt1, $n, round(100 * $hitsAt1 / $n));
if ($misses) {
    echo "  still missed:\n";
    foreach ($misses as $m) echo "    - $m\n";
}
