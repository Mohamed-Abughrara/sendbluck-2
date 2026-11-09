<?php
declare(strict_types=1);

namespace MailDesk\Domain\Exception;

final class TooManyRecipients extends DomainException
{
    public function __construct(public readonly int $max)
    {
        parent::__construct("Maximum {$max} recipients per message.");
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return ['to' => $this->getMessage()];
    }
}
