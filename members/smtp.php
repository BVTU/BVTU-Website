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

function _siteMailLog(string $to, string $subject, bool $ok, string $err = ''): void {
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
        $mail->addAddress($to);

        $mail->isHTML($isHtml);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
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
