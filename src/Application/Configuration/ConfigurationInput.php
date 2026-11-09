<?php
declare(strict_types=1);

namespace MailDesk\Application\Configuration;

final readonly class ConfigurationInput
{
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $host,
        public int $port,
        public string $encryption,
        public string $username,
        public ?string $password,
    ) {
    }
}
