<?php
declare(strict_types=1);

namespace MailDesk\Infrastructure\Mail;

use MailDesk\Application\Port\{MailerInterface, OutgoingEmail, SendResult};
use MailDesk\Domain\Configuration\{Encryption, MailConfiguration};
use PHPMailer\PHPMailer\{Exception as MailException, PHPMailer, SMTP};

final class PHPMailerAdapter implements MailerInterface
{
    public function __construct(private int $timeoutSeconds = 10)
    {
    }

    public function send(MailConfiguration $config, string $plainPassword, OutgoingEmail $email): SendResult
    {
        $mail = $this->build($config, $plainPassword);
        try {
            $mail->setFrom((string) $config->senderEmail, $config->senderName);
            foreach ($email->to as $r) {
                $mail->addAddress($r['email'], $this->cleanName($r['name']));
            }
            foreach ($email->cc as $r) {
                $mail->addCC($r['email'], $this->cleanName($r['name']));
            }
            $mail->Subject = $email->subject;
            $mail->isHTML(true);
            $mail->Body = $email->bodyHtml;
            $mail->AltBody = $email->bodyText;
            $mail->send();
            return SendResult::ok();
        } catch (MailException $e) {
            return SendResult::failed(self::sanitise($mail->ErrorInfo . ' ' . $e->getMessage()));
        } catch (\Throwable) {
            return SendResult::failed('The mail server rejected the message.');
        } finally {
            $mail->smtpClose();
        }
    }

    public function testConnection(MailConfiguration $config, string $plainPassword): SendResult
    {
        $mail = $this->build($config, $plainPassword);
        try {
            return $mail->smtpConnect() ? SendResult::ok() : SendResult::failed('Could not connect to host.');
        } catch (MailException $e) {
            return SendResult::failed(self::sanitise($mail->ErrorInfo . ' ' . $e->getMessage()));
        } catch (\Throwable) {
            return SendResult::failed('Could not connect to host.');
        } finally {
            $mail->smtpClose();
        }
    }

    private function build(MailConfiguration $config, string $plainPassword): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $config->host;
        $mail->Port = $config->port;
        $mail->SMTPAuth = true;
        $mail->Username = $config->username;
        $mail->Password = $plainPassword;
        $mail->Timeout = $this->timeoutSeconds;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // never leak credentials to output
        $mail->SMTPSecure = match ($config->encryption) {
            Encryption::Tls => PHPMailer::ENCRYPTION_STARTTLS,
            Encryption::Ssl => PHPMailer::ENCRYPTION_SMTPS,
            Encryption::None => '',
        };
        $mail->SMTPAutoTLS = $config->encryption !== Encryption::None;
        return $mail;
    }

    private function cleanName(string $name): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $name));
    }

    /** Map raw SMTP errors to safe, user-facing text. */
    public static function sanitise(string $raw): string
    {
        $raw = strtolower($raw);
        return match (true) {
            str_contains($raw, 'authenticate') => 'Authentication failed (check username/password).',
            str_contains($raw, 'timed out'), str_contains($raw, 'timeout') => 'Timed out after 10 s.',
            str_contains($raw, 'tls'), str_contains($raw, 'ssl'), str_contains($raw, 'starttls') => 'TLS negotiation failed.',
            str_contains($raw, 'connect'), str_contains($raw, 'host') => 'Could not connect to host.',
            str_contains($raw, 'recipient') => 'One or more recipients were rejected by the server.',
            default => 'The mail server rejected the message.',
        };
    }
}
