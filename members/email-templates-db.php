<?php
/**
 * email-templates-db.php — editable wording for the automated emails.
 *
 * Only the PROSE is editable: the subject and the paragraphs a person wrote.
 * The generated parts — claim detail tables, amounts, approval links, the HTML
 * wrapper — stay owned by the code, because an email that has lost its approval
 * link or its reference number is worse than one with dated wording.
 *
 * Defaults live in EMAIL_TEMPLATES below, so an email nobody has edited keeps
 * working exactly as it did, and "Reset to default" always has something to
 * return to. The database only ever holds overrides.
 *
 * Why this exists: the claim emails told members their claim was "awaiting
 * review by the BVTU Treasurer" for months after the President became the first
 * signer, because that sentence was buried in string concatenation in exp-db.php
 * where nobody reads it.
 */
require_once __DIR__ . '/db.php';

/**
 * format: 'text' for plain-text emails, 'html' for the wrapped HTML ones.
 * vars:   placeholder => what it will contain, shown to whoever edits.
 * blocks: the editable paragraphs, in the order they appear in the email.
 */
const EMAIL_TEMPLATES = [
    'invite_link' => [
        'label'   => 'Invitation — set up your account',
        'when'    => 'Sent when an admin emails someone their registration link.',
        'format'  => 'text',
        'subject' => 'Set up your BVTU member account',
        'vars'    => ['{{email}}' => 'the recipient’s email address',
                      '{{link}}'  => 'their one-time registration link'],
        'blocks'  => [
            'intro' => ['label' => 'Opening',
                        'text'  => "Hi {{email}},\n\nThe Bulkley Valley Teachers' Union has created a member portal where you can access union resources, submit expense claims, and more.\n\nUse the link below to set up your account. It's one-time use and expires in 72 hours."],
            'outro' => ['label' => 'Closing',
                        'text'  => "If you weren't expecting this email, you can ignore it — no account will be created unless you click the link and set a password.\n\nQuestions? Reply to lp54@bctf.ca\n\n— Bulkley Valley Teachers' Union"],
        ],
    ],

    'invite_welcome' => [
        'label'   => 'Welcome — account created',
        'when'    => 'Sent the moment someone finishes setting up their account.',
        'format'  => 'text',
        'subject' => 'Welcome to the BVTU Member Portal',
        'vars'    => ['{{name}}' => 'their name', '{{portal_url}}' => 'the dashboard address'],
        'blocks'  => [
            'body' => ['label' => 'Message',
                       'text'  => "Hi {{name}},\n\nYour BVTU member account is set up. You can log in any time at:\n{{portal_url}}\n\nIf you have questions, reach out at lp54@bctf.ca.\n\n— Bulkley Valley Teachers' Union"],
        ],
    ],

    'password_reset' => [
        'label'   => 'Password reset',
        'when'    => 'Sent when someone asks to reset their password.',
        'format'  => 'text',
        'subject' => 'BVTU — Password Reset Request',
        'vars'    => ['{{name}}' => 'their name', '{{link}}' => 'the reset link'],
        'blocks'  => [
            'body' => ['label' => 'Message',
                       'text'  => "Hi {{name}},\n\nWe received a request to reset your BVTU member portal password.\n\nClick the link below to set a new password. This link expires in 1 hour.\n\n{{link}}\n\nIf you did not request a password reset, you can safely ignore this email — your password has not changed.\n\n— Bulkley Valley Teachers' Union"],
        ],
    ],

    'claim_submitted' => [
        'label'   => 'Expense claim — submitted',
        'when'    => 'Sent to the member when their claim is submitted.',
        'format'  => 'html',
        'subject' => 'Expense Claim Submitted — {{ref}}',
        'vars'    => ['{{ref}}' => 'the claim reference', '{{submitter}}' => 'who submitted it, when on someone’s behalf'],
        'blocks'  => [
            'intro'    => ['label' => 'Opening (claim submitted by the member)',
                           'text'  => 'Your expense claim has been submitted and is awaiting approval by the BVTU President.'],
            'on_behalf'=> ['label' => 'Opening (submitted on their behalf)',
                           'text'  => 'An expense claim was submitted on your behalf by {{submitter}} and is awaiting approval by the BVTU President.'],
            'outro'    => ['label' => 'Closing',
                           'text'  => 'You will receive an email when it has been reviewed.'],
        ],
    ],

    'claim_authorized' => [
        'label'   => 'Expense claim — approved, payment coming',
        'when'    => 'Sent to the member once both signatures are in.',
        'format'  => 'html',
        'subject' => 'Expense Approved — {{ref}} — E-transfer within 3 business days',
        'vars'    => ['{{ref}}' => 'the claim reference', '{{email}}' => 'where the e-transfer goes'],
        'blocks'  => [
            'intro' => ['label' => 'Opening',
                        'text'  => 'Your expense claim has been approved by both the Local President and the Treasurer.'],
            'payment' => ['label' => 'Payment paragraph',
                          'text'  => 'An e-transfer for the full amount will be sent to {{email}} within 3 business days. Use {{ref}} as the security question answer if prompted.'],
        ],
    ],

    'claim_rejected' => [
        'label'   => 'Expense claim — rejected',
        'when'    => 'Sent to the member when a claim is rejected. The reason given by the signer is added automatically.',
        'format'  => 'html',
        'subject' => 'Expense Claim Rejected — {{ref}}',
        'vars'    => ['{{ref}}' => 'the claim reference'],
        'blocks'  => [
            'intro' => ['label' => 'Opening',
                        'text'  => 'Your expense claim has been rejected.'],
            'outro' => ['label' => 'Closing',
                        'text'  => 'If you have questions, please contact the BVTU Treasurer.'],
        ],
    ],

    'claim_paid' => [
        'label'   => 'Expense claim — paid',
        'when'    => 'Sent to the member when the e-transfer goes out.',
        'format'  => 'html',
        'subject' => 'Expense Claim Paid — {{ref}}',
        'vars'    => ['{{ref}}' => 'the claim reference'],
        'blocks'  => [
            'intro' => ['label' => 'Opening',
                        'text'  => 'Your expense claim payment has been sent!'],
            'outro' => ['label' => 'Closing',
                        'text'  => 'Watch for an Interac e-transfer for the full amount. Use {{ref}} as the reference if asked.'],
        ],
    ],

    'collab_received' => [
        'label'   => 'Collaboration grant — application received',
        'when'    => 'Sent to the applicant as soon as they submit.',
        'format'  => 'text',
        'subject' => 'We received your Collaboration Grant application — BVTU',
        'vars'    => ['{{name}}' => 'the applicant’s name'],
        'blocks'  => [
            'body' => ['label' => 'Message',
                       'text'  => "Hi {{name}},\n\nThank you for submitting your BVTU Collaboration Grant application! We've received it and it will be reviewed at the next monthly Executive Meeting. You'll hear back from us by the 15th of the month.\n\nIf you have any questions in the meantime, feel free to reach out at lp54@bctf.ca.\n\nBulkley Valley Teachers' Union"],
        ],
    ],

    'collab_approved' => [
        'label'   => 'Collaboration grant — approved',
        'when'    => 'Sent to the applicant when their grant is approved.',
        'format'  => 'text',
        'subject' => 'Your Collaboration Grant Application is Approved — BVTU',
        'vars'    => ['{{name}}'        => 'the applicant’s name',
                      '{{days}}'        => 'how many release days',
                      '{{day_word}}'    => '“day” or “days”, to match',
                      '{{collab_line}}' => 'a note about their collaborator, when they named one'],
        'blocks'  => [
            'intro' => ['label' => 'Opening',
                        'text'  => "Hi {{name}},\n\nGreat news — your BVTU Collaboration Grant application has been approved! You've been granted {{days}} release {{day_word}} to use this school year."],
            'booking' => ['label' => 'Booking instructions',
                          'text'  => "Once you have your date(s) confirmed, please submit your absence in Atrieve using \"BVTU business\" as the absence reason. {{collab_line}}\n\nWe really appreciate it when members book with plenty of notice — please aim for at least two weeks ahead of your planned date(s). This gives the district time to arrange TTOC coverage and avoids any last-minute scrambling. The earlier, the better!"],
            'signoff' => ['label' => 'Sign-off',
                          'text'  => "If you have any questions or run into anything, don't hesitate to reach out — we're happy to help.\n\nCody Lind\nPresident, Bulkley Valley Teachers' Union"],
        ],
    ],
];

function emailTplEnsure(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        getDB()->exec("CREATE TABLE IF NOT EXISTS email_templates (
            tpl_key    VARCHAR(60) PRIMARY KEY,
            subject    VARCHAR(255) NOT NULL DEFAULT '',
            blocks     TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by VARCHAR(255) NOT NULL DEFAULT ''
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
}

/** Stored overrides for one template, or [] when it has never been edited. */
function emailTplOverride(string $key): array {
    static $cache = null;
    if ($cache === null) {
        emailTplEnsure();
        $cache = [];
        try {
            foreach (getDB()->query("SELECT tpl_key, subject, blocks FROM email_templates")->fetchAll() as $r) {
                $cache[$r['tpl_key']] = [
                    'subject' => (string)$r['subject'],
                    'blocks'  => json_decode((string)$r['blocks'], true) ?: [],
                ];
            }
        } catch (Exception $e) {}
    }
    return $cache[$key] ?? [];
}

/** The template as it will actually send: defaults with any overrides applied. */
function emailTpl(string $key): array {
    $def = EMAIL_TEMPLATES[$key] ?? null;
    if (!$def) return [];
    $ov  = emailTplOverride($key);

    $def['subject_live'] = ($ov['subject'] ?? '') !== '' ? $ov['subject'] : $def['subject'];
    foreach ($def['blocks'] as $bk => $b) {
        $live = $ov['blocks'][$bk] ?? null;
        $def['blocks'][$bk]['live']    = ($live !== null && trim($live) !== '') ? $live : $b['text'];
        $def['blocks'][$bk]['edited']  = ($live !== null && trim($live) !== '' && $live !== $b['text']);
    }
    $def['edited'] = !empty($ov);
    return $def;
}

/** Substitute {{placeholders}}. HTML templates escape the values, text ones do not. */
function _emailTplFill(string $s, array $vars, bool $html): string {
    $from = array_keys($vars);
    $to   = array_map(function ($v) use ($html) {
        return $html ? htmlspecialchars((string)$v, ENT_QUOTES) : (string)$v;
    }, array_values($vars));
    return str_replace($from, $to, $s);
}

function emailTplSubject(string $key, array $vars = []): string {
    $t = emailTpl($key);
    if (!$t) return '';
    // Never HTML — a subject line is plain text in every mail client.
    return _emailTplFill($t['subject_live'], $vars, false);
}

/**
 * One block, ready to drop into the email.
 *
 * For an HTML template both the wording and the substituted values are escaped,
 * and blank lines become paragraphs — so the wording is plain text and cannot
 * introduce markup, broken or otherwise, into the email around it. HTML tags
 * typed here will appear as typed rather than take effect. For a text template
 * the block is returned as written.
 */
function emailTplBlock(string $key, string $block, array $vars = []): string {
    $t = emailTpl($key);
    if (!$t || !isset($t['blocks'][$block])) return '';
    $html = ($t['format'] ?? 'text') === 'html';
    if (!$html) return _emailTplFill($t['blocks'][$block]['live'], $vars, false);

    // Escape the wording FIRST, then substitute values that are escaped as they
    // go in. Both end up escaped exactly once: an ampersand in "Tom & Jerry" is
    // safe whether it was typed on the wording page or came from a member's
    // name, and neither can introduce markup into the email.
    $text = htmlspecialchars($t['blocks'][$block]['live'], ENT_QUOTES);
    $raw  = _emailTplFill($text, $vars, true);

    $out = '';
    foreach (preg_split('/\n\s*\n/', trim($raw)) as $para) {
        if (trim($para) === '') continue;
        $out .= '<p>' . nl2br(trim($para)) . '</p>';
    }
    return $out;
}

function emailTplSave(string $key, string $subject, array $blocks, string $actor): bool {
    if (!isset(EMAIL_TEMPLATES[$key])) return false;
    emailTplEnsure();
    // Only blocks this template actually has, so a stale form cannot add keys.
    $clean = [];
    foreach (EMAIL_TEMPLATES[$key]['blocks'] as $bk => $_) {
        if (isset($blocks[$bk])) $clean[$bk] = (string)$blocks[$bk];
    }
    try {
        getDB()->prepare(
            "INSERT INTO email_templates (tpl_key, subject, blocks, updated_by) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE subject=VALUES(subject), blocks=VALUES(blocks), updated_by=VALUES(updated_by)"
        )->execute([$key, mb_substr(trim($subject), 0, 255), json_encode($clean), $actor]);
        return true;
    } catch (Exception $e) {
        error_log('emailTplSave: ' . $e->getMessage());
        return false;
    }
}

/** Deleting the override is the reset: the default in code is always intact. */
function emailTplReset(string $key): bool {
    emailTplEnsure();
    try {
        getDB()->prepare("DELETE FROM email_templates WHERE tpl_key=?")->execute([$key]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
