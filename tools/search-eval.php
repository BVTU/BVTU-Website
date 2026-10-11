<?php
/**
 * tools/search-eval.php — does the site search find what members type?
 *
 * Queries the LIVE Algolia index, so it measures what a member actually gets
 * rather than what the indexer intended. Run it before and after a reindex;
 * the numbers only move when the index does.
 *
 *   php tools/search-eval.php
 *   php tools/search-eval.php --verbose    show the top hits for every query
 *
 * Search-only key, same one the public site uses. No writes, no cost.
 */
$root    = dirname(__DIR__);
$verbose = in_array('--verbose', $argv, true);

const APP_ID     = 'IUEMJN3YMB';
const SEARCH_KEY = 'f743d9e8113b01fbb593d0d5ea592854';
const INDEX      = 'bvtu_content';

$set = json_decode(file_get_contents($root . '/tests/search-queries.json'), true);
if (!$set) { fwrite(STDERR, "Could not read tests/search-queries.json\n"); exit(1); }

function algoliaSearch(string $q, int $n = 5): array {
    $ch = curl_init('https://' . APP_ID . '-dsn.algolia.net/1/indexes/' . INDEX . '/query');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Algolia-Application-Id: ' . APP_ID,
            'X-Algolia-API-Key: ' . SEARCH_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'query'                => $q,
            'hitsPerPage'          => $n,
            'attributesToRetrieve' => ['title', 'url', 'type'],
        ]),
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp ?: '{}', true) ?: [];
}

$found = 0; $first = 0; $empty = 0; $misses = [];
foreach ($set['queries'] as $case) {
    $res  = algoliaSearch($case['q']);
    $hits = $res['hits'] ?? [];
    if (!$hits) $empty++;

    $rank = 0;
    foreach ($hits as $k => $h) {
        foreach ($case['expect'] as $want) {
            if (stripos($h['title'] ?? '', $want) !== false) { $rank = $k + 1; break 2; }
        }
    }
    if ($rank === 1) $first++;
    if ($rank >= 1)  $found++;
    else $misses[] = $case['q'] . (count($hits) ? '' : '  (no results at all)');

    printf("%-6s %-26s %s\n", $rank ? "#$rank" : (count($hits) ? 'MISS' : 'EMPTY'),
           $case['q'], count($hits) . ' hit' . (count($hits) === 1 ? '' : 's'));
    if ($verbose || !$rank) {
        foreach (array_slice($hits, 0, 4) as $h) {
            printf("          %-9s %s\n", $h['type'] ?? '?', mb_substr($h['title'] ?? '', 0, 56));
        }
    }
}

$n = count($set['queries']);
printf("\nsite search, live index\n");
printf("  the right thing is in the top 5: %d of %d  (%d%%)\n", $found, $n, round(100 * $found / $n));
printf("  it is the first result:          %d of %d  (%d%%)\n", $first, $n, round(100 * $first / $n));
printf("  searches returning nothing:      %d\n", $empty);
if ($misses) {
    echo "  missed:\n";
    foreach ($misses as $m) echo "    - $m\n";
}
