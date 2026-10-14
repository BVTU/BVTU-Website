<?php
/**
 * lp-checks.php — a last look over a voucher before it goes for signature.
 *
 * Catches the things that are easy to do and annoying to undo: a receipt never
 * attached, a date left blank, a date or a total that does not agree with the
 * receipt behind it.
 *
 * Everything here is a prompt, never a block. A split bill, a tip added by
 * hand, one receipt covering two lines and a receipt dated the day after the
 * meal are all ordinary, and a voucher that cannot be submitted until a
 * machine is satisfied is worse than one submitted with a note to check.
 */
require_once __DIR__ . '/lp-db.php';

/**
 * The part of a line a receipt is expected to account for.
 *
 * Mileage is claimed by distance at the rate, so no receipt exists for it and
 * it must come out before any comparison. Shared with voucher.php so the
 * reviewer and the shared read-only page can never disagree about a line.
 */
function lpReceiptPortion(array $e): float {
    $km = (float)($e['travel_km'] ?? 0) > 0 ? (float)($e['travel_amt'] ?? 0) : 0.0;
    return lpRowTotal($e) - $km;
}

/** True when the line is mileage and nothing else, so no receipt is expected. */
function lpIsMileageOnly(array $e): bool {
    return (float)($e['travel_km'] ?? 0) > 0 && abs(lpReceiptPortion($e)) < 0.01;
}

/** What the receipt was read as saying, or null where it was never scanned. */
function lpScanTotal(array $e): ?float {
    return isset($e['scan_total']) && $e['scan_total'] !== null && $e['scan_total'] !== ''
        ? (float)$e['scan_total'] : null;
}

/**
 * Does the receipt's total disagree with what is claimed?
 * Null when there is nothing to compare, so "unknown" never reads as "fine".
 */
function lpTotalDiffers(array $e): ?bool {
    $scan = lpScanTotal($e);
    if ($scan === null) return null;
    // Nothing on this line is claimed against a receipt — mileage only, with
    // a parking stub or a map screenshot attached. Comparing a receipt total
    // to $0.00 would report a discrepancy on a perfectly ordinary line, and a
    // reviewer that cries wolf is one nobody reads.
    $part = lpReceiptPortion($e);
    if (abs($part) < 0.01) return null;
    return abs($scan - $part) >= 0.01;
}

/** Findings for one line. $n is its position, counting from 1. */
function lpLineLabel(array $e): string {
    $bits = [];
    if (!empty($e['expense_date'])) $bits[] = date('M j', strtotime((string)$e['expense_date']));
    $desc = trim((string)($e['description'] ?? ''));
    if ($desc !== '') $bits[] = mb_strlen($desc) > 38 ? mb_substr($desc, 0, 37) . '…' : $desc;
    return implode(' — ', $bits);
}

function lpReviewLine(array $e, int $n): array {
    $out   = [];
    $where = lpLineLabel($e);
    $add   = function (string $level, string $what, string $detail) use (&$out, $n, $where) {
        $out[] = ['line' => $n, 'where' => $where, 'level' => $level,
                  'what' => $what, 'detail' => $detail];
    };

    $hasFile = trim((string)($e['receipt_path'] ?? '')) !== '';
    $total   = lpRowTotal($e);

    if (trim((string)($e['description'] ?? '')) === '') {
        $add('missing', 'No description',
             'A signer reading this later will not know what it was for.');
    }

    if (empty($e['expense_date'])) {
        $add('missing', 'No date', 'This line has no date on it.');
    }

    if (abs($total) < 0.01) {
        $add('missing', 'No amount', 'Nothing is claimed on this line.');
    }

    if (!$hasFile && !lpIsMileageOnly($e) && abs($total) >= 0.01) {
        $add('missing', 'No receipt attached',
             'Nothing was uploaded for this line. If there genuinely is no receipt, '
           . 'say so in the description so a signer is not left wondering.');
    }

    // Everything below needs a scanned receipt to compare against.
    if (!$hasFile) return $out;

    $differs = lpTotalDiffers($e);
    if ($differs === true) {
        $scan = lpScanTotal($e);
        $add('check', 'Amount does not match the receipt',
             'Claimed ' . lpMoney(lpReceiptPortion($e)) . ', but the receipt reads '
           . lpMoney($scan) . '. Often fine — a split bill, or one receipt across two lines.');
    }

    if (!empty($e['scan_date']) && !empty($e['expense_date'])) {
        $a = strtotime((string)$e['expense_date']);
        $b = strtotime((string)$e['scan_date']);
        if ($a && $b && date('Y-m-d', $a) !== date('Y-m-d', $b)) {
            $days = (int)round(abs($a - $b) / 86400);
            $add('check', 'Date does not match the receipt',
                 'This line says ' . date('M j, Y', $a) . ' and the receipt reads '
               . date('M j, Y', $b) . ' — ' . $days . ' day' . ($days === 1 ? '' : 's')
               . ' apart. Check the right receipt is on the right line.');
        }
    }

    return $out;
}

/**
 * Everything worth a second look on this voucher.
 *
 * Returns ['findings' => [...], 'missing' => int, 'check' => int].
 */
function lpReviewVoucher(array $voucher, array $expenses): array {
    $findings = [];

    foreach (array_values($expenses) as $i => $e) {
        foreach (lpReviewLine($e, $i + 1) as $f) $findings[] = $f;
    }

    // One photograph on two lines is the classic way to claim a cost twice, and
    // it is invisible line by line — only the whole voucher shows it.
    $byHash = [];
    $byPath = [];
    foreach (array_values($expenses) as $i => $e) {
        $hash = trim((string)($e['receipt_sha256'] ?? ''));
        $path = trim((string)($e['receipt_path'] ?? ''));
        if ($hash !== '') $byHash[$hash][] = $i + 1;
        if ($path !== '') $byPath[$path][] = $i + 1;
    }
    // Same bytes under two filenames is the case the hash exists for; same
    // filename covers rows uploaded before hashing was added. Reported once.
    $groups = [];
    foreach (array_merge(array_values($byHash), array_values($byPath)) as $lines) {
        if (count($lines) < 2) continue;
        $key = implode(',', $lines);
        $groups[$key] = $lines;
    }
    foreach ($groups as $lines) {
        if (count($lines) < 2) continue;
        $first = array_shift($lines);
        $findings[] = [
            'line'   => $first,
            'where'  => lpLineLabel(array_values($expenses)[$first - 1] ?? []),
            'level'  => 'check',
            'what'   => 'Same receipt on more than one line',
            'detail' => 'The receipt on line ' . $first . ' is also on line '
                      . implode(' and ', $lines) . '. If one bill really does cover both, '
                      . 'that is fine — otherwise one of them has the wrong receipt.',
        ];
    }

    // usort is only stable from PHP 8.0 and the host is 7.4, so the
    // comparator is made total rather than leaving ties to the sort.
    foreach ($findings as $i => $_) $findings[$i]['seq'] = $i;
    usort($findings, function ($x, $y) {
        if ($x['line'] !== $y['line']) return $x['line'] <=> $y['line'];
        $rank = function ($f) { return $f['level'] === 'missing' ? 0 : 1; };
        if ($rank($x) !== $rank($y)) return $rank($x) <=> $rank($y);
        return $x['seq'] <=> $y['seq'];
    });

    $missing = 0; $check = 0;
    foreach ($findings as $f) {
        if ($f['level'] === 'missing') $missing++; else $check++;
    }

    return ['findings' => $findings, 'missing' => $missing, 'check' => $check];
}

function lpMoney($v): string {
    return '$' . number_format((float)$v, 2);
}
