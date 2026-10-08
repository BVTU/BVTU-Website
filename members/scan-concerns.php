<?php
/**
 * scan-concerns.php — what a receipt scan is allowed to complain about.
 *
 * The four scanners used to ask the model to "note anything suspicious", and it
 * obliged: "should review tipping policy", "transaction occurs in the future".
 * Neither is something the Treasurer can act on, and a flag that fires on
 * nothing teaches people to ignore the flag that matters.
 *
 * So the model now picks from a fixed list or says nothing, and the wording
 * shown is ours, not its. Anything it invents outside the list is dropped.
 */

/** Code => the sentence the Treasurer actually sees. */
const SCAN_CONCERNS = [
    'illegible'  => 'Receipt is hard to read — check the amount by hand.',
    'no_total'   => 'No total found on the receipt.',
    'alcohol'    => 'Includes alcohol.',
    'personal'   => 'Looks like it may include a personal item.',
    'duplicate'  => 'Looks like a duplicate of another receipt.',
];

/** The instruction to paste into a scanner's prompt. */
function scanConcernPrompt(): string {
    return "For concerns: use exactly one of these codes, or null. Nothing else, "
         . "no sentences of your own:\n"
         . "  illegible  — the receipt cannot be read reliably\n"
         . "  no_total   — no total is shown anywhere on it\n"
         . "  alcohol    — it includes alcohol\n"
         . "  personal   — it looks like it includes a personal purchase\n"
         . "  duplicate  — it appears to be a second copy of the same receipt\n"
         . "Use null for everything else. Do not comment on tips, gratuities, "
         . "service charges, dates being in the future or the past, policy, "
         . "whether an amount seems large, or anything the Treasurer cannot act "
         . "on. A clean receipt returns null.";
}

/** A model's concerns value turned into something worth showing, or null. */
function scanConcern($raw): ?string {
    if (!is_string($raw)) return null;
    $key = strtolower(trim($raw));
    $key = trim($key, " .\t\n\r\0\x0B\"'");
    if ($key === '' || $key === 'null' || $key === 'none') return null;
    if (isset(SCAN_CONCERNS[$key])) return SCAN_CONCERNS[$key];
    // An older scan, or a model that wrote a sentence anyway: keep it only if it
    // is plainly one of the five, otherwise say nothing.
    foreach (['illegible' => ['illegible', 'unreadable', 'hard to read', 'blurry'],
              'no_total'  => ['no total', 'total missing', 'cannot find the total'],
              'alcohol'   => ['alcohol', 'wine', 'beer', 'liquor'],
              'personal'  => ['personal'],
              'duplicate' => ['duplicate']] as $code => $needles) {
        foreach ($needles as $n) {
            if (strpos($key, $n) !== false) return SCAN_CONCERNS[$code];
        }
    }
    return null;
}
