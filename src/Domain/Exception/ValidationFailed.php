<?php
declare(strict_types=1);

namespace MailDesk\Domain\Exception;

final class ValidationFailed extends DomainException
{
    /** @param array<string,string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed.');
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
