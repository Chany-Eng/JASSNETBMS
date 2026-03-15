<?php

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class JASSnetMailSender
{
    private string $host;
    private bool $smtpAuth;
    private string $username;
    private string $password;
    private string $encryption;
    private int $port;
    private string $fromEmail;
    private string $fromName;

    public function __construct(
        string $host,
        bool $smtpAuth,
        string $username,
        string $password,
        string $encryption,
        int $port,
        string $fromEmail,
        string $fromName
    ) {
        $this->host = trim($host);
        $this->smtpAuth = $smtpAuth;
        $this->username = trim($username);
        $this->password = trim($password);
        $this->encryption = trim($encryption);
        $this->port = max(1, $port);
        $this->fromEmail = trim($fromEmail);
        $this->fromName = trim($fromName);
    }

    public function isConfigured(): bool
    {
        if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
            return false;
        }

        return $this->host !== ''
            && $this->username !== ''
            && $this->password !== ''
            && $this->fromEmail !== '';
    }

    public function sendOtpEmail(string $email, string $recipientName, string $otpCode, int $expiryMinutes): array
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'invalid_email',
                'email' => $email,
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'mail_not_configured',
                'email' => $email,
            ];
        }

        $safeName = trim($recipientName) !== '' ? trim($recipientName) : 'User';
        $subject = 'ERMS Login OTP';
        $htmlBody = '<div style="font-family:Segoe UI,Arial,sans-serif;color:#1f2937;line-height:1.6">'
            . '<h2 style="margin:0 0 12px;color:#17365c">ERMS Login Verification</h2>'
            . '<p>Hello ' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Your one-time password for ERMS login is:</p>'
            . '<p style="font-size:30px;font-weight:700;letter-spacing:6px;margin:18px 0;color:#111827">' . htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>This code will expire in ' . $expiryMinutes . ' minutes.</p>'
            . '<p>If you did not request this login, please contact the administrator immediately.</p>'
            . '<p style="margin-top:24px;color:#6b7280">JASSNET ERMS</p>'
            . '</div>';
        $textBody = "Hello {$safeName},\n\nYour ERMS one-time password is {$otpCode}.\nIt will expire in {$expiryMinutes} minutes.\n\nIf you did not request this login, please contact the administrator immediately.\n\nJASSNET ERMS";

        return $this->sendEmail($email, $safeName, $subject, $htmlBody, $textBody);
    }

    public function sendEmail(string $email, string $recipientName, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = $this->smtpAuth;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->SMTPSecure = $this->encryption;
            $mail->Port = $this->port;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->fromEmail, $this->fromName !== '' ? $this->fromName : $this->fromEmail);
            $mail->addAddress($email, $recipientName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);
            $mail->send();

            return [
                'success' => true,
                'email' => $email,
                'message_id' => $mail->getLastMessageID(),
            ];
        } catch (Exception $exception) {
            return [
                'success' => false,
                'email' => $email,
                'error' => $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $exception->getMessage(),
            ];
        }
    }
}

$mailSender = new JASSnetMailSender(
    defined('MAIL_HOST') ? MAIL_HOST : '',
    defined('MAIL_SMTP_AUTH') ? (bool) MAIL_SMTP_AUTH : true,
    defined('MAIL_USERNAME') ? MAIL_USERNAME : '',
    defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '',
    defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls',
    defined('MAIL_PORT') ? (int) MAIL_PORT : 587,
    defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : '',
    defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'JASSNET ERMS'
);