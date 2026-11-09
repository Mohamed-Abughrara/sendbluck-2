<?php
declare(strict_types=1);

namespace MailDesk\Application\Contact;

use MailDesk\Application\Port\{AuditLoggerInterface, ClockInterface};
use MailDesk\Domain\Contact\ContactRepositoryInterface;
use MailDesk\Domain\Exception\{NotFound, ValidationFailed};

final class UpdateContact
{
    public function __construct(
        private ContactRepositoryInterface $contacts,
        private AuditLoggerInterface $audit,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $id, ContactInput $in, int $userId): void
    {
        if ($this->contacts->findById($id) === null) {
            throw new NotFound('Contact');
        }
        $contact = ContactFactory::build($id, $in);
        if ($this->contacts->emailExists($contact->email, $id)) {
            throw new ValidationFailed(['email' => 'A contact with this email already exists.']);
        }
        $this->contacts->update($contact, $this->clock->now()->format('Y-m-d H:i:s'));
        $this->audit->log($userId, 'contact.updated', ['contact_id' => $id]);
    }
}
