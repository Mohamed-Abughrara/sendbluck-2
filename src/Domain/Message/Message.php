<?php
declare(strict_types=1);

namespace MailDesk\Domain\Message;

final class Message
{
    /**
     * @param list<array{name:string,email:string}> $to
     * @param list<array{name:string,email:string}> $cc
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly array $to,
        public readonly array $cc,
        public readonly string $subject,
        public readonly string $bodyHtml,
        public readonly MessageStatus $status,
        public readonly ?string $error,
        public readonly \DateTimeImmutable $sentAt,
        public readonly bool $retried = false,
    ) {
    }

    public function recipientCount(): int
    {
        return count($this->to) + count($this->cc);
    }
}
