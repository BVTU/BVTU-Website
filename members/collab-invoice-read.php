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
 * Which absences have been saved from an invoice, one row each.
 *
 * A person's grant row holds one cost and a list of invoice numbers, which
 * cannot say which of their absences on an invoice are already in it. This
 * can: so an invoice read twice is not counted twice, and an absence left
 * unticked the first time can still be saved later.
 */
function cgEnsureInvoiceImports(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        getDB()->exec("CREATE TABLE IF NOT EXISTS collab_grant_invoice_imports (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            app_id         INT NOT NULL,
            person_key     VARCHAR(255) NOT NULL,
            invoice_number VARCHAR(100) NOT NULL,
            absence_date   DATE NOT NULL,
            cost           DECIMAL(10,2) NOT NULL,
            created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by     VARCHAR(255) NOT NULL DEFAULT '',
            UNIQUE KEY uq_absence (app_id, person_key, invoice_number, absence_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return $ok = true;
    } catch (\Exception $e) {
        error_log('cgEnsureInvoiceImports: ' . $e->getMessage());
        return $ok = false;
    }
}

/** Saved absences of one grant: "personkey|invoice|Y-m-d" => true. */
function cgInvoiceImportsFor(int $appId): array {
    $out = [];
    if (!cgEnsureInvoiceImports()) return $out;
    try {
        $st = getDB()->prepare("SELECT person_key, invoice_number, absence_date FROM collab_grant_invoice_imports WHERE app_id=?");
        $st->execute([$appId]);
        foreach ($st->fetchAll() as $r) {
            $out[$r['person_key'] . '|' . $r['invoice_number'] . '|' . $r['absence_date']] = true;
        }
    } catch (\Exception $e) { error_log('cgInvoiceImportsFor: ' . $e->getMessage()); }
    return $out;
}

/** The invoice numbers a line lists. */
function cgLineInvoices(array $l): array {
    return array_values(array_filter(array_map('trim', explode(',', (string)$l['invoice_number'])), 'strlen'));
}

/**
 * Is this absence already on the grant? 'saved' when the reader saved it;
 * 'by hand' when the invoice is on the person's row (or a whole-grant row)
 * but was typed in, so which absences it covers is unknown and none is added;
 * '' when it is not there. An import whose invoice has since been taken off
 * the row no longer counts — the row is what the totals read.
 */
function cgAbsenceStatus(array $lines, array $imports, string $person, string $inv, string $date): string {
    if ($inv === '') return '';
    $pk = cgNameKey($person);
    $onRow = false;
    foreach ($lines as $l) {
        $lp = trim((string)$l['person']);
        if (($lp === '' || cgNameKey($lp) === $pk) && in_array($inv, cgLineInvoices($l), true)) {
            if ($lp === '') return 'by hand';   // carried-over whole-grant figure
            $onRow = true;
        }
    }
    if (!$onRow) return '';
    if (isset($imports[$pk . '|' . $inv . '|' . $date])) return 'saved';
    foreach (array_keys($imports) as $k) {
        if (strpos($k, $pk . '|' . $inv . '|') === 0) return '';   // reader saved others on it, not this one
    }
    return 'by hand';
}

/**
 * Match what was read against the approved grants of the school years its
 * absence dates fall in. Returns:
 *   'rows'      — one per absence: the line as read, and its best match or none
 *   'invoices'  — number, date, description, total and adjustments of each
 * A row's 'match' is null, or ['app_id', 'person', 'applicant', 'label'
 * ('match' | 'likely'), 'why' (list of reasons), 'already' (cgAbsenceStatus():
 * '', 'saved' or 'by hand'), and 'choices' when several grants fit equally].
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
            $imports = cgInvoiceImportsFor((int)$g['id']);
            foreach (cgGrantPeople($g, $folded[(int)$g['id']] ?? []) as $p) {
                $cands[] = ['app' => $g, 'person' => $p, 'dates' => $dates,
                            'lines' => $lines[(int)$g['id']] ?? [], 'imports' => $imports];
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

            // Every candidate at the best score, so a tie is seen, not settled
            // by whichever grant happened to come first.
            $top = []; $bestScore = -1;
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
                if ($score < $bestScore) continue;
                if ($score > $bestScore) { $top = []; $bestScore = $score; }
                $why = [];
                if ($nWhy !== '') $why[] = $nWhy . ' (' . $c['person'] . ')';
                if ($gap === null) $why[] = 'the grant has no dates';
                elseif ($gap > 0) $why[] = 'date ' . $gap . ' day' . ($gap === 1 ? '' : 's') . ' from the grant\'s';
                // Part days are not settled yet: say so rather than guess.
                if ($row['hours'] > 0 && $row['hours'] < 1) $why[] = 'invoice shows part of a day';
                elseif ($part < 1) $why[] = 'grant has this as ' . cgPartLabel($part);
                $status = cgAbsenceStatus($c['lines'], $c['imports'], $c['person'], $num, $iso);
                $top[] = ['app_id' => (int)$c['app']['id'], 'person' => $c['person'],
                          'applicant' => (string)$c['app']['applicant_name'],
                          'label' => ($ns === 3 && $ds === 3) ? 'match' : 'likely',
                          'why' => $why, 'already' => $status];
            }
            if ($top) {
                $m = $top[0];
                if (count($top) > 1) {
                    // Same evidence for two grants: someone has to say which.
                    $m['label'] = 'likely';
                    $m['why'][] = 'on more than one grant — choose which';
                    $m['choices'] = array_map(fn($x) => ['app_id' => $x['app_id'], 'person' => $x['person'],
                                                         'applicant' => $x['applicant'], 'already' => $x['already']], $top);
                }
                $row['match'] = $m;
            }
            $rows[] = $row;
        }
    }
    return ['rows' => $rows, 'invoices' => $invs];
}

/**
 * Write confirmed absences onto their grants. $picks: [['app_id', 'person',
 * 'invoice_number', 'date', 'cost'], …]. Each absence is added once: one
 * already saved by the reader, or on an invoice typed onto the row by hand,
 * is skipped and said so. A person's absences add to their one row on the
 * grant, and a new invoice's number joins the ones listed there.
 * Returns ['saved' => n, 'skipped' => n, 'errors' => [...]].
 */
function cgApplyInvoiceMatches(array $picks, string $actor): array {
    $byApp = [];
    foreach ($picks as $p) {
        $id = (int)($p['app_id'] ?? 0);
        $person = trim((string)($p['person'] ?? ''));
        $cost = round((float)($p['cost'] ?? 0), 2);
        $t = strtotime((string)($p['date'] ?? ''));
        if (!$id || $person === '' || $cost < 0 || !$t) continue;
        $byApp[$id][] = ['person' => $person, 'inv' => trim((string)($p['invoice_number'] ?? '')),
                         'date' => date('Y-m-d', $t), 'cost' => $cost];
    }

    $saved = 0; $skipped = 0; $errors = [];
    if ($byApp && !cgEnsureInvoiceImports()) {
        return ['saved' => 0, 'skipped' => 0, 'errors' => ['The invoice record could not be set up, so nothing was saved.']];
    }
    $db = getDB();
    foreach ($byApp as $id => $absences) {
        $app = cgGetApplication((int)$id);
        if (!$app) { $errors[] = 'Grant #' . $id . ' could not be found.'; continue; }
        $lines = cgInvoiceLinesFor([$app])[(int)$id] ?? [];
        if (cgInvoiceReadFailed()) { $errors[] = $app['applicant_name'] . ': its invoices could not be read, so it was left alone.'; continue; }
        $imports = cgInvoiceImportsFor((int)$id);

        $added = [];
        foreach ($absences as $a) {
            $key = cgNameKey($a['person']) . '|' . $a['inv'] . '|' . $a['date'];
            if (isset($added[$key]) || cgAbsenceStatus($lines, $imports, $a['person'], $a['inv'], $a['date']) !== '') {
                $skipped++; continue;
            }
            $added[$key] = true;
            $found = false;
            foreach ($lines as &$l) {
                if (trim((string)$l['person']) === '' || cgNameKey((string)$l['person']) !== cgNameKey($a['person'])) continue;
                $found = true;
                $nums = cgLineInvoices($l);
                if ($a['inv'] !== '' && !in_array($a['inv'], $nums, true)) $nums[] = $a['inv'];
                $l['invoice_number'] = implode(', ', $nums);
                $l['amount'] = round((float)($l['amount'] ?? 0) + $a['cost'], 2);
                break;
            }
            unset($l);
            if (!$found) {
                $lines[] = ['person' => $a['person'], 'invoice_number' => $a['inv'], 'amount' => $a['cost']];
            }
            $added[$key] = $a;
        }
        $toRecord = array_filter($added, 'is_array');
        if (!$toRecord) continue;

        $err = cgSetFulfilment((int)$id, array_map(fn($l) => [
            'person' => (string)$l['person'], 'invoice_number' => (string)$l['invoice_number'],
            'amount' => $l['amount'] === null ? '' : (string)$l['amount']], $lines), $actor);
        if ($err !== '') { $errors[] = $app['applicant_name'] . ': ' . $err; continue; }
        $ins = $db->prepare("INSERT IGNORE INTO collab_grant_invoice_imports
                             (app_id, person_key, invoice_number, absence_date, cost, created_by) VALUES (?,?,?,?,?,?)");
        foreach ($toRecord as $a) {
            try { $ins->execute([(int)$id, cgNameKey($a['person']), $a['inv'], $a['date'], $a['cost'], $actor]); }
            catch (\Exception $e) { error_log('invoice import record: ' . $e->getMessage()); }
            $saved++;
        }
    }
    return ['saved' => $saved, 'skipped' => $skipped, 'errors' => $errors];
}
