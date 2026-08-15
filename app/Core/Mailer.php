<?php
declare(strict_types=1);

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Thin PHPMailer wrapper.
 *
 * Design notes:
 *  - send() never throws. Mail failure must not roll back a committed RFQ.
 *    It returns false and records the reason in email_log.
 *  - Every attempt is written to email_log, successful or not.
 *  - MAIL_ENABLED=false turns delivery off (useful for local dev and CI)
 *    while still writing the log rows, so flows can be tested end to end.
 */
class Mailer
{
    /**
     * @param string      $to          Recipient address
     * @param string      $subject     Subject line
     * @param string      $htmlBody    HTML body
     * @param string|null $textBody    Plain-text alternative
     * @param string|null $template    Label for the log (e.g. 'rfq_invite')
     * @param string|null $relatedType Entity type for the log
     * @param int|null    $relatedId   Entity id for the log
     */
    public static function send(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?string $template = null,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::log($to, $subject, $template, $relatedType, $relatedId,
                'FAILED', 'Invalid recipient address');
            return false;
        }

        if (!Config::bool('MAIL_ENABLED', true)) {
            self::log($to, $subject, $template, $relatedType, $relatedId,
                'SENT', null);
            error_log("[Mailer] MAIL_ENABLED=false, suppressed mail to {$to}");
            return true;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = Config::require('MAIL_HOST');
            $mail->Port       = Config::int('MAIL_PORT', 587);
            $mail->CharSet    = 'UTF-8';

            $username = Config::get('MAIL_USERNAME');
            $password = Config::get('MAIL_PASSWORD');

            if ($username !== null && $username !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = (string) $password;
            }

            $encryption = strtolower((string) Config::get('MAIL_ENCRYPTION', 'tls'));
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->setFrom(
                Config::require('MAIL_FROM_ADDRESS'),
                (string) Config::get('MAIL_FROM_NAME', 'BuildProcure')
            );

            $replyTo = Config::get('MAIL_REPLY_TO');
            if ($replyTo !== null && $replyTo !== '') {
                $mail->addReplyTo($replyTo);
            }

            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?? strip_tags($htmlBody);

            $mail->send();

            self::log($to, $subject, $template, $relatedType, $relatedId,
                'SENT', null);

            return true;

        } catch (PHPMailerException $e) {
            $reason = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
            self::log($to, $subject, $template, $relatedType, $relatedId,
                'FAILED', $reason);
            error_log("[Mailer] Failed to {$to}: {$reason}");
            return false;

        } catch (\Throwable $e) {
            self::log($to, $subject, $template, $relatedType, $relatedId,
                'FAILED', $e->getMessage());
            error_log("[Mailer] Unexpected error to {$to}: " . $e->getMessage());
            return false;
        }
    }

    private static function log(
        string $recipient,
        string $subject,
        ?string $template,
        ?string $relatedType,
        ?int $relatedId,
        string $status,
        ?string $error
    ): void {
        try {
            $conn = DB::getConnection();

            $stmt = $conn->prepare("
                INSERT INTO email_log
                    (recipient, subject, template, related_type,
                     related_id, status, error_message)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $truncatedError = $error !== null ? substr($error, 0, 500) : null;

            $stmt->bind_param(
                'ssssiss',
                $recipient,
                $subject,
                $template,
                $relatedType,
                $relatedId,
                $status,
                $truncatedError
            );
            $stmt->execute();

        } catch (\Throwable $e) {
            // Logging must never break the caller.
            error_log('[Mailer] email_log write failed: ' . $e->getMessage());
        }
    }
}
