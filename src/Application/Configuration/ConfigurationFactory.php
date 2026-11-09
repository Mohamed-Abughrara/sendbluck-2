<?php
declare(strict_types=1);

namespace MailDesk\Application\Configuration;

use MailDesk\Domain\Configuration\{Encryption, MailConfiguration};
use MailDesk\Domain\Exception\{InvalidEmailAddress, ValidationFailed};
use MailDesk\Domain\Shared\EmailAddress;

/** Builds a validated MailConfiguration from raw input, collecting all field errors. */
final class ConfigurationFactory
{
    public static function build(ConfigurationInput $in, string $encryptedPassword, bool $verified, ?string $lastTestedAt, ?int $id): MailConfiguration
    {
        $errors = [];
        $email = null;
        try {
            $email = new EmailAddress($in->senderEmail);
        } catch (InvalidEmailAddress $e) {
            $errors['sender_email'] = $e->getMessage();
        }
        $enc = Encryption::tryFrom($in->encryption);
        if ($enc === null) {
            $errors['encryption'] = 'Choose TLS, SSL or None.';
        }

        try {
            if ($errors !== []) {
                // still validate the remaining fields to report everything at once
                try {
                    new MailConfiguration($id, $in->senderName, new EmailAddress('x@example.com'), $in->host, $in->port, Encryption::Tls, $in->username, '', false);
                } catch (ValidationFailed $e) {
                    $errors += $e->errors();
                }
                throw new ValidationFailed($errors);
            }
            return new MailConfiguration($id, $in->senderName, $email, trim($in->host), $in->port, $enc, trim($in->username), $encryptedPassword, $verified, $lastTestedAt);
        } catch (ValidationFailed $e) {
            throw new ValidationFailed($e->errors() + $errors);
        }
    }
}
