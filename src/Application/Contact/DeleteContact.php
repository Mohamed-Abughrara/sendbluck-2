<?php
declare(strict_types=1);

namespace MailDesk\Application\Contact;

use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface};
use MailDesk\Domain\Contact\ContactRepositoryInterface;
use MailDesk\Domain\Exception\NotFound;

final class DeleteContact
{
    public function __construct(
        private ContactRepositoryInterface $contacts,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
    ) {
    }

    /** @throws NotFound */
    public function execute(int $id, int $userId): void
    {
        if (!$this->contacts->delete($id, $this->clock->now()->format('Y-m-d H:i:s'))) {
            throw new NotFound('Contact');
        }
        $this->audit->log($userId, 'contact.deleted', ['contact_id' => $id]);
    }
}
