<?php
/**
 * smtp.php — Site-wide SMTP mail helper using PHPMailer.
 *
 * Configuration comes from config.php constants:
 *   SMTP_HOST      e.g. 'smtp.hostinger.com'
 *   SMTP_PORT      e.g. 587
 *   SMTP_USER      e.g. 'noreply@bvtu.ca'
 *   SMTP_PASS      the account password
 *   SMTP_FROM_NAME e.g. 'BVTU Member Portal'
 *
 * Usage:
 *   siteMail('someone@example.com', 'Subject', 'Body text');
 *   siteMail('someone@example.com', 'Subject', '<p>HTML</p>', true);
 *
 * Returns true on success, false on failure (errors logged to PHP error_log).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/lib/phpmailer/Exception.php';
require_once __DIR__ . '/lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/lib/phpmailer/SMTP.php';

/**
 * Where an account's mail actually goes.
 *
 * The one place that decides it, because every email the site sends passes
 * through here. The President signs in with a personal address and reads
 * union mail at lp54@bctf.ca; expense claims were arriving in the wrong
 * inbox. This changes the envelope only — the account's email is still its
 * identity everywhere else, so who may sign what does not move.
 */
/*
 * siteIsTest() lives in inc/env.php and is required here rather than declared
 * again. Two unguarded copies of the same function is a fatal on any page that
 * loads both the shared nav and the mailer — which is most of them.
 */
require_once __DIR__ . '/../inc/env.php';

/** Where every email goes on the test site. */
function siteTestInbox(): string {
    return defined('SITE_TEST_INBOX') ? trim((string)SITE_TEST_INBOX) : '';
}

/**
 * The subject as it should arrive, tagged on the test site with who it would
 * really have gone to — which is most of what you want to check.
 */
function siteMailSubject(string $subject, string $realTo): string {
    if (!siteIsTest()) return $subject;
    return '[TEST → ' . ($realTo !== '' ? $realTo : 'nobody') . '] ' . $subject;
}

function siteDeliveryAddress(string $account): string {
    /*
     * Every email on the test site goes to one inbox instead of the member it
     * names. This is the single most important thing about having a staging
     * copy: the test site may well be pointed at the live database, and without
     * this, opening a page that happens to send would mail real members real
     * notices about things that did not happen.
     *
     * With no inbox configured we return nothing and let the send fail. A test
     * server that cannot mail anyone is a nuisance; one that quietly falls
     * through to the real address is the exact accident this prevents.
     */
    if (siteIsTest()) {
        $inbox = siteTestInbox();
        if ($inbox === '') {
            error_log('siteDeliveryAddress: SITE_ENV is test but SITE_TEST_INBOX is not set — '
                    . 'refusing to deliver to ' . $account);
        }
        return $inbox;
    }

    static $cache = [];
    $key = strtolower(trim($account));
    if ($key === '') return $account;
    if (array_key_exists($key, $cache)) return $cache[$key];

    $to = $account;
    try {
        require_once __DIR__ . '/db.php';
        $s = getDB()->prepare("SELECT notify_email FROM members WHERE LOWER(email)=? LIMIT 1");
        $s->execute([$key]);
        $alt = trim((string)$s->fetchColumn());
        if ($alt !== '' && filter_var($alt, FILTER_VALIDATE_EMAIL)) $to = $alt;
    } catch (\Throwable $e) {
        /* \Throwable, not Exception: this file aliases PHPMailer's own
         * Exception at the top, so `catch (Exception $e)` here catches that
         * class and NOT the PDOException this exists to swallow — which would
         * escape siteMail() altogether and 500 the password-reset page on any
         * database where the column has not been added yet. */
        error_log('siteDeliveryAddress: ' . $e->getMessage());
    }
    return $cache[$key] = $to;
}

function _siteMailLog(string $to, string $subject, bool $ok, string $err = ''): void {
    /* Only a bare address can be resolved; the CC path hands this a composed
     * label ("someone@x (cc ...)"), which is not an account and must be left
     * as written rather than looked up and silently not annotated. */
    if (strpos($to, ' ') === false) {
        $delivered = siteDeliveryAddress($to);
        if (strcasecmp($delivered, $to) !== 0) $to .= ' -> ' . $delivered;
    }
    static $tableReady = false;
    try {
        require_once __DIR__ . '/db.php';
        $db = getDB();
        if (!$tableReady) {
            $db->exec("CREATE TABLE IF NOT EXISTS site_email_log (
                id        INT AUTO_INCREMENT PRIMARY KEY,
                sent_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
                to_email  VARCHAR(255) NOT NULL,
                subject   VARCHAR(500) NOT NULL,
                status    VARCHAR(10)  NOT NULL,
                error_msg VARCHAR(500),
                INDEX idx_sent (sent_at),
                INDEX idx_to   (to_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $tableReady = true;
        }
        $db->prepare("INSERT INTO site_email_log (to_email, subject, status, error_msg) VALUES (?,?,?,?)")
           ->execute([$to, $subject, $ok ? 'sent' : 'failed', $err ?: null]);
    } catch (\Throwable $e) {
        error_log('siteMailLog: ' . $e->getMessage());
    }
}

/**
 * Like siteMail(), with file attachments and an optional Reply-To.
 *
 * From stays the authenticated SMTP mailbox. Putting someone else's address
 * there — lp54@bctf.ca, say — means the recipient's server checks that domain's
 * SPF record, doesn't find this host, and bins the message. Reply-To carries
 * the human address instead, which is unauthenticated and so unaffected.
 *
 * $attachments: [ ['path' => '/abs/path', 'name' => 'shown_name.jpg'], ... ]
 */
function siteMailWithAttachments(
    string $to,
    string $subject,
    string $body,
    array $attachments = [],
    string $replyTo = '',
    string $replyToName = '',
    string $fromNameOverride = '',
    array $cc = []
): bool {
    $cfg = __DIR__ . '/config.php';
    if (file_exists($cfg)) require_once $cfg;

    $host = defined('SMTP_HOST') ? SMTP_HOST : null;
    $port = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
    $user = defined('SMTP_USER') ? SMTP_USER : null;
    $pass = defined('SMTP_PASS') ? SMTP_PASS : null;
    $fromName = $fromNameOverride ?: (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'BVTU Member Portal');

    if (!$host || !$user || !$pass) {
        error_log('siteMailWithAttachments: SMTP not configured');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = ($port === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;

        $mail->setFrom($user, $fromName);
        if ($replyTo) {
            $mail->addReplyTo($replyTo, $replyToName ?: $replyTo);
        } else {
            $mail->addReplyTo($user, $fromName);
        }
        $mail->addAddress(siteDeliveryAddress($to));
        foreach ($cc as $addr) {
            // Redirected the same way as the To: line. bctf-forms.php CCs the
            // President, which is the very inbox this feature exists to fix.
            if (filter_var($addr, FILTER_VALIDATE_EMAIL)) $mail->addCC(siteDeliveryAddress($addr));
        }

        foreach ($attachments as $a) {
            if (!empty($a['path']) && file_exists($a['path'])) {
                $mail->addAttachment($a['path'], $a['name'] ?? basename($a['path']));
            }
        }

        $mail->isHTML(false);
        $mail->CharSet = 'UTF-8';
        // Tagged on test with the address it would really have gone to.
        $mail->Subject = siteMailSubject($subject, $to);
        $mail->Body    = $body;

        $mail->send();
        // The log is keyed on one recipient, so record the copies in the subject
        // rather than leaving them invisible.
        $logTo = $to;
        $dest  = siteDeliveryAddress($to);
        if (strcasecmp($dest, $to) !== 0) $logTo .= ' -> ' . $dest;
        _siteMailLog($logTo . ($cc ? ' (cc ' . implode(', ', $cc) . ')' : ''), $subject, true);
        return true;
    } catch (Exception $e) {
        $err = $mail->ErrorInfo;
        error_log("siteMailWithAttachments failed to {$to}: " . $err);
        _siteMailLog($to, $subject, false, $err);
        return false;
    }
}

function siteMail(string $to, string $subject, string $body, bool $isHtml = false): bool {
    // Load config if not already loaded
    $cfg = __DIR__ . '/config.php';
    if (file_exists($cfg)) require_once $cfg;

    $host     = defined('SMTP_HOST')      ? SMTP_HOST      : null;
    $port     = defined('SMTP_PORT')      ? (int)SMTP_PORT : 587;
    $user     = defined('SMTP_USER')      ? SMTP_USER      : null;
    $pass     = defined('SMTP_PASS')      ? SMTP_PASS      : null;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'BVTU Member Portal';
    $fromAddr = $user ?: 'lp54@bctf.ca';

    if (!$host || !$user || !$pass) {
        error_log("siteMail: SMTP not configured (SMTP_HOST/SMTP_USER/SMTP_PASS missing in config.php)");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $user;
        $mail->Password   = $pass;
        $mail->SMTPSecure = ($port === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $port;

        $mail->setFrom($fromAddr, $fromName);
        $mail->addReplyTo($fromAddr, $fromName);
        $mail->addAddress(siteDeliveryAddress($to));

        $mail->isHTML($isHtml);
        $mail->CharSet = 'UTF-8';
        // Tagged on test with the address it would really have gone to.
        $mail->Subject = siteMailSubject($subject, $to);
        $mail->Body    = $body;
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        _siteMailLog($to, $subject, true);
        return true;
    } catch (Exception $e) {
        $err = $mail->ErrorInfo;
        error_log("siteMail failed to {$to}: " . $err);
        _siteMailLog($to, $subject, false, $err);
        return false;
    }
}
