<?php
declare(strict_types=1);

namespace MailDesk\Domain\Exception;

final class InvalidCredentials extends DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid email or password.');
    }
}
