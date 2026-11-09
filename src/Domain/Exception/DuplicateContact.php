<?php
declare(strict_types=1);

namespace MailDesk\Domain\Exception;

final class DuplicateContact extends DomainException
{
    public function __construct()
    {
        parent::__construct('A contact with this email already exists.');
    }
}
