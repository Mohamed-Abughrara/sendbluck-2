<?php
declare(strict_types=1);

namespace MailDesk\Domain\Exception;

final class RateLimited extends DomainException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("Too many attempts. Try again in {$retryAfter} seconds.");
    }
}
