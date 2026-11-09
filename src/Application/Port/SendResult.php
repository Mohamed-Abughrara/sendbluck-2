<?php
declare(strict_types=1);

namespace MailDesk\Application\Port;

final readonly class SendResult
{
    private function __construct(public bool $success, public ?string $error)
    {
    }

    public static function ok(): self
    {
        return new self(true, null);
    }

    public static function failed(string $sanitisedError): self
    {
        return new self(false, $sanitisedError);
    }
}
