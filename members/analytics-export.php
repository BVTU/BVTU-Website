<?php
/**
 * analytics-export.php — per-page usage, as CSV, for the navigation audit.
 *
 * The audit inventoried what the site HAS. This is what the site is USED for,
 * in the one shape that can be joined to it: one row per path.
 *
 * Authorisation is checked here rather than inherited from whichever page
 * linked in, the same rule contacts-export.php follows: a download URL is
 * guessable and has to stand on its own.
 *
 * Read-only. It writes nothing and streams the result; no file is left in a
 * web-readable directory.
 *
 * Two things limit what it can say, and both are stated in the file itself so
 * a figure is never read as covering more than it does:
 *
 *   - site_views keeps AN_RETAIN_DAYS of rows and anCull() then rolls each day
 *     into site_daily, which has no device and no referrer. Both are read and
 *     added together here, so the page totals span the whole history while the
 *     device split only covers the retention window.
 *   - site_clicks is never rolled up; it is deleted past the same window.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/exec-db.php';
require_once __DIR__ . '/analytics-db.php';

requireLogin();
$member = getMember();
if (!execIsAdmin($member['email'])) { http_response_code(403); exit('Access denied.'); }

anEnsureTables();
$db = getDB();

/* Views and visitors per path, from both tables.
 *
 * site_daily's visitor counts are per day, so adding them across days
 * over-counts somebody who came back on a second day. Summed anyway and
 * labelled "visitor-days", because the alternative — dropping the rolled-up
 * history — would silently shorten the range of every page that predates the
 * retention window. */
$rows = [];
$add = function (string $path, int $views, int $visitors, ?string $first, ?string $last) use (&$rows) {
    $p = '/' . ltrim(trim($path), '/');
    if (!isset($rows[$p])) $rows[$p] = ['views' => 0, 'visitors' => 0, 'first' => null, 'last' => null];
    $rows[$p]['views']    += $views;
    $rows[$p]['visitors'] += $visitors;
    foreach (['first' => $first, 'last' => $last] as $k => $v) {
        if (!$v) continue;
        if ($rows[$p][$k] === null
            || ($k === 'first' && $v < $rows[$p][$k])
            || ($k === 'last'  && $v > $rows[$p][$k])) $rows[$p][$k] = $v;
    }
};

foreach ($db->query(
    "SELECT path, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors,
            MIN(DATE(viewed_at)) AS first_seen, MAX(DATE(viewed_at)) AS last_seen
       FROM site_views GROUP BY path")->fetchAll() as $r) {
    $add($r['path'], (int)$r['views'], (int)$r['visitors'], $r['first_seen'], $r['last_seen']);
}
try {
    foreach ($db->query(
        "SELECT path, SUM(views) AS views, SUM(visitors) AS visitors,
                MIN(day) AS first_seen, MAX(day) AS last_seen
           FROM site_daily GROUP BY path")->fetchAll() as $r) {
        $add($r['path'], (int)$r['views'], (int)$r['visitors'], $r['first_seen'], $r['last_seen']);
    }
} catch (Exception $e) {
    // No rolled-up history yet; the raw table is the whole record.
}

/* Clicks TO a destination, which is the question the audit asks of a link:
 * is anybody using it? Keyed on target, not on the page the click happened on. */
$inbound = [];
foreach ($db->query(
    "SELECT target, COUNT(*) AS n FROM site_clicks WHERE kind='int'
      GROUP BY target")->fetchAll() as $r) {
    $t = '/' . ltrim(trim((string)$r['target']), '/');
    $inbound[$t] = ($inbound[$t] ?? 0) + (int)$r['n'];
}

$devices = [];
foreach ($db->query("SELECT device, COUNT(*) AS n FROM site_views GROUP BY device")->fetchAll() as $r) {
    $devices[(string)$r['device'] ?: 'unknown'] = (int)$r['n'];
}

$span = $db->query("SELECT MIN(DATE(viewed_at)) AS a, MAX(DATE(viewed_at)) AS b FROM site_views")->fetch();

uasort($rows, fn($a, $b) => $b['views'] <=> $a['views']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="bvtu-page-usage-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');

fputcsv($out, ['# BVTU page usage, exported ' . date('Y-m-d H:i')]);
fputcsv($out, ['# Raw views cover ' . (string)($span['a'] ?? '?') . ' to ' . (string)($span['b'] ?? '?')
             . '; days older than ' . AN_RETAIN_DAYS . ' are rolled up into daily totals.']);
fputcsv($out, ['# "Visitors" sums per-day counts once a day has been rolled up, so treat it as visitor-days.']);
fputcsv($out, ['# Clicks are inbound: how often a link POINTING AT this path was used. Kept ' . AN_RETAIN_DAYS . ' days.']);
fputcsv($out, []);
fputcsv($out, ['Path', 'Views', 'Visitors (visitor-days)', 'Inbound clicks', 'First seen', 'Last seen']);
foreach ($rows as $p => $r) {
    fputcsv($out, [$p, $r['views'], $r['visitors'], $inbound[$p] ?? 0, $r['first'], $r['last']]);
}
// Destinations nobody reached at all do not appear above; the audit's
// inventory is what says which those are, so the two are joined on Path.
fputcsv($out, []);
fputcsv($out, ['# Device split (retention window only)']);
foreach ($devices as $d => $n) fputcsv($out, ['', $d, $n]);
fclose($out);
