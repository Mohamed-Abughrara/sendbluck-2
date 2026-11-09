<?php
declare(strict_types=1);

namespace MailDesk\Application\Configuration;

final readonly class TestOutcome
{
    public function __construct(
        public bool $success,
        public ?string $error,
        public string $fingerprint,
    ) {
    }
}
