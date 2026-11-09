<?php
declare(strict_types=1);

namespace MailDesk\Application\Dashboard;

use MailDesk\Domain\Message\Message;

final readonly class DashboardStats
{
    /** @param list<Message> $recent */
    public function __construct(
        public int $contacts,
        public int $sent,
        public int $failed,
        public ?string $lastSent,
        public string $configStatus, // none | unverified | verified
        public ?string $lastTested,
        public array $recent,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contacts' => $this->contacts,
            'sent' => $this->sent,
            'failed' => $this->failed,
            'last_sent' => $this->lastSent,
            'config_status' => $this->configStatus,
        ];
    }
}
