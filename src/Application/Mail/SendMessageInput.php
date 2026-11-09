<?php
declare(strict_types=1);

namespace MailDesk\Application\Mail;

final readonly class SendMessageInput
{
    /**
     * @param list<int> $toContactIds
     * @param list<int> $ccContactIds
     */
    public function __construct(
        public int $userId,
        public array $toContactIds,
        public array $ccContactIds,
        public string $subject,
        public string $bodyHtml,
    ) {
    }
}
