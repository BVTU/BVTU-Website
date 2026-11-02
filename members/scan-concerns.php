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

/**
 * How to read a photograph holding two receipts for one purchase.
 *
 * A restaurant hands over an itemised bill and a card slip with the tip added,
 * and both get photographed together. Which one a scanner calls "the" total is
 * close to a coin toss, and reading the itemised one puts an amount on the
 * voucher that was never charged — short by the tip, every time.
 *
 * $wantOthers asks for the figures it did not pick as well. Only the LP voucher
 * has somewhere to keep them and a reviewer that compares against them.
 */
function scanPairPrompt(bool $wantOthers = false): string {
    $out = "One photo often holds more than one receipt for the same purchase: "
         . "an itemised bill and a card slip with the tip added. When it does, "
         . "total_amount is the amount actually CHARGED — the card slip total, "
         . "tip included. That is what was paid and what is being claimed. Never "
         . "the itemised subtotal and never a pre-tip total when a higher charged "
         . "amount is legible. A tip written on by hand is part of the charged "
         . "amount; add it.";
    if ($wantOthers) {
        $out .= " other_amounts lists the other figures in the image that someone "
              . "could reasonably have claimed as the cost of this purchase: the "
              . "itemised bill's total, a pre-tip total, the second receipt's "
              . "total. NOT amount tendered, change due, cash back, a tax or tip "
              . "line on its own, individual item prices, a loyalty balance or a "
              . "running account balance. Amounts only, no labels, and [] when "
              . "there is only one figure anyone could claim.";
    }
    $out .= " Two receipts for two DIFFERENT purchases in one photo is not this "
          . "case: read the one being claimed"
          . ($wantOthers ? " and leave other_amounts empty." : ".");
    return $out;
}

/**
 * The model's other_amounts, reduced to figures worth comparing against.
 *
 * This is model output heading for a column the reviewer treats as permission:
 * a claim matching anything in here raises no finding. One junk entry would
 * quietly excuse a real mismatch, so only plausible money survives.
 */
function scanOtherAmounts($extracted): array {
    $raw = is_array($extracted) ? ($extracted['other_amounts'] ?? null) : null;
    if (!is_array($raw)) return [];
    $out = [];
    foreach ($raw as $v) {
        if (!is_scalar($v)) continue;
        $v = round((float)$v, 2);
        if ($v > 0 && $v < 100000 && !in_array($v, $out, true)) $out[] = $v;
        if (count($out) >= 6) break;
    }
    return $out;
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
