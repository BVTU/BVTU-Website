<?php
/**
 * collab-invoice-read.php — reading a district release invoice against the
 * collaboration grants.
 *
 * The district bills release time in one invoice that mixes grant teachers
 * with everyone else released that month (Pro-D, the President, committee
 * work). Each absence on the attached sheet names the teacher released
 * ("Absent Employee"), the date, and a subtotal of the TTOC's pay and
 * benefits. This reads those, then matches each against the people and dates
 * on this year's approved grants.
 *
 * Two halves, deliberately apart:
 *   cgReadInvoiceFile()  — Claude reads the scan. It extracts; it decides nothing.
 *   cgMatchInvoice()     — plain PHP decides what matches and says why, so a
 *                          match can always be explained and checked.
 *
 * Nothing is kept: the file is deleted by the caller as soon as it has been
 * read (Cody keeps the original), and only the lines someone confirms are
 * written, as invoice lines on the grant. Lines that match no grant are shown
 * once and forgotten.
 */
require_once __DIR__ . '/collab-grant-db.php';

/** What the reader is asked to return. Every field required, so a missing one
 *  is a schema failure rather than a silent gap. */
function cgInvoiceSchema(): array {
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['invoices'],
        'properties' => [
            'invoices' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['invoice_number', 'invoice_date', 'description', 'total', 'absences', 'adjustments'],
                    'properties' => [
                        'invoice_number' => ['type' => 'string'],
                        'invoice_date'   => ['type' => 'string'],
                        'description'    => ['type' => 'string'],
                        'total'          => ['type' => 'number'],
                        'absences' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['date', 'absent_employee', 'hours', 'cost'],
                                'properties' => [
                                    'date'            => ['type' => 'string'],
                                    'absent_employee' => ['type' => 'string'],
                                    'hours'           => ['type' => 'number'],
                                    'cost'            => ['type' => 'number'],
                                ],
                            ],
                        ],
                        'adjustments' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['label', 'amount'],
                                'properties' => [
                                    'label'  => ['type' => 'string'],
                                    'amount' => ['type' => 'number'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Read an invoice file (PDF or image). Returns ['invoices' => [...]] or
 * ['error' => string]. The caller owns the file and deletes it.
 */
function cgReadInvoiceFile(string $path, string $mime): array {
    if (!defined('CLAUDE_API_KEY')) return ['error' => 'The Claude API key is not set up on this server.'];
    $data = @file_get_contents($path);
    if ($data === false || $data === '') return ['error' => 'The file could not be read.'];

    $source = ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($data)];
    $block  = $mime === 'application/pdf' ? ['type' => 'document', 'source' => $source]
                                          : ['type' => 'image', 'source' => $source];

    $prompt = <<<PROMPT
This is a School District #54 invoice (or several) to the Bulkley Valley Teachers' Union for release time. A file can hold more than one invoice: each has a cover page with an invoice number, date, description and total, usually followed by an attached sheet.

For each invoice, return its number, its date as YYYY-MM-DD, its description and its total.

Then list every absence on its attached sheet. An absence starts with a Date and an "Absent Employee" — the teacher who was released — followed by several pay and benefit lines for the substitute (the "Employee Name" column), and ends with a subtotal for that absence. For each absence give:
- date: the absence date, as YYYY-MM-DD
- absent_employee: the Absent Employee's name exactly as printed, first name then last (not the substitute's name)
- hours: the Hours on its PAY 01 line, or 0 if there is none
- cost: the absence's subtotal (pay plus benefits), as a number

An invoice whose sheet has no Absent Employee column (for example a single person's own salary) has no absences: return an empty list for it.

List any invoice-level adjustments below the subtotal (for example "Less 50% for Pro-D Chair") with the amount as a positive number. Do not apply them to any absence.

The scan may have small OCR-style flaws (a missing space, O for 0). Read through them, but never guess a name or amount you cannot see.
PROMPT;

    $payload = json_encode([
        'model'      => 'claude-opus-5-5',
        'max_tokens' => 16000,
        // If a safety classifier declines, the API retries on its own fallback
        // model inside the same call rather than returning nothing.
        'fallbacks'  => 'default',
        'output_config' => [
            'effort' => 'low',   // transcription, not reasoning
            'format' => ['type' => 'json_schema', 'schema' => cgInvoiceSchema()],
        ],
        'messages' => [[
            'role'    => 'user',
            'content' => [$block, ['type' => 'text', 'text' => $prompt]],
        ]],
    ]);

    @set_time_limit(240);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2026-10-21',
            'anthropic-beta: server-side-fallback-2026-07-01',
            'content-type: application/json',
        ],
        CURLOPT_TIMEOUT => 200,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log('cgReadInvoiceFile: HTTP ' . $httpCode . ' ' . $curlErr . ' ' . substr((string)$response, 0, 500));
        return ['error' => 'The invoice could not be read just now (' . ($httpCode ?: 'no connection') . '). Try again, or enter the costs by hand.'];
    }
    $msg = json_decode((string)$response, true);
    if (($msg['stop_reason'] ?? '') === 'refusal') {
        return ['error' => 'The reader declined this file. Enter the costs by hand.'];
    }
    if (($msg['stop_reason'] ?? '') === 'max_tokens') {
        return ['error' => 'The invoice was too long to read in one go. Try uploading fewer pages.'];
    }
    $text = '';
    foreach ($msg['content'] ?? [] as $b) {
        if (($b['type'] ?? '') === 'text') { $text .= $b['text']; }
    }
    $out = json_decode($text, true);
    if (!is_array($out) || !isset($out['invoices']) || !is_array($out['invoices'])) {
        error_log('cgReadInvoiceFile: unreadable reply ' . substr($text, 0, 500));
        return ['error' => 'The reader\'s answer could not be understood. Enter the costs by hand.'];
    }
    return $out;
}

/** "Gail Tendall" and "GailTendall" are the same name; so are case and accents. */
function cgInvNameKey(string $n): string {
    return str_replace(' ', '', cgNameKey($n));
}

/**
 * How well a name on the invoice fits a name on a grant: 3 exact, 2 close
 * (same surname and first initial, or a typo or two apart), 1 surname only,
 * 0 not them. With a word saying which, for the screen.
 */
function cgInvNameScore(string $invoice, string $grant): array {
    $a = cgInvNameKey($invoice);
    $b = cgInvNameKey($grant);
    if ($a === '' || $b === '') return [0, ''];
    if ($a === $b) return [3, ''];
    $pa = preg_split('/\s+/', cgNameKey($invoice)) ?: [];
    $pb = preg_split('/\s+/', cgNameKey($grant)) ?: [];
    $lastA = end($pa) ?: ''; $lastB = end($pb) ?: '';
    $firstA = $pa[0] ?? ''; $firstB = $pb[0] ?? '';
    if ($lastA !== '' && $lastA === $lastB && $firstA !== '' && $firstB !== '' && $firstA[0] === $firstB[0]) {
        return [2, 'name spelled differently'];
    }
    if (strlen($a) > 5 && levenshtein($a, $b) <= 2) return [2, 'name spelled differently'];
    if ($lastA !== '' && $lastA === $lastB) return [1, 'same surname only'];
    return [0, ''];
}

/**
 * Match what was read against the approved grants of the school years its
 * absence dates fall in. Returns:
 *   'rows'      — one per absence: the line as read, and its best match or none
 *   'invoices'  — number, date, description, total and adjustments of each
 * A row's 'match' is null, or ['app_id', 'person', 'applicant', 'label'
 * ('match' | 'likely'), 'why' (list of reasons), 'already' (true when that
 * person's line on the grant already lists this invoice)].
 */
function cgMatchInvoice(array $read): array {
    $rows = [];
    $invs = [];
    $years = [];
    foreach ($read['invoices'] as $inv) {
        foreach ((array)($inv['absences'] ?? []) as $ab) {
            $t = strtotime((string)($ab['date'] ?? ''));
            if ($t) $years[cgYearOfDate(date('Y-m-d', $t))] = true;
        }
    }

    // Every approved grant in those years, with its people and release dates.
    $cands = [];
    foreach (array_keys($years) as $y) {
        $all = cgGetApplications((int)$y);
        $folded = [];
        foreach ($all as $a) {
            if (!empty($a['duplicate_of'])) $folded[(int)$a['duplicate_of']][] = $a;
        }
        $grants = array_values(array_filter($all, fn($a) => $a['status'] === 'approved' && empty($a['duplicate_of'])));
        $lines  = cgInvoiceLinesFor($grants);
        foreach ($grants as $g) {
            $w = cgAppDateWeights($g);
            $dates = $w ? $w : array_fill_keys(cgAppDatesLoose($g), 1.0);
            foreach (cgGrantPeople($g, $folded[(int)$g['id']] ?? []) as $p) {
                $cands[] = ['app' => $g, 'person' => $p, 'dates' => $dates,
                            'lines' => $lines[(int)$g['id']] ?? []];
            }
        }
    }

    foreach ($read['invoices'] as $inv) {
        $num = trim((string)($inv['invoice_number'] ?? ''));
        $invs[] = [
            'invoice_number' => $num,
            'invoice_date'   => (string)($inv['invoice_date'] ?? ''),
            'description'    => (string)($inv['description'] ?? ''),
            'total'          => (float)($inv['total'] ?? 0),
            'adjustments'    => array_values(array_filter((array)($inv['adjustments'] ?? []),
                                    fn($x) => trim((string)($x['label'] ?? '')) !== '')),
            'absences'       => count((array)($inv['absences'] ?? [])),
        ];
        foreach ((array)($inv['absences'] ?? []) as $ab) {
            $name = trim((string)($ab['absent_employee'] ?? ''));
            $t    = strtotime((string)($ab['date'] ?? ''));
            $iso  = $t ? date('Y-m-d', $t) : '';
            $row  = ['invoice_number' => $num, 'date' => $iso, 'name' => $name,
                     'hours' => (float)($ab['hours'] ?? 0), 'cost' => round((float)($ab['cost'] ?? 0), 2),
                     'match' => null];

            $best = null; $bestScore = -1;
            foreach ($cands as $c) {
                [$ns, $nWhy] = cgInvNameScore($name, $c['person']);
                if ($ns === 0) continue;
                // Days from the absence to the nearest date on the grant.
                $gap = null; $part = 1.0;
                if ($iso !== '') {
                    foreach ($c['dates'] as $gd => $gw) {
                        $d = (int)round(abs(strtotime($gd) - strtotime($iso)) / 86400);
                        if ($gap === null || $d < $gap) { $gap = $d; $part = (float)$gw; }
                    }
                }
                $ds = $gap === null ? 0 : ($gap === 0 ? 3 : ($gap <= 3 ? 2 : ($gap <= 10 ? 1 : 0)));
                // A name that is only a surname needs the exact date to count.
                if ($ns === 1 && $ds < 3) continue;
                if ($ns >= 2 && $ds === 0) continue;
                $score = $ns * 10 + $ds;
                if ($score <= $bestScore) continue;
                $why = [];
                if ($nWhy !== '') $why[] = $nWhy . ' (' . $c['person'] . ')';
                if ($gap === null) $why[] = 'the grant has no dates';
                elseif ($gap > 0) $why[] = 'date ' . $gap . ' day' . ($gap === 1 ? '' : 's') . ' from the grant\'s';
                // Part days are not settled yet: say so rather than guess.
                if ($row['hours'] > 0 && $row['hours'] < 1) $why[] = 'invoice shows part of a day';
                elseif ($part < 1) $why[] = 'grant has this as ' . cgPartLabel($part);
                $already = false;
                foreach ($c['lines'] as $l) {
                    if (cgNameKey((string)$l['person']) === cgNameKey($c['person']) && $num !== ''
                        && in_array($num, array_map('trim', explode(',', (string)$l['invoice_number'])), true)) {
                        $already = true;
                    }
                }
                $best = ['app_id' => (int)$c['app']['id'], 'person' => $c['person'],
                         'applicant' => (string)$c['app']['applicant_name'],
                         'label' => ($ns === 3 && $ds === 3) ? 'match' : 'likely',
                         'why' => $why, 'already' => $already];
                $bestScore = $score;
            }
            $row['match'] = $best;
            $rows[] = $row;
        }
    }
    return ['rows' => $rows, 'invoices' => $invs];
}

/**
 * Write confirmed matches onto their grants. $picks: [['app_id', 'person',
 * 'invoice_number', 'cost'], …]. A person released on two dates of one
 * invoice is one row on the grant, so their absences add up; a second invoice
 * for someone already costed adds to their cost and lists both numbers.
 * Returns ['saved' => n, 'errors' => [...]].
 */
function cgApplyInvoiceMatches(array $picks, string $actor): array {
    $byApp = [];
    foreach ($picks as $p) {
        $id = (int)($p['app_id'] ?? 0);
        $person = trim((string)($p['person'] ?? ''));
        $num = trim((string)($p['invoice_number'] ?? ''));
        $cost = (float)($p['cost'] ?? 0);
        if (!$id || $person === '' || $cost < 0) continue;
        $k = cgNameKey($person);
        if (!isset($byApp[$id][$k])) $byApp[$id][$k] = ['person' => $person, 'nums' => [], 'cost' => 0.0];
        if ($num !== '' && !in_array($num, $byApp[$id][$k]['nums'], true)) $byApp[$id][$k]['nums'][] = $num;
        $byApp[$id][$k]['cost'] = round($byApp[$id][$k]['cost'] + $cost, 2);
    }

    $saved = 0; $errors = [];
    foreach ($byApp as $id => $people) {
        $app = cgGetApplication((int)$id);
        if (!$app) { $errors[] = 'Grant #' . $id . ' could not be found.'; continue; }
        $lines = cgInvoiceLinesFor([$app])[(int)$id] ?? [];
        if (cgInvoiceReadFailed()) { $errors[] = $app['applicant_name'] . ': its invoices could not be read, so it was left alone.'; continue; }
        foreach ($people as $k => $add) {
            $found = false;
            foreach ($lines as &$l) {
                if (cgNameKey((string)$l['person']) !== $k) continue;
                $found = true;
                $have = array_values(array_filter(array_map('trim', explode(',', (string)$l['invoice_number'])), 'strlen'));
                $new  = array_values(array_diff($add['nums'], $have));
                if (!$new && $add['nums']) break;   // this invoice is on the line already
                $l['invoice_number'] = implode(', ', array_merge($have, $new));
                $l['amount'] = round((float)($l['amount'] ?? 0) + $add['cost'], 2);
                break;
            }
            unset($l);
            if (!$found) {
                $lines[] = ['person' => $add['person'], 'invoice_number' => implode(', ', $add['nums']),
                            'amount' => $add['cost']];
            }
        }
        $err = cgSetFulfilment((int)$id, array_map(fn($l) => [
            'person' => (string)$l['person'], 'invoice_number' => (string)$l['invoice_number'],
            'amount' => $l['amount'] === null ? '' : (string)$l['amount']], $lines), $actor);
        if ($err !== '') $errors[] = $app['applicant_name'] . ': ' . $err;
        else $saved += count($people);
    }
    return ['saved' => $saved, 'errors' => $errors];
}
