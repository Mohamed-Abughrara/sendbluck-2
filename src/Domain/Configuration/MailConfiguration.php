<?php
declare(strict_types=1);

namespace MailDesk\Domain\Configuration;

use MailDesk\Domain\Exception\ValidationFailed;
use MailDesk\Domain\Shared\EmailAddress;

final readonly class MailConfiguration
{
    public string $senderName;

    public function __construct(
        public ?int $id,
        string $senderName,
        public EmailAddress $senderEmail,
        public string $host,
        public int $port,
        public Encryption $encryption,
        public string $username,
        public string $encryptedPassword,
        public bool $verified,
        public ?string $lastTestedAt = null,
    ) {
        $errors = [];
        $senderName = trim(str_replace(["\r", "\n"], ' ', $senderName));
        if ($senderName === '' || mb_strlen($senderName) > 100) {
            $errors['sender_name'] = 'Sender name is required (max 100 characters).';
        }
        if ($host === '' || strlen($host) > 255 || !preg_match('/^[A-Za-z0-9._:\-\[\]]+$/', $host)) {
            $errors['smtp_host'] = 'Enter a valid host name or IP address.';
        }
        if ($port < 1 || $port > 65535) {
            $errors['smtp_port'] = 'Port must be between 1 and 65535.';
        }
        if ($username === '' || strlen($username) > 254 || preg_match('/[\r\n]/', $username)) {
            $errors['smtp_username'] = 'Username is required (max 254 characters).';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }
        $this->senderName = $senderName;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }
}
