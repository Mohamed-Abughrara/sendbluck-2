<?php
declare(strict_types=1);

namespace MailDesk\Domain\User;

use MailDesk\Domain\Shared\EmailAddress;

final readonly class User
{
    public function __construct(
        public ?int $id,
        public string $name,
        public EmailAddress $email,
        public string $passwordHash,
    ) {
    }
}
