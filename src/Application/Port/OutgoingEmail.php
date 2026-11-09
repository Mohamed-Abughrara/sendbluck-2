<?php
declare(strict_types=1);

namespace MailDesk\Application\Port;

final readonly class OutgoingEmail
{
    /**
     * @param list<array{name:string,email:string}> $to
     * @param list<array{name:string,email:string}> $cc
     */
    public function __construct(
        public array $to,
        public array $cc,
        public string $subject,
        public string $bodyHtml,
        public string $bodyText,
    ) {
    }
}
