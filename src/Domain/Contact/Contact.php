<?php
declare(strict_types=1);

namespace MailDesk\Domain\Contact;

use MailDesk\Domain\Exception\ValidationFailed;
use MailDesk\Domain\Shared\EmailAddress;

final readonly class Contact
{
    public string $name;

    public function __construct(
        public ?int $id,
        string $name,
        public EmailAddress $email,
        public ?string $createdAt = null,
    ) {
        $name = trim(str_replace(["\r", "\n"], ' ', $name));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ValidationFailed(['name' => 'Name is required (max 100 characters).']);
        }
        $this->name = $name;
    }
}
