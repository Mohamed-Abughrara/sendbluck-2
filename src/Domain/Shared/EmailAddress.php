<?php
declare(strict_types=1);

namespace MailDesk\Domain\Shared;

use MailDesk\Domain\Exception\InvalidEmailAddress;

final readonly class EmailAddress
{
    public string $value;

    public function __construct(string $value)
    {
        $value = strtolower(trim($value));
        if ($value === ''
            || strlen($value) > 254
            || preg_match('/[\r\n]/', $value)
            || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailAddress($value);
        }
        $this->value = $value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
