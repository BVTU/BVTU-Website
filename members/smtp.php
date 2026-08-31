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
        return true;
    } catch (Exception $e) {
        error_log("siteMail failed to {$to}: " . $mail->ErrorInfo);
        return false;
    }
}
