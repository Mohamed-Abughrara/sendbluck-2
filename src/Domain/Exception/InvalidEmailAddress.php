<?php
declare(strict_types=1);

namespace MailDesk\Domain\Exception;

final class InvalidEmailAddress extends DomainException
{
    public function __construct(string $value = '')
    {
        parent::__construct('Enter a valid email address.');
    }
}
