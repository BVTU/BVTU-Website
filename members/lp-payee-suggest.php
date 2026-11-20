<?php
/**
 * lp-payee-suggest.php — who to pay, as "Pay to" is typed.
 *
 * People paid on earlier vouchers come first, most often paid at the top, so
 * somebody e-transferred every month is one pick away. Then matching people
 * from the contact list. Deduplicated on the address, since that is what the
 * money follows.
 *
 * Remembered payees come only from vouchers that went for signature: a draft
 * or a declined voucher may carry a typo, and remembering it would offer the
 * wrong address back every time after.
 *
 * Gated on lpCanCreate(), the same people who can raise a voucher. Returns
 * name and address only — what the form is about to hold anyway.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lp-db.php';
require_once __DIR__ . '/contacts-db.php';

requireLogin();
$member = getMember();
header('Content-Type: application/json');
if (!lpCanCreate($member['email'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}
sendPrivateHeaders();

$q = reqStr('q');
if (mb_strlen($q) < 2) { echo json_encode([]); exit; }

lpEnsureApprovalColumns();

$like = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $q) . '%';
$past = [];
try {
    $st = getDB()->prepare(
        "SELECT payee_name, payee_email
           FROM lp_vouchers
          WHERE payee_name <> '' AND payee_email <> ''
            AND status IN ('submitted', 'treasurer_approved', 'vp_approved', 'paid')
            AND (payee_name LIKE ? ESCAPE '|' OR payee_email LIKE ? ESCAPE '|')
          ORDER BY COALESCE(submitted_at, created_at) DESC
          LIMIT 300"
    );
    $st->execute([$like, $like]);
    // Grouped here rather than in SQL: the address is folded to lower case,
    // and LOWER() on a column in a query discards the index and has failed on
    // collation before. Newest first, so the name kept is the latest spelling.
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $key = contactNormalizeEmail($r['payee_email']);
        if (!isset($past[$key])) {
            $past[$key] = ['name' => $r['payee_name'], 'email' => trim($r['payee_email']), 'times' => 0];
        }
        $past[$key]['times']++;
    }
} catch (Exception $e) {
    error_log('lp-payee-suggest: ' . $e->getMessage());
}

$order = array_keys($past);
usort($order, function ($a, $b) use ($past, $order) {
    $d = $past[$b]['times'] - $past[$a]['times'];
    // Equal counts keep newest-first, the order they arrived in.
    return $d !== 0 ? $d : array_search($a, $order, true) - array_search($b, $order, true);
});

$out  = [];
$seen = [];
foreach (array_slice($order, 0, 5) as $key) {
    $out[] = $past[$key];
    $seen[$key] = true;
}

list($rows) = contactSearch(['q' => $q, 'sort' => 'name', 'dir' => 'asc'], 1, 10);
foreach ($rows as $c) {
    if (count($out) >= 8) break;
    $key = contactNormalizeEmail((string)$c['email']);
    if ($key === '' || isset($seen[$key])) continue;
    $seen[$key] = true;
    $out[] = ['name' => contactDisplayName($c), 'email' => $c['email'], 'times' => 0];
}

echo json_encode($out);
