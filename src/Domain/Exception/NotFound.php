<?php
declare(strict_types=1);

namespace MailDesk\Domain\Exception;

final class NotFound extends DomainException
{
    public function __construct(string $what = 'Resource')
    {
        parent::__construct($what . ' not found.');
    }
}
