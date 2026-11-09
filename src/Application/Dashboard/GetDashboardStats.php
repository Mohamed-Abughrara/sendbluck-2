<?php
declare(strict_types=1);

namespace MailDesk\Application\Dashboard;

use MailDesk\Domain\Configuration\MailConfigurationRepositoryInterface;
use MailDesk\Domain\Contact\ContactRepositoryInterface;
use MailDesk\Domain\Message\{MessageRepositoryInterface, MessageStatus};

final class GetDashboardStats
{
    public function __construct(
        private ContactRepositoryInterface $contacts,
        private MessageRepositoryInterface $messages,
        private MailConfigurationRepositoryInterface $configs,
    ) {
    }

    public function execute(): DashboardStats
    {
        $config = $this->configs->getActive();
        return new DashboardStats(
            $this->contacts->count(),
            $this->messages->countByStatus(MessageStatus::Sent),
            $this->messages->countByStatus(MessageStatus::Failed),
            $this->messages->lastSentAt(),
            $this->status($config),
            $config?->lastTestedAt,
            $this->messages->recent(10),
        );
    }

    /** Lightweight status used by the global top bar. */
    public function configStatus(): string
    {
        return $this->status($this->configs->getActive());
    }

    private function status(?\MailDesk\Domain\Configuration\MailConfiguration $c): string
    {
        return $c === null ? 'none' : ($c->isVerified() ? 'verified' : 'unverified');
    }
}
